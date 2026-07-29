@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-gcs-bucket.ps1"
if errorlevel 1 exit /b 1
