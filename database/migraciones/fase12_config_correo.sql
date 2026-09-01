-- Configuraciones del correo de eventos (Configuración > Correo de Eventos)
INSERT INTO configuracion (clave, valor)
VALUES ('correo_saludo', 'Apreciado/a {padre}:'),
       ('correo_mensaje', 'Queremos invitarte a nuestro próximo evento. ¡Te esperamos!'),
       ('correo_firma', 'Equipo Instituto EvolucionArte'),
       ('correo_remitente', 'Instituto EvolucionArte')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
