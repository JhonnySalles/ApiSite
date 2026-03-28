@echo off
echo.
echo ===========================================
echo   Iniciando testes...
echo ===========================================
echo.

REM Realizar apenas os testes sem o dados padrão do phpunit
REM ./vendor/bin/phpunit --no-output

REM Inicia os testes
./vendor/bin/phpunit

