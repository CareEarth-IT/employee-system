# Import specific_skills SQL dump into production Cloud SQL (specific_skills DB only).
# Does NOT modify ceemployee or any other database.
#
# Usage:
#   deploy\import-specific-skills-prod.cmd
#   deploy\import-specific-skills-prod.cmd -DumpPath "C:\path\to\dump.dmp"
#   deploy\import-specific-skills-prod.cmd -SkipUserSetup

param(
    [string]$DumpPath = "",
    [switch]$SkipUserSetup
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$SqlInstance = $cfg.CloudSqlInstance
$DbName = "specific_skills"
$Bucket = "ce-gr-employee-info-2606st-sql-backups"
$GrantPath = Join-Path $PSScriptRoot "sql\grant-specific-skills-app.sql"

if ($DumpPath -eq "") {
    $DumpPath = Join-Path $env:USERPROFILE "Downloads\specific_skills_20260818.dmp"
}

if (-not (Test-Path $DumpPath)) {
    throw "Dump file not found: $DumpPath"
}

Write-Host ""
Write-Host "=== Import specific_skills dump (production Cloud SQL) ===" -ForegroundColor Cyan
Write-Host "Project  : $ProjectId"
Write-Host "Instance : $SqlInstance"
Write-Host "Database : $DbName ONLY (ceemployee will NOT be touched)"
Write-Host "Dump     : $DumpPath"
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
$stagingDir = Join-Path $PSScriptRoot "_import-staging"
New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null
$cleanDumpPath = Join-Path $stagingDir "specific_skills_import_$timestamp.sql"

Write-Host "==> Prepare import SQL (remove CREATE DATABASE / USE for safety)"
$lines = Get-Content $DumpPath -Encoding UTF8
$cleanLines = foreach ($line in $lines) {
    if ($line -match '^\s*CREATE DATABASE\b') { continue }
    if ($line -match '^\s*USE `specific_skills`\s*;\s*$') { continue }
    $line
}
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($cleanDumpPath, ($cleanLines -join [Environment]::NewLine), $utf8NoBom)

$objectPath = "specific_skills/import_$timestamp.sql"
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
    Write-Host "==> Ensure specific_skills_app user exists"
    $userExists = $false
    $usersOutput = & gcloud sql users list --instance=$SqlInstance --project=$ProjectId --format="value(name)" 2>$null
    if ($LASTEXITCODE -eq 0) {
        $userExists = @($usersOutput | Where-Object { $_ -eq "specific_skills_app" }).Count -gt 0
    }
    if (-not $userExists) {
        $reposRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
        $passwordFile = Join-Path $reposRoot "specific_skills\.deploy-db-password.txt"
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
        Write-Host "    Creating user: specific_skills_app"
        if ((Invoke-Gcloud sql users create specific_skills_app --instance=$SqlInstance --project=$ProjectId --password=$dbPassword) -ne 0) {
            throw "Failed to create Cloud SQL user specific_skills_app"
        }
    } else {
        Write-Host "    User already exists: specific_skills_app"
    }

    if (-not (Test-Path $GrantPath)) {
        throw "Grant SQL not found: $GrantPath"
    }

    $grantObjectPath = "specific_skills/grant_app_$timestamp.sql"
    $grantUri = "gs://$Bucket/$grantObjectPath"

    Write-Host "==> Grant specific_skills_app access to $DbName"
    if ((Invoke-Gcloud storage cp $GrantPath $grantUri) -ne 0) {
        throw "Failed to upload grant SQL to GCS"
    }

    $grantCode = Invoke-Gcloud sql import sql $SqlInstance $grantUri `
        --database=mysql `
        --project=$ProjectId `
        --quiet
    if ($grantCode -ne 0) {
        Write-Host ""
        Write-Host "WARN: Automatic GRANT import failed (Cloud SQL may require root in Console)." -ForegroundColor Yellow
        Write-Host "  Run in Cloud SQL Query (as root) if the portal cannot connect:"
        Write-Host "  GRANT ALL PRIVILEGES ON \`specific_skills\`.* TO \`specific_skills_app\`;"
        Write-Host "  FLUSH PRIVILEGES;"
    }
}

Write-Host ""
Write-Host "==> Import complete"
Write-Host "  Database: $DbName on instance $SqlInstance"
Write-Host "  ceemployee: unchanged"
Write-Host ""
