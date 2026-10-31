/**
 * Utilidades de interfaz: plantillas HTML seguras, snackbar, diálogos,
 * ripple, campos de texto con error e insignias.
 */

/* ---------- Plantillas HTML con escape automático ---------- */
const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ESCAPES[c]);

class HtmlSeguro {
  constructor(texto) { this.texto = texto; }
  toString() { return this.texto; }
}
/** Marca un texto como HTML de confianza (no se escapa). */
export const raw = (texto) => new HtmlSeguro(String(texto));

function aTexto(valor) {
  if (valor instanceof HtmlSeguro) return valor.texto;
  if (Array.isArray(valor)) return valor.map(aTexto).join('');
  if (valor === false || valor === null || valor === undefined) return '';
  return esc(valor);
}

/** Etiqueta de plantilla: escapa cada valor interpolado salvo los marcados con raw()/html. */
export function html(partes, ...valores) {
  let salida = partes[0];
  valores.forEach((v, i) => { salida += aTexto(v) + partes[i + 1]; });
  return new HtmlSeguro(salida);
}

/** Ícono decorativo de Material Symbols. */
export const icono = (nombre, clases = '') => raw(`<span class="icon ${esc(clases)}" aria-hidden="true">${esc(nombre)}</span>`);

/** Reemplaza el contenido de un elemento con una plantilla html``. */
export function pintar(elemento, plantilla) {
  elemento.innerHTML = String(plantilla);
  return elemento;
}

export function debounce(fn, ms = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}

export const menosMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- Ripple ligero ---------- */
const SELECTOR_RIPPLE = '.btn, .icon-btn, .fab, .chip, .nav__item, .list-item--interactive, .card--interactive';

export function iniciarRipple() {
  document.addEventListener('pointerdown', (e) => {
    if (e.button !== 0 || menosMovimiento()) return;
    const objetivo = e.target.closest(SELECTOR_RIPPLE);
    if (!objetivo || objetivo.disabled) return;
    const host = objetivo.classList.contains('nav__item') ? objetivo.querySelector('.nav__indicator') : objetivo;
    if (!host) return;
    const r = host.getBoundingClientRect();
    const tam = Math.max(r.width, r.height) * 2.2;
    const onda = document.createElement('span');
    onda.className = 'ripple';
    onda.style.width = `${tam}px`;
    onda.style.height = `${tam}px`;
    onda.style.left = `${e.clientX - r.left - tam / 2}px`;
    onda.style.top = `${e.clientY - r.top - tam / 2}px`;
    host.classList.add('ripple-host');
    host.appendChild(onda);
    onda.addEventListener('animationend', () => onda.remove(), { once: true });
  }, { passive: true });
}

/* ---------- Snackbar ---------- */
const cola = [];
let snackActivo = false;
let snackTemporizador = null;

/**
 * Muestra un aviso breve.
 * @param {string} mensaje
 * @param {{tipo?: 'exito'|'error'|'info', accion?: string, alAccionar?: Function, duracion?: number}} [opciones]
 */
export function snackbar(mensaje, opciones = {}) {
  cola.push({ mensaje, ...opciones });
  if (!snackActivo) siguienteSnack();
}

function siguienteSnack() {
  const el = document.getElementById('snackbar');
  const item = cola.shift();
  if (!el || !item) { snackActivo = false; return; }
  snackActivo = true;

  const iconos = { exito: 'check_circle', error: 'error', info: 'info' };
  const tipo = item.tipo ?? 'exito';
  el.classList.toggle('snackbar--error', tipo === 'error');
  el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
  el.querySelector('.snackbar__icon').textContent = iconos[tipo] ?? 'info';
  el.querySelector('.snackbar__text').textContent = item.mensaje;
  const accion = el.querySelector('.snackbar__action');
  accion.hidden = !item.accion;
  accion.textContent = item.accion ?? '';
  accion.onclick = () => { item.alAccionar?.(); cerrarSnack(); };
  el.querySelector('.snackbar__close').onclick = cerrarSnack;

  el.classList.add('is-visible');
  requestAnimationFrame(() => {
    if (window.matchMedia('(max-width: 839px)').matches) {
      document.documentElement.style.setProperty('--app-fab-shift', `${el.offsetHeight + 8}px`);
    }
  });
  clearTimeout(snackTemporizador);
  snackTemporizador = setTimeout(cerrarSnack, item.duracion ?? (tipo === 'error' ? 7000 : 4000));
}

function cerrarSnack() {
  const el = document.getElementById('snackbar');
  clearTimeout(snackTemporizador);
  el.classList.remove('is-visible');
  document.documentElement.style.removeProperty('--app-fab-shift');
  setTimeout(siguienteSnack, menosMovimiento() ? 0 : 250);
}

/* ---------- Diálogos ---------- */
const focoPrevio = new WeakMap();

export function abrirDialogo(dlg) {
  focoPrevio.set(dlg, document.activeElement);
  dlg.classList.remove('is-closing');
  if (!dlg.open) dlg.showModal();
}

export function cerrarDialogo(dlg, valor = '') {
  if (!dlg.open || dlg.classList.contains('is-closing')) return;
  const terminar = () => {
    dlg.classList.remove('is-closing');
    dlg.close(valor);
    const previo = focoPrevio.get(dlg);
    if (previo && previo.isConnected && typeof previo.focus === 'function') previo.focus();
    else document.getElementById('contenido')?.focus();
  };
  if (menosMovimiento()) { terminar(); return; }
  dlg.classList.add('is-closing');
  setTimeout(terminar, 190);
}

/** Cierre con Esc, clic en el scrim y botones [data-cerrar], con animación. */
export function prepararDialogo(dlg) {
  dlg.addEventListener('cancel', (e) => { e.preventDefault(); cerrarDialogo(dlg, 'cancelar'); });
  dlg.addEventListener('click', (e) => {
    if (e.target === dlg || e.target.closest('[data-cerrar]')) cerrarDialogo(dlg, 'cancelar');
  });
}

/**
 * Diálogo de confirmación. Resuelve true si el usuario acepta.
 * @param {{titulo: string, mensaje: string, aceptar?: string, icono?: string}} opciones
 */
export function confirmar({ titulo, mensaje, aceptar = 'Aceptar', icono: nombreIcono = 'help' }) {
  const dlg = document.getElementById('dlg-confirmar');
  dlg.querySelector('#dlg-conf-titulo').textContent = titulo;
  dlg.querySelector('#dlg-conf-texto').textContent = mensaje;
  dlg.querySelector('#dlg-conf-aceptar').textContent = aceptar;
  dlg.querySelector('#dlg-conf-icono').textContent = nombreIcono;
  dlg.returnValue = '';
  return new Promise((resolver) => {
    dlg.addEventListener('close', () => resolver(dlg.returnValue === 'aceptar'), { once: true });
    // El formulario es method="dialog": se intercepta para animar el cierre.
    dlg.querySelector('form').onsubmit = (e) => {
      e.preventDefault();
      cerrarDialogo(dlg, e.submitter?.value ?? 'cancelar');
    };
    abrirDialogo(dlg);
  });
}

/* ---------- Campos de texto: errores ---------- */
export function marcarError(form, campo, mensaje) {
  const caja = form.querySelector(`.field[data-field="${campo}"]`);
  if (!caja) return false;
  const input = caja.querySelector('.field__input');
  const ayuda = caja.querySelector('.field__support');
  caja.classList.add('field--error');
  input?.setAttribute('aria-invalid', 'true');
  if (ayuda) ayuda.textContent = mensaje;
  return true;
}

export function limpiarError(caja) {
  if (!caja) return;
  caja.classList.remove('field--error');
  caja.querySelector('.field__input')?.removeAttribute('aria-invalid');
  const ayuda = caja.querySelector('.field__support');
  if (ayuda && ayuda.dataset.ayuda !== undefined) ayuda.textContent = ayuda.dataset.ayuda;
}

export function limpiarErrores(form) {
  form.querySelectorAll('.field').forEach(limpiarError);
  form.querySelectorAll('.banner--error').forEach((b) => { b.hidden = true; });
}

export function mostrarBanner(banner, mensaje) {
  banner.querySelector('.banner__texto').textContent = mensaje;
  banner.hidden = false;
}

export function enfocarPrimerError(form) {
  form.querySelector('.field--error .field__input')?.focus();
}

/* ---------- Insignia de productos por resurtir en la navegación ---------- */
export function actualizarInsignia(cantidad) {
  const insignia = document.getElementById('insignia-resurtir');
  if (!insignia) return;
  const n = Number(cantidad) || 0;
  insignia.hidden = n === 0;
  insignia.textContent = n > 99 ? '99+' : String(n);
  insignia.closest('.nav__item')?.setAttribute('aria-label', n ? `Productos, ${n} por resurtir` : 'Productos');
}

/** Notifica a la aplicación que el inventario cambió (para refrescar la vista). */
export function notificarCambio() {
  document.dispatchEvent(new CustomEvent('inventario:cambio'));
}
