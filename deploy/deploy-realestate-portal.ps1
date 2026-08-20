# Deploy real-estate Laravel app into the employee GCP project.
# Avoids cross-project Cloud Run IAM (real-estate project blocks invoker changes).
#
# Usage:
#   deploy\deploy-realestate-portal.cmd
#   deploy\deploy-realestate-portal.cmd -ProxySecret "共有秘密鍵"

param(
    [string]$RealEstateRoot = "",
    [string]$ProxySecret = "",
    [switch]$GenerateProxySecret,
    [string]$RealEstateSqlProject = "ce-realestate-inside-2606st",
    [string]$RealEstateSqlInstance = "real-estate-db",
    [string]$DbHost = "",
    [string]$DbName = "real_estate",
    [string]$DbUser = "",
    [string]$DbPassword = "",
    [string]$AppKey = "",
    [switch]$NoDockerCache,
    [switch]$SkipEmployeeUpdate,
    [switch]$UseEmployeeCloudSql,
    [switch]$UseCloudBuild,
    [switch]$SkipDbSetup
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot\deploy-common.ps1"

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$Service = "real-estate-portal"
$ArRepo = "employee"
$ProxyAppUrl = "https://employee.careearth.net/realestate-portal"

if ($RealEstateRoot -eq "") {
    $RealEstateRoot = Join-Path (Split-Path $PSScriptRoot -Parent) "..\real-estate"
}
$RealEstateRoot = (Resolve-Path $RealEstateRoot).Path

if ($DbPassword -eq "") {
    $passwordFile = Join-Path $RealEstateRoot ".deploy-db-password.txt"
    if (Test-Path $passwordFile) {
        $DbPassword = (Get-Content $passwordFile -Raw).Trim()
    }
}

$employeeSqlConnection = "$ProjectId`:$Region`:employee"
if ($UseEmployeeCloudSql) {
    $SqlConnection = $employeeSqlConnection
    if ($DbUser -eq "") {
        $DbUser = "real_estate_app"
    }
    if ($DbPassword -eq "") {
        $passwordFile = Join-Path $RealEstateRoot ".deploy-db-password.txt"
        if (Test-Path $passwordFile) {
            $DbPassword = (Get-Content $passwordFile -Raw).Trim()
        }
    }
    if ($DbPassword -eq "") {
        $DbPassword = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "DB_PASSWORD"
    }
    Write-Host "Using employee Cloud SQL (separate database: $DbName; ceemployee data unchanged)"
} else {
    $SqlConnection = "${RealEstateSqlProject}:${Region}:${RealEstateSqlInstance}"
    if ($DbUser -eq "") { $DbUser = "laravel" }
}

if ($DbPassword -eq "") {
    throw "DbPassword is required (pass -DbPassword, UseEmployeeCloudSql with employee Cloud Run DB_PASSWORD, or create $RealEstateRoot\.deploy-db-password.txt)."
}

if ($ProxySecret -eq "") {
    $ProxySecret = Get-LocalEnvValue -ProjectRoot (Split-Path $PSScriptRoot -Parent) -Key "EMPLOYEE_PORTAL_PROXY_SECRET" -Default ""
}
if ($ProxySecret -eq "") {
    $ProxySecret = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
    if ($ProxySecret) {
        Write-Host "EMPLOYEE_PORTAL_PROXY_SECRET: reusing value from existing $Service Cloud Run service"
    }
}
if ($ProxySecret -eq "") {
    $ProxySecret = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
    if ($ProxySecret) {
        Write-Host "EMPLOYEE_PORTAL_PROXY_SECRET: reusing value from existing $($cfg.Service) Cloud Run service"
    }
}
if ($ProxySecret -eq "" -and $GenerateProxySecret) {
    $ProxySecret = [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
}
if ($ProxySecret -eq "") {
    throw "ProxySecret is required for first deploy. Pass -ProxySecret, set EMPLOYEE_PORTAL_PROXY_SECRET in .env, or use -GenerateProxySecret."
}

$employeePortalApiUrl = "https://employee.careearth.net"
$employeeSiteSyncSecret = Get-LocalEnvValue -ProjectRoot (Split-Path $PSScriptRoot -Parent) -Key "EMPLOYEE_SITE_SYNC_SECRET" -Default ""
if ($employeeSiteSyncSecret -eq "") {
    $employeeSiteSyncSecret = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "EMPLOYEE_SITE_SYNC_SECRET"
}

if ($AppKey -eq "") {
    $AppKey = Get-LocalEnvValue -ProjectRoot $RealEstateRoot -Key "APP_KEY" -Default ""
}
if ($AppKey -eq "") {
    $AppKey = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "APP_KEY"
    if ($AppKey) {
        Write-Host "APP_KEY: reusing value from existing $Service Cloud Run service"
    }
}
if ($AppKey -eq "") {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $AppKey = "base64:$([Convert]::ToBase64String($bytes))"
    Write-Host "APP_KEY: generated new key (first deploy only)"
}

$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

if (-not $UseEmployeeCloudSql) {
    if ($DbHost -eq "") {
        $DbHost = (gcloud sql instances describe $RealEstateSqlInstance --project=$RealEstateSqlProject --format="value(ipAddresses[0].ipAddress)" 2>$null)
    }
    if ([string]::IsNullOrWhiteSpace($DbHost)) {
        throw "Could not resolve Cloud SQL public IP for $RealEstateSqlInstance in $RealEstateSqlProject."
    }
}

Write-Host ""
Write-Host "=== Deploy real-estate portal to employee project ===" -ForegroundColor Cyan
Write-Host "Source  : $RealEstateRoot"
Write-Host "Project : $ProjectId"
Write-Host "Service : $Service (employee DB / employee service are NOT modified)"
Write-Host "Image   : $Image"
Write-Host "Cloud SQL (socket): $SqlConnection"
Write-Host "Database: $DbName (user: $DbUser)"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

if ($UseEmployeeCloudSql -and -not $SkipDbSetup) {
    Write-Host "==> Ensure database exists on employee Cloud SQL: $DbName"
    $dbExists = Invoke-Gcloud sql databases describe $DbName --instance=employee --project=$ProjectId
    if ($dbExists -ne 0) {
        Write-Host "Creating database: $DbName"
        if ((Invoke-Gcloud sql databases create $DbName --instance=employee --project=$ProjectId) -ne 0) {
            throw "Failed to create database $DbName on employee Cloud SQL"
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
        Write-Host "Updating password for Cloud SQL user: $DbUser"
        if ((Invoke-Gcloud sql users set-password $DbUser --instance=employee --project=$ProjectId --password=$DbPassword) -ne 0) {
            throw "Failed to update Cloud SQL user $DbUser password"
        }
    }
} elseif ($UseEmployeeCloudSql -and $SkipDbSetup) {
    Write-Host "Skipping Cloud SQL database/user setup (SkipDbSetup; DB data unchanged)" -ForegroundColor Yellow
}

Write-Host "==> Configure Docker for Artifact Registry"
if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
    throw "docker auth configure failed"
}

if ($UseCloudBuild) {
    Write-Host "==> Cloud Build (remote Docker; local daemon not required)"
    $buildCode = Invoke-Gcloud builds submit $RealEstateRoot `
        --project=$ProjectId `
        --region=$Region `
        --tag=$Image
    if ($buildCode -ne 0) {
        throw "Cloud Build failed"
    }
} else {
    Push-Location $RealEstateRoot
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
}

$internalAppUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
if (-not $internalAppUrl) {
    Write-Host "APP_URL: service not deployed yet; will set internal Cloud Run URL after deploy"
    $internalAppUrl = "http://localhost"
} else {
    Write-Host "APP_URL: $internalAppUrl (internal Cloud Run; proxy rewrites links for users)"
}

$envVars = @{
    APP_ENV = "production"
    APP_DEBUG = "false"
    APP_KEY = $AppKey
    APP_URL = $internalAppUrl
    PORTAL_PUBLIC_URL = $ProxyAppUrl
    SESSION_PATH = "/realestate-portal"
    SESSION_COOKIE = "real_estate_portal_session"
    DB_CONNECTION = "mysql"
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = $DbName
    DB_USERNAME = $DbUser
    DB_PASSWORD = $DbPassword
    LOG_CHANNEL = "stderr"
    SESSION_DRIVER = "database"
    SESSION_SECURE_COOKIE = "true"
    CACHE_STORE = "database"
    QUEUE_CONNECTION = "database"
    ADMIN_GOOGLE_HOSTED_DOMAIN = "careearth.info"
    PORTAL_REFERRER_ENFORCED = "false"
    PORTAL_REFERRER_HOSTS = "employee.careearth.net"
    EMPLOYEE_PORTAL_PROXY_SECRET = $ProxySecret
    EMPLOYEE_PORTAL_API_URL = $employeePortalApiUrl
    EMPLOYEE_PORTAL_LOGIN_URL = ""
    LOCAL_LOGIN_FALLBACK_ENABLED = "false"
    EMPLOYEE_PORTAL_SSO_ENABLED = "true"
}

if ($employeeSiteSyncSecret -ne "") {
    $envVars["EMPLOYEE_SITE_SYNC_SECRET"] = $employeeSiteSyncSecret
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

Grant-PublicInvoker -Service $Service -Region $Region
$invokerGranted = Grant-SameProjectInvoker -TargetService $Service -Region $Region -ProjectId $ProjectId

$serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
if (-not $serviceUrl) {
    throw "Could not resolve Cloud Run service URL for $Service"
}

if ($internalAppUrl -ne $serviceUrl) {
    Write-Host "==> Set APP_URL to internal service URL: $serviceUrl"
    $code = Invoke-Gcloud run services update $Service `
        --project=$ProjectId `
        --region=$Region `
        --update-env-vars "APP_URL=$serviceUrl"

    if ($code -ne 0) {
        throw "Failed to set APP_URL on $Service (exit $code)"
    }
}

if ($SkipEmployeeUpdate) {
    Write-Host ""
    Write-Host "==> Skipping employee Cloud Run update (employee data and service unchanged)" -ForegroundColor Yellow
    $useIdentityToken = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN"
    if ($useIdentityToken -eq "") { $useIdentityToken = "true" }
} else {
    Write-Host ""
    Write-Host "==> Updating employee Cloud Run env (URL only; no DB changes)"
    $useIdentityToken = if ($invokerGranted) { "true" } else { "false" }
    $code = Invoke-Gcloud run services update $cfg.Service `
        --project=$ProjectId `
        --region=$Region `
        --update-env-vars "REAL_ESTATE_PORTAL_INTERNAL_URL=$serviceUrl,EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret,REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN=$useIdentityToken"

    if ($code -ne 0) {
        throw "Failed to update employee Cloud Run env (exit $code)"
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Deploy complete!"
Write-Host "Portal URL (via employee): $ProxyAppUrl"
Write-Host "Internal Cloud Run URL     : $serviceUrl"
Write-Host "Health check               : $serviceUrl/up"
Write-Host ""
Write-Host "Save in employee .env for local dev:"
Write-Host "  REAL_ESTATE_PORTAL_INTERNAL_URL=$serviceUrl"
Write-Host "  EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret"
Write-Host "  REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN=$useIdentityToken"
Write-Host ""
Write-Host "If DB connection fails, ask a ce-realestate-inside-2606st admin to grant"
Write-Host "roles/cloudsql.client to 20660435767-compute@developer.gserviceaccount.com"
Write-Host "========================================"
