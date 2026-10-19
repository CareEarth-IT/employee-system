@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync-affiliation-resignation-prod.ps1" %*
exit /b %ERRORLEVEL%
