import { courseCoverUrl, escapeHtml, icon } from "./view-utils.js";

export function formatDuration(minutes) {
  const total = Number(minutes) || 0;
  if (!total) return "A tu ritmo";
  const hours = Math.floor(total / 60);
  const remainder = total % 60;
  if (!hours) return `${remainder} min`;
  return remainder ? `${hours} h ${remainder} min` : `${hours} h`;
}

export function formatLevel(level) {
  return {
    beginner: "Principiante",
    intermediate: "Intermedio",
    advanced: "Avanzado",
  }[level] || level || "Todos los niveles";
}

// Plantillas del catálogo y las lecciones.

export function renderCatalog(courses) {
  const cover = (course) =>
    courseCoverUrl(course)
      ? `<img class="course-cover-image" src="${escapeHtml(courseCoverUrl(course))}" alt="Portada: ${escapeHtml(course.title)}" loading="lazy">`
      : icon("book", "icon-xl");
  const cards = courses
    .map(
      (course, index) =>
        `<article class="course catalog-course ${course.status ? "is-enrolled" : ""}" style="--card-index:${index}" data-course="${escapeHtml(course.slug)}" data-course-id="${Number(course.id)}" tabindex="0" role="link" aria-label="Abrir curso: ${escapeHtml(course.title)}"><div class="course-art course-cover">${cover(course)}<span>${formatDuration(course.estimated_minutes)}</span>${course.status ? '<span class="course-enrolled-badge">Inscrito</span>' : ""}</div><div class="course-copy"><span class="course-topic">${escapeHtml(course.category)}</span><h3>${escapeHtml(course.title)}</h3><p>${escapeHtml(course.short_description)}</p>${course.recommendation_reason ? `<p class="course-recommendation">Para ti: ${escapeHtml(course.recommendation_reason)}</p>` : ""}<div class="catalog-course-meta"><span>${escapeHtml(formatLevel(course.level))}</span><span>${formatDuration(course.estimated_minutes)}</span></div><span class="card-open-hint">${course.status ? "Continuar curso" : "Ver curso"} ${icon("arrow")}</span><button type="button" class="dismiss-course-button" data-dismiss-course>No me interesa</button></div></article>`,
    )
    .join("");
  return `<main class="home catalog-page"><header class="catalog-banner"><div><span class="eyebrow">Biblioteca Plantia</span><h1>Encuentra tu próximo aprendizaje</h1><p>Explora todos los cursos disponibles y elige por dónde quieres empezar.</p><span class="catalog-count">${courses.length} ${courses.length === 1 ? "curso" : "cursos"}</span></div><span class="catalog-banner-art" aria-hidden="true">${icon("leaf", "icon-xl")}</span></header>${courses.length ? `<section class="catalog-results"><div class="course-grid">${cards}</div></section>` : '<section class="catalog-empty"><span class="eyebrow">Biblioteca al día</span><h2>Estamos preparando nuevos cursos</h2><p>Vuelve pronto para descubrir tu próximo aprendizaje.</p></section>'}</main>`;
}

export function renderCourse(course) {
  const coverUrl = courseCoverUrl(course);
  const cover = coverUrl
    ? `<img class="course-detail-cover-image" src="${escapeHtml(coverUrl)}" alt="Portada: ${escapeHtml(course.title)}">`
    : "";
  return `<main class="home course-detail ${course.status ? "enrolled-course" : ""}"><button class="text-button" data-back-courses>${icon("arrow")} Volver a cursos</button>${cover ? `<div class="course-detail-cover">${cover}</div>` : ""}<header class="page-header"><span class="eyebrow">${escapeHtml(course.category)}</span><h1>${escapeHtml(course.title)}</h1><p>${escapeHtml(course.description)}</p><p><b>${course.estimated_minutes} min &middot; ${escapeHtml(course.level)} &middot; Avance: ${Math.round(Number(course.progress))}%</b></p>${course.status ? '<span class="enrolled-label">Inscrito</span>' : '<button class="primary" data-enroll>Inscribirme y comenzar</button>'}<div class="progress-track"><i style="width:${Math.max(0, Math.min(100, Number(course.progress)))}%"></i></div><h2>Objetivos</h2><ul>${(course.outcomes || []).map((item) => `<li>${escapeHtml(item)}</li>`).join("")}</ul><h2>Requisitos</h2><ul>${(course.prerequisites || []).map((item) => `<li>${escapeHtml(item)}</li>`).join("")}</ul></header><section class="lesson-list">${(course.modules || []).map((lesson) => `<article class="lesson-card"><span class="lesson-number">${lesson.lesson_position}</span><div><span class="course-topic">${escapeHtml(lesson.title)}</span><h2>${escapeHtml(lesson.lesson_title)} ${Number(lesson.completed) ? "&#10003;" : ""}</h2><p>${escapeHtml(lesson.lesson_description)}</p><button class="text-button" data-lesson="${lesson.lesson_id}" ${!course.status && Number(lesson.lesson_position) !== 1 ? "disabled" : ""}>${!course.status && Number(lesson.lesson_position) !== 1 ? "Inscríbete para abrir" : "Abrir lección"}</button></div></article>`).join("")}</section></main>`;
}
export function renderLesson(lesson) {
  const blocksData = lesson.blocks || [];
  const quizTotal = blocksData.filter((block) => block.type === "quiz").length;
  let quizNumber = 0;
  const blocks = blocksData
    .map((block) => {
      const data = block.content_data || {};
      if (block.type === "quiz") {
        quizNumber++;
        const quizLabel =
          quizTotal > 1
            ? `Evaluación · Reactivo ${quizNumber} de ${quizTotal}`
            : "Cuestionario";
        return `<article class="lesson-card lesson-quiz"><div><span class="course-topic">${quizLabel}</span><h2>${escapeHtml(block.title)}</h2><p>${escapeHtml(data.question)}</p><div class="quiz-options">${(data.options || []).map((option, index) => `<button class="secondary" data-answer="${index}" data-block="${block.id}">${escapeHtml(option)}</button>`).join("")}</div><p class="hint" data-feedback="${block.id}" aria-live="polite"></p></div></article>`;
      }
      if (block.type === "practice")
        return `<article class="lesson-card lesson-practice"><div><span class="course-topic">Actividad práctica</span><h2>${escapeHtml(block.title)}</h2><p class="practice-instructions">${escapeHtml(data.instructions).replace(/\n/g, "<br>")}</p><label class="practice-label" for="practice-${block.id}">Tu respuesta</label><textarea id="practice-${block.id}" class="practice-answer" data-practice-answer="${block.id}" rows="8" placeholder="Escribe aquí tu respuesta"></textarea><button class="secondary" data-submit-practice="${block.id}">Entregar actividad</button><p class="hint" data-practice-feedback="${block.id}" aria-live="polite"></p></div></article>`;
      const paragraphs = String(block.body || "")
        .split(/\n\s*\n/)
        .filter((paragraph) => paragraph.trim())
        .map(
          (paragraph) =>
            `<p>${escapeHtml(paragraph).replace(/\n/g, "<br>")}</p>`,
        )
        .join("");
      return `<article class="lesson-card lesson-reading"><div><span class="course-topic">Lectura</span><h2>${escapeHtml(block.title)}</h2><div class="lesson-reading-content">${paragraphs}</div></div></article>`;
    })
    .join("");
  return `<main class="home course-detail"><button class="text-button" data-back-course>${icon("arrow")} Volver al curso</button><header class="page-header"><span class="eyebrow">${escapeHtml(lesson.course_title)}</span><h1>${escapeHtml(lesson.title)}</h1><p>${escapeHtml(lesson.description)}</p></header><section class="lesson-list">${blocks}<button class="primary" data-complete>Marcar lección como completada</button></section></main>`;
}
