-- =====================================================================
-- ConsultHours - Esquema de base de datos (MySQL / XAMPP)
-- ConsultHours - Database schema (MySQL / XAMPP)
-- =====================================================================
-- Ejecutar este archivo en phpMyAdmin o con:
-- Run this file in phpMyAdmin or with:
--   mysql -u root -p < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS consulthours
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE consulthours;

-- ---------------------------------------------------------------------
-- Tabla: users
-- Consultores y administradores del sistema.
-- Users table. Consultants and administrators of the system.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    -- Hash generado con password_hash() (bcrypt). Nunca texto plano.
    -- Hash generated with password_hash() (bcrypt). Never plain text.
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin', 'consultant') NOT NULL DEFAULT 'consultant',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: clients
-- Clientes de la consultora a los que se facturan horas.
-- Clients table. Clients of the consultancy that hours are billed to.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: time_records
-- Registro de horas trabajadas por un consultor para un cliente.
-- Time record entries logged by a consultant against a client.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS time_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    -- El dueño del registro SIEMPRE se toma de la sesión del servidor,
    -- nunca de un valor enviado por el cliente (ver backend/api/records.php).
    -- The record owner is ALWAYS taken from the server session, never
    -- from a client-supplied value (see backend/api/records.php).
    consultant_id   INT NOT NULL,
    client_id       INT NOT NULL,
    work_date       DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    -- Horas facturables, derivadas de start_time/end_time en el backend
    -- (no se confía en un valor de horas enviado suelto por el cliente).
    -- Billable hours, derived from start_time/end_time on the backend
    -- (a loose "hours" value sent by the client is never trusted).
    hours           DECIMAL(4,2) NOT NULL,
    description     VARCHAR(255) NOT NULL DEFAULT '',
    billable        TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_consultant FOREIGN KEY (consultant_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_client     FOREIGN KEY (client_id)     REFERENCES clients(id) ON DELETE RESTRICT,
    INDEX idx_consultant_date (consultant_id, work_date),
    INDEX idx_client_date (client_id, work_date)
) ENGINE=InnoDB;
