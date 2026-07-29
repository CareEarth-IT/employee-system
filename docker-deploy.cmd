@echo off
call "%~dp0deploy\docker-deploy.cmd" %*
exit /b %ERRORLEVEL%
