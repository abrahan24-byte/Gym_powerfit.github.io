@echo off
setlocal

set "SRC=%~dp0"
set "DST=C:\xampp\htdocs\gym-test"

if not exist "C:\xampp\htdocs" (
  echo No se encontro C:\xampp\htdocs. Instala XAMPP o ajusta la ruta en este archivo.
  pause
  exit /b 1
)

echo Copiando PowerFit Gym a XAMPP...
if not exist "%DST%" mkdir "%DST%"

robocopy "%SRC%" "%DST%" /MIR /XD ".git" ".vscode" /XF "fix_php.bat" >nul

echo.
echo Listo. Abre XAMPP e inicia Apache y MySQL.
echo URL principal:
echo http://127.0.0.1:5500/gym-test/
echo.
start "" "http://127.0.0.1:5500/gym-test/"
pause
