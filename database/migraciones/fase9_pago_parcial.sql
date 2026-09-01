-- Fase 9: Pago parcial en ventas de cantina
ALTER TABLE `ventas` ADD COLUMN `monto_pagado` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `total`;
UPDATE `ventas` SET `monto_pagado` = `total` WHERE `estado_pago` = 'pagado';