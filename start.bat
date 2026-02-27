@echo off
echo ===================================
echo   Lancement VSM Tool Jira - DEMO
echo ===================================

cd /d %~dp0

set PHP_BIN=%~dp0php\php.exe
set APP_PUBLIC=%~dp0public

start http://localhost:8080

"%PHP_BIN%" -S localhost:8080 -t "%APP_PUBLIC%"

pause