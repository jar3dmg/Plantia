<?php
declare(strict_types=1);

// Punto de entrada HTTP: enruta cada acción y siempre devuelve JSON.
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/back/bootstrap.php';
require __DIR__ . '/back/http.php';
require __DIR__ . '/back/users.php';
require __DIR__ . '/back/profiles.php';
require __DIR__ . '/back/services/LearningService.php';
require __DIR__ . '/back/services/AdaptiveRecommendationService.php';

// Se crean bajo demanda para mantener el controlador independiente de rutas.
$database = database();
$action = $_GET['action'] ?? '';
$data = requestBody();
$user = null;
$learning = null;

try {
    if ($action === 'status') {
        $user = currentUser($database);
        respond(['user' => $user, 'profile' => $user ? userProfile($database, (int) $user['id']) : null]);
    }
    $user = currentUser($database);
    $learning = new LearningService($database, $user);
    if ($action === 'courses') { respond(['courses' => $learning->catalog()]); }
    if ($action === 'plants') {
        $plants = $database->query("SELECT code,common_name,scientific_name,ecosystem FROM plant_taxa WHERE taxon_type='species' AND is_active=1 ORDER BY COALESCE(common_name,scientific_name),scientific_name")->fetchAll(PDO::FETCH_ASSOC);
        respond(['plants' => $plants]);
    }

    if ($action === 'register') {
        registerUser($database, $data);
    }

    if ($action === 'login') {
        loginUser($database, $data);
    }

    if ($action === 'logout') {
        logoutUser($database);
    }

    if (!$user) {
        respond(['error' => 'Inicia sesión para continuar.'], 401);
    }
    if ($action === 'course') { $course=$learning->course((string)($_GET['slug']??'')); respond($course?:['error'=>'Curso no encontrado.'],$course?200:404); }
    if ($action === 'references') { respond(['references'=>$learning->references()]); }
    if ($action === 'lesson') { $lesson=$learning->lesson((int)($_GET['id']??0)); respond($lesson?:['error'=>'Lección no encontrada.'],$lesson?200:404); }
    if ($action === 'enroll') { $learning->enroll((int)($data['course_id']??0)); respond(['ok'=>true]); }
    if ($action === 'adaptive_event') {
        $type=(string)($data['type'] ?? '');
        if (!in_array($type,['dismissed'],true) || !(int)($data['course_id'] ?? 0)) respond(['error'=>'Evento adaptativo no válido.'],422);
        (new AdaptiveRecommendationService($database))->record((int)$user['id'],$type,(int)$data['course_id']);
        respond(['ok'=>true]);
    }
    if ($action === 'submit_attempt') { respond($learning->submitAttempt((int)($data['block_id']??0),$data['answer']??null)); }
    if ($action === 'complete_lesson') { respond($learning->completeLesson((int)($data['lesson_id']??0))); }

    if ($action === 'save_profile') {
        saveProfile($database, $user, $data);
    }

    if ($action === 'save_preferences') {
        saveInterfacePreferences($database, $user, $data);
    }

    if ($action === 'save_learning_preferences') {
        saveLearningPreferences($database, $user, $data);
    }

    respond(['error' => 'Acción no encontrada.'], 404);
} catch (LearningServiceException $exception) {
    respond(['error'=>$exception->getMessage()],$exception->httpStatus);
} catch (PDOException $exception) {
    $message = str_contains($exception->getMessage(), 'UNIQUE')
        ? 'Ya existe una cuenta con ese correo.'
        : 'No fue posible guardar la información.';

    respond(['error' => $message], 422);
}
