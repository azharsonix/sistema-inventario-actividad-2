/**
 * TecnoStock · punto de entrada de la interfaz.
 * Conecta tema, navegación, progreso global, FAB, diálogos y atajos.
 */
import { alCambiarCarga } from './js/api.js';
import { iniciarTema } from './js/tema.js';
import { iniciarRipple, snackbar } from './js/ui.js';
import { iniciarDialogos, abrirProducto } from './js/dialogos.js';
import { navegar, refrescar, rutaActual } from './js/router.js';
import { enfocarBusqueda } from './js/vistas/productos.js';

/* ---------- Fuente de íconos: solo se muestran cuando cargó ---------- */
function vigilarIconos() {
  const raiz = document.documentElement;
  if (!document.fonts?.load) { raiz.classList.add('iconos-listos'); return; }
  document.fonts.load('24px "Material Symbols Outlined"', 'inventory_2')
    .then((fuentes) => { if (fuentes.length) raiz.classList.add('iconos-listos'); })
    .catch(() => { /* sin internet: los botones conservan texto/aria-label */ });
  document.fonts.addEventListener?.('loadingdone', () => {
    if (document.fonts.check('24px "Material Symbols Outlined"', 'inventory_2')) raiz.classList.add('iconos-listos');
  });
}

/* ---------- Progreso lineal global (con pequeño retardo para evitar parpadeos) ---------- */
function iniciarProgreso() {
  const barra = document.getElementById('progreso-global');
  let t = null;
  alCambiarCarga((cargando) => {
    clearTimeout(t);
    if (cargando) t = setTimeout(() => { barra.hidden = false; }, 150);
    else barra.hidden = true;
  });
}

/* ---------- Top app bar: cambia de tono al desplazarse ---------- */
function iniciarTopBar() {
  const barra = document.getElementById('top-bar');
  const alDesplazar = () => barra.classList.toggle('is-scrolled', window.scrollY > 4);
  window.addEventListener('scroll', alDesplazar, { passive: true });
  alDesplazar();
}

function iniciarAtajos() {
  document.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = e.target;
    if (t.closest('input, textarea, select, [contenteditable="true"], dialog[open]')) return;
    if (document.querySelector('dialog[open]')) return;
    e.preventDefault();
    if (rutaActual() !== 'productos') {
      location.hash = '#/productos';
      setTimeout(enfocarBusqueda, 50);
    } else {
      enfocarBusqueda();
    }
  });
}

function iniciarConexion() {
  window.addEventListener('offline', () => snackbar('Sin conexión a internet. Los cambios no se guardarán hasta reconectar.', { tipo: 'error' }));
  window.addEventListener('online', () => {
    snackbar('Conexión restablecida', { tipo: 'info' });
    refrescar();
  });
}

function iniciar() {
  vigilarIconos();
  iniciarTema(document.getElementById('btn-tema'));
  iniciarRipple();
  iniciarProgreso();
  iniciarTopBar();
  iniciarDialogos();
  iniciarAtajos();
  iniciarConexion();

  // El enlace "Saltar al contenido" no debe cambiar el hash (lo usa el enrutador).
  document.querySelector('.skip-link').addEventListener('click', (e) => {
    e.preventDefault();
    const main = document.getElementById('contenido');
    (main.querySelector('.page-title') ?? main).focus();
  });
  document.getElementById('fab-nuevo').addEventListener('click', () => abrirProducto(null));
  document.getElementById('btn-actualizar').addEventListener('click', () => refrescar());
  document.addEventListener('inventario:cambio', () => refrescar());

  window.addEventListener('hashchange', () => navegar({ enfocar: true }));
  if (!location.hash || location.hash === '#' || location.hash === '#/') {
    history.replaceState(null, '', '#/panel');
  }
  navegar();
}

iniciar();
