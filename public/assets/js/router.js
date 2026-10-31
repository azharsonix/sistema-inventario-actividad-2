/**
 * Enrutador por hash: #/panel, #/productos, #/movimientos, #/reporte.
 * Cada vista exporta `meta` ({titulo, fab}), `render(raiz, params, vigente)`
 * y opcionalmente `actualizar()` para refrescar sin reconstruir la vista.
 */
import * as panel from './vistas/panel.js';
import * as productos from './vistas/productos.js';
import * as movimientos from './vistas/movimientos.js';
import * as reporte from './vistas/reporte.js';
import { menosMovimiento } from './ui.js';

const RUTAS = { panel, productos, movimientos, reporte };
const RUTA_INICIAL = 'panel';

let actual = null;
let turno = 0;

export function leerHash() {
  const limpio = location.hash.replace(/^#\/?/, '');
  const [ruta, consulta = ''] = limpio.split('?');
  return { ruta: ruta || RUTA_INICIAL, params: new URLSearchParams(consulta) };
}

/** Actualiza los parámetros de la ruta actual sin disparar una navegación. */
export function reemplazarParams(params) {
  const { ruta } = leerHash();
  const q = params.toString();
  history.replaceState(null, '', `#/${ruta}${q ? `?${q}` : ''}`);
}

export async function navegar({ enfocar = false } = {}) {
  const { ruta, params } = leerHash();
  const vista = RUTAS[ruta];
  if (!vista) {
    location.replace(`#/${RUTA_INICIAL}`);
    return;
  }

  const miTurno = ++turno;
  const vigente = () => miTurno === turno;
  const cambioDeVista = actual?.ruta !== ruta;
  actual = { ruta, vista };

  document.title = `${vista.meta.titulo} · TecnoStock`;
  document.querySelectorAll('.nav__item').forEach((a) => {
    if (a.dataset.ruta === ruta) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  });
  document.getElementById('fab-nuevo').hidden = !vista.meta.fab;

  // Cada render recibe un contenedor nuevo: así los oyentes de la vista
  // anterior desaparecen con su DOM.
  const main = document.getElementById('contenido');
  const raiz = document.createElement('div');
  raiz.className = 'vista';
  raiz.dataset.vista = ruta;
  main.replaceChildren(raiz);
  if (cambioDeVista) {
    window.scrollTo({ top: 0, behavior: 'instant' });
    if (!menosMovimiento()) {
      main.classList.remove('vista-entrando');
      void main.offsetWidth; // reinicia la animación
      main.classList.add('vista-entrando');
    }
  }

  const promesa = vista.render(raiz, params, vigente);
  if (enfocar) raiz.querySelector('.page-title')?.focus({ preventScroll: true });
  await promesa;
}

/** Vuelve a cargar los datos de la vista actual. */
export function refrescar() {
  if (actual?.vista.actualizar) return actual.vista.actualizar();
  return navegar();
}

export const rutaActual = () => actual?.ruta;
