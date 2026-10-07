<?php
/**
 * @var array<string, mixed> $view
 * @var callable(string): string $e
 */
$t = $view['t'];
$asset = static fn (string $name): string => $view['assetBase'] . $name . '?v=' . $view['assetVersion'];
$bays = $view['betaFlags'];
$shownBays = array_slice($bays, 0, 16);
?>
<!doctype html>
<html lang="<?= $e($view['lang']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $e($view['siteName']) ?></title>
    <link rel="icon" href="<?= $e($asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $e($asset('app.css')) ?>">
    <script src="<?= $e($asset('app.js')) ?>" defer></script>
</head>
<body>
<header class="faceplate">
    <div class="faceplate__inner">
        <div class="faceplate__label">
            <h1><?= $e($view['siteName']) ?></h1>
            <p class="faceplate__count" id="count"><?= $e(\SSpkS\WebPage::plural($view['lang'], $view['count'], $t)) ?></p>
        </div>
        <div class="bays" id="bays" aria-hidden="true">
            <?php foreach ($shownBays as $beta): ?>
                <span class="bay<?= $beta ? ' bay--beta' : '' ?>"><span class="bay__led"></span></span>
            <?php endforeach; ?>
            <?php if (count($bays) > count($shownBays)): ?>
                <span class="bays__more">+<?= count($bays) - count($shownBays) ?></span>
            <?php endif; ?>
        </div>
    </div>
</header>

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

<footer class="footer">
    <span>SSpkS <?= $e($view['version']) ?></span>
    <?php if ($view['commit'] !== ''): ?><span><?= $e($view['commit']) ?></span><?php endif; ?>
</footer>

<script type="application/json" id="data"><?= $view['json'] ?></script>
</body>
</html>
