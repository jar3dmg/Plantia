<?php
declare(strict_types=1);

/**
 * Catálogos iniciales de Plantia.
 *
 * El seeder es idempotente: usa los códigos funcionales como llave natural,
 * por lo que puede ejecutarse de nuevo para corregir o completar el catálogo.
 */
final class CatalogSeeder
{
    public static function run(PDO $db): array
    {
        $now = date('Y-m-d H:i:s');

        self::seedCategories($db);
        self::seedRegions($db);
        $tagIds = self::seedTags($db);
        self::seedPlantTaxa($db);

        return [
            'categories' => (int) $db->query('SELECT COUNT(*) FROM course_categories')->fetchColumn(),
            'regions' => (int) $db->query('SELECT COUNT(*) FROM regions')->fetchColumn(),
            'tag_groups' => (int) $db->query('SELECT COUNT(*) FROM tag_groups')->fetchColumn(),
            'tags' => count($tagIds),
            'plant_taxa' => (int) $db->query('SELECT COUNT(*) FROM plant_taxa')->fetchColumn(),
            'seeded_at' => $now,
        ];
    }

    private static function seedCategories(PDO $db): void
    {
        $categories = [
            ['fundamentals', 'Fundamentos botánicos', 'Conceptos esenciales para comprender las plantas.'],
            ['indoor-plants', 'Plantas de interior', 'Cuidados domésticos, iluminación y decoración vegetal.'],
            ['orchards-food', 'Huertos y alimentos', 'Hortalizas, aromáticas, frutales y compostaje.'],
            ['urban-gardening', 'Jardinería urbana', 'Cultivo en balcones, terrazas y espacios reducidos.'],
            ['substrates-containers', 'Sustratos, macetas y trasplantes', 'Contenedores, mezclas y establecimiento de plantas.'],
            ['watering-nutrition', 'Riego y nutrición', 'Riego, fertilización y salud del suelo.'],
            ['propagation', 'Propagación', 'Esquejes, división, semillas, acodos e injertos.'],
            ['pests-diseases', 'Plagas y enfermedades', 'Prevención, diagnóstico y control responsable.'],
            ['landscape-design', 'Paisajismo y diseño', 'Diseño, distribución y selección de especies.'],
            ['climate-adaptation', 'Clima y adaptación', 'Manejo de sol, frío, humedad, sequía y microclimas.'],
            ['safety-wellbeing', 'Seguridad y bienestar vegetal', 'Convivencia segura con mascotas, niños y plantas.'],
        ];
        $statement = $db->prepare(
            'INSERT INTO course_categories (parent_id, code, name, description, is_active) VALUES (NULL, ?, ?, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_active = VALUES(is_active)'
        );
        foreach ($categories as [$code, $name, $description]) {
            $statement->execute([$code, $name, $description]);
        }
    }

    private static function seedRegions(PDO $db): void
    {
        $regions = [
            ['warm-dry', 'Cálida seca', 'Clima caluroso con lluvias limitadas; prioriza ahorro de agua y especies tolerantes a sequía.'],
            ['warm-humid', 'Cálida húmeda', 'Clima caluroso con humedad alta; requiere drenaje y prevención de hongos.'],
            ['temperate-subhumid', 'Templada subhúmeda', 'Temperaturas moderadas con temporada de lluvias.'],
            ['temperate-dry', 'Templada seca', 'Temperaturas moderadas, baja humedad y cambios estacionales marcados.'],
            ['cold-highland', 'Fría o de montaña', 'Temperaturas bajas o riesgo de heladas; necesita protección climática.'],
            ['semiarid', 'Semiárida', 'Precipitación escasa y alta evaporación; favorece riego eficiente.'],
            ['urban-microclimate', 'Urbana con microclima', 'Condiciones modificadas por construcciones, sombra, calor y contenedores.'],
        ];
        $statement = $db->prepare(
            'INSERT INTO regions (code, name, description, is_active) VALUES (?, ?, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_active = VALUES(is_active)'
        );
        foreach ($regions as $region) {
            $statement->execute($region);
        }
    }

    /** @return array<string, int> */
    private static function seedTags(PDO $db): array
    {
        $groups = [
            ['interest', 'Intereses', 'Temas que interesan al usuario.'],
            ['goal', 'Objetivos', 'Resultados que el usuario quiere lograr.'],
            ['context', 'Espacio y cultivo', 'Espacio disponible y forma de cultivo.'],
            ['light', 'Luz', 'Disponibilidad e intensidad de luz.'],
            ['environment', 'Ambiente', 'Humedad, temperatura y condiciones ambientales.'],
            ['time', 'Tiempo', 'Tiempo disponible para aprender y cuidar plantas.'],
            ['ecosystem', 'Ecosistema', 'Ambiente de cultivo o de la planta.'],
            ['topic', 'Temas técnicos', 'Competencias y temas de aprendizaje.'],
            ['content', 'Formato de contenido', 'Preferencias y condiciones de acceso al contenido.'],
            ['safety', 'Seguridad', 'Condiciones de seguridad para plantas, mascotas y niños.'],
        ];
        $groupStatement = $db->prepare(
            'INSERT INTO tag_groups (code, name, description) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
        );
        foreach ($groups as $group) {
            $groupStatement->execute($group);
        }
        $groupIds = $db->query('SELECT code, id FROM tag_groups')->fetchAll(PDO::FETCH_KEY_PAIR);

        $tags = [
            ['interest', 'interest-basic-botany', 'Botánica básica'], ['interest', 'interest-indoor', 'Plantas de interior'],
            ['interest', 'interest-orchard', 'Huertos y frutales'], ['interest', 'interest-care', 'Cuidado de plantas'],
            ['interest', 'interest-landscaping', 'Paisajismo'], ['interest', 'interest-health', 'Botánica y salud'],
            ['interest', 'interest-succulents-cacti', 'Suculentas y cactus'], ['interest', 'interest-orchids', 'Orquídeas'],
            ['interest', 'interest-edibles', 'Plantas comestibles'], ['interest', 'interest-propagation', 'Propagación'],
            ['interest', 'interest-pest-control', 'Plagas y enfermedades'], ['interest', 'interest-composting', 'Compostaje'],
            ['goal', 'goal-learn-basics', 'Aprender lo básico'], ['goal', 'goal-care', 'Mantener mis plantas sanas'],
            ['goal', 'goal-propagate', 'Reproducir plantas'], ['goal', 'goal-design-garden', 'Diseñar un jardín'],
            ['goal', 'goal-grow-food', 'Cultivar alimentos'], ['goal', 'goal-control-pests', 'Controlar plagas'],
            ['goal', 'goal-learn', 'Aprender por diversión'], ['goal', 'goal-professional', 'Desarrollo profesional'],
            ['context', 'context-pot', 'Maceta'], ['context', 'context-garden', 'Jardín'],
            ['context', 'context-balcony', 'Balcón o terraza'], ['context', 'context-small-outdoor', 'Espacio exterior pequeño'],
            ['context', 'context-small-indoor', 'Poco espacio interior'], ['context', 'context-large-indoor', 'Mucho espacio interior'],
            ['context', 'context-vertical', 'Cultivo vertical'],
            ['light', 'light-low', 'Poca luz'], ['light', 'light-indirect', 'Luz indirecta'],
            ['light', 'light-sun', 'Mucha luz solar'], ['light', 'light-afternoon-sun', 'Sol directo de tarde'],
            ['environment', 'environment-humid', 'Ambiente húmedo'], ['environment', 'environment-dry', 'Ambiente seco'],
            ['environment', 'environment-temperature-swings', 'Cambios fuertes de temperatura'],
            ['time', 'time-low', 'Poco tiempo'], ['time', 'time-medium', 'Tiempo moderado'], ['time', 'time-high', 'Mucho tiempo'],
            ['ecosystem', 'ecosystem-indoor', 'Interior'], ['ecosystem', 'ecosystem-outdoor', 'Exterior'],
            ['ecosystem', 'ecosystem-shade', 'Sombra'], ['ecosystem', 'ecosystem-semi-shade', 'Semisombra'],
            ['ecosystem', 'ecosystem-sun', 'Sol'], ['ecosystem', 'ecosystem-dry', 'Seco'],
            ['ecosystem', 'ecosystem-humid', 'Húmedo'], ['ecosystem', 'ecosystem-tropical', 'Tropical'],
            ['topic', 'topic-watering', 'Riego'], ['topic', 'topic-substrates', 'Sustratos'],
            ['topic', 'topic-transplanting', 'Trasplante'], ['topic', 'topic-fertilization', 'Fertilización'],
            ['topic', 'topic-propagation', 'Propagación'], ['topic', 'topic-pruning', 'Poda'],
            ['topic', 'topic-pests', 'Plagas'], ['topic', 'topic-diseases', 'Enfermedades'],
            ['topic', 'topic-diagnosis', 'Diagnóstico básico'], ['topic', 'topic-composting', 'Compostaje'],
            ['topic', 'topic-drainage', 'Drenaje'], ['topic', 'topic-climate-protection', 'Protección climática'],
            ['topic', 'topic-garden-design', 'Diseño de jardín'],
            ['content', 'content-text', 'Contenido textual'], ['content', 'content-video', 'Contenido en video'],
            ['content', 'content-guided-practice', 'Práctica guiada'], ['content', 'content-low-bandwidth', 'Bajo consumo de datos'],
            ['content', 'content-short-modules', 'Módulos breves'],
            ['safety', 'safety-has-pets', 'Tiene mascotas'], ['safety', 'safety-has-children', 'Tiene niños'],
            ['safety', 'safety-pet-safe', 'Plantas seguras para mascotas'], ['safety', 'safety-toxic-plant', 'Planta potencialmente tóxica'],
        ];
        $tagStatement = $db->prepare(
            'INSERT INTO tags (tag_group_id, code, name, description, sort_order, is_active) VALUES (?, ?, ?, NULL, ?, 1) '
            . 'ON DUPLICATE KEY UPDATE tag_group_id = VALUES(tag_group_id), name = VALUES(name), sort_order = VALUES(sort_order), is_active = VALUES(is_active)'
        );
        foreach ($tags as $position => [$group, $code, $name]) {
            $tagStatement->execute([$groupIds[$group], $code, $name, $position]);
        }

        return $db->query('SELECT code, id FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private static function seedPlantTaxa(PDO $db): void
    {
        $families = [
            ['family-araceae', 'Araceae', 'Aráceas'], ['family-asparagaceae', 'Asparagaceae', 'Espárragos y sansevierias'],
            ['family-cactaceae', 'Cactaceae', 'Cactáceas'], ['family-crassulaceae', 'Crassulaceae', 'Crassuláceas'],
            ['family-orchidaceae', 'Orchidaceae', 'Orquídeas'], ['family-lamiaceae', 'Lamiaceae', 'Aromáticas'],
            ['family-solanaceae', 'Solanaceae', 'Solanáceas'], ['family-arecaceae', 'Arecaceae', 'Palmeras'],
        ];
        $familyStatement = $db->prepare(
            'INSERT INTO plant_taxa (parent_id, taxon_type, code, scientific_name, common_name, ecosystem, toxicity_level, pet_safety_warning, is_active) '
            . "VALUES (NULL, 'family', ?, ?, ?, NULL, NULL, NULL, 1) "
            . 'ON DUPLICATE KEY UPDATE scientific_name = VALUES(scientific_name), common_name = VALUES(common_name), is_active = VALUES(is_active)'
        );
        foreach ($families as [$code, $scientificName, $commonName]) {
            $familyStatement->execute([$code, $scientificName, $commonName]);
        }
        $familyIds = $db->query("SELECT code, id FROM plant_taxa WHERE taxon_type = 'family'")->fetchAll(PDO::FETCH_KEY_PAIR);

        $species = [
            ['family-araceae', 'species-epipremnum-aureum', 'Epipremnum aureum', 'Pothos', 'indoor', 'toxic', 'Puede causar irritación si es ingerida por perros o gatos.'],
            ['family-araceae', 'species-monstera-deliciosa', 'Monstera deliciosa', 'Monstera', 'indoor', 'toxic', 'Puede causar irritación si es ingerida por perros o gatos.'],
            ['family-araceae', 'species-philodendron-hederaceum', 'Philodendron hederaceum', 'Filodendro corazón', 'indoor', 'toxic', 'Puede causar irritación si es ingerida por perros o gatos.'],
            ['family-asparagaceae', 'species-dracaena-trifasciata', 'Dracaena trifasciata', 'Sansevieria', 'indoor', 'toxic', 'Puede causar malestar digestivo en mascotas si se ingiere.'],
            ['family-cactaceae', 'species-schlumbergera-truncata', 'Schlumbergera truncata', 'Cactus de Navidad', 'semi-shade', 'safe', NULL],
            ['family-crassulaceae', 'species-echeveria-elegans', 'Echeveria elegans', 'Echeveria', 'dry', 'safe', NULL],
            ['family-crassulaceae', 'species-crassula-ovata', 'Crassula ovata', 'Árbol de jade', 'dry', 'toxic', 'Puede causar malestar digestivo y letargo en mascotas si se ingiere.'],
            ['family-orchidaceae', 'species-phalaenopsis-amabilis', 'Phalaenopsis amabilis', 'Orquídea mariposa', 'humid', 'safe', NULL],
            ['family-lamiaceae', 'species-ocimum-basilicum', 'Ocimum basilicum', 'Albahaca', 'sun', 'safe', NULL],
            ['family-lamiaceae', 'species-mentha-spicata', 'Mentha spicata', 'Menta', 'humid', 'safe', NULL],
            ['family-lamiaceae', 'species-salvia-rosmarinus', 'Salvia rosmarinus', 'Romero', 'sun', 'safe', NULL],
            ['family-solanaceae', 'species-solanum-lycopersicum', 'Solanum lycopersicum', 'Jitomate', 'sun', 'caution', 'Evita que mascotas ingieran hojas y tallos.'],
            ['family-solanaceae', 'species-capsicum-annuum', 'Capsicum annuum', 'Chile', 'sun', 'caution', 'Los frutos picantes pueden irritar a mascotas y niños.'],
            ['family-arecaceae', 'species-chamaedorea-elegans', 'Chamaedorea elegans', 'Palma de salón', 'indoor', 'safe', NULL],
        ];
        $speciesStatement = $db->prepare(
            "INSERT INTO plant_taxa (parent_id, taxon_type, code, scientific_name, common_name, ecosystem, toxicity_level, pet_safety_warning, is_active) VALUES (?, 'species', ?, ?, ?, ?, ?, ?, 1) "
            . 'ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id), scientific_name = VALUES(scientific_name), common_name = VALUES(common_name), ecosystem = VALUES(ecosystem), toxicity_level = VALUES(toxicity_level), pet_safety_warning = VALUES(pet_safety_warning), is_active = VALUES(is_active)'
        );
        foreach ($species as [$familyCode, $code, $scientificName, $commonName, $ecosystem, $toxicity, $warning]) {
            $speciesStatement->execute([$familyIds[$familyCode], $code, $scientificName, $commonName, $ecosystem, $toxicity, $warning]);
        }
    }
}
