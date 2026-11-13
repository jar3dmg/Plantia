import { courseCoverUrl, escapeHtml, icon } from "./view-utils.js";

/** Genera una tarjeta resumida para el catálogo público. */
function courseCard(course, index) {
  const cover = courseCoverUrl(course);
  const minutes = Number(course.estimated_minutes) || 0;
  const duration = minutes
    ? minutes < 60
      ? `${minutes} min`
      : `${Math.floor(minutes / 60)} h${minutes % 60 ? ` ${minutes % 60} min` : ""}`
    : "A tu ritmo";
  const levels = {
    beginner: "Principiante",
    intermediate: "Intermedio",
    advanced: "Avanzado",
  };
  return `<article class="landing-course-card" style="--card-order:${index}">
        <div class="landing-course-image">${cover ? `<img src="${escapeHtml(cover)}" alt="Portada del curso ${escapeHtml(course.title)}" loading="lazy">` : `<span>${icon("leaf", "icon-xl")}</span>`}<small>${duration}</small></div>
        <div class="landing-course-body"><span class="eyebrow">${escapeHtml(course.category || "Botánica")}</span><h3>${escapeHtml(course.title)}</h3><p>${escapeHtml(course.short_description || "")}</p><span class="landing-course-level">${escapeHtml(levels[course.level] || course.level || "Para aprender a tu ritmo")}</span></div>
    </article>`;
}

/** Renderiza la portada pública y conecta sus llamadas a la acción. */
export function renderLanding(container, courses = []) {
  const courseCards = courses.length
    ? courses.slice(0, 3).map(courseCard).join("")
    : `<article class="landing-course-empty"><span>${icon("leaf", "icon-xl")}</span><h3>Estamos preparando el catálogo</h3><p>Muy pronto encontrarás cursos para cuidar, observar y cultivar plantas.</p></article>`;

  container.innerHTML = `<div class="landing-page">
        <header class="landing-nav"><a class="landing-brand" href="#inicio" aria-label="Plantia, inicio"><span class="landing-brand-mark"><img src="front/images/plantia-logo.png" alt=""></span><span>PLANTIA<small>tu botánica de confianza</small></span></a><nav aria-label="Navegación principal"><a href="#cursos">Cursos</a><a href="#como-funciona">Cómo funciona</a><a href="#equipo">Equipo</a></nav><div class="landing-nav-actions"><button class="landing-login" data-landing-login>Iniciar sesión</button><button class="landing-join" data-landing-register>Crear cuenta ${icon("arrow")}</button></div><button class="landing-menu" type="button" aria-label="Abrir menú" aria-expanded="false"><span class="menu-lines" aria-hidden="true"></span></button></header>
        <main>
            <section class="landing-hero" id="inicio"><div class="landing-hero-copy"><span class="landing-kicker"><i></i> Aprende a cuidar lo que crece</span><h1>Tu curiosidad<br>también <em>florece.</em></h1><p>Aprende botánica con cursos claros, prácticos y hechos para acompañarte desde la primera hoja hasta tu próximo cultivo.</p><div class="landing-hero-actions"><button class="landing-join landing-join-large" data-landing-register>Empieza a aprender ${icon("arrow")}</button><a class="landing-explore" href="#cursos">Explorar cursos <span aria-hidden="true" class="explore-arrow"></span></a></div><div class="landing-proof"><div class="landing-proof-avatars" aria-hidden="true"><span></span><span></span><span></span></div><p><b>Aprende a tu ritmo</b><br>Una lección a la vez, desde donde estés.</p></div></div><div class="landing-hero-art" aria-label="Logo de Plantia: una planta en maceta sobre un libro abierto"><div class="landing-sun"></div><img class="landing-hero-logo" src="front/images/plantia-logo.png" alt=""><div class="landing-hero-wordmark"><b>PLANTIA</b><span>tu botánica de confianza</span></div><span class="landing-art-spark spark-a" aria-hidden="true"></span><span class="landing-art-spark spark-b" aria-hidden="true"></span></div></section>
            <section class="landing-values" aria-label="Ventajas de Plantia"><div><span>01</span><p>Aprende con <b>claridad</b></p></div><div><span>02</span><p>Practica a tu <b>ritmo</b></p></div><div><span>03</span><p>Fuentes y uso de IA <b>visibles</b></p></div></section>
            <section class="landing-courses" id="cursos"><div class="landing-section-heading"><div><span class="eyebrow">Una biblioteca viva</span><h2>Un buen lugar para empezar.</h2></div><p>Encuentra el curso que se adapta a tus intereses y crea una ruta propia de aprendizaje.</p></div><div class="landing-course-grid">${courseCards}</div><div class="landing-courses-action"><button class="landing-outline" data-landing-register>Ver todos los cursos ${icon("arrow")}</button></div></section>
            <section class="landing-how" id="como-funciona"><div class="landing-how-art"><span class="landing-how-orbit orbit-one"></span><span class="landing-how-orbit orbit-two"></span><div class="landing-how-center">${icon("leaf", "icon-xl")}</div><span class="how-note note-one">Observa</span><span class="how-note note-two">Aprende</span><span class="how-note note-three">Cultiva</span></div><div class="landing-how-copy"><span class="eyebrow">Aprendizaje que echa raíces</span><h2>Pequeños pasos.<br><em>Grandes cambios.</em></h2><p>En Plantia conviertes tus ganas de aprender en conocimientos que puedes llevar a tu jardín, tus macetas o tu huerto.</p><ul><li><span aria-hidden="true"></span> Elige un curso según lo que quieres aprender.</li><li><span aria-hidden="true"></span> Avanza con lecciones y actividades prácticas.</li><li><span aria-hidden="true"></span> Retoma tu ruta y consulta tu progreso cuando quieras.</li></ul><button class="landing-text-link" data-landing-register>Encuentra tu primer curso ${icon("arrow")}</button></div></section>
            <section class="landing-team" id="equipo"><div class="landing-team-heading"><span class="eyebrow">Detrás de Plantia</span><h2>Hecho con curiosidad<br>y amor por las plantas.</h2><p>Una plataforma para aprender, compartir conocimiento y cuidar mejor nuestro entorno.</p></div><div class="landing-creators"><article><span class="creator-number">01</span><span class="creator-flower" aria-hidden="true"></span><h3>Parra Padilla<br>Sua Carolina</h3><small>CREADORA DE PLANTIA</small></article><article><span class="creator-number">02</span><span class="creator-flower" aria-hidden="true"></span><h3>Meza André Luis<br>Alejandro</h3><small>CREADOR DE PLANTIA</small></article><article><span class="creator-number">03</span><span class="creator-flower" aria-hidden="true"></span><h3>Méndez García<br>Jared</h3><small>CREADOR DE PLANTIA</small></article></div></section>
            <section class="landing-final-cta"><div><span class="eyebrow">Tu siguiente paso empieza aquí</span><h2>Haz espacio para<br>algo que crece.</h2><p>Empieza hoy y cultiva nuevos conocimientos a tu ritmo.</p><button class="landing-join landing-join-large" data-landing-register>Crear mi cuenta ${icon("arrow")}</button></div><span class="final-cta-leaf">${icon("leaf", "icon-xl")}</span></section>
        </main><footer class="landing-footer"><a class="landing-brand" href="#inicio"><span class="landing-brand-mark"><img src="front/images/plantia-logo.png" alt=""></span><span>PLANTIA<small>tu botánica de confianza</small></span></a><p>Aprende, cuida y cultiva.</p><a href="#equipo">Conoce al equipo <span class="footer-arrow" aria-hidden="true"></span></a></footer>
    </div>`;

  const finalHeading = container.querySelector(".landing-final-cta h2");
  const finalCopy = container.querySelector(".landing-final-cta p");
  if (finalHeading) finalHeading.innerHTML = "Empieza hoy a<br><em>cultivar lo que sabes.</em>";
  if (finalCopy) finalCopy.textContent = "Descubre conocimientos prácticos y avanza a tu propio ritmo.";

  container
    .querySelectorAll("[data-landing-login]")
    .forEach((button) =>
      button.addEventListener("click", () =>
        container.dispatchEvent(
          new CustomEvent("landing:navigate", { detail: "login" }),
        ),
      ),
    );
  container
    .querySelectorAll("[data-landing-register]")
    .forEach((button) =>
      button.addEventListener("click", () =>
        container.dispatchEvent(
          new CustomEvent("landing:navigate", { detail: "register" }),
        ),
      ),
    );
  const menu = container.querySelector(".landing-menu");
  menu?.addEventListener("click", () => {
    const open = menu.getAttribute("aria-expanded") !== "true";
    menu.setAttribute("aria-expanded", String(open));
    container
      .querySelector(".landing-nav")
      ?.classList.toggle("menu-open", open);
  });
}
