## Resultados de pruebas: Ejecución 2026-10-31 15:18

**15 de 15 casos aprobados** · 0 no aprobados · 5.304 s

| ID | Tipo | Requisito | Caso | Estado | Tiempo |
|---|---|---|---|---|---|
| CP-01 | Unitaria | RF-01 | Registrar producto con datos válidos normaliza el código y guarda la existencia inicial | ✅ Aprobada | 0.007 s |
| CP-02 | Unitaria | RF-01 | Registrar producto con código duplicado muestra error y no agrega otro producto | ✅ Aprobada | 0.001 s |
| CP-03 | Unitaria | RF-03 | Venta con cantidad disponible descuenta la existencia y registra el movimiento | ✅ Aprobada | 0.001 s |
| CP-04 | Unitaria | RF-03 | Venta mayor a la existencia se rechaza y la existencia no cambia | ✅ Aprobada | 0.001 s |
| CP-05 | Integración | RF-02, RF-03 | Venta guardada en el archivo se conserva al volver a abrir el inventario | ✅ Aprobada | 0.002 s |
| CP-06 | Integración | RF-03 | Venta rechazada dentro de una actualización no modifica el archivo de datos | ✅ Aprobada | 0.004 s |
| CP-07 | Sistema | RF-01, RF-02, RF-03 | Registrar producto, consultarlo y venderlo por HTTP actualiza la existencia | ✅ Aprobada | 0.859 s |
| CP-08 | Sistema | RF-03 | Ventas con cantidad cero, negativa o mayor a la existencia responden 422 y no cambian la existencia | ✅ Aprobada | 0.892 s |
| CP-09 | Aceptación | RF-05 | Al cierre del día el reporte lista para resurtir los productos que quedaron en o bajo su mínimo | ✅ Aprobada | 0.894 s |
| CP-10 | Aceptación | RF-02, RF-04 | Llega mercancía y se vende en mostrador: la consulta de existencias y el historial cuadran | ✅ Aprobada | 0.858 s |
| CP-11 | Sistema | RF-02 | Consultar un producto que no existe responde 404 con mensaje claro y no cambia el inventario | ✅ Aprobada | 0.891 s |
| CP-12 | Unitaria | RF-01 | Registrar producto con nombre vacío, precio o existencia negativos o código inválido muestra error y no lo guarda | ✅ Aprobada | 0.000 s |
| CP-13 | Integración | RF-04 | Entrada de mercancía guardada en el archivo suma la existencia y queda en el historial | ✅ Aprobada | 0.002 s |
| CP-14 | Integración | RF-02 | Consultar un producto existente leído del archivo devuelve sus datos y su existencia sin modificar el archivo | ✅ Aprobada | 0.008 s |
| CP-15 | Aceptación | RF-01, RF-02, RF-03, RF-04, RF-05 | Proceso completo: alta, consulta, entrada, venta y reporte de resurtido de un producto nuevo | ✅ Aprobada | 0.884 s |

