@echo off
echo ===================================
echo   Lancement VSM Tool Jira - DEMO
echo ===================================

cd /d %~dp0

set APP_PUBLIC=%~dp0public

::Chemin si dossier php dans le projet (version standalone)
set PHP_BIN=%~dp0php\php.exe
::Chemin si dossier php est à la racine du lecteur C:\
set PHP_BIN=C:\php\php.exe

start http://localhost:8080

"%PHP_BIN%" -S localhost:8080 -t "%APP_PUBLIC%"

pause