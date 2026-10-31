/**
 * Vista Movimientos: historial de entradas y salidas, agrupado por día,
 * con filtro por tipo y por producto.
 */
import { api } from '../api.js';
import { html, icono, pintar, actualizarInsignia } from '../ui.js';
import { fmtNumero, etiquetaDia } from '../formato.js';
import { encabezado, esBajo, estadoVacio, pintarError, itemMovimiento, esqueletoFilas } from '../componentes.js';
import { reemplazarParams } from '../router.js';

export const meta = { titulo: 'Movimientos', fab: false };

const LIMITE = 500;
const estado = { tipo: '', codigo: '', movimientos: [], productos: [] };
let raizActual = null;
let vigenteActual = () => false;
let solicitud = 0;

export async function render(raiz, params, vigente) {
  raizActual = raiz;
  vigenteActual = vigente;
  estado.tipo = ['entrada', 'salida'].includes(params.get('tipo')) ? params.get('tipo') : '';
  estado.codigo = params.get('codigo') ?? '';

  pintar(raiz, html`
    ${encabezado('Movimientos', 'Historial de entradas y salidas de mercancía')}
    <div class="filtros">
      <div class="chip-set" role="group" aria-label="Filtrar por tipo" id="chips-tipo"></div>
      <div class="field field--select" data-field="producto">
        <div class="field__box">
          <select class="field__input" id="filtro-producto" aria-describedby="filtro-producto-ayuda">
            <option value="">Todos los productos</option>
          </select>
          <label class="field__label" for="filtro-producto">Producto</label>
          ${icono('arrow_drop_down', 'field__trailing')}
        </div>
        <p class="field__support" id="filtro-producto-ayuda"></p>
      </div>
    </div>
    <div id="mov-resultados" aria-busy="true">${esqueletoFilas(8)}</div>`);

  pintarChipsTipo();
  raiz.querySelector('#chips-tipo').addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    estado.tipo = chip.dataset.tipo;
    sincronizarUrl();
    pintarChipsTipo();
    raiz.querySelector(`.chip[data-tipo="${estado.tipo}"]`)?.focus();
    pintarLista();
  });
  raiz.querySelector('#filtro-producto').addEventListener('change', (e) => {
    estado.codigo = e.target.value;
    sincronizarUrl();
    cargarMovimientos();
  });

  await Promise.all([cargarProductos(), cargarMovimientos()]);
}

export const actualizar = () => Promise.all([cargarProductos(), cargarMovimientos()]);

function sincronizarUrl() {
  const p = new URLSearchParams();
  if (estado.tipo) p.set('tipo', estado.tipo);
  if (estado.codigo) p.set('codigo', estado.codigo);
  reemplazarParams(p);
}

function pintarChipsTipo() {
  const chip = (valor, texto, ic) => html`
    <button class="chip" type="button" data-tipo="${valor}" aria-pressed="${estado.tipo === valor}">
      ${icono('check', 'chip__check')}${ic ? icono(ic, 'chip__lead') : ''}<span>${texto}</span>
    </button>`;
  pintar(raizActual.querySelector('#chips-tipo'), html`
    ${chip('', 'Todos')}${chip('entrada', 'Entradas', 'download')}${chip('salida', 'Salidas', 'upload')}`);
}

async function cargarProductos() {
  try {
    const productos = await api.productos();
    if (!vigenteActual()) return;
    estado.productos = productos;
    actualizarInsignia(productos.filter(esBajo).length);
    const select = raizActual.querySelector('#filtro-producto');
    const ordenados = [...productos].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
    const existe = !estado.codigo || ordenados.some((p) => p.codigo === estado.codigo);
    pintar(select, html`
      <option value="">Todos los productos</option>
      ${ordenados.map((p) => html`<option value="${p.codigo}">${p.nombre} (${p.codigo})</option>`)}
      ${existe ? '' : html`<option value="${estado.codigo}">${estado.codigo} (eliminado)</option>`}`);
    select.value = estado.codigo;
    pintarLista();
  } catch { /* el selector queda con "Todos"; la lista muestra su propio error */ }
}

async function cargarMovimientos() {
  const miSolicitud = ++solicitud;
  const cont = raizActual.querySelector('#mov-resultados');
  cont.setAttribute('aria-busy', 'true');
  try {
    const movimientos = await api.movimientos({ codigo: estado.codigo, limite: LIMITE });
    if (miSolicitud !== solicitud || !vigenteActual()) return;
    estado.movimientos = movimientos;
    pintarLista();
  } catch (error) {
    if (miSolicitud !== solicitud || !vigenteActual()) return;
    estado.movimientos = null;
    pintarError(cont, error, () => cargarMovimientos());
  } finally {
    if (miSolicitud === solicitud) cont.removeAttribute('aria-busy');
  }
}

function pintarLista() {
  const cont = raizActual?.querySelector('#mov-resultados');
  if (!cont || !Array.isArray(estado.movimientos)) return;
  const nombres = new Map(estado.productos.map((p) => [p.codigo, p.nombre]));
  const lista = estado.tipo ? estado.movimientos.filter((m) => m.tipo === estado.tipo) : estado.movimientos;

  if (!lista.length) {
    const filtrado = estado.tipo || estado.codigo;
    pintar(cont, estadoVacio({
      ilus: filtrado ? 'busqueda' : 'movimientos',
      titulo: filtrado ? 'Sin movimientos con estos filtros' : 'Aún no hay movimientos',
      texto: filtrado ? 'Prueba con otro tipo o producto.' : 'Registra una entrada o salida desde la vista Productos.',
    }));
    return;
  }

  const entradas = lista.filter((m) => m.tipo === 'entrada').reduce((s, m) => s + Number(m.cantidad), 0);
  const salidas = lista.filter((m) => m.tipo === 'salida').reduce((s, m) => s + Number(m.cantidad), 0);

  const grupos = [];
  for (const m of lista) {
    const dia = etiquetaDia(m.fecha);
    const ultimo = grupos[grupos.length - 1];
    if (ultimo && ultimo.dia === dia) ultimo.items.push(m);
    else grupos.push({ dia, items: [m] });
  }

  pintar(cont, html`
    <div class="resumen-chips" role="status">
      <span class="status status--neutral">${icono('swap_vert')}${fmtNumero(lista.length)} ${lista.length === 1 ? 'movimiento' : 'movimientos'}</span>
      ${entradas ? html`<span class="status status--success">${icono('download')}+${fmtNumero(entradas)} piezas</span>` : ''}
      ${salidas ? html`<span class="status status--warning">${icono('upload')}−${fmtNumero(salidas)} piezas</span>` : ''}
    </div>
    <div class="card card--outlined mov-lista">
      ${grupos.map((g) => html`
        <h2 class="list-subheader">${g.dia}</h2>
        <ul class="list" role="list">${g.items.map((m) => itemMovimiento(m, m.nombre ?? nombres.get(m.codigo), { agrupadoPorDia: true }))}</ul>`)}
    </div>
    ${estado.movimientos.length >= LIMITE ? html`<p class="resultados__conteo">Se muestran los ${LIMITE} movimientos más recientes.</p>` : ''}`);
}
