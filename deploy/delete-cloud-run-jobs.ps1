# Delete one-off Cloud Run Jobs (keeps employee service running)
# Usage: deploy\delete-cloud-run-jobs.cmd
#
# Safe to delete after sync/import work is complete.
# Jobs are recreated automatically when you run deploy\sync-*.cmd again.

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"

$Jobs = @(
    "employee-import",
    "employee-sync-affiliation-company",
    "employee-sync-affiliation-position",
    "employee-sync-affiliation-start",
    "employee-sync-company-phone",
    "employee-sync-hr-detail",
    "employee-sync-hr-detail-org-primary",
    "employee-sync-joined-at"
)

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$authProbe = gcloud run services describe employee --project=$ProjectId --region=$Region --format="value(name)" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw @"
gcloud authentication expired.

Run in a terminal:
  gcloud auth login

Then run: deploy\delete-cloud-run-jobs.cmd
"@
}

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "Project : $ProjectId"
Write-Host "Region  : $Region"
Write-Host "NOTE    : employee service is NOT deleted"
Write-Host ""

foreach ($job in $Jobs) {
    Write-Host "==> Delete job: $job"
    $code = Invoke-Gcloud run jobs delete $job --project=$ProjectId --region=$Region --quiet
    if ($code -ne 0) {
        Write-Host "    (skipped or not found)"
    }
}

Write-Host ""
Write-Host "Remaining jobs:"
Invoke-Gcloud run jobs list --project=$ProjectId --region=$Region --format="table(name)"

Write-Host ""
Write-Host "Done. employee service is unchanged."
