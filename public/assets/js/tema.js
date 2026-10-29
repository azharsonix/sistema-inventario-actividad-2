/**
 * Tema claro/oscuro. Por defecto sigue a prefers-color-scheme; el botón
 * alterna y guarda la preferencia en localStorage (si está disponible).
 */
const CLAVE = 'tecnostock-tema';
const consulta = window.matchMedia('(prefers-color-scheme: dark)');

const leer = () => {
  try { return localStorage.getItem(CLAVE); } catch { return null; }
};
const guardar = (valor) => {
  try { localStorage.setItem(CLAVE, valor); } catch { /* sin almacenamiento */ }
};

export const temaEfectivo = () =>
  document.documentElement.dataset.theme || (consulta.matches ? 'dark' : 'light');

function reflejar(boton) {
  const oscuro = temaEfectivo() === 'dark';
  const texto = oscuro ? 'Activar tema claro' : 'Activar tema oscuro';
  boton.querySelector('.icon').textContent = oscuro ? 'light_mode' : 'dark_mode';
  boton.setAttribute('aria-label', texto);
  boton.title = texto;
  document.getElementById('meta-tema')?.setAttribute('content', oscuro ? '#111418' : '#F8F9FF');
}

export function iniciarTema(boton) {
  const guardado = leer();
  if (guardado === 'light' || guardado === 'dark') document.documentElement.dataset.theme = guardado;
  reflejar(boton);

  boton.addEventListener('click', () => {
    const siguiente = temaEfectivo() === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = siguiente;
    guardar(siguiente);
    reflejar(boton);
  });
  consulta.addEventListener('change', () => reflejar(boton));
}
