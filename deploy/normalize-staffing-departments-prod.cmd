@echo off
echo Normalizing staffing department values on production Cloud SQL ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0normalize-staffing-departments-prod.ps1" %*
exit /b %ERRORLEVEL%
