<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/xml; charset=UTF-8');
$base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$cats = $db->query("SELECT slug FROM categories ORDER BY sort_order")->fetchAll(PDO::FETCH_COLUMN);
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?=$base?>/</loc><priority>1.0</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/about.php</loc><priority>0.8</priority><changefreq>monthly</changefreq></url>
  <url><loc><?=$base?>/catalog.php</loc><priority>0.9</priority><changefreq>weekly</changefreq></url>
  <url><loc><?=$base?>/services.php</loc><priority>0.7</priority><changefreq>monthly</changefreq></url>
  <url><loc><?=$base?>/contacts.php</loc><priority>0.6</priority><changefreq>monthly</changefreq></url>
  <?php foreach($cats as $slug): ?>
  <url>
    <loc><?=$base?>/catalog.php?cat=<?=urlencode($slug)?></loc>
    <priority>0.7</priority>
    <changefreq>weekly</changefreq>
  </url>
  <?php endforeach; ?>
</urlset>