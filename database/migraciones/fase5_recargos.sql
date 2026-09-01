-- Fase 5: Recargo parametrizable por alumno
ALTER TABLE `alumnos` ADD COLUMN `dia_vencimiento` int(11) DEFAULT NULL AFTER `becado`;
ALTER TABLE `alumnos` ADD COLUMN `dias_gracia` int(11) DEFAULT NULL AFTER `dia_vencimiento`;