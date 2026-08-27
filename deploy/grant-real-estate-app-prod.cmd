@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0grant-real-estate-app-prod.ps1" %*
exit /b %ERRORLEVEL%
