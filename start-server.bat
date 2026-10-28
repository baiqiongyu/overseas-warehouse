@echo off
chcp 65001 >nul
rem ============================================================
rem  Warehouse SaaS - local dev server launcher
rem  Usage: double click this file, then open the URLs below.
rem  Requires: PHP 7.4 CLI at PHP_EXE, MySQL on 127.0.0.1:4406
rem ============================================================
set PHP_EXE=D:\wnmp\php\php-7.4.33\php.exe
set PORT=8088

cd /d "%~dp0"

if not exist "%PHP_EXE%" (
  echo [ERROR] PHP not found: %PHP_EXE%
  echo         Edit PHP_EXE in this file to your php.exe path.
  pause
  exit /b 1
)

echo ============================================================
echo  货主门户 : http://127.0.0.1:%PORT%/pages/login.html
echo  运营后台 : http://127.0.0.1:%PORT%/pages/admin/login.html
echo  API 根   : http://127.0.0.1:%PORT%/{customer|admin}
echo ------------------------------------------------------------
echo  Press Ctrl+C to stop the server.
echo ============================================================
echo.

"%PHP_EXE%" -S 127.0.0.1:%PORT% -t public public/router.php
