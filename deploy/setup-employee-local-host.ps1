# 繝ｭ繝ｼ繧ｫ繝ｫ髢狗匱: hosts + Apache VirtualHost 繧定ｨｭ螳夲ｼ・mployee.local・・# 邂｡逅・・→縺励※螳溯｡・ deploy\setup-employee-local-host.cmd
#
# localhost/employee/public 縺ｨ縺ｮ遶ｶ蜷茨ｼ・c-site 遲会ｼ峨ｒ驕ｿ縺代∝ｰら畑 URL 縺ｧ髢九″縺ｾ縺吶・
$ErrorActionPreference = "Stop"

$Root = Split-Path $PSScriptRoot -Parent
$HostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
$HostsEntry = "127.0.0.1`temployee.local"
$VhostsFile = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
$VhostSnippet = Join-Path $PSScriptRoot "xampp-vhost-employee.conf"
$EnvFile = Join-Path $Root ".env"
$Htaccess = Join-Path $Root "public\.htaccess"
$Marker = "# CE-GR employee.local"

function Test-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-Admin)) {
    Write-Host "ERROR: 邂｡逅・・ｨｩ髯舌′蠢・ｦ√〒縺吶・
    Write-Host "deploy\setup-employee-local-host.cmd 繧貞承繧ｯ繝ｪ繝・け -> 邂｡逅・・→縺励※螳溯｡・
    exit 1
}

Write-Host "==> hosts 縺ｫ employee.local 繧定ｿｽ蜉"
$hostsContent = Get-Content $HostsFile -Raw -ErrorAction Stop
if ($hostsContent -notmatch 'employee\.local') {
    Add-Content -Path $HostsFile -Value "`n$HostsEntry"
    Write-Host "  霑ｽ蜉: $HostsEntry"
} else {
    Write-Host "  譌｢縺ｫ逋ｻ骭ｲ貂医∩"
}

Write-Host "==> Apache VirtualHost 繧定ｨｭ螳・
if (-not (Test-Path $VhostsFile)) {
    throw "隕九▽縺九ｊ縺ｾ縺帙ｓ: $VhostsFile ・・AMPP 縺ｮ繝代せ繧堤｢ｺ隱阪＠縺ｦ縺上□縺輔＞・・
}

$snippet = (Get-Content $VhostSnippet -Raw).Trim()
$vhostsContent = Get-Content $VhostsFile -Raw
if ($vhostsContent -notmatch [regex]::Escape($Marker)) {
    Add-Content -Path $VhostsFile -Value "`n$Marker`n$snippet"
    Write-Host "  httpd-vhosts.conf 縺ｫ霑ｽ險倥＠縺ｾ縺励◆"
} else {
    # 譌｢蟄倥・ employee.local VirtualHost 繧呈怙譁ｰ繧ｹ繝九・繝・ヨ縺ｧ鄂ｮ縺肴鋤縺・    $pattern = '(?ms)# CE-GR employee\.local\s*<VirtualHost \*:80>.*?</VirtualHost>'
    $replacement = "$Marker`r`n$snippet"
    $updated = [regex]::Replace($vhostsContent, $pattern, $replacement)
    if ($updated -eq $vhostsContent) {
        Write-Host "  VirtualHost 縺ｯ譌｢縺ｫ逋ｻ骭ｲ貂医∩・育ｽｮ謠帙ヱ繧ｿ繝ｼ繝ｳ荳堺ｸ閾ｴ縺ｮ縺溘ａ謇句虚遒ｺ隱搾ｼ・
    } else {
        Set-Content -Path $VhostsFile -Value $updated -NoNewline -Encoding UTF8
        Write-Host "  VirtualHost 繧呈峩譁ｰ縺励∪縺励◆"
    }
}

Write-Host "==> .env 縺ｮ APP_URL 繧呈峩譁ｰ"
if (Test-Path $EnvFile) {
    $lines = Get-Content $EnvFile
    $updated = $false
    $newLines = foreach ($line in $lines) {
        if ($line -match '^\s*APP_URL=') {
            $updated = $true
            "APP_URL=http://employee.local"
        } else {
            $line
        }
    }
    if (-not $updated) {
        $newLines += "APP_URL=http://employee.local"
    }
    Set-Content -Path $EnvFile -Value $newLines -Encoding UTF8
    Write-Host "  APP_URL=http://employee.local"
}

Write-Host "==> public/.htaccess 縺ｮ RewriteBase 繧・/ 縺ｫ譖ｴ譁ｰ"
$htaccess = Get-Content $Htaccess -Raw
$htaccess = $htaccess -replace 'RewriteBase /employee/public/', 'RewriteBase /'
Set-Content -Path $Htaccess -Value $htaccess -NoNewline -Encoding UTF8

Push-Location $Root
try {
    php artisan config:clear | Out-Host
    php artisan route:clear | Out-Host
} finally {
    Pop-Location
}

Write-Host ""
Write-Host "Done."
Write-Host ""
Write-Host "谺｡縺ｮ謇矩・"
Write-Host "  1. XAMPP Control Panel 縺ｧ Apache 繧・Stop -> Start"
Write-Host "  2. 繝悶Λ繧ｦ繧ｶ縺ｧ髢九￥: http://employee.local/login"
Write-Host ""
Write-Host "譌ｧ URL (http://localhost/employee/public/...) 縺ｯ菴ｿ繧上↑縺・〒縺上□縺輔＞縲・
