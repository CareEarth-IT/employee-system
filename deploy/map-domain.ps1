# Map employee.careearth.net to Cloud Run service "employee"
# Usage: deploy\map-domain.cmd
#
# Prerequisites:
#   1. Cloud Run service "employee" is deployed
#   2. DNS admin access for careearth.net

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$Domain = "employee.careearth.net"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "Project : $ProjectId"
Write-Host "Service : $Service"
Write-Host "Domain  : $Domain"
Write-Host ""

$serviceCode = Invoke-Gcloud run services describe $Service --region=$Region --format="value(status.url)"
if ($serviceCode -ne 0) {
    throw "Cloud Run service '$Service' not found. Run deploy\docker-deploy.cmd first."
}

Write-Host "==> Step 1: Create domain mapping in Cloud Run"
Write-Host "    (GCP Console is easiest if gcloud beta is unavailable)"
Write-Host ""
Write-Host "    Console: Cloud Run -> $Service -> Integrations -> Custom domains -> Add mapping"
Write-Host "    Domain : $Domain"
Write-Host ""

$createCode = Invoke-Gcloud beta run domain-mappings create `
    --service=$Service `
    --domain=$Domain `
    --region=$Region 2>&1

if ($LASTEXITCODE -ne 0) {
    Write-Host "gcloud beta mapping skipped (install beta or use Console above)."
    Write-Host ""
} else {
    Write-Host "Domain mapping created."
    Write-Host ""
}

Write-Host "==> Step 2: Add DNS record at careearth.net"
Write-Host ""
Write-Host "    Type : CNAME"
Write-Host "    Name : employee"
Write-Host "    Value: ghs.googlehosted.com"
Write-Host ""
Write-Host "    (If your DNS provider uses full names, host = employee.careearth.net)"
Write-Host ""

Write-Host "==> Step 3: Wait for SSL (usually 15 min - 24 hours after DNS propagates)"
Write-Host ""

Write-Host "==> Step 4: Set APP_URL and redeploy"
Write-Host "    deploy\deploy-only.cmd"
Write-Host ""
Write-Host "Then open: https://$Domain"
