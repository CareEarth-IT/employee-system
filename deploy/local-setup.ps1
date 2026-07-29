# Local dev setup with SQLite (no XAMPP MySQL required)
# Usage: deploy\local-setup.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

$env:DB_CONNECTION = "sqlite"
$env:DB_DATABASE = Join-Path $Root "database\database.sqlite"
$env:SESSION_DRIVER = "file"
$env:CACHE_STORE = "file"
$env:QUEUE_CONNECTION = "sync"
$env:MAIL_MAILER = "log"

if (-not (Test-Path $env:DB_DATABASE)) {
    New-Item -Path $env:DB_DATABASE -ItemType File | Out-Null
    Write-Host "Created database\database.sqlite"
}

Write-Host "Running migrations..."
php artisan migrate --force

Write-Host "Seeding test users..."
php artisan db:seed --force

Write-Host ""
Write-Host "Done. Open: http://employee.local/login"
Write-Host "First time: deploy\setup-employee-local-host.cmd (Administrator)"
Write-Host ""
Write-Host "Test accounts:"
Write-Host "  ga@example.com / password"
Write-Host "  employee@example.com / password"
Write-Host ""
Write-Host "Update .env for Apache:"
Write-Host "  DB_CONNECTION=sqlite"
Write-Host "  DB_DATABASE=$($env:DB_DATABASE)"
Write-Host "  SESSION_DRIVER=file"
Write-Host "  CACHE_STORE=file"
Write-Host "  QUEUE_CONNECTION=sync"
