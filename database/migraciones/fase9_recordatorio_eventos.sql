-- Fase 9: Recordatorio de eventos (aviso antes de la fecha)
ALTER TABLE `eventos` ADD COLUMN `ultimo_recordatorio` date DEFAULT NULL AFTER `imagen`;