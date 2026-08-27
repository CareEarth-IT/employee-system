@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0hide-realestate-users-performance-prod.ps1" %*
