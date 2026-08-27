# Build employee image via Cloud Build and deploy to Cloud Run (no DB changes).
# Usage: deploy\deploy-employee-cloudbuild.cmd

$ErrorActionPreference = "Stop"

if ($null -eq $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -or $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -eq "") {
    $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "1"
}

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${Service}:latest"
$Root = Split-Path $PSScriptRoot -Parent

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "==> Cloud Build employee image"
$buildCode = Invoke-Gcloud builds submit $Root `
    --project=$ProjectId `
    --region=$Region `
    --tag=$Image
if ($buildCode -ne 0) {
    throw "Cloud Build failed"
}

$appKey = Get-LocalAppKey -ProjectRoot $Root

Write-Host "==> Deploy to Cloud Run: $Service"
Write-CodeDeployNotice
$deployCode = Invoke-CloudRunDeploy `
    -Service $Service `
    -Image $Image `
    -Region $Region `
    -AppUrl "https://employee.careearth.net" `
    -AppKey $appKey `
    -ProjectRoot $Root

if ($deployCode -ne 0) {
    throw "gcloud run deploy failed"
}

Grant-PublicInvoker -Service $Service -Region $Region

Write-Host ""
Write-Host "Employee deploy complete."
