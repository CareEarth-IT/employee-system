# Deploy pre-built specified-skills-portal image (skip docker build/push).
param(
    [string]$Image = "asia-northeast1-docker.pkg.dev/ce-gr-employee-info-2606st/employee/specified-skills-portal:latest"
)

$ErrorActionPreference = "Stop"
. (Join-Path $PSScriptRoot "deploy-common.ps1")

$cfg = Get-DeployConfig
$ProjectId = $cfg.ProjectId
$Region = $cfg.Region
$Service = "specified-skills-portal"
$SqlConnection = "$ProjectId`:$Region`:employee"

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

$DbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
if (-not $DbPassword) {
    throw "DB_PASSWORD not found on $Service"
}

$envVars = @{
    APP_BASE_PATH = "/specified-skills-portal"
    DB_SOCKET = "/cloudsql/$SqlConnection"
    DB_DATABASE = "specific_skills"
    DB_USERNAME = "specific_skills_app"
    DB_PASSWORD = $DbPassword
    EMPLOYEE_PORTAL_PROXY_SECRET = ""
}

$envFile = [System.IO.Path]::GetTempFileName()
try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    Write-Host "==> Deploy Cloud Run: $Service"
    $code = Invoke-Gcloud run deploy $Service `
        --image=$Image `
        --region=$Region `
        --platform=managed `
        --port=8080 `
        --allow-unauthenticated `
        --no-invoker-iam-check `
        --add-cloudsql-instances=$SqlConnection `
        --env-vars-file=$envFile

    if ($code -ne 0) {
        throw "Cloud Run deploy failed"
    }
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

Grant-SameProjectInvoker -TargetService $Service -Region $Region -ProjectId $ProjectId | Out-Null

$serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
if (-not $serviceUrl) {
    throw "Could not resolve service URL"
}

$ProxySecret = Get-CloudRunEnvVar -Service $cfg.Service -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
if (-not $ProxySecret) {
    $ProxySecret = Get-CloudRunEnvVar -Service "real-estate-portal" -Region $Region -Name "EMPLOYEE_PORTAL_PROXY_SECRET"
}
if (-not $ProxySecret) {
    throw "EMPLOYEE_PORTAL_PROXY_SECRET not found"
}

Write-Host "==> Update employee Cloud Run env (URL only)"
$updateCode = Invoke-Gcloud run services update $cfg.Service `
    --project=$ProjectId `
    --region=$Region `
    --update-env-vars "SPECIFIED_SKILLS_PORTAL_INTERNAL_URL=$serviceUrl,EMPLOYEE_PORTAL_PROXY_SECRET=$ProxySecret,SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN=true"

if ($updateCode -ne 0) {
    throw "Failed to update employee env"
}

Write-Host ""
Write-Host "Done."
Write-Host "Portal (via employee): https://employee.careearth.net/specified-skills-portal"
Write-Host "Internal URL         : $serviceUrl"
