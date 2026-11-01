## Regresión con defectos inyectados

| Defecto | Prioridad | Casos que fallaron | Esperados | ¿Detectado? |
|---|---|---|---|---|
| DEF-01 La venta permite vender más unidades de las que hay y deja la existencia negativa | Alta | CP-04, CP-06, CP-08, CP-15 | CP-04, CP-06, CP-08, CP-15 | Sí |
| DEF-02 El reporte no marca para resurtir un producto cuya existencia es igual a su stock mínimo | Media | CP-09, CP-15 | CP-09, CP-15 | Sí |

**Código corregido:** 15 de 15 casos aprobados.
