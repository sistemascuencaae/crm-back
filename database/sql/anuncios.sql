-- =====================================================================
-- Modulo de Anuncios / Publicidad interna del CRM
-- Motor: PostgreSQL   Esquema: crm
-- =====================================================================
-- El proyecto no usa migraciones de Laravel para tablas de negocio
-- (solo hay 7 y son todas de framework), por eso esto va como script
-- directo. Los CREATE son idempotentes; los INSERT del final NO.
--
-- Las marcas de tiempo son timestamp sin zona, igual que el resto del
-- esquema crm. Los modelos ya escriben con America/Guayaquil via Carbon,
-- asi que lo que se guarda es lo que se lee. No mezclar con timestamptz.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Anuncios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm.anuncios (
    id            bigserial    PRIMARY KEY,
    titulo        varchar(150) NOT NULL,
    descripcion   text         NULL,
    fecha_inicio  timestamp    NOT NULL,
    fecha_fin     timestamp    NOT NULL,
    activo        boolean      NOT NULL DEFAULT true,
    -- true = va para toda la empresa y se ignora anuncios_destinos.
    -- Evita insertar una fila por cada departamento en el caso mas comun.
    ver_todos     boolean      NOT NULL DEFAULT false,
    orden         integer      NOT NULL DEFAULT 0,
    created_by    bigint       NULL,
    created_at    timestamp    NULL,
    updated_at    timestamp    NULL,

    CONSTRAINT anuncios_rango_fechas_check CHECK (fecha_fin >= fecha_inicio),
    CONSTRAINT anuncios_created_by_fkey
        FOREIGN KEY (created_by) REFERENCES crm.users (id) ON DELETE SET NULL
);

COMMENT ON TABLE  crm.anuncios             IS 'Anuncios internos mostrados al iniciar sesion y en las notificaciones';
COMMENT ON COLUMN crm.anuncios.ver_todos   IS 'true = visible para todos; ignora crm.anuncios_destinos';
COMMENT ON COLUMN crm.anuncios.orden       IS 'Orden de aparicion en el carrusel entre varios anuncios';

-- indice del filtro caliente: vigentes de hoy
CREATE INDEX IF NOT EXISTS anuncios_vigencia_idx
    ON crm.anuncios (activo, fecha_inicio, fecha_fin);


-- ---------------------------------------------------------------------
-- 2. Imagenes del anuncio (N por anuncio, ordenadas)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm.anuncios_imagenes (
    id          bigserial    PRIMARY KEY,
    anuncio_id  bigint       NOT NULL,
    -- ruta relativa dentro del disco (nas o local), ej: anuncios/2026/xxx.jpg
    ruta        varchar(255) NOT NULL,
    alt         varchar(150) NULL,
    orden       integer      NOT NULL DEFAULT 0,
    created_at  timestamp    NULL,
    updated_at  timestamp    NULL,

    CONSTRAINT anuncios_imagenes_anuncio_id_fkey
        FOREIGN KEY (anuncio_id) REFERENCES crm.anuncios (id) ON DELETE CASCADE
);

COMMENT ON COLUMN crm.anuncios_imagenes.ruta IS 'Ruta del nas o local';

CREATE INDEX IF NOT EXISTS anuncios_imagenes_anuncio_idx
    ON crm.anuncios_imagenes (anuncio_id, orden);


-- ---------------------------------------------------------------------
-- 3. Destinos: a quien le sale el anuncio
-- ---------------------------------------------------------------------
-- Una sola tabla con columna 'tipo' en vez de tres tablas con FK.
--
-- OJO: destino_id NO tiene llave foranea porque apunta a tres tablas
-- distintas segun el tipo. Si borran un departamento o un perfil, la
-- fila queda huerfana y simplemente deja de coincidir con alguien.
--
-- 'tipo' tampoco tiene CHECK, para poder agregar tipos nuevos sin ALTER.
-- Consecuencia: un dedazo ('departmento') entra sin error y el anuncio no
-- le llega a nadie, en silencio. La validacion tiene que hacerla si o si
-- el controlador contra su lista de tipos permitidos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm.anuncios_destinos (
    id          bigserial   PRIMARY KEY,
    anuncio_id  bigint      NOT NULL,
    tipo        varchar(20) NOT NULL,
    destino_id  bigint      NOT NULL,
    created_at  timestamp   NULL,
    updated_at  timestamp   NULL,

    CONSTRAINT anuncios_destinos_anuncio_id_fkey
        FOREIGN KEY (anuncio_id) REFERENCES crm.anuncios (id) ON DELETE CASCADE,
    -- impide agregar dos veces el mismo destino al mismo anuncio
    CONSTRAINT anuncios_destinos_unico
        UNIQUE (anuncio_id, tipo, destino_id)
);

COMMENT ON COLUMN crm.anuncios_destinos.tipo       IS 'departamento -> users.dep_id | perfil -> users.profile_id | usuario -> users.id';
COMMENT ON COLUMN crm.anuncios_destinos.destino_id IS 'Id en la tabla que corresponda segun tipo. Sin FK a proposito';

CREATE INDEX IF NOT EXISTS anuncios_destinos_busqueda_idx
    ON crm.anuncios_destinos (tipo, destino_id);


-- ---------------------------------------------------------------------
-- 4. Vistos
-- ---------------------------------------------------------------------
-- El visto es PERMANENTE por usuario, no por sesion. No sirve para ocultar
-- el anuncio: el modal sale igual en cada inicio de sesion. Sirve para
-- ordenar (los no vistos van primero) y como registro auditable de quien
-- vio que y cuando.
--
-- Por eso no hay sesion_key: no hace falta distinguir sesiones. Una fila
-- por usuario y anuncio, con created_at = la primera vez que lo vio.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm.anuncios_vistos (
    id          bigserial   PRIMARY KEY,
    anuncio_id  bigint      NOT NULL,
    user_id     bigint      NOT NULL,
    created_at  timestamp   NULL,
    updated_at  timestamp   NULL,

    CONSTRAINT anuncios_vistos_anuncio_id_fkey
        FOREIGN KEY (anuncio_id) REFERENCES crm.anuncios (id) ON DELETE CASCADE,
    CONSTRAINT anuncios_vistos_user_id_fkey
        FOREIGN KEY (user_id) REFERENCES crm.users (id) ON DELETE CASCADE,
    -- una sola marca por usuario y anuncio: permite insertar con
    -- ON CONFLICT DO NOTHING sin preguntar antes si ya existe
    CONSTRAINT anuncios_vistos_unico
        UNIQUE (anuncio_id, user_id)
);

CREATE INDEX IF NOT EXISTS anuncios_vistos_usuario_idx
    ON crm.anuncios_vistos (user_id, anuncio_id);


-- =====================================================================
-- 5. INTERRUPTOR DEL MODULO  (crm.parametro)
-- =====================================================================
-- Permite desplegar el codigo a un ambiente donde las tablas de arriba
-- todavia NO existen. Con activar = false, ningun endpoint llega a
-- consultar crm.anuncios y nada revienta.
--
-- La busqueda es por 'abreviacion', igual que la bandera NAS. Si la fila
-- no existe o la abreviacion esta vacia, el modulo queda APAGADO: el
-- default seguro es no funcionar.
-- =====================================================================

-- Si la fila ya existe pero sin abreviacion (caso de la fila id = 28):
-- UPDATE crm.parametro SET abreviacion = 'ANUNCIOS' WHERE id = 28;

-- Si hay que crearla desde cero:
-- INSERT INTO crm.parametro (nombre, descripcion, abreviacion, activar)
-- VALUES ('Activar el modulo de anuncios',
--         'Con la columna activar, se prende o se apaga los anuncios',
--         'ANUNCIOS', false);

-- Encender / apagar:
-- UPDATE crm.parametro SET activar = true  WHERE abreviacion = 'ANUNCIOS';
-- UPDATE crm.parametro SET activar = false WHERE abreviacion = 'ANUNCIOS';

-- Verificar como lo ve el backend:
-- SELECT id, nombre, abreviacion, activar FROM crm.parametro WHERE abreviacion = 'ANUNCIOS';


-- =====================================================================
-- DATOS DE PRUEBA
-- Reproducen el array quemado del frontend para poder cambiar
-- AnunciosService a HTTP y ver exactamente lo mismo.
-- Las rutas apuntan a assets del front; las reales las escribe el
-- controlador al subir al disco nas.
--
-- OJO: este bloque NO es idempotente. Correrlo dos veces duplica los
-- anuncios.
-- =====================================================================

INSERT INTO crm.anuncios (titulo, descripcion, fecha_inicio, fecha_fin, activo, ver_todos, orden, created_at, updated_at)
VALUES
    ('Nueva politica de garantias',
     'Desde el 1 de septiembre cambia el proceso de ingreso de garantias GEX.',
     '2026-01-01 00:00:00', '2026-12-31 23:59:59', true, true, 1, NOW(), NOW()),

    ('Capacitacion de cobranzas',
     'Jueves 20 de agosto, 15h00, sala de reuniones.',
     '2026-01-01 00:00:00', '2026-12-31 23:59:59', true, true, 2, NOW(), NOW()),

    ('Anuncio vencido de prueba',
     'Este no deberia aparecer nunca.',
     '2025-01-01 00:00:00', '2025-06-30 23:59:59', true, true, 3, NOW(), NOW());

INSERT INTO crm.anuncios_imagenes (anuncio_id, ruta, alt, orden, created_at, updated_at)
SELECT a.id, v.ruta, v.alt, v.orden, NOW(), NOW()
FROM crm.anuncios a
JOIN (VALUES
    ('Nueva politica de garantias', 'assets/img/fondo-formulario.jpg', 'Politica de garantias', 1),
    ('Nueva politica de garantias', 'assets/img/logo-espana.png',      'Almacenes Espana',      2),
    ('Capacitacion de cobranzas',   'assets/img/default.jpg',          'Capacitacion',          1),
    ('Anuncio vencido de prueba',   'assets/img/default.png',          'Vencido',               1)
) AS v(titulo, ruta, alt, orden) ON v.titulo = a.titulo;


-- =====================================================================
-- CONSULTA DE VIGENTES PARA UN USUARIO
-- Es la que va a usar AnunciosUsuarioController. Se deja aqui para poder
-- probarla suelta antes de escribir el controlador.
-- Reemplazar :user_id por un id real.
-- =====================================================================
--
-- SELECT a.*, (v.id IS NOT NULL) AS visto
-- FROM crm.anuncios a
-- JOIN crm.users u ON u.id = :user_id
-- LEFT JOIN crm.anuncios_vistos v ON v.anuncio_id = a.id AND v.user_id = u.id
-- WHERE a.activo = true
--   AND NOW() BETWEEN a.fecha_inicio AND a.fecha_fin
--   AND (
--         a.ver_todos = true
--         OR EXISTS (
--              SELECT 1
--              FROM crm.anuncios_destinos d
--              WHERE d.anuncio_id = a.id
--                AND (
--                      (d.tipo = 'departamento' AND d.destino_id = u.dep_id)
--                   OR (d.tipo = 'perfil'       AND d.destino_id = u.profile_id)
--                   OR (d.tipo = 'usuario'      AND d.destino_id = u.id)
--                    )
--            )
--       )
-- -- false ordena antes que true: los NO vistos salen primero.
-- -- Si ya vio todos, la primera clave empata y queda el orden de siempre.
-- ORDER BY (v.id IS NOT NULL), a.orden, a.id;
--
-- Se usa EXISTS y no un LEFT JOIN a destinos: con el join, un anuncio que
-- coincide por departamento Y por usuario devolvia la fila dos veces y
-- obligaba a un DISTINCT que despues pelea con el ORDER BY.


-- =====================================================================
-- MARCAR VISTO (lo llama el endpoint al abrir el modal)
-- =====================================================================
--
-- INSERT INTO crm.anuncios_vistos (anuncio_id, user_id, created_at, updated_at)
-- VALUES (:anuncio_id, :user_id, NOW(), NOW())
-- ON CONFLICT (anuncio_id, user_id) DO NOTHING;


-- =====================================================================
-- CONSULTAS UTILES
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Perfiles que tienen permiso sobre el modulo de Anuncios
-- ---------------------------------------------------------------------
-- view/create/edit/delete son palabras reservadas en PostgreSQL: van
-- entre comillas dobles si o si.
-- ---------------------------------------------------------------------
-- SELECT p.id   AS perfil_id,
--        p.name AS perfil,
--        ac."view"   AS ver,
--        ac."create" AS crear,
--        ac."edit"   AS editar,
--        ac."delete" AS eliminar
-- FROM crm.access ac
-- JOIN crm.menu     m ON m.id = ac.menu_id
-- JOIN crm.profiles p ON p.id = ac.profile_id
-- WHERE m.name = 'ANUNCIOS'
-- ORDER BY p.name;


-- ---------------------------------------------------------------------
-- 2. Usuarios concretos que pueden entrar al modulo
-- ---------------------------------------------------------------------
-- El permiso vive en el perfil, no en el usuario: esto lo expande.
-- ---------------------------------------------------------------------
-- SELECT u.id, u.name, u.usu_alias, p.name AS perfil, ac."view" AS ver
-- FROM crm.users u
-- JOIN crm.profiles p ON p.id = u.profile_id
-- JOIN crm.access  ac ON ac.profile_id = p.id
-- JOIN crm.menu     m ON m.id = ac.menu_id
-- WHERE m.name = 'ANUNCIOS'
--   AND ac."view" = 1
-- ORDER BY p.name, u.name;


-- ---------------------------------------------------------------------
-- 3. Quien ya vio cada anuncio
-- ---------------------------------------------------------------------
-- SELECT a.id AS anuncio_id,
--        a.titulo,
--        u.id AS user_id,
--        u.name,
--        u.usu_alias,
--        d.dep_nombre AS departamento,
--        v.created_at AS visto_el
-- FROM crm.anuncios_vistos v
-- JOIN crm.anuncios a ON a.id = v.anuncio_id
-- JOIN crm.users    u ON u.id = v.user_id
-- LEFT JOIN crm.departamento d ON d.id = u.dep_id
-- ORDER BY a.id, v.created_at DESC;


-- ---------------------------------------------------------------------
-- 4. Resumen: cuantos lo vieron por anuncio
-- ---------------------------------------------------------------------
-- SELECT a.id, a.titulo, a.fecha_inicio, a.fecha_fin,
--        COUNT(v.id) AS total_vistos
-- FROM crm.anuncios a
-- LEFT JOIN crm.anuncios_vistos v ON v.anuncio_id = a.id
-- GROUP BY a.id, a.titulo, a.fecha_inicio, a.fecha_fin
-- ORDER BY a.id DESC;


-- ---------------------------------------------------------------------
-- 5. Quien NO lo ha visto todavia (solo entre sus destinatarios)
-- ---------------------------------------------------------------------
-- Esta es la util para dar seguimiento: aplica el mismo filtro de
-- segmentacion que usa el controlador, asi que no lista a gente a la que
-- el anuncio nunca le iba a llegar.
-- Reemplazar :anuncio_id
-- ---------------------------------------------------------------------
-- SELECT u.id, u.name, u.usu_alias, d.dep_nombre AS departamento, p.name AS perfil
-- FROM crm.users u
-- JOIN crm.anuncios a ON a.id = :anuncio_id
-- LEFT JOIN crm.departamento d ON d.id = u.dep_id
-- LEFT JOIN crm.profiles      p ON p.id = u.profile_id
-- WHERE (
--         a.ver_todos = true
--         OR EXISTS (
--              SELECT 1
--              FROM crm.anuncios_destinos ad
--              WHERE ad.anuncio_id = a.id
--                AND (
--                      (ad.tipo = 'departamento' AND ad.destino_id = u.dep_id)
--                   OR (ad.tipo = 'perfil'       AND ad.destino_id = u.profile_id)
--                   OR (ad.tipo = 'usuario'      AND ad.destino_id = u.id)
--                    )
--            )
--       )
--   AND NOT EXISTS (
--         SELECT 1 FROM crm.anuncios_vistos v
--         WHERE v.anuncio_id = a.id AND v.user_id = u.id
--       )
-- ORDER BY d.dep_nombre, u.name;


-- =====================================================================
-- NOTA DE ZONA HORARIA
-- =====================================================================
-- Se uso timestamp SIN zona a proposito, por consistencia con el resto
-- del esquema crm y porque Ecuador es UTC-5 todo el ano, sin horario de
-- verano. Lo que escribe Carbon con America/Guayaquil es exactamente lo
-- que se lee, sin que el TimeZone de la sesion pueda alterarlo.
--
-- Si algun dia se migra a timestamptz, tiene que ser en TODO el esquema
-- a la vez y declarando 'timezone' => 'America/Guayaquil' en la conexion
-- pgsql de config/database.php. Tener los dos tipos conviviendo es lo
-- que produce fechas corridas 5 horas.
--
-- NOW() devuelve timestamptz y se compara contra columnas timestamp
-- convirtiendo con el TimeZone de la sesion. Si SHOW TIME ZONE no dice
-- America/Guayaquil, usar LOCALTIMESTAMP en vez de NOW() en la consulta
-- de vigencia.
-- =====================================================================


-- =====================================================================
-- REVERSA (descomentar solo si hay que rehacer todo)
-- =====================================================================
-- DROP TABLE IF EXISTS crm.anuncios_vistos;
-- DROP TABLE IF EXISTS crm.anuncios_destinos;
-- DROP TABLE IF EXISTS crm.anuncios_imagenes;
-- DROP TABLE IF EXISTS crm.anuncios;
