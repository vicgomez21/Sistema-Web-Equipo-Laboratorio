    </main>

    <div class="toast" id="toast" role="status"></div>

    <script src="js/lib/app.js"></script>
    <?php if (!empty($scriptPagina)): ?>
        <script src="js/lib/<?= htmlspecialchars($scriptPagina) ?>"></script>
    <?php endif; ?>
</body>
</html>
