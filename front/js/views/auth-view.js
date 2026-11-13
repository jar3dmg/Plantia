/** Muestra el formulario de inicio de sesión o de registro. */
export function renderAuth(container, mode, { submit, navigate, back }) {
  container.innerHTML = `
  <section class="auth-layout">
    <aside class="art">
      <button class="auth-back" type="button" data-auth-back>
        ← Volver a Plantia
      </button>

      <div class="brand">
        PLANTIA
        <small>tu botánica de confianza</small>
      </div>

      <div class="art-mark">
        <img
          class="auth-logo-image"
          src="front/images/plantia-logo.png"
          alt="Logo de Plantia"
        >
      </div>

      <h1>Tu mundo verde comienza aquí.</h1>
      <p>Descubre cursos y una ruta de aprendizaje pensada para ti y tus plantas.</p>

      <div class="art-pills">
        <span>Aprende</span>
        <span>Cuida</span>
        <span>Cultiva</span>
      </div>
    </aside>

    <div class="form-wrap">
      <h2>${mode === "login" ? "Bienvenido de vuelta" : "Crea tu espacio verde"}</h2>

      <p class="hint">
        ${
          mode === "login"
            ? "Tu siguiente aprendizaje te espera."
            : "En unos minutos diseñaremos una ruta para ti."
        }
      </p>

      <form class="form" data-auth-form>
        ${
          mode === "register"
            ? `<label>Nombre(s)<input name="name" type="text" autocomplete="given-name" maxlength="100" required></label>
             <label>Apellido paterno<input name="paternal_last_name" type="text" autocomplete="family-name" maxlength="100" required></label>
             <label>Apellido materno<input name="maternal_last_name" type="text" autocomplete="additional-name" maxlength="100" required></label>
             <label>Teléfono<input name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="+52 55 1234 5678" required></label>`
            : ""
        }

        <label>
          Correo electrónico
          <input name="email" type="email" required>
        </label>

        <label>
          Contraseña
          <input name="password" type="password" minlength="6" required>
        </label>

        <div data-form-message></div>

        <button class="primary">
          ${mode === "login" ? "Entrar a Plantia" : "Crear mi cuenta"}
        </button>
      </form>

    </div>
  </section>
`;
  container
    .querySelector("[data-auth-back]")
    ?.addEventListener("click", (event) => {
      event.preventDefault();
      back?.();
    });
  container.querySelector("[data-auth-form]").onsubmit = (event) => {
    event.preventDefault();
    submit(Object.fromEntries(new FormData(event.currentTarget)));
  };
}
