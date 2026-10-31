# Sistema de Control de Inventario · Actividad 2

[![CI](https://github.com/azharsonix/sistema-inventario-actividad-2/actions/workflows/ci.yml/badge.svg)](https://github.com/azharsonix/sistema-inventario-actividad-2/actions/workflows/ci.yml)

Práctica en equipo de la Universidad Tecnológica de Campeche: requisitos, plan, casos y ejecución de pruebas del sistema que registra productos, consulta existencias y actualiza cantidades con cada venta.

## Entrega

En `docs/`:

- Primera parte: documento con los 8 puntos de la práctica (Word y PDF) y diagramas editables (PowerPoint).
- Segunda parte: documento técnico con la estructura vista en clase (portada, introducción, puntos 1 a 8, conclusiones y anexos), bitácora de ejecución y porcentaje de aprobación por ronda (Word y PDF).

## Requisitos funcionales

| ID | Requisito |
|---|---|
| RF-01 | Registrar productos |
| RF-02 | Consultar existencias |
| RF-03 | Actualizar la existencia con cada venta |
| RF-04 | Registrar entradas de mercancía |
| RF-05 | Reporte de inventario y resurtido |

## Casos de prueba

| Nivel | Casos | Carpeta |
|---|---|---|
| Unitarias | CP-01 a CP-04 | `tests/Unitarias` |
| Integración | CP-05 y CP-06 | `tests/Integracion` |
| Sistema (HTTP) | CP-07 y CP-08 | `tests/Sistema` |
| Aceptación | CP-09 y CP-10 | `tests/Aceptacion` |

## Uso

```bash
composer install
composer test          # los 10 casos
composer evidencia     # cuatro suites + reporte en evidencias/
composer regresion     # inyecta DEF-01 y DEF-02 y comprueba que las pruebas los detectan
php -S localhost:8000 -t public   # API: /api/productos, /api/reporte, /salud
```

## Integración continua

`.github/workflows/ci.yml` se ejecuta en cada push y Pull Request: unitarias (PHP 8.2, 8.3 y 8.4) → integración → sistema → aceptación → regresión con defectos inyectados, y un job final que junta los resultados en el resumen y en el artefacto `evidencia-pruebas`.

## Equipo

| Integrante | Rol |
|---|---|
| Emily Yamilett Pinzón Sánchez | Gestora de proyectos |
| Chuina Guadalupe García Campos | Analista y Tester |
| Pablo Alejandro Magaña Hernández | Desarrollador / DevOps |
| Emmanuel Hernández Barbis | Diseñador |
| Ana Edith Rodríguez Pérez | Cliente (aceptación) |
