# Delete users from production Cloud SQL by email
# Usage:
#   deploy\delete-users-prod.cmd admin@careearth.info external_sharing@careearth.info

param(
    [Parameter(Mandatory = $true, ValueFromRemainingArguments = $true)]
    [string[]]$Emails
)

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$SqlInstance = "employee"
$DbName = "ceemployee"

if ($Emails.Count -eq 0) {
    throw "Specify at least one email address."
}

$normalized = @($Emails | ForEach-Object { $_.Trim().ToLower() } | Where-Object { $_ -ne "" } | Select-Object -Unique)
if ($normalized.Count -eq 0) {
    throw "No valid email addresses."
}

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$authProbe = gcloud run services describe employee --region=asia-northeast1 --project=$ProjectId --format="value(name)" 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "gcloud authentication failed. Run: gcloud auth login"
}

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

$emailList = ($normalized | ForEach-Object { "'$($_ -replace "'", "''")'" }) -join ", "

Write-Host "Target emails:"
$normalized | ForEach-Object { Write-Host "  - $_" }
Write-Host ""
Write-Host "Checking users in Cloud SQL ($DbName)..."

$selectSql = "SELECT id, email, name FROM users WHERE email IN ($emailList);"
$selectCode = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=$selectSql

if ($selectCode -ne 0) {
    throw "Failed to query users."
}

Write-Host ""
Write-Host "Deleting users and related data..."

$deleteSql = @"
DELETE FROM password_reset_tokens WHERE email IN ($emailList);
DELETE FROM sessions WHERE user_id IN (SELECT id FROM users WHERE email IN ($emailList));
DELETE FROM users WHERE email IN ($emailList);
"@

$deleteCode = Invoke-Gcloud sql instances execute-sql $SqlInstance `
    --database=$DbName `
    --sql=$deleteSql

if ($deleteCode -ne 0) {
    throw "Failed to delete users."
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
Write-Host "Done. Deleted $($normalized.Count) account(s) from production."
