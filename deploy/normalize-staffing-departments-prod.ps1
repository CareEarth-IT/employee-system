# Normalize legacy sales departments to 人材派遣事業部 on production Cloud SQL.
# Targets: 大阪営業部 / 東京営業部 / 名古屋営業部 / 福岡営業部 / 営業部 / 派遣事業部
#
# Usage:
#   deploy\normalize-staffing-departments-prod.cmd

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$SqlInstance = "employee"
$DbName = "ceemployee"
$Root = Split-Path $PSScriptRoot -Parent
$SqlFile = Join-Path $Root "deploy\scripts\normalize-staffing-departments-prod.sql"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "=== Normalize staffing departments (production) ===" -ForegroundColor Cyan
Write-Host "Instance : $SqlInstance"
Write-Host "Database : $DbName"
Write-Host "SQL file : $SqlFile"
Write-Host ""
Write-Host "Rewrites:"
Write-Host "  大阪営業部 / 東京営業部 / 名古屋営業部 / 福岡営業部 / 営業部 / 派遣事業部"
Write-Host "  -> 人材派遣事業部"
Write-Host "Fills empty location/jurisdiction from the legacy city prefix."
Write-Host ""

$code = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=@$SqlFile

if ($code -ne 0) {
    throw "Cloud SQL update failed"
}

Write-Host ""
Write-Host "Staffing department normalization completed."
