<?php require_once __DIR__ . '/db.php';
$page_title = cfg($db,'about_h1','О компании') . ' — ' . cfg($db,'seo_site_name','ЛЕНСПЕЦОПТ');
$page_desc  = cfg($db,'seo_about_desc', cfg($db,'seo_description',''));
$about_h1   = cfg($db,'about_h1','О компании');
$about_sub  = cfg($db,'about_sub','Ваш надежный партнер в поставках крепежа');
$about_txt  = cfg($db,'about','');
$stats      = $db->query("SELECT * FROM stats ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
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
    /* Синяя полоса — одинаковая на всех страницах */
    .page-header {
      background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
      color: white;
      padding: 60px 0;
      text-align: center;
    }
    .page-header h1 {
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 12px;
      color: white;
    }
    .page-header p {
      font-size: 1.1rem;
      color: rgba(255,255,255,0.85);
      max-width: 600px;
      margin: 0 auto;
    }
    @media(max-width:768px) { .page-header h1 { font-size: 1.8rem; } }

    /* Блоки статистики */
    .about-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin: 40px 0 10px;
    }
    .stat-box {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 28px 20px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
    .stat-number {
      display: block;
      font-size: 2.4rem;
      font-weight: 900;
      color: #2563eb;
      line-height: 1;
      margin-bottom: 8px;
    }
    .stat-label {
      display: block;
      font-size: 14px;
      color: #64748b;
      line-height: 1.4;
    }
    @media(max-width:768px) { .about-stats { grid-template-columns: repeat(2,1fr); } }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<section class="page-header">
  <div class="container">
    <h1><?=htmlspecialchars($about_h1)?></h1>
    <p><?=htmlspecialchars($about_sub)?></p>
  </div>
</section>

<section class="about-section">
  <div class="container">
    <?php if(!empty($about_txt)): ?>
      <?=$about_txt?>
    <?php else: ?>
    <div class="about-content">
      <h2>Кто мы такие</h2>
      <p>Компания ЛЕНСПЕЦОПТ — ведущий поставщик крепежных изделий и метизов на территории России. Мы работаем с 2010 года и за это время успели зарекомендовать себя как надежного партнера для сотен компаний по всей стране.</p>
      <p>Наш склад площадью более 5000 м² вмещает более 10 000 наименований крепежа различных типов и размеров. Мы работаем напрямую с заводами-производителями из России, Европы и Азии, что позволяет предлагать лучшие цены на рынке.</p>
      <h2>Наши ценности</h2>
      <ul>
        <li><strong>Качество</strong> — все товары сертифицированы и соответствуют ГОСТ</li>
        <li><strong>Надежность</strong> — всегда выполняем свои обязательства в срок</li>
        <li><strong>Индивидуальный подход</strong> — работаем с каждым клиентом персонально</li>
        <li><strong>Честность</strong> — прозрачное ценообразование и открытое общение</li>
      </ul>
    </div>
    <?php endif; ?>
    <?php if(!empty($stats)): ?>
    <div class="about-stats">
      <?php foreach($stats as $st): ?>
      <div class="stat-box">
        <span class="stat-number"><?=htmlspecialchars($st['number'])?></span>
        <span class="stat-label"><?=htmlspecialchars($st['label'])?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>
<script src="script.js"></script>
</body></html>