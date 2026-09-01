<?php
require_once __DIR__ . '/db.php';

$category = trim($_GET['cat'] ?? '');
$isSale = isset($_GET['sale']) && $_GET['sale'] == '1';

$cats_raw = $db->query("SELECT slug, name FROM categories WHERE slug <> 'rasprodazha-krepezha' ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$category_names = [];
foreach ($cats_raw as $c) {
    $category_names[$c['slug']] = $c['name'];
}

if ($isSale) {
    $category_title = 'Распродажа крепежа';
} else {
    $category_title = ($category && isset($category_names[$category])) ? $category_names[$category] : 'Каталог';
}

$page_title = $category_title . ' — ' . cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ');
$page_desc = $isSale
    ? 'Распродажа крепежа и метизов — актуальные позиции ЛЕНСПЕЦОПТ.'
    : ($category ? ($category_title . ' — ' . cfg($db, 'seo_catalog_desc', cfg($db, 'seo_description', ''))) : cfg($db, 'seo_catalog_desc', cfg($db, 'seo_description', '')));

$products = [];
if ($isSale) {
    $st = $db->query("SELECT * FROM products WHERE is_sale = 1 ORDER BY category, name ASC");
    $products = $st->fetchAll(PDO::FETCH_ASSOC);
} elseif ($category) {
    $stmt = $db->prepare("SELECT * FROM products WHERE category = :cat ORDER BY name ASC");
    $stmt->execute(['cat' => $category]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$counts = [];
foreach ($db->query("SELECT category, COUNT(*) as cnt FROM products GROUP BY category")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[$row['category']] = (int)$row['cnt'];
}
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
    .product-catalog-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.07); transition: transform .2s, box-shadow .2s; text-align: center; }
    .product-catalog-card:hover { transform: translateY(-4px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
    .product-catalog-img { width: 100%; height: 160px; object-fit: cover; display: block; background: #eff6ff; }
    .product-catalog-name { padding: 12px 14px; font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.4; }
    .empty-msg { text-align: center; padding: 60px 20px; color: #94a3b8; font-size: 18px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
    .back-link { display: inline-flex; align-items: center; gap: 6px; color: #2563eb; text-decoration: none; font-weight: 600; margin-bottom: 20px; font-size: 15px; }
    .back-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="catalog-page">
  <div class="container">

    <?php if (!$category && !$isSale): ?>
      <div class="breadcrumb"><a href="/">Главная</a> → Каталог</div>
      <h1>Каталог товаров</h1>
      <p class="lead">Выберите категорию товаров или перейдите в раздел распродажи в верхнем меню.</p>

      <div class="categories-grid">
        <?php foreach ($category_names as $key => $name): ?>
          <a href="/catalog.php?cat=<?=htmlspecialchars($key)?>" class="category-card">
            <img src="<?=htmlspecialchars(getCatImg($key))?>" alt="<?=htmlspecialchars($name)?>" class="category-card-img">
            <div class="category-card-body">
              <div class="category-card-name"><?=htmlspecialchars($name)?></div>
              <div class="category-card-count"><?=isset($counts[$key]) ? $counts[$key] . ' позиций' : '0 позиций'?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <div class="breadcrumb">
        <a href="/">Главная</a> →
        <?php if ($isSale): ?> Распродажа <?php else: ?> <a href="/catalog.php">Каталог</a> → <?=htmlspecialchars($category_title)?> <?php endif; ?>
      </div>
      <a href="/catalog.php" class="back-link">← Назад к каталогу</a>
      <h1><?=htmlspecialchars($category_title)?></h1>

      <?php if (!empty($products)): ?>
        <div class="products-catalog-grid">
          <?php foreach ($products as $p): ?>
            <?php $img = (!empty($p['image']) && file_exists(ltrim($p['image'], '/'))) ? $p['image'] : getPlaceholderImage(); ?>
            <div class="product-catalog-card">
              <img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($p['name'])?>" class="product-catalog-img">
              <div class="product-catalog-name"><?=htmlspecialchars($p['name'])?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-msg">
          <?= $isSale ? 'Товары для распродажи пока не выбраны.' : 'Товары в этой категории пока не добавлены.' ?><br>
          <a href="/contacts.php" style="color:#2563eb">Свяжитесь с нами</a> для уточнения наличия.
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php include 'footer.php'; ?>
<script src="script.js"></script>
</body>
</html>
