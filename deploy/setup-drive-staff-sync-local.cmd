@echo off
setlocal
cd /d "%~dp0.."
echo Copying drive staff sync settings from employee Cloud Run to .env ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-drive-staff-sync-local.ps1"
exit /b %ERRORLEVEL%
