@echo off
rem Ejecuta las cuatro suites (CP-01 a CP-10) y abre el reporte de evidencia.
cd /d "%~dp0"
php scripts\ejecutar-pruebas.php --ronda="Ejecucion local"
start "" "evidencias\reporte-pruebas.html"
pause
