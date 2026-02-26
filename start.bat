@echo off
echo ===================================
echo   Lancement VSM Tool Jira - DEMO
echo ===================================

cd /d %~dp0

set PHP_BIN=%~dp0php\php.exe
set APP_PUBLIC=%~dp0public

start http://localhost:8080

php\php.exe -S localhost:8080 -t public

pause