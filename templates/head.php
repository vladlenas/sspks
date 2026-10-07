<?php
/**
 * @var array<string, mixed> $view
 * @var callable(string): string $e
 * @var string $script Asset file name of the page script.
 */
$asset = static fn (string $name): string => $view['assetBase'] . $name . '?v=' . $view['assetVersion'];
?>
<!doctype html>
<html lang="<?= $e($view['lang']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $e($view['title'] ?? $view['siteName']) ?></title>
    <link rel="icon" href="<?= $e($asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $e($asset('app.css')) ?>">
    <script src="<?= $e($asset($script)) ?>" defer></script>
</head>
