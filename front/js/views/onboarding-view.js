import { goals, interests, plantTypes, regions } from "../catalog.js?coldstart-v2";
import { escapeHtml, icon } from "./view-utils.js";

function renderPlantSelector(answers, catalog) {
  const rawPlants = catalog.length ? catalog : plantTypes.map(([code, common_name]) => ({ code, common_name, scientific_name: "" }));
  const plants = [...new Map(rawPlants.map((plant) => [`${plant.common_name || ""}|${plant.scientific_name || ""}`.toLowerCase(), plant])).values()];
  const selected = new Set(answers.plant_codes || []);
  const inputs = plants.filter(({ code }) => selected.has(code)).map(({ code }) => `<input class="plant-selected-input" type="hidden" name="plant_code" value="${escapeHtml(code)}">`).join("");
  const rows = plants.map((plant) => `<button type="button" class="plant-search-row ${selected.has(plant.code) ? "selected" : ""}" data-plant-option="${escapeHtml(plant.code)}" data-common="${escapeHtml((plant.common_name || "").toLowerCase())}" data-scientific="${escapeHtml((plant.scientific_name || "").toLowerCase())}" data-search="${escapeHtml(`${plant.common_name || ""} ${plant.scientific_name || ""}`.toLowerCase())}"><span><b>${escapeHtml(plant.common_name || plant.scientific_name)}</b>${plant.scientific_name && plant.common_name ? `<small>${escapeHtml(plant.scientific_name)}</small>` : ""}</span><i aria-hidden="true">${selected.has(plant.code) ? "✓" : "+"}</i></button>`).join("");
  return `<span class="eyebrow">Paso 5 · Tus plantas</span><h1>¿Qué plantas tienes?</h1><p class="hint">Escribe un nombre común o científico: verás las cinco coincidencias más relevantes. Este paso es opcional.</p><div class="plant-picker"><label for="plant-search">Buscar una planta</label><input id="plant-search" type="search" data-plant-search placeholder="Ej. pothos, albahaca, Monstera" autocomplete="off"></div><div class="plant-search-list" data-plant-list hidden>${rows}</div><div data-selected-plants>${inputs}<p class="selected-plants-title">${selected.size ? "Tus plantas seleccionadas" : "Aún no has seleccionado plantas"}</p><ul class="selected-plants-list"></ul></div>`;
}

/** Muestra un paso del cuestionario inicial y conecta sus acciones. */
export function renderOnboarding(
  container,
  step,
  answers,
  plants,
  { submit, back, logout },
) {
  let content =
    step === 1
      ? `<span class="eyebrow">Paso 1 · Tu entorno</span><h1>Cuéntanos dónde crece tu vida verde.</h1><p class="hint">Esto nos ayuda a recomendarte mejores cursos.</p><div class="choices"><label class="choice"><span class="choice-icon">${icon("garden", "icon-lg")}</span><input type="checkbox" name="has_garden" ${answers.has_garden ? "checked" : ""}><b>Tengo jardín</b><small>Plantas al aire libre</small></label><label class="choice"><span class="choice-icon">${icon("pot", "icon-lg")}</span><input type="checkbox" name="has_pots" ${answers.has_pots ? "checked" : ""}><b>Tengo macetas</b><small>Plantas dentro o fuera de casa</small></label></div>`
      : step === 2
        ? `<span class="eyebrow">Paso 2 · Tus intereses</span><h1>¿Qué te gustaría explorar?</h1><p class="hint">Elige todos los temas que despierten tu curiosidad.</p><div class="choices">${interests.map((interest, index) => `<label class="choice"><span class="choice-icon">${icon(["leaf", "garden", "pot", "sun", "heart", "book"][index], "icon-lg")}</span><input type="checkbox" name="interest" value="${escapeHtml(interest)}" ${answers.interests.includes(interest) ? "checked" : ""}><b>${escapeHtml(interest)}</b></label>`).join("")}</div>`
      : step === 3
        ? `<span class="eyebrow">Paso 3 · Tu intención</span><h1>¿Qué quieres lograr con Plantia?</h1><p class="hint">Elegiremos cursos que se adapten a ti.</p><div class="choices">${goals.map((goal, index) => `<label class="choice"><span class="choice-icon">${icon(["leaf", "sun", "garden", "book"][index], "icon-lg")}</span><input type="radio" name="goal" value="${escapeHtml(goal)}" ${answers.goal === goal ? "checked" : ""}><b>${escapeHtml(goal)}</b></label>`).join("")}</div>`
        : step === 4
          ? `<span class="eyebrow">Paso 4 · Tu región</span><h1>¿Cuál describe mejor tu región?</h1><p class="hint">Usaremos esta información para recomendar contenidos adecuados a tus condiciones de cultivo.</p><div class="choices">${regions.map(([code, name], index) => `<label class="choice"><span class="choice-icon">${icon(["sun", "leaf", "garden", "pot"][index % 4], "icon-lg")}</span><input type="radio" name="region_code" value="${code}" ${answers.region_code === code ? "checked" : ""} required><b>${name}</b></label>`).join("")}</div>`
          : step === 5
            ? `<span class="eyebrow">Paso 5 · Tus plantas</span><h1>¿Qué tipo de plantas tienes?</h1><p class="hint">Selecciona todas las que tengas. Este paso es opcional.</p><div class="choices">${plantTypes.map(([code, name], index) => `<label class="choice"><span class="choice-icon">${icon(["leaf", "pot", "garden", "sun"][index % 4], "icon-lg")}</span><input type="checkbox" name="plant_code" value="${code}" ${answers.plant_codes.includes(code) ? "checked" : ""}><b>${name}</b></label>`).join("")}</div>`
            : `<span class="eyebrow">Paso 6 · Mascotas</span><h1>¿Tienes mascotas en casa?</h1><p class="hint">Esto nos permitirá señalar cuidados y plantas que requieren atención especial.</p><div class="choices"><label class="choice"><span class="choice-icon">${icon("heart", "icon-lg")}</span><input type="radio" name="has_pets" value="yes" ${answers.has_pets === true ? "checked" : ""} required><b>Sí, tengo mascotas</b><small>Consideraré recomendaciones de seguridad.</small></label><label class="choice"><span class="choice-icon">${icon("leaf", "icon-lg")}</span><input type="radio" name="has_pets" value="no" ${answers.has_pets === false ? "checked" : ""} required><b>No tengo mascotas</b><small>Podrás actualizarlo más adelante.</small></label></div>`;

  if (step === 5) content = renderPlantSelector(answers, plants || []);
  container.innerHTML = `<header class="topbar"><span class="logo"><img class="project-logo-image" src="front/images/plantia-logo.png" alt=""><span>PLANTIA<small>tu botánica de confianza</small></span></span><button class="secondary" data-logout>Salir</button></header><section class="center cold-wrap"><div class="steps">${[1, 2, 3, 4, 5, 6].map((number) => `<i class="step ${number <= step ? "on" : ""}"></i>`).join("")}</div><article class="card"><form data-onboarding>${content}<div data-form-message></div><div class="actions">${step > 1 ? '<button type="button" class="secondary" data-back>Regresar</button>' : "<span></span>"}<button class="primary">${step === 6 ? "Ver mis cursos" : `Continuar ${icon("arrow")}`}</button></div></form></article></section>`;
  container.querySelector("[data-logout]").onclick = logout;
  container.querySelector("[data-back]")?.addEventListener("click", back);
  const plantSearch = container.querySelector("[data-plant-search]");
  const form = container.querySelector("[data-onboarding]");
  const refreshSelectedPlants = () => {
    const selectedCodes = new Set([...form.querySelectorAll(".plant-selected-input")].map((input) => input.value));
    const target = form.querySelector("[data-selected-plants]");
    const catalog = (plants || []).length ? plants : plantTypes.map(([code, common_name]) => ({ code, common_name, scientific_name: "" }));
    const selectedPlants = catalog.filter(({ code }) => selectedCodes.has(code));
    target.querySelector(".selected-plants-title").textContent = selectedPlants.length ? "Tus plantas seleccionadas" : "Aún no has seleccionado plantas";
    target.querySelector(".selected-plants-list").innerHTML = selectedPlants.map((plant) => `<li><span>${escapeHtml(plant.common_name || plant.scientific_name)}</span><button type="button" data-remove-plant="${escapeHtml(plant.code)}" aria-label="Quitar ${escapeHtml(plant.common_name || plant.scientific_name)}">×</button></li>`).join("");
    form.querySelectorAll("[data-plant-option]").forEach((option) => { const active = selectedCodes.has(option.dataset.plantOption); option.classList.toggle("selected", active); option.querySelector("i").textContent = active ? "✓" : "+"; });
    target.querySelectorAll("[data-remove-plant]").forEach((button) => button.onclick = () => { form.querySelector(`.plant-selected-input[value="${button.dataset.removePlant}"]`)?.remove(); refreshSelectedPlants(); });
  };
  form.querySelectorAll("[data-plant-option]").forEach((button) => button.onclick = () => { const existing = form.querySelector(`.plant-selected-input[value="${button.dataset.plantOption}"]`); if (existing) existing.remove(); else form.querySelector("[data-selected-plants]").insertAdjacentHTML("afterbegin", `<input class="plant-selected-input" type="hidden" name="plant_code" value="${escapeHtml(button.dataset.plantOption)}">`); refreshSelectedPlants(); });
  plantSearch?.addEventListener("input", () => {
    const query = plantSearch.value.trim().toLowerCase();
    const list = form.querySelector("[data-plant-list]");
    const rows = [...form.querySelectorAll("[data-plant-option]")];
    if (!query) {
      rows.forEach((row, index) => { row.hidden = index >= 5; });
      list.hidden = false;
      return;
    }
    const score = (row) => {
      const common = row.dataset.common || "";
      const scientific = row.dataset.scientific || "";
      if (common === query || scientific === query) return 0;
      if (common.startsWith(query) || scientific.startsWith(query)) return 1;
      if (row.dataset.search.includes(` ${query}`)) return 2;
      return 3;
    };
    const matches = rows.filter((row) => row.dataset.search.includes(query)).sort((a, b) => score(a) - score(b) || a.dataset.search.localeCompare(b.dataset.search)).slice(0, 5);
    rows.forEach((row) => { row.hidden = !matches.includes(row); });
    list.hidden = matches.length === 0;
  });
  if (plantSearch) {
    refreshSelectedPlants();
    plantSearch.dispatchEvent(new Event("input"));
  }
  form.onsubmit = (event) => {
    event.preventDefault();
    submit(new FormData(event.currentTarget));
  };
}
