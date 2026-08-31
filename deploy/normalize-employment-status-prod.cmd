@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0normalize-employment-status-prod.ps1" %*
exit /b %ERRORLEVEL%
