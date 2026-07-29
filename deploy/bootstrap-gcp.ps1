# GCP initial setup (PowerShell)
# Usage: deploy\bootstrap-gcp.cmd

$ErrorActionPreference = "Stop"
$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$ArRepo = "employee"

Write-Host "Project: $ProjectId"

gcloud config set project $ProjectId
if ($LASTEXITCODE -ne 0) {
    throw "gcloud config failed"
}

Write-Host "Enabling APIs..."
gcloud services enable `
    run.googleapis.com `
    artifactregistry.googleapis.com `
    sqladmin.googleapis.com `
    servicenetworking.googleapis.com `
    storage.googleapis.com

if ($LASTEXITCODE -ne 0) {
    throw "Failed to enable APIs"
}

Write-Host "Creating Artifact Registry repository (if missing)..."
$repoCheck = gcloud artifacts repositories describe $ArRepo --location=$Region 2>&1
if ($LASTEXITCODE -ne 0) {
    gcloud artifacts repositories create $ArRepo `
        --repository-format=docker `
        --location=$Region `
        --description="CE-GR employee site"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create Artifact Registry repository"
    }
}

Write-Host ""
Write-Host "Bootstrap finished."
Write-Host "Next:"
Write-Host "  1. deploy\setup-cloudsql.cmd"
Write-Host "  2. deploy\setup-gcs-bucket.cmd"
Write-Host "  3. deploy\docker-deploy.cmd"
$projectNumber = gcloud projects describe $ProjectId --format="value(projectNumber)"
Write-Host "IAM (Console): add Cloud SQL Client to ${projectNumber}-compute@developer.gserviceaccount.com"
