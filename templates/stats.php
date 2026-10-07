<?php
/**
 * @var array<string, mixed> $view
 * @var callable(string): string $e
 */
$t = $view['t'];
$script = 'stats.js';
require $__templates . '/head.php';
?>
<body>
<?php require $__templates . '/header.php'; ?>

<main class="page page--stats">
    <section class="readouts" id="readouts" aria-label="<?= $e($t['statsTitle']) ?>"></section>

    <section class="stats-block" id="server" hidden>
        <h2><?= $e($t['statsDaily']) ?></h2>
        <div class="chart" id="chart"></div>
        <p class="stats-note" id="since"></p>

        <h2><?= $e($t['statsByPackage']) ?></h2>
        <div class="table-scroll">
            <table class="stats-table" id="by-package"></table>
        </div>
    </section>
    <p class="empty" id="server-empty" hidden></p>

    <section class="stats-block" id="github">
        <h2><?= $e($t['statsGithub']) ?></h2>
    </section>

    <noscript><p class="empty"><?= $e($t['noscript']) ?></p></noscript>
</main>

<?php require $__templates . '/footer.php'; ?>
