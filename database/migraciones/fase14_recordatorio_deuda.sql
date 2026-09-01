-- Configuración del recordatorio de deudas a tutores
-- recordatorio_deuda_dia: día del mes en que se envía automáticamente
-- recordatorio_deuda_activo: '1' activado, '0' desactivado
-- recordatorio_deuda_ultimo: último mes enviado (YYYY-MM) para evitar duplicados
INSERT INTO configuracion (clave, valor)
VALUES ('recordatorio_deuda_dia', '25'),
       ('recordatorio_deuda_activo', '1'),
       ('recordatorio_deuda_ultimo', '')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);