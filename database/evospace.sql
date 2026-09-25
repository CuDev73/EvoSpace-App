-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: evospace
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `evospace`
--

/*!40000 DROP DATABASE IF EXISTS `evospace`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `evospace` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `evospace`;

--
-- Table structure for table `abonos`
--

DROP TABLE IF EXISTS `abonos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `abonos` (
  `id_abono` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_abono` date NOT NULL,
  `profesor` varchar(100) NOT NULL,
  `monto_abono` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_abono`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `abonos`
--

LOCK TABLES `abonos` WRITE;
/*!40000 ALTER TABLE `abonos` DISABLE KEYS */;
INSERT INTO `abonos` VALUES (2,'2026-07-27','jhoan.ramirez',2500000.00,NULL,NULL),(3,'2026-07-27','Maria Benitez',2354535.00,NULL,NULL),(4,'2026-07-27','profesor',2500000.00,NULL,NULL),(5,'2026-07-28','Maria Benitez',2354535.00,NULL,NULL),(6,'2026-07-28','Maria Benitez',145465.00,NULL,NULL),(7,'2026-07-29','jhoan.ramirez',2500000.00,NULL,NULL),(8,'2026-07-29','profesor',2500000.00,NULL,NULL),(9,'2026-07-29','jhoan.ramirez',5000000.00,NULL,NULL),(10,'2026-07-29','Maria Benitez',145465.00,NULL,NULL),(11,'2026-07-30','jhoan.ramirez',5000000.00,'',NULL);
/*!40000 ALTER TABLE `abonos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alumnos`
--

DROP TABLE IF EXISTS `alumnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alumnos` (
  `id_alumno` int(11) NOT NULL AUTO_INCREMENT,
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
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_alumno`),
  UNIQUE KEY `ci` (`ci`),
  KEY `id_curso` (`id_curso`),
  KEY `id_padre` (`id_padre`),
  CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  CONSTRAINT `alumnos_ibfk_2` FOREIGN KEY (`id_padre`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alumnos`
--

LOCK TABLES `alumnos` WRITE;
/*!40000 ALTER TABLE `alumnos` DISABLE KEYS */;
INSERT INTO `alumnos` VALUES (1,'Mariela','Nuñez Esteche',20,2022,1312.00,'283892','254234',NULL,1,NULL,NULL,1,'2026-07-23 01:58:31'),(2,'Natan','Levy',1,2023,0.00,'123456','098765',NULL,1,NULL,NULL,1,'2026-07-23 01:58:31'),(3,'Clara','Vallejos',9,2024,0.00,'654321','099888',NULL,0,NULL,NULL,1,'2026-07-23 01:58:31'),(4,'Jessica','Giménez',18,2021,800.00,'111222','456789',NULL,1,NULL,NULL,1,'2026-07-23 01:58:31'),(5,'Carlos','Ruiz',2,2023,0.00,'987654','555123',NULL,0,NULL,NULL,1,'2026-07-23 01:58:31'),(6,'Sofía','Martínez',14,2024,0.00,'456123','555456',NULL,0,NULL,NULL,1,'2026-07-23 01:58:31'),(7,'Sofía','González',1,2023,0.00,'1000001','0981111111',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(8,'Mateo','Rodríguez',2,2022,0.00,'1000002','0982222222',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(9,'Valentina','López',3,2024,0.00,'1000003','0983333333',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(10,'Tomás','Martínez',4,2021,0.00,'1000004','0984444444',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(11,'Camila','Pérez',5,2023,0.00,'1000005','0985555555',3,1,NULL,NULL,1,'2026-07-26 22:35:43'),(12,'Lucas','García',6,2022,0.00,'1000006','0986666666',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(13,'Isabella','Fernández',1,2024,0.00,'1000007','0987777777',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(14,'Facundo','Ramírez',2,2021,0.00,'1000008','0988888888',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(15,'Mía','Torres',3,2023,0.00,'1000009','0989999999',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(16,'Benjamín','Díaz',4,2022,0.00,'1000010','0971111111',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(17,'Emma','Álvarez',5,2024,0.00,'1000011','0972222222',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(18,'Joaquín','Romero',6,2021,0.00,'1000012','0973333333',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(19,'Martina','Gómez',1,2023,0.00,'1000013','0974444444',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(20,'Santino','Benítez',2,2022,0.00,'1000014','0975555555',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(21,'Renata','Duarte',3,2024,0.00,'1000015','0976666666',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(22,'Nicolás','Sánchez',4,2021,0.00,'1000016','0977777777',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(23,'Julieta','Ortiz',5,2023,0.00,'1000017','0978888888',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(24,'Matías','Herrera',6,2022,0.00,'1000018','0979999999',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(25,'Daniela','Mendoza',1,2024,0.00,'1000019','0961111111',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(26,'Lautaro','Paredes',2,2021,0.00,'1000020','0962222222',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(27,'Agustina','Luna',7,2023,0.00,'1000021','0963333333',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(28,'Valentino','Rojas',8,2022,0.00,'1000022','0964444444',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(29,'Delfina','Vázquez',9,2024,0.00,'1000023','0965555555',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(30,'Lorenzo','Aguirre',10,2021,0.00,'1000024','0966666666',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(31,'Bianca','Moreno',11,2023,0.00,'1000025','0967777777',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(32,'Thiago','Cáceres',12,2022,0.00,'1000026','0968888888',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(33,'Catalina','Flores',13,2024,0.00,'1000027','0969999999',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(34,'Santiago','Giménez',14,2021,0.00,'1000028','0951111111',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(35,'Victoria','Godoy',7,2023,0.00,'1000029','0952222222',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(36,'Franco','López',8,2022,0.00,'1000030','0953333333',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(37,'Pilar','Díaz',9,2024,0.00,'1000031','0954444444',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(38,'Lucas','Martín',10,2021,0.00,'1000032','0955555555',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(39,'Alma','Pereyra',11,2023,0.00,'1000033','0956666666',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(40,'Bautista','Ramos',12,2022,0.00,'1000034','0957777777',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(41,'Lola','Acosta',13,2024,0.00,'1000035','0958888888',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(42,'Emilio','Silva',14,2021,0.00,'1000036','0959999999',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(43,'Lara','García',7,2023,0.00,'1000037','0941111111',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(44,'Santos','Sosa',8,2022,0.00,'1000038','0942222222',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(45,'Zoe','Martínez',9,2024,0.00,'1000039','0943333333',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(46,'Bruno','Fernández',11,2021,0.00,'1000040','0944444444',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(47,'Alan','Benítez',15,2020,850.50,'1000041','0945555555',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(48,'Candela','Mansilla',16,2021,1200.00,'1000042','0946666666',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(49,'Nicolás','Alcaraz',17,2022,950.75,'1000043','0947777777',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(50,'Lucía','Báez',18,2020,1400.25,'1000044','0948888888',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(51,'Diego','Cardozo',19,2023,1100.00,'1000045','0949999999',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(52,'Sabrina','Olmedo',20,2021,1300.50,'1000046','0931111111',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(53,'Federico','Godoy',21,2022,800.00,'1000047','0932222222',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(54,'Juliana','López',15,2020,1600.00,'1000048','0933333333',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(55,'Maximiliano','Pérez',16,2023,900.00,'1000049','0934444444',3,0,NULL,NULL,1,'2026-07-26 22:35:43'),(56,'Rocío','Giménez',17,2021,1450.75,'1000050','0935555555',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(57,'Matías','Díaz',18,2022,1150.25,'1000051','0936666666',3,1,NULL,NULL,1,'2026-07-26 22:35:43'),(58,'Abril','Figueredo',19,2020,950.00,'1000052','0937777777',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(59,'Tomás','Escobar',20,2023,1200.00,'1000053','0938888888',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(60,'Mía','Ramírez',21,2021,1350.50,'1000054','0939999999',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(61,'Ignacio','Ojeda',15,2022,750.00,'1000055','0921111111',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(62,'Elena','Martín',16,2020,1550.00,'1000056','0922222222',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(63,'Julián','Vega',17,2023,1000.00,'1000057','0923333333',NULL,1,NULL,NULL,1,'2026-07-26 22:35:43'),(64,'Malena','Soria',18,2021,1250.25,'1000058','0924444444',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(65,'Facundo','Pinto',20,2022,880.50,'1000059','0925555555',NULL,0,NULL,NULL,1,'2026-07-26 22:35:43'),(66,'Brenda','Córdoba',21,2020,21.00,'1000060','0926666666',3,1,NULL,NULL,1,'2026-07-26 22:35:43'),(67,'Maria','Córdoba',1,2026,0.00,'00034123','',14,1,NULL,NULL,1,'2026-08-30 19:08:01');
/*!40000 ALTER TABLE `alumnos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asistencia` (
  `id_asistencia` int(11) NOT NULL AUTO_INCREMENT,
  `id_alumno` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `presente` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_asistencia`),
  UNIQUE KEY `id_alumno` (`id_alumno`,`fecha`),
  KEY `id_curso` (`id_curso`),
  CONSTRAINT `asistencia_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE,
  CONSTRAINT `asistencia_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencia`
--

LOCK TABLES `asistencia` WRITE;
/*!40000 ALTER TABLE `asistencia` DISABLE KEYS */;
INSERT INTO `asistencia` VALUES (52,13,1,'2026-07-03',1,NULL,'2026-07-29 23:16:23'),(53,13,1,'2026-07-06',1,NULL,'2026-07-29 23:16:23'),(54,13,1,'2026-07-31',1,NULL,'2026-07-29 23:16:23'),(55,19,1,'2026-07-03',1,NULL,'2026-07-29 23:16:23'),(56,19,1,'2026-07-06',1,NULL,'2026-07-29 23:16:23'),(57,7,1,'2026-07-03',1,NULL,'2026-07-29 23:16:23'),(58,7,1,'2026-07-06',1,NULL,'2026-07-29 23:16:23'),(59,2,1,'2026-07-03',1,NULL,'2026-07-29 23:16:23'),(60,2,1,'2026-07-06',1,NULL,'2026-07-29 23:16:23'),(61,25,1,'2026-07-03',1,NULL,'2026-07-29 23:16:23'),(62,25,1,'2026-07-06',1,NULL,'2026-07-29 23:16:23'),(63,2,1,'2026-07-30',1,'','2026-07-30 01:43:06'),(64,7,1,'2026-07-30',0,'','2026-07-30 01:43:06'),(65,13,1,'2026-07-30',1,'','2026-07-30 01:43:06'),(66,19,1,'2026-07-30',1,'','2026-07-30 01:43:06'),(67,25,1,'2026-07-30',0,'','2026-07-30 01:43:06');
/*!40000 ALTER TABLE `asistencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras_alumnos`
--

DROP TABLE IF EXISTS `compras_alumnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compras_alumnos` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `id_alumno` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `producto` varchar(100) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `pagado` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_compra`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `compras_alumnos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras_alumnos`
--

LOCK TABLES `compras_alumnos` WRITE;
/*!40000 ALTER TABLE `compras_alumnos` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras_alumnos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras_proveedores`
--

DROP TABLE IF EXISTS `compras_proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compras_proveedores` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_compra`),
  KEY `id_proveedor` (`id_proveedor`),
  CONSTRAINT `compras_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras_proveedores`
--

LOCK TABLES `compras_proveedores` WRITE;
/*!40000 ALTER TABLE `compras_proveedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras_proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `clave` varchar(50) NOT NULL,
  `valor` text NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES ('correo_firma','Equipo Instituto EvolucionArte'),('correo_mensaje','Queremos invitarte a nuestro próximo evento. ¡Te esperamos!'),('correo_remitente','Instituto EvolucionArte'),('correo_saludo','Apreciado/a {tutor}:'),('dia_limite_pago','10'),('dias_gracia_pago','10'),('limite_horas_profesionales','200'),('porcentaje_beca','50'),('recargo_por_dia','1000'),('recibo_logo','uploads/recibo/logo_recibo.jpg'),('recibo_mensaje',''),('recibo_nombre','EvoSpace'),('recibo_pie',''),('recibo_ruc',''),('recibo_titulo',''),('recordatorio_deuda_activo','1'),('recordatorio_deuda_dia','30'),('recordatorio_deuda_ultimo','2026-08'),('smtp_host','');
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cursos`
--

DROP TABLE IF EXISTS `cursos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('Acrotelas','Infantil','Superior') NOT NULL,
  `orden` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `cupo_maximo` int(11) DEFAULT NULL COMMENT 'NULL = sin limite',
  PRIMARY KEY (`id_curso`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cursos`
--

LOCK TABLES `cursos` WRITE;
/*!40000 ALTER TABLE `cursos` DISABLE KEYS */;
INSERT INTO `cursos` VALUES (1,'Inicial','Acrotelas',1,1,13),(2,'Primer Curso','Acrotelas',2,1,NULL),(3,'Segundo Curso','Acrotelas',3,1,NULL),(4,'Tercer Curso','Acrotelas',4,1,NULL),(5,'Cuarto Curso','Acrotelas',5,1,NULL),(6,'Quinto Curso','Acrotelas',6,1,NULL),(7,'Nivel Inicial I','Infantil',1,1,NULL),(8,'Nivel Inicial II','Infantil',2,1,NULL),(9,'Primer Grado','Infantil',3,1,NULL),(10,'Segundo Grado','Infantil',4,1,NULL),(11,'Tercer Grado','Infantil',5,1,NULL),(12,'Cuarto Grado','Infantil',6,1,NULL),(13,'Quinto Grado','Infantil',7,1,NULL),(14,'Sexto Grado','Infantil',8,1,NULL),(15,'Principiante Superior','Superior',1,1,NULL),(16,'Preparatorio Superior','Superior',2,1,NULL),(17,'Primer Curso','Superior',3,1,NULL),(18,'Segundo Curso','Superior',4,1,NULL),(19,'Tercer Curso','Superior',5,1,NULL),(20,'Cuarto Curso','Superior',6,1,NULL),(21,'Quinto Curso','Superior',7,1,NULL);
/*!40000 ALTER TABLE `cursos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compra_proveedor`
--

DROP TABLE IF EXISTS `detalle_compra_proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compra_proveedor` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_compra` (`id_compra`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_compra_proveedor_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras_proveedores` (`id_compra`) ON DELETE CASCADE,
  CONSTRAINT `detalle_compra_proveedor_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compra_proveedor`
--

LOCK TABLES `detalle_compra_proveedor` WRITE;
/*!40000 ALTER TABLE `detalle_compra_proveedor` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_compra_proveedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_ventas`
--

DROP TABLE IF EXISTS `detalle_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_ventas` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_venta` (`id_venta`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE,
  CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_ventas`
--

LOCK TABLES `detalle_ventas` WRITE;
/*!40000 ALTER TABLE `detalle_ventas` DISABLE KEYS */;
INSERT INTO `detalle_ventas` VALUES (1,1,1,1,20000.00,20000.00),(2,1,1,1,20000.00,20000.00),(3,1,1,1,20000.00,20000.00),(4,2,1,1,20000.00,20000.00),(5,3,1,1,20000.00,20000.00),(6,4,1,1,20000.00,20000.00),(7,5,1,1,20000.00,20000.00),(8,5,1,1,20000.00,20000.00),(9,5,1,1,20000.00,20000.00),(12,7,1,6,20000.00,120000.00),(13,8,3,3,3000.00,9000.00),(14,9,3,4,3000.00,12000.00),(15,10,3,1,3000.00,3000.00),(16,11,3,6,3000.00,18000.00),(17,12,4,1,5000.00,5000.00),(18,13,4,20,5000.00,100000.00),(19,14,4,30,5000.00,150000.00),(20,14,3,1,3000.00,3000.00),(21,15,3,1,3000.00,3000.00),(26,19,4,1,5000.00,5000.00),(27,19,3,1,3000.00,3000.00);
/*!40000 ALTER TABLE `detalle_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entradas_alumno`
--

DROP TABLE IF EXISTS `entradas_alumno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entradas_alumno` (
  `id_entrada_alumno` int(11) NOT NULL AUTO_INCREMENT,
  `id_entrada_curso` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `cantidad_total` int(11) NOT NULL DEFAULT 0,
  `fecha_entrega` date NOT NULL,
  PRIMARY KEY (`id_entrada_alumno`),
  UNIQUE KEY `uq_entrada_alumno` (`id_entrada_curso`,`id_alumno`),
  KEY `id_entrada_curso` (`id_entrada_curso`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `entradas_alumno_alumno_fk` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE,
  CONSTRAINT `entradas_alumno_curso_fk` FOREIGN KEY (`id_entrada_curso`) REFERENCES `entradas_curso` (`id_entrada_curso`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entradas_alumno`
--

LOCK TABLES `entradas_alumno` WRITE;
/*!40000 ALTER TABLE `entradas_alumno` DISABLE KEYS */;
INSERT INTO `entradas_alumno` VALUES (3,2,67,2,5,'2026-08-31');
/*!40000 ALTER TABLE `entradas_alumno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entradas_curso`
--

DROP TABLE IF EXISTS `entradas_curso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entradas_curso` (
  `id_entrada_curso` int(11) NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `id_evento` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `precio` decimal(10,0) NOT NULL DEFAULT 0,
  `fecha_asignacion` date NOT NULL,
  `estado` enum('activa','cerrada') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id_entrada_curso`),
  KEY `id_curso` (`id_curso`),
  KEY `id_evento` (`id_evento`),
  CONSTRAINT `entradas_curso_curso_fk` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  CONSTRAINT `entradas_curso_evento_fk` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entradas_curso`
--

LOCK TABLES `entradas_curso` WRITE;
/*!40000 ALTER TABLE `entradas_curso` DISABLE KEYS */;
INSERT INTO `entradas_curso` VALUES (2,1,NULL,20,10000,'2026-08-30','activa');
/*!40000 ALTER TABLE `entradas_curso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evento_curso`
--

DROP TABLE IF EXISTS `evento_curso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evento_curso` (
  `id_evento` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  PRIMARY KEY (`id_evento`,`id_curso`),
  KEY `id_curso` (`id_curso`),
  CONSTRAINT `evento_curso_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE CASCADE,
  CONSTRAINT `evento_curso_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evento_curso`
--

LOCK TABLES `evento_curso` WRITE;
/*!40000 ALTER TABLE `evento_curso` DISABLE KEYS */;
/*!40000 ALTER TABLE `evento_curso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventos`
--

DROP TABLE IF EXISTS `eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventos` (
  `id_evento` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `mensaje_bienvenida` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `lugar` varchar(200) DEFAULT NULL,
  `enlace_ubicacion` varchar(255) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#c81015',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `imagen` varchar(255) DEFAULT NULL,
  `ultimo_recordatorio` date DEFAULT NULL,
  PRIMARY KEY (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventos`
--

LOCK TABLES `eventos` WRITE;
/*!40000 ALTER TABLE `eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios`
--

DROP TABLE IF EXISTS `horarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horarios` (
  `id_horario` int(11) NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `id_profesor` int(11) DEFAULT NULL,
  `dia_semana` varchar(20) NOT NULL DEFAULT '1',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id_horario`),
  KEY `id_curso` (`id_curso`),
  KEY `id_profesor` (`id_profesor`),
  CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  CONSTRAINT `horarios_ibfk_2` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id_profesor`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
INSERT INTO `horarios` VALUES (1,1,3,'5','21:40:00','22:15:00'),(2,1,3,'1','19:30:00','20:30:00');
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horas_profesionales_log`
--

DROP TABLE IF EXISTS `horas_profesionales_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horas_profesionales_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_alumno` int(11) NOT NULL,
  `horas` decimal(6,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `horas_profesionales_log_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horas_profesionales_log`
--

LOCK TABLES `horas_profesionales_log` WRITE;
/*!40000 ALTER TABLE `horas_profesionales_log` DISABLE KEYS */;
INSERT INTO `horas_profesionales_log` VALUES (1,66,20.00,NULL,'2026-07-30','2026-07-29 23:21:57'),(2,66,1.00,NULL,'2026-07-30','2026-07-29 23:22:09');
/*!40000 ALTER TABLE `horas_profesionales_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migraciones_aplicadas`
--

DROP TABLE IF EXISTS `migraciones_aplicadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migraciones_aplicadas` (
  `nombre` varchar(100) NOT NULL,
  `aplicada_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migraciones_aplicadas`
--

LOCK TABLES `migraciones_aplicadas` WRITE;
/*!40000 ALTER TABLE `migraciones_aplicadas` DISABLE KEYS */;
INSERT INTO `migraciones_aplicadas` VALUES ('col_alumnos_becado','2026-09-01 20:47:40'),('col_alumnos_dia_vencimiento','2026-09-01 20:47:40'),('col_alumnos_dias_gracia','2026-09-01 20:47:40'),('col_alumnos_horas_profesionales','2026-09-01 20:47:40'),('col_entradas_alumno_cantidad_total','2026-09-01 20:47:40'),('col_pagos_concepto','2026-09-01 20:55:29'),('col_pagos_id_evento','2026-09-01 20:55:29'),('col_usuarios_dia_cobro','2026-09-01 20:47:40'),('fase12_config_correo','2026-09-01 17:46:29'),('fase13_dia_cobro','2026-09-01 17:46:29'),('fase5_recargo_por_dia','2026-09-01 17:46:29'),('horas_profesionales_log','2026-09-01 20:47:40');
/*!40000 ALTER TABLE `migraciones_aplicadas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_evento` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `tipo` enum('evento','pago','general') DEFAULT 'evento',
  `leida` tinyint(1) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notificacion`),
  KEY `id_evento` (`id_evento`),
  KEY `fk_notificaciones_usuario` (`id_usuario`),
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_alumno` int(11) NOT NULL,
  `id_evento` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `monto` decimal(10,2) NOT NULL,
  `descuento` decimal(5,2) DEFAULT 0.00,
  `recargo` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` enum('Efectivo','Transferencia','Tarjeta','Otro','Fiado') NOT NULL DEFAULT 'Efectivo',
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `id_alumno` (`id_alumno`),
  KEY `pagos_evento_fk` (`id_evento`),
  CONSTRAINT `pagos_evento_fk` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE SET NULL,
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES (1,41,NULL,'2026-07-26','cuota',1,220000.00,0.00,15000.00,235000.00,'Efectivo',NULL,NULL,'2026-07-27 02:13:53'),(2,41,NULL,'2026-07-27','cuota',1,220000.00,0.00,16000.00,236000.00,'Efectivo',NULL,NULL,'2026-07-27 23:01:50'),(3,17,NULL,'2026-07-28','cuota',1,91000.00,0.00,17000.00,108000.00,'Efectivo',NULL,NULL,'2026-07-28 19:57:07'),(4,57,NULL,'2026-07-28','cuota',1,114000.00,0.00,17000.00,131000.00,'Efectivo',NULL,NULL,'2026-07-28 19:58:07'),(5,41,NULL,'2026-07-28','cuota',1,220000.00,0.00,17000.00,237000.00,'Efectivo',NULL,NULL,'2026-07-29 02:32:10'),(6,57,NULL,'2026-07-28','cuota',1,114000.00,0.00,17000.00,131000.00,'Efectivo',NULL,NULL,'2026-07-29 02:39:45'),(7,41,NULL,'2026-07-29','cuota',1,220000.00,0.00,18000.00,238000.00,'Efectivo',NULL,NULL,'2026-07-29 12:32:39'),(8,17,NULL,'2026-07-29','cuota',1,91000.00,0.00,18000.00,109000.00,'Efectivo',NULL,NULL,'2026-07-29 12:32:56'),(9,46,NULL,'2026-07-29','cuota',1,220000.00,0.00,19000.00,239000.00,'Efectivo','Pago de cuota',NULL,'2026-07-29 22:00:16'),(10,1,NULL,'2026-07-30','cuota',1,250000.00,0.00,20000.00,270000.00,'Efectivo',NULL,NULL,'2026-07-29 22:12:24'),(11,66,NULL,'2026-07-30','cuota',1,114000.00,0.00,20000.00,134000.00,'Efectivo',NULL,NULL,'2026-07-29 22:26:59'),(13,1,NULL,'2026-08-30','matrículo',1,100000.00,0.00,0.00,100000.00,'Efectivo',NULL,NULL,'2026-08-30 13:28:41'),(17,67,NULL,'2026-08-31','cuota',1,100000.00,0.00,16000.00,116000.00,'Efectivo',NULL,NULL,'2026-08-31 00:26:04'),(20,67,NULL,'2026-08-31','entradas',1,10000.00,0.00,0.00,10000.00,'Efectivo',NULL,NULL,'2026-08-31 01:48:58'),(21,67,NULL,'2026-09-02','entradas',3,10000.00,0.00,0.00,30000.00,'Efectivo',NULL,NULL,'2026-09-02 05:11:16'),(22,67,NULL,'2026-09-24','cuota',1,100000.00,0.00,0.00,100000.00,'Efectivo',NULL,NULL,'2026-09-24 01:37:10'),(23,67,NULL,'2026-09-24','matrícula',1,150000.00,0.00,0.00,150000.00,'Efectivo',NULL,NULL,'2026-09-24 01:37:23');
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos_alumnos_cantina`
--

DROP TABLE IF EXISTS `pagos_alumnos_cantina`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos_alumnos_cantina` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_alumno` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `pagos_alumnos_cantina_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos_alumnos_cantina`
--

LOCK TABLES `pagos_alumnos_cantina` WRITE;
/*!40000 ALTER TABLE `pagos_alumnos_cantina` DISABLE KEYS */;
INSERT INTO `pagos_alumnos_cantina` VALUES (1,1,'2026-07-28',120000.00,'2026-07-28 21:36:18'),(2,1,'2026-07-28',120000.00,'2026-07-28 21:36:36'),(3,1,'2026-07-28',120000.00,'2026-07-28 21:38:31'),(4,17,'2026-07-28',9000.00,'2026-07-28 21:38:38'),(5,18,'2026-07-29',1500.00,'2026-07-29 02:33:58'),(6,32,'2026-07-29',18000.00,'2026-07-29 12:33:59'),(7,1,'2026-07-30',5000.00,'2026-07-29 23:25:51');
/*!40000 ALTER TABLE `pagos_alumnos_cantina` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos_proveedores`
--

DROP TABLE IF EXISTS `pagos_proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos_proveedores` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `concepto` varchar(200) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `id_proveedor` (`id_proveedor`),
  CONSTRAINT `pagos_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos_proveedores`
--

LOCK TABLES `pagos_proveedores` WRITE;
/*!40000 ALTER TABLE `pagos_proveedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagos_proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos` (
  `id_permiso` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

LOCK TABLES `permisos` WRITE;
/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
INSERT INTO `permisos` VALUES (13,'gestionar_usuarios','Crear/editar/eliminar usuarios'),(18,'alumnos','Ver y editar alumnos'),(19,'pagos','Ver y editar pagos'),(20,'profesores','Ver y editar profesores'),(21,'eventos','Ver y editar eventos'),(22,'cantina','Ver y editar cantina'),(23,'asistencia','Ver y editar asistencia'),(24,'configuracion','Ver y editar configuración'),(25,'usuarios','Gestionar usuarios'),(27,'horarios','Ver y editar horarios de cursos');
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `precios`
--

DROP TABLE IF EXISTS `precios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `precios` (
  `id_precio` int(11) NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `concepto` varchar(50) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `descuento_beca` decimal(5,2) DEFAULT 0.00,
  `aplica_beca` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_precio`),
  UNIQUE KEY `id_curso` (`id_curso`,`concepto`),
  CONSTRAINT `precios_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `precios`
--

LOCK TABLES `precios` WRITE;
/*!40000 ALTER TABLE `precios` DISABLE KEYS */;
INSERT INTO `precios` VALUES (1,1,'matrícula',150000.00,0.00,0),(2,2,'matrícula',150000.00,0.00,0),(3,3,'matrícula',150000.00,0.00,0),(4,4,'matrícula',150000.00,0.00,0),(5,5,'matrícula',150000.00,0.00,0),(6,6,'matrícula',150000.00,0.00,0),(8,1,'cuota',200000.00,45.45,1),(9,2,'cuota',200000.00,45.45,1),(10,3,'cuota',200000.00,45.45,1),(11,4,'cuota',200000.00,45.45,1),(12,5,'cuota',200000.00,45.45,1),(13,6,'cuota',200000.00,45.45,1),(15,1,'vestuarios',150000.00,0.00,0),(16,2,'vestuarios',150000.00,0.00,0),(17,3,'vestuarios',150000.00,0.00,0),(18,4,'vestuarios',150000.00,0.00,0),(19,5,'vestuarios',150000.00,0.00,0),(20,6,'vestuarios',150000.00,0.00,0),(22,1,'entradas',80000.00,0.00,0),(23,2,'entradas',80000.00,0.00,0),(24,3,'entradas',80000.00,0.00,0),(25,4,'entradas',80000.00,0.00,0),(26,5,'entradas',80000.00,0.00,0),(27,6,'entradas',80000.00,0.00,0),(29,7,'matrícula',180000.00,0.00,0),(30,8,'matrícula',180000.00,0.00,0),(31,9,'matrícula',180000.00,0.00,0),(32,10,'matrícula',180000.00,0.00,0),(33,11,'matrícula',180000.00,0.00,0),(34,12,'matrícula',180000.00,0.00,0),(35,13,'matrícula',180000.00,0.00,0),(36,14,'matrícula',180000.00,0.00,0),(44,7,'cuota',220000.00,45.45,1),(45,8,'cuota',220000.00,45.45,1),(46,9,'cuota',220000.00,45.45,1),(47,10,'cuota',220000.00,45.45,1),(48,11,'cuota',220000.00,45.45,1),(49,12,'cuota',220000.00,45.45,1),(50,13,'cuota',220000.00,45.45,1),(51,14,'cuota',220000.00,45.45,1),(59,7,'vestuarios',150000.00,0.00,0),(60,8,'vestuarios',150000.00,0.00,0),(61,9,'vestuarios',150000.00,0.00,0),(62,10,'vestuarios',150000.00,0.00,0),(63,11,'vestuarios',150000.00,0.00,0),(64,12,'vestuarios',150000.00,0.00,0),(65,13,'vestuarios',150000.00,0.00,0),(66,14,'vestuarios',150000.00,0.00,0),(74,7,'entradas',80000.00,0.00,0),(75,8,'entradas',80000.00,0.00,0),(76,9,'entradas',80000.00,0.00,0),(77,10,'entradas',80000.00,0.00,0),(78,11,'entradas',80000.00,0.00,0),(79,12,'entradas',80000.00,0.00,0),(80,13,'entradas',80000.00,0.00,0),(81,14,'entradas',80000.00,0.00,0),(89,7,'folleto',25000.00,0.00,0),(90,8,'folleto',25000.00,0.00,0),(91,9,'folleto',25000.00,0.00,0),(92,10,'folleto',25000.00,0.00,0),(93,11,'folleto',25000.00,0.00,0),(94,12,'folleto',25000.00,0.00,0),(95,13,'folleto',25000.00,0.00,0),(96,14,'folleto',25000.00,0.00,0),(104,15,'matrícula',180000.00,0.00,0),(105,16,'matrícula',180000.00,0.00,0),(106,17,'matrícula',180000.00,0.00,0),(107,18,'matrícula',180000.00,0.00,0),(108,19,'matrícula',180000.00,0.00,0),(109,20,'matrícula',180000.00,0.00,0),(110,21,'matrícula',180000.00,0.00,0),(111,15,'cuota',250000.00,45.45,1),(112,16,'cuota',250000.00,45.45,1),(113,17,'cuota',250000.00,45.45,1),(114,18,'cuota',250000.00,45.45,1),(115,19,'cuota',250000.00,45.45,1),(116,20,'cuota',250000.00,45.45,1),(117,21,'cuota',250000.00,45.45,1),(118,15,'vestuarios',150000.00,0.00,0),(119,16,'vestuarios',150000.00,0.00,0),(120,17,'vestuarios',150000.00,0.00,0),(121,18,'vestuarios',150000.00,0.00,0),(122,19,'vestuarios',150000.00,0.00,0),(123,20,'vestuarios',150000.00,0.00,0),(124,21,'vestuarios',150000.00,0.00,0),(125,15,'entradas',80000.00,0.00,0),(126,16,'entradas',80000.00,0.00,0),(127,17,'entradas',80000.00,0.00,0),(128,18,'entradas',80000.00,0.00,0),(129,19,'entradas',80000.00,0.00,0),(130,20,'entradas',80000.00,0.00,0),(131,21,'entradas',80000.00,0.00,0),(132,15,'folleto',0.00,0.00,0),(133,16,'folleto',0.00,0.00,0),(134,17,'folleto',0.00,0.00,0),(135,18,'folleto',0.00,0.00,0),(136,19,'folleto',0.00,0.00,0),(137,20,'folleto',0.00,0.00,0),(138,21,'folleto',0.00,0.00,0);
/*!40000 ALTER TABLE `precios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `precio_compra` decimal(10,2) DEFAULT 0.00,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `id_proveedor` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_producto`),
  KEY `id_proveedor` (`id_proveedor`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'Papas a la crema','Snacks',20000.00,1,'2026-07-26 17:41:32',15000.00,0,1),(2,'Papas fritas','Snacks',123.00,1,'2026-07-26 17:45:49',0.00,0,NULL),(3,'Papas a la crema2','Snacks',3000.00,1,'2026-07-28 21:29:57',1300.00,13,1),(4,'Bolsa de papas','Snacks',5000.00,1,'2026-07-29 12:34:40',3500.00,48,1),(6,'Cocacolita','Bebidas',12000.00,1,'2026-08-31 16:17:43',15000.00,100,NULL);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profesores`
--

DROP TABLE IF EXISTS `profesores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profesores` (
  `id_profesor` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `salario_base` decimal(10,2) DEFAULT NULL,
  `fecha_contratacion` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_profesor`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `profesores_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profesores`
--

LOCK TABLES `profesores` WRITE;
/*!40000 ALTER TABLE `profesores` DISABLE KEYS */;
INSERT INTO `profesores` VALUES (1,2,5000000.00,'2026-07-26',1),(2,5,15000000.00,'2026-07-26',1),(3,6,5000000.00,'2026-07-26',1);
/*!40000 ALTER TABLE `profesores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `nombre_contacto` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `tipo_productos` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_proveedor`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'Carlita',1,'aasdf','0926666666','961751338','maradsf@gmail.com','asdf','Bebidas');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_permiso`
--

DROP TABLE IF EXISTS `rol_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol_permiso` (
  `id_rol` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL,
  PRIMARY KEY (`id_rol`,`id_permiso`),
  KEY `id_permiso` (`id_permiso`),
  CONSTRAINT `rol_permiso_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE,
  CONSTRAINT `rol_permiso_ibfk_2` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_permiso`
--

LOCK TABLES `rol_permiso` WRITE;
/*!40000 ALTER TABLE `rol_permiso` DISABLE KEYS */;
INSERT INTO `rol_permiso` VALUES (1,13);
/*!40000 ALTER TABLE `rol_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrador con acceso total'),(2,'profesor','Profesor con acceso limitado a alumnos y asistencia'),(3,'padre','Padre con acceso solo a sus hijos'),(4,'auxiliar','Auxiliar con acceso a listas de alumnos y asistencia');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre_completo` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `dia_cobro` tinyint(4) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `cedula` (`cedula`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'admin','admin@evospace.com','1234567','$2y$10$LBSyD2UFwBLJA/G1i4CRh.pVZJ/q/n2zhkSGNilT5OxM6IK3ccyBC',1,'Administrador',NULL,1,NULL,'2026-07-23 01:58:31'),(2,'profesor','profe@evospace.com','2345678','$2y$10$0PZciBLEdsMtmyGGvdhqZ.z4v9gM8BzW58AWsHTTgehmRdn.iR5VS',2,'Profesor Ejemplo',NULL,1,NULL,'2026-07-23 01:58:31'),(3,'padre','villoan73@gmail.com','3456789','$2y$10$UQdNcG8C567qEegz4T0l4OBwJtWNG2HUNcoZK8bi2NwDBG2Cl68LW',3,'Padre Ejemplo',NULL,1,NULL,'2026-07-23 01:58:31'),(5,'jhoan.ramirez','','7007909','$2y$10$.dgzoYtUKLxS4q/szOodEeubeh0dboBo9KeaMgDS90JDxazsmAXv2',2,'Jhoan Ramirez',NULL,1,NULL,'2026-07-26 17:05:36'),(6,'Maria Benitez','maradsf@gmail.com','123','$2y$10$swRGLHNgXDv3nvuba8DOuuvFCTtPB73A91pYhlIYpV64D4TcURxdO',2,'Maria Benitez',NULL,1,NULL,'2026-07-26 20:59:55'),(8,'cantinero','mtxuwu234@gmaaaail.com','123123123','$2y$10$W1DGFgxr692d1XLCUSDLHOaKdpSuKC2rifLDulhu86UR0NajFj4ey',4,'cantinero',NULL,1,NULL,'2026-07-29 12:41:28'),(14,'fasdf@@1a','mtxuwu234@gmail.com','123123','$2y$10$uxj./4/YJ50taNKScXwf/.pweN47EFVuWl9zWdFCETSyNEjJTT3aG',3,'Jfas',NULL,1,15,'2026-08-30 19:07:52');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios_permisos`
--

DROP TABLE IF EXISTS `usuarios_permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios_permisos` (
  `id_usuario` int(11) NOT NULL,
  `permiso` varchar(50) NOT NULL,
  PRIMARY KEY (`id_usuario`,`permiso`),
  CONSTRAINT `usuarios_permisos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios_permisos`
--

LOCK TABLES `usuarios_permisos` WRITE;
/*!40000 ALTER TABLE `usuarios_permisos` DISABLE KEYS */;
INSERT INTO `usuarios_permisos` VALUES (2,'alumnos'),(2,'asistencia'),(2,'eventos'),(2,'pagos'),(2,'usuarios'),(5,'alumnos'),(5,'asistencia'),(5,'eventos'),(5,'pagos'),(6,'alumnos'),(6,'asistencia'),(6,'eventos'),(6,'pagos'),(8,'cantina'),(8,'configuracion');
/*!40000 ALTER TABLE `usuarios_permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('Efectivo','Fiado') NOT NULL,
  `tipo_comprador` enum('alumno','profesor','otro') DEFAULT 'otro',
  `nombre_comprador` varchar(150) DEFAULT NULL,
  `id_alumno` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `comprobante` varchar(255) DEFAULT NULL,
  `estado_pago` enum('pagado','pendiente','parcial') DEFAULT 'pagado',
  PRIMARY KEY (`id_venta`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES (1,'2026-07-26 14:45:04',60000.00,60000.00,'Fiado','otro',NULL,NULL,NULL,'',NULL,'pagado'),(2,'2026-07-27 00:00:00',20000.00,20000.00,'Efectivo','alumno','Mariela Nuñez Esteche',1,NULL,'',NULL,'pagado'),(3,'2026-07-27 00:00:00',20000.00,20000.00,'Efectivo','alumno','Mariela Nuñez Esteche',1,NULL,'',NULL,'pagado'),(4,'2026-07-27 00:00:00',20000.00,20000.00,'Efectivo','alumno','Mariela Nuñez Esteche',1,NULL,'',NULL,'pagado'),(5,'2026-07-28 00:00:00',60000.00,60000.00,'Fiado','alumno','Joaquín Romero',18,NULL,'',NULL,'pagado'),(7,'2026-07-28 00:00:00',120000.00,120000.00,'Fiado','alumno','Mariela Nuñez Esteche',1,NULL,'',NULL,'pagado'),(8,'2026-07-28 00:00:00',9000.00,9000.00,'Fiado','alumno','Emma Álvarez',17,NULL,'',NULL,'pagado'),(9,'2026-07-29 00:00:00',12000.00,12000.00,'Efectivo','alumno','Brenda Córdoba',66,NULL,'',NULL,'pagado'),(10,'2026-07-29 00:00:00',3000.00,3000.00,'Efectivo','alumno','Joaquín Romero',18,NULL,'',NULL,'pagado'),(11,'2026-07-29 00:00:00',18000.00,18000.00,'Fiado','alumno','Thiago Cáceres',32,NULL,'',NULL,'pagado'),(12,'2026-07-30 00:00:00',5000.00,5000.00,'Fiado','alumno','Mariela Nuñez Esteche',1,NULL,'',NULL,'pagado'),(13,'2026-07-30 00:00:00',100000.00,100000.00,'Fiado','alumno','Joaquín Romero',18,NULL,'',NULL,'pagado'),(14,'2026-07-30 00:00:00',153000.00,153000.00,'Efectivo','alumno','Tomás Escobar',59,NULL,'',NULL,'pagado'),(15,'2026-07-30 00:00:00',3000.00,3000.00,'Fiado','alumno','Jessica Giménez',4,NULL,'',NULL,'pagado'),(19,'2026-09-02 00:00:00',8000.00,8000.00,'Fiado','alumno','Clara Vallejos',3,NULL,'Debe, va a pagar mañana',NULL,'pagado');
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-24  6:01:40
