import { escapeHtml, icon } from "./view-utils.js";

const routes = {
  home: "inicio",
  courses: "cursos",
  route: "mi-ruta",
  profile: "perfil",
};
const labels = {
  home: "Inicio",
  courses: "Cursos",
  route: "Mi ruta",
  profile: "Perfil",
};

/** Construye el marco común de las pantallas privadas. */
export function dashboardLayout(view, user, profile, content) {
  const navigation = Object.entries(routes)
    .map(
      ([key, path]) => `
        <a href="#/${path}" data-route="${key}" class="${view === key ? "active" : ""}">
            ${icon({ home: "home", courses: "book", route: "route", profile: "user" }[key])}
            ${labels[key]}
        </a>
    `,
    )
    .join("");
  const firstName = escapeHtml((user?.name || "").split(" ")[0]);
  return `<div class="dashboard"><aside class="sidebar"><span class="logo"><img class="project-logo-image" src="front/images/plantia-logo.png" alt=""><span>PLANTIA<small>tu botánica de confianza</small></span></span><nav class="side-nav">${navigation}</nav><div class="sidebar-bottom"><details class="references"><summary>${icon("book")}<span><b>Referencias</b><small>Fuentes y uso de IA</small></span><i aria-hidden="true"></i></summary><div class="references-content"><p>Fuentes, contenido generado con IA e imágenes.</p><button class="references-button" type="button" data-open-references>Ver referencias completas</button></div></details><div class="side-user"><b>${firstName}</b><small>${escapeHtml(profile?.goal || "Tu espacio de aprendizaje")}</small><button class="secondary" data-logout>Cerrar sesión</button></div></div></aside>${content}</div><dialog class="references-dialog" aria-labelledby="referencesTitle" data-references-dialog><div class="references-dialog-head"><div><span class="eyebrow">Biblioteca de fuentes</span><h2 id="referencesTitle">Referencias</h2></div><button type="button" class="dialog-close" aria-label="Cerrar referencias" data-close-references>×</button></div><div class="references-full"><p class="references-intro">Fuentes de cursos, contenido de la plataforma e imágenes.</p><div data-reference-list><p>Cargando referencias...</p></div></div></dialog>`;
}

/** Asocia los eventos del panel lateral y del diálogo de referencias. */
export function bindDashboard(container, handlers) {
  container.querySelectorAll("[data-route]").forEach((link) =>
    link.addEventListener("click", (event) => {
      event.preventDefault();
      handlers.navigate(link.dataset.route);
    }),
  );
  container
    .querySelector("[data-logout]")
    ?.addEventListener("click", handlers.logout);
  const dialog = container.querySelector("[data-references-dialog]");
  container
    .querySelector("[data-open-references]")
    ?.addEventListener("click", () => {
      handlers.loadReferences(dialog);
      dialog?.showModal();
    });
  container
    .querySelector("[data-close-references]")
    ?.addEventListener("click", () => dialog?.close());
}
