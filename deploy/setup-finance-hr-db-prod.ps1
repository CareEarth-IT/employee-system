# Create finance_hr database and tables on production Cloud SQL.
#
# Usage:
#   deploy\setup-finance-hr-db-prod.cmd
#   deploy\setup-finance-hr-db-prod.cmd -SkipImport   # DB only, skip schema import

param(
    [switch]$SkipImport
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$SqlInstance = "employee"
$DbName = "finance_hr"
$Bucket = "ce-gr-employee-info-2606st-sql-backups"
$SchemaPath = Join-Path (Split-Path $PSScriptRoot -Parent) "apps\finance-hr\sql\schema-prod-init.sql"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host "Project  : $ProjectId"
Write-Host "Instance : $SqlInstance"
Write-Host "Database : $DbName"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$dbExists = Invoke-Gcloud sql databases describe $DbName --instance=$SqlInstance --project=$ProjectId
if ($dbExists -ne 0) {
    Write-Host "==> Create database: $DbName"
    if ((Invoke-Gcloud sql databases create $DbName --instance=$SqlInstance --project=$ProjectId) -ne 0) {
        throw "Failed to create database $DbName"
    }
} else {
    Write-Host "Database already exists: $DbName"
}

if ($SkipImport) {
    Write-Host ""
    Write-Host "Skip schema import (-SkipImport)."
    exit 0
}

if (-not (Test-Path $SchemaPath)) {
    throw "Schema file not found: $SchemaPath"
}

$sqlServiceEmail = & gcloud sql instances describe $SqlInstance `
    --project=$ProjectId `
    --format="value(serviceAccountEmailAddress)" 2>&1
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sqlServiceEmail)) {
    throw "Failed to read Cloud SQL service account"
}

Write-Host "==> Ensure backup bucket: gs://$Bucket"
$bucketCheck = Invoke-Gcloud storage buckets describe "gs://$Bucket" 2>&1
if ($bucketCheck -ne 0) {
    if ((Invoke-Gcloud storage buckets create "gs://$Bucket" `
            --project=$ProjectId `
            --location=$Region `
            --uniform-bucket-level-access) -ne 0) {
        throw "Failed to create bucket gs://$Bucket"
    }
}

$sqlServiceAgent = "serviceAccount:$sqlServiceEmail"
Write-Host "==> Grant Cloud SQL SA read on bucket"
$iamCode = Invoke-Gcloud storage buckets add-iam-policy-binding "gs://$Bucket" `
    --member=$sqlServiceAgent `
    --role="roles/storage.objectViewer"
if ($iamCode -ne 0) {
    throw "Failed to grant bucket access to Cloud SQL service account"
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$objectPath = "finance_hr/schema-prod-init_$timestamp.sql"
$uri = "gs://$Bucket/$objectPath"

Write-Host "==> Upload schema: $uri"
if ((Invoke-Gcloud storage cp $SchemaPath $uri) -ne 0) {
    throw "Failed to upload schema to GCS"
}

Write-Host "==> Import schema into $DbName (may take 1-2 minutes)"
$importCode = Invoke-Gcloud sql import sql $SqlInstance $uri `
    --database=$DbName `
    --project=$ProjectId
if ($importCode -ne 0) {
    throw "Cloud SQL import failed"
}

$GrantPath = Join-Path (Split-Path $PSScriptRoot -Parent) "apps\finance-hr\sql\grant-ceemployee-finance-hr.sql"
$grantObjectPath = "finance_hr/grant-ceemployee_$timestamp.sql"
$grantUri = "gs://$Bucket/$grantObjectPath"

Write-Host "==> Grant ceemployee user access to $DbName"
if ((Invoke-Gcloud storage cp $GrantPath $grantUri) -ne 0) {
    throw "Failed to upload grant SQL to GCS"
}

$grantCode = Invoke-Gcloud sql import sql $SqlInstance $grantUri `
    --database=mysql `
    --project=$ProjectId
if ($grantCode -ne 0) {
    throw "Cloud SQL grant import failed"
}

Write-Host ""
Write-Host "==> Verify database exists"
$dbList = & gcloud sql databases list --instance=$SqlInstance --project=$ProjectId --format="value(name)" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "Failed to list databases"
}
if (@($dbList | Where-Object { $_ -eq $DbName }).Count -eq 0) {
    throw "Database $DbName was not found after setup"
}

Write-Host "  OK: $DbName is present on instance $SqlInstance"

Write-Host ""
Write-Host "Done. finance_hr database is ready on production Cloud SQL."
