@echo off
REM =====================================================================
REM  START_HERE.bat
REM  ---------------------------------------------------------------------
REM  Starts everything the project needs, in the right order.
REM
REM  Double-click this file before you open the project in a browser.
REM
REM  It starts:
REM    1. MySQL Server 8.0  (port 3307) - the database + MySQL Workbench
REM    2. Apache (XAMPP)                - serves the PHP pages
REM
REM  Then open:  http://localhost/restaurant-stock-management/
REM
REM  Login with the admin credentials you created (see README.md)
REM =====================================================================

echo ==============================================
echo  RESTAURANT FOOD STOCK MANAGEMENT SYSTEM
echo  Starting everything...
echo ==============================================
echo.

REM --- 1. MySQL Server 8.0 (port 3307) ---------------------------
echo [1/2] Checking MySQL Server 8.0 (port 3307)...
netstat -ano | findstr ":3307" | findstr "LISTENING" >nul
if not errorlevel 1 (
    echo       MySQL Server 8.0 is already running.
) else (
    echo       Starting MySQL Server 8.0...
    start "MySQL Server 8.0" /min "C:\mysql-server-8.0\PFiles64\MySQL\MySQL Server 8.0\bin\mysqld.exe" --defaults-file=C:\mysql-server-8.0\my.ini
    timeout /t 5 /nobreak >nul
    echo       MySQL Server 8.0 started.
)

REM --- 2. Apache ------------------------------------------------
echo [2/2] Checking Apache (XAMPP)...
netstat -ano | findstr ":80 " | findstr "LISTENING" >nul
if not errorlevel 1 (
    echo       Apache is already running.
) else (
    echo       Apache is not running - please start it in the XAMPP Control Panel.
)

echo.
echo ==============================================
echo  Ready.
echo.
echo  Open this address in your browser:
echo      http://localhost/restaurant-stock-management/
echo.
echo  Login:  use the admin account you created with setup\create_admin.php
echo ==============================================
echo.
timeout /t 12 /nobreak >nul
start "" "http://localhost/restaurant-stock-management/"
exit
