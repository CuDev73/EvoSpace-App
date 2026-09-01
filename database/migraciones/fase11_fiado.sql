-- Fase 11: habilita 'Fiado' en pagos.metodo_pago (la ficha ya lo ofrecía y fallaba)
ALTER TABLE pagos
  MODIFY COLUMN metodo_pago ENUM('Efectivo','Transferencia','Tarjeta','Otro','Fiado') NOT NULL DEFAULT 'Efectivo';