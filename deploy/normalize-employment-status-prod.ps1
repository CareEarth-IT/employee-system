# Normalize employee_hr_details.employment_status on production (在籍中 -> 在籍).
# Usage:
#   deploy\normalize-employment-status-prod.cmd --dry-run
#   deploy\normalize-employment-status-prod.cmd

param(
    [switch]$DryRun,
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$JobName = "employee-normalize-employment-status"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${Service}:latest"
$AppUrl = "https://employee.careearth.net"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$authProbe = gcloud run services describe $Service --region=$Region --format="value(name)" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "gcloud authentication failed. Run: gcloud auth login"
}

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

if (-not $SkipBuild) {
    Write-Host "==> Cloud Build employee image"
    $buildCode = Invoke-Gcloud builds submit $Root `
        --project=$ProjectId `
        --region=$Region `
        --tag=$Image
    if ($buildCode -ne 0) {
        throw "Cloud Build failed"
    }
} else {
    Write-Host "==> Skip build (--skip-build)"
}

$appKey = Get-LocalAppKey -ProjectRoot $Root
$dbPassword = Get-LocalDbPassword -ProjectRoot $Root
if (-not $dbPassword) {
    $dbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
}
if (-not $dbPassword) {
    throw "DB_PASSWORD is not set in .env and could not be read from Cloud Run"
}

Grant-CloudSqlClient -ProjectId $ProjectId

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

$artisanArgs = @("artisan", "employee:normalize-employment-status")
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

Write-Host "==> Execute job (wait for completion)"
$executeCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
if ($executeCode -ne 0) {
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
    Write-Host "Production employment_status normalization completed."
}
