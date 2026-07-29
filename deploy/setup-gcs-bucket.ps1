# Create GCS bucket for profile photos (PowerShell)
# Usage: deploy\setup-gcs-bucket.cmd

$ErrorActionPreference = "Stop"
. "$PSScriptRoot\deploy-common.ps1"

$cfg = Get-DeployConfig
$bucket = "$($cfg.ProjectId)-employee-photos"

Write-Host "Project: $($cfg.ProjectId)"
Write-Host "Bucket:  $bucket"
Write-Host "Region:  $($cfg.Region)"

$code = Invoke-Gcloud config set project $cfg.ProjectId
if ($code -ne 0) {
    throw "gcloud config failed"
}

Write-Host "Enabling Cloud Storage API..."
$code = Invoke-Gcloud services enable storage.googleapis.com
if ($code -ne 0) {
    throw "Failed to enable storage.googleapis.com"
}

$code = Invoke-Gcloud storage buckets describe "gs://$bucket" --format="value(name)"
if ($code -eq 0) {
    Write-Host "Bucket already exists: gs://$bucket"
} else {
    Write-Host "Creating bucket gs://$bucket ..."
    $code = Invoke-Gcloud storage buckets create "gs://$bucket" `
        --location=$cfg.Region `
        --uniform-bucket-level-access
    if ($code -ne 0) {
        throw "Failed to create bucket"
    }
    Write-Host "Bucket created: gs://$bucket"
}

Grant-GcsObjectAdmin -ProjectId $cfg.ProjectId

Write-Host ""
Write-Host "GCS setup finished."
Write-Host "Cloud Run env (set automatically on deploy):"
Write-Host "  FILESYSTEM_PROFILE_PHOTOS_DISK=gcs"
Write-Host "  GOOGLE_CLOUD_PROJECT_ID=$($cfg.ProjectId)"
Write-Host "  GCS_BUCKET=$bucket"
