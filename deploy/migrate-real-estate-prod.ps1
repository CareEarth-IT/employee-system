# Apply real_estate DB migrations on production Cloud SQL (data preserved).
# Reconciles SQL-import schema drift (e.g. contract_key already exists) then migrates.
#
# Usage:
#   deploy\migrate-real-estate-prod.cmd

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$PortalService = "real-estate-portal"
$JobName = "employee-migrate-real-estate"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${PortalService}:latest"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host ""
Write-Host "=== Migrate real_estate DB (production) ===" -ForegroundColor Cyan
Write-Host "Target DB: real_estate only (ceemployee unchanged)"
Write-Host "Image    : $Image"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$SqlConnection = "$ProjectId`:$Region`:employee"
$DbPassword = Get-CloudRunEnvVar -Service $PortalService -Region $Region -Name "DB_PASSWORD"
if (-not $DbPassword) {
    throw "DB_PASSWORD not found on $PortalService"
}

$AppKey = Get-CloudRunEnvVar -Service $PortalService -Region $Region -Name "APP_KEY"
if (-not $AppKey) {
    throw "APP_KEY not found on $PortalService"
}

$migrateScriptPath = Join-Path $PSScriptRoot "scripts\migrate-real-estate.php"
if (-not (Test-Path $migrateScriptPath)) {
    throw "Migrate script not found: $migrateScriptPath"
}

$migrateScriptBody = Get-Content $migrateScriptPath -Raw
$migrateScriptB64 = [Convert]::ToBase64String(
    [System.Text.Encoding]::UTF8.GetBytes($migrateScriptBody)
)

$envVars = @{
    DB_CONNECTION = "mysql"
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = "real_estate"
    DB_USERNAME = "real_estate_app"
    DB_PASSWORD = $DbPassword
    APP_KEY = $AppKey
    MIGRATE_SCRIPT_B64 = $migrateScriptB64
}

$argsJoined = "-r,eval(base64_decode(getenv('MIGRATE_SCRIPT_B64')));"

$envFile = [System.IO.Path]::GetTempFileName()
try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    Write-Host "==> Deploy job: $JobName"
    $jobCode = Invoke-Gcloud run jobs deploy $JobName `
        --image=$Image `
        --region=$Region `
        --set-cloudsql-instances=$SqlConnection `
        --env-vars-file=$envFile `
        --command=php `
        "--args=$argsJoined" `
        --max-retries=0 `
        --task-timeout=10m `
        --memory=512Mi `
        --cpu=1

    if ($jobCode -ne 0) { throw "job deploy failed" }

    Write-Host "==> Execute migration job"
    $execCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
    if ($execCode -ne 0) {
        Write-Host ""
        Write-Host "Job failed. Recent logs:"
        Invoke-Gcloud logging read `
            "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
            --limit=40 `
            --format="value(textPayload)" `
            --freshness=1h
        throw "job execution failed"
    }

    Write-Host ""
    Write-Host "==> Job logs"
    Invoke-Gcloud logging read `
        "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
        --limit=40 `
        --format="value(textPayload)" `
        --freshness=1h
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

Write-Host ""
Write-Host "Done."
