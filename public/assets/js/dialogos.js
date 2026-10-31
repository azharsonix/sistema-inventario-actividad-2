/**
 * Diálogos de negocio: alta/edición de producto, entrada/salida y eliminación.
 * Validan en el cliente con las mismas reglas del dominio y muestran los
 * mensajes de error que devuelva la API (campo `error`).
 */
import { api } from './api.js';
import {
  html, pintar, icono, snackbar, confirmar, abrirDialogo, cerrarDialogo, prepararDialogo,
  marcarError, limpiarError, limpiarErrores, mostrarBanner, enfocarPrimerError, notificarCambio,
} from './ui.js';
import { fmtMoneda, fmtNumero, piezas } from './formato.js';
import { estadoStock, itemMovimiento } from './componentes.js';

const RE_CODIGO = /^[A-Z0-9-]{3,20}$/;
const RE_ENTERO = /^\d+$/;

let dlgProducto, formProducto, dlgMov, formMov;
let productoEnEdicion = null;      // null = alta
let movimientoActual = null;       // { producto, tipo }

export function iniciarDialogos() {
  dlgProducto = document.getElementById('dlg-producto');
  formProducto = document.getElementById('form-producto');
  dlgMov = document.getElementById('dlg-movimiento');
  formMov = document.getElementById('form-movimiento');

  [dlgProducto, dlgMov, document.getElementById('dlg-confirmar')].forEach(prepararDialogo);

  // Al escribir se limpia el error del campo.
  for (const form of [formProducto, formMov]) {
    form.addEventListener('input', (e) => {
      const caja = e.target.closest('.field');
      if (caja?.classList.contains('field--error')) limpiarError(caja);
    });
  }

  // El código se escribe siempre en mayúsculas.
  const codigo = formProducto.elements.codigo;
  codigo.addEventListener('input', () => {
    const { selectionStart: a, selectionEnd: b } = codigo;
    const mayus = codigo.value.toUpperCase();
    if (mayus !== codigo.value) {
      codigo.value = mayus;
      codigo.setSelectionRange(a, b);
    }
  });

  formProducto.addEventListener('submit', guardarProducto);
  formMov.addEventListener('submit', guardarMovimiento);
  formMov.elements.cantidad.addEventListener('input', previsualizarMovimiento);
}

/* =====================================================================
 * Producto: alta / edición
 * ===================================================================== */

/**
 * Abre el formulario de producto.
 * @param {object|null} producto Producto a editar, o null para uno nuevo.
 */
export function abrirProducto(producto = null) {
  productoEnEdicion = producto;
  const f = formProducto.elements;
  formProducto.reset();
  limpiarErrores(formProducto);

  const edicion = Boolean(producto);
  document.getElementById('dlg-producto-titulo').textContent = edicion ? 'Editar producto' : 'Nuevo producto';
  document.getElementById('dlg-producto-icono').textContent = edicion ? 'edit_note' : 'add_box';
  document.getElementById('producto-guardar').textContent = edicion ? 'Guardar cambios' : 'Registrar producto';

  f.codigo.disabled = edicion;
  f.existencia.disabled = edicion;
  const ayudaCodigo = document.getElementById('f-codigo-ayuda');
  const ayudaExist = document.getElementById('f-existencia-ayuda');
  ayudaCodigo.dataset.ayuda = edicion ? 'El código no se puede modificar' : '3 a 20 caracteres: A-Z, 0-9 o guion';
  ayudaExist.dataset.ayuda = edicion ? 'Cambia solo con entradas y salidas' : 'Piezas en almacén; 0 si se omite';
  ayudaCodigo.textContent = ayudaCodigo.dataset.ayuda;
  ayudaExist.textContent = ayudaExist.dataset.ayuda;
  document.querySelector('label[for="f-existencia"]').textContent = edicion ? 'Existencia actual' : 'Existencia inicial';

  const resumen = document.getElementById('producto-resumen');
  const movs = document.getElementById('producto-movs');
  if (edicion) {
    f.codigo.value = producto.codigo;
    f.nombre.value = producto.nombre ?? '';
    f.categoria.value = producto.categoria ?? '';
    f.precio.value = String(producto.precio ?? '');
    f.stockMinimo.value = String(producto.stockMinimo ?? 0);
    f.existencia.value = String(producto.existencia ?? 0);
    pintar(resumen, html`
      <span class="status status--neutral">${icono('inventory')}${piezas(producto.existencia)}</span>
      <span class="status status--neutral">${icono('payments')}Valor ${fmtMoneda(producto.valor ?? producto.existencia * producto.precio)}</span>
      ${estadoStock(producto)}`);
    resumen.hidden = false;
    cargarMovimientosProducto(producto.codigo);
  } else {
    resumen.hidden = true;
    movs.hidden = true;
  }

  cargarCategoriasSugeridas();
  abrirDialogo(dlgProducto);
  (edicion ? f.nombre : f.codigo).focus();
}

async function cargarCategoriasSugeridas() {
  try {
    const categorias = await api.categorias();
    pintar(document.getElementById('lista-categorias'), html`${categorias.map((c) => html`<option value="${c}"></option>`)}`);
  } catch { /* las sugerencias son opcionales */ }
}

async function cargarMovimientosProducto(codigo) {
  const seccion = document.getElementById('producto-movs');
  const lista = seccion.querySelector('ul');
  seccion.hidden = true;
  try {
    const movimientos = await api.movimientos({ codigo, limite: 4 });
    if (productoEnEdicion?.codigo !== codigo) return;
    if (!movimientos.length) return;
    pintar(lista, html`${movimientos.map((m) => itemMovimiento(m, m.nombre))}`);
    seccion.hidden = false;
  } catch { /* sección informativa */ }
}

function validarProducto() {
  const f = formProducto.elements;
  const edicion = Boolean(productoEnEdicion);
  const errores = {};
  const datos = {};

  if (!edicion) {
    const codigo = f.codigo.value.trim().toUpperCase();
    f.codigo.value = codigo;
    if (!codigo) errores.codigo = 'El código es obligatorio.';
    else if (!RE_CODIGO.test(codigo)) errores.codigo = 'Usa de 3 a 20 caracteres: letras A-Z, números o guion.';
    datos.codigo = codigo;
  }

  const nombre = f.nombre.value.trim();
  if (!nombre) errores.nombre = 'El nombre del producto es obligatorio.';
  datos.nombre = nombre;

  const categoria = f.categoria.value.trim();
  if (!categoria) errores.categoria = 'La categoría es obligatoria.';
  datos.categoria = categoria;

  const precioTexto = f.precio.value.trim();
  const precio = Number(precioTexto);
  if (precioTexto === '' || f.precio.validity.badInput) errores.precio = 'Escribe el precio (0 o mayor).';
  else if (!Number.isFinite(precio)) errores.precio = 'El precio debe ser un número.';
  else if (precio < 0) errores.precio = 'El precio no puede ser negativo.';
  else datos.precio = Math.round(precio * 100) / 100;

  const stockTexto = f.stockMinimo.value.trim();
  if (f.stockMinimo.validity.badInput || (stockTexto !== '' && !RE_ENTERO.test(stockTexto))) {
    errores.stockMinimo = 'Debe ser un número entero, 0 o mayor.';
  } else {
    datos.stockMinimo = stockTexto === '' ? 0 : Number(stockTexto);
  }

  if (!edicion) {
    const existTexto = f.existencia.value.trim();
    if (f.existencia.validity.badInput || (existTexto !== '' && !RE_ENTERO.test(existTexto))) {
      errores.existencia = 'Debe ser un número entero, 0 o mayor.';
    } else {
      datos.existencia = existTexto === '' ? 0 : Number(existTexto);
    }
  }
  return { datos, errores };
}

/** Relaciona un mensaje de la API con el campo del formulario al que se refiere. */
function campoDeMensaje(mensaje) {
  const m = mensaje.toLowerCase();
  if (m.includes('código') || m.includes('codigo')) return 'codigo';
  if (m.includes('nombre')) return 'nombre';
  if (m.includes('categor')) return 'categoria';
  if (m.includes('precio')) return 'precio';
  if (m.includes('stock')) return 'stockMinimo';
  if (m.includes('existencia')) return 'existencia';
  return null;
}

async function guardarProducto(e) {
  e.preventDefault();
  limpiarErrores(formProducto);
  const { datos, errores } = validarProducto();
  if (Object.keys(errores).length) {
    for (const [campo, mensaje] of Object.entries(errores)) marcarError(formProducto, campo, mensaje);
    enfocarPrimerError(formProducto);
    return;
  }

  const boton = document.getElementById('producto-guardar');
  const textoBoton = boton.textContent;
  boton.disabled = true;
  boton.textContent = 'Guardando…';
  try {
    if (productoEnEdicion) {
      const { nombre, categoria, precio, stockMinimo } = datos;
      const p = await api.modificarProducto(productoEnEdicion.codigo, { nombre, categoria, precio, stockMinimo });
      snackbar(`Producto actualizado: ${p?.nombre ?? nombre}`);
    } else {
      const p = await api.crearProducto(datos);
      snackbar(`Producto registrado: ${p?.codigo ?? datos.codigo}`);
    }
    cerrarDialogo(dlgProducto, 'guardado');
    notificarCambio();
  } catch (error) {
    const campo = error.red ? null : campoDeMensaje(error.message);
    const editable = campo && !(productoEnEdicion && (campo === 'codigo' || campo === 'existencia'));
    if (editable && marcarError(formProducto, campo, error.message)) {
      enfocarPrimerError(formProducto);
    } else {
      mostrarBanner(document.getElementById('producto-error'), error.message);
    }
    if (productoEnEdicion && error.estado === 404) {
      snackbar(error.message, { tipo: 'error' });
      notificarCambio();
    }
  } finally {
    boton.disabled = false;
    boton.textContent = textoBoton;
  }
}

/* =====================================================================
 * Entradas y salidas
 * ===================================================================== */

/**
 * @param {object} producto
 * @param {'entrada'|'salida'} tipo
 */
export function abrirMovimiento(producto, tipo) {
  movimientoActual = { producto, tipo };
  formMov.reset();
  limpiarErrores(formMov);
  const entrada = tipo === 'entrada';
  const existencia = Number(producto.existencia) || 0;

  document.getElementById('dlg-mov-titulo').textContent = entrada ? 'Registrar entrada' : 'Registrar salida';
  document.getElementById('dlg-mov-icono').textContent = entrada ? 'download' : 'upload';
  document.getElementById('mov-guardar').textContent = entrada ? 'Registrar entrada' : 'Registrar salida';
  document.getElementById('mov-nombre').textContent = producto.nombre;
  document.getElementById('mov-codigo').textContent = producto.codigo;
  document.getElementById('mov-existencia').textContent = fmtNumero(existencia);
  document.getElementById('mov-existencia-etiqueta').textContent = entrada ? 'en existencia' : 'disponibles';

  const ayudaCant = document.getElementById('f-cantidad-ayuda');
  ayudaCant.dataset.ayuda = entrada
    ? 'Piezas que ingresan al almacén'
    : `Disponible: ${piezas(existencia)}`;
  ayudaCant.textContent = ayudaCant.dataset.ayuda;
  const ayudaMotivo = document.getElementById('f-motivo-ayuda');
  ayudaMotivo.dataset.ayuda = entrada ? 'Si lo dejas vacío: «Compra a proveedor»' : 'Si lo dejas vacío: «Venta»';
  ayudaMotivo.textContent = ayudaMotivo.dataset.ayuda;

  const input = formMov.elements.cantidad;
  if (entrada) input.removeAttribute('max');
  else input.max = String(existencia);

  previsualizarMovimiento();
  abrirDialogo(dlgMov);
  input.focus();

  if (!entrada && existencia === 0) {
    mostrarBanner(document.getElementById('mov-error'), 'Este producto está agotado: no hay piezas para registrar una salida.');
  }
}

function previsualizarMovimiento() {
  if (!movimientoActual) return;
  const { producto, tipo } = movimientoActual;
  const salida = document.getElementById('mov-resultado');
  const texto = formMov.elements.cantidad.value.trim();
  const existencia = Number(producto.existencia) || 0;
  if (!RE_ENTERO.test(texto) || Number(texto) <= 0) {
    pintar(salida, html`${icono('info', 'icon--sm')}Existencia resultante: <strong>${fmtNumero(existencia)}</strong>`);
    return;
  }
  const resultado = tipo === 'entrada' ? existencia + Number(texto) : existencia - Number(texto);
  pintar(salida, resultado < 0
    ? html`${icono('error', 'icon--sm')}No alcanza: faltan <strong>${fmtNumero(-resultado)}</strong> piezas`
    : html`${icono('arrow_forward', 'icon--sm')}Existencia resultante: <strong>${fmtNumero(resultado)}</strong>`);
}

async function guardarMovimiento(e) {
  e.preventDefault();
  if (!movimientoActual) return;
  limpiarErrores(formMov);
  const { producto, tipo } = movimientoActual;
  const texto = formMov.elements.cantidad.value.trim();
  const existencia = Number(producto.existencia) || 0;

  let error = null;
  if (texto === '' || formMov.elements.cantidad.validity.badInput) error = 'Escribe la cantidad de piezas.';
  else if (!RE_ENTERO.test(texto) || Number(texto) <= 0) error = 'La cantidad debe ser un número entero mayor que cero.';
  else if (tipo === 'salida' && Number(texto) > existencia) {
    error = `Existencia insuficiente: hay ${fmtNumero(existencia)} y se pidieron ${fmtNumero(Number(texto))}.`;
  }
  if (error) {
    marcarError(formMov, 'cantidad', error);
    enfocarPrimerError(formMov);
    return;
  }

  const cuerpo = { cantidad: Number(texto) };
  const motivo = formMov.elements.motivo.value.trim();
  if (motivo) cuerpo.motivo = motivo;

  const boton = document.getElementById('mov-guardar');
  const textoBoton = boton.textContent;
  boton.disabled = true;
  boton.textContent = 'Registrando…';
  try {
    const r = tipo === 'entrada'
      ? await api.registrarEntrada(producto.codigo, cuerpo)
      : await api.registrarSalida(producto.codigo, cuerpo);
    const final = r?.producto?.existencia ?? r?.movimiento?.existenciaResultante;
    const signo = tipo === 'entrada' ? '+' : '−';
    snackbar(`${tipo === 'entrada' ? 'Entrada' : 'Salida'} registrada: ${signo}${fmtNumero(cuerpo.cantidad)} ${producto.codigo}`
      + (final !== undefined ? ` (quedan ${fmtNumero(final)})` : ''));
    cerrarDialogo(dlgMov, 'guardado');
    notificarCambio();
  } catch (err) {
    if (!err.red && /cantidad|existencia/i.test(err.message)) {
      marcarError(formMov, 'cantidad', err.message);
      enfocarPrimerError(formMov);
    } else {
      mostrarBanner(document.getElementById('mov-error'), err.message);
    }
    if (err.estado === 404) notificarCambio();
  } finally {
    boton.disabled = false;
    boton.textContent = textoBoton;
  }
}

/* =====================================================================
 * Eliminación
 * ===================================================================== */

export async function eliminarProducto(producto) {
  const ok = await confirmar({
    titulo: '¿Eliminar producto?',
    mensaje: `Se eliminará «${producto.nombre}» (${producto.codigo}) con sus ${piezas(producto.existencia)}. Esta acción no se puede deshacer.`,
    aceptar: 'Eliminar',
    icono: 'delete',
  });
  if (!ok) return false;
  try {
    await api.eliminarProducto(producto.codigo);
    snackbar(`Producto eliminado: ${producto.codigo}`);
    notificarCambio();
    return true;
  } catch (error) {
    snackbar(error.message, { tipo: 'error' });
    if (error.estado === 404) notificarCambio();
    return false;
  }
}
