@echo off
title ComputeCnicos - Auto Sync con GitHub
color 0A

echo ============================================
echo   ComputeCnicos - Sincronizacion en Tiempo Real
echo   Monitoreando cambios en el repositorio remoto
echo   Intervalo: cada 30 segundos
echo   Presiona Ctrl+C para detener
echo ============================================
echo.

:loop
echo [%date% %time%] Verificando cambios remotos...

REM Obtener referencias remotas sin descargar archivos
git fetch origin

REM Comparar HEAD local con origin/main
for /f %%i in ('git rev-parse HEAD') do set LOCAL=%%i
for /f %%i in ('git rev-parse origin/main') do set REMOTE=%%i

if "%LOCAL%"=="%REMOTE%" (
    echo [%date% %time%] Sin cambios. Repositorio actualizado.
) else (
    echo [%date% %time%] *** CAMBIOS DETECTADOS! Actualizando...
    git pull origin main
    echo [%date% %time%] *** Repositorio actualizado exitosamente!
)

echo.
REM Espera 30 segundos usando ping (funciona en cualquier contexto)
ping -n 31 127.0.0.1 > nul
goto loop
