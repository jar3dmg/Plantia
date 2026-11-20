<?php
declare(strict_types=1);

/** Recupera el perfil y reconstruye sus intereses a partir de etiquetas. */
function userProfile(PDO $db, int $userId): ?array
{
    $profileQuery = $db->prepare('SELECT region_id, knowledge_level, has_pets, weak_connection, theme, font_size, reading_mode, font_mode FROM user_profiles WHERE user_id = ?');
    $profileQuery->execute([$userId]);
    $profile = $profileQuery->fetch(PDO::FETCH_ASSOC);
    if ($profile === false) {
        return null;
    }

    $tags = $db->prepare('SELECT t.code, t.name FROM user_tags ut INNER JOIN tags t ON t.id = ut.tag_id WHERE ut.user_id = ?');
    $tags->execute([$userId]);
    $values = $tags->fetchAll(PDO::FETCH_ASSOC);
    $profile['has_garden'] = (int) (bool) array_filter($values, static fn (array $tag): bool => $tag['code'] === 'context-garden');
    $profile['has_pots'] = (int) (bool) array_filter($values, static fn (array $tag): bool => $tag['code'] === 'context-pot');
    $profile['garden_plants'] = [];
    $profile['pot_plants'] = [];
    $plants = $db->prepare('SELECT pt.code FROM user_plants up INNER JOIN plant_taxa pt ON pt.id = up.plant_taxon_id WHERE up.user_id = ?');
    $plants->execute([$userId]);
    $profile['plant_codes'] = $plants->fetchAll(PDO::FETCH_COLUMN);
    $profile['interests'] = array_values(array_map(static fn (array $tag): string => $tag['name'], array_filter($values, static fn (array $tag): bool => str_starts_with($tag['code'], 'interest-'))));
    $goals = array_values(array_filter($values, static fn (array $tag): bool => str_starts_with($tag['code'], 'goal-')));
    $profile['goal'] = $goals[0]['name'] ?? '';

    return $profile;
}

/** Valida y guarda las preferencias visuales y de accesibilidad. */
function saveInterfacePreferences(PDO $db, array $user, array $data): void
{
    $theme = $data['theme'] ?? 'light';
    $fontSize = $data['font_size'] ?? 'normal';
    $readingMode = !empty($data['reading_mode']) ? 1 : 0;
    $fontMode = $data['font_mode'] ?? 'standard';
    $level = $data['knowledge_level'] ?? 'beginner';

    if (!in_array($theme, ['light', 'dark'], true) || !in_array($fontSize, ['small', 'normal', 'large'], true) || !in_array($fontMode, ['standard', 'dyslexia'], true) || !in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
        respond(['error' => 'La preferencia seleccionada no es válida.'], 422);
    }

    $query = $db->prepare('UPDATE user_profiles SET theme = ?, font_size = ?, reading_mode = ?, font_mode = ?, knowledge_level = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
    $query->execute([$theme, $fontSize, $readingMode, $fontMode, $level, (int) $user['id']]);
    (new AdaptiveRecommendationService($db))->record((int) $user['id'], 'preferences_updated', null, ['knowledge_level' => $level]);
    respond(['ok' => true]);
}

/** Persiste las respuestas del cuestionario inicial como etiquetas de usuario. */
function saveProfile(PDO $db, array $user, array $data): void
{
    $interests = array_values(array_filter($data['interests'] ?? []));
    $goal = trim($data['goal'] ?? '');
    $regionCode = trim($data['region_code'] ?? '');
    $hasPets = !empty($data['has_pets']) ? 1 : 0;
    $plantCodes = array_values(array_unique(array_filter($data['plant_codes'] ?? [], 'is_string')));

    if (!$interests || $goal === '' || $regionCode === '') {
        respond(['error' => 'Elige al menos un interés, un objetivo y una región.'], 422);
    }

    $userId = (int) $user['id'];
    $region = $db->prepare('SELECT id FROM regions WHERE code = ? AND is_active = 1');
    $region->execute([$regionCode]);
    $regionId = (int) $region->fetchColumn();
    if (!$regionId) {
        respond(['error' => 'La región seleccionada no es válida.'], 422);
    }
    $validPlants = $db->query("SELECT code, id FROM plant_taxa WHERE taxon_type = 'species' AND is_active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($plantCodes as $code) {
        if (!isset($validPlants[$code])) {
            respond(['error' => 'Una de las plantas seleccionadas no es válida.'], 422);
        }
    }
    $profile = $db->prepare('INSERT INTO user_profiles(user_id, region_id, has_pets, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE region_id = VALUES(region_id), has_pets = VALUES(has_pets), updated_at = CURRENT_TIMESTAMP');
    $profile->execute([$userId, $regionId, $hasPets]);

    $db->prepare("DELETE FROM user_tags WHERE user_id = ? AND tag_id IN (SELECT id FROM tags WHERE code LIKE 'interest-%' OR code LIKE 'goal-%' OR code IN ('context-garden', 'context-pot'))")->execute([$userId]);
    $codes = [];
    foreach ($interests as $interest) {
        $codes[] = match ($interest) { 'Botánica básica' => 'interest-basic-botany', 'Huertos y frutales' => 'interest-orchard', 'Cuidado de plantas' => 'interest-care', 'Paisajismo' => 'interest-landscaping', 'Plantas de interior' => 'interest-indoor', 'Botánica y salud' => 'interest-health', default => '' };
    }
    $codes[] = match ($goal) { 'Cuidar mis plantas' => 'goal-care', 'Aprender por diversión' => 'goal-learn', 'Crear un huerto' => 'goal-orchard', 'Desarrollo profesional' => 'goal-professional', default => '' };
    if (!empty($data['has_garden'])) { $codes[] = 'context-garden'; }
    if (!empty($data['has_pots'])) { $codes[] = 'context-pot'; }
    $tag = $db->prepare('INSERT INTO user_tags(user_id, tag_id, created_at) SELECT ?, id, CURRENT_TIMESTAMP FROM tags WHERE code = ?');
    foreach (array_filter($codes) as $code) { $tag->execute([$userId, $code]); }
    $existingPlant = $db->prepare('SELECT id FROM user_plants WHERE user_id = ? AND plant_taxon_id = ?');
    $addPlant = $db->prepare('INSERT INTO user_plants(user_id, plant_taxon_id, quantity, created_at) VALUES (?, ?, 1, CURRENT_TIMESTAMP)');
    foreach ($plantCodes as $code) {
        $plantId = (int) $validPlants[$code];
        $existingPlant->execute([$userId, $plantId]);
        if (!$existingPlant->fetchColumn()) {
            $addPlant->execute([$userId, $plantId]);
        }
    }
    (new AdaptiveRecommendationService($db))->record($userId, 'profile_updated', null, ['interests' => $codes, 'goal' => $goal]);
    respond(['ok' => true]);
}
