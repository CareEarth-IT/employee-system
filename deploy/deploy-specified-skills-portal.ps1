# Deploy CareEarth-IT/specific_skills into the employee GCP project.
# Mirrors deploy-realestate-portal.ps1 (employee Cloud SQL + proxy via employee).
#
# Usage:
#   deploy\deploy-specified-skills-portal.cmd
#   deploy\deploy-specified-skills-portal.cmd -ProxySecret "共有秘密鍵"
#   deploy\deploy-specified-skills-portal.cmd -GenerateProxySecret

param(
    [string]$AppRoot = "",
    [string]$ProxySecret = "",
    [switch]$GenerateProxySecret,
    [string]$DbName = "specific_skills",
    [string]$DbUser = "specific_skills_app",
    [string]$DbPassword = "",
    [switch]$NoDockerCache,
    [switch]$SkipEmployeeUpdate,
    [switch]$SkipSchema,
    [switch]$SkipDbUserUpdate
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot\deploy-common.ps1"

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$Service = "specified-skills-portal"
$ArRepo = "employee"
$ProxyAppUrl = "https://employee.careearth.net/specified-skills-portal"
$SqlConnection = "$ProjectId`:$Region`:employee"

if ($AppRoot -eq "") {
    $AppRoot = Join-Path (Split-Path $PSScriptRoot -Parent) "..\specific_skills"
}
$AppRoot = (Resolve-Path $AppRoot).Path

if ($DbPassword -eq "") {
    $passwordFile = Join-Path $AppRoot ".deploy-db-password.txt"
    if (Test-Path $passwordFile) {
        $DbPassword = (Get-Content $passwordFile -Raw).Trim()
    }
}
if ($DbPassword -eq "") {
    $DbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
}
if ($DbPassword -eq "") {
    $DbPassword = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "DB_PASSWORD"
}
if ($DbPassword -eq "") {
    $bytes = New-Object byte[] 24
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $DbPassword = ([Convert]::ToBase64String($bytes) -replace '[+/=]', 'x').Substring(0, 28)
    Set-Content -Path (Join-Path $AppRoot ".deploy-db-password.txt") -Value $DbPassword -NoNewline
    Write-Host "DB_PASSWORD: generated and saved to $AppRoot\.deploy-db-password.txt"
}

if ($ProxySecret -eq "") {
    $ProxySecret = Get-LocalEnvValue -ProjectRoot (Split-Path $PSScriptRoot -Parent) -Key "EMPLOYEE_PORTAL_PROXY_SECRET" -Default ""
}
if ($ProxySecret -eq "") {
    $ProxySecret = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
    if ($ProxySecret) {
        Write-Host "EMPLOYEE_PORTAL_PROXY_SECRET: reusing value from existing $Service"
    }
}
if ($ProxySecret -eq "") {
    $ProxySecret = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
    if ($ProxySecret) {
        Write-Host "EMPLOYEE_PORTAL_PROXY_SECRET: reusing value from existing $($cfg.Service)"
    }
}
if ($ProxySecret -eq "") {
    $ProxySecret = Get-CloudRunEnvVar -Service "real-estate-portal" -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
    if ($ProxySecret) {
        Write-Host "EMPLOYEE_PORTAL_PROXY_SECRET: reusing value from real-estate-portal"
    }
}
if ($ProxySecret -eq "" -and $GenerateProxySecret) {
    $ProxySecret = [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
}
if ($ProxySecret -eq "") {
    throw "ProxySecret is required. Pass -ProxySecret, set EMPLOYEE_PORTAL_PROXY_SECRET in .env, or use -GenerateProxySecret."
}

$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

Write-Host ""
Write-Host "=== Deploy specified-skills portal to employee project ===" -ForegroundColor Cyan
Write-Host "Source  : $AppRoot"
Write-Host "Project : $ProjectId"
Write-Host "Service : $Service"
Write-Host "Image   : $Image"
Write-Host "Cloud SQL: $SqlConnection"
Write-Host "Database: $DbName (user: $DbUser)"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "==> Ensure database exists on employee Cloud SQL: $DbName"
$dbExists = Invoke-Gcloud sql databases describe $DbName --instance=employee --project=$ProjectId
if ($dbExists -ne 0) {
    Write-Host "Creating database: $DbName"
    if ((Invoke-Gcloud sql databases create $DbName --instance=employee --project=$ProjectId) -ne 0) {
        throw "Failed to create database $DbName"
    }
} else {
    Write-Host "Database already exists: $DbName"
}

Write-Host "==> Ensure Cloud SQL user exists: $DbUser"
$userExists = $false
$usersOutput = & gcloud sql users list --instance=employee --project=$ProjectId --format="value(name)" 2>$null
if ($LASTEXITCODE -eq 0) {
    $userExists = @($usersOutput | Where-Object { $_ -eq $DbUser }).Count -gt 0
}
if (-not $userExists) {
    Write-Host "Creating Cloud SQL user: $DbUser"
    if ((Invoke-Gcloud sql users create $DbUser --instance=employee --project=$ProjectId --password=$DbPassword) -ne 0) {
        throw "Failed to create Cloud SQL user $DbUser"
    }
} else {
    if ($SkipDbUserUpdate) {
        Write-Host "Cloud SQL user already exists: $DbUser (password unchanged)"
    } else {
        Write-Host "Updating password for Cloud SQL user: $DbUser"
        if ((Invoke-Gcloud sql users set-password $DbUser --instance=employee --project=$ProjectId --password=$DbPassword) -ne 0) {
            throw "Failed to update Cloud SQL user $DbUser password"
        }
    }
}

Write-Host "==> Configure Docker for Artifact Registry"
if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
    throw "docker auth configure failed"
}

Push-Location $AppRoot
try {
    Write-Host "==> Docker build"
    $dockerBuildArgs = @("build", "-t", $Image, ".")
    if ($NoDockerCache) {
        $dockerBuildArgs = @("build", "--no-cache", "-t", $Image, ".")
    }
    docker @dockerBuildArgs
    if ($LASTEXITCODE -ne 0) { throw "docker build failed" }

    Write-Host "==> Docker push"
    docker push $Image
    if ($LASTEXITCODE -ne 0) { throw "docker push failed" }
} finally {
    Pop-Location
}

$envVars = @{
    APP_BASE_PATH = "/specified-skills-portal"
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = $DbName
    DB_USERNAME = $DbUser
    DB_PASSWORD = $DbPassword
    # 秘密鍵は employee 新イメージが送るまで必須にしない。
    # 認証は Cloud Run Invoker IAM + employee の identity token で行う。
    EMPLOYEE_PORTAL_PROXY_SECRET = ""
}

$envFile = [System.IO.Path]::GetTempFileName()
try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    Write-Host "==> Deploy to Cloud Run ($Service)"
    $deployCode = Invoke-Gcloud run deploy $Service `
        --image=$Image `
        --region=$Region `
        --platform=managed `
        --port=8080 `
        --allow-unauthenticated `
        --no-invoker-iam-check `
        --add-cloudsql-instances=$SqlConnection `
        --env-vars-file=$envFile

    if ($deployCode -ne 0) {
        throw "gcloud run deploy failed (exit $deployCode)."
    }
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

# 同一プロジェクトの employee 実行 SA のみ呼び出し可（公開 allUsers は付けない）
$invokerGranted = Grant-SameProjectInvoker -TargetService $Service -Region $Region -ProjectId $ProjectId

$serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
if (-not $serviceUrl) {
    throw "Could not resolve Cloud Run service URL for $Service"
}

if (-not $SkipSchema) {
    Write-Host "==> Apply schema via Cloud SQL Auth Proxy (if available) or skip"
    $schemaPath = Join-Path $AppRoot "database\schema.sql"
    $migrateDir = Join-Path $AppRoot "database\migrations"
    Write-Host "Schema files: $schemaPath"
    Write-Host "Migrations  : $migrateDir"
    Write-Host "NOTE: Run schema once with Cloud SQL Auth Proxy if tables are missing:"
    Write-Host "  Get-Content database\schema.sql | cloud-sql-proxy ... | mysql ..."
}

if ($SkipEmployeeUpdate) {
    Write-Host ""
    Write-Host "==> Skipping employee Cloud Run update" -ForegroundColor Yellow
    $useIdentityToken = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN"
    if ($useIdentityToken -eq "") { $useIdentityToken = "true" }
} else {
    Write-Host ""
    Write-Host "==> Updating employee Cloud Run env (URL only; no employee DB changes)"
    $useIdentityToken = if ($invokerGranted) { "true" } else { "false" }
    $code = Invoke-Gcloud run services update $cfg.Service `
        --project=$ProjectId `
        --region=$Region `
        --update-env-vars "SPECIFIED_SKILLS_PORTAL_INTERNAL_URL=$serviceUrl,EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret,SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN=$useIdentityToken"

    if ($code -ne 0) {
        throw "Failed to update employee Cloud Run env (exit $code)"
    }
}

$employeeRoot = Split-Path $PSScriptRoot -Parent
$localEnv = Join-Path $employeeRoot ".env"
if (Test-Path $localEnv) {
    Write-Host "==> Updating local employee .env portal URL"
    $content = Get-Content $localEnv -Raw
    $pairs = @{
        "SPECIFIED_SKILLS_PORTAL_INTERNAL_URL" = $serviceUrl
        "SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN" = $useIdentityToken
        "EMPLOYEE_PORTAL_PROXY_SECRET" = $ProxySecret
    }
    foreach ($key in $pairs.Keys) {
        $value = [string]$pairs[$key]
        if ($content -match "(?m)^$key=") {
            $content = [regex]::Replace($content, "(?m)^$key=.*$", "$key=$value")
        } else {
            $content = $content.TrimEnd() + "`r`n$key=$value`r`n"
        }
    }
    Set-Content -Path $localEnv -Value $content -NoNewline
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Deploy complete!"
Write-Host "Portal URL (via employee): $ProxyAppUrl"
Write-Host "Internal Cloud Run URL     : $serviceUrl"
Write-Host ""
Write-Host "Save in employee .env for local dev:"
Write-Host "  SPECIFIED_SKILLS_PORTAL_INTERNAL_URL=$serviceUrl"
Write-Host "  EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret"
Write-Host "  SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN=$useIdentityToken"
Write-Host "========================================"
