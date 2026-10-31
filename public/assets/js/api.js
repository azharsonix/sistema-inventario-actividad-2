/**
 * Cliente de la API REST del inventario.
 * Todas las rutas son relativas a la raíz del sitio (/api/...).
 * Los errores llegan como {"error": "mensaje"} y se convierten en ApiError.
 */

export class ApiError extends Error {
  /**
   * @param {string} mensaje Mensaje para mostrar al usuario.
   * @param {number} estado Código HTTP (0 si no hubo respuesta).
   * @param {boolean} [red] true si el problema fue de conexión.
   */
  constructor(mensaje, estado, red = false) {
    super(mensaje);
    this.name = 'ApiError';
    this.estado = estado;
    this.red = red;
  }
}

/* ---------- Seguimiento de solicitudes en curso (para el progreso lineal) ---------- */
let pendientes = 0;
const oyentes = new Set();

/** Registra una función que recibe true/false según haya solicitudes en curso. */
export function alCambiarCarga(fn) {
  oyentes.add(fn);
  return () => oyentes.delete(fn);
}

function cambiarPendientes(delta) {
  pendientes = Math.max(0, pendientes + delta);
  oyentes.forEach((fn) => fn(pendientes > 0));
}

const MENSAJES_ESTADO = {
  400: 'La solicitud no es válida.',
  404: 'No se encontró el recurso solicitado.',
  405: 'Operación no permitida.',
  409: 'La operación entra en conflicto con los datos actuales.',
  422: 'Los datos enviados no son válidos.',
  500: 'Ocurrió un error en el servidor. Inténtalo más tarde.',
};

async function solicitar(metodo, ruta, cuerpo) {
  cambiarPendientes(1);
  try {
    const opciones = { method: metodo, headers: { Accept: 'application/json' } };
    if (cuerpo !== undefined) {
      opciones.headers['Content-Type'] = 'application/json';
      opciones.body = JSON.stringify(cuerpo);
    }

    let respuesta;
    try {
      respuesta = await fetch(ruta, opciones);
    } catch {
      throw new ApiError('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.', 0, true);
    }

    const texto = await respuesta.text();
    let datos = null;
    if (texto) {
      try {
        datos = JSON.parse(texto);
      } catch {
        throw new ApiError(
          respuesta.ok
            ? 'La respuesta del servidor no es válida.'
            : (MENSAJES_ESTADO[respuesta.status] ?? `Error del servidor (${respuesta.status}).`),
          respuesta.status,
        );
      }
    }

    if (!respuesta.ok) {
      const mensaje = (datos && typeof datos.error === 'string' && datos.error)
        || MENSAJES_ESTADO[respuesta.status]
        || `Error del servidor (${respuesta.status}).`;
      throw new ApiError(mensaje, respuesta.status);
    }
    return datos ?? {};
  } finally {
    cambiarPendientes(-1);
  }
}

const cod = (codigo) => encodeURIComponent(String(codigo));

function query(params) {
  const p = new URLSearchParams();
  for (const [clave, valor] of Object.entries(params)) {
    if (valor !== undefined && valor !== null && valor !== '') p.set(clave, String(valor));
  }
  const s = p.toString();
  return s ? `?${s}` : '';
}

export const api = {
  /** @returns {Promise<object[]>} */
  productos: ({ q, categoria } = {}) =>
    solicitar('GET', `/api/productos${query({ q, categoria })}`).then((d) => d.productos ?? []),

  producto: (codigo) =>
    solicitar('GET', `/api/productos/${cod(codigo)}`).then((d) => d.producto),

  crearProducto: (datos) =>
    solicitar('POST', '/api/productos', datos).then((d) => d.producto),

  modificarProducto: (codigo, datos) =>
    solicitar('PUT', `/api/productos/${cod(codigo)}`, datos).then((d) => d.producto),

  eliminarProducto: (codigo) =>
    solicitar('DELETE', `/api/productos/${cod(codigo)}`),

  /** @returns {Promise<{movimiento: object, producto: object}>} */
  registrarEntrada: (codigo, datos) =>
    solicitar('POST', `/api/productos/${cod(codigo)}/entradas`, datos),

  registrarSalida: (codigo, datos) =>
    solicitar('POST', `/api/productos/${cod(codigo)}/salidas`, datos),

  movimientos: ({ codigo, limite } = {}) =>
    solicitar('GET', `/api/movimientos${query({ codigo, limite })}`).then((d) => d.movimientos ?? []),

  categorias: () =>
    solicitar('GET', '/api/categorias').then((d) => d.categorias ?? []),

  reporte: () => solicitar('GET', '/api/reporte'),
};
