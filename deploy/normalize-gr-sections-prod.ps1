# Normalize GR section/team values in production Cloud SQL.
# Target storage: canonical GR section + team combined values.
#
# Usage:
#   deploy\normalize-gr-sections-prod.cmd
#   deploy\normalize-gr-sections-prod.cmd --apply

param(
    [switch]$Apply
)

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Root = Split-Path $PSScriptRoot -Parent
$Script = Join-Path $Root "deploy\scripts\normalize-gr-sections-prod.php"

. (Join-Path $PSScriptRoot "deploy-common.ps1")

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "=== Normalize GR sections/teams (production) ===" -ForegroundColor Cyan
Write-Host "Script : $Script"
Write-Host ""

$args = @()
if ($Apply) {
    $args += "--apply"
}

& php $Script @args

if ($LASTEXITCODE -ne 0) {
    throw "GR section normalization failed"
}

Write-Host ""
Write-Host "GR section/team normalization completed."
