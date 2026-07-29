# Add realestate.careearth.net DNS record to Cloud DNS (careearth-net-zone)
# Usage: deploy\add-realestate-dns.cmd
#
# DNS project : ce-gr-dns-set-2606st
# Zone        : careearth-net-zone
# Record      : realestate.careearth.net A 216.239.32.21 (same as employee.careearth.net for Cloud Run)

$ErrorActionPreference = "Stop"

$DnsProjectId = "ce-gr-dns-set-2606st"
$DnsZone = "careearth-net-zone"
$RecordName = "realestate.careearth.net."
$RecordType = "A"
$RecordValue = "216.239.32.21"
$RecordTtl = "300"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host "DNS project : $DnsProjectId"
Write-Host "Zone        : $DnsZone"
Write-Host "Record      : $RecordName $RecordType $RecordValue (TTL $RecordTtl)"
Write-Host ""

$previous = $ErrorActionPreference
$ErrorActionPreference = "Continue"
$existing = & gcloud dns record-sets list `
    --project=$DnsProjectId `
    --zone=$DnsZone `
    --name=$RecordName `
    --type=$RecordType `
    --format="value(rrdatas)" 2>$null
$ErrorActionPreference = $previous

if ($existing) {
    Write-Host "DNS record already exists: $RecordName -> $existing"
    Write-Host "No changes made."
    exit 0
}

Write-Host "==> Creating DNS A record ..."
$createCode = Invoke-Gcloud dns record-sets create $RecordName `
    --project=$DnsProjectId `
    --zone=$DnsZone `
    --type=$RecordType `
    --ttl=$RecordTtl `
    --rrdatas=$RecordValue

if ($createCode -ne 0) {
    throw "Failed to create DNS record. Check gcloud auth and DNS permissions."
}

Write-Host ""
Write-Host "DNS record created."
Write-Host ""
Write-Host "Next steps:"
Write-Host "  1. deploy\map-realestate-domain.cmd  (Cloud Run custom domain mapping)"
Write-Host "  2. Wait for SSL (15 min - 24 hours)"
Write-Host "  3. deploy\grant-realestate-invoker.cmd  (employee SA -> real-estate invoker)"
Write-Host "  4. deploy\docker-deploy.cmd  (employee proxy routes)"
Write-Host ""
Write-Host "Users open: https://employee.careearth.net/realestate-portal/applications/create"
Write-Host ""
Write-Host "Verify: gcloud dns record-sets list --zone=$DnsZone --project=$DnsProjectId --filter=name:realestate"
