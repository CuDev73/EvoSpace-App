
# EvoSpace

Sistema de gestión integral para academias de danza, artes escénicas y centros formativos.  
Diseñado para administrar alumnos, pagos, asistencia, eventos, ventas de cantina y usuarios con roles y permisos granulares.

---

## Índice

- [Descripción general](#descripción-general)
- [Características principales](#características-principales)
- [Tecnologías utilizadas](#tecnologías-utilizadas)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Requisitos previos](#requisitos-previos)
- [Instalación y configuración](#instalación-y-configuración)
- [Usuarios predeterminados](#usuarios-predeterminados)
- [Módulos funcionales](#módulos-funcionales)
  - [Gestión de alumnos](#gestión-de-alumnos)
  - [Pagos y recargos](#pagos-y-recargos)
  - [Asistencia](#asistencia)
  - [Eventos y notificaciones](#eventos-y-notificaciones)
  - [Cantina](#cantina)
  - [Usuarios y permisos](#usuarios-y-permisos)
- [Notas técnicas](#notas-técnicas)
- [Posibles problemas y soluciones](#posibles-problemas-y-soluciones)
- [Licencia](#licencia)

---

## Descripción general

EvoSpace nació de la necesidad de tener un único sistema que cubriera todas las áreas operativas de una academia de danza. Reemplaza planillas Excel, libretas de asistencia, cuadernos de pagos y comunicaciones por correo, unificando todo en una aplicación web accesible desde cualquier navegador.

El sistema está pensado para tres perfiles principales:
- **Administradores**: control total, estadísticas, configuración.
- **Profesores**: registro de asistencia, visualización de alumnos.
- **Padres**: seguimiento de pagos, eventos y notificaciones.

Cada perfil ve solo lo que necesita, y los permisos se pueden afinar por usuario, no solo por rol.

---

## Características principales

- **Gestión completa de alumnos**: alta, baja, edición, asignación de curso, becas, datos de contacto y relación con padres.
- **Pagos inteligentes**: cálculo automático de cuotas con descuentos por beca (redondeo al millar), recargos automáticos a partir del día 11 de cada mes.
- **Asistencia diaria y mensual**: registro rápido por día o vista mensual con selección de presente/ausente. Exportación a Excel (formato XLSX real con PhpSpreadsheet) o CSV.
- **Eventos y notificaciones**: creación de eventos asociados a cursos. Envío automático de correos a los padres de los alumnos de esos cursos. Las notificaciones quedan registradas en la base de datos y se muestran en el panel del padre.
- **Módulo de cantina**: administración de productos (precios, activos), registro de ventas con tipo de comprador (alumno, profesor, otro), método de pago (efectivo, transferencia, fiado). Resumen de caja diario, semanal y total, con gráfico de ventas de los últimos 7 días.
- **Usuarios y permisos granulares**: no solo roles fijos (admin, profesor, padre, auxiliar), sino que cada usuario puede tener permisos individuales (ver alumnos, editar pagos, gestionar usuarios, etc.). Esto permite, por ejemplo, crear un usuario "profesor" que solo vea asistencia, o un "auxiliar" que vea alumnos pero no pagos.
- **Paneles diferenciados**:
  - **Admin**: estadísticas de alumnos, profesores, padres, recaudación, eventos próximos y acceso rápido a todas las secciones.
  - **Profesor**: lista de cursos con acceso directo a registro de asistencia (diario, mensual, historial y exportación).
  - **Padre**: eventos próximos, notificaciones, resumen de deudas de sus hijos por mes, con cálculo de recargo si corresponde.
- **Interfaz moderna y responsive**: construida con Bootstrap 5, con estilos personalizados y una barra de navegación lateral (offcanvas) adaptada a móviles.

---

## Tecnologías utilizadas

- **PHP 8.0+** con PDO para conexión segura a la base de datos.
- **MySQL / MariaDB** como motor de base de datos relacional.
- **Bootstrap 5** para el frontend (CSS y componentes).
- **Bootstrap Icons** para la iconografía.
- **JavaScript vanilla** para interactividad (cálculos en tiempo real, buscadores, modales, gráficos).
- **PHPMailer** para envío de correos SMTP (compatible con Gmail, Outlook, etc.).
- **PhpSpreadsheet** para exportación a Excel (formato XLSX).
- **Chart.js** para gráficos en el resumen de cantina.
- **Git** para control de versiones.

---

## Estructura del proyecto

```
evospace/
├── config/
│   └── db.php                    # Conexión a BD y constantes SMTP
├── database/
│   └── evospace.sql              # Script completo de instalación de la BD
├── helpers/
│   └── functions.php             # Funciones globales (permisos, correo, formato, cálculos)
├── includes/
│   ├── header.php                # Cabecera HTML y estilos CSS
│   ├── navbar.php                # Menú lateral (offcanvas) con lógica de permisos
│   └── footer.php                # Scripts de Bootstrap
├── roles/
│   ├── admin.php                 # Panel del administrador
│   ├── profesor.php              # Panel del profesor (selección de curso)
│   └── padre.php                 # Panel del padre (resumen, eventos, notificaciones)
├── secciones/
│   ├── alumnos.php               # CRUD de alumnos con tabla y modal
│   ├── pagos.php                 # Registro de pagos con modal y precios dinámicos
│   ├── asistencia/               # Módulo completo de asistencia
│   │   ├── registrar.php         # Registro diario por curso
│   │   ├── guardar.php           # Guardado de asistencia diaria
│   │   ├── ver.php               # Historial de asistencia por curso
│   │   ├── mensual.php           # Vista mensual con checkboxes
│   │   ├── exportar_excel.php    # Exportación a CSV (historial)
│   │   └── exportar_excel_mensual.php # Exportación a XLSX (mensual)
│   ├── eventos/                  # Módulo de eventos
│   │   ├── eventos.php           # CRUD de eventos con filtros
│   │   ├── models/               # Modelos EventoModel y NotificacionModel
│   │   └── marcar_leida.php      # Acción para marcar notificaciones como leídas
│   ├── cantina/                  # Módulo de cantina
│   │   ├── ventas.php            # Listado y formulario de ventas
│   │   ├── productos.php         # CRUD de productos
│   │   ├── resumen.php           # Resumen de caja y gráfico
│   │   └── obtener_ventas_semana.php # API para gráfico
│   ├── configuracion/            # Configuración del sistema
│   │   ├── configuracion.php
│   │   └── configurar_pagos.php  # Precios y porcentaje de beca
│   ├── usuarios.php              # CRUD de usuarios con asignación de permisos
│   ├── profesores.php            # Gestión de profesores (admin)
│   ├── get_hijos.php             # API para asignar hijos a padres
│   ├── obtener_precios.php       # API para precios de un curso
│   └── obtener_pagos.php         # API para ver pagos de un alumno
├── vendor/                       # Librerías externas (PHPMailer, PhpSpreadsheet)
├── index.php                     # Login
├── logout.php                    # Cierre de sesión
└── README.md                     # Este archivo
```

---

## Requisitos previos

- Servidor web con Apache o Nginx.
- PHP 8.0 o superior con extensiones:
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
  - `fileinfo` (para PhpSpreadsheet)
  - `zip` (para exportación a XLSX)
- MySQL 5.7 o MariaDB 10.4+.
- Composer (opcional, solo si se usan las librerías con autoload; en este proyecto están incluidas manualmente).
- Cuenta de correo SMTP (ej. Gmail) con "Contraseña de aplicación" generada (requiere verificación en dos pasos).

---

## Instalación y configuración

1. **Clonar o descargar el código** en el directorio raíz del servidor (ej. `/var/www/html/evospace` o `C:\xampp\htdocs\evospace`).

2. **Crear la base de datos**:
   - Crear una base de datos vacía (ej. `evospace`).
   - Importar el archivo `database/evospace.sql` (puede hacerse con phpMyAdmin, Adminer o desde línea de comandos: `mysql -u root -p evospace < evospace.sql`).

3. **Configurar la conexión a la base de datos**:
   - Editar `config/db.php` y ajustar las credenciales:
     ```php
     $host = 'localhost';
     $dbname = 'evospace';
     $user = 'tu_usuario';
     $pass = 'tu_contraseña';
     ```

4. **Configurar el envío de correos** (necesario para notificaciones de eventos):
   - En el mismo archivo `config/db.php`, completar las constantes SMTP:
     ```php
     define('SMTP_HOST', 'smtp.gmail.com');
     define('SMTP_PORT', 587);
     define('SMTP_USER', 'tucorreo@gmail.com');
     define('SMTP_PASS', 'contraseña_de_aplicacion');
     define('SMTP_FROM', 'tucorreo@gmail.com');
     define('SMTP_FROM_NAME', 'EvoSpace - Escuela');
     ```
   - La `SMTP_PASS` debe ser una **Contraseña de aplicación** de Google (no la contraseña normal). Se genera en la configuración de seguridad de la cuenta de Google (requiere verificación en dos pasos).

5. **Permisos de archivos** (en Linux):
   ```bash
   chmod -R 755 /ruta/a/evospace
   chmod 600 /ruta/a/evospace/config/db.php  # si se quiere proteger
   ```

6. **Acceder al sistema**:
   - Abrir el navegador y entrar a `http://localhost/evospace/`.
   - Usar las credenciales predeterminadas (ver más abajo).

---

## Usuarios predeterminados

| Usuario   | Contraseña | Rol      |
|-----------|------------|----------|
| admin     | admin123   | Admin    |
| profesor  | profe123   | Profesor |
| padre     | padre123   | Padre    |
| auxiliar  | aux123     | Auxiliar |

Las contraseñas están hasheadas en la base de datos con `password_hash()`. Se recomienda cambiarlas después del primer inicio de sesión.

---

## Módulos funcionales

### Gestión de alumnos

- Listado completo con búsqueda en tiempo real.
- Creación y edición mediante modal.
- Campos: nombre, apellido, curso, año de ingreso, horas profesionales (solo para nivel Superior), CI, teléfono, padre/madre asignado, becado, activo.
- Asignación de padres desde el listado de usuarios con rol `padre`.
- Eliminación con confirmación.

### Pagos y recargos

- Registro de pagos para un alumno en un curso específico.
- Conceptos: matrícula, cuota, vestuarios, entradas, folleto (según el tipo de curso).
- Cálculo automático:
  - Si el alumno es becado y se selecciona "cuota", el monto se reduce según el porcentaje de beca global (configurable en `configurar_pagos.php`) y se redondea a la unidad de millar.
  - Si la fecha del pago es posterior al día 10 del mes, se aplica un recargo de 1000 Gs por día de atraso (configurable).
- El total se actualiza dinámicamente en el modal.
- Almacenamiento de: monto base, descuento, recargo, total, método de pago.
- Visualización de pagos por alumno (modal con pestañas para cuotas y otros conceptos).

### Asistencia

- **Registro diario**: selección de curso y fecha, lista de alumnos con switch presente/ausente y campo de observaciones. Por defecto todos los alumnos aparecen como "Presente" si no hay registro previo.
- **Guardado seguro**: al guardar, se eliminan los registros anteriores de ese día y se insertan todos los alumnos del curso (con su estado).
- **Vista mensual**: tabla con todos los días del mes (o solo días con registros, según configuración) y checkboxes para marcar presente/ausente. Permite guardar todo el mes de una vez.
- **Historial**: lista de días con resumen de presentes/ausentes y detalle por alumno.
- **Exportación**:
  - Del historial a CSV.
  - Del resumen mensual a XLSX (Excel real, con columnas de totales y porcentajes).
- Acceso restringido a profesores y administradores.

### Eventos y notificaciones

- **Creación de eventos**: título, fecha, hora, lugar, enlace de Google Maps (opcional), descripción y selección de cursos (múltiples).
- **Notificaciones automáticas**:
  - Al guardar un evento, se buscan los padres de los alumnos de los cursos seleccionados.
  - Se envía un correo a cada padre con los detalles del evento.
  - Se registra una notificación en la base de datos para cada curso (vinculada al evento).
  - Las notificaciones aparecen en el panel del padre, con posibilidad de marcarlas como leídas.
- Edición y eliminación de eventos (actualiza notificaciones y reenvía correos si se edita).

### Cantina

- **Productos**: alta, edición, desactivación. Cada producto tiene nombre y precio.
- **Ventas**: registro de venta con selección de productos (uno o varios), cantidad, método de pago (efectivo, transferencia, fiado), tipo de comprador (alumno, profesor, otro) y nombre del comprador.
- **Resumen de caja**:
  - Ventas de hoy (desglosado por método).
  - Ventas de la última semana (desglosado por método).
  - Total general acumulado.
  - Gráfico de barras con las ventas diarias de los últimos 7 días.
- El listado de ventas incluye buscador por comprador o ID.

### Usuarios y permisos

- **Gestión de usuarios**: CRUD completo con campos: usuario, email, cédula, contraseña, rol, activo.
- **Permisos individuales**: al crear o editar un usuario, se puede marcar una lista de permisos (ver alumnos, editar pagos, gestionar eventos, etc.) que sobrescriben los permisos por defecto del rol.
- **Rol admin** tiene acceso total (no necesita permisos marcados, ya que la función `tienePermiso()` lo trata como verdadero siempre).
- Los permisos se guardan en la tabla `usuarios_permisos` y se cargan al iniciar sesión.

---

## Notas técnicas

- Los precios de las cuotas se redondean a la unidad de millar (múltiplo de 1000) para todos los casos (con o sin beca), para mantener consistencia.
- El porcentaje de beca es global y se aplica solo al concepto "cuota". Se configura en `configurar_pagos.php` y se guarda en `configuracion`.
- El recargo por atraso se calcula en el cliente (JavaScript) y se guarda en la base de datos. El valor por día se obtiene de `configuracion` y se pasa al JS mediante variables PHP.
- La exportación a Excel usa `PhpSpreadsheet` y genera archivos `.xlsx` con formato básico (fuente en negrita en encabezados, colores de fondo, autoajuste de columnas).
- Las notificaciones se envían usando PHPMailer con autenticación SMTP. Si falla el envío, se registra el error en el log de PHP (`error_log`).
- La base de datos incluye restricciones de clave foránea con `ON DELETE CASCADE` para mantener la integridad referencial.
- El sistema utiliza sesiones PHP para mantener el estado del usuario y sus permisos.

---

## Posibles problemas y soluciones

| Problema | Causa probable | Solución |
|----------|----------------|----------|
| Error de conexión a BD | Credenciales incorrectas en `db.php` | Verificar usuario, contraseña, host y nombre de BD. |
| Error al enviar correos | SMTP mal configurado o contraseña de aplicación incorrecta | Usar una Contraseña de Aplicación de Gmail (verificar en Seguridad de Google). |
| No se guarda la asistencia de ausentes | Se omiten los checkboxes desmarcados | El guardado ya fue corregido para insertar todos los alumnos del curso, con estado presente=0 para los ausentes. |
| El navbar no muestra todas las opciones al admin | El permiso del admin no se está cargando en sesión | Verificar que en `index.php` se asigne `$_SESSION['permisos'] = null` para admin. |
| La exportación a Excel da error 500 | Falta la extensión `zip` o `fileinfo` en PHP | Activar esas extensiones en `php.ini`. Si no, usar la exportación a CSV. |
| El gráfico de cantina no se muestra | `obtener_ventas_semana.php` no tiene permisos o hay error en la consulta | Revisar que el usuario tenga permisos y que la tabla `ventas` tenga datos. |
| Los eventos no llegan a los padres | El curso seleccionado no tiene alumnos con padre asignado | Asignar un padre a algún alumno del curso y verificar que su email sea válido. |
| Las notificaciones no aparecen en el panel del padre | El padre no tiene hijos en cursos con notificaciones o la tabla `notificaciones` está vacía | Crear un evento con un curso que tenga hijos del padre. |

---

## Licencia

Este proyecto es de uso interno de la academia. No está licenciado para distribución comercial ni pública sin autorización expresa.

Código fuente y documentación © 2026 - EvoSpace.
