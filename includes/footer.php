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
</body>
</html>