<?php
declare(strict_types=1);

/** @return array<int, array{version: string, up: callable(PDO): void}> */
function migrations(): array
{
    return [
        require __DIR__ . '/../database/migrations/001_mysql_schema.php',
        require __DIR__ . '/../database/migrations/002_user_interface_preferences.php',
        require __DIR__ . '/../database/migrations/003_recommendation_rule_payloads.php',
        require __DIR__ . '/../database/migrations/004_learning_content_metadata.php',
        require __DIR__ . '/../database/migrations/005_database_auth_sessions.php',
        require __DIR__ . '/../database/migrations/006_adaptive_engine_events.php',
    ];
}

/** Ejecuta solo migraciones que aún no figuran en schema_migrations. */
function runMigrations(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $applied = $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

    foreach (migrations() as $migration) {
        if (in_array($migration['version'], $applied, true)) {
            continue;
        }

        ($migration['up'])($db);
        $statement = $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $statement->execute([$migration['version']]);
    }
}
