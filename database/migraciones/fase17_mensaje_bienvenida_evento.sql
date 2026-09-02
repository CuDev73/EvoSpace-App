-- Fase 17: Mensaje de bienvenida editable por evento (antes venía de Configuración).
ALTER TABLE `eventos`
    ADD COLUMN `mensaje_bienvenida` TEXT DEFAULT NULL AFTER `descripcion`;