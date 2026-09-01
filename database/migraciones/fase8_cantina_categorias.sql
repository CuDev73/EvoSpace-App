-- Fase 8: Cantina - categoría de productos
ALTER TABLE `productos` ADD COLUMN `categoria` varchar(100) DEFAULT NULL AFTER `nombre`;