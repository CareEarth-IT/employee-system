# Configure employee <-> real-estate portal proxy without cross-project IAM.
# Uses shared EMPLOYEE_PORTAL_PROXY_SECRET and --no-invoker-iam-check on real-estate Cloud Run.
#
# Usage:
#   deploy\setup-realestate-proxy.cmd -ProxySecret "共有秘密鍵"
#   deploy\setup-realestate-proxy.cmd -GenerateSecret

param(
    [string]$ProxySecret = "",
    [switch]$GenerateSecret,
    [string]$RealEstateProjectId = "ce-gr-employee-info-2606st",
    [string]$RealEstateService = "real-estate-portal",
    [string]$Region = "asia-northeast1"
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot\deploy-common.ps1"

if ($GenerateSecret -and $ProxySecret -eq "") {
    $ProxySecret = [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
}

if ($ProxySecret -eq "") {
    throw "ProxySecret is required. Pass -ProxySecret or -GenerateSecret."
}

$cfg = Get-DeployConfig

Write-Host ""
Write-Host "=== Real-estate portal proxy (no cross-project IAM) ===" -ForegroundColor Cyan
Write-Host "Employee service : $($cfg.Service) @ $($cfg.ProjectId)"
Write-Host "Real-estate service: $RealEstateService @ $RealEstateProjectId"
Write-Host ""

Write-Host "Updating employee Cloud Run env ..."
$code = Invoke-Gcloud run services update $cfg.Service `
    --project=$($cfg.ProjectId) `
    --region=$Region `
    --update-env-vars "EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret,REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN=false"

if ($code -ne 0) {
    throw "Failed to update employee Cloud Run (exit $code)"
}

Write-Host "Updating real-estate Cloud Run env ..."
$realEstateArgs = @(
    "run", "services", "update", $RealEstateService,
    "--project", $RealEstateProjectId,
    "--region", $Region,
    "--update-env-vars", "EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret,PORTAL_REFERRER_ENFORCED=true,PORTAL_REFERRER_HOSTS=employee.careearth.net"
)

if ($RealEstateProjectId -eq "ce-realestate-inside-2606st") {
    Write-Host "(legacy project: also trying --no-invoker-iam-check; skip if IAM denied)"
    $realEstateArgs += "--no-invoker-iam-check"
}

$code = Invoke-Gcloud @realEstateArgs

if ($code -ne 0) {
    throw "Failed to update real-estate Cloud Run (exit $code)"
}

Write-Host ""
Write-Host "Done. Save this secret in .env on both apps for local dev:" -ForegroundColor Green
Write-Host "  EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret"
Write-Host ""
Write-Host "If real-estate is not yet in the employee project, run: deploy\deploy-realestate-portal.cmd"
