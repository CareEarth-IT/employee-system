# Restore a Cloud SQL dump into local XAMPP MariaDB (employee database)
# The backup file is read only; it is never modified or deleted.
#
# Usage:
#   deploy\restore-local-db.cmd
#   deploy\restore-local-db.cmd database\backups\ceemployee_20260706_105342.sql
#
# Prerequisites:
#   - XAMPP MySQL/MariaDB running on port 3306
#   - .env DB_DATABASE=employee, DB_USERNAME=root, DB_PASSWORD=(empty or your password)

param(
    [string]$BackupFile = "",
    [string]$Database = "employee",
    [string]$DbUser = "root",
    [string]$DbPassword = "",
    [string]$MysqlBin = "C:\xampp\mysql\bin"
)

$ErrorActionPreference = "Stop"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$DbPassword = Get-LocalDbPassword -ProjectRoot $Root
if ($DbPassword -eq $null) {
    $DbPassword = ""
}

if ($BackupFile -eq "") {
    $backupsDir = Join-Path $Root "database\backups"
    $latest = Get-ChildItem $backupsDir -Filter "*.sql" -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1

    if (-not $latest) {
        throw @"
No SQL backup found in database\backups\

Download from GCS, for example:
  gcloud storage cp gs://ce-gr-employee-info-2606st-sql-backups/cloudsql/ceemployee/LATEST.sql database/backups/
"@
    }

    $BackupFile = $latest.FullName
} elseif (-not [System.IO.Path]::IsPathRooted($BackupFile)) {
    $BackupFile = Join-Path $Root $BackupFile
}

if (-not (Test-Path $BackupFile)) {
    throw "Backup file not found: $BackupFile"
}

if (-not (Test-Path "$MysqlBin\mysql.exe")) {
    throw "mysql.exe not found: $MysqlBin\mysql.exe"
}

function Invoke-Mysql {
    param([string]$Sql)

    $args = @("-u", $DbUser, "--default-character-set=utf8mb4")

    if ($DbPassword -ne "") {
        $args += @("-p$DbPassword")
    }

    if ($Sql -ne "") {
        $args += @("-e", $Sql)
    }

    & "$MysqlBin\mysql.exe" @args
    if ($LASTEXITCODE -ne 0) {
        throw "mysql command failed"
    }
}

function Test-MysqlRunning {
    try {
        Invoke-Mysql -Sql "SELECT 1"
        return $true
    } catch {
        return $false
    }
}

Write-Host "Backup file : $BackupFile"
Write-Host "Target DB   : $Database"
Write-Host ""

if (-not (Test-MysqlRunning)) {
    throw @"
Cannot connect to MySQL on 127.0.0.1:3306.

Start MySQL from the XAMPP Control Panel, then run this script again.
"@
}

Write-Host "==> Recreate local database: $Database"
Invoke-Mysql -Sql "DROP DATABASE IF EXISTS ``$Database``; CREATE DATABASE ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

Write-Host "==> Import backup (read-only source file)"
$tempSql = [System.IO.Path]::GetTempFileName() + ".sql"

try {
    $content = Get-Content -Path $BackupFile -Raw -Encoding UTF8
    $content = $content -replace 'ceemployee', $Database
    $content = $content -replace "DEFAULT ENCRYPTION='N'", ""
    $content = $content -replace 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci'
    Set-Content -Path $tempSql -Value $content -Encoding UTF8

    $importArgs = @("-u", $DbUser, "--default-character-set=utf8mb4", $Database)

    if ($DbPassword -ne "") {
        $importArgs = @("-u", $DbUser, "-p$DbPassword", "--default-character-set=utf8mb4", $Database)
    }

    Get-Content -Path $tempSql -Raw | & "$MysqlBin\mysql.exe" @importArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Import failed"
    }
} finally {
    Remove-Item $tempSql -Force -ErrorAction SilentlyContinue
}

Write-Host "==> Verify row counts"
$userCount = & "$MysqlBin\mysql.exe" -u $DbUser $(if ($DbPassword -ne "") { "-p$DbPassword" }) `
    -N -e "SELECT COUNT(*) FROM users;" $Database 2>$null
$profileCount = & "$MysqlBin\mysql.exe" -u $DbUser $(if ($DbPassword -ne "") { "-p$DbPassword" }) `
    -N -e "SELECT COUNT(*) FROM employee_profiles;" $Database 2>$null

Write-Host ""
Write-Host "Restore completed."
Write-Host "  users             : $userCount"
Write-Host "  employee_profiles : $profileCount"
Write-Host ""
Write-Host "Backup kept at:"
Write-Host "  $BackupFile"
Write-Host ""
Write-Host "Local .env should use:"
Write-Host "  DB_DATABASE=$Database"
Write-Host "  DB_HOST=127.0.0.1"
Write-Host "  DB_PORT=3306"
