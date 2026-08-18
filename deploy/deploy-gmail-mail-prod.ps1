# Apply Gmail SMTP from local .env to production Cloud Run (employee service).
# Does NOT rebuild Docker unless you pass -FullDeploy.
#
# Usage:
#   deploy\deploy-gmail-mail-prod.cmd
#   deploy\deploy-gmail-mail-prod.cmd -FullDeploy

param(
    [switch]$FullDeploy
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "0"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

Write-Host ""
Write-Host "=== Deploy Gmail SMTP to production ===" -ForegroundColor Cyan
Write-Host "DEPLOY_PRESERVE_CLOUD_RUN_MAIL=0 (overwrite Cloud Run MAIL_*)"
Write-Host ""

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if (-not (Test-MailConfiguredForProduction -ProjectRoot $Root)) {
    throw "Fix MAIL_* in .env before deploying Gmail SMTP to production."
}

$mailHost = Get-LocalEnvValue -ProjectRoot $Root -Key "MAIL_HOST"
if ($mailHost -ne "smtp.gmail.com") {
    throw "MAIL_HOST must be smtp.gmail.com (current: $mailHost). Update .env before running this script."
}

if ($FullDeploy) {
    Write-Host "==> Full deploy (docker build + push + Cloud Run)"
    & (Join-Path $PSScriptRoot "docker-deploy.ps1")
} else {
    Write-Host "==> Env-only deploy (existing image, update MAIL_* on Cloud Run)"
    & (Join-Path $PSScriptRoot "deploy-only.ps1")
}

if ($LASTEXITCODE -ne 0) {
    throw "Deploy failed"
}

Write-Host ""
Write-Host "==> Verify Cloud Run MAIL_* (masked password)"
$mailKeys = @("MAIL_MAILER", "MAIL_HOST", "MAIL_PORT", "MAIL_USERNAME", "MAIL_FROM_ADDRESS", "MAIL_FROM_NAME")
foreach ($key in $mailKeys) {
    $value = Get-CloudRunEnvVar -Service "employee" -Region "asia-northeast1" -Name $key
    if ($key -eq "MAIL_FROM_NAME" -and (Test-MailFromNameCorrupted -Name $value)) {
        Write-Host "  WARN: $key looks corrupted in Cloud Run console: $value" -ForegroundColor Yellow
        Write-Host "  App uses config/mail.php production display name (CE-Group 社員専用)"
    } else {
        Write-Host "  $key = $value"
    }
}

Write-Host ""
Write-Host "Done. Test with:"
Write-Host "  php artisan mail:test yuta_masui@careearth.info"
Write-Host "  or submit one equipment purchase application on production"
Write-Host ""
