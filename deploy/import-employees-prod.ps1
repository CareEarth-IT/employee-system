# Build image (with CSV), deploy Cloud Run service, run bulk import via Cloud Run Job
# Usage:
#   deploy\import-employees-prod.cmd
#   deploy\import-employees-prod.cmd --dry-run
#   deploy\import-employees-prod.cmd --skip-build

param(
    [string]$File = "database/imports/employees.csv",
    [switch]$DryRun,
    [string]$Limit = "",
    [switch]$SkipBuild,
    [switch]$SkipServiceDeploy
)

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$ArRepo = "employee"
$AppUrl = "https://employee.careearth.net"
$JobName = "employee-import"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$csvPath = $File -replace '\\', '/'
$resolvedCsv = if ([System.IO.Path]::IsPathRooted($File)) { $File } else { Join-Path $Root $File }

if (-not (Test-Path $resolvedCsv)) {
    throw "CSV not found: $resolvedCsv"
}

Write-Host "WARNING: employee:import-bulk does NOT change existing employees unless --sync-* flags are set."
Write-Host "         Existing records are marked import_locked and protected from overwrite."
Write-Host "         App-edited users / profiles / affiliations stay locked after UI save."
Write-Host "         Use deploy\\sync-joined-at-prod.cmd for joined_at from roster CSV."
Write-Host ""
Write-Host "CSV     : $resolvedCsv"
Write-Host "Image   : $Image"
Write-Host "Job     : $JobName"
Write-Host ""

$authProbe = gcloud run services describe $Service --region=$Region --format="value(name)" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw @"
gcloud authentication expired or Cloud Run is unreachable.

Run in a terminal:
  gcloud auth login

Then run this script again.
"@
}

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

if (-not $SkipBuild) {
    Write-Host "==> Configure Docker for Artifact Registry"
    if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
        throw "docker auth configure failed"
    }

    try {
        Clear-DeployCsvStaging -ProjectRoot $Root
        Stage-DeployCsv -ProjectRoot $Root -SourceFile $resolvedCsv -StagingFileName "employees.csv" | Out-Null

        Write-Host "==> Docker build (employee CSV staged for this job only)"
        docker build -t $Image .
        if ($LASTEXITCODE -ne 0) { throw "docker build failed" }

        Write-Host "==> Docker push"
        docker push $Image
        if ($LASTEXITCODE -ne 0) { throw "docker push failed" }
    } finally {
        Clear-DeployCsvStaging -ProjectRoot $Root
    }
} else {
    Write-Host "==> Skip build/push (--skip-build)"
}

$appKey = Get-LocalAppKey -ProjectRoot $Root
$dbPassword = Get-LocalDbPassword -ProjectRoot $Root
if (-not $dbPassword) {
    $dbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
    if ($dbPassword) {
        Write-Host "DB_PASSWORD: reusing value from existing Cloud Run service"
    }
}
if (-not $dbPassword) {
    throw "DB_PASSWORD is not set in .env (and could not read it from Cloud Run)"
}

Grant-CloudSqlClient -ProjectId $ProjectId

if (-not $SkipServiceDeploy) {
    Write-Host "==> Deploy Cloud Run service"
    $deployCode = Invoke-CloudRunDeploy `
        -Service $Service `
        -Image $Image `
        -Region $Region `
        -AppUrl $AppUrl `
        -AppKey $appKey `
        -ProjectRoot $Root

    if ($deployCode -ne 0) { throw "Cloud Run service deploy failed" }

    Grant-PublicInvoker -Service $Service -Region $Region
} else {
    Write-Host "==> Skip service deploy"
}

$resolvedAppUrl = Resolve-AppUrl -Service $Service -Region $Region -PreferredUrl $AppUrl
$cfg = Get-DeployConfig

$envVars = Get-CloudRunEnvVars `
    -AppUrl $resolvedAppUrl `
    -CloudSqlConnection $cfg.CloudSqlConnection `
    -DbName $cfg.DbName `
    -DbUser $cfg.DbUser `
    -DbPassword $dbPassword `
    -AppKey $appKey `
    -ProjectRoot $Root

$envVars["RUN_MIGRATIONS"] = "false"
$envVars["RUN_SEED"] = "false"

$artisanArgs = @("artisan", "employee:import-bulk", "database/imports/.deploy-staging/employees.csv")
if ($DryRun) {
    $artisanArgs += "--dry-run"
}
if ($Limit -ne "") {
    $artisanArgs += "--limit=$Limit"
}

$argsJoined = ($artisanArgs | ForEach-Object { $_ -replace ',', '\,' }) -join ','

Write-Host "==> Deploy Cloud Run Job: $JobName"
Write-Host "    php $($artisanArgs -join ' ')"

$envFile = [System.IO.Path]::GetTempFileName()
try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    $jobCode = Invoke-Gcloud run jobs deploy $JobName `
        --image=$Image `
        --region=$Region `
        --set-cloudsql-instances=$($cfg.CloudSqlConnection) `
        --env-vars-file=$envFile `
        --command=php `
        --args=$argsJoined `
        --max-retries=0 `
        --task-timeout=30m `
        --memory=512Mi `
        --cpu=1

    if ($jobCode -ne 0) { throw "Cloud Run job deploy failed" }
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

Write-Host "==> Execute import job (wait for completion)"
$executeCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
if ($executeCode -ne 0) {
    Write-Host ""
    Write-Host "Import job failed. Recent logs:"
    Invoke-Gcloud logging read `
        "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
        --limit=80 `
        --format="value(textPayload)" `
        --freshness=1h
    throw "Cloud Run job execution failed"
}

Write-Host ""
Write-Host "==> Job logs"
Invoke-Gcloud logging read `
    "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
    --limit=80 `
    --format="value(textPayload)" `
    --freshness=1h

if ($DryRun) {
    Write-Host ""
    Write-Host "dry-run completed. Production DB was not changed."
} else {
    Write-Host ""
    Write-Host "Production import completed."
    Write-Host "Users can set passwords via the login page (forgot-password)."
}
