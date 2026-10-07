<?php
/**
 * @var array<string, mixed> $view
 * @var callable(string): string $e
 */
?>
<footer class="footer">
    <span>SSpkS <?= $e($view['version']) ?></span>
    <?php if ($view['commit'] !== ''): ?><span><?= $e($view['commit']) ?></span><?php endif; ?>
</footer>

<script type="application/json" id="data"><?= $view['json'] ?></script>
</body>
</html>
