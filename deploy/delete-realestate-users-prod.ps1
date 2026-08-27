# Delete real-estate portal users (careearth_users) from production Cloud SQL.
# Does NOT modify ceemployee database contents.
#
# Usage:
#   deploy\delete-realestate-users-prod.cmd yuta_masui@careearth.info

param(
    [Parameter(Mandatory = $true, ValueFromRemainingArguments = $true)]
    [string[]]$Emails
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$PortalService = "real-estate-portal"
$JobName = "employee-delete-realestate-users"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${PortalService}:latest"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$normalized = @($Emails | ForEach-Object { $_.Trim().ToLower() } | Where-Object { $_ -ne "" } | Select-Object -Unique)
if ($normalized.Count -eq 0) {
    throw "Specify at least one email address."
}

Write-Host ""
Write-Host "=== Delete real_estate portal users (production) ===" -ForegroundColor Cyan
Write-Host "Target DB: real_estate only (ceemployee unchanged)"
Write-Host "Emails:"
$normalized | ForEach-Object { Write-Host "  - $_" }
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$SqlConnection = "$ProjectId`:$Region`:employee"
$DbPassword = Get-CloudRunEnvVar -Service $PortalService -Region $Region -Name "DB_PASSWORD"
if (-not $DbPassword) {
    throw "DB_PASSWORD not found on $PortalService"
}

$scriptPath = Join-Path $PSScriptRoot "scripts\delete-realestate-users.php"
if (-not (Test-Path $scriptPath)) {
    throw "Delete script not found: $scriptPath"
}

$scriptBody = Get-Content $scriptPath -Raw
$scriptB64 = [Convert]::ToBase64String(
    [System.Text.Encoding]::UTF8.GetBytes($scriptBody)
)

$envVars = @{
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = "real_estate"
    DB_USERNAME = "real_estate_app"
    DB_PASSWORD = $DbPassword
    DELETE_EMAILS = ($normalized -join ",")
    DELETE_SCRIPT_B64 = $scriptB64
}

$argsJoined = "-r,eval(base64_decode(getenv('DELETE_SCRIPT_B64')));"

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
        --task-timeout=5m `
        --memory=512Mi `
        --cpu=1

    if ($jobCode -ne 0) { throw "job deploy failed" }

    Write-Host "==> Execute delete job"
    $execCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
    if ($execCode -ne 0) {
        Write-Host ""
        Write-Host "Job failed. Recent logs:"
        Invoke-Gcloud logging read `
            "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
            --limit=30 `
            --format="value(textPayload)" `
            --freshness=1h
        throw "job execution failed"
    }

    Write-Host ""
    Write-Host "==> Job logs"
    Invoke-Gcloud logging read `
        "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
        --limit=30 `
        --format="value(textPayload)" `
        --freshness=1h
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

Write-Host ""
Write-Host "Done."
