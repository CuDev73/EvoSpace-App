<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/evospace/assets/js/app.js"></script>

<!-- Scroll to top -->
<button id="scrollTopBtn" class="btn btn-evo rounded-circle shadow-sm" onclick="window.scrollTo({top:0,behavior:'smooth'})" style="display:none;position:fixed;bottom:20px;right:20px;width:44px;height:44px;z-index:9999;padding:0;">
    <i class="bi bi-chevron-up"></i>
</button>
<script>
window.addEventListener('scroll', function() {
    const btn = document.getElementById('scrollTopBtn');
    btn.style.display = window.scrollY > 300 ? 'block' : 'none';
});
</script>
</body>
</html>