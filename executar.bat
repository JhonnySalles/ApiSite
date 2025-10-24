@echo off
set HOST=localhost
set PORT=8000
set DOC_ROOT=public
set ROUTER_SCRIPT=public/index.php

echo.
echo ===========================================
echo   API Iniciada!
echo   Ouvindo em: http://%HOST%:%PORT%
echo   Documentacao disponivel em: http://%HOST%:%PORT%/docs
echo ===========================================
echo.

REM Inicia o servidor PHP
php -S %HOST%:%PORT% -t %DOC_ROOT% %ROUTER_SCRIPT%