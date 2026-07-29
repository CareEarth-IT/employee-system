@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0check-equipment-purchase-approvers-prod.ps1" %*
