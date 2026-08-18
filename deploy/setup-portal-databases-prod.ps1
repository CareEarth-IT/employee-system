# Create portal databases on production Cloud SQL (employee instance).
# Does NOT deploy Cloud Run or modify ceemployee.
#
# Usage:
#   deploy\setup-portal-databases-prod.cmd
#   deploy\setup-portal-databases-prod.cmd -WithUsers

param(
    [switch]$WithUsers
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

. (Join-Path $PSScriptRoot "deploy-common.ps1")

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Instance = $cfg.CloudSqlInstance
$SqlConnection = $cfg.CloudSqlConnection

$portalDatabases = @(
    @{ Name = "real_estate"; User = "real_estate_app"; PasswordFile = "..\real-estate\.deploy-db-password.txt" },
    @{ Name = "specific_skills"; User = "specific_skills_app"; PasswordFile = "..\specific_skills\.deploy-db-password.txt" }
)

Write-Host ""
Write-Host "=== Create portal databases on Cloud SQL ===" -ForegroundColor Cyan
Write-Host "Project  : $ProjectId"
Write-Host "Instance : $Instance"
Write-Host "Socket   : $SqlConnection"
Write-Host "Targets  : real_estate, specific_skills (ceemployee is NOT modified)"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed (run: gcloud auth login)"
}

foreach ($portal in $portalDatabases) {
    $dbName = $portal.Name
    Write-Host "==> Database: $dbName"
    $exists = Invoke-Gcloud sql databases describe $dbName --instance=$Instance --project=$ProjectId
    if ($exists -eq 0) {
        Write-Host "    Already exists."
    } else {
        Write-Host "    Creating..."
        if ((Invoke-Gcloud sql databases create $dbName --instance=$Instance --project=$ProjectId) -ne 0) {
            throw "Failed to create database $dbName"
        }
        Write-Host "    Created."
    }
}

if ($WithUsers) {
    Write-Host ""
    Write-Host "==> Cloud SQL users (optional for portal apps)"
    $employeeRoot = Split-Path $PSScriptRoot -Parent
    $reposRoot = Split-Path $employeeRoot -Parent

    foreach ($portal in $portalDatabases) {
        $dbUser = $portal.User
        $passwordFile = Join-Path $reposRoot ($portal.PasswordFile -replace '^\.\.\\', '')

        $dbPassword = ""
        if (Test-Path $passwordFile) {
            $dbPassword = (Get-Content $passwordFile -Raw).Trim()
        }
        if ($dbPassword -eq "") {
            $bytes = New-Object byte[] 24
            [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
            $dbPassword = ([Convert]::ToBase64String($bytes) -replace '[+/=]', 'x').Substring(0, 28)
            $passwordDir = Split-Path $passwordFile -Parent
            if (-not (Test-Path $passwordDir)) {
                New-Item -ItemType Directory -Path $passwordDir -Force | Out-Null
            }
            Set-Content -Path $passwordFile -Value $dbPassword -NoNewline
            Write-Host "    Generated password saved: $passwordFile"
        }

        $userExists = $false
        $usersOutput = & gcloud sql users list --instance=$Instance --project=$ProjectId --format="value(name)" 2>$null
        if ($LASTEXITCODE -eq 0) {
            $userExists = @($usersOutput | Where-Object { $_ -eq $dbUser }).Count -gt 0
        }

        if (-not $userExists) {
            Write-Host "    Creating user: $dbUser"
            if ((Invoke-Gcloud sql users create $dbUser --instance=$Instance --project=$ProjectId --password=$dbPassword) -ne 0) {
                throw "Failed to create user $dbUser"
            }
        } else {
            Write-Host "    Updating password for user: $dbUser"
            if ((Invoke-Gcloud sql users set-password $dbUser --instance=$Instance --project=$ProjectId --password=$dbPassword) -ne 0) {
                throw "Failed to update password for $dbUser"
            }
        }
    }
}

Write-Host ""
Write-Host "==> Current databases on $Instance"
Invoke-Gcloud sql databases list --instance=$Instance --project=$ProjectId --format="table(name,charset,collation)" | Out-Null

Write-Host ""
Write-Host "Done. Next: import SQL dumps into real_estate / specific_skills, then deploy portal Cloud Run services."
Write-Host ""
