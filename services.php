<?php
require_once __DIR__ . '/db.php';

$category = trim($_GET['cat'] ?? '');
$service_categories = $db->query("SELECT slug, name, sort_order FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$service_category_map = [];
foreach ($service_categories as $row) {
    $service_category_map[$row['slug']] = $row;
}

$services = [];
$category_row = null;
if ($category && isset($service_category_map[$category])) {
    $category_row = $service_category_map[$category];
    $st = $db->prepare("SELECT * FROM services WHERE is_active = 1 AND category_slug = ? ORDER BY sort_order, title");
    $st->execute([$category]);
    $services = $st->fetchAll(PDO::FETCH_ASSOC);
}

$counts = [];
foreach ($db->query("SELECT category_slug, COUNT(*) AS cnt FROM services WHERE is_active = 1 GROUP BY category_slug")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[$row['category_slug']] = (int)$row['cnt'];
}

$svc_h1 = $category_row ? $category_row['name'] : cfg($db, 'services_h1', 'Каталог услуг');
$svc_sub = $category_row
    ? 'Выберите нужную услугу и оставьте заявку'
    : cfg($db, 'services_sub', 'Выберите категорию услуг для перехода к нужному направлению.');
$page_title = $svc_h1 . ' — ' . cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ');
$page_desc = cfg($db, 'seo_services_desc', cfg($db, 'seo_description', ''));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?=htmlspecialchars($page_title)?></title>
  <?php include 'meta.php'; ?>
  <link rel="stylesheet" href="style.css">
  <style>
    .catalog-page { padding: 50px 0; background: #f8fafc; min-height: 60vh; }
    .catalog-page h1 { font-size: 32px; margin-bottom: 8px; color: #1e293b; }
    .catalog-page .lead { color: #64748b; margin-bottom: 22px; }
    .breadcrumb { margin-bottom: 20px; font-size: 14px; color: #64748b; }
    .breadcrumb a { color: #2563eb; text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }
    .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-top: 30px; }
    .category-card { background: white; border-radius: 12px; text-decoration: none; color: #1e293b; box-shadow: 0 2px 10px rgba(0,0,0,.07); transition: transform .2s, box-shadow .2s, border-color .2s; border: 2px solid transparent; display: block; overflow: hidden; }
    .category-card:hover { transform: translateY(-4px); box-shadow: 0 6px 20px rgba(0,0,0,.13); border-color: #2563eb; }
    .category-card-img { width: 100%; height: 160px; object-fit: cover; display: block; background: #eff6ff; }
    .category-card-body { padding: 16px; text-align: center; }
    .category-card-name { font-size: 15px; font-weight: 700; line-height: 1.3; margin-bottom: 6px; }
    .category-card-count { font-size: 13px; color: #94a3b8; }
    .products-catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 10px; }
    .product-catalog-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.07); transition: transform .2s, box-shadow .2s; text-align: center; display: block; text-decoration: none; color: #1e293b; }
    .product-catalog-card:hover { transform: translateY(-4px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
    .product-catalog-img { width: 100%; height: 160px; object-fit: cover; display: block; background: #eff6ff; }
    .product-catalog-name { padding: 12px 14px; font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.4; min-height: 64px; display: flex; align-items: center; justify-content: center; text-align: center; }
    .empty-msg { text-align: center; padding: 60px 20px; color: #94a3b8; font-size: 18px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
    .back-link { display: inline-flex; align-items: center; gap: 6px; color: #2563eb; text-decoration: none; font-weight: 600; margin-bottom: 20px; font-size: 15px; }
    .back-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="catalog-page">
  <div class="container">

    <?php if (!$category_row): ?>
      <div class="breadcrumb"><a href="/">Главная</a> → Услуги</div>
      <h1><?=htmlspecialchars($svc_h1)?></h1>
      <p class="lead"><?=htmlspecialchars($svc_sub)?></p>

      <div class="categories-grid">
        <?php foreach ($service_categories as $row): ?>
          <a href="/services.php?cat=<?=htmlspecialchars($row['slug'])?>" class="category-card">
            <img src="<?=htmlspecialchars(getServiceCatImg($row['slug']))?>" alt="<?=htmlspecialchars($row['name'])?>" class="category-card-img">
            <div class="category-card-body">
              <div class="category-card-name"><?=htmlspecialchars($row['name'])?></div>
              <div class="category-card-count"><?=($counts[$row['slug']] ?? 0)?> позиций</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <div class="breadcrumb"><a href="/">Главная</a> → <a href="/services.php">Услуги</a> → <?=htmlspecialchars($category_row['name'])?></div>
      <a href="/services.php" class="back-link">← Назад к услугам</a>
      <h1><?=htmlspecialchars($category_row['name'])?></h1>

      <?php if ($services): ?>
        <div class="products-catalog-grid">
          <?php foreach ($services as $s): ?>
            <?php $img = (!empty($s['image']) && file_exists(ltrim($s['image'], '/'))) ? $s['image'] : getPlaceholderImage(); ?>
            <a href="/contacts.php?request_type=service&service_category=<?=urlencode($category)?>&service=<?=urlencode($s['slug'])?>" class="product-catalog-card">
              <img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($s['title'])?>" class="product-catalog-img">
              <div class="product-catalog-name"><?=htmlspecialchars($s['title'])?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-msg">Услуги в этой категории пока не добавлены.</div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="script.js"></script>
</body>
</html>
