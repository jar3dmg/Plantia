<?php
declare(strict_types=1);

require __DIR__ . '/../back/bootstrap.php';
require __DIR__ . '/seeders/ComprehensivePlantCatalogSeeder.php';

$result = ComprehensivePlantCatalogSeeder::run(database());
echo "Catálogo botánico completo actualizado.\n";
echo "Familias: {$result['families']}; especies: {$result['species']}; relaciones curso-planta: {$result['relations']}.\n";
