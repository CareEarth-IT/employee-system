# Create Cloud SQL database/user and grant Cloud Run access
# Usage: deploy\setup-cloudsql.cmd
# Prerequisite: set DB_PASSWORD in .env (app DB user password)

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$SqlInstance = "employee"
$DbName = "ceemployee"
$DbUser = "ceemployee"
$Root = Split-Path $PSScriptRoot -Parent

. (Join-Path $PSScriptRoot "deploy-common.ps1")

function Wait-CloudSqlInstance {
    param([string]$Instance)

    Write-Host "Waiting for Cloud SQL instance '$Instance' to become RUNNABLE ..."
    for ($i = 1; $i -le 60; $i++) {
        $status = gcloud sql instances describe $Instance --format="value(state)" 2>$null
        if ($status -eq "RUNNABLE") {
            Write-Host "Instance is RUNNABLE."
            return
        }
        Write-Host "  state=$status (${i}/60, retry in 30s)"
        Start-Sleep -Seconds 30
    }
    throw "Cloud SQL instance '$Instance' did not become RUNNABLE in time"
}

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

$dbPassword = Get-LocalDbPassword -ProjectRoot $Root
if (-not $dbPassword) {
    throw @"
DB_PASSWORD is not set in .env

1. Open .env and set a strong password, e.g.:
   DB_PASSWORD=your-secure-password-here

2. Run deploy\setup-cloudsql.cmd again
"@
}

Wait-CloudSqlInstance -Instance $SqlInstance

Write-Host "==> Create database: $DbName"
Invoke-Gcloud sql databases create $DbName --instance=$SqlInstance 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Database may already exist (continuing)"
}

Write-Host "==> Create user: $DbUser"
Invoke-Gcloud sql users create $DbUser `
    --instance=$SqlInstance `
    --password=$dbPassword 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "User may already exist; updating password"
    if ((Invoke-Gcloud sql users set-password $DbUser --instance=$SqlInstance --password=$dbPassword) -ne 0) {
        throw "failed to create or update SQL user"
    }
}

Write-Host "==> Grant Cloud SQL Client to Cloud Run service account"
Grant-CloudSqlClient -ProjectId $ProjectId

Write-Host ""
Write-Host "Done."
Write-Host "  Instance : $SqlInstance"
Write-Host "  Database : $DbName"
Write-Host "  User     : $DbUser"
Write-Host "  Password : (from .env DB_PASSWORD)"
Write-Host ""
Write-Host "Next: deploy\deploy-only.cmd  (or deploy\docker-deploy.cmd)"
