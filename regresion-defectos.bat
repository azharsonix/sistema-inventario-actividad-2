@echo off
rem Inyecta DEF-01 y DEF-02 en una copia de src y comprueba que las pruebas los detectan.
cd /d "%~dp0"
php scripts\simular-defectos.php
pause
