# Restore real-estate-portal DB connection to employee Cloud SQL (no Docker rebuild).
# Usage: deploy\fix-realestate-portal-db.cmd
#
# Use this when /realestate-portal returns 500 after a deploy that pointed DB_SOCKET
# at ce-realestate-inside-2606st (cross-project Cloud SQL permission error).

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "real-estate-portal"
$SqlInstance = "${ProjectId}:${Region}:employee"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "==> Fix $Service Cloud SQL connection (employee instance, real_estate DB)"
Write-Host "    DB_SOCKET=/cloudsql/$SqlInstance"
Write-Host "    DB_USERNAME=real_estate_app"
Write-Host ""

$code = Invoke-Gcloud run services update $Service `
    --project=$ProjectId `
    --region=$Region `
    --set-cloudsql-instances=$SqlInstance `
    --update-env-vars "DB_SOCKET=/cloudsql/$SqlInstance,DB_USERNAME=real_estate_app"

if ($code -ne 0) {
    throw "gcloud run services update failed (exit $code)"
}

$url = Get-CloudRunServiceUrl -Service $Service -Region $Region
Write-Host ""
Write-Host "Done."
if ($url) {
    Write-Host "Health check: $url/up"
}
Write-Host "Portal URL   : https://employee.careearth.net/realestate-portal"
