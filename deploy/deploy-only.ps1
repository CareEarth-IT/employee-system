# Deploy existing image to Cloud Run (skip docker build/push)
# Usage: deploy\deploy-only.cmd
# Mail: defaults to preserving Cloud Run MAIL_* (do not push unverified local Sakura SMTP).
# To push local .env mail intentionally: $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "0"

$ErrorActionPreference = "Stop"

if ($null -eq $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -or $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -eq "") {
    $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "1"
}

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$ArRepo = "employee"
$AppUrl = "https://employee.careearth.net"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"
$Root = Split-Path $PSScriptRoot -Parent

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

$appKey = Get-LocalAppKey -ProjectRoot $Root

Write-Host "==> Deploy to Cloud Run: $Image"
Write-CodeDeployNotice
$deployCode = Invoke-CloudRunDeploy `
    -Service $Service `
    -Image $Image `
    -Region $Region `
    -AppUrl $AppUrl `
    -AppKey $appKey `
    -ProjectRoot $Root

if ($deployCode -ne 0) {
    throw "gcloud run deploy failed"
}

Grant-PublicInvoker -Service $Service -Region $Region

Write-Host ""
Write-Host "Done."
$serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
if ($serviceUrl) {
    Write-Host "Service URL: $serviceUrl"
} else {
    Write-Host "Check URL in Cloud Run console (employee service)."
}
