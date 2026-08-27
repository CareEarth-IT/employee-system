# Sync 支援管理 CSV to production specific_skills DB (add missing only).
#
# Usage:
#   deploy\sync-support-csv-prod.cmd
#   deploy\sync-support-csv-prod.cmd -CsvPath "C:\path\to\支援管理.csv"
#   deploy\sync-support-csv-prod.cmd -Apply
#   deploy\sync-support-csv-prod.cmd -SkipBuild

param(
    [string]$CsvPath = "",
    [switch]$Apply,
    [switch]$PromoteMissing,
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$PortalService = "specified-skills-portal"
$JobName = "employee-sync-support-csv"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${PortalService}:latest"
$Bucket = "ce-gr-employee-info-2606st-sql-backups"
$SqlConnection = "$ProjectId`:$Region`:employee"

$Root = Split-Path $PSScriptRoot -Parent
$AppRoot = Join-Path (Split-Path $Root -Parent) "specific_skills"
if (-not (Test-Path $AppRoot)) {
    throw "specific_skills repo not found: $AppRoot"
}

if ($CsvPath -eq "") {
    $CsvPath = Join-Path $env:USERPROFILE "Downloads\支援管理.csv"
}
if (-not (Test-Path $CsvPath)) {
    throw "CSV not found: $CsvPath"
}

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host ""
Write-Host "=== Sync 支援管理 CSV (production specific_skills) ===" -ForegroundColor Cyan
Write-Host "Target DB : specific_skills only (existing rows unchanged)"
Write-Host "CSV       : $CsvPath"
Write-Host "Mode      : $(if ($Apply) { if ($PromoteMissing) { 'apply + promote-missing (status only)' } else { 'apply (insert missing)' } } else { 'dry-run' })"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

if (-not $SkipBuild) {
    Write-Host "==> Docker build portal image"
    if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
        throw "docker auth configure failed"
    }

    Push-Location $AppRoot
    try {
        docker build -t $Image .
        if ($LASTEXITCODE -ne 0) { throw "docker build failed" }

        docker push $Image
        if ($LASTEXITCODE -ne 0) { throw "docker push failed" }
    } finally {
        Pop-Location
    }
} else {
    Write-Host "==> Using existing image: $Image"
}

$DbPassword = Get-CloudRunEnvVar -Service $PortalService -Region $Region -Name "DB_PASSWORD"
if (-not $DbPassword) {
    throw "DB_PASSWORD not found on $PortalService"
}

$timestamp = Get-Date -Format "yyyyMMddHHmmss"
$objectName = "support-sync/support_management_$timestamp.csv"
$mountObject = "support-sync/support.csv"
$gcsUri = "gs://$Bucket/$objectName"
$mountUri = "gs://$Bucket/$mountObject"

Write-Host "==> Upload CSV to $gcsUri"
$uploadCode = Invoke-Gcloud storage cp $CsvPath $gcsUri
if ($uploadCode -ne 0) {
    throw "CSV upload failed"
}

Write-Host "==> Stage CSV for volume mount: $mountUri"
$stageCode = Invoke-Gcloud storage cp $gcsUri $mountUri
if ($stageCode -ne 0) {
    throw "Failed to stage CSV for Cloud Run volume mount"
}

$scriptArgs = @('database/sync_support_from_csv.php', "/csv/$($mountObject -replace '\\','/')")
if ($Apply) {
    $scriptArgs += '--apply'
}
if ($PromoteMissing) {
    $scriptArgs += '--promote-missing'
}
if (-not $Apply) {
    $scriptArgs += '--dry-run'
}
$argsJoined = ($scriptArgs -join ',')

$envVars = @{
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = "specific_skills"
    DB_USERNAME = "specific_skills_app"
    DB_PASSWORD = $DbPassword
    APP_BASE_PATH = "/specified-skills-portal"
}

$envFile = [System.IO.Path]::GetTempFileName()

try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    $volumeSpec = "name=sync-csv,type=cloud-storage,bucket=$Bucket,readonly=true"
    $volumeMountSpec = "volume=sync-csv,mount-path=/csv"

    Write-Host "==> Deploy job: $JobName"
    $jobCode = Invoke-Gcloud run jobs deploy $JobName `
        --image=$Image `
        --region=$Region `
        --set-cloudsql-instances=$SqlConnection `
        --env-vars-file=$envFile `
        --command=php `
        "--args=$argsJoined" `
        "--add-volume=$volumeSpec" `
        "--add-volume-mount=$volumeMountSpec" `
        --max-retries=0 `
        --task-timeout=10m `
        --memory=512Mi `
        --cpu=1

    if ($jobCode -ne 0) { throw "job deploy failed" }

    Write-Host "==> Execute job"
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
if (-not $Apply) {
    Write-Host "Dry-run finished. To apply changes:" -ForegroundColor Yellow
    Write-Host "  deploy\sync-support-csv-prod.cmd -Apply -PromoteMissing"
} else {
    Write-Host "Done. Existing field values were not modified; only missing rows and/or status promotions were applied." -ForegroundColor Green
}
Write-Host ""
