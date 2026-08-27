@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0migrate-real-estate-prod.ps1" %*
exit /b %ERRORLEVEL%
