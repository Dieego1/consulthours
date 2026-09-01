-- =====================================================================
-- ES: ConsultHours -- Esquema de base de datos (MySQL / XAMPP). Solo
--     crea las tablas vacias; para llenarlas con datos de prueba usa
--     seed.php (linea de comandos) o seed.sql (phpMyAdmin).
-- EN: ConsultHours -- Database schema (MySQL / XAMPP). Only creates the
--     empty tables; to fill them with test data use seed.php
--     (command line) or seed.sql (phpMyAdmin).
--
-- ES: Ejecutar este archivo en phpMyAdmin o con:
-- EN: Run this file in phpMyAdmin or with:
--       mysql -u root -p < schema.sql
-- =====================================================================

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
    -- ES: Hash generado con password_hash() (bcrypt). Nunca texto plano.
    -- EN: Hash generated with password_hash() (bcrypt). Never plain text.
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin', 'consultant') NOT NULL DEFAULT 'consultant',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ES: Tabla: clients -- Clientes de la consultora a los que se facturan horas.
-- EN: Table: clients -- Clients of the consultancy that hours are billed to.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ES: Tabla: time_records -- Registro de horas trabajadas por un
--     consultor para un cliente.
-- EN: Table: time_records -- Time record entries logged by a
--     consultant against a client.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS time_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    -- ES: El dueño del registro SIEMPRE se toma de la sesión del
    --     servidor, nunca de un valor enviado por el cliente (ver
    --     backend/api/records.php).
    -- EN: The record owner is ALWAYS taken from the server session,
    --     never from a client-supplied value (see
    --     backend/api/records.php).
    consultant_id   INT NOT NULL,
    client_id       INT NOT NULL,
    work_date       DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    -- ES: Horas facturables, derivadas de start_time/end_time en el
    --     backend (no se confía en un valor de horas enviado suelto
    --     por el cliente).
    -- EN: Billable hours, derived from start_time/end_time on the
    --     backend (a loose "hours" value sent by the client is never
    --     trusted).
    hours           DECIMAL(4,2) NOT NULL,
    description     VARCHAR(255) NOT NULL DEFAULT '',
    billable        TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_consultant FOREIGN KEY (consultant_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_client     FOREIGN KEY (client_id)     REFERENCES clients(id) ON DELETE RESTRICT,
    INDEX idx_consultant_date (consultant_id, work_date),
    INDEX idx_client_date (client_id, work_date)
) ENGINE=InnoDB;
