# Apply specific_skills DB migrations on production Cloud SQL (data preserved).
# Does NOT modify ceemployee.
#
# Usage:
#   deploy\migrate-specific-skills-prod.cmd
#   deploy\migrate-specific-skills-prod.cmd -RebuildImage

param(
    [switch]$RebuildImage
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$PortalService = "specified-skills-portal"
$JobName = "employee-migrate-specific-skills"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${PortalService}:latest"

$Root = Split-Path $PSScriptRoot -Parent
$AppRoot = Join-Path (Split-Path $Root -Parent) "specific_skills"
if (-not (Test-Path $AppRoot)) {
    throw "specific_skills repo not found: $AppRoot"
}

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host ""
Write-Host "=== Migrate specific_skills DB (production) ===" -ForegroundColor Cyan
Write-Host "Target DB: specific_skills only (ceemployee unchanged)"
Write-Host "App root : $AppRoot"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$cfg = Get-DeployConfig
$SqlConnection = "$ProjectId`:$Region`:employee"

if ($RebuildImage) {
    Write-Host "==> Cloud Build portal image (includes database/migrate.php)"
    $buildCode = Invoke-Gcloud builds submit $AppRoot `
        --project=$ProjectId `
        --region=$Region `
        --tag=$Image
    if ($buildCode -ne 0) {
        throw "Cloud Build failed"
    }
} else {
    Write-Host "==> Using existing image: $Image"
    Write-Host "    (pass -RebuildImage if migrate.php is not in the image yet)"
}

$DbPassword = Get-CloudRunEnvVar -Service $PortalService -Region $Region -Name "DB_PASSWORD"
if (-not $DbPassword) {
    throw "DB_PASSWORD not found on $PortalService"
}

$envVars = @{
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = "specific_skills"
    DB_USERNAME = "specific_skills_app"
    DB_PASSWORD = $DbPassword
    APP_BASE_PATH = "/specified-skills-portal"
}

$argsJoined = "database/migrate.php"
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
Write-Host ""
