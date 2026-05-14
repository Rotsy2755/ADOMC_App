@echo off
REM ============================================================
REM  ADOMC - Background worker launcher
REM ------------------------------------------------------------
REM  Consumes asynchronous jobs (Pareto, MCDM, sensitivity,
REM  reports) from the Doctrine messenger transport.
REM  Keep this window open during normal use of the application.
REM ============================================================
setlocal
cd /d "%~dp0"

set TIME_LIMIT=1800
set MEMORY_LIMIT=256M

echo  ADOMC worker -- transport: async  (Ctrl+C to stop)
echo  ----------------------------------------------------

:loop
php -d memory_limit=512M ^
    -d max_execution_time=0 ^
    bin\console messenger:consume async ^
    --time-limit=%TIME_LIMIT% ^
    --memory-limit=%MEMORY_LIMIT% ^
    --no-interaction ^
    -v
echo  Worker recycled. Restarting...
goto loop

endlocal
