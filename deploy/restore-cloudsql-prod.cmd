@echo off
echo WARNING: Restores production ceemployee database from backup.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0restore-cloudsql-prod.ps1" %*
exit /b %ERRORLEVEL%
