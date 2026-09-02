# Sync affiliation company from roster CSV via Cloud Run Job.
# Does NOT change department, start_date, position, or other fields.
# Skips affiliations with start_date=2026-07-01 (manual department corrections).
#
# Usage:
#   deploy\sync-affiliation-company-prod.cmd --dry-run
#   deploy\sync-affiliation-company-prod.cmd
#
# Place roster CSV at database/imports/employee-roster.csv before running.

param(
    [string]$File = "database/imports/employee-roster.csv",
    [switch]$DryRun,
    [switch]$SkipBuild,
    [switch]$WithServiceDeploy,
    [switch]$MatchEmailOnly
)

$SkipServiceDeploy = -not $WithServiceDeploy

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$ArRepo = "employee"
$AppUrl = "https://employee.careearth.net"
$JobName = "employee-sync-affiliation-company"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$resolvedCsv = if ([System.IO.Path]::IsPathRooted($File)) { $File } else { Join-Path $Root $File }

if (-not (Test-Path $resolvedCsv)) {
    throw @"
Roster CSV not found: $resolvedCsv

Copy your roster CSV to:
  database\imports\employee-roster.csv
"@
}

Write-Host "CSV     : $resolvedCsv"
Write-Host "Image   : $Image"
Write-Host "Job     : $JobName"
Write-Host ""
Write-Host "WARNING: Updates affiliation company only (from roster CSV 所属 code)."
Write-Host "         CE->CareEarth, CEVN->Care EarthVietnam, GT->GROWTEC, EM->Earth Management, ME/MD->MidEarth"
Write-Host "         Department, start_date, position are NOT changed."
Write-Host "         start_date=2026-07-01 affiliations are skipped."
Write-Host ""

$containerCsvPath = "database/imports/.deploy-staging/employee-roster.csv"

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
        Stage-DeployCsv -ProjectRoot $Root -SourceFile $resolvedCsv -StagingFileName "employee-roster.csv" | Out-Null

        Write-Host "==> Docker build (roster CSV staged for this job only)"
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
    throw "DB_PASSWORD is not set in .env"
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

$artisanArgs = @("artisan", "employee:sync-affiliation-company", $containerCsvPath)
if ($DryRun) {
    $artisanArgs += "--dry-run"
}
if ($MatchEmailOnly) {
    $artisanArgs += "--match-email-only"
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
    Write-Host "Production affiliation company sync completed."
}
