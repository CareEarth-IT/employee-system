# Send equipment purchase test mails against production Cloud SQL + SMTP.
# Creates one test application in production DB and sends real emails.
#
# Usage:
#   deploy\test-equipment-purchase-mail-prod.cmd
#   deploy\test-equipment-purchase-mail-prod.cmd --skip-build
#
# Examples:
#   deploy\test-equipment-purchase-mail-prod.cmd -Type onsite_over_30k -Department 福岡営業部

param(
    [string]$Type = "onsite_over_30k",
    [string]$Department = "福岡営業部",
    [string]$Applicant = "yuta_masui@careearth.info",
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$ArRepo = "employee"
$AppUrl = "https://employee.careearth.net"
$JobName = "employee-test-equipment-mail"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/${ArRepo}/${Service}:latest"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host "Job      : $JobName"
Write-Host "Type     : $Type"
Write-Host "Dept     : $Department"
Write-Host "Applicant: $Applicant"
Write-Host ""
Write-Host "WARNING: Creates a test application in production DB and sends real emails."
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

if (-not $SkipBuild) {
    Write-Host "==> Configure Docker for Artifact Registry"
    if ((Invoke-Gcloud auth configure-docker "${Region}-docker.pkg.dev" --quiet) -ne 0) {
        throw "docker auth configure failed"
    }

    Clear-DeployCsvStaging -ProjectRoot $Root
    Write-Host "==> Docker build"
    docker build -t $Image .
    if ($LASTEXITCODE -ne 0) { throw "docker build failed" }

    Write-Host "==> Docker push"
    docker push $Image
    if ($LASTEXITCODE -ne 0) { throw "docker push failed" }
} else {
    Write-Host "==> Skip build/push (--skip-build)"
}

$appKey = Get-LocalAppKey -ProjectRoot $Root
$dbPassword = Get-LocalDbPassword -ProjectRoot $Root
if (-not $dbPassword) {
    $dbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
    if ($dbPassword) {
        Write-Host "DB_PASSWORD: reusing value from existing Cloud Run service"
    }
}
if (-not $dbPassword) {
    throw "DB_PASSWORD is not set in .env and could not be read from Cloud Run"
}

Grant-CloudSqlClient -ProjectId $ProjectId

$resolvedAppUrl = Resolve-AppUrl -Service $Service -Region $Region -PreferredUrl $AppUrl
$cfg = Get-DeployConfig

$envVars = Get-CloudRunEnvVars `
    -AppUrl $resolvedAppUrl `
    -CloudSqlConnection $cfg.CloudSqlConnection `
    -DbName $cfg.DbName `
    -DbUser $cfg.DbUser `
    -DbPassword $dbPassword `
    -AppKey $appKey `
    -ProjectRoot $Root

$envVars["RUN_MIGRATIONS"] = "false"
$envVars["RUN_SEED"] = "false"

$artisanArgs = @(
    "artisan",
    "equipment-purchase:test-mail",
    "--type=$Type",
    "--department=$Department",
    "--applicant=$Applicant"
)

$argsJoined = ($artisanArgs | ForEach-Object { $_ -replace ',', '\,' }) -join ','

Write-Host "==> Deploy Cloud Run Job: $JobName"
Write-Host "    php $($artisanArgs -join ' ')"

$envFile = [System.IO.Path]::GetTempFileName()
try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    $jobCode = Invoke-Gcloud run jobs deploy $JobName `
        --image=$Image `
        --region=$Region `
        --set-cloudsql-instances=$($cfg.CloudSqlConnection) `
        --env-vars-file=$envFile `
        --command=php `
        --args=$argsJoined `
        --max-retries=0 `
        --task-timeout=10m `
        --memory=512Mi `
        --cpu=1

    if ($jobCode -ne 0) { throw "Cloud Run job deploy failed" }
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

Write-Host "==> Execute mail test job (wait for completion)"
$executeCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
if ($executeCode -ne 0) {
    Write-Host ""
    Write-Host "Job failed. Recent logs:"
    Invoke-Gcloud logging read `
        "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
        --limit=80 `
        --format="value(textPayload)" `
        --freshness=1h
    throw "Cloud Run job execution failed"
}

Write-Host ""
Write-Host "==> Job logs"
Invoke-Gcloud logging read `
    "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
    --limit=80 `
    --format="value(textPayload)" `
    --freshness=1h

Write-Host ""
Write-Host "Check inboxes (and spam folders):"
Write-Host "  Applicant receipt -> $Applicant"
Write-Host "  Approval request  -> hirotaka_kanayama@careearth.info (Fukuoka branch manager)"
