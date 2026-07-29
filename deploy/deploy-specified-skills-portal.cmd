@echo off
setlocal
cd /d "%~dp0.."
echo Deploy specified-skills portal into employee GCP project ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy-specified-skills-portal.ps1" %*
exit /b %ERRORLEVEL%
