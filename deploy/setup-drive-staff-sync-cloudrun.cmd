@echo off
setlocal
cd /d "%~dp0.."
echo Setting drive staff sync env on employee Cloud Run ...
if "%~1"=="" (
  echo Usage: deploy\setup-drive-staff-sync-cloudrun.cmd "共有秘密鍵"
  exit /b 1
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-drive-staff-sync-cloudrun.ps1" -SyncSecret "%~1"
exit /b %ERRORLEVEL%
