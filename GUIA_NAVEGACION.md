# EvoSpace — Guía de navegación de usuario

Sistema de gestión escolar del **Instituto Evolución Arte**.

## 1. Acceso

1. Abrí el navegador y entrá a la dirección del sistema.
2. Ingresá tu **usuario** y **contraseña** (te los asigna el administrador).
3. Presioná **Entrar**. El sistema te lleva directamente a tu panel según tu rol.

> Si la contraseña es olvidada, el administrador puede restablecerla desde
> **Usuarios**.

## 2. Barra superior (navbar)

Visible en todas las pantallas.

- **Logo EvoSpace** (centro): te lleva a tu panel de inicio.
- **Menú (≡)**: abre el panel lateral con las secciones que tenés habilitadas.
- **Botón ← (Volver)**: aparece en pantallas internas para volver a la pantalla anterior.

## 3. Roles y qué ve cada uno

Cada usuario ve **solo las secciones habilitadas para su rol**. El
administrador define los permisos desde **Usuarios**.

### Admin (administrador)
Panel completo de gestión:

- **Resumen**: alumnos activos, recaudación del mes, asistencia del día.
- **Alumnos / Inscripciones**: alta, edición, fichas y avance de curso.
- **Asistencia**: registro diario y resumen mensual por curso.
- **Horarios**: horarios por curso y profesor.
- **Profesores**: cargas y salarios (con abono de saldo pendiente).
- **Cantina**: ventas, historial y deudores (cobro de saldos fiados).
- **Entradas / Rifas** y **Eventos**.
- **Usuarios** y **Configuración** (permisos, pagos, recordatorios).
- Accede además a **todos** los paneles (profesor, padre, auxiliar) si es necesario.

### Auxiliar
Panel propio (separado del admin) con las secciones que el administrador
habilite, típicamente:

- **Asistencia**: registrar y consultar.
- **Alumnos / Inscripciones**: consultar fichas de alumnos.
- **Cantina**: ventas y cobro de deudores.
- **Horarios** y **Eventos**.

No puede administrar usuarios ni configuración a menos que se le otorgue.

### Profesor
Panel docente centrado en asistencia:

- Seleccionar **curso** y **fecha** para cargar o editar la asistencia del día.
- Ver el **resumen mensual** de asistencia de sus cursos.
- Ver **horarios** de sus cursos.

### Padre / Tutor
Panel para seguimiento del alumno:

- Ver la **ficha** de su/s hijo/s (cuotas, pagos, asistencia).
- Ver **recibos** y deudas pendientes.
- Consultar **horarios** del curso del alumno.

---

## 4. Secciones principales

### Asistencia
1. Entrá en **Asistencia** desde el menú.
2. Elegí **curso** y **fecha**.
3. Marcá **Presente / Ausente** (o justificado) por alumno.
4. Guardá. El sistema permite corregir la asistencia de días anteriores.

### Alumnos e Inscripciones
1. **Alumnos**: buscador y lista de alumnos activos; abrí la **ficha** de cada uno.
2. **Inscripciones**: alta de un alumno nuevo (datos personales, curso, padre/tutor vinculado).

### Cantina
- **Vender**: registrá la venta indicando producto y método de pago
  (*Efectivo* o *Fiado*).
- **Historial**: todas las ventas pagadas con el total cobrado hoy/mes.
- **Deudores**: alumnos con saldo fiado pendiente; usá **Cobrar** para
  registrar el abono (el monto se autocompleta con el saldo).

### Profesores (solo admin)
- Carga del profesor y su asignación de horas.
- **Salarios**: sueldo mensual y abonos. Al abrir el modal el monto se
  autocompleta con el saldo pendiente; se puede modificar.

### Entradas / Rifas y Eventos
- Registro de entradas vendidas y eventos del instituto (fecha, precio).

### Usuarios (solo admin)
- Alta de usuarios con **rol** (admin, profesor, padre, auxiliar).
- Asignación de **permisos** por sección.
- Restablecimiento de contraseña y estado (activo/inactivo).

### Configuración (solo admin)
- Datos de la institución, precio de cuotas, métodos de pago y
  recordatorios automáticos de deuda.

---

## 5. Consejos rápidos

- Usá el **buscador** de alumnos para ir directo a una ficha.
- Los montos se muestran en **guaraníes (Gs)** con separador de miles.
- Si una sección no aparece en tu menú, no tenés permiso para verla:
  contactá al administrador.
- Cerrá sesión siempre en equipos compartidos (botón **Salir** del menú).

---

## Cambios recientes

- **Ventas de cantina** reorganizadas: historial (solo pagadas), resumen de
  cobros y página propia de **Deudores** con botón *Cobrar*.
- Acuenta de salario de profesores: el monto del abono se **autocompleta**
  con el saldo pendiente.
- **Nuevo rol Auxiliar**: panel propio (antes caía en el panel de admin);
  el auxiliar ve solo las secciones que el administrador le habilite.