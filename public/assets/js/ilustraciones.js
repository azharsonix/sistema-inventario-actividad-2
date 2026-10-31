/**
 * Ilustraciones SVG para estados vacíos y de error.
 * Usan los tokens de color (se adaptan al tema) y no dependen de la
 * fuente de íconos, así que también se ven sin internet.
 */
import { raw } from './ui.js';

const P = 'var(--md-sys-color-primary)';
const PC = 'var(--md-sys-color-primary-container)';
const OPC = 'var(--md-sys-color-on-primary-container)';
const TC = 'var(--md-sys-color-tertiary-container)';
const T = 'var(--md-sys-color-tertiary)';
const S = 'var(--md-sys-color-surface-container-highest)';
const OV = 'var(--md-sys-color-outline-variant)';
const E = 'var(--md-sys-color-error)';
const EC = 'var(--md-sys-color-error-container)';
const SUC = 'var(--app-color-success)';
const SUCC = 'var(--app-color-success-container)';
const W = 'var(--app-color-warning)';
const WC = 'var(--app-color-warning-container)';

const fondo = `
  <ellipse cx="80" cy="60" rx="66" ry="50" style="fill:${S}"/>
  <ellipse cx="80" cy="106" rx="42" ry="5" style="fill:${OV};opacity:.7"/>`;

const destellos = `
  <circle cx="26" cy="30" r="3" style="fill:${T}"/>
  <circle cx="136" cy="24" r="4" style="fill:${TC}"/>
  <path d="M132 86h8M136 82v8" style="stroke:${P};stroke-width:2;stroke-linecap:round"/>`;

const caja = (dx = 0) => `
  <g transform="translate(${dx} 0)">
    <path d="M80 44 108 58 80 72 52 58Z" style="fill:${OPC};opacity:.35"/>
    <path d="M52 58 80 72V102L52 88Z" style="fill:${PC}"/>
    <path d="M108 58 80 72V102L108 88Z" style="fill:${P}"/>
    <path d="M52 58 80 44 70 34 42 48Z" style="fill:${PC};stroke:${P};stroke-width:1.5;stroke-linejoin:round"/>
    <path d="M108 58 80 44 92 34 120 48Z" style="fill:${TC};stroke:${T};stroke-width:1.5;stroke-linejoin:round"/>
  </g>`;

const DIBUJOS = {
  caja: `${fondo}${caja()}${destellos}`,
  busqueda: `${fondo}${caja(-14)}
    <circle cx="108" cy="54" r="17" style="fill:var(--md-sys-color-surface);stroke:${P};stroke-width:6"/>
    <path d="M120 67 134 81" style="stroke:${P};stroke-width:8;stroke-linecap:round"/>
    <path d="M102 50q6-8 12 0" style="stroke:${T};stroke-width:3;fill:none;stroke-linecap:round"/>`,
  ok: `${fondo}
    <circle cx="80" cy="58" r="32" style="fill:${SUCC}"/>
    <path d="M65 58 76 69 97 47" style="fill:none;stroke:${SUC};stroke-width:8;stroke-linecap:round;stroke-linejoin:round"/>
    ${destellos}`,
  error: `${fondo}
    <path d="M52 82h56a18 18 0 0 0 0-36 26 26 0 0 0-50-4 20 20 0 0 0-6 40Z" style="fill:${PC};stroke:${P};stroke-width:2"/>
    <path d="M48 30 116 96" style="stroke:${E};stroke-width:7;stroke-linecap:round"/>
    <circle cx="124" cy="30" r="10" style="fill:${EC}"/>
    <path d="M124 24v7M124 35v.5" style="stroke:${E};stroke-width:3;stroke-linecap:round"/>`,
  movimientos: `${fondo}
    <rect x="44" y="34" width="72" height="22" rx="11" style="fill:${SUCC}"/>
    <path d="M58 45h24m-8-7 8 7-8 7" style="fill:none;stroke:${SUC};stroke-width:4;stroke-linecap:round;stroke-linejoin:round"/>
    <rect x="44" y="64" width="72" height="22" rx="11" style="fill:${WC}"/>
    <path d="M102 75H78m8-7-8 7 8 7" style="fill:none;stroke:${W};stroke-width:4;stroke-linecap:round;stroke-linejoin:round"/>
    ${destellos}`,
};

/** @param {'caja'|'busqueda'|'ok'|'error'|'movimientos'} nombre */
export function ilustracion(nombre) {
  return raw(`<svg class="empty__art" viewBox="0 0 160 120" aria-hidden="true" focusable="false">${DIBUJOS[nombre] ?? DIBUJOS.caja}</svg>`);
}
