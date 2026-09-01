-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 30, 2026 at 03:57 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `evospace`
--

-- --------------------------------------------------------

--
-- Table structure for table `abonos`
--

CREATE TABLE `abonos` (
  `id_abono` int(11) NOT NULL,
  `fecha_abono` date NOT NULL,
  `profesor` varchar(100) NOT NULL,
  `monto_abono` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `abonos`
--

INSERT INTO `abonos` (`id_abono`, `fecha_abono`, `profesor`, `monto_abono`, `descripcion`, `imagen`) VALUES
(2, '2026-07-27', 'jhoan.ramirez', 2500000.00, NULL, NULL),
(3, '2026-07-27', 'Maria Benitez', 2354535.00, NULL, NULL),
(4, '2026-07-27', 'profesor', 2500000.00, NULL, NULL),
(5, '2026-07-28', 'Maria Benitez', 2354535.00, NULL, NULL),
(6, '2026-07-28', 'Maria Benitez', 145465.00, NULL, NULL),
(7, '2026-07-29', 'jhoan.ramirez', 2500000.00, NULL, NULL),
(8, '2026-07-29', 'profesor', 2500000.00, NULL, NULL),
(9, '2026-07-29', 'jhoan.ramirez', 5000000.00, NULL, NULL),
(10, '2026-07-29', 'Maria Benitez', 145465.00, NULL, NULL),
(11, '2026-07-30', 'jhoan.ramirez', 5000000.00, '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `alumnos`
--

CREATE TABLE `alumnos` (
  `id_alumno` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `anio_ingreso` year(4) NOT NULL,
  `horas_profesionales` decimal(6,2) DEFAULT 0.00,
  `ci` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `id_padre` int(11) DEFAULT NULL,
  `becado` tinyint(1) DEFAULT 0,
  `dia_vencimiento` int(11) DEFAULT NULL,
  `dias_gracia` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumnos`
--

INSERT INTO `alumnos` (`id_alumno`, `nombre`, `apellido`, `id_curso`, `anio_ingreso`, `horas_profesionales`, `ci`, `telefono`, `id_padre`, `becado`, `activo`, `fecha_creacion`) VALUES
(1, 'Mariela', 'Nuñez Esteche', 20, '2022', 1312.00, '283892', '254234', NULL, 0, 1, '2026-07-23 01:58:31'),
(2, 'Natan', 'Levy', 1, '2023', 0.00, '123456', '098765', NULL, 1, 1, '2026-07-23 01:58:31'),
(3, 'Clara', 'Vallejos', 9, '2024', 0.00, '654321', '099888', NULL, 0, 1, '2026-07-23 01:58:31'),
(4, 'Jessica', 'Giménez', 18, '2021', 800.00, '111222', '456789', NULL, 1, 1, '2026-07-23 01:58:31'),
(5, 'Carlos', 'Ruiz', 2, '2023', 0.00, '987654', '555123', NULL, 0, 1, '2026-07-23 01:58:31'),
(6, 'Sofía', 'Martínez', 14, '2024', 0.00, '456123', '555456', NULL, 0, 1, '2026-07-23 01:58:31'),
(7, 'Sofía', 'González', 1, '2023', 0.00, '1000001', '0981111111', NULL, 0, 1, '2026-07-26 22:35:43'),
(8, 'Mateo', 'Rodríguez', 2, '2022', 0.00, '1000002', '0982222222', NULL, 1, 1, '2026-07-26 22:35:43'),
(9, 'Valentina', 'López', 3, '2024', 0.00, '1000003', '0983333333', NULL, 0, 1, '2026-07-26 22:35:43'),
(10, 'Tomás', 'Martínez', 4, '2021', 0.00, '1000004', '0984444444', NULL, 0, 1, '2026-07-26 22:35:43'),
(11, 'Camila', 'Pérez', 5, '2023', 0.00, '1000005', '0985555555', 3, 1, 1, '2026-07-26 22:35:43'),
(12, 'Lucas', 'García', 6, '2022', 0.00, '1000006', '0986666666', NULL, 0, 1, '2026-07-26 22:35:43'),
(13, 'Isabella', 'Fernández', 1, '2024', 0.00, '1000007', '0987777777', NULL, 0, 1, '2026-07-26 22:35:43'),
(14, 'Facundo', 'Ramírez', 2, '2021', 0.00, '1000008', '0988888888', NULL, 1, 1, '2026-07-26 22:35:43'),
(15, 'Mía', 'Torres', 3, '2023', 0.00, '1000009', '0989999999', NULL, 0, 1, '2026-07-26 22:35:43'),
(16, 'Benjamín', 'Díaz', 4, '2022', 0.00, '1000010', '0971111111', NULL, 0, 1, '2026-07-26 22:35:43'),
(17, 'Emma', 'Álvarez', 5, '2024', 0.00, '1000011', '0972222222', NULL, 1, 1, '2026-07-26 22:35:43'),
(18, 'Joaquín', 'Romero', 6, '2021', 0.00, '1000012', '0973333333', NULL, 0, 1, '2026-07-26 22:35:43'),
(19, 'Martina', 'Gómez', 1, '2023', 0.00, '1000013', '0974444444', NULL, 0, 1, '2026-07-26 22:35:43'),
(20, 'Santino', 'Benítez', 2, '2022', 0.00, '1000014', '0975555555', NULL, 1, 1, '2026-07-26 22:35:43'),
(21, 'Renata', 'Duarte', 3, '2024', 0.00, '1000015', '0976666666', NULL, 0, 1, '2026-07-26 22:35:43'),
(22, 'Nicolás', 'Sánchez', 4, '2021', 0.00, '1000016', '0977777777', NULL, 0, 1, '2026-07-26 22:35:43'),
(23, 'Julieta', 'Ortiz', 5, '2023', 0.00, '1000017', '0978888888', NULL, 1, 1, '2026-07-26 22:35:43'),
(24, 'Matías', 'Herrera', 6, '2022', 0.00, '1000018', '0979999999', NULL, 0, 1, '2026-07-26 22:35:43'),
(25, 'Daniela', 'Mendoza', 1, '2024', 0.00, '1000019', '0961111111', NULL, 0, 1, '2026-07-26 22:35:43'),
(26, 'Lautaro', 'Paredes', 2, '2021', 0.00, '1000020', '0962222222', NULL, 0, 1, '2026-07-26 22:35:43'),
(27, 'Agustina', 'Luna', 7, '2023', 0.00, '1000021', '0963333333', NULL, 0, 1, '2026-07-26 22:35:43'),
(28, 'Valentino', 'Rojas', 8, '2022', 0.00, '1000022', '0964444444', NULL, 1, 1, '2026-07-26 22:35:43'),
(29, 'Delfina', 'Vázquez', 9, '2024', 0.00, '1000023', '0965555555', NULL, 0, 1, '2026-07-26 22:35:43'),
(30, 'Lorenzo', 'Aguirre', 10, '2021', 0.00, '1000024', '0966666666', NULL, 0, 1, '2026-07-26 22:35:43'),
(31, 'Bianca', 'Moreno', 11, '2023', 0.00, '1000025', '0967777777', NULL, 1, 1, '2026-07-26 22:35:43'),
(32, 'Thiago', 'Cáceres', 12, '2022', 0.00, '1000026', '0968888888', NULL, 0, 1, '2026-07-26 22:35:43'),
(33, 'Catalina', 'Flores', 13, '2024', 0.00, '1000027', '0969999999', NULL, 0, 1, '2026-07-26 22:35:43'),
(34, 'Santiago', 'Giménez', 14, '2021', 0.00, '1000028', '0951111111', NULL, 1, 1, '2026-07-26 22:35:43'),
(35, 'Victoria', 'Godoy', 7, '2023', 0.00, '1000029', '0952222222', NULL, 0, 1, '2026-07-26 22:35:43'),
(36, 'Franco', 'López', 8, '2022', 0.00, '1000030', '0953333333', NULL, 0, 1, '2026-07-26 22:35:43'),
(37, 'Pilar', 'Díaz', 9, '2024', 0.00, '1000031', '0954444444', NULL, 1, 1, '2026-07-26 22:35:43'),
(38, 'Lucas', 'Martín', 10, '2021', 0.00, '1000032', '0955555555', NULL, 0, 1, '2026-07-26 22:35:43'),
(39, 'Alma', 'Pereyra', 11, '2023', 0.00, '1000033', '0956666666', NULL, 0, 1, '2026-07-26 22:35:43'),
(40, 'Bautista', 'Ramos', 12, '2022', 0.00, '1000034', '0957777777', NULL, 1, 1, '2026-07-26 22:35:43'),
(41, 'Lola', 'Acosta', 13, '2024', 0.00, '1000035', '0958888888', NULL, 0, 1, '2026-07-26 22:35:43'),
(42, 'Emilio', 'Silva', 14, '2021', 0.00, '1000036', '0959999999', NULL, 0, 1, '2026-07-26 22:35:43'),
(43, 'Lara', 'García', 7, '2023', 0.00, '1000037', '0941111111', NULL, 0, 1, '2026-07-26 22:35:43'),
(44, 'Santos', 'Sosa', 8, '2022', 0.00, '1000038', '0942222222', NULL, 0, 1, '2026-07-26 22:35:43'),
(45, 'Zoe', 'Martínez', 9, '2024', 0.00, '1000039', '0943333333', NULL, 1, 1, '2026-07-26 22:35:43'),
(46, 'Bruno', 'Fernández', 11, '2021', 0.00, '1000040', '0944444444', NULL, 0, 1, '2026-07-26 22:35:43'),
(47, 'Alan', 'Benítez', 15, '2020', 850.50, '1000041', '0945555555', NULL, 0, 1, '2026-07-26 22:35:43'),
(48, 'Candela', 'Mansilla', 16, '2021', 1200.00, '1000042', '0946666666', NULL, 1, 1, '2026-07-26 22:35:43'),
(49, 'Nicolás', 'Alcaraz', 17, '2022', 950.75, '1000043', '0947777777', NULL, 0, 1, '2026-07-26 22:35:43'),
(50, 'Lucía', 'Báez', 18, '2020', 1400.25, '1000044', '0948888888', NULL, 0, 1, '2026-07-26 22:35:43'),
(51, 'Diego', 'Cardozo', 19, '2023', 1100.00, '1000045', '0949999999', NULL, 1, 1, '2026-07-26 22:35:43'),
(52, 'Sabrina', 'Olmedo', 20, '2021', 1300.50, '1000046', '0931111111', NULL, 0, 1, '2026-07-26 22:35:43'),
(53, 'Federico', 'Godoy', 21, '2022', 800.00, '1000047', '0932222222', NULL, 0, 1, '2026-07-26 22:35:43'),
(54, 'Juliana', 'López', 15, '2020', 1600.00, '1000048', '0933333333', NULL, 1, 1, '2026-07-26 22:35:43'),
(55, 'Maximiliano', 'Pérez', 16, '2023', 900.00, '1000049', '0934444444', 3, 0, 1, '2026-07-26 22:35:43'),
(56, 'Rocío', 'Giménez', 17, '2021', 1450.75, '1000050', '0935555555', NULL, 0, 1, '2026-07-26 22:35:43'),
(57, 'Matías', 'Díaz', 18, '2022', 1150.25, '1000051', '0936666666', 3, 1, 1, '2026-07-26 22:35:43'),
(58, 'Abril', 'Figueredo', 19, '2020', 950.00, '1000052', '0937777777', NULL, 0, 1, '2026-07-26 22:35:43'),
(59, 'Tomás', 'Escobar', 20, '2023', 1200.00, '1000053', '0938888888', NULL, 0, 1, '2026-07-26 22:35:43'),
(60, 'Mía', 'Ramírez', 21, '2021', 1350.50, '1000054', '0939999999', NULL, 1, 1, '2026-07-26 22:35:43'),
(61, 'Ignacio', 'Ojeda', 15, '2022', 750.00, '1000055', '0921111111', NULL, 0, 1, '2026-07-26 22:35:43'),
(62, 'Elena', 'Martín', 16, '2020', 1550.00, '1000056', '0922222222', NULL, 0, 1, '2026-07-26 22:35:43'),
(63, 'Julián', 'Vega', 17, '2023', 1000.00, '1000057', '0923333333', NULL, 1, 1, '2026-07-26 22:35:43'),
(64, 'Malena', 'Soria', 18, '2021', 1250.25, '1000058', '0924444444', NULL, 0, 1, '2026-07-26 22:35:43'),
(65, 'Facundo', 'Pinto', 20, '2022', 880.50, '1000059', '0925555555', NULL, 0, 1, '2026-07-26 22:35:43'),
(66, 'Brenda', 'Córdoba', 21, '2020', 21.00, '1000060', '0926666666', 3, 1, 1, '2026-07-26 22:35:43');

-- --------------------------------------------------------

--
-- Table structure for table `asistencia`
--

CREATE TABLE `asistencia` (
  `id_asistencia` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `presente` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asistencia`
--

INSERT INTO `asistencia` (`id_asistencia`, `id_alumno`, `id_curso`, `fecha`, `presente`, `observaciones`, `fecha_creacion`) VALUES
(52, 13, 1, '2026-07-03', 1, NULL, '2026-07-29 23:16:23'),
(53, 13, 1, '2026-07-06', 1, NULL, '2026-07-29 23:16:23'),
(54, 13, 1, '2026-07-31', 1, NULL, '2026-07-29 23:16:23'),
(55, 19, 1, '2026-07-03', 1, NULL, '2026-07-29 23:16:23'),
(56, 19, 1, '2026-07-06', 1, NULL, '2026-07-29 23:16:23'),
(57, 7, 1, '2026-07-03', 1, NULL, '2026-07-29 23:16:23'),
(58, 7, 1, '2026-07-06', 1, NULL, '2026-07-29 23:16:23'),
(59, 2, 1, '2026-07-03', 1, NULL, '2026-07-29 23:16:23'),
(60, 2, 1, '2026-07-06', 1, NULL, '2026-07-29 23:16:23'),
(61, 25, 1, '2026-07-03', 1, NULL, '2026-07-29 23:16:23'),
(62, 25, 1, '2026-07-06', 1, NULL, '2026-07-29 23:16:23'),
(63, 2, 1, '2026-07-30', 1, '', '2026-07-30 01:43:06'),
(64, 7, 1, '2026-07-30', 0, '', '2026-07-30 01:43:06'),
(65, 13, 1, '2026-07-30', 1, '', '2026-07-30 01:43:06'),
(66, 19, 1, '2026-07-30', 1, '', '2026-07-30 01:43:06'),
(67, 25, 1, '2026-07-30', 0, '', '2026-07-30 01:43:06');

-- --------------------------------------------------------

--
-- Table structure for table `compras_alumnos`
--

CREATE TABLE `compras_alumnos` (
  `id_compra` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `producto` varchar(100) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `pagado` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compras_proveedores`
--

CREATE TABLE `compras_proveedores` (
  `id_compra` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(50) NOT NULL,
  `valor` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `configuracion`
--

INSERT INTO `configuracion` (`clave`, `valor`) VALUES
('dia_limite_pago', '10'),
('limite_horas_profesionales', '200'),
('porcentaje_beca', '50'),
('recargo_por_dia', '1000'),
('recibo_logo', ''),
('recibo_mensaje', 'Gracias por confiar en EvoSpace'),
('recibo_nombre', 'EvoSpace'),
('recibo_pie', 'Este documento es un comprobante de pago válido'),
('recibo_ruc', '12345678-0'),
('recibo_titulo', 'Academia de Artes Escénicas');

-- --------------------------------------------------------

--
-- Table structure for table `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('Acrotelas','Infantil','Superior') NOT NULL,
  `orden` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `cupo_maximo` int(11) DEFAULT NULL COMMENT 'NULL = sin limite'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre`, `tipo`, `orden`, `activo`, `cupo_maximo`) VALUES
(1, 'Inicial', 'Acrotelas', 1, 1, 14),
(2, 'Primer Curso', 'Acrotelas', 2, 1, NULL),
(3, 'Segundo Curso', 'Acrotelas', 3, 1, NULL),
(4, 'Tercer Curso', 'Acrotelas', 4, 1, NULL),
(5, 'Cuarto Curso', 'Acrotelas', 5, 1, NULL),
(6, 'Quinto Curso', 'Acrotelas', 6, 1, NULL),
(7, 'Nivel Inicial I', 'Infantil', 1, 1, NULL),
(8, 'Nivel Inicial II', 'Infantil', 2, 1, NULL),
(9, 'Primer Grado', 'Infantil', 3, 1, NULL),
(10, 'Segundo Grado', 'Infantil', 4, 1, NULL),
(11, 'Tercer Grado', 'Infantil', 5, 1, NULL),
(12, 'Cuarto Grado', 'Infantil', 6, 1, NULL),
(13, 'Quinto Grado', 'Infantil', 7, 1, NULL),
(14, 'Sexto Grado', 'Infantil', 8, 1, NULL),
(15, 'Principiante Superior', 'Superior', 1, 1, NULL),
(16, 'Preparatorio Superior', 'Superior', 2, 1, NULL),
(17, 'Primer Curso', 'Superior', 3, 1, NULL),
(18, 'Segundo Curso', 'Superior', 4, 1, NULL),
(19, 'Tercer Curso', 'Superior', 5, 1, NULL),
(20, 'Cuarto Curso', 'Superior', 6, 1, NULL),
(21, 'Quinto Curso', 'Superior', 7, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `detalle_compra_proveedor`
--

CREATE TABLE `detalle_compra_proveedor` (
  `id_detalle` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id_detalle`, `id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 1, 20000.00, 20000.00),
(2, 1, 1, 1, 20000.00, 20000.00),
(3, 1, 1, 1, 20000.00, 20000.00),
(4, 2, 1, 1, 20000.00, 20000.00),
(5, 3, 1, 1, 20000.00, 20000.00),
(6, 4, 1, 1, 20000.00, 20000.00),
(7, 5, 1, 1, 20000.00, 20000.00),
(8, 5, 1, 1, 20000.00, 20000.00),
(9, 5, 1, 1, 20000.00, 20000.00),
(12, 7, 1, 6, 20000.00, 120000.00),
(13, 8, 3, 3, 3000.00, 9000.00),
(14, 9, 3, 4, 3000.00, 12000.00),
(15, 10, 3, 1, 3000.00, 3000.00),
(16, 11, 3, 6, 3000.00, 18000.00),
(17, 12, 4, 1, 5000.00, 5000.00),
(18, 13, 4, 20, 5000.00, 100000.00),
(19, 14, 4, 30, 5000.00, 150000.00),
(20, 14, 3, 1, 3000.00, 3000.00),
(21, 15, 3, 1, 3000.00, 3000.00);

-- --------------------------------------------------------

--
-- Table structure for table `eventos`
--

CREATE TABLE `eventos` (
  `id_evento` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `lugar` varchar(200) DEFAULT NULL,
  `enlace_ubicacion` varchar(255) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#c81015',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `imagen` varchar(255) DEFAULT NULL,
  `ultimo_recordatorio` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `eventos`
--

INSERT INTO `eventos` (`id_evento`, `titulo`, `descripcion`, `fecha`, `hora`, `lugar`, `enlace_ubicacion`, `color`, `fecha_creacion`, `imagen`) VALUES
(2, 'Festival para el dia de mañana', 'Llevar ropa adecuada', '2026-07-29', '12:40:00', 'Teatro', 'https://maps.app.goo.gl/h3cDEHZ6ZeE27swW6', '#c61010', '2026-07-29 02:41:12', NULL),
(3, 'Festival para el dia de mañana', 'JASDF', '2026-07-30', '09:37:00', NULL, 'https://maps.app.goo.gl/h3cDEHZ6ZeE27swW6', '#ffffff', '2026-07-29 12:36:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evento_curso`
--

CREATE TABLE `evento_curso` (
  `id_evento` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evento_curso`
--

INSERT INTO `evento_curso` (`id_evento`, `id_curso`) VALUES
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(2, 21),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(3, 12),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 17),
(3, 18),
(3, 19),
(3, 20),
(3, 21);

-- --------------------------------------------------------

--
-- Table structure for table `horarios`
--

CREATE TABLE `horarios` (
  `id_horario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_profesor` int(11) DEFAULT NULL,
  `dia_semana` varchar(20) NOT NULL DEFAULT '1',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `horarios`
--

INSERT INTO `horarios` (`id_horario`, `id_curso`, `id_profesor`, `dia_semana`, `hora_inicio`, `hora_fin`) VALUES
(1, 1, 3, '5', '21:40:00', '22:15:00'),
(2, 1, 3, '1', '19:30:00', '20:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `horas_profesionales_log`
--

CREATE TABLE `horas_profesionales_log` (
  `id_log` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `horas` decimal(6,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `horas_profesionales_log`
--

INSERT INTO `horas_profesionales_log` (`id_log`, `id_alumno`, `horas`, `descripcion`, `fecha`, `fecha_creacion`) VALUES
(1, 66, 20.00, NULL, '2026-07-30', '2026-07-29 23:21:57'),
(2, 66, 1.00, NULL, '2026-07-30', '2026-07-29 23:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `tipo` enum('evento','pago','general') DEFAULT 'evento',
  `leida` tinyint(1) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notificaciones`
--

INSERT INTO `notificaciones` (`id_notificacion`, `id_evento`, `id_usuario`, `titulo`, `mensaje`, `tipo`, `leida`, `fecha`) VALUES
(43, 2, 3, 'Nuevo evento: Festival para el dia de mañana', 'Llevar ropa adecuada', 'evento', 1, '2026-07-29 02:41:17'),
(44, 3, 3, 'Nuevo evento: Festival para el dia de mañana', 'JASDF', 'evento', 1, '2026-07-29 12:36:48'),
(45, 3, 3, 'Nuevo evento: Festival para el dia de mañana', 'JASDF', 'evento', 0, '2026-07-30 00:00:38'),
(46, 3, 3, 'Festival para el dia de mañana', 'JASDF', 'evento', 0, '2026-07-30 00:06:18'),
(47, 3, 3, 'Festival para el dia de mañana', 'JASDF', 'evento', 0, '2026-07-30 00:06:39');

-- --------------------------------------------------------

--
-- Table structure for table `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `id_evento` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `monto` decimal(10,2) NOT NULL,
  `descuento` decimal(5,2) DEFAULT 0.00,
  `recargo` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` enum('Efectivo','Transferencia','Tarjeta','Otro') DEFAULT 'Efectivo',
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pagos`
--

INSERT INTO `pagos` (`id_pago`, `id_alumno`, `fecha`, `concepto`, `cantidad`, `monto`, `descuento`, `recargo`, `total`, `metodo_pago`, `descripcion`, `imagen`, `fecha_creacion`) VALUES
(1, 41, '2026-07-26', 'cuota', 1, 220000.00, 0.00, 15000.00, 235000.00, 'Efectivo', NULL, NULL, '2026-07-27 02:13:53'),
(2, 41, '2026-07-27', 'cuota', 1, 220000.00, 0.00, 16000.00, 236000.00, 'Efectivo', NULL, NULL, '2026-07-27 23:01:50'),
(3, 17, '2026-07-28', 'cuota', 1, 91000.00, 0.00, 17000.00, 108000.00, 'Efectivo', NULL, NULL, '2026-07-28 19:57:07'),
(4, 57, '2026-07-28', 'cuota', 1, 114000.00, 0.00, 17000.00, 131000.00, 'Efectivo', NULL, NULL, '2026-07-28 19:58:07'),
(5, 41, '2026-07-28', 'cuota', 1, 220000.00, 0.00, 17000.00, 237000.00, 'Efectivo', NULL, NULL, '2026-07-29 02:32:10'),
(6, 57, '2026-07-28', 'cuota', 1, 114000.00, 0.00, 17000.00, 131000.00, 'Efectivo', NULL, NULL, '2026-07-29 02:39:45'),
(7, 41, '2026-07-29', 'cuota', 1, 220000.00, 0.00, 18000.00, 238000.00, 'Efectivo', NULL, NULL, '2026-07-29 12:32:39'),
(8, 17, '2026-07-29', 'cuota', 1, 91000.00, 0.00, 18000.00, 109000.00, 'Efectivo', NULL, NULL, '2026-07-29 12:32:56'),
(9, 46, '2026-07-29', 'cuota', 1, 220000.00, 0.00, 19000.00, 239000.00, 'Efectivo', 'Pago de cuota', NULL, '2026-07-29 22:00:16'),
(10, 1, '2026-07-30', 'cuota', 1, 250000.00, 0.00, 20000.00, 270000.00, 'Efectivo', NULL, NULL, '2026-07-29 22:12:24'),
(11, 66, '2026-07-30', 'cuota', 1, 114000.00, 0.00, 20000.00, 134000.00, 'Efectivo', NULL, NULL, '2026-07-29 22:26:59');

-- --------------------------------------------------------

--
-- Table structure for table `pagos_alumnos_cantina`
--

CREATE TABLE `pagos_alumnos_cantina` (
  `id_pago` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pagos_alumnos_cantina`
--

INSERT INTO `pagos_alumnos_cantina` (`id_pago`, `id_alumno`, `fecha`, `monto`, `fecha_creacion`) VALUES
(1, 1, '2026-07-28', 120000.00, '2026-07-28 21:36:18'),
(2, 1, '2026-07-28', 120000.00, '2026-07-28 21:36:36'),
(3, 1, '2026-07-28', 120000.00, '2026-07-28 21:38:31'),
(4, 17, '2026-07-28', 9000.00, '2026-07-28 21:38:38'),
(5, 18, '2026-07-29', 1500.00, '2026-07-29 02:33:58'),
(6, 32, '2026-07-29', 18000.00, '2026-07-29 12:33:59'),
(7, 1, '2026-07-30', 5000.00, '2026-07-29 23:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `pagos_proveedores`
--

CREATE TABLE `pagos_proveedores` (
  `id_pago` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `concepto` varchar(200) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permisos`
--

CREATE TABLE `permisos` (
  `id_permiso` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permisos`
--

INSERT INTO `permisos` (`id_permiso`, `nombre`, `descripcion`) VALUES
(13, 'gestionar_usuarios', 'Crear/editar/eliminar usuarios'),
(18, 'alumnos', 'Ver y editar alumnos'),
(19, 'pagos', 'Ver y editar pagos'),
(20, 'profesores', 'Ver y editar profesores'),
(21, 'eventos', 'Ver y editar eventos'),
(22, 'cantina', 'Ver y editar cantina'),
(23, 'asistencia', 'Ver y editar asistencia'),
(24, 'configuracion', 'Ver y editar configuración'),
(25, 'usuarios', 'Gestionar usuarios'),
(27, 'horarios', 'Ver y editar horarios de cursos');

-- --------------------------------------------------------

--
-- Table structure for table `precios`
--

CREATE TABLE `precios` (
  `id_precio` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `concepto` varchar(50) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `descuento_beca` decimal(5,2) DEFAULT 0.00,
  `aplica_beca` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `precios`
--

INSERT INTO `precios` (`id_precio`, `id_curso`, `concepto`, `precio`, `descuento_beca`, `aplica_beca`) VALUES
(1, 1, 'matrícula', 150000.00, 0.00, 0),
(2, 2, 'matrícula', 150000.00, 0.00, 0),
(3, 3, 'matrícula', 150000.00, 0.00, 0),
(4, 4, 'matrícula', 150000.00, 0.00, 0),
(5, 5, 'matrícula', 150000.00, 0.00, 0),
(6, 6, 'matrícula', 150000.00, 0.00, 0),
(8, 1, 'cuota', 200000.00, 45.45, 1),
(9, 2, 'cuota', 200000.00, 45.45, 1),
(10, 3, 'cuota', 200000.00, 45.45, 1),
(11, 4, 'cuota', 200000.00, 45.45, 1),
(12, 5, 'cuota', 200000.00, 45.45, 1),
(13, 6, 'cuota', 200000.00, 45.45, 1),
(15, 1, 'vestuarios', 150000.00, 0.00, 0),
(16, 2, 'vestuarios', 150000.00, 0.00, 0),
(17, 3, 'vestuarios', 150000.00, 0.00, 0),
(18, 4, 'vestuarios', 150000.00, 0.00, 0),
(19, 5, 'vestuarios', 150000.00, 0.00, 0),
(20, 6, 'vestuarios', 150000.00, 0.00, 0),
(22, 1, 'entradas', 80000.00, 0.00, 0),
(23, 2, 'entradas', 80000.00, 0.00, 0),
(24, 3, 'entradas', 80000.00, 0.00, 0),
(25, 4, 'entradas', 80000.00, 0.00, 0),
(26, 5, 'entradas', 80000.00, 0.00, 0),
(27, 6, 'entradas', 80000.00, 0.00, 0),
(29, 7, 'matrícula', 180000.00, 0.00, 0),
(30, 8, 'matrícula', 180000.00, 0.00, 0),
(31, 9, 'matrícula', 180000.00, 0.00, 0),
(32, 10, 'matrícula', 180000.00, 0.00, 0),
(33, 11, 'matrícula', 180000.00, 0.00, 0),
(34, 12, 'matrícula', 180000.00, 0.00, 0),
(35, 13, 'matrícula', 180000.00, 0.00, 0),
(36, 14, 'matrícula', 180000.00, 0.00, 0),
(44, 7, 'cuota', 220000.00, 45.45, 1),
(45, 8, 'cuota', 220000.00, 45.45, 1),
(46, 9, 'cuota', 220000.00, 45.45, 1),
(47, 10, 'cuota', 220000.00, 45.45, 1),
(48, 11, 'cuota', 220000.00, 45.45, 1),
(49, 12, 'cuota', 220000.00, 45.45, 1),
(50, 13, 'cuota', 220000.00, 45.45, 1),
(51, 14, 'cuota', 220000.00, 45.45, 1),
(59, 7, 'vestuarios', 150000.00, 0.00, 0),
(60, 8, 'vestuarios', 150000.00, 0.00, 0),
(61, 9, 'vestuarios', 150000.00, 0.00, 0),
(62, 10, 'vestuarios', 150000.00, 0.00, 0),
(63, 11, 'vestuarios', 150000.00, 0.00, 0),
(64, 12, 'vestuarios', 150000.00, 0.00, 0),
(65, 13, 'vestuarios', 150000.00, 0.00, 0),
(66, 14, 'vestuarios', 150000.00, 0.00, 0),
(74, 7, 'entradas', 80000.00, 0.00, 0),
(75, 8, 'entradas', 80000.00, 0.00, 0),
(76, 9, 'entradas', 80000.00, 0.00, 0),
(77, 10, 'entradas', 80000.00, 0.00, 0),
(78, 11, 'entradas', 80000.00, 0.00, 0),
(79, 12, 'entradas', 80000.00, 0.00, 0),
(80, 13, 'entradas', 80000.00, 0.00, 0),
(81, 14, 'entradas', 80000.00, 0.00, 0),
(89, 7, 'folleto', 25000.00, 0.00, 0),
(90, 8, 'folleto', 25000.00, 0.00, 0),
(91, 9, 'folleto', 25000.00, 0.00, 0),
(92, 10, 'folleto', 25000.00, 0.00, 0),
(93, 11, 'folleto', 25000.00, 0.00, 0),
(94, 12, 'folleto', 25000.00, 0.00, 0),
(95, 13, 'folleto', 25000.00, 0.00, 0),
(96, 14, 'folleto', 25000.00, 0.00, 0),
(104, 15, 'matrícula', 180000.00, 0.00, 0),
(105, 16, 'matrícula', 180000.00, 0.00, 0),
(106, 17, 'matrícula', 180000.00, 0.00, 0),
(107, 18, 'matrícula', 180000.00, 0.00, 0),
(108, 19, 'matrícula', 180000.00, 0.00, 0),
(109, 20, 'matrícula', 180000.00, 0.00, 0),
(110, 21, 'matrícula', 180000.00, 0.00, 0),
(111, 15, 'cuota', 250000.00, 45.45, 1),
(112, 16, 'cuota', 250000.00, 45.45, 1),
(113, 17, 'cuota', 250000.00, 45.45, 1),
(114, 18, 'cuota', 250000.00, 45.45, 1),
(115, 19, 'cuota', 250000.00, 45.45, 1),
(116, 20, 'cuota', 250000.00, 45.45, 1),
(117, 21, 'cuota', 250000.00, 45.45, 1),
(118, 15, 'vestuarios', 150000.00, 0.00, 0),
(119, 16, 'vestuarios', 150000.00, 0.00, 0),
(120, 17, 'vestuarios', 150000.00, 0.00, 0),
(121, 18, 'vestuarios', 150000.00, 0.00, 0),
(122, 19, 'vestuarios', 150000.00, 0.00, 0),
(123, 20, 'vestuarios', 150000.00, 0.00, 0),
(124, 21, 'vestuarios', 150000.00, 0.00, 0),
(125, 15, 'entradas', 80000.00, 0.00, 0),
(126, 16, 'entradas', 80000.00, 0.00, 0),
(127, 17, 'entradas', 80000.00, 0.00, 0),
(128, 18, 'entradas', 80000.00, 0.00, 0),
(129, 19, 'entradas', 80000.00, 0.00, 0),
(130, 20, 'entradas', 80000.00, 0.00, 0),
(131, 21, 'entradas', 80000.00, 0.00, 0),
(132, 15, 'folleto', 0.00, 0.00, 0),
(133, 16, 'folleto', 0.00, 0.00, 0),
(134, 17, 'folleto', 0.00, 0.00, 0),
(135, 18, 'folleto', 0.00, 0.00, 0),
(136, 19, 'folleto', 0.00, 0.00, 0),
(137, 20, 'folleto', 0.00, 0.00, 0),
(138, 21, 'folleto', 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `precio_compra` decimal(10,2) DEFAULT 0.00,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `id_proveedor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `productos`
--

INSERT INTO `productos` (`id_producto`, `nombre`, `categoria`, `precio`, `activo`, `fecha_creacion`, `precio_compra`, `cantidad`, `id_proveedor`) VALUES
(1, 'Papas a la crema', 'Snacks', 20000.00, 1, '2026-07-26 17:41:32', 15000.00, 0, 1),
(2, 'Papas fritas', 'Snacks', 123.00, 1, '2026-07-26 17:45:49', 0.00, 0, NULL),
(3, 'Papas a la crema2', 'Snacks', 3000.00, 1, '2026-07-28 21:29:57', 1300.00, 14, 1),
(4, 'Bolsa de papas', 'Snacks', 5000.00, 1, '2026-07-29 12:34:40', 3500.00, 49, 1);

-- --------------------------------------------------------

--
-- Table structure for table `profesores`
--

CREATE TABLE `profesores` (
  `id_profesor` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `salario_base` decimal(10,2) DEFAULT NULL,
  `fecha_contratacion` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profesores`
--

INSERT INTO `profesores` (`id_profesor`, `id_usuario`, `salario_base`, `fecha_contratacion`, `activo`) VALUES
(1, 2, 5000000.00, '2026-07-26', 1),
(2, 5, 15000000.00, '2026-07-26', 1),
(3, 6, 5000000.00, '2026-07-26', 1);

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `nombre_contacto` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `tipo_productos` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `nombre`, `activo`, `nombre_contacto`, `telefono`, `whatsapp`, `email`, `direccion`, `tipo_productos`) VALUES
(1, 'Carlita', 1, 'aasdf', '0926666666', '961751338', 'maradsf@gmail.com', 'asdf', 'Bebidas');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'admin', 'Administrador con acceso total'),
(2, 'profesor', 'Profesor con acceso limitado a alumnos y asistencia'),
(3, 'padre', 'Padre con acceso solo a sus hijos'),
(4, 'auxiliar', 'Auxiliar con acceso a listas de alumnos y asistencia');

-- --------------------------------------------------------

--
-- Table structure for table `rol_permiso`
--

CREATE TABLE `rol_permiso` (
  `id_rol` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rol_permiso`
--

INSERT INTO `rol_permiso` (`id_rol`, `id_permiso`) VALUES
(1, 13);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre_completo` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `dia_cobro` tinyint(4) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `usuario`, `email`, `cedula`, `password_hash`, `id_rol`, `nombre_completo`, `telefono`, `activo`, `dia_cobro`, `fecha_creacion`) VALUES
(1, 'admin', 'admin@evospace.com', '1234567', '$2y$10$LBSyD2UFwBLJA/G1i4CRh.pVZJ/q/n2zhkSGNilT5OxM6IK3ccyBC', 1, 'Administrador', NULL, 1, NULL, '2026-07-23 01:58:31'),
(2, 'profesor', 'profe@evospace.com', '2345678', '$2y$10$0PZciBLEdsMtmyGGvdhqZ.z4v9gM8BzW58AWsHTTgehmRdn.iR5VS', 2, 'Profesor Ejemplo', NULL, 1, NULL, '2026-07-23 01:58:31'),
(3, 'padre', 'villoan73@gmail.com', '3456789', '$2y$10$MEtWjyh39YEj62xf0fCsrebtyVdBmdwBzz8gwh.1/4WmjMj0C6zIi', 3, 'Padre Ejemplo', NULL, 1, NULL, '2026-07-23 01:58:31'),
(5, 'jhoan.ramirez', '', '7007909', '$2y$10$.dgzoYtUKLxS4q/szOodEeubeh0dboBo9KeaMgDS90JDxazsmAXv2', 2, 'Jhoan Ramirez', NULL, 1, NULL, '2026-07-26 17:05:36'),
(6, 'Maria Benitez', 'maradsf@gmail.com', '123', '$2y$10$36bXBybs.APGTb5L0v9vMuC7JYpvMCwDWpKIui39ff5.sW5bxW.QK', 2, 'Maria Benitez', NULL, 1, NULL, '2026-07-26 20:59:55'),
(8, 'cantinero', 'maradsf@gaamail.com', '123123123', '$2y$10$o.hINDQDrKyQKRTP3qjJaeHLgN4eltq06LZT8HEJhL9Qdxhsdflrq', 4, 'cantinero', NULL, 1, NULL, '2026-07-29 12:41:28');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios_permisos`
--

CREATE TABLE `usuarios_permisos` (
  `id_usuario` int(11) NOT NULL,
  `permiso` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios_permisos`
--

INSERT INTO `usuarios_permisos` (`id_usuario`, `permiso`) VALUES
(2, 'alumnos'),
(2, 'asistencia'),
(2, 'eventos'),
(2, 'pagos'),
(2, 'usuarios'),
(5, 'alumnos'),
(5, 'asistencia'),
(5, 'eventos'),
(5, 'pagos'),
(6, 'alumnos'),
(6, 'asistencia'),
(6, 'eventos'),
(6, 'pagos'),
(8, 'cantina'),
(8, 'pagos');

-- --------------------------------------------------------

--
-- Table structure for table `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('Efectivo','Transferencia','Fiado') NOT NULL,
  `tipo_comprador` enum('alumno','profesor','otro') DEFAULT 'otro',
  `nombre_comprador` varchar(150) DEFAULT NULL,
  `id_alumno` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado_pago` enum('pagado','pendiente','parcial') DEFAULT 'pagado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entradas_curso` (
  `id_entrada_curso` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_evento` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `precio` decimal(10,0) NOT NULL DEFAULT 0,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('activa','cerrada') NOT NULL DEFAULT 'activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entradas_alumno` (
  `id_entrada_alumno` int(11) NOT NULL,
  `id_entrada_curso` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `fecha_entrega` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ventas`
--

INSERT INTO `ventas` (`id_venta`, `fecha`, `total`, `monto_pagado`, `metodo_pago`, `tipo_comprador`, `nombre_comprador`, `id_alumno`, `id_usuario`, `observaciones`, `estado_pago`) VALUES
(1, '2026-07-26 14:45:04', 60000.00, 60000.00, 'Fiado', 'otro', NULL, NULL, NULL, '', 'pagado'),
(2, '2026-07-27 00:00:00', 20000.00, 20000.00, 'Efectivo', 'alumno', 'Mariela Nuñez Esteche', 1, NULL, '', 'pagado'),
(3, '2026-07-27 00:00:00', 20000.00, 20000.00, 'Efectivo', 'alumno', 'Mariela Nuñez Esteche', 1, NULL, '', 'pagado'),
(4, '2026-07-27 00:00:00', 20000.00, 20000.00, 'Efectivo', 'alumno', 'Mariela Nuñez Esteche', 1, NULL, '', 'pagado'),
(5, '2026-07-28 00:00:00', 60000.00, 60000.00, 'Fiado', 'alumno', 'Joaquín Romero', 18, NULL, '', 'pagado'),
(7, '2026-07-28 00:00:00', 120000.00, 120000.00, 'Fiado', 'alumno', 'Mariela Nuñez Esteche', 1, NULL, '', 'pagado'),
(8, '2026-07-28 00:00:00', 9000.00, 9000.00, 'Fiado', 'alumno', 'Emma Álvarez', 17, NULL, '', 'pagado'),
(9, '2026-07-29 00:00:00', 12000.00, 12000.00, 'Efectivo', 'alumno', 'Brenda Córdoba', 66, NULL, '', 'pagado'),
(10, '2026-07-29 00:00:00', 3000.00, 3000.00, 'Efectivo', 'alumno', 'Joaquín Romero', 18, NULL, '', 'pagado'),
(11, '2026-07-29 00:00:00', 18000.00, 18000.00, 'Fiado', 'alumno', 'Thiago Cáceres', 32, NULL, '', 'pagado'),
(12, '2026-07-30 00:00:00', 5000.00, 5000.00, 'Fiado', 'alumno', 'Mariela Nuñez Esteche', 1, NULL, '', 'pagado'),
(13, '2026-07-30 00:00:00', 100000.00, 100000.00, 'Fiado', 'alumno', 'Joaquín Romero', 18, NULL, '', 'pagado'),
(14, '2026-07-30 00:00:00', 153000.00, 153000.00, 'Efectivo', 'alumno', 'Tomás Escobar', 59, NULL, '', 'pagado'),
(15, '2026-07-30 00:00:00', 3000.00, 3000.00, 'Fiado', 'alumno', 'Jessica Giménez', 4, NULL, '', 'pagado');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `abonos`
--
ALTER TABLE `abonos`
  ADD PRIMARY KEY (`id_abono`);

--
-- Indexes for table `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id_alumno`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_padre` (`id_padre`);

--
-- Indexes for table `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `id_alumno` (`id_alumno`,`fecha`),
  ADD KEY `id_curso` (`id_curso`);

--
-- Indexes for table `compras_alumnos`
--
ALTER TABLE `compras_alumnos`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `id_alumno` (`id_alumno`);

--
-- Indexes for table `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `id_proveedor` (`id_proveedor`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

--
-- Indexes for table `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indexes for table `detalle_compra_proveedor`
--
ALTER TABLE `detalle_compra_proveedor`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_compra` (`id_compra`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indexes for table `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indexes for table `entradas_alumno`
--
ALTER TABLE `entradas_alumno`
  ADD PRIMARY KEY (`id_entrada_alumno`),
  ADD UNIQUE KEY `uq_entrada_alumno` (`id_entrada_curso`, `id_alumno`),
  ADD KEY `id_entrada_curso` (`id_entrada_curso`),
  ADD KEY `id_alumno` (`id_alumno`);

--
-- Indexes for table `entradas_curso`
--
ALTER TABLE `entradas_curso`
  ADD PRIMARY KEY (`id_entrada_curso`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_evento` (`id_evento`);

--
-- Indexes for table `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id_evento`);

--
-- Indexes for table `evento_curso`
--
ALTER TABLE `evento_curso`
  ADD PRIMARY KEY (`id_evento`,`id_curso`),
  ADD KEY `id_curso` (`id_curso`);

--
-- Indexes for table `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_profesor` (`id_profesor`);

--
-- Indexes for table `horas_profesionales_log`
--
ALTER TABLE `horas_profesionales_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_alumno` (`id_alumno`);

--
-- Indexes for table `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `id_evento` (`id_evento`),
  ADD KEY `fk_notificaciones_usuario` (`id_usuario`);

--
-- Indexes for table `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_alumno` (`id_alumno`),
  ADD KEY `id_evento` (`id_evento`);

--
-- Indexes for table `pagos_alumnos_cantina`
--
ALTER TABLE `pagos_alumnos_cantina`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_alumno` (`id_alumno`);

--
-- Indexes for table `pagos_proveedores`
--
ALTER TABLE `pagos_proveedores`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_proveedor` (`id_proveedor`);

--
-- Indexes for table `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id_permiso`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `precios`
--
ALTER TABLE `precios`
  ADD PRIMARY KEY (`id_precio`),
  ADD UNIQUE KEY `id_curso` (`id_curso`,`concepto`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `id_proveedor` (`id_proveedor`);

--
-- Indexes for table `profesores`
--
ALTER TABLE `profesores`
  ADD PRIMARY KEY (`id_profesor`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD PRIMARY KEY (`id_rol`,`id_permiso`),
  ADD KEY `id_permiso` (`id_permiso`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Indexes for table `usuarios_permisos`
--
ALTER TABLE `usuarios_permisos`
  ADD PRIMARY KEY (`id_usuario`,`permiso`);

--
-- Indexes for table `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `abonos`
--
ALTER TABLE `abonos`
  MODIFY `id_abono` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id_alumno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `compras_alumnos`
--
ALTER TABLE `compras_alumnos`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `detalle_compra_proveedor`
--
ALTER TABLE `detalle_compra_proveedor`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `entradas_alumno`
--
ALTER TABLE `entradas_alumno`
  MODIFY `id_entrada_alumno` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entradas_curso`
--
ALTER TABLE `entradas_curso`
  MODIFY `id_entrada_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `horas_profesionales_log`
--
ALTER TABLE `horas_profesionales_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `pagos_alumnos_cantina`
--
ALTER TABLE `pagos_alumnos_cantina`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pagos_proveedores`
--
ALTER TABLE `pagos_proveedores`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `precios`
--
ALTER TABLE `precios`
  MODIFY `id_precio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `profesores`
--
ALTER TABLE `profesores`
  MODIFY `id_profesor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `alumnos_ibfk_2` FOREIGN KEY (`id_padre`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Constraints for table `asistencia`
--
ALTER TABLE `asistencia`
  ADD CONSTRAINT `asistencia_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE,
  ADD CONSTRAINT `asistencia_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`);

--
-- Constraints for table `compras_alumnos`
--
ALTER TABLE `compras_alumnos`
  ADD CONSTRAINT `compras_alumnos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE;

--
-- Constraints for table `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD CONSTRAINT `compras_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`);

--
-- Constraints for table `detalle_compra_proveedor`
--
ALTER TABLE `detalle_compra_proveedor`
  ADD CONSTRAINT `detalle_compra_proveedor_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras_proveedores` (`id_compra`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_compra_proveedor_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE;

--
-- Constraints for table `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`);

--
-- Constraints for table `evento_curso`
--
ALTER TABLE `evento_curso`
  ADD CONSTRAINT `evento_curso_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE CASCADE,
  ADD CONSTRAINT `evento_curso_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Constraints for table `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `horarios_ibfk_2` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id_profesor`) ON DELETE SET NULL;

--
-- Constraints for table `horas_profesionales_log`
--
ALTER TABLE `horas_profesionales_log`
  ADD CONSTRAINT `horas_profesionales_log_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE;

--
-- Constraints for table `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE CASCADE;

--
-- Constraints for table `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_evento_fk` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE SET NULL;

--
-- Constraints for table `pagos_alumnos_cantina`
--
ALTER TABLE `pagos_alumnos_cantina`
  ADD CONSTRAINT `pagos_alumnos_cantina_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE;

--
-- Constraints for table `entradas_alumno`
--
ALTER TABLE `entradas_alumno`
  ADD CONSTRAINT `entradas_alumno_alumno_fk` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE,
  ADD CONSTRAINT `entradas_alumno_curso_fk` FOREIGN KEY (`id_entrada_curso`) REFERENCES `entradas_curso` (`id_entrada_curso`) ON DELETE CASCADE;

--
-- Constraints for table `entradas_curso`
--
ALTER TABLE `entradas_curso`
  ADD CONSTRAINT `entradas_curso_curso_fk` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `entradas_curso_evento_fk` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE SET NULL;

--
-- Constraints for table `pagos_proveedores`
--
ALTER TABLE `pagos_proveedores`
  ADD CONSTRAINT `pagos_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE CASCADE;

--
-- Constraints for table `precios`
--
ALTER TABLE `precios`
  ADD CONSTRAINT `precios_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Constraints for table `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL;

--
-- Constraints for table `profesores`
--
ALTER TABLE `profesores`
  ADD CONSTRAINT `profesores_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Constraints for table `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD CONSTRAINT `rol_permiso_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE,
  ADD CONSTRAINT `rol_permiso_ibfk_2` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE;

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Constraints for table `usuarios_permisos`
--
ALTER TABLE `usuarios_permisos`
  ADD CONSTRAINT `usuarios_permisos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;