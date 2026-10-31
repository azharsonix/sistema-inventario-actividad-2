/**
 * Vista Panel: indicadores, productos por resurtir y últimos movimientos.
 */
import { api } from '../api.js';
import { html, icono, pintar, actualizarInsignia } from '../ui.js';
import { fmtMoneda, fmtNumero, fmtFechaLarga } from '../formato.js';
import {
  encabezado, esBajo, estadoVacio, pintarError, itemMovimiento, medidor, esqueletoKpis, esqueletoFilas,
} from '../componentes.js';
import { abrirMovimiento, abrirProducto } from '../dialogos.js';

export const meta = { titulo: 'Panel', fab: true };

let raizActual = null;
let vigenteActual = () => false;
let productosPorCodigo = new Map();

function saludo() {
  const h = new Date().getHours();
  if (h < 12) return 'Buenos días';
  if (h < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

export async function render(raiz, _params, vigente) {
  raizActual = raiz;
  vigenteActual = vigente;
  const hoy = fmtFechaLarga(new Date());
  pintar(raiz, html`
    ${encabezado(saludo(), `Resumen del inventario · ${hoy.charAt(0).toUpperCase()}${hoy.slice(1)}`)}
    <div id="panel-cuerpo">${esqueletoKpis()}<div class="panel-grid">${esqueletoFilas(4)}${esqueletoFilas(4)}</div></div>`);
  raiz.addEventListener('click', alHacerClic);
  await cargar();
}

export const actualizar = () => cargar();

async function cargar() {
  const raiz = raizActual;
  const vigente = vigenteActual;
  const cuerpo = raiz.querySelector('#panel-cuerpo');
  if (!cuerpo) return;
  try {
    const [reporte, productos, movimientos] = await Promise.all([
      api.reporte(),
      api.productos(),
      api.movimientos({ limite: 6 }),
    ]);
    if (!vigente()) return;
    productosPorCodigo = new Map(productos.map((p) => [p.codigo, p]));
    const porResurtir = productos.filter(esBajo)
      .sort((a, b) => (a.existencia / Math.max(1, a.stockMinimo)) - (b.existencia / Math.max(1, b.stockMinimo)));
    actualizarInsignia(porResurtir.length);
    const categorias = new Set(productos.map((p) => p.categoria).filter(Boolean)).size;

    const totalProductos = reporte.totalProductos ?? productos.length;
    const totalUnidades = reporte.totalUnidades ?? productos.reduce((s, p) => s + Number(p.existencia), 0);
    const valorTotal = reporte.valorTotal ?? productos.reduce((s, p) => s + Number(p.valor), 0);
    const nResurtir = Array.isArray(reporte.bajoStockMinimo) ? reporte.bajoStockMinimo.length : porResurtir.length;

    pintar(cuerpo, html`
      <section class="kpis" aria-label="Indicadores del inventario">
        <a class="card card--elevated card--interactive kpi" href="#/productos">
          <span class="kpi__icon">${icono('inventory_2')}</span>
          <p class="kpi__label">Productos</p>
          <p class="kpi__value">${fmtNumero(totalProductos)}</p>
          <p class="kpi__hint">en ${fmtNumero(categorias)} ${categorias === 1 ? 'categoría' : 'categorías'}</p>
        </a>
        <div class="card card--elevated kpi kpi--secondary">
          <span class="kpi__icon">${icono('stacks')}</span>
          <p class="kpi__label">Unidades</p>
          <p class="kpi__value">${fmtNumero(totalUnidades)}</p>
          <p class="kpi__hint">piezas en existencia</p>
        </div>
        <a class="card card--elevated card--interactive kpi kpi--tertiary" href="#/reporte">
          <span class="kpi__icon">${icono('payments')}</span>
          <p class="kpi__label">Valor del inventario</p>
          <p class="kpi__value">${fmtMoneda(valorTotal)}</p>
          <p class="kpi__hint">existencia × precio</p>
        </a>
        <a class="card ${nResurtir ? 'card--filled kpi--alerta' : 'card--elevated kpi--ok'} card--interactive kpi" href="#/productos?filtro=bajo">
          <span class="kpi__icon">${icono(nResurtir ? 'production_quantity_limits' : 'verified')}</span>
          <p class="kpi__label">Por resurtir</p>
          <p class="kpi__value">${fmtNumero(nResurtir)}</p>
          <p class="kpi__hint">${nResurtir ? 'en o bajo su stock mínimo' : 'todo en orden'}</p>
        </a>
      </section>

      <div class="panel-grid">
        <section class="card card--outlined panel-card" aria-labelledby="titulo-resurtir">
          <div class="card__header">
            ${icono('production_quantity_limits')}
            <h2 class="card__title" id="titulo-resurtir">Productos por resurtir</h2>
            ${porResurtir.length > 5 ? html`<a class="btn btn--text btn--small" href="#/productos?filtro=bajo">Ver todos (${porResurtir.length})</a>` : ''}
          </div>
          ${porResurtir.length
            ? html`<ul class="list" role="list">${porResurtir.slice(0, 5).map((p) => html`
                <li class="list-item resurtir-item">
                  <span class="list-item__leading">${icono(Number(p.existencia) === 0 ? 'block' : 'warning')}</span>
                  <div class="list-item__content">
                    <p class="list-item__headline">${p.nombre}</p>
                    <p class="list-item__supporting"><span class="codigo">${p.codigo}</span> · ${fmtNumero(p.existencia)} de mínimo ${fmtNumero(p.stockMinimo)}</p>
                    <div class="resurtir-item__meter">${medidor(Number(p.existencia), Math.max(1, Number(p.stockMinimo)) * 2, 'progress--error')}</div>
                  </div>
                  <button class="btn btn--tonal btn--small" type="button" data-entrada="${p.codigo}" aria-label="Registrar entrada de ${p.nombre}">
                    ${icono('add')}Entrada
                  </button>
                </li>`)}</ul>`
            : estadoVacio({ ilus: 'ok', titulo: 'Todo en orden', texto: 'Ningún producto está en su stock mínimo.', compacto: true })}
        </section>

        <section class="card card--outlined panel-card" aria-labelledby="titulo-ultimos">
          <div class="card__header">
            ${icono('history')}
            <h2 class="card__title" id="titulo-ultimos">Últimos movimientos</h2>
            <a class="btn btn--text btn--small" href="#/movimientos">Ver historial</a>
          </div>
          ${movimientos.length
            ? html`<ul class="list" role="list">${movimientos.map((m) => itemMovimiento(m, m.nombre ?? productosPorCodigo.get(m.codigo)?.nombre))}</ul>`
            : estadoVacio({ ilus: 'movimientos', titulo: 'Aún no hay movimientos', texto: 'Las entradas y salidas que registres aparecerán aquí.', compacto: true })}
        </section>
      </div>
      ${totalProductos === 0 ? estadoVacio({
        ilus: 'caja', titulo: 'Tu inventario está vacío',
        texto: 'Registra tu primer producto para empezar a controlar existencias.',
        accion: { id: 'nuevo', texto: 'Registrar producto' },
      }) : ''}`);
  } catch (error) {
    if (!vigente()) return;
    pintarError(cuerpo, error, () => cargar());
  }
}

function alHacerClic(e) {
  const entrada = e.target.closest('[data-entrada]');
  if (entrada) {
    const p = productosPorCodigo.get(entrada.dataset.entrada);
    if (p) abrirMovimiento(p, 'entrada');
    return;
  }
  if (e.target.closest('[data-accion="nuevo"]')) abrirProducto(null);
}
