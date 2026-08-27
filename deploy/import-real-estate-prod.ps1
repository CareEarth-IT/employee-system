# Import real_estate SQL dump into production Cloud SQL (real_estate DB only).
# Does NOT modify ceemployee or specific_skills.
#
# Usage:
#   deploy\import-real-estate-prod.cmd
#   deploy\import-real-estate-prod.cmd -DumpPath "C:\Users\...\estate (2).sql"
#   deploy\import-real-estate-prod.cmd -KeepExisting
#
# Default: drops and recreates real_estate (schema + data from dump). Use -KeepExisting
# only when the database is empty or the dump has no CREATE TABLE statements.

param(
    [string]$DumpPath = "",
    [switch]$SkipUserSetup,
    [switch]$KeepExisting
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$SqlInstance = $cfg.CloudSqlInstance
$DbName = "real_estate"
$Bucket = "ce-gr-employee-info-2606st-sql-backups"
$GrantPath = Join-Path $PSScriptRoot "sql\grant-real-estate-app.sql"
$Root = Split-Path $PSScriptRoot -Parent

if ($DumpPath -eq "") {
    $DumpPath = Join-Path $env:USERPROFILE "Downloads\estate (2).sql"
}

if (-not (Test-Path $DumpPath)) {
    throw "Dump file not found: $DumpPath"
}

Write-Host ""
Write-Host "=== Import real_estate dump (production Cloud SQL) ===" -ForegroundColor Cyan
Write-Host "Project  : $ProjectId"
Write-Host "Instance : $SqlInstance"
Write-Host "Database : $DbName ONLY (ceemployee / specific_skills will NOT be touched)"
Write-Host "Dump     : $DumpPath"
Write-Host "Mode     : $(if ($KeepExisting) { 'keep existing DB (no drop)' } else { 'recreate real_estate then import' })"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$importsDir = Join-Path $Root "database\imports"
New-Item -ItemType Directory -Path $importsDir -Force | Out-Null
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$archivePath = Join-Path $importsDir "real_estate_$timestamp.sql"
Copy-Item -Path $DumpPath -Destination $archivePath -Force
Write-Host "Archived dump: $archivePath"

$dbExists = Invoke-Gcloud sql databases describe $DbName --instance=$SqlInstance --project=$ProjectId
if ($dbExists -eq 0) {
    if ($KeepExisting) {
        Write-Host "Database already exists: $DbName (import may fail if tables already exist)"
    } else {
        Write-Host "==> Drop database: $DbName"
        if ((Invoke-Gcloud sql databases delete $DbName --instance=$SqlInstance --project=$ProjectId --quiet) -ne 0) {
            throw "Failed to drop database $DbName"
        }
    }
}

if ($KeepExisting -and $dbExists -eq 0) {
    Write-Host "Database already exists: $DbName"
} elseif (-not ($KeepExisting -and $dbExists -eq 0)) {
    Write-Host "==> Create database: $DbName"
    if ((Invoke-Gcloud sql databases create $DbName --instance=$SqlInstance --project=$ProjectId) -ne 0) {
        throw "Failed to create database $DbName"
    }
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

$stagingDir = Join-Path $PSScriptRoot "_import-staging"
New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null
$cleanDumpPath = Join-Path $stagingDir "real_estate_import_$timestamp.sql"

Write-Host "==> Prepare import SQL (remove CREATE DATABASE / USE estate)"
$lines = Get-Content $DumpPath -Encoding UTF8
$cleanLines = foreach ($line in $lines) {
    if ($line -match '^\s*CREATE DATABASE\b') { continue }
    if ($line -match '^\s*USE `estate`\s*;\s*$') { continue }
    if ($line -match '^\s*USE `real_estate`\s*;\s*$') { continue }
    $line
}
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($cleanDumpPath, ($cleanLines -join [Environment]::NewLine), $utf8NoBom)

$objectPath = "real_estate/import_$timestamp.sql"
$uri = "gs://$Bucket/$objectPath"

Write-Host "==> Upload dump: $uri"
if ((Invoke-Gcloud storage cp $cleanDumpPath $uri) -ne 0) {
    throw "Failed to upload dump to GCS"
}

Write-Host "==> Import into $DbName (may take 1-3 minutes)"
$importCode = Invoke-Gcloud sql import sql $SqlInstance $uri `
    --database=$DbName `
    --project=$ProjectId `
    --quiet
if ($importCode -ne 0) {
    throw "Cloud SQL import failed"
}

if (-not $SkipUserSetup) {
    Write-Host ""
    Write-Host "==> Ensure real_estate_app user exists"
    $reposRoot = Split-Path $Root -Parent
    $passwordFile = Join-Path $reposRoot "real-estate\.deploy-db-password.txt"
    $dbPassword = ""
    if (Test-Path $passwordFile) {
        $dbPassword = (Get-Content $passwordFile -Raw).Trim()
    }
    if ($dbPassword -eq "") {
        $bytes = New-Object byte[] 24
        [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
        $dbPassword = ([Convert]::ToBase64String($bytes) -replace '[+/=]', 'x').Substring(0, 28)
        $passwordDir = Split-Path $passwordFile -Parent
        if (-not (Test-Path $passwordDir)) {
            New-Item -ItemType Directory -Path $passwordDir -Force | Out-Null
        }
        Set-Content -Path $passwordFile -Value $dbPassword -NoNewline
        Write-Host "    Generated password saved: $passwordFile"
    }

    $userExists = $false
    $usersOutput = & gcloud sql users list --instance=$SqlInstance --project=$ProjectId --format="value(name)" 2>$null
    if ($LASTEXITCODE -eq 0) {
        $userExists = @($usersOutput | Where-Object { $_ -eq "real_estate_app" }).Count -gt 0
    }
    if (-not $userExists) {
        Write-Host "    Creating user: real_estate_app"
        if ((Invoke-Gcloud sql users create real_estate_app --instance=$SqlInstance --project=$ProjectId --password=$dbPassword) -ne 0) {
            throw "Failed to create Cloud SQL user real_estate_app"
        }
    } else {
        Write-Host "    User already exists: real_estate_app"
    }

    if (-not (Test-Path $GrantPath)) {
        throw "Grant SQL not found: $GrantPath"
    }

    $grantObjectPath = "real_estate/grant_app_$timestamp.sql"
    $grantUri = "gs://$Bucket/$grantObjectPath"

    Write-Host "==> Grant real_estate_app access to $DbName"
    if ((Invoke-Gcloud storage cp $GrantPath $grantUri) -ne 0) {
        throw "Failed to upload grant SQL to GCS"
    }

    $grantCode = Invoke-Gcloud sql import sql $SqlInstance $grantUri `
        --database=mysql `
        --project=$ProjectId `
        --quiet
    if ($grantCode -ne 0) {
        Write-Host ""
        Write-Host "WARN: Automatic GRANT import failed (MySQL 1410)." -ForegroundColor Yellow
        Write-Host "  Run the same job as specific_skills:"
        Write-Host "  deploy\grant-real-estate-app-prod.cmd -RootPassword ""(Cloud SQL root password)"""
        Write-Host "  Optional: add -SyncPortalPassword to sync real-estate-portal DB_PASSWORD"
    }
}

Write-Host ""
Write-Host "==> Import complete" -ForegroundColor Green
Write-Host "  Database : $DbName on instance $SqlInstance"
Write-Host "  ceemployee / specific_skills : unchanged"
Write-Host ""
Write-Host "Next:"
Write-Host "  1. deploy\fix-realestate-portal-db.cmd   (if portal DB connection errors)"
Write-Host "  2. Open https://employee.careearth.net/realestate-portal/"
Write-Host ""
