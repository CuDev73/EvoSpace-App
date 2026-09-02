<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/evospace/assets/js/app.js"></script>

<!-- Scroll to top -->
<button id="scrollTopBtn" class="btn btn-evo rounded-circle shadow-sm scroll-top-btn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Subir">
    <i class="bi bi-chevron-up"></i>
</button>
<script>
(function() {
    const btn = document.getElementById('scrollTopBtn');
    if (!btn) return;
    const barraFija = document.querySelector('.fixed-bottom');
    function actualizar() {
        const visible = window.scrollY > 400;
        btn.classList.toggle('show', visible);
        if (barraFija) {
            const altoBarra = barraFija.offsetHeight || 56;
            btn.style.bottom = (altoBarra + 10) + 'px';
        }
    }
    window.addEventListener('scroll', actualizar, { passive: true });
    window.addEventListener('resize', actualizar);
    actualizar();
})();
</script>

<!-- Modal de confirmación de eliminar -->
<div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>¿Estás seguro?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalConfirmarEliminarTexto">¿Deseas continuar?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminarSi">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    let formPendiente = null;
    window.confirmarEliminar = function(form, mensaje) {
        formPendiente = form;
        const texto = document.getElementById('modalConfirmarEliminarTexto');
        if (texto) texto.textContent = mensaje || '¿Deseas continuar?';
        const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
        modal.show();
        return false;
    };
    const btnSi = document.getElementById('btnConfirmarEliminarSi');
    if (btnSi) {
        btnSi.addEventListener('click', function() {
            const f = formPendiente;
            formPendiente = null;
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmarEliminar'));
            if (modal) modal.hide();
            if (f) f.submit();
        });
    }
    document.querySelectorAll('.btn[data-bs-dismiss="modal"]').forEach(function(btn) {
        if (btn.closest('#modalConfirmarEliminar')) btn.addEventListener('click', function() { formPendiente = null; });
    });
})();
</script>
</body>
</html>