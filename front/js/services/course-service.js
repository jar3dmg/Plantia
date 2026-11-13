import { request } from "./api-client.js";

// Adaptadores del dominio de aprendizaje. Mantienen las vistas sin detalles HTTP.

export const listCourses = () => request("courses");
export const listReferences = () => request("references");
export const getCourse = (slug) => request("course", { query: { slug } });
export const getLesson = (id) => request("lesson", { query: { id } });
export const enrollInCourse = (courseId) =>
  request("enroll", { payload: { course_id: courseId } });
export const recordAdaptiveEvent = (courseId, type) =>
  request("adaptive_event", { payload: { course_id: courseId, type } });
export const submitActivityAttempt = (blockId, answer) =>
  request("submit_attempt", { payload: { block_id: blockId, answer } });
export const completeLesson = (lessonId) =>
  request("complete_lesson", { payload: { lesson_id: lessonId } });
