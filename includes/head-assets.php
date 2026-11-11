<?php
$asset_base = $asset_base ?? '';
$include_charts = $include_charts ?? false;
if (!function_exists('csrfToken')) {
    require_once __DIR__ . '/auth.php';
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
<script>
(function () {
    try {
        var t = localStorage.getItem('denthub-theme');
        if (!t) {
            t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.setAttribute('data-bs-theme', t);
    } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($asset_base); ?>assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?php echo htmlspecialchars($asset_base); ?>assets/js/app.js" defer></script>
<script src="<?php echo htmlspecialchars($asset_base); ?>assets/js/theme.js" defer></script>
<?php if (!empty($include_charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
