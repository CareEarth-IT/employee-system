# Map realestate.careearth.net to Cloud Run in ce-realestate-inside-2606st
# Usage: deploy\map-realestate-domain.cmd
#
# Access model (no public invoker):
#   Users -> employee.careearth.net/realestate-portal -> real-estate (private IAM)
#   Run: deploy\grant-realestate-invoker.cmd

$ErrorActionPreference = "Stop"

$ProjectId = "ce-realestate-inside-2606st"
$Region = "asia-northeast1"
$Service = "real-estate"
$Domain = "realestate.careearth.net"
$DnsProjectId = "ce-gr-dns-set-2606st"
$DnsZone = "careearth-net-zone"

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
    Write-Host "Cloud Run service '$Service' not found in $ProjectId."
    Invoke-Gcloud run services list --region=$Region --format="table(name,status.url)"
    throw "Update `$Service in map-realestate-domain.ps1 if the name differs, then re-run."
}

Write-Host "Cloud Run URL: $(Invoke-Gcloud run services describe $Service --region=$Region --format='value(status.url)')"
Write-Host ""

$previous = $ErrorActionPreference
$ErrorActionPreference = "Continue"
$dnsRecord = & gcloud dns record-sets list `
    --project=$DnsProjectId `
    --zone=$DnsZone `
    --name="$Domain." `
    --format="value(name)" 2>$null
$ErrorActionPreference = $previous

if (-not $dnsRecord) {
    Write-Host "WARN: DNS record for $Domain not found. Run: deploy\add-realestate-dns.cmd"
    Write-Host ""
}

Write-Host "==> Custom domain mapping (optional; primary access is employee proxy)"
Write-Host "    Console: Cloud Run -> $Service -> Integrations -> Custom domains"
Write-Host "    Domain : $Domain"
Write-Host ""

Write-Host "==> Required: private access via employee proxy"
Write-Host "    1. deploy\grant-realestate-invoker.cmd"
Write-Host "    2. Redeploy employee (proxy routes)"
Write-Host "    3. Redeploy real-estate with APP_URL=https://employee.careearth.net/realestate-portal"
Write-Host ""
Write-Host "Users open: https://employee.careearth.net/realestate-portal/applications/create"
