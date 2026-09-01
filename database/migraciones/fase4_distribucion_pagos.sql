-- Fase 4: Distribución de pagos
-- 1) Lotes de entradas/rifas asignados a un curso (opcionalmente ligados a un evento)
CREATE TABLE IF NOT EXISTS `entradas_curso` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Distribución individual de entradas/rifas a alumnos del curso
CREATE TABLE IF NOT EXISTS `entradas_alumno` (
  `id_entrada_alumno` int(11) NOT NULL AUTO_INCREMENT,
  `id_entrada_curso` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `fecha_entrega` date NOT NULL,
  PRIMARY KEY (`id_entrada_alumno`),
  UNIQUE KEY `uq_entrada_alumno` (`id_entrada_curso`, `id_alumno`),
  KEY `id_entrada_curso` (`id_entrada_curso`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `entradas_alumno_curso_fk` FOREIGN KEY (`id_entrada_curso`) REFERENCES `entradas_curso` (`id_entrada_curso`) ON DELETE CASCADE,
  CONSTRAINT `entradas_alumno_alumno_fk` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Vestuarios por evento: pago opcionalmente ligado a un evento
ALTER TABLE `pagos` ADD COLUMN `id_evento` int(11) DEFAULT NULL AFTER `id_alumno`;
ALTER TABLE `pagos` ADD CONSTRAINT `pagos_evento_fk` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`) ON DELETE SET NULL;