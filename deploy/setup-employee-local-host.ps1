# ローカル開発: hosts + Apache VirtualHost を設定（employee.local）
# 管理者として実行: deploy\setup-employee-local-host.cmd
#
# localhost/employee/public との競合（ec-site 等）を避け、専用 URL で開きます。

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
    Write-Host "ERROR: 管理者権限が必要です。"
    Write-Host "deploy\setup-employee-local-host.cmd を右クリック -> 管理者として実行"
    exit 1
}

Write-Host "==> hosts に employee.local を追加"
$hostsContent = Get-Content $HostsFile -Raw -ErrorAction Stop
if ($hostsContent -notmatch 'employee\.local') {
    Add-Content -Path $HostsFile -Value "`n$HostsEntry"
    Write-Host "  追加: $HostsEntry"
} else {
    Write-Host "  既に登録済み"
}

Write-Host "==> Apache VirtualHost を設定"
if (-not (Test-Path $VhostsFile)) {
    throw "見つかりません: $VhostsFile （XAMPP のパスを確認してください）"
}

$snippet = (Get-Content $VhostSnippet -Raw).Trim()
$vhostsContent = Get-Content $VhostsFile -Raw
if ($vhostsContent -notmatch [regex]::Escape($Marker)) {
    Add-Content -Path $VhostsFile -Value "`n$Marker`n$snippet"
    Write-Host "  httpd-vhosts.conf に追記しました"
} else {
    # 既存の employee.local VirtualHost を最新スニペットで置き換え
    $pattern = '(?ms)# CE-GR employee\.local\s*<VirtualHost \*:80>.*?</VirtualHost>'
    $replacement = "$Marker`r`n$snippet"
    $updated = [regex]::Replace($vhostsContent, $pattern, $replacement)
    if ($updated -eq $vhostsContent) {
        Write-Host "  VirtualHost は既に登録済み（置換パターン不一致のため手動確認）"
    } else {
        Set-Content -Path $VhostsFile -Value $updated -NoNewline -Encoding UTF8
        Write-Host "  VirtualHost を更新しました"
    }
}

Write-Host "==> .env の APP_URL を更新"
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

Write-Host "==> public/.htaccess の RewriteBase を / に更新"
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
Write-Host "次の手順:"
Write-Host "  1. XAMPP Control Panel で Apache を Stop -> Start"
Write-Host "  2. ブラウザで開く: http://employee.local/login"
Write-Host ""
Write-Host "旧 URL (http://localhost/employee/public/...) は使わないでください。"
