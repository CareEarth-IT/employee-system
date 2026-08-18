# Delete equipment purchase application(s) from production Cloud SQL by ID.
# Usage:
#   deploy\delete-equipment-purchase-prod.cmd 130
#   deploy\delete-equipment-purchase-prod.cmd 130,131

param(
    [Parameter(Mandatory = $true)]
    [string]$Ids
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$SqlInstance = "employee"
$DbName = "ceemployee"

$idList = @(
    $Ids -split '[,\s;]+' |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -match '^\d+$' } |
        ForEach-Object { [int]$_ } |
        Select-Object -Unique
)

if ($idList.Count -eq 0) {
    throw "Specify at least one numeric application ID (example: 130)."
}

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

$idSql = ($idList | ForEach-Object { [string]$_ }) -join ", "

Write-Host "Target application ID(s): $idSql"
Write-Host ""
Write-Host "Checking records in Cloud SQL ($DbName)..."

$selectSql = @"
SELECT id, status, product_name, purchase_reason, application_date
FROM equipment_purchase_applications
WHERE id IN ($idSql);
"@

$selectCode = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=$selectSql

if ($selectCode -ne 0) {
    throw "Failed to query equipment purchase applications."
}

Write-Host ""
Write-Host "Deleting application(s)..."

$deleteSql = "DELETE FROM equipment_purchase_applications WHERE id IN ($idSql);"

$deleteCode = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=$deleteSql

if ($deleteCode -ne 0) {
    throw "Failed to delete equipment purchase application(s)."
}

Write-Host ""
Write-Host "Verifying deletion..."

$verifyCode = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=$selectSql

if ($verifyCode -ne 0) {
    throw "Failed to verify deletion."
}

Write-Host ""
Write-Host "Done. Deleted $($idList.Count) application(s) from production."
