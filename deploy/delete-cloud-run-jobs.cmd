@echo off
setlocal
cd /d "%~dp0.."
echo Deleting one-off Cloud Run jobs (employee service is NOT deleted)...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0delete-cloud-run-jobs.ps1"
exit /b %ERRORLEVEL%
