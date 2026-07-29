# Reset XAMPP MariaDB data directory (local dev recovery)
# Usage: powershell -ExecutionPolicy Bypass -File deploy\repair-xampp-mysql.ps1

$ErrorActionPreference = "Stop"

$MysqlBin = "C:\xampp\mysql\bin"
$MysqlRoot = "C:\xampp\mysql"
$MysqlData = "$MysqlRoot\data"
$BackupData = "$MysqlRoot\data_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
$ProjectRoot = Split-Path $PSScriptRoot -Parent

Write-Host "==> Stop mysqld"
Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

Write-Host "==> Backup current data folder"
if (Test-Path $MysqlData) {
    Rename-Item $MysqlData $BackupData
}

Write-Host "==> Initialize fresh MariaDB data directory"
New-Item -ItemType Directory -Path $MysqlData | Out-Null
& "$MysqlBin\mysql_install_db.exe" --datadir="$($MysqlData -replace '\\','/')"
if ($LASTEXITCODE -ne 0) {
    throw "mysql_install_db failed"
}

Write-Host "==> Start MariaDB"
$mysqld = Start-Process -FilePath "$MysqlBin\mysqld.exe" `
    -ArgumentList @("--defaults-file=$MysqlBin\my.ini", "--standalone") `
    -PassThru -WindowStyle Hidden

function Wait-ForMysql {
    for ($i = 1; $i -le 30; $i++) {
        if (netstat -ano | Select-String ":3306.*LISTENING") {
            Start-Sleep -Seconds 2
            return $true
        }
        Start-Sleep -Seconds 1
    }

    return $false
}

if (-not (Wait-ForMysql)) {
    Stop-Process -Id $mysqld.Id -Force -ErrorAction SilentlyContinue
    throw "MariaDB did not start. Backup kept at: $BackupData"
}

Write-Host "==> Create employee database"
& "$MysqlBin\mysql.exe" -u root --execute "CREATE DATABASE employee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) {
    throw "CREATE DATABASE failed"
}

Write-Host "==> Run Laravel migrations"
Push-Location $ProjectRoot
& php artisan migrate --force
Pop-Location

Write-Host ""
Write-Host "Done."
Write-Host "  Backup : $BackupData"
Write-Host "  MariaDB: running on port 3306"
Write-Host "  Database: employee (recreated)"
Write-Host "Re-import local employee data if needed."
