<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Document') ?></title>
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body style="background:#fff">
<?= $content ?>
<script>window.addEventListener('load', () => { if (new URLSearchParams(location.search).get('print') === '1') window.print(); });</script>
</body>
</html>
