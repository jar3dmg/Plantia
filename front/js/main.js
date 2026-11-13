/** Punto de entrada: controla sesión, navegación y renderizado de las vistas. */
import {
  authenticate,
  endSession,
  getSession,
  getPlantCatalog,
  saveOnboardingProfile,
  savePreferences,
} from "./services/session-service.js";
import {
  completeLesson,
  enrollInCourse,
  getCourse,
  getLesson,
  listCourses,
  listReferences,
  recordAdaptiveEvent,
  submitActivityAttempt,
} from "./services/course-service.js";
import { dashboardLayout, bindDashboard } from "./views/dashboard-view.js";
import { renderAuth } from "./views/auth-view.js";
import { renderOnboarding } from "./views/onboarding-view.js?coldstart-v3";
import {
  renderCatalog,
  renderCourse,
  renderLesson,
  formatDuration,
  formatLevel,
} from "./views/course-view.js";
import {
  courseCoverUrl,
  escapeHtml,
  icon,
  showMessage,
} from "./views/view-utils.js";
import { renderReferenceGroups } from "./views/references-view.js";
import { renderLanding } from "./views/landing-view.js";

const app = document.querySelector("#app");
const state = {
  user: null,
  profile: null,
  step: 1,
  answers: { has_garden: false, has_pots: false, interests: [], goal: "", region_code: "", has_pets: null, plant_codes: [] },
  plants: [],
};
const routeNames = {
  home: "inicio",
  courses: "cursos",
  route: "mi-ruta",
  profile: "perfil",
};
const routeViews = {
  inicio: "home",
  cursos: "courses",
  "mi-ruta": "route",
  perfil: "profile",
};

function renderFatal(error) {
  console.error("Error al iniciar Plantia:", error);
  app.innerHTML =
    '<section class="center"><article class="card"><h1>No se pudo cargar Plantia</h1><p class="hint">Ocurrió un error al iniciar. Recarga la página; si continúa, comprueba que PHP y MySQL estén activos.</p><button class="secondary" onclick="location.reload()">Recargar</button></article></section>';
}

function showLoading(message = "Cargando...") {
  app.innerHTML = `<section class="center"><article class="card"><p class="hint">${escapeHtml(message)}</p></article></section>`;
}

function showServiceError(container, message) {
  const main = container.querySelector("main") || container;
  showMessage(
    main,
    message || "No se pudieron cargar los datos. Inténtalo de nuevo.",
  );
}

function requestCourseEnrollment(course) {
  return new Promise((resolve) => {
    const dialog = document.createElement("dialog");
    dialog.className = "enrollment-dialog";
    dialog.innerHTML = `<div class="enrollment-dialog-content"><span class="eyebrow">Antes de continuar</span><h2>Inscríbete para iniciar esta lección</h2><p></p><div class="enrollment-dialog-actions"><button type="button" class="secondary" data-cancel-enrollment>Ahora no</button><button type="button" class="primary" data-confirm-enrollment>Inscribirme</button></div></div>`;
    dialog.querySelector("p").textContent = `Para abrir esta lección necesitas inscribirte en “${course.title}”.`;
    const finish = (confirmed) => { dialog.close(); dialog.remove(); resolve(confirmed); };
    dialog.querySelector("[data-cancel-enrollment]").onclick = () => finish(false);
    dialog.querySelector("[data-confirm-enrollment]").onclick = () => finish(true);
    dialog.addEventListener("cancel", (event) => { event.preventDefault(); finish(false); });
    document.body.append(dialog);
    dialog.showModal();
  });
}

function courseCover(course) {
  const coverUrl = courseCoverUrl(course);
  return coverUrl
    ? `<img class="course-cover-image" src="${escapeHtml(coverUrl)}" alt="Portada: ${escapeHtml(course.title)}" loading="lazy">`
    : icon("leaf", "icon-xl");
}

function attachDashboard(view) {
  bindDashboard(app, {
    navigate: renderView,
    logout,
    loadReferences: async (dialog) => {
      const target = dialog?.querySelector("[data-reference-list]");
      if (!target) return;
      const result = await listReferences();
      if (result.error) {
        target.textContent = result.error;
        return;
      }
      target.innerHTML = renderReferenceGroups(result.references || []);
    },
  });
}

function renderDashboard(view, content) {
  app.innerHTML = dashboardLayout(view, state.user, state.profile, content);
  attachDashboard(view);
}

async function renderHome() {
  showLoading("Preparando tu espacio de aprendizaje...");
  const result = await listCourses();
  if (result.error) {
    renderDashboard(
      "home",
      `<main class="home"><section class="welcome-banner"><span class="eyebrow">Tu espacio de aprendizaje</span><h1>Hola, ${escapeHtml(state.user.name.split(" ")[0])}</h1><p>Bienvenido a Plantia. Explora los cursos y aprende a tu ritmo.</p><button class="hero-button" data-open-courses>Explorar cursos ${icon("arrow")}</button></section><section class="empty-course-feature"><span class="eyebrow">Tu aprendizaje comienza aquí</span><h2>Descubre tu próximo curso</h2><p>Explora el catálogo y encuentra algo nuevo para cultivar.</p><button class="primary" data-open-courses>Ver cursos disponibles</button></section></main>`,
    );
    app
      .querySelectorAll("[data-open-courses]")
      .forEach((button) => (button.onclick = () => renderView("courses")));
    return;
  }
  const courses = result.courses || [];
  const enrolled = courses.filter((course) => Boolean(course.status));
  const recent =
    enrolled.find((course) => course.last_accessed_at) || enrolled[0];
  const recentFeature = recent
    ? `<section class="recent-course-panel"><div class="recent-course-art">${courseCover(recent)}<span class="recent-course-badge">${recent.status === "completed" ? "Completado" : "Tu curso en curso"}</span></div><div class="recent-course-copy"><span class="course-topic">${escapeHtml(recent.category)}</span><h2>${escapeHtml(recent.title)}</h2><p>${escapeHtml(recent.short_description)}</p><div class="progress-label"><span>Tu avance</span><b>${Math.round(Number(recent.progress) || 0)}%</b></div><div class="progress-track"><i style="width:${Math.max(0, Math.min(100, Number(recent.progress) || 0))}%"></i></div><button class="primary" data-resume-course="${escapeHtml(recent.slug)}">${recent.status === "completed" ? "Volver al curso" : "Continuar aprendiendo"} ${icon("arrow")}</button></div></section>`
    : `<section class="empty-course-feature"><span class="eyebrow">Tu aprendizaje comienza aquí</span><h2>Descubre tu próximo curso</h2><p>Aún no tienes cursos inscritos. Explora el catálogo y elige uno para comenzar.</p><button class="secondary" data-open-courses>Ver cursos disponibles ${icon("arrow")}</button></section>`;
  renderDashboard(
    "home",
    `<main class="home home-page"><section class="welcome-banner"><div class="welcome-content"><span class="tag">Tu espacio de aprendizaje</span><h1>Hola, ${escapeHtml(state.user.name.split(" ")[0])}</h1><p>Nos alegra tenerte aquí. Sigue cultivando tu curiosidad, una lección a la vez.</p><button class="hero-button" data-open-courses>Explorar cursos ${icon("arrow")}</button></div><span class="welcome-orbit" aria-hidden="true">${icon("leaf", "icon-xl")}</span></section><section class="home-learning-section"><div class="section-heading"><div><span class="eyebrow">Continúa donde te quedaste</span><h2>Tu aprendizaje reciente</h2></div></div>${recentFeature}</section></main>`,
  );
  const discoveryCourses = courses.slice(0, 3);
  if (discoveryCourses.length) {
    app.querySelector(".home-learning-section")?.insertAdjacentHTML(
      "afterend",
      `<section class="home-discovery"><div class="section-heading"><div><span class="eyebrow">Sigue explorando</span><h2>Más cursos para ti</h2></div><button class="text-button" data-open-courses>Ver catálogo completo ${icon("arrow")}</button></div><div class="home-course-grid">${discoveryCourses.map((course) => `<article class="home-course-card" data-home-course="${escapeHtml(course.slug)}" tabindex="0" role="link" aria-label="Abrir curso: ${escapeHtml(course.title)}"><div class="home-course-cover">${courseCover(course)}${course.status ? '<span>Inscrito</span>' : ""}</div><div><span class="course-topic">${escapeHtml(course.category)}</span><h3>${escapeHtml(course.title)}</h3><p>${escapeHtml(course.short_description)}</p><b>${course.status ? "Continuar curso" : "Explorar curso"} ${icon("arrow")}</b></div></article>`).join("")}</div></section>`,
    );
  }
  app
    .querySelectorAll("[data-open-courses]")
    .forEach((button) => (button.onclick = () => renderView("courses")));
  app
    .querySelector("[data-resume-course]")
    ?.addEventListener("click", (event) =>
      renderCourseDetail(event.currentTarget.dataset.resumeCourse, "home"),
    );
  app.querySelectorAll("[data-home-course]").forEach((card) => {
    const openCourse = () => renderCourseDetail(card.dataset.homeCourse, "home");
    card.onclick = openCourse;
    card.onkeydown = (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openCourse();
      }
    };
  });
}

async function renderCourses() {
  showLoading("Cargando cursos disponibles...");
  const result = await listCourses();
  if (result.error) {
    renderDashboard(
      "courses",
      '<main class="home"><h1>Cursos disponibles</h1></main>',
    );
    showServiceError(app, result.error);
    return;
  }
  renderDashboard("courses", renderCatalog(result.courses || []));
  app
    .querySelectorAll("[data-course]")
    .forEach(
      (card) => {
        const openCourse = () => renderCourseDetail(card.dataset.course, "courses");
        card.onclick = openCourse;
        card.onkeydown = (event) => {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            openCourse();
          }
        };
      },
    );
  app.querySelectorAll("[data-dismiss-course]").forEach((button) => {
    button.onclick = async (event) => {
      event.preventDefault();
      event.stopPropagation();
      const card = button.closest("[data-course]");
      const result = await recordAdaptiveEvent(Number(card?.dataset.courseId), "dismissed");
      if (result.error) { showServiceError(app, result.error); return; }
      renderCourses();
    };
  });
}
async function renderCourseDetail(slug, returnView = "courses") {
  showLoading("Cargando curso...");
  const course = await getCourse(slug);
  if (course.error) {
    await renderCourses();
    showServiceError(app, course.error);
    return;
  }
  renderDashboard(returnView, renderCourse(course));
  const courseSummary = app.querySelector(".course-detail .page-header p b");
  if (courseSummary) {
    courseSummary.textContent = `${formatDuration(course.estimated_minutes)} · ${formatLevel(course.level)} · Avance: ${Math.round(Number(course.progress) || 0)}%`;
  }
  if (!course.status) {
    app.querySelectorAll("[data-lesson][disabled]").forEach((button) => {
      button.disabled = false;
      button.textContent = "Inscribirme para abrir";
    });
  }
  app.querySelector("[data-back-courses]").onclick = () =>
    renderView(returnView);
  app
    .querySelector("[data-enroll]")
    ?.addEventListener("click", async (event) => {
      event.currentTarget.disabled = true;
      const result = await enrollInCourse(course.id);
      if (result.error) {
        event.currentTarget.disabled = false;
        showServiceError(app, result.error);
        return;
      }
      await renderCourseDetail(slug, returnView);
    });
  app
    .querySelectorAll("[data-lesson]")
    .forEach(
      (button) =>
        (button.onclick = async () => {
          if (!course.status) {
            const confirmed = await requestCourseEnrollment(course);
            if (!confirmed) return;
            const result = await enrollInCourse(course.id);
            if (result.error) { showServiceError(app, result.error); return; }
          }
          renderLessonView(button.dataset.lesson, returnView);
        }),
    );
}

async function renderLessonView(id, returnView = "courses") {
  showLoading("Cargando lección...");
  const lesson = await getLesson(id);
  if (lesson.error) {
    await renderCourses();
    showServiceError(app, lesson.error);
    return;
  }
  renderDashboard(returnView, renderLesson(lesson));
  app.querySelector("[data-back-course]").onclick = () =>
    renderCourseDetail(lesson.slug, returnView);
  app.querySelectorAll("[data-answer]").forEach(
    (button) =>
      (button.onclick = async () => {
        const result = await submitActivityAttempt(
          Number(button.dataset.block),
          Number(button.dataset.answer),
        );
        const feedback = app.querySelector(
          `[data-feedback="${button.dataset.block}"]`,
        );
        const options = app.querySelectorAll(
          `[data-answer][data-block="${button.dataset.block}"]`,
        );
        options.forEach((option) => {
          option.classList.remove(
            "answer-option-correct",
            "answer-option-incorrect",
          );
          option.setAttribute("aria-pressed", "false");
        });
        if (typeof result.passed === "boolean") {
          button.classList.add(
            result.passed ? "answer-option-correct" : "answer-option-incorrect",
          );
          button.setAttribute("aria-pressed", "true");
        }
        if (feedback) {
          feedback.textContent =
            result.error ||
            `${result.passed ? "Respuesta correcta." : "Respuesta incorrecta."} ${result.feedback || ""}`;
          feedback.classList.toggle(
            "answer-correct",
            !result.error && result.passed === true,
          );
          feedback.classList.toggle(
            "answer-incorrect",
            !result.error && result.passed === false,
          );
          feedback.setAttribute("role", "status");
        }
      }),
  );
  app.querySelectorAll("[data-submit-practice]").forEach(
    (button) =>
      (button.onclick = async () => {
        const blockId = Number(button.dataset.submitPractice);
        const answer =
          app.querySelector(`[data-practice-answer="${blockId}"]`)?.value || "";
        const result = await submitActivityAttempt(blockId, answer);
        const feedback = app.querySelector(
          `[data-practice-feedback="${blockId}"]`,
        );
        if (feedback)
          feedback.textContent = result.feedback || result.error || "";
      }),
  );
  app.querySelector("[data-complete]").onclick = async () => {
    const result = await completeLesson(Number(id));
    if (result.error) {
      showServiceError(app, result.error);
      return;
    }
    app.querySelector("[data-complete]").textContent =
      `Lección completada · ${result.progress}%`;
  };
}

async function renderRoute() {
  showLoading("Preparando tu ruta de aprendizaje...");
  const result = await listCourses();
  if (result.error) {
    renderDashboard("route", '<main class="home"><h1>Mi ruta</h1></main>');
    showServiceError(app, result.error);
    return;
  }
  const enrolled = (result.courses || []).filter((course) =>
    Boolean(course.status),
  );
  const recent =
    enrolled.find((course) => course.last_accessed_at) || enrolled[0];
  const progress = (course) =>
    Math.max(0, Math.min(100, Number(course.progress) || 0));
  const feature = recent
    ? `<section class="route-feature"><div class="route-feature-art">${courseCover(recent)}<span class="route-feature-kicker">${recent.status === "completed" ? "Curso completado" : "Retoma tu aprendizaje"}</span></div><div class="route-feature-copy"><span class="course-topic">${escapeHtml(recent.category)}</span><h2>${escapeHtml(recent.title)}</h2><p>${escapeHtml(recent.short_description)}</p><div class="progress-label"><span>${recent.status === "completed" ? "Completado" : "Avance del curso"}</span><b>${progress(recent)}%</b></div><div class="progress-track"><i style="width:${progress(recent)}%"></i></div><button class="primary" data-route-course="${escapeHtml(recent.slug)}">${recent.status === "completed" ? "Repasar curso" : "Continuar curso"} ${icon("arrow")}</button></div></section>`
    : '<section class="empty-course-feature"><span class="eyebrow">Tu ruta empieza aquí</span><h2>Aún no tienes cursos en curso</h2><p>Explora los cursos disponibles y agrega el primero a tu ruta de aprendizaje.</p><button class="primary" data-open-courses>Explorar cursos</button></section>';
  const rest = recent
    ? enrolled.filter((course) => course.slug !== recent.slug)
    : [];
  const rows = rest
    .map(
      (course) =>
        `<article class="route-list-item"><span class="route-list-icon route-list-cover">${courseCover(course)}</span><div class="route-list-copy"><span class="course-topic">${escapeHtml(course.category)}</span><h3>${escapeHtml(course.title)}</h3><p>${escapeHtml(course.short_description)}</p><div class="progress-track"><i style="width:${progress(course)}%"></i></div><small>${course.status === "completed" ? "Completado" : `${progress(course)}% completado`}</small></div><button class="text-button" data-route-course="${escapeHtml(course.slug)}">${course.status === "completed" ? "Repasar" : "Continuar"} ${icon("arrow")}</button></article>`,
    )
    .join("");
  renderDashboard(
    "route",
    `<main class="home route-page"><header class="page-header route-heading"><span class="eyebrow">Tu aprendizaje</span><h1>Mi ruta</h1><p>Aquí tienes tus cursos y el progreso que has construido.</p></header>${recent ? `<section class="route-feature-section"><div class="section-heading"><div><span class="eyebrow">Tu último acceso</span><h2>Continúa desde aquí</h2></div></div>${feature}</section>` : feature}<section class="route-list-section"><div class="section-heading"><div><span class="eyebrow">Tu biblioteca personal</span><h2>El resto de tus cursos</h2></div><span class="route-course-count">${rest.length} ${rest.length === 1 ? "curso" : "cursos"}</span></div>${rest.length ? `<div class="route-course-list">${rows}</div>` : recent ? '<p class="route-empty-note">Este es tu único curso inscrito por ahora.</p>' : ""}</section></main>`,
  );
  app
    .querySelectorAll("[data-open-courses]")
    .forEach((button) => (button.onclick = () => renderView("courses")));
  app
    .querySelectorAll("[data-route-course]")
    .forEach(
      (button) =>
        (button.onclick = () =>
          renderCourseDetail(button.dataset.routeCourse, "route")),
    );
}
function renderProfile() {
  const levels = {
    beginner: "Principiante",
    intermediate: "Intermedio",
    advanced: "Avanzado",
  };
  const profile = state.profile || {};
  const interests = profile.interests || [];
  const readingEnabled =
    profile.reading_mode === true ||
    profile.reading_mode === 1 ||
    profile.reading_mode === "1";
  renderDashboard(
    "profile",
    `<main class="home profile-page"><div class="profile-grid"><section class="profile-card"><h2>Datos de Cuenta</h2><div class="account"><span class="avatar">${escapeHtml(state.user.name.slice(0, 2).toUpperCase())}</span><div><b>${escapeHtml(state.user.name)}</b><p>${escapeHtml(state.user.email)}</p></div></div><h3>Preferencias de aprendizaje</h3><p class="setting-label">Mi objetivo actual</p><span class="selected-chip">${escapeHtml(profile.goal || "Sin definir")}</span><p class="setting-label">Nivel de experiencia</p><div class="segmented">${Object.entries(
      levels,
    )
      .map(
        ([value, label]) =>
          `<button type="button" data-level="${value}" aria-pressed="${(profile.knowledge_level || "beginner") === value}" class="${(profile.knowledge_level || "beginner") === value ? "selected" : ""}">${label}</button>`,
      )
      .join(
        "",
      )}</div><p class="setting-label">Intereses específicos</p><div class="interest-tags">${interests.length ? interests.map((item) => `<span>${escapeHtml(item)}</span>`).join("") : "<span>Sin intereses registrados</span>"}</div></section><section class="profile-card accessibility"><h2>Interfaz y accesibilidad</h2><div class="accessibility-setting"><p class="setting-label">Tamaño de texto</p><div class="segmented">${[
      ["small", "A- Pequeño"],
      ["normal", "A Normal"],
      ["large", "A+ Grande"],
    ]
      .map(
        ([value, label]) =>
          `<button type="button" data-font-size="${value}" aria-pressed="${(profile.font_size || "normal") === value}" class="${(profile.font_size || "normal") === value ? "selected" : ""}">${label}</button>`,
      )
      .join(
        "",
      )}</div></div><div class="accessibility-setting"><p class="setting-label">Tema visual</p><div class="segmented"><button type="button" data-theme-choice="light" aria-pressed="${(profile.theme || "light") === "light"}" class="${(profile.theme || "light") === "light" ? "selected" : ""}"><span aria-hidden="true">&#9728;</span> Claro</button><button type="button" data-theme-choice="dark" aria-pressed="${profile.theme === "dark"}" class="${profile.theme === "dark" ? "selected" : ""}"><span aria-hidden="true">&#9680;</span> Oscuro</button></div></div><div class="accessibility-setting reading-setting"><div><p class="setting-label">Modo lectura</p><p class="setting-description">Oculta elementos decorativos para enfocarte.</p></div><label class="switch"><input type="checkbox" data-reading ${readingEnabled ? "checked" : ""} aria-label="Activar modo lectura"><span></span><small>Modo simplificado</small></label></div><div class="accessibility-setting"><p class="setting-label">Tipografía para dislexia</p><p class="setting-description">Mejora el espaciado y la lectura continua.</p><div class="segmented"><button type="button" data-font-mode="standard" aria-pressed="${(profile.font_mode || "standard") === "standard"}" class="${(profile.font_mode || "standard") === "standard" ? "selected" : ""}">Fuente estándar</button><button type="button" data-font-mode="dyslexia" aria-pressed="${profile.font_mode === "dyslexia"}" class="${profile.font_mode === "dyslexia" ? "selected" : ""}">Modo dislexia</button></div></div></section></div></main>`,
  );
  app
    .querySelectorAll("[data-level]")
    .forEach(
      (button) =>
        (button.onclick = () =>
          savePreference({ knowledge_level: button.dataset.level })),
    );
  app
    .querySelectorAll("[data-font-size]")
    .forEach(
      (button) =>
        (button.onclick = () =>
          savePreference({ font_size: button.dataset.fontSize })),
    );
  app
    .querySelectorAll("[data-theme-choice]")
    .forEach(
      (button) =>
        (button.onclick = () =>
          savePreference({ theme: button.dataset.themeChoice })),
    );
  app.querySelector("[data-reading]").onchange = (event) =>
    savePreference({ reading_mode: event.target.checked });
  app
    .querySelectorAll("[data-font-mode]")
    .forEach(
      (button) =>
        (button.onclick = () =>
          savePreference({ font_mode: button.dataset.fontMode })),
    );
}

async function savePreference(changes) {
  const previous = { ...state.profile };
  if (state.profile) Object.assign(state.profile, changes);
  applyInterfacePreferences(state.profile || {});
  const result = await savePreferences({
    theme: state.profile?.theme || "light",
    font_size: state.profile?.font_size || "normal",
    font_mode: state.profile?.font_mode || "standard",
    reading_mode:
      state.profile?.reading_mode === true ||
      state.profile?.reading_mode === 1 ||
      state.profile?.reading_mode === "1",
    knowledge_level: state.profile?.knowledge_level || "beginner",
  });
  if (result.error) {
    if (state.profile) Object.assign(state.profile, previous);
    applyInterfacePreferences(state.profile || {});
    showServiceError(app, result.error);
    return;
  }
  if (app.querySelector(".profile-page")) renderProfile();
}

function applyInterfacePreferences(profile) {
  const root = document.documentElement;
  root.dataset.theme = profile.theme || "light";
  root.dataset.fontSize = profile.font_size || "normal";
  root.dataset.fontMode = profile.font_mode || "standard";
  root.classList.toggle(
    "reading-mode",
    profile.reading_mode === true ||
      profile.reading_mode === 1 ||
      profile.reading_mode === "1",
  );
}
function renderView(view) {
  const next =
    {
      home: renderHome,
      courses: renderCourses,
      route: renderRoute,
      profile: renderProfile,
    }[view] || renderHome;
  const hash = `#/${routeNames[view] || routeNames.home}`;
  if (location.hash !== hash) history.replaceState(null, "", hash);
  Promise.resolve(next()).catch(renderFatal);
}

function showOnboarding() {
  renderOnboarding(app, state.step, state.answers, state.plants, {
    logout,
    back: () => {
      state.step = Math.max(1, state.step - 1);
      showOnboarding();
    },
    submit: async (form) => {
      if (state.step === 1) {
        state.answers.has_garden = form.get("has_garden") === "on";
        state.answers.has_pots = form.get("has_pots") === "on";
      }
      if (state.step === 2) state.answers.interests = form.getAll("interest");
      if (state.step === 3) state.answers.goal = form.get("goal") || "";
      if (state.step === 4) state.answers.region_code = form.get("region_code") || "";
      if (state.step === 5) state.answers.plant_codes = form.getAll("plant_code");
      if (state.step === 6) state.answers.has_pets = form.get("has_pets") === "yes";
      if (state.step < 6) {
        state.step++;
        showOnboarding();
        return;
      }
      const result = await saveOnboardingProfile(state.answers);
      if (result.error) {
        showMessage(
          app.querySelector("[data-onboarding] [data-form-message]"),
          result.error,
        );
        return;
      }
      state.profile = { ...state.answers };
      renderView("home");
    },
  });
}

async function logout() {
  await endSession();
  state.user = null;
  state.profile = null;
  state.step = 1;
  showAuth();
}

async function handleAuth(credentials) {
  const mode = app
    .querySelector("[data-auth-form]")
    ?.querySelector('[name="name"]')
    ? "register"
    : "login";
  const result = await authenticate(mode, credentials);
  if (result.error) {
    showMessage(app.querySelector("[data-form-message]"), result.error);
    return;
  }
  await initialize();
}

function showAuth(mode = "login") {
  renderAuth(app, mode, {
    submit: handleAuth,
    navigate: showAuth,
    back: showPublicLanding,
  });
}

function transitionToAuth(mode) {
  const landing = app.querySelector(".landing-page");
  if (!landing) {
    showAuth(mode);
    return;
  }
  landing.classList.add("landing-leaving");
  window.setTimeout(() => showAuth(mode), 280);
}

async function showPublicLanding() {
  history.replaceState(null, "", `${location.pathname}${location.search}`);
  const result = await listCourses();
  renderLanding(app, result.error ? [] : result.courses || []);
  app.addEventListener(
    "landing:navigate",
    (event) => transitionToAuth(event.detail),
    { once: true },
  );
}

async function initialize() {
  showLoading("Iniciando Plantia...");
  const result = await getSession();
  if (result.error) throw new Error(result.error);
  state.user = result.user;
  state.profile = result.profile;
  if (!state.user) {
    await showPublicLanding();
    return;
  }
  if (!state.profile) {
    const plants = await getPlantCatalog();
    state.plants = plants.error ? [] : plants.plants || [];
    showOnboarding();
    return;
  }
  applyInterfacePreferences(state.profile);
  renderView(routeViews[location.hash.replace(/^#\/?/, "")] || "home");
}

window.addEventListener("hashchange", () => {
  if (!state.user || !state.profile) return;
  renderView(routeViews[location.hash.replace(/^#\/?/, "")] || "home");
});
initialize().catch(renderFatal);
