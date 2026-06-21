USE GymDB;
GO

INSERT INTO Usuarios
(Nombre, Usuario, Contrasena, Rol)
VALUES
('Abraham Bermudes', 'abraham', '12345', 'Administrador'),
('Rudy Reyes', 'rudy', '12345', 'Administrador'),
('Leopoldo Siwel', 'leopoldo', '12345', 'Empleado'),
('Yubelca', 'yubelca', '12345', 'Empleado'),
('Octavio Delgado', 'octavio', '12345', 'Empleado');
GO

-- VER TODOS LOS USUARIOS REGISTRADOS
SELECT * FROM Usuarios;
GOs