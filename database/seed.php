<?php
declare(strict_types=1);

require __DIR__ . '/../back/bootstrap.php';
require __DIR__ . '/seeders/CatalogSeeder.php';
require __DIR__ . '/seeders/ExtendedCatalogSeeder.php';
require __DIR__ . '/seeders/RecommendationRulesSeeder.php';
require __DIR__ . '/seeders/LearningCoursesSeeder.php';
require __DIR__ . '/seeders/ComprehensivePlantCatalogSeeder.php';
require __DIR__ . '/seeders/ExpandedLearningCoursesSeeder.php';

$db = database();
CatalogSeeder::run($db);
$result = ExtendedCatalogSeeder::run($db);
$rules = RecommendationRulesSeeder::run($db);
$courses = LearningCoursesSeeder::run($db);
$plants = ComprehensivePlantCatalogSeeder::run($db);
$expandedCourses = ExpandedLearningCoursesSeeder::run($db);
echo "Seeders ejecutados correctamente.\n";
echo "Cursos de aprendizaje: {$courses}.\n";
echo "Catálogo botánico: {$plants['families']} familias, {$plants['species']} especies y {$plants['relations']} relaciones de curso.\n";
echo "Cursos ampliados: {$expandedCourses}.\n";
echo "Categorías: {$result['categories']}; regiones: {$result['regions']}; etiquetas: {$result['tags']}; taxones: {$result['plant_taxa']}; reglas: {$rules}.\n";
