<?php require_once __DIR__ . '/db.php'; ?>
<?php
  $page_title = cfg($db,'hero_h1','Крепеж и метизы оптом') . ' — ' . cfg($db,'seo_site_name','ЛЕНСПЕЦОПТ');
  $page_desc  = cfg($db,'seo_index_desc', cfg($db,'seo_description','Крепеж и метизы оптом — более 10 000 наименований. Доставка по России.'));
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
    .hero { position: relative; overflow: hidden; }
    .hero-bg-overlay { position:absolute;inset:0;background-size:cover;background-position:center;opacity:.20;z-index:0;pointer-events:none; }
    .hero .container { position: relative; z-index: 1; }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<?php
  $hero_h1   = cfg($db,'hero_h1','Крепеж и метизы оптом и в розницу');
  $hero_sub  = cfg($db,'hero_sub','Болты, гайки, шайбы, саморезы — более 10 000 наименований на складе');
  $hero_bg   = cfg($db,'hero_bg','');
  $advantages = $db->query("SELECT * FROM advantages ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="hero">
  <?php if($hero_bg&&file_exists(ltrim($hero_bg,'/'))): ?>
  <div class="hero-bg-overlay" style="background-image:url('<?=htmlspecialchars($hero_bg)?>')"></div>
  <?php endif; ?>
  <div class="container">
    <h1><?=htmlspecialchars($hero_h1)?></h1>
    <p><?=htmlspecialchars($hero_sub)?></p>
    <div class="hero-buttons">
      <a href="catalog.php" class="btn btn-primary">Смотреть каталог</a>
      <a href="contacts.php" class="btn btn-secondary">Связаться с нами</a>
    </div>
  </div>
</section>

<section class="features"><div class="container">
  <h2 class="section-title">Наши преимущества</h2>
  <div class="features-grid">
    <?php foreach($advantages as $adv): ?>
    <div class="feature-card">
      <?php if(!empty($adv['image'])&&file_exists(ltrim($adv['image'],'/'))) : ?>
        <div class="feature-icon"><img src="<?=htmlspecialchars($adv['image'])?>" alt="" style="width:48px;height:48px;object-fit:contain"></div>
      <?php else: ?>
        <div class="feature-icon"><?=htmlspecialchars($adv['icon'])?></div>
      <?php endif; ?>
      <h3><?=htmlspecialchars($adv['title'])?></h3>
      <p><?=htmlspecialchars($adv['subtitle'])?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="popular-products"><div class="container">
  <h2 class="section-title">Популярные товары</h2>
  <div class="products-grid">
    <?php
    $pop=$db->query("SELECT * FROM products WHERE is_popular=1 ORDER BY name LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    if($pop): foreach($pop as $p):
      $img=(!empty($p['image'])&&file_exists(ltrim($p['image'],'/'))) ? $p['image'] : getPlaceholderImage();
    ?>
    <div class="product-card">
      <img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($p['name'])?>" style="width:100%;height:160px;object-fit:cover;border-radius:8px;margin-bottom:15px">
      <h3><?=htmlspecialchars($p['name'])?></h3>
      <a href="catalog.php?cat=<?=htmlspecialchars($p['category'])?>" class="btn-small">В каталоге</a>
    </div>
    <?php endforeach; else: ?>
    <p style="color:#94a3b8;grid-column:1/-1;text-align:center;padding:30px">Популярные товары не выбраны — отметьте в <a href="admin.php">админ-панели</a>.</p>
    <?php endif; ?>
  </div>
  <div style="text-align:center;margin-top:30px"><a href="catalog.php" class="btn btn-primary">Весь каталог</a></div>
</div></section>

<section class="popular-products"><div class="container">
  <h2 class="section-title">Распродажа крепежа</h2>
  <div class="products-grid">
    <?php
    $sale=$db->query("SELECT * FROM products WHERE is_sale=1 ORDER BY name LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    if($sale): foreach($sale as $p):
      $img=(!empty($p['image'])&&file_exists(ltrim($p['image'],'/'))) ? $p['image'] : getPlaceholderImage();
    ?>
    <div class="product-card">
      <img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($p['name'])?>" style="width:100%;height:160px;object-fit:cover;border-radius:8px;margin-bottom:15px">
      <h3><?=htmlspecialchars($p['name'])?></h3>
      <a href="catalog.php?sale=1" class="btn-small">Смотреть распродажу</a>
    </div>
    <?php endforeach; else: ?>
    <p style="color:#94a3b8;grid-column:1/-1;text-align:center;padding:30px">Товары для распродажи пока не выбраны — отметьте их в <a href="admin.php?tab=catalog">админ-панели</a>.</p>
    <?php endif; ?>
  </div>
  <div style="text-align:center;margin-top:30px"><a href="catalog.php?sale=1" class="btn btn-primary">Вся распродажа</a></div>
</div></section>

<?php include 'footer.php'; ?>
<script src="script.js"></script>
</body></html>