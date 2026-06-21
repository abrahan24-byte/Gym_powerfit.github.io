CREATE DATABASE IF NOT EXISTS powerfit_gym
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE powerfit_gym;

CREATE TABLE IF NOT EXISTS membresias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(60) NOT NULL,
  precio DECIMAL(10, 2) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO membresias (codigo, nombre, precio, descripcion) VALUES
  ('basica', 'Basica', 5.00, 'Acceso al gimnasio en horario regular.'),
  ('estandar', 'Estandar', 25.00, 'Acceso completo mas clases grupales.'),
  ('premium', 'Premium', 40.00, 'Acceso ilimitado, clases y asesoria nutricional.'),
  ('vip', 'VIP', 60.00, 'Entrenador personal y acceso prioritario.')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  precio = VALUES(precio),
  descripcion = VALUES(descripcion);

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) DEFAULT NULL,
  membresia_id INT NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'activa',
  fecha_vencimiento DATE DEFAULT NULL,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_membresias
    FOREIGN KEY (membresia_id) REFERENCES membresias(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  membresia_id INT NOT NULL,
  monto DECIMAL(10, 2) NOT NULL,
  metodo VARCHAR(30) NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  referencia VARCHAR(100) DEFAULT NULL,
  fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pagos_usuarios
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_pagos_membresias
    FOREIGN KEY (membresia_id) REFERENCES membresias(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  fecha_expiracion DATETIME NOT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_resets_usuarios
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL AFTER correo;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'activa' AFTER membresia_id;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS fecha_vencimiento DATE DEFAULT NULL AFTER estado;
ALTER TABLE usuarios MODIFY correo VARCHAR(255) NOT NULL;
UPDATE usuarios SET fecha_vencimiento = DATE_ADD(DATE(fecha_registro), INTERVAL 30 DAY) WHERE fecha_vencimiento IS NULL;
