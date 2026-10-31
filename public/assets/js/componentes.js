/**
 * Piezas de interfaz reutilizadas por varias vistas.
 */
import { html, icono, pintar } from './ui.js';
import { ilustracion } from './ilustraciones.js';
import { fmtFecha, fmtNumero, fmtRelativa, fmtHora } from './formato.js';

/** true si el producto está en o por debajo de su stock mínimo. */
export const esBajo = (p) =>
  typeof p.bajoStockMinimo === 'boolean' ? p.bajoStockMinimo : Number(p.existencia) <= Number(p.stockMinimo);

export const categoriaDe = (p) => (p.categoria && String(p.categoria).trim()) || 'Sin categoría';

/** Etiqueta de estado de existencia. */
export function estadoStock(p) {
  if (Number(p.existencia) === 0) return html`<span class="status status--error">${icono('block')}Agotado</span>`;
  if (esBajo(p)) return html`<span class="status status--error">${icono('warning')}Stock bajo</span>`;
  return '';
}

/** Encabezado de página (h1 enfocable al navegar). */
export function encabezado(titulo, subtitulo, acciones = '') {
  return html`<header class="page-header">
    <div class="page-header__text">
      <h1 class="page-title" tabindex="-1">${titulo}</h1>
      ${subtitulo ? html`<p class="page-subtitle">${subtitulo}</p>` : ''}
    </div>
    ${acciones ? html`<div class="page-header__actions">${acciones}</div>` : ''}
  </header>`;
}

/** Estado vacío ilustrado. */
export function estadoVacio({ ilus = 'caja', titulo, texto, accion, compacto = false }) {
  return html`<div class="empty ${compacto ? 'empty--compact' : ''}">
    ${ilustracion(ilus)}
    <h2 class="empty__title">${titulo}</h2>
    ${texto ? html`<p class="empty__text">${texto}</p>` : ''}
    ${accion ? html`<button class="btn ${accion.estilo ?? 'btn--filled'}" type="button" data-accion="${accion.id}">${icono(accion.icono ?? 'add')}${accion.texto}</button>` : ''}
  </div>`;
}

/** Pinta un estado de error de red/servidor con botón Reintentar. */
export function pintarError(contenedor, error, reintentar) {
  const red = error?.red;
  pintar(contenedor, html`<div class="empty" role="alert">
    ${ilustracion('error')}
    <h2 class="empty__title">${red ? 'Sin conexión con el servidor' : 'No se pudo cargar la información'}</h2>
    <p class="empty__text">${error?.message ?? 'Ocurrió un error inesperado.'}</p>
    <button class="btn btn--filled" type="button" data-reintentar>${icono('refresh')}Reintentar</button>
  </div>`);
  contenedor.querySelector('[data-reintentar]').addEventListener('click', reintentar);
}

/** Elemento de lista para un movimiento de inventario. */
export function itemMovimiento(m, nombre, { agrupadoPorDia = false } = {}) {
  const entrada = m.tipo === 'entrada';
  const nombreVisible = nombre ?? m.nombre ?? 'Producto eliminado';
  return html`<li class="list-item mov mov--${entrada ? 'entrada' : 'salida'}">
    <span class="list-item__leading mov__avatar">${icono(entrada ? 'download' : 'upload')}</span>
    <div class="list-item__content">
      <p class="list-item__headline"><span class="visually-hidden">${entrada ? 'Entrada de ' : 'Salida de '}</span>${nombreVisible}<span class="codigo">${m.codigo}</span></p>
      <p class="list-item__supporting">${m.motivo || (entrada ? 'Entrada' : 'Salida')} · quedan ${fmtNumero(m.existenciaResultante)}</p>
    </div>
    <div class="list-item__trailing">
      <span class="mov__cantidad" aria-label="${entrada ? 'más' : 'menos'} ${fmtNumero(m.cantidad)} piezas">${entrada ? '+' : '−'}${fmtNumero(m.cantidad)}</span>
      <time class="mov__hora" datetime="${m.fecha}" title="${fmtFecha(m.fecha)}">${fmtRelativa(m.fecha)}</time>
      ${agrupadoPorDia ? html`<span class="mov__fecha">${fmtHora(m.fecha)}</span>` : html`<span class="mov__fecha">${fmtFecha(m.fecha)}</span>`}
    </div>
  </li>`;
}

/** Barra de progreso determinada (existencia frente a mínimo). */
export function medidor(valor, maximo, variante = '') {
  const pct = maximo > 0 ? Math.max(0, Math.min(100, (valor / maximo) * 100)) : 0;
  return html`<div class="progress ${variante}" aria-hidden="true"><div class="progress__bar" style="width:${pct.toFixed(1)}%"></div></div>`;
}

export function esqueletoKpis() {
  return html`<div class="kpis">${[1, 2, 3, 4].map(() => html`<div class="skeleton sk-kpi"></div>`)}</div>`;
}

export function esqueletoFilas(n = 6) {
  return html`<div aria-hidden="true">${Array.from({ length: n }, () => html`<div class="skeleton sk-fila"></div>`)}</div>`;
}
