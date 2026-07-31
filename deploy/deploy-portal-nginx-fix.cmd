@echo off
setlocal
cd /d "%~dp0.."
echo.
echo === 特定技能ポータル nginx 修正を本番反映 ===
echo - データ: 変更なし (マイグレーションのみ)
echo - メール: Cloud Run 上のさくら SMTP 設定を維持
echo.

gcloud auth print-access-token >nul 2>&1
if errorlevel 1 (
    echo gcloud の再ログインが必要です。ブラウザが開きます...
    gcloud auth login
    if errorlevel 1 exit /b 1
)

set DEPLOY_PRESERVE_CLOUD_RUN_MAIL=1
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0docker-deploy.ps1"
exit /b %ERRORLEVEL%
