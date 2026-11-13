#!/usr/bin/env python3
"""Motor adaptativo híbrido de Plantia: reglas de negocio + Machine Learning.

PROPÓSITO
=========
Este archivo decide qué cursos mostrar primero a cada persona. Sigue el flujo:

    perfil + cursos + eventos -> reglas -> ML (si está entrenado) -> ranking explicable

Las reglas son la fuente de verdad: permiten aplicar seguridad, nivel, tiempo e
intereses de forma transparente. El modelo ML nunca excluye ni habilita un
curso; sólo añade hasta 30 puntos para ordenar cursos ya permitidos.

ENTRADAS PRINCIPALES
====================
* ``profile``: perfil de cold start (nivel, mascotas, conexión y ``tags``).
* ``courses``: catálogo; cada curso debe tener id, nivel, minutos y etiquetas.
* ``events``: acciones históricas, por ejemplo ``opened`` o ``enrolled``.
* ``rules``: reglas almacenadas en MySQL; si no llegan, se usan reglas mínimas.

SALIDA
======
Un documento JSON con el puntaje de reglas, probabilidad ML, razones y reglas
aplicadas para cada curso. PHP usa estas razones en la tarjeta “Para ti”.

USO
===
Demostración sin base de datos:
    python tools/adaptive_engine.py demo

Producción: recibe JSON desde PHP por archivo o por entrada estándar (``-``):
    python tools/adaptive_engine.py recommend --input entrada.json --output recomendaciones.json

Entrenamiento incremental con datos etiquetados (opened/enrolled=1, dismissed=0):
    python tools/adaptive_engine.py train --input entrenamiento.json --model storage/adaptive-model.json

Los archivos JSONL de eventos y decisiones son append-only: no se sobrescriben y
permiten auditar por qué se recomendó algo. El contrato completo también está
en README.md.
"""
from __future__ import annotations

import argparse
import json
import math
import random
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable


LEVELS = {"beginner": 0, "intermediate": 1, "advanced": 2}


def now() -> str:
    """Devuelve una fecha UTC ISO-8601 para que los registros sean comparables."""
    return datetime.now(timezone.utc).isoformat()


def append_jsonl(path: str | None, record: dict[str, Any]) -> None:
    """Añade un registro JSON a una bitácora sin modificar registros previos."""
    if not path:
        return
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    with target.open("a", encoding="utf-8") as stream:
        stream.write(json.dumps(record, ensure_ascii=False) + "\n")


def tags(item: dict[str, Any]) -> set[str]:
    """Normaliza las etiquetas de un perfil o curso a un conjunto consultable."""
    return set(item.get("tags", []))


def contains_tag(course: dict[str, Any], tag: str | None) -> bool:
    """Una acción sin etiqueta objetivo aplica a cualquier curso compatible."""
    return tag is None or tag in tags(course)


def course_matches(course: dict[str, Any], criteria: dict[str, Any]) -> bool:
    """Evalúa los criterios de ``action_data`` contra un curso.

    Devuelve ``True`` sólo cuando el curso puede recibir la acción de la regla.
    Aquí se concentran los filtros declarativos usados por las reglas de MySQL;
    agregar un nuevo criterio no obliga a cambiar la estructura de las reglas.
    """
    ctags = tags(course)
    if criteria.get("current_course") is False:
        return False
    if "course_must_have_tag" in criteria and criteria["course_must_have_tag"] not in ctags:
        return False
    if "course_level" in criteria and course.get("level") != criteria["course_level"]:
        return False
    minutes = int(course.get("estimated_minutes") or 0)
    if minutes and "max_estimated_minutes" in criteria and minutes > criteria["max_estimated_minutes"]:
        return False
    if minutes and "min_estimated_minutes" in criteria and minutes < criteria["min_estimated_minutes"]:
        return False
    if "duration_range_minutes" in criteria:
        low, high = criteria["duration_range_minutes"]
        if minutes and not low <= minutes <= high:
            return False
    if criteria.get("video_or_heavy_image_majority") and course.get("heavy_content") is not True:
        return False
    if criteria.get("course_matches_registered_plant") and not course.get("matches_registered_plant"):
        return False
    if criteria.get("course_matches_plant_family") and not course.get("matches_plant_family"):
        return False
    if criteria.get("course_matches_registered_plant_or_family") and not (course.get("matches_registered_plant") or course.get("matches_plant_family")):
        return False
    for plant_key in ("course_matches_plant_ecosystem", "course_matches_plant_family", "course_matches_dominant_ecosystem"):
        if criteria.get(plant_key) and not course.get(plant_key):
            return False
    if "relationship" in criteria and course.get("relationship") != criteria["relationship"]:
        return False
    if "relationship_in" in criteria and course.get("relationship") not in criteria["relationship_in"]:
        return False
    for course_key in ("higher_level_same_topic", "same_topic_as_abandoned_course", "course_matches_repeated_topic", "course_required_tag_missing"):
        if criteria.get(course_key) and not course.get(course_key):
            return False
    for behavior_key in ("similar_to_opened_course", "same_or_similar_to_dismissed_course", "complementary_to_enrolled_course", "course_matches_repeated_topic"):
        if criteria.get(behavior_key) and not course.get(behavior_key):
            return False
    return True


def fact(profile: dict[str, Any], events: list[dict[str, Any]], key: str, course: dict[str, Any] | None = None) -> Any:
    """Obtiene un hecho que puede ser consultado por una condición de regla."""
    if key == "user_tag":
        return tags(profile)
    if key == "event":
        return {e.get("type") for e in events}
    if key == "recommendation_event":
        return {e.get("type") for e in events if e.get("type") in {"opened", "dismissed", "enrolled"}}
    if course and key in course:
        return course[key]
    return profile.get(key)


def condition_matches(condition: dict[str, Any], profile: dict[str, Any], events: list[dict[str, Any]], course: dict[str, Any] | None = None) -> bool:
    """Interpreta una condición declarativa: equals, in, gte, gt o lt."""
    if condition["attribute"] == "profile_field":
        expected = condition.get("value", [])
        return len(expected) >= 2 and profile.get(expected[0]) == expected[1]
    actual = fact(profile, events, condition["attribute"], course)
    expected = condition.get("value", [])
    expected = expected if isinstance(expected, list) else [expected]
    op = condition.get("operator", "equals")
    if op in {"equals", "has_plant"}:
        if op == "has_plant":
            return bool(profile.get("plants"))
        return any(value in actual for value in expected) if isinstance(actual, (set, list, tuple)) else actual in expected
    if op == "gte":
        return actual is not None and float(actual) >= float(expected[0])
    if op == "gt":
        return actual is not None and float(actual) > float(expected[0])
    if op == "lt":
        return actual is not None and float(actual) < float(expected[0])
    if op == "in":
        return actual in expected
    return False


@dataclass
class ScoredCourse:
    """Estado temporal de un curso mientras el decisor calcula su recomendación."""
    course: dict[str, Any]
    rule_score: float = 0.0
    behavior_score: float = 0.0
    ml_probability: float | None = None
    score: float = 0.0
    excluded: bool = False
    reasons: list[str] = None  # type: ignore[assignment]
    applied_rules: list[str] = None  # type: ignore[assignment]

    def __post_init__(self) -> None:
        self.reasons = []
        self.applied_rules = []


class RuleEngine:
    """Aplica reglas por prioridad y deja evidencia de cada decisión.

    ``exclude_course`` retira un curso antes de ordenar. ``add_score`` y
    ``subtract_score`` modifican el puntaje de reglas. Las razones y códigos
    guardados aquí explican el resultado sin tener que inferirlo después.
    """
    def score(self, profile: dict[str, Any], courses: list[dict[str, Any]], events: list[dict[str, Any]], rules: list[dict[str, Any]]) -> list[ScoredCourse]:
        results = [ScoredCourse(course=c) for c in courses]
        for rule in sorted((r for r in rules if r.get("is_active", True)), key=lambda r: r.get("priority", 0), reverse=True):
            for result in results:
                if not all(condition_matches(condition, profile, events, result.course) for condition in rule.get("conditions", [])):
                    continue
                for action in rule.get("actions", []):
                    data = action.get("data", action.get("action_data", {})) or {}
                    if not course_matches(result.course, data):
                        continue
                    kind = action["action"]
                    if kind == "exclude_course":
                        result.excluded = True
                    elif kind in {"add_score", "subtract_score"} and contains_tag(result.course, action.get("target_tag")):
                        delta = float(action.get("score", 0))
                        result.rule_score += delta
                        result.reasons.append(action.get("message") or rule.get("name", rule.get("code", "Regla aplicada")))
                    elif kind == "show_warning":
                        result.reasons.append(action.get("message") or data.get("message_key", "Aviso de seguridad"))
                    result.applied_rules.append(rule.get("code", rule.get("name", "rule")))
        return results


class OnlineLogisticRegression:
    """Clasificador logístico pequeño, explicable y persistible sin librerías.

    Aprende pesos a partir de ejemplos ``label``: 1 significa interacción
    positiva y 0 falta de interés. Sus características incluyen etiquetas del
    perfil y curso, coincidencias, cercanía de nivel, duración y eventos.
    No sustituye reglas de seguridad: sólo ordena candidatos permitidos.
    """
    def __init__(self, weights: dict[str, float] | None = None, bias: float = 0.0, samples: int = 0):
        self.weights, self.bias, self.samples = weights or {}, bias, samples

    @staticmethod
    def features(profile: dict[str, Any], course: dict[str, Any], events: list[dict[str, Any]]) -> dict[str, float]:
        """Convierte perfil, curso e historial en valores numéricos para ML."""
        values: dict[str, float] = {"bias": 1.0, "minutes_short": float((course.get("estimated_minutes") or 0) <= 90)}
        for tag in tags(profile): values[f"user:{tag}"] = 1.0
        for tag in tags(course): values[f"course:{tag}"] = 1.0
        for tag in tags(profile) & tags(course): values[f"match:{tag}"] = 1.0
        user_level, course_level = LEVELS.get(profile.get("knowledge_level", "beginner"), 0), LEVELS.get(course.get("level", "beginner"), 0)
        values["level_distance"] = -abs(user_level - course_level)
        course_events = [e for e in events if e.get("course_id") == course.get("id")]
        values["previous_open"] = float(any(e.get("type") == "opened" for e in course_events))
        values["previous_dismiss"] = float(any(e.get("type") == "dismissed" for e in course_events))
        return values

    def probability(self, profile: dict[str, Any], course: dict[str, Any], events: list[dict[str, Any]]) -> float | None:
        """Calcula la afinidad prevista; sin ejemplos devuelve ``None`` (neutral)."""
        if not self.samples:
            return None
        x = self.features(profile, course, events)
        z = self.bias + sum(self.weights.get(name, 0.0) * value for name, value in x.items() if name != "bias")
        return 1.0 / (1.0 + math.exp(-max(-35, min(35, z))))

    def fit(self, rows: Iterable[dict[str, Any]], epochs: int = 30, learning_rate: float = 0.08, l2: float = 0.001) -> dict[str, float]:
        """Entrena por descenso de gradiente y devuelve una métrica orientativa.

        La exactitud se calcula sobre el mismo conjunto de entrenamiento, por
        lo que sirve para detectar fallos técnicos, no como evaluación final.
        """
        rows = list(rows)
        if not rows:
            raise ValueError("El conjunto de entrenamiento está vacío.")
        rng = random.Random(42)
        for _ in range(epochs):
            rng.shuffle(rows)
            for row in rows:
                x = self.features(row["profile"], row["course"], row.get("events", []))
                y = float(row["label"])
                p = self.probability(row["profile"], row["course"], row.get("events", [])) or 0.5
                error = y - p
                self.bias += learning_rate * error
                for name, value in x.items():
                    if name != "bias": self.weights[name] = self.weights.get(name, 0.0) + learning_rate * (error * value - l2 * self.weights.get(name, 0.0))
        self.samples += len(rows)
        correct = sum(((self.probability(r["profile"], r["course"], r.get("events", [])) or .5) >= .5) == bool(r["label"]) for r in rows)
        return {"samples": len(rows), "training_accuracy": round(correct / len(rows), 4)}

    def save(self, path: str) -> None:
        """Persiste pesos, sesgo y número de ejemplos en JSON versionado."""
        target = Path(path); target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(json.dumps({"version": 1, "weights": self.weights, "bias": self.bias, "samples": self.samples}, ensure_ascii=False, indent=2), encoding="utf-8")

    @classmethod
    def load(cls, path: str | None) -> "OnlineLogisticRegression":
        """Carga un modelo existente o devuelve uno neutral para cold start."""
        if not path or not Path(path).exists(): return cls()
        data = json.loads(Path(path).read_text(encoding="utf-8"))
        return cls(data.get("weights"), data.get("bias", 0.0), data.get("samples", 0))


def apply_behavior_signal(ranked: list[ScoredCourse], events: list[dict[str, Any]]) -> None:
    """Da un peso alto y explicable a las acciones recientes de la persona.

    Los eventos llegan de MySQL del más reciente al más antiguo. Abrir un curso
    es una señal de interés directo: el último curso abierto recibe 250 puntos
    y vuelve al primer lugar del catálogo, excepto si una regla lo excluye.
    Los cursos relacionados por etiquetas reciben un refuerzo menor. Así el
    catálogo cambia de forma perceptible después de cada clic, no sólo después
    de entrenar el modelo ML.
    """
    by_id = {item.course.get("id"): item for item in ranked}
    tag_map = {item.course.get("id"): tags(item.course) for item in ranked}
    opened_tags: set[str] = set()
    dismissed_tags: set[str] = set()
    latest_opened_id = next((event.get("course_id") for event in events if event.get("type") == "opened"), None)
    latest_enrolled_id = next((event.get("course_id") for event in events if event.get("type") == "enrolled"), None)

    for event in events:
        course_id, kind = event.get("course_id"), event.get("type")
        course = by_id.get(course_id)
        if kind == "opened":
            opened_tags.update(tag_map.get(course_id, set()))
            if course:
                points = 250 if course_id == latest_opened_id else 60
                course.behavior_score += points
                course.reasons.append("Lo abriste recientemente: prioridad alta." if points == 250 else "Ya mostraste interés en este curso.")
                course.applied_rules.append("behavior.recent-open")
        elif kind == "enrolled":
            if course:
                points = 280 if course_id == latest_enrolled_id else 80
                course.behavior_score += points
                course.reasons.append("Es parte de tu ruta de aprendizaje.")
                course.applied_rules.append("behavior.enrolled")
        elif kind == "dismissed":
            dismissed_tags.update(tag_map.get(course_id, set()))
            if course:
                course.behavior_score -= 250
                course.reasons.append("Marcaste este curso como no interesante.")
                course.applied_rules.append("behavior.dismissed")

    for course in ranked:
        course_id = course.course.get("id")
        if course_id not in {latest_opened_id, latest_enrolled_id}:
            related_open = len(tags(course.course) & opened_tags)
            if related_open:
                course.behavior_score += min(90, related_open * 30)
                course.reasons.append("Está relacionado con cursos que abriste.")
                course.applied_rules.append("behavior.related-interest")
        related_dismiss = len(tags(course.course) & dismissed_tags)
        if related_dismiss:
            course.behavior_score -= min(90, related_dismiss * 30)
            course.reasons.append("Se parece a un tema que descartaste.")
            course.applied_rules.append("behavior.related-dismiss")


def recommend(document: dict[str, Any], model: OnlineLogisticRegression, limit: int = 6) -> dict[str, Any]:
    """Genera recomendaciones explicables y ordenadas.

    La fórmula final es ``puntaje_reglas + (probabilidad_ml * 30)``. Si no hay
    entrenamiento, la segunda parte no existe y el sistema funciona únicamente
    con reglas; por eso el cold start no depende de Machine Learning.
    """
    profile, courses = document["profile"], document["courses"]
    events, rules = document.get("events", []), document.get("rules", default_rules())
    ranked = RuleEngine().score(profile, courses, events, rules)
    apply_behavior_signal(ranked, events)
    for item in ranked:
        item.ml_probability = model.probability(profile, item.course, events)
        # Reglas mandan; ML añade hasta 30 puntos sólo después de entrenarse.
        item.score = item.rule_score + item.behavior_score + (30 * item.ml_probability if item.ml_probability is not None else 0)
        if item.ml_probability is not None: item.reasons.append(f"Afinidad predicha por el modelo: {item.ml_probability:.0%}")
    visible = sorted((x for x in ranked if not x.excluded), key=lambda x: (-x.score, x.course.get("estimated_minutes", 999999), x.course.get("title", "")))[:limit]
    return {"generated_at": now(), "engine": {"strategy": "rules_plus_ml", "model_samples": model.samples}, "profile_id": profile.get("id"), "recommendations": [{"course_id": x.course.get("id"), "slug": x.course.get("slug"), "title": x.course.get("title"), "score": round(x.score, 2), "rule_score": round(x.rule_score, 2), "behavior_score": round(x.behavior_score, 2), "ml_probability": None if x.ml_probability is None else round(x.ml_probability, 4), "reasons": list(dict.fromkeys(x.reasons)), "applied_rules": list(dict.fromkeys(x.applied_rules))} for x in visible]}


def default_rules() -> list[dict[str, Any]]:
    """Reglas seguras de respaldo para ejecutar el script fuera de MySQL."""
    return [
        {"code": "safety.pets", "name": "Prioriza alternativas seguras para mascotas", "priority": 100, "conditions": [{"attribute": "has_pets", "operator": "equals", "value": [True, 1]}], "actions": [{"action": "add_score", "target_tag": "safety-pet-safe", "score": 25}, {"action": "show_warning", "data": {"message_key": "Revisa la seguridad de especies con mascotas"}}]},
        {"code": "level.beginner", "name": "Ajuste a nivel principiante", "priority": 80, "conditions": [{"attribute": "knowledge_level", "operator": "equals", "value": ["beginner"]}], "actions": [{"action": "add_score", "score": 25, "data": {"course_level": "beginner"}}, {"action": "subtract_score", "score": -20, "data": {"course_level": "advanced"}}]},
        {"code": "time.low", "name": "Prefiere contenido breve", "priority": 75, "conditions": [{"attribute": "user_tag", "operator": "equals", "value": ["time-low"]}], "actions": [{"action": "add_score", "score": 18, "data": {"max_estimated_minutes": 90}}, {"action": "subtract_score", "score": -20, "data": {"min_estimated_minutes": 240}}]},
        {"code": "interest.match", "name": "Coincidencia de intereses", "priority": 50, "conditions": [], "actions": []},
    ]


def demo_document() -> dict[str, Any]:
    """Datos pequeños y reproducibles usados por el comando ``demo``."""
    return {"profile": {"id": 7, "knowledge_level": "beginner", "has_pets": True, "tags": ["interest-indoor", "topic-watering", "time-low"]}, "events": [{"type": "opened", "course_id": 1}], "courses": [{"id": 1, "slug": "plantas-felices-en-casa", "title": "Plantas felices en casa", "level": "beginner", "estimated_minutes": 75, "tags": ["interest-indoor", "topic-watering", "safety-pet-safe"]}, {"id": 2, "slug": "propaga-tus-plantas", "title": "Propaga tus plantas", "level": "intermediate", "estimated_minutes": 180, "tags": ["topic-propagation"]}, {"id": 3, "slug": "botanica-desde-cero", "title": "Botánica desde cero", "level": "beginner", "estimated_minutes": 240, "tags": ["interest-basic-botany"]}]}


def read_json(path: str) -> Any:
    """Lee un JSON desde archivo o desde stdin cuando PHP indica ``-``."""
    if path == "-":
        return json.load(sys.stdin)
    return json.loads(Path(path).read_text(encoding="utf-8"))


def main() -> int:
    """Define la interfaz de línea de comandos y despacha cada operación."""
    parser = argparse.ArgumentParser(description="Motor adaptativo híbrido de Plantia")
    sub = parser.add_subparsers(dest="command", required=True)
    rec = sub.add_parser("recommend"); rec.add_argument("--input", required=True); rec.add_argument("--output"); rec.add_argument("--model"); rec.add_argument("--limit", type=int, default=6); rec.add_argument("--decisions-log")
    train = sub.add_parser("train"); train.add_argument("--input", required=True); train.add_argument("--model", required=True)
    demo = sub.add_parser("demo"); demo.add_argument("--model"); demo.add_argument("--decisions-log")
    event = sub.add_parser("record-event"); event.add_argument("--input", required=True); event.add_argument("--events-log", required=True)
    args = parser.parse_args()
    if args.command == "train":
        model = OnlineLogisticRegression.load(args.model); result = model.fit(read_json(args.input)); model.save(args.model); print(json.dumps(result, ensure_ascii=False, indent=2)); return 0
    if args.command == "record-event":
        record = read_json(args.input)
        if record.get("type") not in {"opened", "dismissed", "enrolled", "completed", "plant_registered", "attempt_submitted"}:
            raise ValueError("type debe ser opened, dismissed, enrolled, completed, plant_registered o attempt_submitted.")
        if "course_id" not in record and record["type"] not in {"plant_registered"}:
            raise ValueError("course_id es obligatorio para este evento.")
        record.setdefault("occurred_at", now())
        append_jsonl(args.events_log, record)
        print(json.dumps({"ok": True, "event": record}, ensure_ascii=False, indent=2))
        return 0
    document = demo_document() if args.command == "demo" else read_json(args.input)
    result = recommend(document, OnlineLogisticRegression.load(args.model), getattr(args, "limit", 6))
    append_jsonl(args.decisions_log, {"type": "recommendation_generated", **result})
    encoded = json.dumps(result, ensure_ascii=False, indent=2)
    if getattr(args, "output", None): Path(args.output).write_text(encoded + "\n", encoding="utf-8")
    print(encoded)
    return 0


if __name__ == "__main__":
    try: raise SystemExit(main())
    except (ValueError, KeyError, json.JSONDecodeError) as error:
        print(f"Error de entrada: {error}", file=sys.stderr); raise SystemExit(2)
