@echo off
echo Restoring Cloud SQL backup into local XAMPP database ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0restore-local-db.ps1" %*
exit /b %ERRORLEVEL%
