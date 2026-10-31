/**
 * Vista Reporte: totales, desglose por categoría (barras CSS),
 * productos por resurtir y detalle imprimible.
 */
import { api } from '../api.js';
import { html, icono, pintar, actualizarInsignia } from '../ui.js';
import { fmtMoneda, fmtNumero, fmtFecha, fmtPorcentaje } from '../formato.js';
import { encabezado, esBajo, estadoStock, estadoVacio, pintarError, esqueletoKpis, esqueletoFilas, categoriaDe } from '../componentes.js';
import { abrirMovimiento, abrirProducto } from '../dialogos.js';

export const meta = { titulo: 'Reporte', fab: false };

let raizActual = null;
let vigenteActual = () => false;
let porCodigo = new Map();

export async function render(raiz, _params, vigente) {
  raizActual = raiz;
  vigenteActual = vigente;
  pintar(raiz, html`
    <div class="reporte-print-header">
      <svg class="logo" aria-hidden="true"><use href="#logo-tecnostock"/></svg>
      <div class="reporte-print-header__marca"><strong>TecnoStock</strong><br>Inventario para tiendas de cómputo</div>
      <div class="reporte-print-header__fecha" id="reporte-fecha-impresion"></div>
    </div>
    ${encabezado('Reporte de inventario', 'Existencias, valor y productos por resurtir',
      html`<button class="btn btn--tonal" type="button" id="btn-imprimir">${icono('print')}Imprimir</button>`)}
    <div id="reporte-cuerpo" aria-busy="true">${esqueletoKpis()}<div class="reporte-grid">${esqueletoFilas(5)}${esqueletoFilas(5)}</div></div>`);
  raiz.querySelector('#btn-imprimir').addEventListener('click', () => window.print());
  raiz.addEventListener('click', alHacerClic);
  await cargar();
}

export const actualizar = () => cargar();

async function cargar() {
  const raiz = raizActual;
  const vigente = vigenteActual;
  const cuerpo = raiz.querySelector('#reporte-cuerpo');
  try {
    const [reporte, productos] = await Promise.all([api.reporte(), api.productos()]);
    if (!vigente()) return;
    porCodigo = new Map(productos.map((p) => [p.codigo, p]));

    // El detalle del reporte ya trae categoría y precio; si faltara algo, se completa con /api/productos.
    const detalle = (reporte.detalle ?? []).map((d) => {
      const p = porCodigo.get(d.codigo) ?? {};
      const fila = { ...p, ...d };
      fila.categoria = d.categoria ?? p.categoria;
      fila.precio = d.precio ?? p.precio;
      fila.stockMinimo = d.stockMinimo ?? p.stockMinimo;
      const codigosBajos = new Set(reporte.bajoStockMinimo ?? []);
      fila.bajoStockMinimo = typeof d.bajoStockMinimo === 'boolean' ? d.bajoStockMinimo : codigosBajos.has(d.codigo);
      return fila;
    });
    const bajos = detalle.filter(esBajo);
    actualizarInsignia(bajos.length);

    const valorTotal = Number(reporte.valorTotal ?? detalle.reduce((s, d) => s + Number(d.valor), 0));
    const totalUnidades = Number(reporte.totalUnidades ?? detalle.reduce((s, d) => s + Number(d.existencia), 0));
    const generado = reporte.generado ?? new Date().toISOString();
    raiz.querySelector('#reporte-fecha-impresion').textContent = `Generado: ${fmtFecha(generado)}`;

    // Desglose por categoría
    const mapa = new Map();
    for (const d of detalle) {
      const c = categoriaDe(d);
      const g = mapa.get(c) ?? { categoria: c, productos: 0, unidades: 0, valor: 0 };
      g.productos += 1;
      g.unidades += Number(d.existencia) || 0;
      g.valor += Number(d.valor) || 0;
      mapa.set(c, g);
    }
    const categorias = [...mapa.values()].sort((a, b) => b.valor - a.valor);
    const maxValor = Math.max(1, ...categorias.map((c) => c.valor));

    if (!detalle.length) {
      pintar(cuerpo, estadoVacio({
        ilus: 'caja', titulo: 'No hay datos para el reporte',
        texto: 'Registra productos para ver existencias y valor del inventario.',
        accion: { id: 'nuevo', texto: 'Registrar producto' },
      }));
      cuerpo.removeAttribute('aria-busy');
      return;
    }

    pintar(cuerpo, html`
      <p class="page-subtitle no-print" style="margin:-12px 0 16px">${icono('schedule', 'icon--sm')} Generado el ${fmtFecha(generado)}</p>
      <section class="kpis" aria-label="Totales">
        <div class="card card--elevated kpi"><span class="kpi__icon">${icono('inventory_2')}</span>
          <p class="kpi__label">Productos</p><p class="kpi__value">${fmtNumero(reporte.totalProductos ?? detalle.length)}</p>
          <p class="kpi__hint">${fmtNumero(categorias.length)} ${categorias.length === 1 ? 'categoría' : 'categorías'}</p></div>
        <div class="card card--elevated kpi kpi--secondary"><span class="kpi__icon">${icono('stacks')}</span>
          <p class="kpi__label">Unidades</p><p class="kpi__value">${fmtNumero(totalUnidades)}</p>
          <p class="kpi__hint">piezas en existencia</p></div>
        <div class="card card--elevated kpi kpi--tertiary"><span class="kpi__icon">${icono('payments')}</span>
          <p class="kpi__label">Valor total</p><p class="kpi__value">${fmtMoneda(valorTotal)}</p>
          <p class="kpi__hint">existencia × precio</p></div>
        <div class="card ${bajos.length ? 'card--filled kpi--alerta' : 'card--elevated kpi--ok'} kpi"><span class="kpi__icon">${icono(bajos.length ? 'production_quantity_limits' : 'verified')}</span>
          <p class="kpi__label">Por resurtir</p><p class="kpi__value">${fmtNumero(bajos.length)}</p>
          <p class="kpi__hint">en o bajo su stock mínimo</p></div>
      </section>

      <div class="reporte-grid">
        <section class="card card--outlined section" aria-labelledby="titulo-categorias">
          <h2 class="section-title" id="titulo-categorias">${icono('category')}Valor por categoría</h2>
          <ul class="barras" role="list">
            ${categorias.map((c) => html`
            <li class="barra">
              <div>
                <p class="barra__nombre">${c.categoria}</p>
                <p class="barra__meta">${fmtNumero(c.productos)} ${c.productos === 1 ? 'producto' : 'productos'} · ${fmtNumero(c.unidades)} piezas</p>
              </div>
              <p class="barra__valor">${fmtMoneda(c.valor)}<small>${fmtPorcentaje(valorTotal ? c.valor / valorTotal : 0)} del total</small></p>
              <div class="barra__track" aria-hidden="true"><div class="barra__fill" style="width:${((c.valor / maxValor) * 100).toFixed(1)}%"></div></div>
            </li>`)}
          </ul>
        </section>

        <section class="card card--outlined section" aria-labelledby="titulo-resurtir-rep">
          <h2 class="section-title" id="titulo-resurtir-rep">${icono('production_quantity_limits')}Productos a resurtir</h2>
          ${bajos.length ? html`
          <ul class="list" role="list" style="padding:0">
            ${bajos.map((d) => html`
            <li class="list-item resurtir-item" style="padding-inline:0">
              <span class="list-item__leading">${icono(Number(d.existencia) === 0 ? 'block' : 'warning')}</span>
              <div class="list-item__content">
                <p class="list-item__headline">${d.nombre}</p>
                <p class="list-item__supporting"><span class="codigo">${d.codigo}</span> · existencia ${fmtNumero(d.existencia)} · mínimo ${fmtNumero(d.stockMinimo)}</p>
              </div>
              <button class="btn btn--tonal btn--small no-print" type="button" data-entrada="${d.codigo}" aria-label="Registrar entrada de ${d.nombre}">${icono('add')}Entrada</button>
            </li>`)}
          </ul>` : estadoVacio({ ilus: 'ok', titulo: 'Todo en orden', texto: 'Ningún producto requiere resurtido.', compacto: true })}
        </section>
      </div>

      <section class="section" aria-labelledby="titulo-detalle">
        <h2 class="section-title" id="titulo-detalle">${icono('table_view')}Detalle del inventario</h2>
        <div class="table-wrap">
          <table class="table table--apilable">
            <caption class="visually-hidden">Detalle del inventario por producto</caption>
            <thead>
              <tr>
                <th scope="col">Código</th><th scope="col">Producto</th><th scope="col">Categoría</th>
                <th scope="col" class="num">Precio</th><th scope="col" class="num">Existencia</th>
                <th scope="col" class="num">Mínimo</th><th scope="col" class="num">Valor</th><th scope="col">Estado</th>
              </tr>
            </thead>
            <tbody>
              ${detalle.map((d) => html`
              <tr class="${esBajo(d) ? 'table-row--alerta' : ''}">
                <td data-label="Código"><span class="codigo">${d.codigo}</span></td>
                <td data-label="Producto">${d.nombre}</td>
                <td data-label="Categoría">${categoriaDe(d)}</td>
                <td data-label="Precio" class="num">${d.precio !== undefined ? fmtMoneda(d.precio) : '—'}</td>
                <td data-label="Existencia" class="num">${fmtNumero(d.existencia)}</td>
                <td data-label="Mínimo" class="num">${d.stockMinimo !== undefined ? fmtNumero(d.stockMinimo) : '—'}</td>
                <td data-label="Valor" class="num">${fmtMoneda(d.valor)}</td>
                <td data-label="Estado">${estadoStock(d) || html`<span class="status status--success">${icono('check', 'icon--sm')}Correcto</span>`}</td>
              </tr>`)}
            </tbody>
            <tfoot>
              <tr>
                <td data-label="" colspan="4">Total</td>
                <td data-label="Unidades" class="num">${fmtNumero(totalUnidades)}</td>
                <td data-label="" class="num"></td>
                <td data-label="Valor total" class="num">${fmtMoneda(valorTotal)}</td>
                <td data-label=""></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>`);
    cuerpo.removeAttribute('aria-busy');
  } catch (error) {
    if (!vigente()) return;
    pintarError(cuerpo, error, () => cargar());
  }
}

function alHacerClic(e) {
  const entrada = e.target.closest('[data-entrada]');
  if (entrada) {
    const p = porCodigo.get(entrada.dataset.entrada);
    if (p) abrirMovimiento(p, 'entrada');
    return;
  }
  if (e.target.closest('[data-accion="nuevo"]')) abrirProducto(null);
}
