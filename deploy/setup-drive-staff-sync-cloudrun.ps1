# Set drive staff sync env vars on employee Cloud Run.
# gas-app 側は scripts/setup-employee-site-sync-cloudrun.ps1 で同じ SyncSecret を設定済みの想定。
#
# Usage:
#   deploy\setup-drive-staff-sync-cloudrun.cmd -SyncSecret "共有秘密鍵"

param(
    [string]$DriveApiUrl = "https://gas-app-231655548437.asia-northeast1.run.app",
    [Parameter(Mandatory = $true)]
    [string]$SyncSecret
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot\deploy-common.ps1"

$cfg = Get-DeployConfig

Write-Host ""
Write-Host "=== Drive staff sync -> Employee Cloud Run ===" -ForegroundColor Cyan
Write-Host "Service : $($cfg.Service)"
Write-Host "Drive API: $DriveApiUrl"
Write-Host ""

$code = Invoke-Gcloud run services update $cfg.Service `
    --project=$($cfg.ProjectId) `
    --region=$($cfg.Region) `
    --update-env-vars "DRIVE_APP_API_URL=$DriveApiUrl,EMPLOYEE_SITE_SYNC_SECRET=$SyncSecret"

if ($code -ne 0) {
    throw "gcloud run services update failed (exit $code)"
}

Write-Host ""
Write-Host "Done. employee posts staff profiles to gas-app on sync." -ForegroundColor Green
