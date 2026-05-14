@echo off
REM =========================================================================
REM  ADOMC - Local development launcher
REM  Starts the Symfony built-in PHP server with safe runtime limits.
REM =========================================================================

setlocal
cd /d "%~dp0"

set HOST=127.0.0.1
set PORT=8000

echo.
echo  ADOMC dev server  ->  http://%HOST%:%PORT%
echo  Press Ctrl+C to stop.
echo.

php -d memory_limit=512M ^
    -d max_execution_time=0 ^
    -d default_socket_timeout=120 ^
    -S %HOST%:%PORT% ^
    -t public

endlocal
