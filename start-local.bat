@echo off
REM ============================================================
REM  NhoSub - khoi dong MySQL (Laragon) + web server
REM  Chay file nay moi khi may khoi dong lai.
REM ============================================================
set MYSQL_HOME=D:\App\laragon\bin\mysql\mysql-8.0.30-winx64
set PROJ=D:\NhoSub

echo [1/2] Khoi dong MySQL...
start "NhoSub MySQL" /min "%MYSQL_HOME%\bin\mysqld.exe" --defaults-file="%MYSQL_HOME%\my.ini" --console

echo      Cho MySQL san sang...
timeout /t 12 /nobreak >nul

echo [2/2] Khoi dong web server tai http://localhost:8000 ...
cd /d "%PROJ%"
start "NhoSub Web" cmd /k "php artisan serve --port=8000"

echo.
echo Xong. Mo trinh duyet: http://localhost:8000
echo Dang nhap admin: admin / Admin@123456
echo.
pause
