@echo off
setlocal
cd /d "%~dp0.."
echo Deploy real-estate portal into employee GCP project ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy-realestate-portal.ps1" %*
exit /b %ERRORLEVEL%
