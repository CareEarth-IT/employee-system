# Build Docker image locally and push to Artifact Registry
# Usage: deploy\docker-deploy.cmd
# Mail: defaults to preserving Cloud Run MAIL_* (do not push unverified local Sakura SMTP).
# To push local .env mail intentionally: $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "0"

$ErrorActionPreference = "Stop"

if ($null -eq $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -or $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -eq "") {
    $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "1"
}

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$ArRepo = "employee"
$AppUrl = "https://employee.careearth.net"

$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host "Project : $ProjectId"
Write-Host "Service : $Service"
Write-Host "Image   : $Image"
Write-Host ""
Write-CodeDeployNotice

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Write-Host "==> Configure Docker for Artifact Registry"
if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
    throw "docker auth configure failed"
}

Write-Host "==> Ensure Artifact Registry repository exists"
$describeCode = Invoke-Gcloud artifacts repositories describe $ArRepo --location=$Region --format="value(name)"
if ($describeCode -ne 0) {
    Write-Host "Creating repository: $ArRepo"
    if ((Invoke-Gcloud artifacts repositories create $ArRepo --repository-format=docker --location=$Region --description="CE-GR employee site") -ne 0) {
        throw "failed to create Artifact Registry repo"
    }
} else {
    Write-Host "Repository already exists: $ArRepo"
}

Write-Host "==> Docker build (may take several minutes)"
Clear-DeployCsvStaging -ProjectRoot $Root
docker build -t $Image .
if ($LASTEXITCODE -ne 0) { throw "docker build failed" }

Write-Host "==> Docker push"
docker push $Image
if ($LASTEXITCODE -ne 0) { throw "docker push failed" }

$appKey = Get-LocalAppKey -ProjectRoot $Root

Write-Host "==> Deploy to Cloud Run"
$deployCode = Invoke-CloudRunDeploy `
    -Service $Service `
    -Image $Image `
    -Region $Region `
    -AppUrl $AppUrl `
    -AppKey $appKey `
    -ProjectRoot $Root

if ($deployCode -ne 0) { throw "gcloud run deploy failed" }

Grant-PublicInvoker -Service $Service -Region $Region

Write-Host ""
Write-Host "Done."
$serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
if ($serviceUrl) {
    Write-Host "Service URL: $serviceUrl"
} else {
    Write-Host "Check URL in Cloud Run console (employee service)."
}
