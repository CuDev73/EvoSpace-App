/* ============================================================
   EvoSpace - JavaScript principal
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  // ----------------------------------------------------------
  // 1. Auto-dismiss alertas después de 5 segundos
  // ----------------------------------------------------------
  document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
    setTimeout(function () {
      var bsAlert = bootstrap.Alert ? new bootstrap.Alert(alert) : null;
      if (bsAlert) bsAlert.close();
    }, 5000);
  });

  // ----------------------------------------------------------
  // 2. Buscadores en tablas (input#buscador)
  // ----------------------------------------------------------
  document.querySelectorAll('input[id^="buscador"]').forEach(function (input) {
    input.addEventListener('keyup', function () {
      var filtro = this.value.toLowerCase();
      var tabla = this.closest('.container, .card-body, .row, div').querySelector('table');
      if (!tabla) return;
      var filas = tabla.querySelectorAll('tbody tr');
      filas.forEach(function (fila) {
        fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
      });
    });
  });

  // ----------------------------------------------------------
  // 3. "Seleccionar / Deseleccionar todos" (checkboxes por grupo)
  // ----------------------------------------------------------
  document.querySelectorAll('[data-toggle-all]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var container = this.closest('.mb-3, .card-body, div');
      var checkboxes = container.querySelectorAll('input[type="checkbox"]');
      var todasMarcadas = Array.from(checkboxes).every(function (cb) { return cb.checked; });
      checkboxes.forEach(function (cb) { cb.checked = !todasMarcadas; });
      this.textContent = todasMarcadas ? this.getAttribute('data-label-all') : this.getAttribute('data-label-none');
    });
  });

  // ----------------------------------------------------------
  // 4. Confirmación en formularios de eliminación
  // ----------------------------------------------------------
  document.querySelectorAll('form[onsubmit*="confirm"]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var msg = this.getAttribute('data-confirm') || '¿Estás seguro?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // ----------------------------------------------------------
  // 5. Tooltips (si se usan)
  // ----------------------------------------------------------
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

});

// ------------------------------------------------------------
// Función global: Obtener parámetros de URL
// ------------------------------------------------------------
function getParam(name) {
  var params = new URLSearchParams(window.location.search);
  return params.get(name) || '';
}

// ------------------------------------------------------------
// Función global: Formatear número con separador de miles
// ------------------------------------------------------------
function formatNumber(n) {
  return Number(n).toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
