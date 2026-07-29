# Copy drive staff sync settings from employee Cloud Run to local .env
# Usage: deploy\setup-drive-staff-sync-local.cmd

$ErrorActionPreference = "Stop"

. "$PSScriptRoot\deploy-common.ps1"

$Root = Split-Path $PSScriptRoot -Parent
$EnvFile = Join-Path $Root ".env"
$cfg = Get-DeployConfig

Write-Host ""
Write-Host "=== Drive staff sync -> local .env ===" -ForegroundColor Cyan
Write-Host "Source: employee Cloud Run ($($cfg.ProjectId))"
Write-Host ""

$json = & gcloud run services describe $($cfg.Service) `
    --project=$($cfg.ProjectId) `
    --region=$($cfg.Region) `
    --format="json(spec.template.spec.containers[0].env)" 2>&1

if ($LASTEXITCODE -ne 0) {
    throw "Failed to read employee Cloud Run env: $json"
}

$parsed = $json | ConvertFrom-Json
$envMap = @{}
foreach ($item in $parsed.spec.template.spec.containers[0].env) {
    if ($item.name -and $item.value) {
        $envMap[$item.name] = $item.value
    }
}

$driveUrl = [string]$envMap["DRIVE_APP_API_URL"]
$syncSecret = [string]$envMap["EMPLOYEE_SITE_SYNC_SECRET"]

if ($driveUrl -eq "" -or $syncSecret -eq "") {
    throw @"
DRIVE_APP_API_URL or EMPLOYEE_SITE_SYNC_SECRET is missing on employee Cloud Run.

Run first (same secret on gas-app Cloud Run):
  deploy\setup-drive-staff-sync-cloudrun.cmd `"共有秘密鍵`"
  gas-app\scripts\setup-employee-site-sync-cloudrun.ps1 -SyncSecret `"共有秘密鍵`"
"@
}

if (-not (Test-Path $EnvFile)) {
    throw ".env not found: $EnvFile"
}

function Set-EnvValue {
    param(
        [string[]]$Lines,
        [string]$Key,
        [string]$Value
    )

    $updated = $false
    $result = foreach ($line in $Lines) {
        if ($line -match "^\s*$([regex]::Escape($Key))=") {
            $updated = $true
            "$Key=$Value"
        } else {
            $line
        }
    }

    if (-not $updated) {
        $result += "$Key=$Value"
    }

    return ,$result
}

$lines = Get-Content $EnvFile
$lines = Set-EnvValue -Lines $lines -Key "DRIVE_APP_API_URL" -Value $driveUrl
$lines = Set-EnvValue -Lines $lines -Key "EMPLOYEE_SITE_SYNC_SECRET" -Value $syncSecret
Set-Content -Path $EnvFile -Value $lines -Encoding UTF8

Write-Host "Updated .env:"
Write-Host "  DRIVE_APP_API_URL=$driveUrl"
Write-Host "  EMPLOYEE_SITE_SYNC_SECRET=(set)"
Write-Host ""

Push-Location $Root
try {
    php artisan config:clear | Out-Host
} finally {
    Pop-Location
}

Write-Host "Done. Test at: http://employee.local/dashboard?tab=company-car" -ForegroundColor Green
