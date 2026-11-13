<?php
declare(strict_types=1);

/** Centraliza consultas, inscripciones y progreso del dominio de aprendizaje. */
final class LearningService
{
    public function __construct(private PDO $db, private ?array $user = null) {}

    /** Obtiene cursos publicados junto con el avance de la persona actual. */
    public function catalog(): array
    {
        $query = $this->db->prepare("SELECT c.id,c.slug,c.title,c.short_description,c.cover_image_url,c.estimated_minutes,c.level,cc.name category,COALESCE(uc.progress_percentage,0) progress,uc.status,uc.final_score,uc.abandonment_reason,uc.last_accessed_at FROM courses c JOIN course_categories cc ON cc.id=c.category_id LEFT JOIN user_courses uc ON uc.course_id=c.id AND uc.user_id=? WHERE c.status='published' ORDER BY CASE WHEN uc.id IS NULL THEN 1 ELSE 0 END,uc.last_accessed_at DESC,c.published_at DESC");
        $query->execute([$this->user['id'] ?? 0]);
        $courses=$query->fetchAll();
        return $this->user ? (new AdaptiveRecommendationService($this->db))->rank((int)$this->user['id'],$courses) : $courses;
    }

    /** Obtiene el detalle del curso y sus lecciones ordenadas. */
    public function course(string $slug): ?array
    {
        $query = $this->db->prepare("SELECT c.*,cc.name category,COALESCE(uc.progress_percentage,0) progress,uc.status FROM courses c JOIN course_categories cc ON cc.id=c.category_id LEFT JOIN user_courses uc ON uc.course_id=c.id AND uc.user_id=? WHERE c.slug=? AND c.status='published'");
        $query->execute([$this->user['id'] ?? 0, $slug]);
        $course = $query->fetch();
        if (!$course) return null;
        if ($this->user) (new AdaptiveRecommendationService($this->db))->record((int)$this->user['id'],'opened',(int)$course['id']);
        $this->markCourseAccessed((int) $course['id']);

        foreach (['course_learning_outcomes'=>'outcomes','course_prerequisites'=>'prerequisites'] as $table=>$field) {
            $items=$this->db->prepare("SELECT body FROM {$table} WHERE course_id=? ORDER BY position");
            $items->execute([$course['id']]);
            $course[$field]=$items->fetchAll(PDO::FETCH_COLUMN);
        }
        $modules=$this->db->prepare('SELECT m.id,m.title,m.description,m.position,m.estimated_minutes,l.id lesson_id,l.title lesson_title,l.description lesson_description,l.position lesson_position,l.estimated_minutes lesson_minutes,COALESCE(ulp.is_completed,0) completed FROM course_modules m JOIN lessons l ON l.module_id=m.id LEFT JOIN user_courses uc ON uc.course_id=m.course_id AND uc.user_id=? LEFT JOIN user_lesson_progress ulp ON ulp.user_course_id=uc.id AND ulp.lesson_id=l.id WHERE m.course_id=? ORDER BY m.position,l.position');
        $modules->execute([$this->user['id'] ?? 0, $course['id']]);
        $course['modules']=$modules->fetchAll();
        return $course;
    }

    /** Obtiene una lección y oculta las respuestas correctas de sus cuestionarios. */
    public function lesson(int $lessonId): ?array
    {
        $query=$this->db->prepare("SELECT l.*,m.course_id,c.title course_title,c.slug FROM lessons l JOIN course_modules m ON m.id=l.module_id JOIN courses c ON c.id=m.course_id WHERE l.id=? AND c.status='published'");
        $query->execute([$lessonId]);
        $lesson=$query->fetch();
        if (!$lesson) return null;
        $this->markCourseAccessed((int) $lesson['course_id']);
        if (empty($lesson['is_preview'])) {
            $this->enrollmentId((int)$lesson['course_id']);
        }
        $blocks=$this->db->prepare('SELECT id,type,title,body,content_data,position,is_required FROM lesson_blocks WHERE lesson_id=? ORDER BY position');
        $blocks->execute([$lessonId]);
        $lesson['blocks']=$blocks->fetchAll();
        foreach ($lesson['blocks'] as &$block) {
            $data=json_decode($block['content_data'] ?? '{}', true) ?: [];
            unset($data['correct_option']);
            $block['content_data']=$data;
        }
        return $lesson;
    }

    /** Recupera y clasifica las referencias visibles para la interfaz. */
    public function references(): array
    {
        $query=$this->db->query("SELECT c.title course_title,r.title,r.url,r.source_type,r.notes,CASE WHEN r.source_type='image' OR r.url LIKE 'front/images/%' OR r.title LIKE 'Imagen generada con IA:%' THEN 'images' WHEN r.source_type='ai' THEN 'platform' ELSE 'courses' END reference_group FROM content_references r JOIN courses c ON c.id=r.course_id WHERE c.status='published' ORDER BY reference_group,c.title,r.position");
        return $query->fetchAll();
    }

    /** Inscribe a la persona actual en un curso publicado. */
    public function enroll(int $courseId): void
    {
        $check=$this->db->prepare("SELECT id FROM courses WHERE id=? AND status='published'");
        $check->execute([$courseId]);
        if (!$check->fetchColumn()) throw new LearningServiceException('Curso no disponible.',404);
        $query=$this->db->prepare("INSERT INTO user_courses(user_id,course_id,status,started_at,last_accessed_at,created_at,updated_at) VALUES(?,?,'in_progress',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status=IF(status='not_started','in_progress',status),last_accessed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP");
        $query->execute([$this->user['id'],$courseId]);
        (new AdaptiveRecommendationService($this->db))->record((int)$this->user['id'],'enrolled',$courseId);
    }

    /** Guarda un intento de actividad y calcula el resultado de un cuestionario. */
    public function submitAttempt(int $blockId, mixed $answer): array
    {
        $query=$this->db->prepare('SELECT b.*,m.course_id FROM lesson_blocks b JOIN lessons l ON l.id=b.lesson_id JOIN course_modules m ON m.id=l.module_id WHERE b.id=?');
        $query->execute([$blockId]);
        $block=$query->fetch();
        if (!$block || !in_array($block['type'],['quiz','practice'],true)) throw new LearningServiceException('Actividad no válida.',422);
        $enrollment=$this->enrollmentId((int)$block['course_id']);
        $content=json_decode($block['content_data'] ?? '{}',true) ?: [];
        $practice=$block['type']==='practice';
        if ($practice && (!is_string($answer) || trim($answer)==='')) throw new LearningServiceException('Escribe tu observación antes de enviarla.',422);
        if (!$practice && (!is_int($answer) && !ctype_digit((string)$answer))) throw new LearningServiceException('Selecciona una respuesta válida.',422);
        $passed=!$practice && (int)$answer===(int)($content['correct_option'] ?? -1);
        $attemptQuery=$this->db->prepare('SELECT COALESCE(MAX(attempt_number),0)+1 FROM user_block_attempts WHERE user_course_id=? AND lesson_block_id=?');
        $attemptQuery->execute([$enrollment,$blockId]);
        $attempt=(int)$attemptQuery->fetchColumn();
        $feedback=$practice?'Tu actividad quedó guardada y pendiente de revisión.':($content['feedback'] ?? '');
        $save=$this->db->prepare('INSERT INTO user_block_attempts(user_course_id,lesson_block_id,attempt_number,answer_data,score,is_passed,feedback,submitted_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $save->execute([$enrollment,$blockId,$attempt,json_encode(['answer'=>$answer],JSON_UNESCAPED_UNICODE),$practice?null:($passed?100:0),$practice?null:$passed,$feedback]);
        (new AdaptiveRecommendationService($this->db))->record((int)$this->user['id'],'attempt_submitted',(int)$block['course_id'],['score'=>$practice ? null : ($passed ? 100 : 0)]);
        return ['ok'=>true,'pending_review'=>$practice,'passed'=>$practice?null:$passed,'score'=>$practice?null:($passed?100:0),'feedback'=>$feedback];
    }

    /** Marca una lección como terminada y recalcula el porcentaje del curso. */
    public function completeLesson(int $lessonId): array
    {
        $lookup=$this->db->prepare('SELECT m.course_id FROM lessons l JOIN course_modules m ON m.id=l.module_id WHERE l.id=?');
        $lookup->execute([$lessonId]);
        $courseId=(int)$lookup->fetchColumn();
        if (!$courseId) throw new LearningServiceException('Lección no encontrada.',404);
        $enrollment=$this->enrollmentId($courseId);
        $this->db->beginTransaction();
        try {
            $save=$this->db->prepare('INSERT INTO user_lesson_progress(user_course_id,lesson_id,progress_percentage,is_completed,started_at,completed_at,updated_at) VALUES(?,?,100,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE progress_percentage=100,is_completed=1,completed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP');
            $save->execute([$enrollment,$lessonId]);
            $count=$this->db->prepare('SELECT COUNT(*) FROM lessons l JOIN course_modules m ON m.id=l.module_id WHERE m.course_id=? AND l.is_required=1');
            $count->execute([$courseId]);
            $total=(int)$count->fetchColumn();
            $doneQuery=$this->db->prepare('SELECT COUNT(*) FROM user_lesson_progress p JOIN lessons l ON l.id=p.lesson_id JOIN course_modules m ON m.id=l.module_id WHERE p.user_course_id=? AND m.course_id=? AND p.is_completed=1 AND l.is_required=1');
            $doneQuery->execute([$enrollment,$courseId]);
            $progress=$total?round((int)$doneQuery->fetchColumn()*100/$total,2):0;
            $scoreQuery=$this->db->prepare('SELECT AVG(score) FROM user_block_attempts WHERE user_course_id=? AND score IS NOT NULL');
            $scoreQuery->execute([$enrollment]);
            $finalScore=$scoreQuery->fetchColumn();
            $update=$this->db->prepare("UPDATE user_courses SET progress_percentage=?,status=?,final_score=?,completed_at=IF(?=100,CURRENT_TIMESTAMP,NULL),last_accessed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $update->execute([$progress,$progress===100?'completed':'in_progress',$finalScore === null ? null : round((float)$finalScore,2),$progress,$enrollment]);
            $this->db->commit();
            if ($progress===100) (new AdaptiveRecommendationService($this->db))->record((int)$this->user['id'],'completed',$courseId,['final_score'=>$finalScore === null ? null : round((float)$finalScore,2)]);
            return ['ok'=>true,'progress'=>$progress];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function enrollmentId(int $courseId): int
    {
        $query=$this->db->prepare('SELECT id FROM user_courses WHERE user_id=? AND course_id=?');
        $query->execute([$this->user['id'],$courseId]);
        $id=(int)$query->fetchColumn();
        if (!$id) throw new LearningServiceException('Inscríbete en el curso antes de continuar.',403);
        return $id;
    }

    private function markCourseAccessed(int $courseId): void
    {
        if (!$this->user) return;
        $query = $this->db->prepare('UPDATE user_courses SET last_accessed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND course_id=?');
        $query->execute([(int) $this->user['id'], $courseId]);
    }
}

final class LearningServiceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422) { parent::__construct($message); }
}
