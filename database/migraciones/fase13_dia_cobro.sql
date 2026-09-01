-- fase13: día de cobro por tutor/a (padre)
ALTER TABLE usuarios ADD COLUMN dia_cobro TINYINT NULL DEFAULT NULL AFTER activo;