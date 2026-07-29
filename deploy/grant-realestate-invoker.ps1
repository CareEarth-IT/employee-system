# Grant employee Cloud Run service account invoker access on real-estate (no public access)
# Usage: deploy\grant-realestate-invoker.cmd
#
# Requires Project IAM Admin on ce-realestate-inside-2606st (or Run Admin on the service).

$ErrorActionPreference = "Stop"

$EmployeeProjectId = "ce-gr-employee-info-2606st"
$RealEstateProjectId = "ce-realestate-inside-2606st"
$Region = "asia-northeast1"
$Service = "real-estate"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$employeeNumber = & gcloud projects describe $EmployeeProjectId --format="value(projectNumber)" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "Failed to read employee project number"
}

$member = "serviceAccount:${employeeNumber}-compute@developer.gserviceaccount.com"

Write-Host "Employee SA : $member"
Write-Host "Target      : $RealEstateProjectId / $Service"
Write-Host ""

$code = Invoke-Gcloud run services add-iam-policy-binding $Service `
    --project=$RealEstateProjectId `
    --region=$Region `
    --member=$member `
    --role=roles/run.invoker

if ($code -ne 0) {
    Write-Host ""
    Write-Host "==> GCP admin: run the following in ce-realestate-inside-2606st"
    Write-Host "    (needs run.services.setIamPolicy — e.g. roles/run.admin or Project IAM Admin)"
    Write-Host ""
    Write-Host "gcloud run services add-iam-policy-binding $Service ``"
    Write-Host "  --project=$RealEstateProjectId ``"
    Write-Host "  --region=$Region ``"
    Write-Host "  --member=$member ``"
    Write-Host "  --role=roles/run.invoker"
    Write-Host ""
    throw "Failed to grant run.invoker. Ask a GCP admin to run the command above."
}

Write-Host ""
Write-Host "Granted run.invoker to employee service account."
Write-Host "real-estate stays private; users access via employee.careearth.net/realestate-portal"
