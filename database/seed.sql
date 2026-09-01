-- =====================================================================
-- ES: ConsultHours -- Volcado SQL completo (esquema + datos de prueba),
--     listo para importarse directamente en phpMyAdmin.
-- EN: ConsultHours -- Full SQL dump (schema + seed data), ready to
--     import directly in phpMyAdmin.
--
-- ES: Generado a partir de database/seed_data.json (la misma fuente que
--     usa database/seed.php) para que ambos caminos -- importar este
--     archivo en phpMyAdmin, o correr seed.php por consola -- produzcan
--     exactamente los mismos datos.
-- EN: Generated from database/seed_data.json (the same source used by
--     database/seed.php) so both paths -- importing this file in
--     phpMyAdmin, or running seed.php from the CLI -- produce exactly
--     the same data.
--
-- ES: Como en phpMyAdmin no corre PHP, las contrasenas ya vienen
--     hasheadas con password_hash()/bcrypt (no en texto plano); las
--     contrasenas reales para iniciar sesion son las de seed_data.json
--     (ver tambien README.md): admin/admin123, carla/carla2024,
--     miguel/miguel!!
-- EN: Since phpMyAdmin does not run PHP, the passwords below are already
--     hashed with password_hash()/bcrypt (not plain text); the real
--     login passwords are the ones in seed_data.json (see also
--     README.md): admin/admin123, carla/carla2024, miguel/miguel!!
-- =====================================================================

-- ES: Se elimina primero por si ya existia a medias (de un intento
--     anterior que fallo a mitad de camino) -- asi este script
--     siempre parte de cero y nunca choca con tablas/datos previos.
-- EN: Dropped first in case it already existed halfway (from a
--     previous attempt that failed partway through) -- this way the
--     script always starts clean and never collides with leftover
--     tables/data.
DROP DATABASE IF EXISTS consulthours;

CREATE DATABASE IF NOT EXISTS consulthours
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE consulthours;

-- ---------------------------------------------------------------------
-- ES: Tabla: users -- Consultores y administradores del sistema.
-- EN: Table: users -- Consultants and administrators of the system.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin', 'consultant') NOT NULL DEFAULT 'consultant',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ES: Tabla: clients -- Clientes de la consultora.
-- EN: Table: clients -- Clients of the consultancy.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ES: Tabla: time_records -- Horas trabajadas por consultor y cliente.
-- EN: Table: time_records -- Hours worked, per consultant and client.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS time_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    consultant_id   INT NOT NULL,
    client_id       INT NOT NULL,
    work_date       DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    hours           DECIMAL(4,2) NOT NULL,
    description     VARCHAR(255) NOT NULL DEFAULT '',
    billable        TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_consultant FOREIGN KEY (consultant_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_client     FOREIGN KEY (client_id)     REFERENCES clients(id) ON DELETE RESTRICT,
    INDEX idx_consultant_date (consultant_id, work_date),
    INDEX idx_client_date (client_id, work_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ES: Datos de prueba / EN: Seed data
--
-- ES: No hace falta TRUNCATE aqui: como el script empezo con DROP
--     DATABASE + CREATE TABLE, las tablas ya nacen vacias. Se evita
--     TRUNCATE a proposito porque en InnoDB este comando falla si la
--     tabla esta referenciada por una llave foranea -- incluso con 0
--     filas -- a menos que FOREIGN_KEY_CHECKS este en 0; y algunas
--     interfaces (p. ej. la casilla "Habilitar la revision de las
--     claves foraneas" de phpMyAdmin) pueden ignorar ese SET.
-- EN: No TRUNCATE needed here: since the script started with DROP
--     DATABASE + CREATE TABLE, the tables are already empty. TRUNCATE
--     is avoided on purpose because in InnoDB it fails on a table
--     referenced by a foreign key -- even with 0 rows -- unless
--     FOREIGN_KEY_CHECKS is 0; and some UIs (e.g. phpMyAdmin's
--     "Enable foreign key checks" checkbox) can override that SET.
-- ---------------------------------------------------------------------
INSERT INTO users (id, username, password_hash, full_name, role) VALUES
  (1, 'admin', '$2y$10$NXK.oHawbaIYKHuRKDWzA.iJYjddegw3pYeAcK7X1as98gliRS0oe', 'Administrador General', 'admin'),
  (2, 'carla', '$2y$10$AT8cN4psT7RNV0wRz3iF7eY4DxH1uSq2sU/GNKtFJ9B9hjrDY7oMq', 'Carla Jiménez', 'consultant'),
  (3, 'miguel', '$2y$10$3eeHkMFSEsg6ueC/4evvte4/qZ5851OmoZQRTE9DyibcCwPOTgjCu', 'Miguel Torres', 'consultant');

INSERT INTO clients (id, name) VALUES
  (1, 'Acme Corp'),
  (2, 'Globex Industries'),
  (3, 'Initech Solutions'),
  (4, 'Umbrella Labs');

INSERT INTO time_records (consultant_id, client_id, work_date, start_time, end_time, hours, description, billable) VALUES
  (2, 1, '2026-07-02', '09:00:00', '12:00:00', 3.00, 'Levantamiento de requerimientos', 1),
  (2, 2, '2026-07-03', '13:00:00', '17:00:00', 4.00, 'Desarrollo de módulo de reportes', 1),
  (2, 1, '2026-07-07', '09:00:00', '11:00:00', 2.00, 'Revisión de código', 1),
  (2, 3, '2026-07-10', '10:00:00', '12:00:00', 2.00, 'Capacitación interna (no facturable)', 0),
  (2, 2, '2026-07-15', '14:00:00', '18:00:00', 4.00, 'Implementación de API', 1),
  (2, 1, '2026-07-22', '09:00:00', '13:00:00', 4.00, 'Pruebas de integración', 1),
  (3, 3, '2026-07-01', '09:00:00', '13:00:00', 4.00, 'Configuración de servidores', 1),
  (3, 4, '2026-07-08', '09:00:00', '12:00:00', 3.00, 'Auditoría de seguridad', 1),
  (3, 3, '2026-07-14', '13:00:00', '17:00:00', 4.00, 'Migración de base de datos', 1),
  (3, 4, '2026-07-21', '09:00:00', '11:00:00', 2.00, 'Soporte interno post-venta (no facturable)', 0),
  (2, 4, '2026-08-04', '09:00:00', '11:30:00', 2.50, 'Diagnóstico de incidente', 1),
  (2, 1, '2026-08-06', '09:00:00', '12:00:00', 3.00, 'Reunión de arquitectura (registro A, se traslapa con B)', 1),
  (2, 2, '2026-08-06', '11:00:00', '13:00:00', 2.00, 'Soporte urgente (registro B, se traslapa con A de 11:00 a 12:00)', 1),
  (2, 1, '2026-08-12', '09:00:00', '17:00:00', 8.00, 'Sprint de desarrollo', 1),
  (2, 3, '2026-08-18', '09:00:00', '12:00:00', 3.00, 'Reunión interna de equipo (no facturable)', 0),
  (2, 2, '2026-08-25', '09:00:00', '13:00:00', 4.00, 'Optimización de consultas SQL', 1),
  (3, 3, '2026-08-05', '09:00:00', '13:00:00', 4.00, 'Desarrollo de dashboard', 1),
  (3, 4, '2026-08-11', '09:00:00', '15:00:00', 6.00, 'Implementación de CI/CD', 1),
  (3, 3, '2026-08-19', '09:00:00', '12:00:00', 3.00, 'Revisión de arquitectura', 1),
  (3, 4, '2026-08-27', '09:00:00', '13:00:00', 4.00, 'Corrección de bugs críticos', 1);

