/**
 * Formatos para México (es-MX): moneda MXN, números y fechas.
 */
const LOCALE = 'es-MX';

const moneda = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'MXN' });
const monedaCorta = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'MXN', notation: 'compact', maximumFractionDigits: 1 });
const entero = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 0 });
const porcentaje = new Intl.NumberFormat(LOCALE, { style: 'percent', maximumFractionDigits: 1 });
const fechaHora = new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium', timeStyle: 'short' });
const soloHora = new Intl.DateTimeFormat(LOCALE, { timeStyle: 'short' });
const fechaLarga = new Intl.DateTimeFormat(LOCALE, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const relativo = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

const num = (n) => {
  const v = Number(n);
  return Number.isFinite(v) ? v : 0;
};

export const fmtMoneda = (n) => moneda.format(num(n));
export const fmtMonedaCorta = (n) => (Math.abs(num(n)) >= 100000 ? monedaCorta.format(num(n)) : moneda.format(num(n)));
export const fmtNumero = (n) => entero.format(num(n));
export const fmtPorcentaje = (n) => porcentaje.format(num(n));

const fecha = (iso) => {
  const d = iso instanceof Date ? iso : new Date(iso);
  return Number.isNaN(d.getTime()) ? null : d;
};

export const fmtFecha = (iso) => {
  const d = fecha(iso);
  return d ? fechaHora.format(d) : '—';
};
export const fmtHora = (iso) => {
  const d = fecha(iso);
  return d ? soloHora.format(d) : '';
};
export const fmtFechaLarga = (iso) => {
  const d = fecha(iso);
  return d ? fechaLarga.format(d) : '';
};

const UNIDADES = [
  ['year', 31536000], ['month', 2592000], ['week', 604800],
  ['day', 86400], ['hour', 3600], ['minute', 60],
];

/** "hace 5 minutos", "ayer", "hace 3 días"… */
export function fmtRelativa(iso, ahora = Date.now()) {
  const d = fecha(iso);
  if (!d) return '';
  const segundos = Math.round((d.getTime() - ahora) / 1000);
  if (Math.abs(segundos) < 45) return 'justo ahora';
  for (const [unidad, s] of UNIDADES) {
    if (Math.abs(segundos) >= s || unidad === 'minute') {
      return relativo.format(Math.round(segundos / s), unidad);
    }
  }
  return '';
}

const claveDia = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;

/** Etiqueta para agrupar por día: "Hoy", "Ayer" o la fecha larga. */
export function etiquetaDia(iso) {
  const d = fecha(iso);
  if (!d) return 'Sin fecha';
  const hoy = new Date();
  const ayer = new Date(hoy);
  ayer.setDate(hoy.getDate() - 1);
  if (claveDia(d) === claveDia(hoy)) return 'Hoy';
  if (claveDia(d) === claveDia(ayer)) return 'Ayer';
  const texto = fechaLarga.format(d);
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

export const piezas = (n) => `${fmtNumero(n)} ${num(n) === 1 ? 'pieza' : 'piezas'}`;
