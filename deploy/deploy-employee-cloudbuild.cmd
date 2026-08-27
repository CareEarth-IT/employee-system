@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy-employee-cloudbuild.ps1" %*
exit /b %ERRORLEVEL%
