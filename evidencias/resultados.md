## Resultados de pruebas: Ronda 1 - ejecución inicial

**10 de 10 casos aprobados** · 0 no aprobados · 3.697 s

| ID | Tipo | Requisito | Caso | Estado | Tiempo |
|---|---|---|---|---|---|
| CP-01 | Unitaria | RF-01 | Registrar producto con datos válidos normaliza el código y guarda la existencia inicial | ✅ Aprobada | 0.004 s |
| CP-02 | Unitaria | RF-01 | Registrar producto con código duplicado muestra error y no agrega otro producto | ✅ Aprobada | 0.000 s |
| CP-03 | Unitaria | RF-03 | Venta con cantidad disponible descuenta la existencia y registra el movimiento | ✅ Aprobada | 0.000 s |
| CP-04 | Unitaria | RF-03 | Venta mayor a la existencia se rechaza y la existencia no cambia | ✅ Aprobada | 0.000 s |
| CP-05 | Integración | RF-02, RF-03 | Venta guardada en el archivo se conserva al volver a abrir el inventario | ✅ Aprobada | 0.007 s |
| CP-06 | Integración | RF-03 | Venta rechazada dentro de una actualización no modifica el archivo de datos | ✅ Aprobada | 0.001 s |
| CP-07 | Sistema | RF-01, RF-02, RF-03 | Registrar producto, consultarlo y venderlo por HTTP actualiza la existencia | ✅ Aprobada | 0.926 s |
| CP-08 | Sistema | RF-03 | Ventas con cantidad cero, negativa o mayor a la existencia responden 422 y no cambian la existencia | ✅ Aprobada | 0.922 s |
| CP-09 | Aceptación | RF-05 | Al cierre del día el reporte lista para resurtir los productos que quedaron en o bajo su mínimo | ✅ Aprobada | 0.949 s |
| CP-10 | Aceptación | RF-02, RF-04 | Llega mercancía y se vende en mostrador: la consulta de existencias y el historial cuadran | ✅ Aprobada | 0.888 s |

