CREATE DATABASE IF NOT EXISTS TallerMecanico2
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE TallerMecanico2;

-- =========================================================
-- CREACIÓN DE TABLAS BÁSICAS
-- =========================================================
CREATE TABLE Paises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Provincias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50),
    Pais_id INT,
    FOREIGN KEY (Pais_id) REFERENCES Paises(id)
) ENGINE=InnoDB;

CREATE TABLE Localidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50),
    Provincia_id INT,
    FOREIGN KEY (Provincia_id) REFERENCES Provincias(id)
) ENGINE=InnoDB;

CREATE TABLE Barrios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50),
    Localidad_id INT,
    FOREIGN KEY (Localidad_id) REFERENCES Localidades(id)
) ENGINE=InnoDB;

CREATE TABLE Domicilios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Barrio_id INT,
    calle VARCHAR(50),
    altura VARCHAR(10),
    piso VARCHAR(5),
    departamento VARCHAR(5),
    FOREIGN KEY (Barrio_id) REFERENCES Barrios(id)
) ENGINE=InnoDB;

-- =========================================================
-- PERSONAS Y EMPRESAS
-- =========================================================
CREATE TABLE Personas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50),
    apellido VARCHAR(50),
    dni VARCHAR(20),
    contrasenia VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE Clientes (
    Persona_id INT PRIMARY KEY,
    FOREIGN KEY (Persona_id) REFERENCES Personas(id)
) ENGINE=InnoDB;

CREATE TABLE Cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Persona_id INT,
    Cargo_id INT,
    FOREIGN KEY (Persona_id) REFERENCES Personas(id),
    FOREIGN KEY (Cargo_id) REFERENCES Cargos(id)
) ENGINE=InnoDB;

CREATE TABLE Administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Persona_id INT,
    FOREIGN KEY (Persona_id) REFERENCES Personas(id)
) ENGINE=InnoDB;

CREATE TABLE Empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(100),
    CUIT VARCHAR(20)
) ENGINE=InnoDB;

-- =========================================================
-- DOMICILIOS INTERMEDIOS
-- =========================================================
CREATE TABLE Personas_Domicilios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Persona_id INT,
    Domicilio_id INT,
    FOREIGN KEY (Persona_id) REFERENCES Personas(id),
    FOREIGN KEY (Domicilio_id) REFERENCES Domicilios(id)
) ENGINE=InnoDB;

CREATE TABLE Domicilios_Empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Empresas_id INT,
    Domicilio_id INT,
    FOREIGN KEY (Empresas_id) REFERENCES Empresas(id),
    FOREIGN KEY (Domicilio_id) REFERENCES Domicilios(id)
) ENGINE=InnoDB;

-- =========================================================
-- CONTACTOS
-- =========================================================
CREATE TABLE Tipos_Contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Contacto_Persona (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Persona_id INT,
    Tipo_Contacto_id INT,
    valor VARCHAR(100),
    FOREIGN KEY (Persona_id) REFERENCES Personas(id),
    FOREIGN KEY (Tipo_Contacto_id) REFERENCES Tipos_Contactos(id)
) ENGINE=InnoDB;

CREATE TABLE Contactos_Empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Empresas_id INT,
    Tipo_Contacto_id INT,
    valor VARCHAR(100),
    FOREIGN KEY (Empresas_id) REFERENCES Empresas(id),
    FOREIGN KEY (Tipo_Contacto_id) REFERENCES Tipos_Contactos(id)
) ENGINE=InnoDB;

-- =========================================================
-- AUTOMOVILES
-- (cambios mínimos: nuevas columnas en esta tabla)
-- =========================================================
CREATE TABLE Marcas_Automoviles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Modelos_Automoviles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50),
    Marca_Automvil_id INT,
    FOREIGN KEY (Marca_Automvil_id) REFERENCES Marcas_Automoviles(id)
) ENGINE=InnoDB;

CREATE TABLE Automoviles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50),
    anio VARCHAR(10),
    km VARCHAR(20),
    color VARCHAR(20),
    patente VARCHAR(20) NULL,
    descripcion_extra VARCHAR(100) NULL,
    foto_Cedula_Frente  VARCHAR(255) NULL,
    foto_Cedula_Trasera VARCHAR(255) NULL,
    Modelo_Automovil_id INT,
    FOREIGN KEY (Modelo_Automovil_id) REFERENCES Modelos_Automoviles(id)
) ENGINE=InnoDB;

CREATE TABLE Vehiculos_Personas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Persona_id INT,
    automoviles_id INT,
    FOREIGN KEY (Persona_id) REFERENCES Personas(id),
    FOREIGN KEY (automoviles_id) REFERENCES Automoviles(id)
) ENGINE=InnoDB;

CREATE TABLE Vehiculos_Empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Empresas_id INT,
    automoviles_id INT,
    FOREIGN KEY (Empresas_id) REFERENCES Empresas(id),
    FOREIGN KEY (automoviles_id) REFERENCES Automoviles(id)
) ENGINE=InnoDB;

-- =========================================================
-- TURNOS Y ESTADOS
-- =========================================================
USE TallerMecanico2; 
CREATE TABLE Estados_Turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_turno DATE,
    hora_turno TIME,
    Estado_Turno_id INT,
    Automovil_id INT,
    FOREIGN KEY (Estado_Turno_id) REFERENCES Estados_Turnos(id),
    FOREIGN KEY (Automovil_id) REFERENCES Automoviles(id)
) ENGINE=InnoDB;
ALTER TABLE Turnos
  ADD COLUMN motivo VARCHAR(100) NOT NULL AFTER Automovil_id,
  ADD COLUMN descripcion VARCHAR(500) NULL AFTER motivo;

-- =========================================================
-- REPARACIONES
-- =========================================================
CREATE TABLE Estados_Ordenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Repuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Marca VARCHAR(50),
    Modelo VARCHAR(50),
    Codigo VARCHAR(20),
    Descripcion VARCHAR(50),
    Precio FLOAT,
    Stock INT
) ENGINE=InnoDB;

CREATE TABLE Detalles_Presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cantidad INT,
    precio_mano_obra FLOAT,
    Repuesto_id INT,
    FOREIGN KEY (Repuesto_id) REFERENCES Repuestos(id)
) ENGINE=InnoDB;

CREATE TABLE presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(100),
    Detalle_Presupuesto_id INT,
    FOREIGN KEY (Detalle_Presupuesto_id) REFERENCES Detalles_Presupuestos(id)
) ENGINE=InnoDB;

CREATE TABLE Ordenes_Reparaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Automovil_id INT,
    Empleado_id INT,
    presupuesto_id INT,
    EstadoOrdenReparacion_id INT,
    fecha_ingreso DATE,
    descripcion VARCHAR(100),
    FOREIGN KEY (Automovil_id) REFERENCES Automoviles(id),
    FOREIGN KEY (Empleado_id) REFERENCES Empleados(id),
    FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id),
    FOREIGN KEY (EstadoOrdenReparacion_id) REFERENCES Estados_Ordenes(id)
) ENGINE=InnoDB;

-- =========================================================
-- FACTURACIÓN
-- =========================================================
CREATE TABLE TiposPagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE Facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    TipoPago_id INT,
    fechaEmision DATE,
    FOREIGN KEY (TipoPago_id) REFERENCES TiposPagos(id)
) ENGINE=InnoDB;

CREATE TABLE Detalles_Facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Factura_id INT,
    Orden_Reparacion_id INT,
    FOREIGN KEY (Factura_id) REFERENCES Facturas(id),
    FOREIGN KEY (Orden_Reparacion_id) REFERENCES Ordenes_Reparaciones(id)
) ENGINE=InnoDB;

-- =========================================================
-- CARGA DE DATOS
-- =========================================================
INSERT INTO Paises (descripcion) VALUES ('Argentina');
INSERT INTO Provincias (descripcion, Pais_id) VALUES ('Buenos Aires', 1);
INSERT INTO Localidades (descripcion, Provincia_id) VALUES ('La Plata', 1);
INSERT INTO Barrios (descripcion, Localidad_id) VALUES ('Centro', 1);
INSERT INTO Domicilios (Barrio_id, calle, altura, piso, departamento) VALUES
(1, 'Calle 50', '1234', '1', 'A'),
(1, 'Calle 60', '567', NULL, NULL);

INSERT INTO Personas (nombre, apellido, dni, contrasenia) VALUES
('Juan', 'Pérez', '30111222', '1234'),
('María', 'López', '30222333', 'abcd'),
('Carlos', 'Gómez', '30111444', 'pass');

INSERT INTO Clientes (Persona_id) VALUES (1), (2);

INSERT INTO Cargos (descripcion) VALUES ('Mecánico'), ('Recepcionista');
INSERT INTO Empleados (Persona_id, Cargo_id) VALUES (3, 1);

INSERT INTO Empresas (razon_social, CUIT) VALUES ('Transporte SRL', '30-11223344-5');

INSERT INTO Tipos_Contactos (descripcion) VALUES ('Teléfono'), ('Email');
INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES
(1, 1, '221-555123'),
(2, 2, 'maria@mail.com');
INSERT INTO Contactos_Empresas (Empresas_id, Tipo_Contacto_id, valor) VALUES
(1, 1, '221-999888');

INSERT INTO Marcas_Automoviles (descripcion) VALUES ('Fiat'), ('Toyota');
INSERT INTO Modelos_Automoviles (descripcion, Marca_Automvil_id) VALUES
('Punto', 1),
('Corolla', 2);

-- Nuevos campos en Automoviles -> pongo NULL donde no aplique
INSERT INTO Automoviles (descripcion, anio, km, color, patente, descripcion_extra, foto_Cedula_Frente, foto_Cedula_Trasera, Modelo_Automovil_id) VALUES
('Fiat Punto',    '2015', '85000', 'Rojo',  NULL, NULL, NULL, NULL, 1),
('Toyota Corolla','2018', '65000', 'Negro', NULL, NULL, NULL, NULL, 2);

INSERT INTO Vehiculos_Personas (Persona_id, automoviles_id) VALUES
(1, 1),
(2, 2);
INSERT INTO Vehiculos_Empresas (Empresas_id, automoviles_id) VALUES
(1, 2);

INSERT INTO Estados_Turnos (descripcion) VALUES
('pendiente asignacion'),
('asignado'),
('cancelado'),
('terminado');
INSERT INTO Turnos (fecha_turno, hora_turno, Estado_Turno_id, Automovil_id) VALUES
('2025-09-05', '10:00:00', 1, 1),
('2025-09-06', '15:00:00', 2, 2);

INSERT INTO Estados_Ordenes (descripcion) VALUES ('En proceso'), ('Finalizada');

INSERT INTO Repuestos (Marca, Modelo, Codigo, Descripcion, Precio, Stock) VALUES
('Bosch', 'Universal', 'R001', 'Bujía', 2000, 50),
('Philips', 'H4', 'L002', 'Lámpara H4', 1500, 30);

INSERT INTO Detalles_Presupuestos (cantidad, precio_mano_obra, Repuesto_id) VALUES
(4, 5000, 1),
(2, 3000, 2);

INSERT INTO presupuestos (descripcion, Detalle_Presupuesto_id) VALUES
('Cambio de bujías', 1),
('Cambio de lámparas delanteras', 2);

INSERT INTO Ordenes_Reparaciones (Automovil_id, Empleado_id, presupuesto_id, EstadoOrdenReparacion_id, fecha_ingreso, descripcion) VALUES
(1, 1, 1, 1, '2025-09-01', 'Cambio de bujías'),
(2, 1, 2, 2, '2025-09-02', 'Cambio de lámparas');

INSERT INTO TiposPagos (descripcion) VALUES ('Efectivo'), ('Tarjeta');
INSERT INTO Facturas (TipoPago_id, fechaEmision) VALUES
(1, '2025-09-03'),
(2, '2025-09-04');
INSERT INTO Detalles_Facturas (Factura_id, Orden_Reparacion_id) VALUES
(1, 1),
(2, 2);

-- persona y auto
SELECT 
    p.id AS PersonaID,
    p.nombre,
    p.apellido,
    ma.descripcion AS Marca,
    mo.descripcion AS Modelo,
    a.anio,
    a.color
FROM Clientes c
INNER JOIN Personas p ON c.Persona_id = p.id
INNER JOIN Vehiculos_Personas vp ON p.id = vp.Persona_id
INNER JOIN Automoviles a ON vp.automoviles_id = a.id
INNER JOIN Modelos_Automoviles mo ON a.Modelo_Automovil_id = mo.id
INNER JOIN Marcas_Automoviles ma ON mo.Marca_Automvil_id = ma.id;

-- turnos por persona
SELECT 
    t.id AS TurnoID,
    t.fecha_turno,
    t.hora_turno,
    et.descripcion AS EstadoTurno,
    ma.descripcion AS Marca,
    mo.descripcion AS Modelo,
    a.anio,
    a.color,
    p.nombre,
    p.apellido
FROM Turnos t
INNER JOIN Estados_Turnos et ON t.Estado_Turno_id = et.id
INNER JOIN Automoviles a ON t.Automovil_id = a.id
INNER JOIN Modelos_Automoviles mo ON a.Modelo_Automovil_id = mo.id
INNER JOIN Marcas_Automoviles ma ON mo.Marca_Automvil_id = ma.id
INNER JOIN Vehiculos_Personas vp ON a.id = vp.automoviles_id
INNER JOIN Personas p ON vp.Persona_id = p.id;

-- órdenes / factura
SELECT 
    o.id AS OrdenID,
    o.fecha_ingreso,
    o.descripcion AS Trabajo,
    eo.descripcion AS EstadoOrden,
    ma.descripcion AS Marca,
    mo.descripcion AS Modelo,
    a.anio,
    a.color,
    cli.nombre AS ClienteNombre,
    cli.apellido AS ClienteApellido,
    emp.nombre AS EmpleadoNombre,
    emp.apellido AS EmpleadoApellido,
    car.descripcion AS CargoEmpleado
FROM Ordenes_Reparaciones o
INNER JOIN Estados_Ordenes eo ON o.EstadoOrdenReparacion_id = eo.id
INNER JOIN Automoviles a ON o.Automovil_id = a.id
INNER JOIN Modelos_Automoviles mo ON a.Modelo_Automovil_id = mo.id
INNER JOIN Marcas_Automoviles ma ON mo.Marca_Automvil_id = ma.id
INNER JOIN Vehiculos_Personas vp ON a.id = vp.automoviles_id
INNER JOIN Personas cli ON vp.Persona_id = cli.id
INNER JOIN Empleados em ON o.Empleado_id = em.id
INNER JOIN Personas emp ON em.Persona_id = emp.id
INNER JOIN Cargos car ON em.Cargo_id = car.id;

SELECT 
    f.id AS FacturaID,
    f.fechaEmision,
    tp.descripcion AS TipoPago,
    o.descripcion AS Trabajo,
    ma.descripcion AS Marca,
    mo.descripcion AS Modelo,
    a.anio,
    a.color,
    cli.nombre AS ClienteNombre,
    cli.apellido AS ClienteApellido
FROM Facturas f
INNER JOIN TiposPagos tp ON f.TipoPago_id = tp.id
INNER JOIN Detalles_Facturas df ON f.id = df.Factura_id
INNER JOIN Ordenes_Reparaciones o ON df.Orden_Reparacion_id = o.id
INNER JOIN Automoviles a ON o.Automovil_id = a.id
INNER JOIN Modelos_Automoviles mo ON a.Modelo_Automovil_id = mo.id
INNER JOIN Marcas_Automoviles ma ON mo.Marca_Automvil_id = ma.id
INNER JOIN Vehiculos_Personas vp ON a.id = vp.automoviles_id
INNER JOIN Personas cli ON vp.Persona_id = cli.id;

-- Ajustes adicionales que ya usabas
ALTER TABLE Personas
  MODIFY contrasenia VARCHAR(255) NOT NULL;

CREATE TABLE IF NOT EXISTS PasswordResets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  persona_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (persona_id) REFERENCES Personas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Evitar emails duplicados (mismo tipo + mismo valor)
ALTER TABLE Contacto_Persona
  ADD UNIQUE KEY uq_tipo_valor (Tipo_Contacto_id, valor);
  
INSERT INTO Personas (nombre, apellido, dni, contrasenia)
VALUES ('Euge', 'Admin', '46254327', '$2y$10$2wc0Lxevs02iLd8Spz9.6eGLmLTUOM842GVZUlloxR7opeV51Cvge');

INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor)
SELECT p.id, t.id, 'gonzalezeugee14@gmail.com'
FROM Personas p
JOIN Tipos_Contactos t ON t.descripcion = 'Email'
WHERE p.dni = '46254327';

-- Hacer admin
INSERT INTO Administradores (Persona_id)
SELECT id FROM Personas WHERE dni = '46254327';

-- (Opcional) Hacer cliente también
INSERT INTO Clientes (Persona_id)
SELECT id FROM Personas WHERE dni = '46254327';

SELECT p.id, p.nombre, p.apellido
FROM Personas p
JOIN Administradores a ON a.Persona_id = p.id
WHERE p.dni = '46254327';

SELECT p.id, p.nombre, p.apellido
FROM Personas p
JOIN Contacto_Persona cp ON cp.Persona_id = p.id
JOIN Tipos_Contactos t   ON t.id = cp.Tipo_Contacto_id
WHERE p.dni = '46254327' AND t.descripcion='Email';
