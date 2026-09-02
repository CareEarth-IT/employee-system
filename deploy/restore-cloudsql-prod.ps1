# Restore production Cloud SQL (ceemployee) from a GCS SQL dump.
# WARNING: Replaces the entire ceemployee database (employee portal data).
#
# Usage:
#   deploy\restore-cloudsql-prod.cmd -Confirm
#   deploy\restore-cloudsql-prod.cmd -Confirm -GcsUri "gs://.../ceemployee_20260901_102924.sql"
#   deploy\restore-cloudsql-prod.cmd -Confirm -LocalFile "database\backups\ceemployee_20260901_102924.sql"
#
# Default GcsUri is the backup taken before the 2026-09-01 Airtable roster sync.

param(
    [string]$GcsUri = "gs://ce-gr-employee-info-2606st-sql-backups/cloudsql/ceemployee/ceemployee_20260901_102924.sql",
    [string]$LocalFile = "",
    [string]$Database = "ceemployee",
    [switch]$Confirm,
    [switch]$SkipPreBackup
)

$ErrorActionPreference = "Stop"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$SqlInstance = $cfg.CloudSqlInstance
$Bucket = "ce-gr-employee-info-2606st-sql-backups"
$Root = Split-Path $PSScriptRoot -Parent

if (-not $Confirm) {
    throw @"
Restore was NOT run (missing -Confirm).

This replaces the entire '$Database' database on Cloud SQL instance '$SqlInstance'.
To proceed after verifying the backup URI:

  deploy\restore-cloudsql-prod.cmd -Confirm

Optional:
  deploy\restore-cloudsql-prod.cmd -Confirm -GcsUri "gs://.../your-backup.sql"
  deploy\restore-cloudsql-prod.cmd -Confirm -LocalFile "database\backups\ceemployee_20260901_102924.sql"
"@
}

Write-Host ""
Write-Host "=== Restore Cloud SQL (PRODUCTION) ===" -ForegroundColor Red
Write-Host "Project  : $ProjectId"
Write-Host "Instance : $SqlInstance"
Write-Host "Database : $Database"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$importUri = $GcsUri

if ($LocalFile -ne "") {
    $resolvedLocal = if ([System.IO.Path]::IsPathRooted($LocalFile)) {
        $LocalFile
    } else {
        Join-Path $Root $LocalFile
    }

    if (-not (Test-Path $resolvedLocal)) {
        throw "Local backup not found: $resolvedLocal"
    }

    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $objectPath = "cloudsql/$Database/restore_upload_$timestamp.sql"
    $importUri = "gs://$Bucket/$objectPath"

    Write-Host "Local file : $resolvedLocal"
    Write-Host "Upload to  : $importUri"
    Write-Host ""

    if ((Invoke-Gcloud storage cp $resolvedLocal $importUri) -ne 0) {
        throw "Failed to upload local backup to GCS"
    }
} else {
    Write-Host "GCS backup : $importUri"
    Write-Host ""

    $objectExists = Invoke-Gcloud storage ls $importUri 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Backup not found in GCS: $importUri"
    }
}

$sqlServiceEmail = & gcloud sql instances describe $SqlInstance `
    --project=$ProjectId `
    --format="value(serviceAccountEmailAddress)" 2>&1
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sqlServiceEmail)) {
    throw "Failed to read Cloud SQL service account for instance '$SqlInstance'"
}

$sqlServiceAgent = "serviceAccount:$sqlServiceEmail"
Write-Host "Cloud SQL SA: $sqlServiceEmail"

Write-Host "==> Ensure backup bucket exists: gs://$Bucket"
$bucketCheck = Invoke-Gcloud storage buckets describe "gs://$Bucket" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "Backup bucket not found: gs://$Bucket"
}

Write-Host "==> Grant Cloud SQL SA read access to bucket"
$iamCode = Invoke-Gcloud storage buckets add-iam-policy-binding "gs://$Bucket" `
    --member=$sqlServiceAgent `
    --role="roles/storage.objectViewer"
if ($iamCode -ne 0) {
    Write-Host "WARN: Could not grant bucket read access automatically."
    Write-Host "Grant Storage Object Viewer on gs://$Bucket to: $sqlServiceEmail"
    Write-Host ""
}

if (-not $SkipPreBackup) {
    $preBackupPath = "cloudsql/$Database/${Database}_before_restore_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql"
    $preBackupUri = "gs://$Bucket/$preBackupPath"

    Write-Host "==> Pre-restore export (current DB snapshot)"
    Write-Host "    $preBackupUri"

    $exportCode = Invoke-Gcloud sql export sql $SqlInstance $preBackupUri `
        --project=$ProjectId `
        --database=$Database
    if ($exportCode -ne 0) {
        throw "Pre-restore export failed. Aborting restore."
    }

    Write-Host "    Pre-restore backup saved."
    Write-Host ""
}

$dbExists = Invoke-Gcloud sql databases describe $Database --instance=$SqlInstance --project=$ProjectId
if ($dbExists -eq 0) {
    Write-Host "==> Drop database: $Database"
    if ((Invoke-Gcloud sql databases delete $Database --instance=$SqlInstance --project=$ProjectId --quiet) -ne 0) {
        throw "Failed to drop database $Database"
    }
}

Write-Host "==> Create database: $Database"
if ((Invoke-Gcloud sql databases create $Database --instance=$SqlInstance --project=$ProjectId) -ne 0) {
    throw "Failed to create database $Database"
}

Write-Host "==> Import backup (may take 1-3 minutes)"
Write-Host "    $importUri"

$importCode = Invoke-Gcloud sql import sql $SqlInstance $importUri `
    --database=$Database `
    --project=$ProjectId `
    --quiet
if ($importCode -ne 0) {
    throw "Cloud SQL import failed"
}

Write-Host ""
Write-Host "Restore completed." -ForegroundColor Green
Write-Host "  Database : $Database"
Write-Host "  Source   : $importUri"
Write-Host ""
Write-Host "Verify: https://employee.careearth.net/employees"
Write-Host ""
