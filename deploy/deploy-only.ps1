# Deploy already-built image to Cloud Run (no docker build)
$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${Service}:latest"
$AppUrl = "https://employee.careearth.net"
$Root = Split-Path $PSScriptRoot -Parent

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

$appKey = Get-LocalAppKey -ProjectRoot $Root

Write-Host "==> Deploy to Cloud Run"
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

$serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
$revision = & gcloud run services describe $Service --region=$Region --format="value(status.latestReadyRevisionName)" 2>&1

Write-Host ""
Write-Host "Done."
Write-Host "Service URL: $serviceUrl"
Write-Host "Revision   : $revision"
