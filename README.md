# EvoSpace

Sistema de gestión integral para academias de danza, artes escénicas y centros formativos.

Lo desarrollé pensando en unificar todo el manejo diario de una academia en un solo lugar: alumnos, pagos, asistencia, eventos, la cantina, las rifas/entradas y los usuarios con sus permisos. Antes esto se llevaba en planillas Excel, libretas de asistencia, cuadernos de pagos y correos sueltos; ahora todo vive en una aplicación web a la que se accede desde cualquier navegador.

---

## Índice

- [Descripción general](#descripción-general)
- [Perfiles del sistema](#perfiles-del-sistema)
- [Características principales](#características-principales)
- [Tecnologías utilizadas](#tecnologías-utilizadas)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Modelo de permisos](#modelo-de-permisos)
- [Requisitos previos](#requisitos-previos)
- [Instalación y configuración](#instalación-y-configuración)
- [Usuarios predeterminados](#usuarios-predeterminados)
- [Módulos funcionales](#módulos-funcionales)
- [Notas técnicas](#notas-técnicas)
- [Migraciones de base de datos](#migraciones-de-base-de-datos)
- [Posibles problemas y soluciones](#posibles-problemas-y-soluciones)
- [Licencia](#licencia)

---

## Descripción general

EvoSpace nació de una necesidad concreta: la academia manejaba sus áreas operativas con herramientas separadas y eso generaba errores, pérdida de tiempo y datos desorganizados. El sistema centraliza:

- Registro y seguimiento de **alumnos** por nivel y curso.
- **Pagos** con cuotas, conceptos, descuentos por beca y recargos por atraso.
- **Asistencia** diaria y mensual con exportación.
- **Eventos** y notificaciones por correo a los tutores.
- **Cantina**: productos, ventas, fiado y ganancias.
- **Rifas / entradas**: lotes, distribución y control de ventas.
- **Usuarios** con roles y permisos granulares.

La idea central es que cada persona vea **solo lo que necesita** para su tarea, sin pantallas vacías ni datos ajenos.

---

## Perfiles del sistema

| Perfil | Acceso principal |
|--------|------------------|
| **Admin** | Control total: todas las secciones, estadísticas y configuración. Ve el panel completo. |
| **Auxiliar** | Panel de inicio adaptado a sus permisos (por ejemplo, un cantinero ve solo su sección de cantina). |
| **Profesor** | Selección de cursos y registro de asistencia (diario, mensual, historial y exportación). |
| **Tutor/a (padre)** | Eventos de los cursos de sus hijos, notificaciones y resumen de deudas por mes. |

Los permisos no dependen solo del rol: cada usuario puede tener una lista propia de permisos, lo que permite perfiles muy específicos (un auxiliar que solo vea cantina, otro que también vea configuración, etc.).

---

## Características principales

- **Gestión completa de alumnos**: alta, baja, edición, asignación de curso, becas, datos de contacto, relación con el tutor/a y **ficha del alumno** con toda su información en un solo lugar.
- **Pagos inteligentes**: cálculo de cuotas con descuento por beca (redondeo al millar), recargos automáticos por atraso, y conceptos variables según el nivel (matrícula, cuota, vestuarios, entradas, folleto).
- **Ficha del alumno**: vista integral con curso, tutor/a, deuda actual, cantina, rifas/entradas, pagos históricos, asistencia y acciones (avanzar de curso, registrar pago, recordatorios).
- **Registro de pagos**: desde la ficha se registran pagos de cuota, folleto, venta de entradas, matrícula o vestuarios, con cálculo automático de recargo y monto total.
- **Asistencia diaria y mensual**: registro por curso y día, vista mensual con checkboxes, historial y exportación a Excel (XLSX real con PhpSpreadsheet) o CSV.
- **Eventos y notificaciones**: creación de eventos asociados a cursos, envío automático por correo a los tutores de esos cursos y registro en la base de datos que se muestra en el panel del tutor/a.
- **Cantina**: productos con stock y precios, ventas con tipo de comprador (alumno, profesor, tutor/a, otro), método de pago (efectivo, transferencia, tarjeta, fiado), control de cobros pendientes/parciales y resumen de caja con ganancias.
- **Rifas / entradas**: creación de lotes por curso y evento, distribución de unidades a los alumnos, control de cuántas le quedan a cada uno y registro de la venta desde la ficha.
- **Usuarios y permisos granulares**: roles fijos más permisos individuales por usuario, administrados desde un panel con tarjetas por rol.
- **Panel de inicio adaptado (perm-aware)**: el admin ve el panel completo; los auxiliares (como el cantinero) ven un saludo de bienvenida y solo las tarjetas de las secciones que tienen habilitadas.
- **Configuración del sistema**: precios por curso, beca, vencimiento y recargo, datos del recibo, plantilla del correo de eventos y recordatorio mensual de deudas.
- **Interfaz moderna y responsive**: Bootstrap 5 con barra de navegación lateral (offcanvas) adaptada a móviles.

---

## Tecnologías utilizadas

- **PHP 8.0+** con PDO para conexión segura a la base de datos (consultas preparadas contra inyección SQL).
- **MySQL / MariaDB** como motor de base de datos relacional.
- **Bootstrap 5** para el frontend (CSS y componentes).
- **Bootstrap Icons** para la iconografía.
- **JavaScript vanilla** para interactividad: cálculos en tiempo real, buscadores, modales y gráficos.
- **PHPMailer** para envío de correos SMTP (Gmail, Outlook, etc.).
- **PhpSpreadsheet** para exportación a Excel (formato XLSX) y **FPDF / mPDF** para recibos PDF.
- **Chart.js** para los gráficos del dashboard y de la cantina.
- **Git** para control de versiones.

---

## Estructura del proyecto

```
evospace/
├── config/
│   ├── db.php                    # Conexión a BD y constantes SMTP
│   └── .env                      # Variables de entorno (si se usa)
├── database/
│   ├── evospace.sql              # Script completo de instalación de la BD
│   ├── backup.sh                 # Script de respaldo
│   └── migraciones/              # Migraciones incrementales (faseN_*.sql)
├── helpers/
│   ├── functions.php             # Funciones globales (permisos, correo, formato, cálculos)
│   └── asistencia.php            # Lógica de asistencia
├── includes/
│   ├── header.php                # Cabecera HTML y estilos CSS
│   ├── navbar.php                # Menú lateral con lógica de permisos (oculta grupos vacíos)
│   ├── footer.php                # Scripts de Bootstrap
│   └── curso_picker.php          # Selector de curso reutilizable
├── roles/
│   ├── admin.php                 # Panel del administrador / panel adaptado para auxiliares
│   ├── profesor.php              # Panel del profesor (cursos y asistencia)
│   └── padre.php                 # Panel del tutor/a (hijos, deudas, eventos)
├── secciones/
│   ├── alumnos.php               # CRUD de alumnos
│   ├── inscripciones.php         # Alta de nuevos alumnos
│   ├── ficha_alumno.php          # Ficha integral del alumno (pagos, rifas, deudas)
│   ├── horarios.php              # Gestión de horarios por curso
│   ├── profesores.php            # Gestión de profesores y abonos
│   ├── usuarios.php              # Usuarios, roles y permisos
│   ├── pagos.php / recibo.php*   # Registro de pagos y recibos PDF
│   ├── asistencia/               # Módulo completo de asistencia
│   ├── eventos/                  # Módulo de eventos y notificaciones
│   ├── entradas/                 # Rifas / entradas (lotes y distribución)
│   ├── cantina/                  # Módulo de cantina (productos, ventas, resumen)
│   └── configuracion/            # Panel de configuración del sistema
├── assets/
│   └── css/estilos.css           # Estilos personalizados
├── uploads/                      # Archivos subidos (pagos, logo, recibo)
├── vendor/                       # Librerías externas (PHPMailer, PhpSpreadsheet, mPDF)
├── index.php                     # Login
└── logout.php                    # Cierre de sesión
```

---

## Modelo de permisos

El sistema combina **roles** y **permisos individuales**:

- Cada usuario pertenece a un **rol** (`admin`, `profesor`, `padre`, `auxiliar`).
- El rol define permisos por defecto, pero se pueden **afinar por usuario**: la tabla `usuarios_permisos` guarda una lista propia de permisos que sobrescriben/complementan los del rol.
- El **admin** tiene acceso total: la función `tienePermiso()` devuelve siempre `true` para él, sin necesidad de marcar permisos.
- Al iniciar sesión, los permisos del usuario se cargan en la sesión y se consultan en cada sección con `tienePermiso()` / `verificarPermiso()`.

Esto permite, por ejemplo, un **cantinero** (rol `auxiliar`) con permisos únicamente de `cantina` y `configuracion`: inicia en un panel donde solo ve las tarjetas de "Ver Cantina" y "Configuración", y la barra de navegación **oculta automáticamente los grupos donde no tiene ninguna sección habilitada**.

Permisos disponibles (catálogo en la tabla `permisos`):
`alumnos`, `pagos`, `profesores`, `eventos`, `cantina`, `asistencia`, `configuracion`, `usuarios`, `gestionar_usuarios`, `horarios`.

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
- Composer (opcional; las librerías están incluidas en `vendor/`).
- Cuenta SMTP (ej. Gmail) con "Contraseña de aplicación" para el envío de correos.

---

## Instalación y configuración

1. **Clonar o descargar el código** en el directorio raíz del servidor (ej. `/var/www/html/evospace` o `C:\xampp\htdocs\evospace`).

2. **Crear la base de datos**:
   - Crear una base vacía (ej. `evospace`).
   - Importar `database/evospace.sql`:
     ```bash
     mysql -u root -p evospace < database/evospace.sql
     ```

3. **Configurar la conexión** en `config/db.php`:
   ```php
   $host = 'localhost';
   $dbname = 'evospace';
   $user = 'tu_usuario';
   $pass = 'tu_contraseña';
   ```

4. **Configurar el correo SMTP** en `config/db.php` (necesario para notificaciones de eventos y recordatorios):
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'tucorreo@gmail.com');
   define('SMTP_PASS', 'contraseña_de_aplicacion');
   define('SMTP_FROM', 'tucorreo@gmail.com');
   define('SMTP_FROM_NAME', 'EvoSpace - Academia');
   ```
   La `SMTP_PASS` debe ser una **Contraseña de aplicación** de Google (requiere verificación en dos pasos).

5. **Permisos de archivos** (en Linux):
   ```bash
   chmod -R 755 /ruta/a/evospace
   chmod 600 /ruta/a/evospace/config/db.php
   ```

6. **Acceder al sistema** en `http://localhost/evospace/` con las credenciales predeterminadas (ver más abajo).

---

## Usuarios predeterminados

| Usuario   | Contraseña | Rol      |
|-----------|------------|----------|
| admin     | admin123   | Admin    |
| profesor  | profe123   | Profesor |
| padre     | padre123   | Padre    |
| auxiliar  | aux123     | Auxiliar |

Las contraseñas están hasheadas con `password_hash()`. **Cambialas después del primer ingreso.**

---

## Módulos funcionales

### Gestión de alumnos (`alumnos.php`, `inscripciones.php`)

- Listado con búsqueda en tiempo real.
- Alta (`inscripciones.php`) y edición mediante modal.
- Campos: nombre, apellido, curso, año de ingreso, horas profesionales (solo Superior), CI, teléfono, tutor/a asignado, becado y activo.
- Asignación de tutor/a desde el listado de usuarios con rol `padre`, y correspondencia con sus hijos.
- Eliminación con confirmación.

### Ficha del alumno (`ficha_alumno.php`)

Vista integral de cada alumno:

- Datos personales, curso actual, tutor/a y día de cobro.
- **Avanzar de curso**: botón para pasar al siguiente nivel/módulo del mismo tipo.
- **Registrar pago / venta**: botón siempre visible que abre el modal de pago con selector de concepto (Cuota, Folleto, Entradas, Matrícula, Vestuarios). Para entradas/rifas se elige el lote y se descuenta la cantidad vendida.
- Tarjetas de estado: total pagado, deuda de cantina, rifas/entradas y deuda actual de la cuota.
- Listado de pagos históricos, asistencia y horas profesionales (nivel Superior).

### Pagos y recargos

- Conceptos por tipo de curso: `matrícula`, `cuota`, `vestuarios`, `entradas`, `folleto`.
- Cálculo automático:
  - Alumno becado → descuento por beca (porcentaje global configurable) redondeado a la unidad de millar.
  - Fecha posterior al día límite + días de gracia → recargo por día de atraso.
  - Total = (monto × cantidad) + recargo, actualizado en vivo.
- Registro de imágenes de comprobante (opcional).
- Generación de **recibos PDF**.

### Asistencia (`asistencia/`)

- **Registro diario**: curso + fecha, lista de alumnos con switch presente/ausente y observaciones.
- **Vista mensual**: tabla con días del mes y checkboxes; guardado del mes completo de una vez.
- **Historial**: días con resumen de presentes/ausentes y detalle por alumno.
- **Exportación**: historial a CSV y resumen mensual a XLSX (Excel real con totales y porcentajes).
- Acceso restringido a profesores y administradores.

### Horarios (`horarios.php`)

- Asignación de horarios por curso: día(s) de la semana, hora de inicio/fin y profesor.
- Se muestran en la ficha del alumno y se consultan desde el panel del profesor.

### Eventos y notificaciones (`eventos/`)

- **Creación de eventos**: título, fecha, hora, lugar, enlace (opcional), descripción y cursos asociados (múltiples).
- **Notificaciones automáticas**: al guardar, se envían correos a los tutores de los alumnos de los cursos y se registra la notificación en la BD (visible en el panel del tutor/a, con opción de marcarla como leída).
- Edición y eliminación de eventos (actualiza notificaciones y reenvía correos si se edita).

### Cantina (`cantina/`)

- **Productos**: alta, edición, desactivación, con nombre, categoría, precio de venta, precio de compra y stock.
- **Nueva venta**: carrito de productos, cantidades, método de pago (efectivo, transferencia, tarjeta, fiado) y tipo de comprador (alumno, profesor, tutor/a, otro) con búsqueda de comprador.
- **Ventas / historial**: filtros por fecha, comprador, tipo y estado (pagado, pendiente, parcial); cobro total o parcial; anulación (devuelve stock).
- **Fiado**: las ventas a crédito generan deuda para el alumno (se refleja en la ficha y en el panel del tutor/a).
- **Resumen y ganancias**: por período, con ingresos, costos, ganancia por producto y exportación, además de un gráfico de ventas.

### Rifas / Entradas (`entradas/`)

- **Lotes**: creación por curso y evento, con cantidad y precio unitario; estados `activa` / `cerrada`.
- **Distribución**: asignar unidades de rifas a cada alumno del curso, validando que la suma no exceda el lote.
- **Venta**: desde la ficha del alumno se registra la venta de rifas como un pago de concepto `entradas`, descontando la cantidad vendida y mostrando el progreso (ej. "0/5").
- En la ficha, la tarjeta de rifas indica cuántas unidades tiene y cuántas se vendieron.

### Usuarios y roles (`usuarios.php`)

- **CRUD de usuarios**: usuario, email, cédula, contraseña, rol, activo.
- **Permisos individuales**: al crear o editar, se marcan permisos que sobrescriben los del rol.
- **Vista por rol**: tarjetas que agrupan a los usuarios por rol (Admin, Profesor, Tutor/a, Auxiliar) con acciones por usuario y modal de permisos.
- **Panel de inicio adaptado**: los auxiliares ven un saludo de bienvenida y las tarjetas de las secciones que tienen permitidas.

### Configuración (`configuracion/`)

- **Pagos** (`configurar_pagos.php`): precios de todos los conceptos por curso, porcentaje de beca global, días de gracia, día límite y recargo por día.
- **Recibo** (`configurar_recibo.php`): nombre de la academia, RUC, título, mensaje y pie del recibo PDF, más el logotipo.
- **Correo de eventos** (`configurar_correo.php`): saludo, mensaje, firma y remitente.
- **Recordatorio de deudas** (`configurar_recordatorio.php`): envío mensual por correo a tutores con deudas (cuota y cantina), con plantilla editable y envío manual.
- **Horas profesionales**: límite de horas para el nivel Superior (desde el panel de configuración).

---

## Notas técnicas

- Los precios de las cuotas se redondean a la unidad de millar (múltiplo de 1000) para mantener consistencia.
- El porcentaje de beca es global y aplica solo al concepto "cuota"; se configura y guarda en `configuracion`.
- El recargo por atraso se calcula en el cliente (JS) con los valores de configuración y se persiste en el pago.
- Las ventas de rifas descuentan primero las unidades restantes del alumno (`entradas_alumno.cantidad`) y guardan el total asignado en `cantidad_total`.
- La exportación a Excel usa PhpSpreadsheet (`.xlsx`) y los recibos usan bibliotecas de PDF (mPDF/FPDF).
- Se usan consultas preparadas con PDO en toda la aplicación para prevenir inyección SQL.
- Las notificaciones se envían con PHPMailer (SMTP); si falla, se registra en `error_log`.
- La base de datos usa claves foráneas con `ON DELETE CASCADE` para mantener la integridad referencial.
- Las sesiones PHP mantienen el estado del usuario y sus permisos.
- El menú lateral es dinámico y **oculta automáticamente los grupos sin secciones permitidas** para el usuario logueado.

---

## Migraciones de base de datos

Las tablas nuevas se agregan de forma incremental en `database/migraciones/`:

| Archivo | Contenido |
|---------|-----------|
| `fase4_distribucion_pagos.sql` | Distribución de pagos / conceptos por curso. |
| `fase5_recargos.sql` | Recargos por atraso y configuración. |
| `fase8_cantina_categorias.sql` | Categorías de productos de cantina. |
| `fase9_pago_parcial.sql` | Pagos parciales y estado de ventas. |
| `fase9_recordatorio_eventos.sql` | Recordatorios de eventos. |
| `fase11_fiado.sql` | Venta fiada / deuda de cantina. |
| `fase12_config_correo.sql` | Plantilla del correo de eventos. |
| `fase13_dia_cobro.sql` | Día de cobro por tutor/a. |
| `fase14_recordatorio_deuda.sql` | Recordatorio mensual de deudas. |

Si importás `evospace.sql` desde cero, ya incluye la última versión del esquema; las migraciones son para actualizar instalaciones existentes.

---

## Posibles problemas y soluciones

| Problema | Causa probable | Solución |
|----------|----------------|----------|
| Error de conexión a BD | Credenciales incorrectas en `db.php` | Verificar host, usuario, contraseña y nombre de BD. |
| Error al enviar correos | SMTP mal configurado o contraseña de aplicación incorrecta | Usar una Contraseña de Aplicación de Gmail. |
| No se guarda la asistencia de ausentes | Se omiten los checkboxes desmarcados | El guardado inserta todos los alumnos del curso, con `presente=0` para los ausentes. |
| El navbar no muestra todas las opciones al admin | El permiso del admin no está cargado en sesión | Verificar que en `index.php` se asigne `$_SESSION['permisos'] = null` para admin. |
| Un auxiliar no ve sus secciones en el inicio | Permisos no asignados en `usuarios_permisos` | Marcar los permisos correspondientes en el modal del usuario. |
| La exportación a Excel da error 500 | Faltan las extensiones `zip` o `fileinfo` | Activarlas en `php.ini`; si no, usar CSV. |
| El gráfico de cantina no se muestra | `obtener_ventas_semana.php` sin permisos o tabla `ventas` vacía | Revisar permisos y que existan ventas. |
| Los eventos no llegan a los tutores | El curso no tiene alumnos con tutor/a asignado | Asignar un tutor/a con email válido a los alumnos del curso. |
| Las rifas no descuentan al vender | No se eligió el lote en el modal de pago | Seleccionar el lote en "Entradas (rifas)" y la cantidad. |

---

## Licencia

Este proyecto es de uso interno de la academia. No está licenciado para distribución comercial ni pública sin autorización expresa.

Código fuente y documentación © 2026 - EvoSpace.
