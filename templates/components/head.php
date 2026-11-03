<?php
use Prospak\Core\Config;
use Prospak\Core\View;
?>
<!doctype html>
<html lang="en" dir="ltr" data-bs-theme="light" data-bs-theme-color="theme-color-default">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title><?= View::escape($title ?? 'Portal') ?> | Prospak Academy</title>
 <link rel="icon" href="<?= View::escape(Config::url('/assets/images/favicon.ico')) ?>">
 <link rel="stylesheet" href="<?= View::escape(Config::url('/assets/css/libs.min.css')) ?>">
 <link rel="stylesheet" href="<?= View::escape(Config::url('/assets/css/hope-ui.min.css')) ?>">
 <link rel="stylesheet" href="<?= View::escape(Config::url('/assets/css/custom.min.css')) ?>">
 <link rel="stylesheet" href="<?= View::escape(Config::url('/assets/css/app.css')) ?>">
</head>
