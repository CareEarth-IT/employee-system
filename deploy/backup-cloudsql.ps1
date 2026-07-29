# Export Cloud SQL (ce-gr-employee-info-2606st) to GCS for backup
# Usage:
#   deploy\backup-cloudsql.cmd
#   deploy\backup-cloudsql.cmd -Database ceemployee

param(
    [string]$Database = "ceemployee",
    [string]$Instance = "employee",
    [string]$Bucket = "ce-gr-employee-info-2606st-sql-backups"
)

$ErrorActionPreference = "Stop"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$objectPath = "cloudsql/$Database/$Database`_$timestamp.sql"
$uri = "gs://$Bucket/$objectPath"

Write-Host "Project  : $ProjectId"
Write-Host "Instance : $Instance"
Write-Host "Database : $Database"
Write-Host "Export to: $uri"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$sqlServiceEmail = & gcloud sql instances describe $Instance `
    --project=$ProjectId `
    --format="value(serviceAccountEmailAddress)" 2>&1
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sqlServiceEmail)) {
    throw "Failed to read Cloud SQL service account for instance '$Instance'"
}

$sqlServiceAgent = "serviceAccount:$sqlServiceEmail"
Write-Host "Cloud SQL SA: $sqlServiceEmail"

Write-Host "==> Ensure backup bucket exists: gs://$Bucket"
$bucketCheck = Invoke-Gcloud storage buckets describe "gs://$Bucket" 2>&1
if ($LASTEXITCODE -ne 0) {
    $code = Invoke-Gcloud storage buckets create "gs://$Bucket" `
        --project=$ProjectId `
        --location=$Region `
        --uniform-bucket-level-access
    if ($code -ne 0) {
        throw "Failed to create bucket gs://$Bucket"
    }
}

Write-Host "==> Grant Cloud SQL service agent write access to bucket"
$code = Invoke-Gcloud storage buckets add-iam-policy-binding "gs://$Bucket" `
    --member=$sqlServiceAgent `
    --role=roles/storage.objectAdmin
if ($code -ne 0) {
    Write-Host "WARN: Could not grant bucket IAM automatically."
    Write-Host "In GCP Console -> Cloud Storage -> $Bucket -> Permissions:"
    Write-Host "  Principal: $sqlServiceEmail"
    Write-Host "  Role: Storage Object Admin"
    Write-Host ""
}

Write-Host "==> Export Cloud SQL database"
$code = Invoke-Gcloud sql export sql $Instance $uri `
    --project=$ProjectId `
    --database=$Database
if ($code -ne 0) {
    throw "Cloud SQL export failed"
}

Write-Host ""
Write-Host "Backup completed."
Write-Host "  URI: $uri"
Write-Host ""
Write-Host "List backups:"
Write-Host "  gcloud storage ls gs://$Bucket/cloudsql/$Database/"
Write-Host ""
Write-Host "Download to local:"
Write-Host "  gcloud storage cp $uri database/backups/"
