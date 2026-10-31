/**
 * Vista Productos: búsqueda, filtros por categoría, tabla (escritorio)
 * o tarjetas (móvil) y acciones por producto.
 */
import { api } from '../api.js';
import { html, icono, pintar, debounce, actualizarInsignia } from '../ui.js';
import { fmtMoneda, fmtNumero } from '../formato.js';
import { encabezado, esBajo, estadoStock, estadoVacio, pintarError, esqueletoFilas, categoriaDe } from '../componentes.js';
import { abrirProducto, abrirMovimiento, eliminarProducto } from '../dialogos.js';
import { reemplazarParams } from '../router.js';

export const meta = { titulo: 'Productos', fab: true };

const estado = { q: '', categoria: '', soloBajo: false, productos: [], categorias: [] };
let raizActual = null;
let vigenteActual = () => false;
let solicitud = 0;

export async function render(raiz, params, vigente) {
  raizActual = raiz;
  vigenteActual = vigente;
  estado.q = params.get('q') ?? '';
  estado.categoria = params.get('categoria') ?? '';
  estado.soloBajo = params.get('filtro') === 'bajo';

  pintar(raiz, html`
    ${encabezado('Productos', 'Catálogo y existencias de la tienda')}
    <div class="toolbar" role="search">
      <div class="field field--leading field--trailing" data-field="buscar">
        <div class="field__box">
          ${icono('search', 'field__leading')}
          <input class="field__input" id="buscar-producto" type="search" placeholder=" " autocomplete="off"
                 spellcheck="false" value="${estado.q}" aria-describedby="buscar-ayuda" enterkeyhint="search">
          <label class="field__label" for="buscar-producto">Buscar por código o nombre</label>
          <button class="icon-btn field__trailing" type="button" id="limpiar-busqueda" aria-label="Limpiar búsqueda" ${estado.q ? '' : 'hidden'}>
            ${icono('close')}
          </button>
        </div>
        <p class="field__support solo-escritorio" id="buscar-ayuda">Atajo: tecla «/»</p>
      </div>
      <div class="chip-set" role="group" aria-label="Filtrar por categoría" id="chips-categorias"></div>
    </div>
    <div id="resultados" aria-busy="true">${esqueletoFilas(6)}</div>`);

  const buscar = raiz.querySelector('#buscar-producto');
  const limpiar = raiz.querySelector('#limpiar-busqueda');
  const buscarConRetardo = debounce(() => {
    estado.q = buscar.value.trim();
    sincronizarUrl();
    cargarProductos();
  }, 300);
  buscar.addEventListener('input', () => {
    limpiar.hidden = buscar.value === '';
    buscarConRetardo();
  });
  buscar.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && buscar.value) { e.stopPropagation(); limpiar.click(); }
  });
  limpiar.addEventListener('click', () => {
    buscar.value = '';
    limpiar.hidden = true;
    estado.q = '';
    sincronizarUrl();
    cargarProductos();
    buscar.focus();
  });
  raiz.querySelector('#chips-categorias').addEventListener('click', alElegirChip);
  raiz.querySelector('#resultados').addEventListener('click', alAccionar);

  pintarChips();
  await Promise.all([cargarCategorias(), cargarProductos()]);
}

export const actualizar = () => Promise.all([cargarCategorias(), cargarProductos()]);

function sincronizarUrl() {
  const p = new URLSearchParams();
  if (estado.q) p.set('q', estado.q);
  if (estado.categoria) p.set('categoria', estado.categoria);
  if (estado.soloBajo) p.set('filtro', 'bajo');
  reemplazarParams(p);
}

async function cargarCategorias() {
  try {
    const categorias = await api.categorias();
    if (!vigenteActual()) return;
    estado.categorias = categorias;
    pintarChips();
  } catch { /* los chips son secundarios; el error se ve en la lista */ }
}

function pintarChips() {
  const contenedor = raizActual?.querySelector('#chips-categorias');
  if (!contenedor) return;
  const lista = [...estado.categorias];
  if (estado.categoria && !lista.includes(estado.categoria)) lista.push(estado.categoria);
  const chip = (valor, texto, activo) => html`
    <button class="chip" type="button" data-categoria="${valor}" aria-pressed="${activo}">
      ${icono('check', 'chip__check')}<span>${texto}</span>
    </button>`;
  pintar(contenedor, html`
    ${chip('', 'Todas', !estado.categoria)}
    ${lista.map((c) => chip(c, c, estado.categoria === c))}
    <button class="chip" type="button" data-bajo aria-pressed="${estado.soloBajo}">
      ${icono('check', 'chip__check')}${icono('warning', 'chip__lead')}<span>Stock bajo</span>
    </button>`);
}

function alElegirChip(e) {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  if (chip.hasAttribute('data-bajo')) estado.soloBajo = !estado.soloBajo;
  else estado.categoria = chip.dataset.categoria;
  sincronizarUrl();
  pintarChips();
  raizActual.querySelector(chip.hasAttribute('data-bajo') ? '[data-bajo]' : `.chip[data-categoria="${CSS.escape(estado.categoria)}"]`)?.focus();
  cargarProductos();
}

async function cargarProductos() {
  const miSolicitud = ++solicitud;
  const resultados = raizActual?.querySelector('#resultados');
  if (!resultados) return;
  resultados.setAttribute('aria-busy', 'true');
  try {
    const productos = await api.productos({ q: estado.q, categoria: estado.categoria });
    if (miSolicitud !== solicitud || !vigenteActual()) return;
    estado.productos = productos;
    if (!estado.q && !estado.categoria) actualizarInsignia(productos.filter(esBajo).length);
    pintarResultados(resultados);
  } catch (error) {
    if (miSolicitud !== solicitud || !vigenteActual()) return;
    pintarError(resultados, error, () => cargarProductos());
  } finally {
    if (miSolicitud === solicitud) resultados.removeAttribute('aria-busy');
  }
}

function accionesFila(p, { compacto = false } = {}) {
  const sinExistencia = Number(p.existencia) === 0;
  return html`
    <button class="icon-btn" type="button" data-accion="editar" data-codigo="${p.codigo}" aria-label="Ver o editar ${p.nombre}" title="Ver / editar">${icono('edit')}</button>
    <button class="icon-btn icon-btn--success" type="button" data-accion="entrada" data-codigo="${p.codigo}" aria-label="Registrar entrada de ${p.nombre}" title="Registrar entrada">${icono('add_circle')}</button>
    <button class="icon-btn icon-btn--warning" type="button" data-accion="salida" data-codigo="${p.codigo}" aria-label="Registrar salida de ${p.nombre}" title="${sinExistencia ? 'Sin existencia' : 'Registrar salida'}" ${sinExistencia ? 'disabled' : ''}>${icono('do_not_disturb_on')}</button>
    ${compacto ? html`<span class="spacer"></span>` : ''}
    <button class="icon-btn icon-btn--danger" type="button" data-accion="eliminar" data-codigo="${p.codigo}" aria-label="Eliminar ${p.nombre}" title="Eliminar">${icono('delete')}</button>`;
}

function pintarResultados(contenedor) {
  const visibles = estado.soloBajo ? estado.productos.filter(esBajo) : estado.productos;
  const hayFiltros = Boolean(estado.q || estado.categoria || estado.soloBajo);

  if (!visibles.length) {
    pintar(contenedor, hayFiltros
      ? estadoVacio({
        ilus: estado.soloBajo && !estado.q && !estado.categoria ? 'ok' : 'busqueda',
        titulo: estado.soloBajo && !estado.q && !estado.categoria ? 'Ningún producto por resurtir' : 'Sin resultados',
        texto: estado.q ? `No hay productos que coincidan con «${estado.q}».` : 'Ningún producto cumple con los filtros elegidos.',
        accion: { id: 'limpiar-filtros', texto: 'Quitar filtros', icono: 'filter_alt_off', estilo: 'btn--tonal' },
      })
      : estadoVacio({
        ilus: 'caja',
        titulo: 'Aún no hay productos',
        texto: 'Registra tu primer producto: laptops, accesorios, componentes…',
        accion: { id: 'nuevo', texto: 'Registrar producto' },
      }));
    return;
  }

  const valor = visibles.reduce((s, p) => s + Number(p.valor ?? p.existencia * p.precio), 0);
  const unidades = visibles.reduce((s, p) => s + Number(p.existencia), 0);

  pintar(contenedor, html`
    <p class="resultados__conteo" role="status">
      ${fmtNumero(visibles.length)} ${visibles.length === 1 ? 'producto' : 'productos'} ·
      ${fmtNumero(unidades)} piezas · ${fmtMoneda(valor)}
    </p>

    <div class="table-wrap tabla-productos">
      <table class="table">
        <caption class="visually-hidden">Productos del inventario</caption>
        <thead>
          <tr>
            <th scope="col">Código</th>
            <th scope="col">Producto</th>
            <th scope="col">Categoría</th>
            <th scope="col" class="num">Precio</th>
            <th scope="col" class="num">Existencia</th>
            <th scope="col" class="num">Valor</th>
            <th scope="col" class="num"><span class="visually-hidden">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          ${visibles.map((p) => html`
          <tr class="table-row--interactive ${esBajo(p) ? 'table-row--alerta' : ''}" data-codigo="${p.codigo}">
            <td><span class="codigo">${p.codigo}</span></td>
            <td><button class="celda-nombre" type="button" data-accion="editar" data-codigo="${p.codigo}">${p.nombre}</button></td>
            <td>${categoriaDe(p)}</td>
            <td class="num">${fmtMoneda(p.precio)}</td>
            <td class="num">
              <span class="existencia ${esBajo(p) ? 'existencia--baja' : ''}">
                <span class="existencia__num">${fmtNumero(p.existencia)}</span>
                ${estadoStock(p) || html`<span class="celda-sub">mín. ${fmtNumero(p.stockMinimo)}</span>`}
              </span>
            </td>
            <td class="num">${fmtMoneda(p.valor ?? p.existencia * p.precio)}</td>
            <td class="acciones"><div class="acciones-grupo">${accionesFila(p)}</div></td>
          </tr>`)}
        </tbody>
      </table>
    </div>

    <ul class="tarjetas-productos" role="list" aria-label="Productos del inventario">
      ${visibles.map((p) => html`
      <li class="card card--outlined prod-card ${esBajo(p) ? 'prod-card--baja' : ''}">
        <div class="prod-card__head">
          <div class="prod-card__titulos">
            <p class="prod-card__nombre">${p.nombre}</p>
            <p class="prod-card__meta"><span class="codigo">${p.codigo}</span><span>${categoriaDe(p)}</span></p>
          </div>
          ${estadoStock(p)}
        </div>
        <dl class="prod-card__datos">
          <div><dt>Precio</dt><dd>${fmtMoneda(p.precio)}</dd></div>
          <div><dt>Existencia</dt><dd class="${esBajo(p) ? 'existencia--baja' : ''}">${fmtNumero(p.existencia)} <small>mín. ${fmtNumero(p.stockMinimo)}</small></dd></div>
          <div><dt>Valor</dt><dd>${fmtMoneda(p.valor ?? p.existencia * p.precio)}</dd></div>
        </dl>
        <div class="prod-card__acciones">${accionesFila(p, { compacto: true })}</div>
      </li>`)}
    </ul>`);
}

function alAccionar(e) {
  const boton = e.target.closest('[data-accion]');
  if (boton) {
    const accion = boton.dataset.accion;
    if (accion === 'nuevo') { abrirProducto(null); return; }
    if (accion === 'limpiar-filtros') {
      estado.q = '';
      estado.categoria = '';
      estado.soloBajo = false;
      const buscar = raizActual.querySelector('#buscar-producto');
      buscar.value = '';
      raizActual.querySelector('#limpiar-busqueda').hidden = true;
      sincronizarUrl();
      pintarChips();
      cargarProductos();
      return;
    }
    const p = estado.productos.find((x) => x.codigo === boton.dataset.codigo);
    if (!p) return;
    if (accion === 'editar') abrirProducto(p);
    else if (accion === 'entrada') abrirMovimiento(p, 'entrada');
    else if (accion === 'salida') abrirMovimiento(p, 'salida');
    else if (accion === 'eliminar') eliminarProducto(p);
    return;
  }
  // Clic en cualquier parte de la fila abre el detalle.
  const fila = e.target.closest('tr[data-codigo]');
  if (fila && !window.getSelection()?.toString()) {
    const p = estado.productos.find((x) => x.codigo === fila.dataset.codigo);
    if (p) abrirProducto(p);
  }
}

/** Enfoca la búsqueda (atajo de teclado «/»). */
export function enfocarBusqueda() {
  raizActual?.querySelector('#buscar-producto')?.focus();
}
