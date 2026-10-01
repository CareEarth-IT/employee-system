# Restore pre-existing resigned employees from backup CSV snapshots.
# Usage:
#   deploy\restore-resigned-from-backup-prod.ps1 -DryRun
#   deploy\restore-resigned-from-backup-prod.ps1
#   deploy\restore-resigned-from-backup-prod.ps1 -SkipCloudBuild

param(
    [switch]$DryRun,
    [switch]$SkipCloudBuild
)

$ErrorActionPreference = "Stop"

$ProjectId = "ce-gr-employee-info-2606st"
$Region = "asia-northeast1"
$Service = "employee"
$AppUrl = "https://employee.careearth.net"
$JobName = "employee-restore-resigned"
$Image = "${Region}-docker.pkg.dev/${ProjectId}/employee/${Service}:latest"
$Root = Split-Path $PSScriptRoot -Parent
$UsersCsv = Join-Path $Root "storage\app\restore\resigned-users.csv"
$AffiliationsCsv = Join-Path $Root "storage\app\restore\resigned-affiliations.csv"
$ContainerUsers = "deploy/roster-job-data/resigned-users.csv"
$ContainerAffiliations = "deploy/roster-job-data/resigned-affiliations.csv"

Set-Location $Root
. (Join-Path $PSScriptRoot "deploy-common.ps1")

if (-not (Test-Path $UsersCsv)) {
    throw "Users CSV not found: $UsersCsv"
}
if (-not (Test-Path $AffiliationsCsv)) {
    throw "Affiliations CSV not found: $AffiliationsCsv"
}

if ((Invoke-Gcloud config set project $ProjectId) -ne 0) {
    throw "gcloud config failed"
}

Clear-DeployCsvStaging -ProjectRoot $Root
$stagedUsers = Stage-DeployCsv -ProjectRoot $Root -SourceFile $UsersCsv -StagingFileName "resigned-users.csv"
$stagedAff = Stage-DeployCsv -ProjectRoot $Root -SourceFile $AffiliationsCsv -StagingFileName "resigned-affiliations.csv"
Write-Host "Staged: $stagedUsers"
Write-Host "Staged: $stagedAff"

try {
    if (-not $SkipCloudBuild) {
        Write-Host "==> Cloud Build employee image (includes restore CSVs)"
        $buildCode = Invoke-Gcloud builds submit $Root `
            --project=$ProjectId `
            --region=$Region `
            --tag=$Image
        if ($buildCode -ne 0) {
            throw "Cloud Build failed"
        }

        $appKey = Get-LocalAppKey -ProjectRoot $Root
        Write-Host "==> Deploy Cloud Run service"
        $env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL = "1"
        $deployCode = Invoke-CloudRunDeploy `
            -Service $Service `
            -Image $Image `
            -Region $Region `
            -AppUrl $AppUrl `
            -AppKey $appKey `
            -ProjectRoot $Root
        if ($deployCode -ne 0) {
            throw "Cloud Run service deploy failed"
        }
        Grant-PublicInvoker -Service $Service -Region $Region
    } else {
        Write-Host "==> Skip Cloud Build / service deploy"
    }

    $appKey = Get-LocalAppKey -ProjectRoot $Root
    $dbPassword = Get-LocalDbPassword -ProjectRoot $Root
    if (-not $dbPassword) {
        $dbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
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
        "employee:restore-resigned-from-backup",
        $ContainerUsers,
        $ContainerAffiliations
    )
    if ($DryRun) {
        $artisanArgs += "--dry-run"
    }
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
            --task-timeout=60m `
            --memory=512Mi `
            --cpu=1
        if ($jobCode -ne 0) {
            throw "Cloud Run job deploy failed"
        }
    } finally {
        Remove-Item $envFile -Force -ErrorAction SilentlyContinue
    }

    Write-Host "==> Execute job (wait)"
    $executeCode = Invoke-Gcloud run jobs execute $JobName --region=$Region --wait
    if ($executeCode -ne 0) {
        Invoke-Gcloud logging read `
            "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
            --limit=100 `
            --format="value(textPayload)" `
            --freshness=1h
        throw "Cloud Run job execution failed"
    }

    Write-Host ""
    Write-Host "==> Job logs"
    Invoke-Gcloud logging read `
        "resource.type=cloud_run_job AND resource.labels.job_name=$JobName" `
        --limit=150 `
        --format="value(textPayload)" `
        --freshness=1h

    if ($DryRun) {
        Write-Host "dry-run completed. Production DB was not changed."
    } else {
        Write-Host "Resigned restore completed."
    }
} finally {
    Clear-DeployCsvStaging -ProjectRoot $Root
}
