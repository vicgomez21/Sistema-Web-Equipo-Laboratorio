    </main>

    <div class="toast" id="toast" role="status"></div>

    <script src="js/lib/app.js?v=<?= filemtime(__DIR__ . '/../js/lib/app.js') ?>"></script>
    <?php if (!empty($scriptPagina)): ?>
        <script src="js/lib/<?= htmlspecialchars($scriptPagina) ?>?v=<?= filemtime(__DIR__ . '/../js/lib/' . $scriptPagina) ?>"></script>
    <?php endif; ?>
</body>
</html>
