<?php
/**
 * @var array<string, mixed> $view
 * @var callable(string): string $e
 */
$t = $view['t'];
$script = 'app.js';
require $__templates . '/head.php';
?>
<body>
<?php require $__templates . '/header.php'; ?>

<main class="page">
    <section class="source" aria-labelledby="source-label">
        <div class="source__text">
            <h2 id="source-label"><?= $e($t['sourceLabel']) ?></h2>
            <p class="source__hint"><?= $e($t['sourceHint']) ?></p>
        </div>
        <div class="source__field">
            <code id="source-url"><?= $e($view['sourceUrl']) ?></code>
            <button type="button" class="button" id="copy-source" data-copied="<?= $e($t['copied']) ?>"><?= $e($t['copy']) ?></button>
        </div>
    </section>

    <div class="toolbar" id="toolbar" hidden>
        <label class="field field--search">
            <span class="field__label"><?= $e($t['search']) ?></span>
            <input type="search" id="q" autocomplete="off" spellcheck="false" placeholder="<?= $e($t['search']) ?>">
        </label>
        <label class="field">
            <span class="field__label"><?= $e($t['platform']) ?></span>
            <select id="platform"></select>
        </label>
        <label class="field">
            <span class="field__label"><?= $e($t['dsm']) ?></span>
            <select id="dsm">
                <option value=""><?= $e($t['dsmAny']) ?></option>
                <option value="7"><?= $e($t['dsm7']) ?></option>
                <option value="6"><?= $e($t['dsm6']) ?></option>
            </select>
        </label>
        <a class="toolbar__help" href="https://kb.synology.com/en-global/DSM/tutorial/What_kind_of_CPU_does_my_NAS_have" target="_blank" rel="noopener noreferrer"><?= $e($t['platformHelp']) ?></a>
    </div>

    <ul class="packages" id="packages"></ul>
    <div class="empty" id="empty" hidden></div>
    <div class="broken" id="broken" hidden></div>

    <noscript><p class="empty"><?= $e($t['noscript']) ?></p></noscript>
</main>

<?php require $__templates . '/footer.php'; ?>
