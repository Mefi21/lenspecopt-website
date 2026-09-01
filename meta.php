<?php
// meta.php
// Использование:
// $page_title = '...';
// $page_desc  = '...';
// include 'meta.php';

$_site_name = cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ');
$_keywords  = cfg($db, 'seo_keywords', 'крепеж, метизы, болты, гайки, шайбы, анкеры');
$_gverify   = cfg($db, 'google_verification', '');
$_favicon   = cfg($db, 'favicon', '');
$_og_image  = cfg($db, 'seo_og_image', '');

// Если страница не передала title/description
if (!isset($page_title) || trim((string)$page_title) === '') {
    $page_title = $_site_name;
}

if (!isset($page_desc) || trim((string)$page_desc) === '') {
    $page_desc = cfg($db, 'seo_description', 'Оптовые поставки крепежа и метизов в Санкт-Петербурге.');
}

// Надежное определение HTTPS
$_is_https =
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

$_scheme = $_is_https ? 'https' : 'http';
$_host   = $_SERVER['HTTP_HOST'] ?? 'lsopt.ru';
$_uri    = $_SERVER['REQUEST_URI'] ?? '/';

$_base_url = $_scheme . '://' . $_host;
$_full_url = $_base_url . $_uri;

// ===== OG image =====
$_og_image_url  = '';
$_og_image_type = '';
$_og_width      = '1200';
$_og_height     = '630';

if (!empty($_og_image)) {
    $_og_file_path = __DIR__ . '/' . ltrim($_og_image, '/');

    if (is_file($_og_file_path)) {
        $_og_image_url = $_base_url . '/' . ltrim($_og_image, '/');

        $ext = strtolower(pathinfo($_og_file_path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $_og_image_type = 'image/jpeg';
        } elseif ($ext === 'png') {
            $_og_image_type = 'image/png';
        } elseif ($ext === 'webp') {
            $_og_image_type = 'image/webp';
        }

        $imgSize = @getimagesize($_og_file_path);
        if (is_array($imgSize) && !empty($imgSize[0]) && !empty($imgSize[1])) {
            $_og_width  = (string)$imgSize[0];
            $_og_height = (string)$imgSize[1];
        }
    }
}

// ===== Favicon =====
// Сначала пробуем брать favicon из корня сайта:
// /favicon.ico или /favicon.png
// Если их нет — берем из настройки админки
$_favicon_url  = '';
$_favicon_type = '';

$_root_favicon_ico = __DIR__ . '/favicon.ico';
$_root_favicon_png = __DIR__ . '/favicon.png';

if (is_file($_root_favicon_ico)) {
    $_favicon_url  = '/favicon.ico';
    $_favicon_type = 'image/x-icon';
} elseif (is_file($_root_favicon_png)) {
    $_favicon_url  = '/favicon.png';
    $_favicon_type = 'image/png';
} elseif (!empty($_favicon)) {
    $_fav_file_path = __DIR__ . '/' . ltrim($_favicon, '/');
    if (is_file($_fav_file_path)) {
        $_favicon_url = '/' . ltrim($_favicon, '/');

        $ext = strtolower(pathinfo($_fav_file_path, PATHINFO_EXTENSION));
        if ($ext === 'ico') {
            $_favicon_type = 'image/x-icon';
        } elseif ($ext === 'svg') {
            $_favicon_type = 'image/svg+xml';
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $_favicon_type = 'image/jpeg';
        } elseif ($ext === 'webp') {
            $_favicon_type = 'image/webp';
        } else {
            $_favicon_type = 'image/png';
        }
    }
}
?>

<meta name="description" content="<?= htmlspecialchars($page_desc, ENT_QUOTES, 'UTF-8') ?>">
<meta name="keywords" content="<?= htmlspecialchars($_keywords, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= htmlspecialchars($_full_url, ENT_QUOTES, 'UTF-8') ?>">

<?php if (!empty($_gverify)): ?>
<meta name="google-site-verification" content="<?= htmlspecialchars($_gverify, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($page_desc, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:url" content="<?= htmlspecialchars($_full_url, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:site_name" content="<?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:locale" content="ru_RU">

<?php if ($_og_image_url): ?>
<meta property="og:image" content="<?= htmlspecialchars($_og_image_url, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image:secure_url" content="<?= htmlspecialchars($_og_image_url, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($_og_image_type): ?>
<meta property="og:image:type" content="<?= htmlspecialchars($_og_image_type, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<meta property="og:image:width" content="<?= htmlspecialchars($_og_width, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image:height" content="<?= htmlspecialchars($_og_height, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image:alt" content="<?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_desc, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($_og_image_url, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>

<!-- Favicon -->
<?php if ($_favicon_url): ?>
<link rel="icon" type="<?= htmlspecialchars($_favicon_type, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($_favicon_url, ENT_QUOTES, 'UTF-8') ?>">
<link rel="shortcut icon" type="<?= htmlspecialchars($_favicon_type, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($_favicon_url, ENT_QUOTES, 'UTF-8') ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars($_favicon_url, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>

<!-- JSON-LD: LocalBusiness -->
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ'),
    'url' => $_base_url,
    'telephone' => cfg($db, 'phone1', ''),
    'email' => cfg($db, 'email1', ''),
    'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => cfg($db, 'seo_city', 'Санкт-Петербург'),
        'addressCountry' => 'RU',
        'streetAddress' => cfg($db, 'address', '')
    ],
    'openingHours' => array_values(array_filter([
        cfg($db, 'hours1', ''),
        cfg($db, 'hours2', '')
    ]))
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<!-- JSON-LD: WebSite -->
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'url' => 'https://lsopt.ru/',
    'name' => 'ЛЕНСПЕЦОПТ',
    'alternateName' => ['Ленспецопт']
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>