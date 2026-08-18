# Grant specific_skills_app on production Cloud SQL via Cloud Run job (unix socket).
# Avoids Cloud Shell mysql client caching_sha2_password issues.
# Does NOT modify ceemployee database contents.
#
# Usage:
#   deploy\grant-specific-skills-app-prod.cmd -RootPassword "your-root-password"
#   deploy\grant-specific-skills-app-prod.cmd -RootPassword "..." -SyncPortalPassword

param(
    [Parameter(Mandatory = $true)]
    [string]$RootPassword,
    [switch]$SyncPortalPassword
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$PortalService = "specified-skills-portal"
$JobName = "employee-grant-specific-skills"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/employee:latest"

$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

. (Join-Path $PSScriptRoot "deploy-common.ps1")

Write-Host ""
Write-Host "=== Grant specific_skills_app (Cloud Run job) ===" -ForegroundColor Cyan
Write-Host "Target DB: specific_skills only (ceemployee data unchanged)"
Write-Host ""

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed. Run: gcloud auth login"
}

$cfg = Get-DeployConfig

Write-Host "==> Set Cloud SQL root password on instance: $($cfg.CloudSqlInstance)"
foreach ($rootHost in @("", "%")) {
    $hostArgs = @()
    if ($rootHost -ne "") {
        $hostArgs = @("--host=$rootHost")
    }
    $pwCode = Invoke-Gcloud sql users set-password root `
        --instance=$($cfg.CloudSqlInstance) `
        --project=$ProjectId `
        @hostArgs `
        --password=$RootPassword
    if ($pwCode -ne 0) {
        throw "Failed to set Cloud SQL root password (host=$rootHost)"
    }
}

$grantScriptPath = Join-Path $PSScriptRoot "scripts\grant-specific-skills-app.php"
if (-not (Test-Path $grantScriptPath)) {
    throw "Grant script not found: $grantScriptPath"
}

# eval() cannot run a <?php prefix; ship body only (no Docker rebuild needed).
$grantScriptBody = (Get-Content $grantScriptPath -Raw) -replace '^\s*<\?php\s*', ''
$grantScriptB64 = [Convert]::ToBase64String(
    [System.Text.Encoding]::UTF8.GetBytes($grantScriptBody)
)

$envVars = @{
    DB_SOCKET = "/cloudsql/$($cfg.CloudSqlConnection)"
    GRANT_MYSQL_USER = "root"
    GRANT_MYSQL_PASSWORD = $RootPassword
    GRANT_SCRIPT_B64 = $grantScriptB64
}

# No spaces: gcloud --args splits on whitespace when unquoted.
$argsJoined = "-r,eval(base64_decode(getenv('GRANT_SCRIPT_B64')));"

$envFile = [System.IO.Path]::GetTempFileName()

try {
    Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

    Write-Host "==> Deploy job: $JobName"
    $jobCode = Invoke-Gcloud run jobs deploy $JobName `
        --image=$Image `
        --region=$Region `
        --set-cloudsql-instances=$($cfg.CloudSqlConnection) `
        --env-vars-file=$envFile `
        --command=php `
        "--args=$argsJoined" `
        --max-retries=0 `
        --task-timeout=5m `
        --memory=512Mi `
        --cpu=1

    if ($jobCode -ne 0) { throw "job deploy failed" }

    Write-Host "==> Execute job"
    $execCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
    if ($execCode -ne 0) {
        Write-Host ""
        Write-Host "Job failed. Recent logs:"
        Invoke-Gcloud logging read `
            "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
            --limit=30 `
            --format="value(textPayload)" `
            --freshness=1h
        throw "job execution failed"
    }

    Write-Host ""
    Write-Host "==> Job logs"
    Invoke-Gcloud logging read `
        "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
        --limit=30 `
        --format="value(textPayload)" `
        --freshness=1h
} finally {
    Remove-Item $envFile -Force -ErrorAction SilentlyContinue
}

if ($SyncPortalPassword) {
    $reposRoot = Split-Path $Root -Parent
    $passwordFile = Join-Path $reposRoot "specific_skills\.deploy-db-password.txt"
    if (-not (Test-Path $passwordFile)) {
        throw "Password file not found: $passwordFile (run import or setup user first)"
    }
    $portalPassword = (Get-Content $passwordFile -Raw).Trim()
    if ($portalPassword -eq "") {
        throw "specific_skills_app password file is empty"
    }

    Write-Host ""
    Write-Host "==> Update $PortalService DB_PASSWORD on Cloud Run"
    $revision = Get-CloudRunLatestReadyRevision -Service $PortalService -Region $Region
    if ($revision -eq "") {
        throw "Could not resolve latest ready revision for $PortalService"
    }

    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $revisionJson = & gcloud run revisions describe $revision `
        --region=$Region `
        --project=$ProjectId `
        --format="json(spec.containers[0].env)" 2>$null
    $ErrorActionPreference = $previous
    if ($LASTEXITCODE -ne 0 -or -not $revisionJson) {
        throw "Could not read env vars from revision $revision"
    }

    $portalEnv = @{}
    foreach ($item in (Get-CloudRunEnvItemsFromGcloudJson -Json $revisionJson)) {
        if ($item.name -and $null -ne $item.value) {
            $portalEnv[[string]$item.name] = [string]$item.value
        }
    }
    if ($portalEnv.Count -eq 0) {
        throw "No env vars found on revision $revision"
    }
    $portalEnv["DB_PASSWORD"] = $portalPassword

    $portalEnvFile = [System.IO.Path]::GetTempFileName()
    try {
        Write-CloudRunEnvVarsFile -Vars $portalEnv -Path $portalEnvFile
        $code = Invoke-Gcloud run services update $PortalService `
            --project=$ProjectId `
            --region=$Region `
            --env-vars-file=$portalEnvFile
        if ($code -ne 0) {
            throw "Failed to update $PortalService DB_PASSWORD"
        }
        Write-Host "  OK: portal service password synced"
    } finally {
        Remove-Item $portalEnvFile -Force -ErrorAction SilentlyContinue
    }
}

Write-Host ""
Write-Host "Done."
Write-Host ""
