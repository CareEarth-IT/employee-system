function Invoke-Gcloud {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)

    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $output = & gcloud @Args 2>&1
    $exitCode = $LASTEXITCODE
    $output | ForEach-Object { Write-Host $_ }
    $ErrorActionPreference = $previous
    return $exitCode
}

function Get-LocalAppKey {
    param([string]$ProjectRoot)

    $envFile = Join-Path $ProjectRoot ".env"
    if (Test-Path $envFile) {
        foreach ($line in Get-Content $envFile -Encoding UTF8) {
            if ($line -match '^\s*APP_KEY=(.+)$') {
                $key = $Matches[1].Trim().Trim('"').Trim("'")
                if ($key -and $key -ne "base64:" -and $key.Length -gt 10) {
                    return $key
                }
            }
        }
    }

    Push-Location $ProjectRoot
    try {
        $generated = & php artisan key:generate --show 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "php artisan key:generate failed: $generated"
        }
        return ($generated | Select-Object -Last 1).ToString().Trim()
    } finally {
        Pop-Location
    }
}

function Get-LocalDbPassword {
    param([string]$ProjectRoot)

    $envFile = Join-Path $ProjectRoot ".env"
    if (-not (Test-Path $envFile)) {
        return ""
    }

    foreach ($line in Get-Content $envFile -Encoding UTF8) {
        if ($line -match '^\s*DB_PASSWORD=(.*)$') {
            $value = $Matches[1].Trim().Trim('"').Trim("'")
            if ($value -ne "") {
                return $value
            }
        }
    }

    return ""
}

function Get-CloudRunLatestReadyRevision {
    param(
        [string]$Service,
        [string]$Region
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $revision = & gcloud run services describe $Service --region=$Region --format="value(status.latestReadyRevisionName)" 2>$null
    $exitCode = $LASTEXITCODE
    $ErrorActionPreference = $previous

    if ($exitCode -ne 0 -or -not $revision) {
        return ""
    }

    return [string]$revision.Trim()
}

function Get-CloudRunEnvItemsFromGcloudJson {
    param([string]$Json)

    if (-not $Json) {
        return @()
    }

    $parsed = $Json | ConvertFrom-Json
    $serviceContainers = $parsed.spec.template.spec.containers
    if ($serviceContainers -and $serviceContainers.Count -gt 0 -and $serviceContainers[0].env) {
        return @($serviceContainers[0].env)
    }

    $revisionContainers = $parsed.spec.containers
    if ($revisionContainers -and $revisionContainers.Count -gt 0 -and $revisionContainers[0].env) {
        return @($revisionContainers[0].env)
    }

    return @()
}

function Get-CloudRunEnvVarFromItems {
    param(
        [array]$Items,
        [string]$Name
    )

    foreach ($item in $Items) {
        if ($item.name -eq $Name -and $item.value) {
            return [string]$item.value
        }
    }

    return ""
}

function Get-CloudRunEnvVar {
    param(
        [string]$Service,
        [string]$Region,
        [string]$Name
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"

    $json = & gcloud run services describe $Service --region=$Region --format="json(spec.template.spec.containers[0].env)" 2>$null
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 0 -and $json) {
        $items = Get-CloudRunEnvItemsFromGcloudJson -Json $json
        $value = Get-CloudRunEnvVarFromItems -Items $items -Name $Name
        if ($value -ne "") {
            $ErrorActionPreference = $previous
            return $value
        }
    }

    # Service template can be left with only a subset of vars after a failed partial update.
    # Fall back to the revision that is actually serving traffic.
    $revision = Get-CloudRunLatestReadyRevision -Service $Service -Region $Region
    if ($revision -ne "") {
        $revisionJson = & gcloud run revisions describe $revision --region=$Region --format="json(spec.containers[0].env)" 2>$null
        if ($LASTEXITCODE -eq 0 -and $revisionJson) {
            $revisionItems = Get-CloudRunEnvItemsFromGcloudJson -Json $revisionJson
            $value = Get-CloudRunEnvVarFromItems -Items $revisionItems -Name $Name
            if ($value -ne "") {
                $ErrorActionPreference = $previous
                return $value
            }
        }
    }

    $ErrorActionPreference = $previous
    return ""
}

function Get-LocalEnvValue {
    param(
        [string]$ProjectRoot,
        [string]$Key,
        [string]$Default = ""
    )

    $envFile = Join-Path $ProjectRoot ".env"
    if (-not (Test-Path $envFile)) {
        return $Default
    }

    foreach ($line in Get-Content $envFile -Encoding UTF8) {
        if ($line -match "^\s*$([regex]::Escape($Key))=(.*)$") {
            $value = $Matches[1].Trim().Trim('"').Trim("'")
            if ($value -eq "null") {
                return $Default
            }

            return $value
        }
    }

    return $Default
}

function Get-ProductionMailFromName {
    # Build "CE-Group 社員専用" from code points so deploy works even when .env is not UTF-8.
    $suffix = [string]::Concat(@(
        [char]0x793E,
        [char]0x54E1,
        [char]0x5C02,
        [char]0x7528
    ))

    return "CE-Group $suffix"
}

function Test-MailFromNameCorrupted {
    param([string]$Name)

    if ([string]::IsNullOrWhiteSpace($Name)) {
        return $true
    }

    if ($Name -match '\?{2,}') {
        return $true
    }

    $expected = Get-ProductionMailFromName
    return ($Name -ne $expected)
}

function Get-LocalMailEnvVars {
    param([string]$ProjectRoot)

    $vars = [ordered]@{}
    $keys = @(
        "MAIL_MAILER",
        "MAIL_SCHEME",
        "MAIL_URL",
        "MAIL_HOST",
        "MAIL_PORT",
        "MAIL_USERNAME",
        "MAIL_PASSWORD",
        "MAIL_FROM_ADDRESS",
        "MAIL_FROM_NAME"
    )

    foreach ($key in $keys) {
        $value = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key $key
        if ($value -ne "") {
            $vars[$key] = $value
        }
    }

    if ($vars.Contains("MAIL_FROM_NAME") -and $vars["MAIL_FROM_NAME"] -match '\$\{APP_NAME\}') {
        $appName = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key "APP_NAME" -Default (Get-ProductionMailFromName)
        $vars["MAIL_FROM_NAME"] = $appName
    }

    if (-not $vars.Contains("MAIL_FROM_NAME") -or (Test-MailFromNameCorrupted -Name [string]$vars["MAIL_FROM_NAME"])) {
        $vars["MAIL_FROM_NAME"] = Get-ProductionMailFromName
    }

    return $vars
}

function Test-MailConfiguredForProduction {
    param([string]$ProjectRoot)

    $mailer = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key "MAIL_MAILER" -Default "log"
    $mailHost = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key "MAIL_HOST"
    $username = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key "MAIL_USERNAME"
    $password = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key "MAIL_PASSWORD"
    $from = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key "MAIL_FROM_ADDRESS"

    if ($mailer -eq "log" -or $mailer -eq "array") {
        Write-Host ""
        Write-Host "WARN: MAIL_MAILER=$mailer in .env — password reset emails will NOT be sent."
        Write-Host "Set SMTP in .env, then redeploy. Example:"
        Write-Host "  MAIL_MAILER=smtp"
        Write-Host "  MAIL_HOST=smtp.gmail.com"
        Write-Host "  MAIL_PORT=587"
        Write-Host "  MAIL_USERNAME=yuta_masui@careearth.info"
        Write-Host "  MAIL_PASSWORD=(Google app password)"
        Write-Host "  MAIL_FROM_ADDRESS=yuta_masui@careearth.info"
        Write-Host ""

        return $false
    }

    if (-not $mailHost -or -not $username -or -not $password -or -not $from) {
        Write-Host ""
        Write-Host "WARN: MAIL_HOST / MAIL_USERNAME / MAIL_PASSWORD / MAIL_FROM_ADDRESS is incomplete in .env."
        Write-Host ""

        return $false
    }

    Write-Host "Mail: $mailer via $mailHost (from $from)"

    return $true
}

function Get-DeployConfig {
    return [PSCustomObject]@{
        ProjectId          = "ce-gr-employee-info-2606st"
        Region             = "asia-northeast1"
        Service            = "employee"
        RuntimeServiceAccount = "wp-media-uploader@ce-gr-employee-info-2606st.iam.gserviceaccount.com"
        CloudSqlInstance   = "employee"
        CloudSqlConnection = "ce-gr-employee-info-2606st:asia-northeast1:employee"
        DbName             = "ceemployee"
        DbUser             = "ceemployee"
    }
}

function Get-CloudRunEnvVars {
    param(
        [string]$AppUrl,
        [string]$CloudSqlConnection,
        [string]$DbName,
        [string]$DbUser,
        [string]$DbPassword,
        [string]$AppKey,
        [string]$ProjectRoot = ""
    )

    $cfg = Get-DeployConfig

    $vars = [ordered]@{
        APP_ENV               = "production"
        APP_DEBUG             = "false"
        APP_URL               = $AppUrl
        APP_KEY               = $AppKey
        LOG_CHANNEL           = "stderr"
        SESSION_DRIVER        = "database"
        SESSION_SECURE_COOKIE = "true"
        SESSION_PATH          = "/"
        CACHE_STORE           = "database"
        QUEUE_CONNECTION      = "database"
        DB_CONNECTION         = "mysql"
        DB_PORT               = "3306"
        DB_DATABASE           = $DbName
        DB_USERNAME           = $DbUser
        DB_PASSWORD           = $DbPassword
        DB_SOCKET             = "/cloudsql/$CloudSqlConnection"
        RUN_MIGRATIONS        = "true"
        RUN_SEED              = "false"
        FILESYSTEM_PROFILE_PHOTOS_DISK = "gcs"
        FILESYSTEM_DASHBOARD_CONTENTS_DISK = "gcs"
        FILESYSTEM_BRANDING_DISK = "gcs"
        GOOGLE_CLOUD_PROJECT_ID = $cfg.ProjectId
        GCS_BUCKET            = "$($cfg.ProjectId)-employee-photos"
    }

    if ($ProjectRoot -ne "") {
        $localMail = Get-LocalMailEnvVars -ProjectRoot $ProjectRoot
        $localMailer = if ($localMail.Contains("MAIL_MAILER")) { [string]$localMail["MAIL_MAILER"] } else { "log" }
        # Keep existing Cloud Run SMTP when local mail is still under verification
        # (e.g. Sakura) or when DEPLOY_PRESERVE_CLOUD_RUN_MAIL=1 is set.
        $preserveCloudRunMail = ($env:DEPLOY_PRESERVE_CLOUD_RUN_MAIL -eq "1") -or
            ($localMailer -eq "log") -or
            ($localMailer -eq "array")

        if ($preserveCloudRunMail) {
            $mailKeys = @(
                "MAIL_MAILER",
                "MAIL_SCHEME",
                "MAIL_URL",
                "MAIL_HOST",
                "MAIL_PORT",
                "MAIL_USERNAME",
                "MAIL_PASSWORD",
                "MAIL_FROM_ADDRESS",
                "MAIL_FROM_NAME"
            )

            foreach ($mailKey in $mailKeys) {
                $existing = Get-CloudRunEnvVar -Service $cfg.Service -Region $cfg.Region -Name $mailKey
                if ($existing -ne "") {
                    $vars[$mailKey] = $existing
                }
            }

            if (-not $vars.Contains("MAIL_MAILER") -or [string]$vars["MAIL_MAILER"] -in @("log", "array", "")) {
                Write-Host ""
                Write-Host "WARN: Production MAIL is not configured (MAIL_MAILER=log). Approval / password-reset emails will NOT be sent."
                Write-Host "Set SMTP in .env before deploy, or configure MAIL_* on Cloud Run directly."
                Write-Host ""
            } else {
                Write-Host "Mail: preserving Cloud Run settings ($($vars['MAIL_MAILER']))"
            }
        } else {
            foreach ($entry in $localMail.GetEnumerator()) {
                $vars[$entry.Key] = $entry.Value
            }
            $vars["MAIL_FROM_NAME"] = Get-ProductionMailFromName
        }

        $portalEnvKeys = @(
            "REAL_ESTATE_PORTAL_INTERNAL_URL",
            "REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN",
            "EMPLOYEE_PORTAL_PROXY_SECRET",
            "DISPATCH_PORTAL_INTERNAL_URL",
            "DISPATCH_PORTAL_USE_IDENTITY_TOKEN",
            "SPECIFIED_SKILLS_PORTAL_INTERNAL_URL",
            "SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN",
            "FOOD_PORTAL_INTERNAL_URL",
            "FOOD_PORTAL_USE_IDENTITY_TOKEN",
            "TELECOM_PORTAL_INTERNAL_URL",
            "TELECOM_PORTAL_USE_IDENTITY_TOKEN",
            "BEAUTY_PORTAL_INTERNAL_URL",
            "BEAUTY_PORTAL_USE_IDENTITY_TOKEN",
            "DRIVE_APP_API_URL",
            "EMPLOYEE_SITE_SYNC_SECRET"
        )

        foreach ($portalEnvKey in $portalEnvKeys) {
            $value = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key $portalEnvKey -Default ""
            if ($value -ne "") {
                $vars[$portalEnvKey] = $value
            }
        }

        $wordpressEnvKeys = @(
            "WORDPRESS_SITE_URL",
            "WORDPRESS_SITE_TITLE",
            "WORDPRESS_ADMIN_USER",
            "WORDPRESS_ADMIN_PASSWORD",
            "WORDPRESS_ADMIN_EMAIL"
        )

        foreach ($wordpressEnvKey in $wordpressEnvKeys) {
            $value = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key $wordpressEnvKey -Default ""
            if ($value -eq "") {
                $value = Get-CloudRunEnvVar -Service $cfg.Service -Region $cfg.Region -Name $wordpressEnvKey
            }
            if ($value -ne "") {
                $vars[$wordpressEnvKey] = $value
            }
        }

        if (-not $vars.Contains("WORDPRESS_SITE_URL")) {
            $vars["WORDPRESS_SITE_URL"] = "$AppUrl/wordpress"
        }
        if (-not $vars.Contains("WORDPRESS_SITE_TITLE")) {
            $vars["WORDPRESS_SITE_TITLE"] = "CE-Group お知らせ"
        }
        if (-not $vars.Contains("WORDPRESS_ADMIN_USER")) {
            $vars["WORDPRESS_ADMIN_USER"] = "ceadmin"
        }
        if (-not $vars.Contains("WORDPRESS_ADMIN_EMAIL")) {
            $vars["WORDPRESS_ADMIN_EMAIL"] = "yuta_masui@careearth.info"
        }
        if (-not $vars.Contains("WORDPRESS_ADMIN_PASSWORD")) {
            # First deploy generates one at boot if still empty; prefer setting in .env
            Write-Host "WARN: WORDPRESS_ADMIN_PASSWORD is not set. WordPress will generate one on first install (see Cloud Run logs)."
        } else {
            Write-Host "WordPress: admin user $($vars['WORDPRESS_ADMIN_USER']) -> $($vars['WORDPRESS_SITE_URL'])"
        }

        # WP-Stateless / GCS (SA JSON keys are blocked by org policy; use Cloud Run ADC)
        if (-not $vars.Contains("WORDPRESS_GCS_BUCKET")) {
            $vars["WORDPRESS_GCS_BUCKET"] = "$($cfg.ProjectId)-wp-images"
        }
        if (-not $vars.Contains("WORDPRESS_GCS_MODE")) {
            $vars["WORDPRESS_GCS_MODE"] = "stateless"
        }
        if (-not $vars.Contains("WORDPRESS_GCS_USE_ADC")) {
            $vars["WORDPRESS_GCS_USE_ADC"] = "1"
        }
        Write-Host "WordPress GCS: bucket $($vars['WORDPRESS_GCS_BUCKET']) mode=$($vars['WORDPRESS_GCS_MODE']) adc=$($vars['WORDPRESS_GCS_USE_ADC'])"

        # 経理・人事お問い合わせ（apps/finance-hr）— Cloud SQL 同一インスタンス上の別 DB
        $vars["FINANCE_HR_DB_SOCKET"] = "/cloudsql/$CloudSqlConnection"
        $vars["FINANCE_HR_DB_DATABASE"] = "finance_hr"
        $vars["FINANCE_HR_DB_USERNAME"] = $DbUser
        $vars["FINANCE_HR_DB_PASSWORD"] = $DbPassword
        $vars["FINANCE_HR_WEB_APP_URL"] = "$AppUrl/finance-hr"

        foreach ($financeHrEnvKey in @("FINANCE_HR_SSO_SECRET", "FINANCE_HR_CHAT_WEBHOOK_URL", "FINANCE_HR_CHAT_WEBHOOK_URL_HR")) {
            $value = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key $financeHrEnvKey -Default ""
            if ($value -eq "") {
                $value = Get-CloudRunEnvVar -Service $cfg.Service -Region $cfg.Region -Name $financeHrEnvKey
            }
            if ($value -ne "") {
                $vars[$financeHrEnvKey] = $value
            }
        }
        Write-Host "Finance HR: DB=finance_hr url=$($vars['FINANCE_HR_WEB_APP_URL'])"

        foreach ($devRequestEnvKey in @("DEVELOPMENT_REQUEST_CHAT_WEBHOOK_URL", "DEVELOPMENT_REQUEST_EDITOR_DEPARTMENT_KEYWORDS", "DEVELOPMENT_REQUEST_VIEWER_DEPARTMENT_KEYWORDS")) {
            $value = Get-LocalEnvValue -ProjectRoot $ProjectRoot -Key $devRequestEnvKey -Default ""
            if ($value -eq "") {
                $value = Get-CloudRunEnvVar -Service $cfg.Service -Region $cfg.Region -Name $devRequestEnvKey
            }
            if ($value -ne "") {
                $vars[$devRequestEnvKey] = $value
            }
        }
        if ($vars.Contains("DEVELOPMENT_REQUEST_CHAT_WEBHOOK_URL")) {
            Write-Host "Development requests: Chat webhook configured"
        }

        $existingRealEstateUrl = Get-CloudRunEnvVar -Service $cfg.Service -Region $cfg.Region -Name "REAL_ESTATE_PORTAL_INTERNAL_URL"
        if ($existingRealEstateUrl -ne "" -and [string]$vars["REAL_ESTATE_PORTAL_INTERNAL_URL"] -match 'real-estate-hw4gkpjhea|ce-realestate-inside') {
            Write-Host "REAL_ESTATE_PORTAL_INTERNAL_URL: keeping Cloud Run value ($existingRealEstateUrl); ignoring stale .env"
            $vars["REAL_ESTATE_PORTAL_INTERNAL_URL"] = $existingRealEstateUrl
        }

        if (-not $vars.Contains("DRIVE_APP_API_URL")) {
            $vars["DRIVE_APP_API_URL"] = "https://gas-app-231655548437.asia-northeast1.run.app"
        }

        if (-not $vars.Contains("REAL_ESTATE_PORTAL_INTERNAL_URL")) {
            $vars["REAL_ESTATE_PORTAL_INTERNAL_URL"] = "https://real-estate-portal-3hlnt2gvnq-an.a.run.app"
        }

        if (-not $vars.Contains("REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN")) {
            if ($vars.Contains("EMPLOYEE_PORTAL_PROXY_SECRET") -and [string]$vars["EMPLOYEE_PORTAL_PROXY_SECRET"] -ne "") {
                $vars["REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN"] = "false"
            } else {
                $vars["REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN"] = "true"
            }
        }

        $realEstateInternalUrl = [string]$vars["REAL_ESTATE_PORTAL_INTERNAL_URL"]
        if ($realEstateInternalUrl -match 'xxxxx|example\.|placeholder|\{|\}') {
            throw "REAL_ESTATE_PORTAL_INTERNAL_URL is still a placeholder ($realEstateInternalUrl). Set the real Cloud Run URL in .env (see: gcloud run services describe real-estate --project=ce-realestate-inside-2606st --region=asia-northeast1 --format='value(status.url)')."
        }
    }

    return $vars
}

function Escape-CloudRunEnvYamlValue {
    param([string]$Value)

    $builder = New-Object System.Text.StringBuilder
    foreach ($ch in $Value.ToCharArray()) {
        $code = [int][char]$ch
        switch ($ch) {
            '\' { [void]$builder.Append('\\') }
            '"' { [void]$builder.Append('\"') }
            "`n" { [void]$builder.Append('\n') }
            "`r" { [void]$builder.Append('\r') }
            "`t" { [void]$builder.Append('\t') }
            default {
                if ($code -ge 0x20 -and $code -le 0x7e) {
                    [void]$builder.Append($ch)
                } else {
                    [void]$builder.Append(('\u{0:x4}' -f $code))
                }
            }
        }
    }

    return $builder.ToString()
}

function Write-CloudRunEnvVarsFile {
    param(
        [System.Collections.IDictionary]$Vars,
        [string]$Path
    )

    $lines = New-Object System.Collections.Generic.List[string]
    foreach ($entry in $Vars.GetEnumerator()) {
        $escaped = Escape-CloudRunEnvYamlValue -Value ([string]$entry.Value)
        $lines.Add("$($entry.Key): `"$escaped`"")
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($Path, $lines, $utf8NoBom)
}

function Grant-CloudSqlClient {
    param(
        [string]$ProjectId,
        [string]$ServiceAccountEmail = ""
    )

    if (-not $ServiceAccountEmail) {
        $projectNumber = (gcloud projects describe $ProjectId --format="value(projectNumber)")
        $ServiceAccountEmail = "${projectNumber}-compute@developer.gserviceaccount.com"
    }

    $member = "serviceAccount:$ServiceAccountEmail"
    Write-Host "Granting Cloud SQL Client to $member"
    $code = Invoke-Gcloud projects add-iam-policy-binding $ProjectId `
        --member=$member `
        --role=roles/cloudsql.client

    if ($code -ne 0) {
        Write-Host ""
        Write-Host "Could not grant Cloud SQL Client via gcloud (403?)."
        Write-Host "Grant in GCP Console -> IAM:"
        Write-Host "  Principal: $ServiceAccountEmail"
        Write-Host "  Role: Cloud SQL Client"
        Write-Host ""
    }
}

function Grant-GcsObjectAdmin {
    param(
        [string]$ProjectId,
        [string]$ServiceAccountEmail = ""
    )

    if (-not $ServiceAccountEmail) {
        $projectNumber = (gcloud projects describe $ProjectId --format="value(projectNumber)")
        $ServiceAccountEmail = "${projectNumber}-compute@developer.gserviceaccount.com"
    }

    $member = "serviceAccount:$ServiceAccountEmail"
    Write-Host "Granting Storage Object Admin to $member (employee assets)"
    $code = Invoke-Gcloud projects add-iam-policy-binding $ProjectId `
        --member=$member `
        --role=roles/storage.objectAdmin

    if ($code -ne 0) {
        Write-Host ""
        Write-Host "Could not grant Storage Object Admin via gcloud (403?)."
        Write-Host "Grant in GCP Console -> IAM:"
        Write-Host "  Principal: $ServiceAccountEmail"
        Write-Host "  Role: Storage Object Admin"
        Write-Host ""
    }
}

function Grant-BucketObjectAdmin {
    param(
        [string]$Bucket,
        [string]$ServiceAccountEmail,
        [string[]]$Roles = @("roles/storage.objectAdmin", "roles/storage.legacyBucketReader")
    )

    $member = "serviceAccount:$ServiceAccountEmail"
    foreach ($role in $Roles) {
        Write-Host "Granting $role on gs://$Bucket to $member"
        $code = Invoke-Gcloud storage buckets add-iam-policy-binding "gs://$Bucket" `
            --member=$member `
            --role=$role

        if ($code -ne 0) {
            Write-Host ""
            Write-Host "Could not grant $role on gs://$Bucket."
            Write-Host "Principal: $ServiceAccountEmail"
            Write-Host ""
        }
    }
}

function Grant-WordPressGcsAccess {
    param(
        [string]$ProjectId,
        [string]$ServiceAccountEmail
    )

    Grant-BucketObjectAdmin `
        -Bucket "$ProjectId-wp-images" `
        -ServiceAccountEmail $ServiceAccountEmail
}

function Grant-EmployeePhotosGcsAccess {
    param(
        [string]$ProjectId,
        [string]$ServiceAccountEmail
    )

    Grant-BucketObjectAdmin `
        -Bucket "$ProjectId-employee-photos" `
        -ServiceAccountEmail $ServiceAccountEmail `
        -Roles @("roles/storage.objectAdmin")
}

function Get-CloudRunServiceUrl {
    param(
        [string]$Service,
        [string]$Region
    )

    $url = & gcloud run services describe $Service --region=$Region --format="value(status.url)" 2>$null
    if ($LASTEXITCODE -eq 0 -and $url) {
        return $url
    }
    return $null
}

function Resolve-AppUrl {
    param(
        [string]$Service,
        [string]$Region,
        [string]$PreferredUrl
    )

    $serviceUrl = Get-CloudRunServiceUrl -Service $Service -Region $Region
    if ($serviceUrl) {
        Write-Host "APP_URL: $PreferredUrl"
        Write-Host "Cloud Run URL: $serviceUrl (default hostname until custom domain is used)"
    } else {
        Write-Host "APP_URL: $PreferredUrl (first deploy)"
    }

    return $PreferredUrl
}

function Invoke-CloudRunDeploy {
    param(
        [string]$Service,
        [string]$Image,
        [string]$Region,
        [string]$AppUrl,
        [string]$AppKey,
        [string]$ProjectRoot
    )

    $cfg = Get-DeployConfig
    $dbPassword = Get-LocalDbPassword -ProjectRoot $ProjectRoot
    if (-not $dbPassword) {
        $dbPassword = Get-CloudRunEnvVar -Service $Service -Region $Region -Name "DB_PASSWORD"
        if ($dbPassword) {
            Write-Host "DB_PASSWORD: reusing value from existing Cloud Run service"
        }
    }
    if (-not $dbPassword) {
        throw @"
DB_PASSWORD is not set in .env

Run deploy\setup-cloudsql.cmd after setting DB_PASSWORD in .env
"@
    }

    Grant-CloudSqlClient `
        -ProjectId $cfg.ProjectId `
        -ServiceAccountEmail $cfg.RuntimeServiceAccount
    Grant-WordPressGcsAccess `
        -ProjectId $cfg.ProjectId `
        -ServiceAccountEmail $cfg.RuntimeServiceAccount
    Grant-EmployeePhotosGcsAccess `
        -ProjectId $cfg.ProjectId `
        -ServiceAccountEmail $cfg.RuntimeServiceAccount

    Test-MailConfiguredForProduction -ProjectRoot $ProjectRoot | Out-Null

    $resolvedAppUrl = Resolve-AppUrl -Service $Service -Region $Region -PreferredUrl $AppUrl
    $envVars = Get-CloudRunEnvVars `
        -AppUrl $resolvedAppUrl `
        -CloudSqlConnection $cfg.CloudSqlConnection `
        -DbName $cfg.DbName `
        -DbUser $cfg.DbUser `
        -DbPassword $dbPassword `
        -AppKey $AppKey `
        -ProjectRoot $ProjectRoot

    $envFile = [System.IO.Path]::GetTempFileName()
    try {
        Write-CloudRunEnvVarsFile -Vars $envVars -Path $envFile

        Write-Host "DB source: Cloud SQL ($($cfg.CloudSqlConnection))"

        $deployArgs = @(
            "run", "deploy", $Service,
            "--image=$Image",
            "--region=$Region",
            "--platform=managed",
            "--port=8080",
            "--allow-unauthenticated",
            "--no-invoker-iam-check",
            "--service-account=$($cfg.RuntimeServiceAccount)",
            "--add-cloudsql-instances=$($cfg.CloudSqlConnection)",
            "--env-vars-file=$envFile"
        )

        $deployCode = Invoke-Gcloud @deployArgs
        if ($deployCode -ne 0) {
            return $deployCode
        }

        return 0
    } finally {
        Remove-Item $envFile -Force -ErrorAction SilentlyContinue
    }
}

function Grant-SameProjectInvoker {
    param(
        [string]$TargetService,
        [string]$Region,
        [string]$ProjectId
    )

    $projectNumber = (gcloud projects describe $ProjectId --format="value(projectNumber)")
    $computeSa = "serviceAccount:${projectNumber}-compute@developer.gserviceaccount.com"

    Write-Host "Granting $TargetService run.invoker to $computeSa (same project)"
    $code = Invoke-Gcloud run services add-iam-policy-binding $TargetService `
        --region=$Region `
        --project=$ProjectId `
        --member=$computeSa `
        --role=roles/run.invoker

    if ($code -ne 0) {
        Write-Host ""
        Write-Host "Could not grant same-project invoker via gcloud."
        Write-Host "Console: Cloud Run -> $TargetService -> Permissions -> Grant access"
        Write-Host "  Principal: ${projectNumber}-compute@developer.gserviceaccount.com"
        Write-Host "  Role: Cloud Run Invoker"
        Write-Host ""
    }

    return ($code -eq 0)
}

function Grant-PublicInvoker {
    param(
        [string]$Service,
        [string]$Region
    )

    Write-Host "Granting public access (run.invoker)"
    $code = Invoke-Gcloud run services add-iam-policy-binding $Service `
        --region=$Region `
        --member=allUsers `
        --role=roles/run.invoker

    if ($code -ne 0) {
        Write-Host ""
        Write-Host "Could not grant public access via gcloud."
        Write-Host "In Cloud Run console: employee -> Security -> Allow unauthenticated invocations"
        Write-Host ""
    }
}

$script:DeployCsvStagingRelativeDir = "database/imports/.deploy-staging"

function Get-DeployCsvStagingDir {
    param([string]$ProjectRoot)

    return Join-Path $ProjectRoot $script:DeployCsvStagingRelativeDir
}

function Stage-DeployCsv {
    param(
        [string]$ProjectRoot,
        [string]$SourceFile,
        [string]$StagingFileName
    )

    $dir = Get-DeployCsvStagingDir -ProjectRoot $ProjectRoot
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $dest = Join-Path $dir $StagingFileName
    Copy-Item $SourceFile $dest -Force

    return ($script:DeployCsvStagingRelativeDir -replace '\\', '/') + "/" + $StagingFileName
}

function Clear-DeployCsvStaging {
    param([string]$ProjectRoot)

    $dir = Get-DeployCsvStagingDir -ProjectRoot $ProjectRoot
    if (Test-Path $dir) {
        Remove-Item $dir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

function Write-CodeDeployNotice {
    Write-Host "Code deploy only:"
    Write-Host "  - HR CSV files are NOT included in the Docker image"
    Write-Host "  - No CSV import / joined_at sync runs automatically"
    Write-Host "  - DB schema: migrations only (RUN_MIGRATIONS=true, RUN_SEED=false)"
    Write-Host "  - Existing users / profiles / affiliations are NOT overwritten by code deploy"
    Write-Host "  - import_locked protects app-edited data from bulk CSV import"
    Write-Host ""
}
