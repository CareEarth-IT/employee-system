@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-realestate-portal-db.ps1" %*
exit /b %ERRORLEVEL%
