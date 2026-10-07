<?php
/**
 * Front panel shared by all pages. Needs from $view: siteName, subtitle,
 * betaFlags, navLink (null or ['href', 'label']).
 *
 * @var array<string, mixed> $view
 * @var callable(string): string $e
 */
$bays = $view['betaFlags'];
$shownBays = array_slice($bays, 0, 16);
?>
<header class="faceplate">
    <div class="faceplate__inner">
        <div class="faceplate__label">
            <h1><?= $e($view['siteName']) ?></h1>
            <p class="faceplate__count" id="count"><?= $e($view['subtitle']) ?></p>
            <?php if ($view['navLink'] !== null): ?>
                <a class="faceplate__nav" href="<?= $e($view['navLink']['href']) ?>"><?= $e($view['navLink']['label']) ?></a>
            <?php endif; ?>
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
