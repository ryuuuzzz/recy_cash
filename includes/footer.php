    <!-- FOOTER -->
    <footer class="main-footer">
        <p>&copy; <?= date('Y') ?> RecyCash. Semua hak dilindungi.</p>
    </footer>
</div><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.1/dist/aos.js"></script>
<script src="<?= $baseUrl ?>/assets/js/app.js"></script>
<script>
// Registrasi Service Worker (PWA / mode offline)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= $baseUrl ?>/sw.js').catch(function () {
            // abaikan kegagalan registrasi (mis. server tidak mendukung)
        });
    });
}
</script>

<?php if (!empty($extraJS)): ?>
    <?php foreach ($extraJS as $js): ?>
        <script src="<?= $js ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
