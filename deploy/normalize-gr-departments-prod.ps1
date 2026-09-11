# Normalize GR department values in production Cloud SQL.
# Target storage: department = GR部（グローバル部）, jurisdiction/location = 大阪|東京|名古屋|福岡
#
# Usage:
#   deploy\normalize-gr-departments-prod.cmd

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$SqlInstance = "employee"
$DbName = "ceemployee"
$Root = Split-Path $PSScriptRoot -Parent
$SqlFile = Join-Path $Root "deploy\scripts\normalize-gr-departments-prod.sql"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "=== Normalize GR departments (production) ===" -ForegroundColor Cyan
Write-Host "Instance : $SqlInstance"
Write-Host "Database : $DbName"
Write-Host "SQL file : $SqlFile"
Write-Host ""
Write-Host "Target form:"
Write-Host "  department = GR department canonical label"
Write-Host "  jurisdiction / location = Osaka/Tokyo/Nagoya/Fukuoka"
Write-Host ""
Write-Host "Also normalizes comma-separated legacy global business labels."
Write-Host ""

$code = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=@$SqlFile

if ($code -ne 0) {
    throw "Cloud SQL update failed"
}

Write-Host ""
Write-Host "GR department normalization completed."
