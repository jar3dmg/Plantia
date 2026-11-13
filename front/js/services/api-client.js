/** Ejecuta una solicitud contra la API y normaliza los errores de red/JSON. */
export async function request(action, { payload, query = {} } = {}) {
  const params = new URLSearchParams({ action, ...query });
  let response;

  try {
    response = await fetch(`api.php?${params}`, {
      method: payload === undefined ? "GET" : "POST",
      credentials: "same-origin",
      headers:
        payload === undefined ? {} : { "Content-Type": "application/json" },
      body: payload === undefined ? undefined : JSON.stringify(payload),
    });
  } catch {
    return {
      error:
        "No se pudo conectar con Plantia. Comprueba que el servidor esté activo.",
    };
  }

  try {
    return await response.json();
  } catch {
    return {
      error: `El servidor respondió con un formato inesperado (HTTP ${response.status}).`,
    };
  }
}
