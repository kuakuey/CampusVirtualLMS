</div>
</main>

<?php if (usuario_actual()): ?>
<footer class="app-footer">
    <div class="container-fluid px-3 px-lg-4 py-3 d-flex flex-wrap justify-content-between gap-2">
        <span>&copy; <?= date('Y') ?> <?= escapar(NOMBRE_APP) ?> · Plataforma LMS</span>
        <span class="text-muted">sistema web CDA</span>
    </div>
</footer>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= URL_APP ?>/assets/js/app.js"></script>
</body>
</html>
