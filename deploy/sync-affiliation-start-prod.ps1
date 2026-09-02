# Align affiliation start_date with profile joined_at via Cloud Run Job.
# Does NOT change department, company, position, or other fields.
#
# Usage:
#   deploy\sync-affiliation-start-prod.cmd --dry-run
#   deploy\sync-affiliation-start-prod.cmd
#
# Deploy application code first: deploy\docker-deploy.cmd

param(
    [switch]$DryRun,
    [switch]$SkipBuild,
    [switch]$WithServiceDeploy
)

$SkipServiceDeploy = -not $WithServiceDeploy

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$ArRepo = "employee"
$AppUrl = "https://employee.careearth.net"
$JobName = "employee-sync-affiliation-start"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host "Image   : $Image"
Write-Host "Job     : $JobName"
Write-Host ""
Write-Host "WARNING: Updates affiliation start_date only (from joined_at)."
Write-Host "         Department, company, position, and import_locked are NOT changed."
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

if (-not $SkipBuild) {
    Write-Host "==> Configure Docker for Artifact Registry"
    if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
        throw "docker auth configure failed"
    }

    Clear-DeployCsvStaging -ProjectRoot $Root
    Write-Host "==> Docker build"
    docker build -t $Image .
    if ($LASTEXITCODE -ne 0) { throw "docker build failed" }

    Write-Host "==> Docker push"
    docker push $Image
    if ($LASTEXITCODE -ne 0) { throw "docker push failed" }
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
    throw "DB_PASSWORD is not set in .env and could not be read from Cloud Run"
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

$artisanArgs = @("artisan", "employee:sync-affiliation-start")
if ($DryRun) {
    $artisanArgs += "--dry-run"
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

Write-Host "==> Execute sync job (wait for completion)"
$executeCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
if ($executeCode -ne 0) {
    Write-Host ""
    Write-Host "Sync job failed. Recent logs:"
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
    Write-Host "Production affiliation start_date sync completed."
}
