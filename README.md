# Plantia — Tu botánica de confianza

Plataforma de aprendizaje de botánica con registro, inicio de sesión, cuestionario inicial y catálogo de cursos personalizado.

## Propósito y arquitectura

Plantia es una plataforma web de aprendizaje de botánica con cursos, lecciones,
actividades prácticas y cuestionarios. Cada persona crea un perfil inicial para
adaptar su experiencia y puede configurar opciones de accesibilidad.

La aplicación está construida sin framework: el navegador carga módulos ES
nativos y consume una API PHP de una sola entrada; PHP persiste los datos en
MySQL/MariaDB mediante PDO.

```text
Navegador (front/js) → api.php → back/ → MySQL/MariaDB
       ↑                   ↓
  vistas HTML         respuestas JSON
```

- `front/js/main.js`: inicio, estado en memoria y navegación.
- `front/js/views/`: vistas y controles de la interfaz.
- `front/js/services/`: cliente HTTP y operaciones de sesión/aprendizaje.
- `back/`: autenticación, perfiles, HTTP y lógica de cursos.
- `database/migrations/`: cambios versionados del esquema.
- `database/seeders/`: catálogo, reglas y contenido inicial.

## Flujo principal

1. La persona se registra o inicia sesión.
2. Completa el cuestionario de entorno, intereses y objetivo.
3. Elige un curso, consulta sus lecciones y entrega actividades.
4. El servidor guarda intentos, progreso y preferencias.

## API

El único punto de entrada es `api.php?action=<acción>`. Las acciones que reciben
datos usan JSON y las operaciones privadas requieren una cookie de sesión.

| Acción | Método | Responsabilidad |
| --- | --- | --- |
| `status` | GET | Consulta sesión y perfil. |
| `register`, `login`, `logout` | POST | Gestionan autenticación. |
| `courses`, `course`, `lesson` | GET | Obtienen contenidos de aprendizaje. |
| `enroll`, `submit_attempt`, `complete_lesson` | POST | Registran avance. |
| `save_profile`, `save_preferences` | POST | Guardan perfil y accesibilidad. |
| `references` | GET | Lista fuentes por categoría. |

## Convenciones de código

- Guarda archivos de texto en UTF-8 sin BOM.
- Usa plantillas HTML multilínea y sangría de cuatro espacios en las vistas.
- Escapa datos interpolados en HTML con `escapeHtml`.
- Usa consultas preparadas para cualquier dato externo.
- Para modificar el esquema, agrega una migración nueva; no edites una aplicada.

## Requisitos

Antes de iniciar el proyecto, instala y deja disponible en la terminal:

- **PHP 8.2 o superior**, con `PDO`, `pdo_mysql` y `json` activados.
- **MySQL 8+ o MariaDB 10.6+** en ejecución. La cuenta configurada debe poder
  crear la base de datos la primera vez y crear/alterar tablas después.
- Un navegador moderno con JavaScript habilitado. No se requieren Node.js, npm,
  Composer ni un proceso de compilación.
- Git, solo si se va a clonar el repositorio.

Comprueba PHP y sus extensiones con:

```sh
php --version
php -m
```

En la lista de módulos deben aparecer `PDO` y `pdo_mysql`.

## Configuración de base de datos

Plantia lee las variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y
`DB_PASSWORD`. Si no se definen, usa `127.0.0.1:3306`, la base
`botanica_cursos`, el usuario `root` y una contraseña vacía.

| Variable | Valor predeterminado | Descripción |
| --- | --- | --- |
| `DB_HOST` | `127.0.0.1` | Servidor de MySQL/MariaDB. |
| `DB_PORT` | `3306` | Puerto del servidor. |
| `DB_DATABASE` | `botanica_cursos` | Base que Plantia crea si no existe. |
| `DB_USERNAME` | `root` | Usuario de conexión. |
| `DB_PASSWORD` | vacío | Contraseña del usuario. |

```powershell
# Windows PowerShell: ajusta los valores al servidor local o remoto.
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'botanica_cursos'
$env:DB_USERNAME = 'plantia'
$env:DB_PASSWORD = 'una-contrasena-segura'
```

```sh
# macOS, Linux, Git Bash o WSL
export DB_HOST='127.0.0.1'
export DB_PORT='3306'
export DB_DATABASE='botanica_cursos'
export DB_USERNAME='plantia'
export DB_PASSWORD='una-contrasena-segura'
```

## Instalación e inicio

Desde la raíz del repositorio, los siguientes comandos funcionan en cualquier
sistema operativo donde `php` esté disponible en el `PATH`:

```sh
# Crea la base de datos y aplica las migraciones pendientes.
php database/migrate.php

# Carga catálogo, reglas, referencias y cursos de aprendizaje.
php database/seed.php

# Arranca el servidor de desarrollo desde la raíz del repositorio.
php -S localhost:8000
```

Abre `http://localhost:8000` y deja esa terminal abierta mientras uses la
aplicación. Las migraciones y seeders son idempotentes: puedes ejecutarlos de
nuevo sin duplicar los datos iniciales.

## Ejecución local (referencia rápida)

Requiere PHP 8.2 o posterior con `pdo_mysql` y MariaDB/MySQL iniciado.

```powershell
php database/migrate.php
php database/seed.php
php -S localhost:8000
```

Visita `http://localhost:8000`. La primera solicitud también ejecuta las migraciones pendientes.

## Migraciones MySQL / MariaDB

Las migraciones se registran en `schema_migrations`, así que pueden ejecutarse varias veces sin duplicar tablas ni catálogos.

La base se llama `botanica_cursos` por defecto y aparecerá en phpMyAdmin.

Para aplicar y comprobar las migraciones manualmente:

```powershell
php database/migrate.php
```

## Catálogos iniciales

Los seeders son idempotentes. El comando general inserta o actualiza categorías,
regiones climáticas, etiquetas, taxonomía vegetal, reglas, referencias y cursos
de aprendizaje, sin alterar cuentas de usuarios existentes.

```powershell
php database/seed.php
```

Para cargar la ampliación general del catálogo (más categorías, regiones, etiquetas y taxones), después del seeder inicial ejecuta:

```powershell
php database/seed_extended_catalog.php
```

El catálogo botánico ampliado y sus relaciones con los cursos se ejecutan dentro de `database/seed.php`. También se pueden actualizar de forma independiente:

```powershell
php database/seed_comprehensive_catalog.php
```

Para cambios futuros, agrega una migración con una versión nueva a `database/migrations/` y regístrala en `back/migrations.php`. No modifiques una migración que ya se haya aplicado en instalaciones compartidas.

La conexión se configura con las variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME` y `DB_PASSWORD`, descritas en la sección de configuración.

## Solución de problemas

- Si `php` no se reconoce como comando, instala PHP o agrega su directorio al
  `PATH` y abre una terminal nueva.
- Si aparece `could not find driver`, habilita `pdo_mysql` en el `php.ini` que
  indica `php --ini` y reinicia la terminal.
- Si la conexión falla, inicia MySQL/MariaDB y revisa los valores `DB_*`.
- Si la cuenta no puede crear la base, concédele permisos sobre `DB_DATABASE` o
  crea la base manualmente antes de ejecutar las migraciones.
- Si el puerto 8000 está ocupado, usa `php -S localhost:8080` y abre esa URL.

## Estructura

```text
api.php                 Punto de entrada de la API
back/
  bootstrap.php         Sesión y conexión a la base de datos
  http.php              Lectura y respuestas HTTP
  users.php             Registro, login y usuario autenticado
  profiles.php          Datos del cold start
front/js/
  services/             Cliente HTTP, sesión y operaciones de cursos
  views/                Vistas de acceso, onboarding y aprendizaje
  catalog.js            Catálogo y opciones del cuestionario
front/js/main.js        Inicio y navegación de la aplicación
app.css                 Estilos de Plantia
storage/                Sesiones locales, ignoradas por Git
```

## Datos guardados

Actualmente la base almacena, entre otros, estos grupos de datos:

- `users` y `auth_sessions`: identidad, credenciales protegidas y sesiones.
- `user_profiles` y `user_tags`: perfil inicial y preferencias de interfaz.
- `courses`, `course_modules`, `lessons` y `lesson_blocks`: contenido.
- `user_courses`, `user_lesson_progress` y `user_block_attempts`: avance e intentos.
- Catálogos de categorías, etiquetas, taxones, reglas y referencias.

## Motor adaptativo híbrido

Plantia usa un motor híbrido: las reglas de negocio y seguridad tienen
prioridad y un modelo de aprendizaje automático solamente mejora el orden de
los cursos permitidos. Así, una predicción no puede ignorar condiciones como
el nivel, poco tiempo disponible o seguridad para mascotas.

El ejecutable autocontenido es `tools/adaptive_engine.py`; no requiere paquetes
de Python. Incluye recepción de eventos, perfil, decisor reglas + ML,
explicaciones y registro auditable de decisiones.

```powershell
# Demostración sin base de datos ni archivos previos.
python tools/adaptive_engine.py demo

# Recomendación desde JSON y bitácora append-only de decisiones.
python tools/adaptive_engine.py recommend --input entrada.json --output recomendaciones.json --decisions-log storage/decisions.jsonl

# Recibe un evento inmutable de la interfaz (por ejemplo, una apertura).
python tools/adaptive_engine.py record-event --input evento.json --events-log storage/events.jsonl

# Entrenamiento incremental: profile, course, label (1=aceptación, 0=descarte)
# y opcionalmente events, por cada objeto del arreglo de entrada.
python tools/adaptive_engine.py train --input entrenamiento.json --model storage/adaptive-model.json
```

Contrato mínimo para `entrada.json`:

```json
{
  "profile": {"id": 7, "knowledge_level": "beginner", "has_pets": true, "tags": ["interest-indoor", "time-low"]},
  "events": [{"type": "opened", "course_id": 1}],
  "courses": [{"id": 1, "slug": "plantas-felices", "title": "Plantas felices", "level": "beginner", "estimated_minutes": 75, "tags": ["interest-indoor", "safety-pet-safe"]}]
}
```

Para consumir las reglas ya sembradas en MySQL, expórtalas como objetos con
`code`, `priority`, `conditions` y `actions`, y añádelas como `rules` al mismo
documento. Las acciones admiten `target_tag`, `score` y `data` (o
`action_data`), de forma que las reglas siguen siendo datos y no código Python.

La aplicación web ya invoca el motor al solicitar `courses`: usa el perfil de
cold start, las etiquetas del usuario y las etiquetas de cada curso. Al abrir o
inscribirse en un curso, registra un evento en `adaptive_events`; al volver al
catálogo, ese comportamiento se incorpora al orden y a la explicación “Para
ti”. La migración `006_adaptive_engine_events` se aplica automáticamente al
iniciar la API. PHP busca el ejecutable `python`; si tiene otro nombre o ruta,
define la variable de entorno `PYTHON_BIN` antes de iniciar `php -S`.


## Autenticación

El correo y la contraseña se verifican contra `users`. Tras validarlos, Plantia entrega una cookie `HttpOnly` de sesión y conserva en `auth_sessions` solo el hash del token, con caducidad de ocho horas. Al cerrar sesión el token se revoca; al expirar se limpian las filas vencidas. La aplicación no utiliza archivos de sesión ni necesita la carpeta `storage`.
