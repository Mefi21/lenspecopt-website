<?php
session_start();
define('ADMIN_PASS', getenv('ADMIN_PASSWORD') ?: '');

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (ADMIN_PASS !== '' && hash_equals(ADMIN_PASS, (string) $_POST['password'])) {
        $_SESSION['admin'] = true;
    } else {
        $login_err = 'Неверный пароль';
    }
}

if (!isset($_SESSION['admin'])): ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Вход — Админ</title>
<style>
body{font-family:Arial,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#fff;padding:36px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,.12);width:340px}h2{margin:0 0 20px;color:#0f172a;text-align:center}input[type=password]{width:100%;padding:13px;border:2px solid #e2e8f0;border-radius:8px;font-size:16px;box-sizing:border-box;margin-bottom:16px}input[type=password]:focus{outline:none;border-color:#2563eb}button{width:100%;padding:13px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:16px;cursor:pointer}button:hover{background:#1d4ed8}.err{background:#fee2e2;color:#b91c1c;padding:10px 12px;border-radius:8px;font-size:14px;margin-bottom:14px}
</style>
</head>
<body>
<div class="box">
  <h2>🔐 Админ-панель</h2>
  <?php if(isset($login_err)): ?><div class="err"><?=htmlspecialchars($login_err)?></div><?php endif; ?>
  <form method="POST">
    <input type="password" name="password" placeholder="Пароль" autofocus>
    <button type="submit">Войти</button>
  </form>
</div>
</body>
</html>
<?php exit; endif;

require_once __DIR__ . '/db.php';

foreach (['uploads', 'uploads/categories', 'uploads/products', 'uploads/service_categories', 'uploads/services', 'uploads/advantages', 'uploads/hero', 'uploads/seo'] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

function admin_upload_image($field, $dir, $filenameBase, $oldPath = '', $allowed = ['jpg','jpeg','png','webp','gif','svg']) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $oldPath;
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return $oldPath;

    if ($oldPath) {
        $oldFs = ltrim($oldPath, '/');
        if (file_exists($oldFs)) @unlink($oldFs);
    }

    foreach ($allowed as $a) {
        $existing = rtrim($dir, '/') . '/' . $filenameBase . '.' . $a;
        if (file_exists($existing)) @unlink($existing);
    }

    $target = rtrim($dir, '/') . '/' . $filenameBase . '.' . $ext;
    move_uploaded_file($_FILES[$field]['tmp_name'], $target);
    return '/' . $target;
}

function service_image_path(string $slugOrId): string {
    foreach (['jpg','jpeg','png','webp','gif','svg'] as $ext) {
        $path = "uploads/services/{$slugOrId}.{$ext}";
        if (file_exists($path)) return '/' . $path;
    }
    return '';
}

function swap_sort(PDO $db, string $table, string $pkCol, $pkValue, string $groupCol = '', $groupValue = null, string $direction = 'up'): void {
    $currentSql = "SELECT {$pkCol}, sort_order" . ($groupCol ? ", {$groupCol}" : '') . " FROM {$table} WHERE {$pkCol} = ? LIMIT 1";
    $st = $db->prepare($currentSql);
    $st->execute([$pkValue]);
    $current = $st->fetch(PDO::FETCH_ASSOC);
    if (!$current) return;

    $sort = (int)($current['sort_order'] ?? 0);
    $op = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';

    if ($groupCol) {
        $neighborSql = "SELECT {$pkCol}, sort_order FROM {$table} WHERE {$groupCol} = ? AND sort_order {$op} ? ORDER BY sort_order {$order}, {$pkCol} {$order} LIMIT 1";
        $neighbor = $db->prepare($neighborSql);
        $neighbor->execute([$groupValue, $sort]);
    } else {
        $neighborSql = "SELECT {$pkCol}, sort_order FROM {$table} WHERE sort_order {$op} ? ORDER BY sort_order {$order}, {$pkCol} {$order} LIMIT 1";
        $neighbor = $db->prepare($neighborSql);
        $neighbor->execute([$sort]);
    }
    $other = $neighbor->fetch(PDO::FETCH_ASSOC);
    if (!$other) return;

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE {$table} SET sort_order = -1 WHERE {$pkCol} = ?")->execute([$pkValue]);
        $db->prepare("UPDATE {$table} SET sort_order = ? WHERE {$pkCol} = ?")->execute([$sort, $other[$pkCol]]);
        $db->prepare("UPDATE {$table} SET sort_order = ? WHERE {$pkCol} = ?")->execute([(int)$other['sort_order'], $pkValue]);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
    }
}

$msg = '';
$tab = $_GET['tab'] ?? 'catalog';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['password'])) {
    if (isset($_POST['add_cat'])) {
        $name = trim($_POST['cat_name'] ?? '');
        $slug = slugify(trim($_POST['cat_slug'] ?? ''));
        if ($name && $slug) {
            try {
                $sort = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) FROM categories")->fetchColumn() + 1;
                $db->prepare("INSERT INTO categories (slug, name, sort_order) VALUES (?, ?, ?)")->execute([$slug, $name, $sort]);
                $msg = '✅ Категория добавлена';
            } catch (Exception $e) {
                $msg = '❌ Не удалось добавить категорию. Проверь slug.';
            }
        }
        $tab = 'catalog';
    }

    if (isset($_POST['upload_cat_img'])) {
        $key = trim($_POST['cat_key'] ?? '');
        if ($key !== '') {
            admin_upload_image('cat_img', 'uploads/categories', $key, '', ['jpg','jpeg','png','webp','gif','svg']);
            $msg = '✅ Изображение категории обновлено';
        }
        $tab = 'catalog';
    }

    if (isset($_POST['add_prod'])) {
        $cat = trim($_POST['prod_cat'] ?? '');
        $name = trim($_POST['prod_name'] ?? '');
        if ($cat && $name) {
            $db->prepare("INSERT INTO products (category, name) VALUES (?, ?)")->execute([$cat, $name]);
            $msg = '✅ Товар добавлен';
        }
        $tab = 'catalog';
    }

    if (isset($_POST['upload_prod_img'])) {
        $pid = (int)($_POST['pid'] ?? 0);
        if ($pid > 0) {
            $old = $db->prepare("SELECT image FROM products WHERE id = ?");
            $old->execute([$pid]);
            $oldImage = (string)$old->fetchColumn();
            $path = admin_upload_image('prod_img', 'uploads/products', (string)$pid, $oldImage, ['jpg','jpeg','png','webp','gif','svg']);
            $db->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$path, $pid]);
            $msg = '✅ Фото товара обновлено';
        }
        $tab = 'catalog';
    }

    if (isset($_POST['add_svccat'])) {
        $name = trim($_POST['svccat_name'] ?? '');
        $slug = slugify(trim($_POST['svccat_slug'] ?? ''));
        if ($name && $slug) {
            try {
                $sort = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) FROM service_categories")->fetchColumn() + 1;
                $db->prepare("INSERT INTO service_categories (slug, name, sort_order, is_active) VALUES (?, ?, ?, 1)")->execute([$slug, $name, $sort]);
                $msg = '✅ Категория услуг добавлена';
            } catch (Exception $e) {
                $msg = '❌ Не удалось добавить категорию услуг. Проверь slug.';
            }
        }
        $tab = 'services';
    }

    if (isset($_POST['upload_svccat_img'])) {
        $key = trim($_POST['svccat_key'] ?? '');
        if ($key !== '') {
            admin_upload_image('svccat_img', 'uploads/service_categories', $key, '', ['jpg','jpeg','png','webp','gif','svg']);
            $msg = '✅ Изображение категории услуг обновлено';
        }
        $tab = 'services';
    }

    if (isset($_POST['add_svc'])) {
        $cat = trim($_POST['svc_cat'] ?? '');
        $title = trim($_POST['svc_name'] ?? '');
        if ($cat && $title) {
            $st = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM services WHERE category_slug = ?");
            $st->execute([$cat]);
            $sort = (int)$st->fetchColumn() + 1;
            $slug = uniqueServiceSlug($db, $title . '-' . $cat);
            $db->prepare("INSERT INTO services (title, description, icon, sort_order, subtitle, image, slug, category_slug, content, keywords, is_active) VALUES (?, '', '⚙️', ?, '', '', ?, ?, '', '', 1)")
               ->execute([$title, $sort, $slug, $cat]);
            $msg = '✅ Услуга добавлена';
        }
        $tab = 'services';
    }

    if (isset($_POST['upload_svc_img'])) {
        $sid = (int)($_POST['sid'] ?? 0);
        if ($sid > 0) {
            $st = $db->prepare("SELECT image FROM services WHERE id = ?");
            $st->execute([$sid]);
            $oldImage = (string)$st->fetchColumn();
            $path = admin_upload_image('svc_img', 'uploads/services', (string)$sid, $oldImage, ['jpg','jpeg','png','webp','gif','svg']);
            $db->prepare("UPDATE services SET image = ? WHERE id = ?")->execute([$path, $sid]);
            $msg = '✅ Фото услуги обновлено';
        }
        $tab = 'services';
    }

    if (isset($_POST['adv_save'])) {
        $id = (int)($_POST['adv_id'] ?? 0);
        $title = trim($_POST['adv_title'] ?? '');
        $subtitle = trim($_POST['adv_subtitle'] ?? '');
        $icon = trim($_POST['adv_icon'] ?? '📦');
        $order = (int)($_POST['adv_order'] ?? 0);
        $old = trim($_POST['adv_old_image'] ?? '');
        $image = admin_upload_image('adv_image', 'uploads/advantages', 'adv_' . time() . '_' . rand(100,999), $old, ['jpg','jpeg','png','webp','gif','svg']);

        if ($id > 0) {
            if ($image === $old) $image = $old;
            $db->prepare("UPDATE advantages SET title = ?, subtitle = ?, icon = ?, image = ?, sort_order = ? WHERE id = ?")
               ->execute([$title, $subtitle, $icon, $image, $order, $id]);
            $msg = '✅ Преимущество обновлено';
        } else {
            $db->prepare("INSERT INTO advantages (title, subtitle, icon, image, sort_order) VALUES (?, ?, ?, ?, ?)")
               ->execute([$title, $subtitle, $icon, $image, $order]);
            $msg = '✅ Преимущество добавлено';
        }
        $tab = 'advantages';
    }

    if (isset($_POST['stat_save'])) {
        $id = (int)($_POST['stat_id'] ?? 0);
        $number = trim($_POST['stat_number'] ?? '');
        $label = trim($_POST['stat_label'] ?? '');
        $order = (int)($_POST['stat_order'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE stats SET number = ?, label = ?, sort_order = ? WHERE id = ?")
               ->execute([$number, $label, $order, $id]);
            $msg = '✅ Статистика обновлена';
        } else {
            $db->prepare("INSERT INTO stats (number, label, sort_order) VALUES (?, ?, ?)")
               ->execute([$number, $label, $order]);
            $msg = '✅ Показатель добавлен';
        }
        $tab = 'stats';
    }

    if (isset($_POST['save_settings'])) {
        $fields = ['phone1','phone2','email1','email2','address','hours1','hours2','hero_h1','hero_sub','about','about_h1','about_sub','services_h1','services_sub','contacts_h1','contacts_sub'];
        foreach ($fields as $f) setCfg($db, $f, trim($_POST[$f] ?? ''));

        $logoOld = cfg($db, 'logo', '');
        $logo = admin_upload_image('logo', 'uploads', 'logo_' . time(), $logoOld, ['png','jpg','jpeg','svg','webp']);
        if ($logo !== $logoOld || (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK)) setCfg($db, 'logo', $logo);

        $heroOld = cfg($db, 'hero_bg', '');
        $hero = admin_upload_image('hero_bg', 'uploads/hero', 'hero_' . time(), $heroOld, ['jpg','jpeg','png','webp']);
        if ($hero !== $heroOld || (isset($_FILES['hero_bg']) && $_FILES['hero_bg']['error'] === UPLOAD_ERR_OK)) setCfg($db, 'hero_bg', $hero);

        $msg = '✅ Настройки сохранены';
        $tab = 'settings';
    }

    if (isset($_POST['save_seo'])) {
        $fields = ['seo_site_name','seo_city','seo_description','seo_keywords','seo_index_desc','seo_about_desc','seo_services_desc','seo_contacts_desc','seo_catalog_desc','google_verification'];
        foreach ($fields as $f) setCfg($db, $f, trim($_POST[$f] ?? ''));

        $favOld = cfg($db, 'favicon', '');
        $fav = admin_upload_image('favicon', 'uploads/seo', 'favicon_' . time(), $favOld, ['ico','png','jpg','jpeg','svg','webp']);
        if ($fav !== $favOld || (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK)) setCfg($db, 'favicon', $fav);

        $ogOld = cfg($db, 'seo_og_image', '');
        $og = admin_upload_image('og_image', 'uploads/seo', 'og_' . time(), $ogOld, ['jpg','jpeg','png','webp']);
        if ($og !== $ogOld || (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK)) setCfg($db, 'seo_og_image', $og);

        $msg = '✅ SEO сохранено';
        $tab = 'seo';
    }
}

if (isset($_GET['togglepop'])) {
    $pid = (int)$_GET['togglepop'];
    $st = $db->prepare("SELECT is_popular FROM products WHERE id = ?");
    $st->execute([$pid]);
    $cur = (int)$st->fetchColumn();
    $db->prepare("UPDATE products SET is_popular = ? WHERE id = ?")->execute([$cur ? 0 : 1, $pid]);
    header('Location: admin.php?tab=catalog');
    exit;
}

if (isset($_GET['togglesale'])) {
    $pid = (int)$_GET['togglesale'];
    $st = $db->prepare("SELECT is_sale FROM products WHERE id = ?");
    $st->execute([$pid]);
    $cur = (int)$st->fetchColumn();
    $db->prepare("UPDATE products SET is_sale = ? WHERE id = ?")->execute([$cur ? 0 : 1, $pid]);
    header('Location: admin.php?tab=catalog');
    exit;
}

if (isset($_GET['delcat'])) {
    $slug = trim($_GET['delcat']);
    if ($slug !== '') {
        $db->prepare("DELETE FROM categories WHERE slug = ?")->execute([$slug]);
    }
    header('Location: admin.php?tab=catalog');
    exit;
}

if (isset($_GET['delprod'])) {
    $pid = (int)$_GET['delprod'];
    $old = $db->prepare("SELECT image FROM products WHERE id = ?");
    $old->execute([$pid]);
    $img = (string)$old->fetchColumn();
    if ($img && file_exists(ltrim($img, '/'))) @unlink(ltrim($img, '/'));
    $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
    header('Location: admin.php?tab=catalog');
    exit;
}

if (isset($_GET['delsvccat'])) {
    $slug = trim($_GET['delsvccat']);
    $st = $db->prepare("SELECT COUNT(*) FROM services WHERE category_slug = ?");
    $st->execute([$slug]);
    if ((int)$st->fetchColumn() > 0) {
        $msg = '❌ Нельзя удалить категорию услуг, пока в ней есть услуги.';
        $tab = 'services';
    } else {
        foreach (['jpg','jpeg','png','webp','gif','svg'] as $ext) {
            $path = "uploads/service_categories/{$slug}.{$ext}";
            if (file_exists($path)) @unlink($path);
        }
        $db->prepare("DELETE FROM service_categories WHERE slug = ?")->execute([$slug]);
        header('Location: admin.php?tab=services');
        exit;
    }
}

if (isset($_GET['delsvc'])) {
    $id = (int)$_GET['delsvc'];
    $st = $db->prepare("SELECT image FROM services WHERE id = ?");
    $st->execute([$id]);
    $img = (string)$st->fetchColumn();
    if ($img && file_exists(ltrim($img, '/'))) @unlink(ltrim($img, '/'));
    $db->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
    header('Location: admin.php?tab=services');
    exit;
}

if (isset($_GET['svccatup'])) {
    swap_sort($db, 'service_categories', 'slug', trim($_GET['svccatup']), '', null, 'up');
    header('Location: admin.php?tab=services');
    exit;
}
if (isset($_GET['svccatdown'])) {
    swap_sort($db, 'service_categories', 'slug', trim($_GET['svccatdown']), '', null, 'down');
    header('Location: admin.php?tab=services');
    exit;
}
if (isset($_GET['svcup'])) {
    $id = (int)$_GET['svcup'];
    $st = $db->prepare("SELECT category_slug FROM services WHERE id = ?");
    $st->execute([$id]);
    $group = (string)$st->fetchColumn();
    swap_sort($db, 'services', 'id', $id, 'category_slug', $group, 'up');
    header('Location: admin.php?tab=services');
    exit;
}
if (isset($_GET['svcdown'])) {
    $id = (int)$_GET['svcdown'];
    $st = $db->prepare("SELECT category_slug FROM services WHERE id = ?");
    $st->execute([$id]);
    $group = (string)$st->fetchColumn();
    swap_sort($db, 'services', 'id', $id, 'category_slug', $group, 'down');
    header('Location: admin.php?tab=services');
    exit;
}

if (isset($_GET['dellogo'])) {
    $old = cfg($db, 'logo', '');
    if ($old && file_exists(ltrim($old, '/'))) @unlink(ltrim($old, '/'));
    setCfg($db, 'logo', '');
    header('Location: admin.php?tab=settings');
    exit;
}
if (isset($_GET['delhero_bg'])) {
    $old = cfg($db, 'hero_bg', '');
    if ($old && file_exists(ltrim($old, '/'))) @unlink(ltrim($old, '/'));
    setCfg($db, 'hero_bg', '');
    header('Location: admin.php?tab=settings');
    exit;
}
if (isset($_GET['delfavicon'])) {
    $old = cfg($db, 'favicon', '');
    if ($old && file_exists(ltrim($old, '/'))) @unlink(ltrim($old, '/'));
    setCfg($db, 'favicon', '');
    header('Location: admin.php?tab=seo');
    exit;
}
if (isset($_GET['delogimg'])) {
    $old = cfg($db, 'seo_og_image', '');
    if ($old && file_exists(ltrim($old, '/'))) @unlink(ltrim($old, '/'));
    setCfg($db, 'seo_og_image', '');
    header('Location: admin.php?tab=seo');
    exit;
}
if (isset($_GET['deladv'])) {
    $id = (int)$_GET['deladv'];
    $st = $db->prepare("SELECT image FROM advantages WHERE id = ?");
    $st->execute([$id]);
    $img = (string)$st->fetchColumn();
    if ($img && file_exists(ltrim($img, '/'))) @unlink(ltrim($img, '/'));
    $db->prepare("DELETE FROM advantages WHERE id = ?")->execute([$id]);
    header('Location: admin.php?tab=advantages');
    exit;
}
if (isset($_GET['delstat'])) {
    $db->prepare("DELETE FROM stats WHERE id = ?")->execute([(int)$_GET['delstat']]);
    header('Location: admin.php?tab=stats');
    exit;
}

$edit_adv = null;
if (isset($_GET['editadv'])) {
    $st = $db->prepare("SELECT * FROM advantages WHERE id = ?");
    $st->execute([(int)$_GET['editadv']]);
    $edit_adv = $st->fetch(PDO::FETCH_ASSOC);
}
$edit_stat = null;
if (isset($_GET['editstat'])) {
    $st = $db->prepare("SELECT * FROM stats WHERE id = ?");
    $st->execute([(int)$_GET['editstat']]);
    $edit_stat = $st->fetch(PDO::FETCH_ASSOC);
}

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$categoryOptions = array_filter($categories, function($c){ return ($c['slug'] ?? '') !== 'rasprodazha-krepezha'; });
$cat_map = [];
foreach ($categories as $c) $cat_map[$c['slug']] = $c['name'];

$search = trim($_GET['s'] ?? '');
$cf = trim($_GET['cf'] ?? '');
$where = [];
$params = [];
if ($search !== '') { $where[] = "name LIKE ?"; $params[] = "%{$search}%"; }
if ($cf !== '') { $where[] = "category = ?"; $params[] = $cf; }
$sql = "SELECT * FROM products" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY category, name";
$st = $db->prepare($sql);
$st->execute($params);
$products = $st->fetchAll(PDO::FETCH_ASSOC);
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$popularCnt = (int)$db->query("SELECT COUNT(*) FROM products WHERE is_popular = 1")->fetchColumn();
$saleCnt = (int)$db->query("SELECT COUNT(*) FROM products WHERE is_sale = 1")->fetchColumn();
$withPhoto = (int)$db->query("SELECT COUNT(*) FROM products WHERE TRIM(COALESCE(image,'')) <> ''")->fetchColumn();

$adv_list = $db->query("SELECT * FROM advantages ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
$stats_list = $db->query("SELECT * FROM stats ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

$service_categories = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$service_cat_map = [];
foreach ($service_categories as $c) $service_cat_map[$c['slug']] = $c['name'];
$svcSearch = trim($_GET['ss'] ?? '');
$svcCf = trim($_GET['scf'] ?? '');
$svcWhere = ["TRIM(COALESCE(category_slug,'')) <> ''"];
$svcParams = [];
if ($svcSearch !== '') { $svcWhere[] = "title LIKE ?"; $svcParams[] = "%{$svcSearch}%"; }
if ($svcCf !== '') { $svcWhere[] = "category_slug = ?"; $svcParams[] = $svcCf; }
$svcSql = "SELECT * FROM services WHERE " . implode(' AND ', $svcWhere) . " ORDER BY category_slug, sort_order, title";
$st = $db->prepare($svcSql);
$st->execute($svcParams);
$services = $st->fetchAll(PDO::FETCH_ASSOC);
$totalServices = (int)$db->query("SELECT COUNT(*) FROM services WHERE TRIM(COALESCE(category_slug,'')) <> ''")->fetchColumn();
$serviceWithPhoto = (int)$db->query("SELECT COUNT(*) FROM services WHERE TRIM(COALESCE(category_slug,'')) <> '' AND TRIM(COALESCE(image,'')) <> ''")->fetchColumn();
$serviceCatWithPhoto = 0;
foreach ($service_categories as $c) {
    $img = getServiceCatImg($c['slug']);
    if ($img !== getPlaceholderImage()) $serviceCatWithPhoto++;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Админ-панель</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}a{text-decoration:none}.top{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#0f172a;color:#fff;position:sticky;top:0;z-index:10}.top a{color:#fff;margin-left:14px;font-size:14px}.tabs{display:flex;gap:10px;flex-wrap:wrap;padding:16px 18px;background:#fff;border-bottom:1px solid #e2e8f0}.tab{padding:10px 14px;border-radius:999px;background:#eef2ff;color:#334155;font-weight:700;font-size:14px}.tab.on{background:#2563eb;color:#fff}.wrap{padding:20px;max-width:1480px;margin:0 auto}.msg{background:#dcfce7;border:1px solid #86efac;color:#166534;padding:12px 14px;border-radius:10px;margin-bottom:16px}.card,.fcard{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;margin-bottom:18px;box-shadow:0 4px 18px rgba(15,23,42,.04)}.fcard{background:#f8fbff}h1,h2,h3{margin:0 0 14px}.row{display:flex;gap:14px;flex-wrap:wrap}.fg{flex:1;min-width:220px}label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:6px}input[type=text],input[type=password],textarea,select{width:100%;padding:11px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;background:#fff}input:focus,textarea:focus,select:focus{outline:none;border-color:#2563eb}textarea{min-height:90px;resize:vertical}.btn{display:inline-block;border:none;border-radius:10px;padding:10px 14px;font-weight:700;font-size:14px;cursor:pointer}.b-blue{background:#2563eb;color:#fff}.b-red{background:#ef4444;color:#fff}.b-yellow{background:#f59e0b;color:#fff}.b-green{background:#10b981;color:#fff}.b-purple{background:#7c3aed;color:#fff}.b-slate{background:#64748b;color:#fff}.btn-sm{padding:7px 10px;font-size:12px;border-radius:8px}.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:18px}.s{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center}.sn{font-size:30px;font-weight:900;color:#2563eb}.sl{font-size:13px;color:#64748b}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.filters input,.filters select{flex:1;min-width:170px}table{width:100%;border-collapse:collapse}th,td{padding:12px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top;text-align:left;font-size:14px}th{font-size:12px;text-transform:uppercase;color:#64748b}.thumb{width:72px;height:72px;object-fit:cover;border-radius:12px;background:#eff6ff;border:1px solid #dbeafe}.muted{color:#64748b;font-size:13px}.hint{font-size:12px;color:#64748b;margin-top:6px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}.code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;background:#f1f5f9;border-radius:8px;padding:2px 6px}.inline-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.service-table-actions a,.service-table-actions button,.product-actions a{margin-bottom:6px}.service-table-actions{display:flex;gap:8px;flex-wrap:wrap}.product-actions{display:flex;gap:8px;flex-wrap:wrap}.badge-yes,.badge-no{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}.badge-yes{background:#dbeafe;color:#1d4ed8}.badge-no{background:#f1f5f9;color:#64748b}@media(max-width:980px){.grid2{grid-template-columns:1fr}.wrap{padding:14px}.top{flex-direction:column;align-items:flex-start;gap:10px}}
</style>
</head>
<body>
<div class="top">
  <h1 style="font-size:18px">⚙️ Админ-панель</h1>
  <div>
    <a href="/" target="_blank">🌐 Открыть сайт</a>
    <a href="?logout=1">Выйти</a>
  </div>
</div>

<div class="tabs">
  <a class="tab <?=($tab==='catalog')?'on':''?>" href="?tab=catalog">📦 Каталог</a>
  <a class="tab <?=($tab==='advantages')?'on':''?>" href="?tab=advantages">⭐ Преимущества</a>
  <a class="tab <?=($tab==='stats')?'on':''?>" href="?tab=stats">📊 Статистика</a>
  <a class="tab <?=($tab==='services')?'on':''?>" href="?tab=services">🛠 Услуги</a>
  <a class="tab <?=($tab==='settings')?'on':''?>" href="?tab=settings">⚙️ Настройки</a>
  <a class="tab <?=($tab==='seo')?'on':''?>" href="?tab=seo">🔍 SEO</a>
</div>

<div class="wrap">
<?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<?php if($tab==='catalog'): ?>
  <div class="stats-row">
    <div class="s"><div class="sn"><?=count($categories)?></div><div class="sl">Категорий</div></div>
    <div class="s"><div class="sn"><?=$totalProducts?></div><div class="sl">Товаров</div></div>
    <div class="s"><div class="sn"><?=$withPhoto?></div><div class="sl">С фото</div></div>
    <div class="s"><div class="sn"><?=$popularCnt?></div><div class="sl">На главной</div></div>
    <div class="s"><div class="sn"><?=$saleCnt?></div><div class="sl">В распродаже</div></div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div class="muted">Рекомендуемое соотношение сторон для фото категорий и товаров: <strong>4:3</strong>. Оптимально загружать изображения в одном формате, например <strong>1200×900 px</strong>.</div>
  </div>

  <div class="grid2">
    <div class="card">
      <h3>➕ Добавить категорию</h3>
      <form method="POST">
        <input type="hidden" name="add_cat" value="1">
        <div class="row">
          <div class="fg"><label>Название</label><input type="text" name="cat_name" placeholder="Например: Болты"></div>
          <div class="fg"><label>Slug</label><input type="text" name="cat_slug" placeholder="bolty"></div>
        </div>
        <button type="submit" class="btn b-blue">Добавить категорию</button>
      </form>
    </div>

    <div class="card">
      <h3>➕ Добавить товар</h3>
      <form method="POST">
        <input type="hidden" name="add_prod" value="1">
        <div class="row">
          <div class="fg"><label>Название товара</label><input type="text" name="prod_name" placeholder="Болт M10x40"></div>
          <div class="fg"><label>Категория</label>
            <select name="prod_cat">
              <option value="">Выберите</option>
              <?php foreach($categoryOptions as $c): ?>
                <option value="<?=htmlspecialchars($c['slug'])?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn b-blue">Добавить товар</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>Категории каталога</h3>
    <table>
      <thead><tr><th>Категория</th><th>Slug</th><th>Фото</th><th>Обновить фото</th><th>Действие</th></tr></thead>
      <tbody>
      <?php foreach($categories as $c): ?>
        <tr>
          <td><strong><?=htmlspecialchars($c['name'])?></strong></td>
          <td><span class="code"><?=htmlspecialchars($c['slug'])?></span></td>
          <td><img class="thumb" src="<?=htmlspecialchars(getCatImg($c['slug']))?>" alt=""></td>
          <td>
            <form method="POST" enctype="multipart/form-data" class="inline-actions">
              <input type="hidden" name="upload_cat_img" value="1">
              <input type="hidden" name="cat_key" value="<?=htmlspecialchars($c['slug'])?>">
              <input type="file" name="cat_img" accept="image/*" required>
              <span class="hint">4:3</span>
              <button type="submit" class="btn b-green btn-sm">Загрузить</button>
            </form>
          </td>
          <td>
            <a href="?tab=catalog&delcat=<?=urlencode($c['slug'])?>" class="btn b-red btn-sm" onclick="return confirm('Удалить категорию?')">Удалить</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Список товаров</h3>
    <form method="GET" class="filters">
      <input type="hidden" name="tab" value="catalog">
      <input type="text" name="s" value="<?=htmlspecialchars($search)?>" placeholder="Поиск по названию">
      <select name="cf">
        <option value="">Все категории</option>
        <?php foreach($categoryOptions as $c): ?>
          <option value="<?=htmlspecialchars($c['slug'])?>" <?=$cf===$c['slug']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn b-blue" type="submit">Фильтр</button>
    </form>

    <table>
      <thead><tr><th>Фото</th><th>Название</th><th>Категория</th><th>На главной</th><th>Распродажа</th><th>Обновить фото</th><th>Действия</th></tr></thead>
      <tbody>
      <?php foreach($products as $p): ?>
        <?php $img = ($p['image'] && file_exists(ltrim($p['image'], '/'))) ? $p['image'] : getPlaceholderImage(); ?>
        <tr>
          <td><img class="thumb" src="<?=htmlspecialchars($img)?>" alt=""></td>
          <td><strong><?=htmlspecialchars($p['name'])?></strong></td>
          <td><?=htmlspecialchars($cat_map[$p['category']] ?? $p['category'])?></td>
          <td><span class="<?=$p['is_popular'] ? 'badge-yes' : 'badge-no'?>"><?=$p['is_popular'] ? 'Да' : 'Нет'?></span></td>
          <td><span class="<?=$p['is_sale'] ? 'badge-yes' : 'badge-no'?>"><?=$p['is_sale'] ? 'Да' : 'Нет'?></span></td>
          <td>
            <form method="POST" enctype="multipart/form-data" class="inline-actions">
              <input type="hidden" name="upload_prod_img" value="1">
              <input type="hidden" name="pid" value="<?=$p['id']?>">
              <input type="file" name="prod_img" accept="image/*" required>
              <span class="hint">4:3</span>
              <button type="submit" class="btn b-green btn-sm">Загрузить</button>
            </form>
          </td>
          <td>
            <div class="product-actions">
              <a href="?tab=catalog&togglepop=<?=$p['id']?>" class="btn b-yellow btn-sm"><?=$p['is_popular']?'Убрать':'На главную'?></a>
              <a href="?tab=catalog&togglesale=<?=$p['id']?>" class="btn b-purple btn-sm"><?=$p['is_sale']?'Убрать':'В распродажу'?></a>
              <a href="?tab=catalog&delprod=<?=$p['id']?>" class="btn b-red btn-sm" onclick="return confirm('Удалить товар?')">Удалить</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$products): ?><tr><td colspan="7" class="muted">Ничего не найдено.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if($tab==='advantages'): ?>
  <div class="fcard">
    <h3><?=$edit_adv ? '✏️ Редактировать преимущество' : '➕ Добавить преимущество'?></h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="adv_save" value="1">
      <input type="hidden" name="adv_id" value="<?=$edit_adv['id'] ?? 0?>">
      <input type="hidden" name="adv_old_image" value="<?=htmlspecialchars($edit_adv['image'] ?? '')?>">
      <div class="row">
        <div class="fg"><label>Заголовок</label><input type="text" name="adv_title" value="<?=htmlspecialchars($edit_adv['title'] ?? '')?>"></div>
        <div class="fg"><label>Подзаголовок</label><input type="text" name="adv_subtitle" value="<?=htmlspecialchars($edit_adv['subtitle'] ?? '')?>"></div>
      </div>
      <div class="row">
        <div class="fg"><label>Иконка</label><input type="text" name="adv_icon" value="<?=htmlspecialchars($edit_adv['icon'] ?? '📦')?>"></div>
        <div class="fg"><label>Порядок</label><input type="text" name="adv_order" value="<?=htmlspecialchars($edit_adv['sort_order'] ?? '0')?>"></div>
        <div class="fg"><label>Фото</label><input type="file" name="adv_image" accept="image/*"></div>
      </div>
      <button type="submit" class="btn b-blue"><?=$edit_adv ? 'Сохранить' : 'Добавить'?></button>
      <?php if($edit_adv): ?><a href="?tab=advantages" class="btn b-yellow">Отмена</a><?php endif; ?>
    </form>
  </div>
  <div class="card">
    <h3>Список преимуществ</h3>
    <table>
      <thead><tr><th>Фото</th><th>Заголовок</th><th>Подзаголовок</th><th>Порядок</th><th>Действия</th></tr></thead>
      <tbody>
      <?php foreach($adv_list as $a): ?>
        <?php $img = ($a['image'] && file_exists(ltrim($a['image'], '/'))) ? $a['image'] : ''; ?>
        <tr>
          <td><?php if($img): ?><img class="thumb" src="<?=htmlspecialchars($img)?>" alt=""><?php else: ?><span style="font-size:28px"><?=htmlspecialchars($a['icon'])?></span><?php endif; ?></td>
          <td><strong><?=htmlspecialchars($a['title'])?></strong></td>
          <td><?=htmlspecialchars($a['subtitle'])?></td>
          <td><?=$a['sort_order']?></td>
          <td><a href="?tab=advantages&editadv=<?=$a['id']?>" class="btn b-yellow btn-sm">Изменить</a> <a href="?tab=advantages&deladv=<?=$a['id']?>" class="btn b-red btn-sm" onclick="return confirm('Удалить?')">Удалить</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if($tab==='stats'): ?>
  <div class="fcard">
    <h3><?=$edit_stat ? '✏️ Редактировать показатель' : '➕ Добавить показатель'?></h3>
    <form method="POST">
      <input type="hidden" name="stat_save" value="1">
      <input type="hidden" name="stat_id" value="<?=$edit_stat['id'] ?? 0?>">
      <div class="row">
        <div class="fg"><label>Значение</label><input type="text" name="stat_number" value="<?=htmlspecialchars($edit_stat['number'] ?? '')?>" placeholder="15+"></div>
        <div class="fg"><label>Подпись</label><input type="text" name="stat_label" value="<?=htmlspecialchars($edit_stat['label'] ?? '')?>" placeholder="лет на рынке"></div>
        <div class="fg"><label>Порядок</label><input type="text" name="stat_order" value="<?=htmlspecialchars($edit_stat['sort_order'] ?? '0')?>"></div>
      </div>
      <button type="submit" class="btn b-blue"><?=$edit_stat ? 'Сохранить' : 'Добавить'?></button>
      <?php if($edit_stat): ?><a href="?tab=stats" class="btn b-yellow">Отмена</a><?php endif; ?>
    </form>
  </div>
  <div class="card">
    <h3>Список показателей</h3>
    <table>
      <thead><tr><th>Значение</th><th>Подпись</th><th>Порядок</th><th>Действия</th></tr></thead>
      <tbody>
      <?php foreach($stats_list as $s): ?>
        <tr>
          <td><strong style="font-size:22px;color:#2563eb"><?=htmlspecialchars($s['number'])?></strong></td>
          <td><?=htmlspecialchars($s['label'])?></td>
          <td><?=$s['sort_order']?></td>
          <td><a href="?tab=stats&editstat=<?=$s['id']?>" class="btn b-yellow btn-sm">Изменить</a> <a href="?tab=stats&delstat=<?=$s['id']?>" class="btn b-red btn-sm" onclick="return confirm('Удалить?')">Удалить</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if($tab==='services'): ?>
  <div class="stats-row">
    <div class="s"><div class="sn"><?=count($service_categories)?></div><div class="sl">Категорий</div></div>
    <div class="s"><div class="sn"><?=$totalServices?></div><div class="sl">Услуг</div></div>
    <div class="s"><div class="sn"><?=$serviceWithPhoto?></div><div class="sl">С фото</div></div>
    <div class="s"><div class="sn"><?=$serviceCatWithPhoto?></div><div class="sl">Категорий с фото</div></div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div class="muted">Для услуг используйте такое же фото, как у товаров: рекомендуемое соотношение сторон <strong>4:3</strong>, например <strong>1200×900 px</strong>. Тогда плитки на сайте будут одинаковыми по виду и размеру.</div>
  </div>

  <div class="grid2">
    <div class="card">
      <h3>➕ Добавить категорию услуг</h3>
      <form method="POST">
        <input type="hidden" name="add_svccat" value="1">
        <div class="row">
          <div class="fg"><label>Название</label><input type="text" name="svccat_name" placeholder="Например: Гальваника"></div>
          <div class="fg"><label>Slug</label><input type="text" name="svccat_slug" placeholder="galvanika"></div>
        </div>
        <button type="submit" class="btn b-blue">Добавить категорию</button>
      </form>
    </div>

    <div class="card">
      <h3>➕ Добавить услугу</h3>
      <form method="POST">
        <input type="hidden" name="add_svc" value="1">
        <div class="row">
          <div class="fg"><label>Название услуги</label><input type="text" name="svc_name" placeholder="Хроматирование"></div>
          <div class="fg"><label>Категория</label>
            <select name="svc_cat">
              <option value="">Выберите</option>
              <?php foreach($service_categories as $c): ?>
                <option value="<?=htmlspecialchars($c['slug'])?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn b-blue">Добавить услугу</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>Категории услуг</h3>
    <table>
      <thead><tr><th>Категория</th><th>Slug</th><th>Фото</th><th>Обновить фото</th><th>Сортировка</th><th>Действие</th></tr></thead>
      <tbody>
      <?php foreach($service_categories as $c): ?>
        <tr>
          <td><strong><?=htmlspecialchars($c['name'])?></strong></td>
          <td><span class="code"><?=htmlspecialchars($c['slug'])?></span></td>
          <td><img class="thumb" src="<?=htmlspecialchars(getServiceCatImg($c['slug']))?>" alt=""></td>
          <td>
            <form method="POST" enctype="multipart/form-data" class="inline-actions">
              <input type="hidden" name="upload_svccat_img" value="1">
              <input type="hidden" name="svccat_key" value="<?=htmlspecialchars($c['slug'])?>">
              <input type="file" name="svccat_img" accept="image/*" required>
              <span class="hint">4:3</span>
              <button type="submit" class="btn b-green btn-sm">Загрузить</button>
            </form>
          </td>
          <td>
            <div class="inline-actions">
              <a href="?tab=services&svccatup=<?=urlencode($c['slug'])?>" class="btn b-slate btn-sm">↑</a>
              <a href="?tab=services&svccatdown=<?=urlencode($c['slug'])?>" class="btn b-slate btn-sm">↓</a>
            </div>
          </td>
          <td><a href="?tab=services&delsvccat=<?=urlencode($c['slug'])?>" class="btn b-red btn-sm" onclick="return confirm('Удалить категорию услуг?')">Удалить</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Список услуг</h3>
    <form method="GET" class="filters">
      <input type="hidden" name="tab" value="services">
      <input type="text" name="ss" value="<?=htmlspecialchars($svcSearch)?>" placeholder="Поиск по названию">
      <select name="scf">
        <option value="">Все категории</option>
        <?php foreach($service_categories as $c): ?>
          <option value="<?=htmlspecialchars($c['slug'])?>" <?=$svcCf===$c['slug']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn b-blue" type="submit">Фильтр</button>
    </form>

    <table>
      <thead><tr><th>Фото</th><th>Название</th><th>Категория</th><th>Порядок</th><th>Обновить фото</th><th>Действия</th></tr></thead>
      <tbody>
      <?php foreach($services as $s): ?>
        <?php $img = ($s['image'] && file_exists(ltrim($s['image'], '/'))) ? $s['image'] : getPlaceholderImage(); ?>
        <tr>
          <td><img class="thumb" src="<?=htmlspecialchars($img)?>" alt=""></td>
          <td><strong><?=htmlspecialchars($s['title'])?></strong></td>
          <td><?=htmlspecialchars($service_cat_map[$s['category_slug']] ?? $s['category_slug'])?></td>
          <td><?=$s['sort_order']?></td>
          <td>
            <form method="POST" enctype="multipart/form-data" class="inline-actions">
              <input type="hidden" name="upload_svc_img" value="1">
              <input type="hidden" name="sid" value="<?=$s['id']?>">
              <input type="file" name="svc_img" accept="image/*" required>
              <span class="hint">4:3</span>
              <button type="submit" class="btn b-green btn-sm">Загрузить</button>
            </form>
          </td>
          <td>
            <div class="service-table-actions">
              <a href="?tab=services&svcup=<?=$s['id']?>" class="btn b-slate btn-sm">↑</a>
              <a href="?tab=services&svcdown=<?=$s['id']?>" class="btn b-slate btn-sm">↓</a>
              <a href="?tab=services&delsvc=<?=$s['id']?>" class="btn b-red btn-sm" onclick="return confirm('Удалить услугу?')">Удалить</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$services): ?><tr><td colspan="6" class="muted">Услуги не найдены.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if($tab==='settings'): ?>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="save_settings" value="1">
    <div class="card">
      <h3>🖼 Логотип</h3>
      <?php $logo = cfg($db, 'logo', ''); ?>
      <?php if($logo && file_exists(ltrim($logo, '/'))): ?>
        <img src="<?=htmlspecialchars($logo)?>" style="max-height:70px;background:#0f172a;padding:8px;border-radius:10px;margin-bottom:10px">
        <div><a href="?tab=settings&dellogo=1" class="btn b-red btn-sm" onclick="return confirm('Удалить логотип?')">Удалить логотип</a></div>
      <?php else: ?><p class="muted">Логотип не загружен</p><?php endif; ?>
      <input type="file" name="logo" accept="image/*">
    </div>

    <div class="card">
      <h3>🏠 Главный баннер</h3>
      <div class="row">
        <div class="fg"><label>H1</label><input type="text" name="hero_h1" value="<?=htmlspecialchars(cfg($db, 'hero_h1', ''))?>"></div>
        <div class="fg"><label>Подзаголовок</label><input type="text" name="hero_sub" value="<?=htmlspecialchars(cfg($db, 'hero_sub', ''))?>"></div>
      </div>
      <div class="row">
        <div class="fg">
          <label>Фоновое фото</label>
          <?php $hero = cfg($db, 'hero_bg', ''); ?>
          <?php if($hero && file_exists(ltrim($hero, '/'))): ?>
            <div style="margin-bottom:10px"><img src="<?=htmlspecialchars($hero)?>" style="max-height:90px;border-radius:10px"></div>
            <a href="?tab=settings&delhero_bg=1" class="btn b-red btn-sm" onclick="return confirm('Удалить фон?')">Удалить фон</a>
          <?php else: ?><p class="muted">Фон не загружен</p><?php endif; ?>
          <input type="file" name="hero_bg" accept="image/*">
        </div>
      </div>
    </div>

    <div class="card">
      <h3>📄 Заголовки страниц</h3>
      <div class="row">
        <div class="fg"><label>О компании — заголовок</label><input type="text" name="about_h1" value="<?=htmlspecialchars(cfg($db,'about_h1','О компании'))?>"></div>
        <div class="fg"><label>О компании — подзаголовок</label><input type="text" name="about_sub" value="<?=htmlspecialchars(cfg($db,'about_sub','Ваш надежный партнер'))?>"></div>
      </div>
      <div class="row">
        <div class="fg"><label>Услуги — заголовок</label><input type="text" name="services_h1" value="<?=htmlspecialchars(cfg($db,'services_h1','Услуги'))?>"></div>
        <div class="fg"><label>Услуги — подзаголовок</label><input type="text" name="services_sub" value="<?=htmlspecialchars(cfg($db,'services_sub','Металлообработка и гальваника'))?>"></div>
      </div>
      <div class="row">
        <div class="fg"><label>Контакты — заголовок</label><input type="text" name="contacts_h1" value="<?=htmlspecialchars(cfg($db,'contacts_h1','Контакты'))?>"></div>
        <div class="fg"><label>Контакты — подзаголовок</label><input type="text" name="contacts_sub" value="<?=htmlspecialchars(cfg($db,'contacts_sub','Свяжитесь с нами'))?>"></div>
      </div>
    </div>

    <div class="card">
      <h3>ℹ️ Текст «О компании»</h3>
      <textarea name="about" rows="10"><?=htmlspecialchars(cfg($db, 'about', ''))?></textarea>
    </div>

    <div class="card">
      <h3>📞 Контакты</h3>
      <div class="row">
        <div class="fg"><label>Телефон 1</label><input type="text" name="phone1" value="<?=htmlspecialchars(cfg($db, 'phone1', ''))?>"></div>
        <div class="fg"><label>Телефон 2</label><input type="text" name="phone2" value="<?=htmlspecialchars(cfg($db, 'phone2', ''))?>"></div>
      </div>
      <div class="row">
        <div class="fg"><label>Email 1</label><input type="text" name="email1" value="<?=htmlspecialchars(cfg($db, 'email1', ''))?>"></div>
        <div class="fg"><label>Email 2</label><input type="text" name="email2" value="<?=htmlspecialchars(cfg($db, 'email2', ''))?>"></div>
      </div>
      <div class="row"><div class="fg"><label>Адрес</label><input type="text" name="address" value="<?=htmlspecialchars(cfg($db, 'address', ''))?>"></div></div>
      <div class="row">
        <div class="fg"><label>Режим работы — строка 1</label><input type="text" name="hours1" value="<?=htmlspecialchars(cfg($db, 'hours1', ''))?>"></div>
        <div class="fg"><label>Режим работы — строка 2</label><input type="text" name="hours2" value="<?=htmlspecialchars(cfg($db, 'hours2', ''))?>"></div>
      </div>
    </div>

    <button type="submit" class="btn b-blue">Сохранить настройки</button>
  </form>
<?php endif; ?>

<?php if($tab==='seo'): ?>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="save_seo" value="1">

    <div class="card">
      <h3>🌐 Favicon</h3>
      <?php $fav = cfg($db, 'favicon', ''); ?>
      <?php if($fav && file_exists(ltrim($fav, '/'))): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px"><img src="<?=htmlspecialchars($fav)?>" style="width:32px;height:32px;object-fit:contain"><a href="?tab=seo&delfavicon=1" class="btn b-red btn-sm" onclick="return confirm('Удалить favicon?')">Удалить</a></div>
      <?php else: ?><p class="muted">Favicon не загружен</p><?php endif; ?>
      <input type="file" name="favicon" accept=".ico,.png,.svg,.jpg,.webp">
    </div>

    <div class="card">
      <h3>🖼 OG-изображение</h3>
      <?php $ogi = cfg($db, 'seo_og_image', ''); ?>
      <?php if($ogi && file_exists(ltrim($ogi, '/'))): ?>
        <div style="margin-bottom:12px"><img src="<?=htmlspecialchars($ogi)?>" style="max-height:100px;border-radius:10px"></div>
        <a href="?tab=seo&delogimg=1" class="btn b-red btn-sm" onclick="return confirm('Удалить?')">Удалить</a>
      <?php else: ?><p class="muted">OG-изображение не загружено</p><?php endif; ?>
      <input type="file" name="og_image" accept="image/*">
    </div>

    <div class="card">
      <h3>🔍 Основное SEO</h3>
      <div class="row">
        <div class="fg"><label>Название сайта</label><input type="text" name="seo_site_name" value="<?=htmlspecialchars(cfg($db, 'seo_site_name', ''))?>"></div>
        <div class="fg"><label>Город</label><input type="text" name="seo_city" value="<?=htmlspecialchars(cfg($db, 'seo_city', ''))?>"></div>
      </div>
      <div class="row"><div class="fg"><label>Общее описание</label><textarea name="seo_description"><?=htmlspecialchars(cfg($db, 'seo_description', ''))?></textarea></div></div>
      <div class="row"><div class="fg"><label>Ключевые слова</label><input type="text" name="seo_keywords" value="<?=htmlspecialchars(cfg($db, 'seo_keywords', ''))?>"></div></div>
      <div class="row"><div class="fg"><label>Google verification</label><input type="text" name="google_verification" value="<?=htmlspecialchars(cfg($db, 'google_verification', ''))?>"></div></div>
    </div>

    <div class="card">
      <h3>📄 Описания страниц</h3>
      <div class="row"><div class="fg"><label>Главная</label><input type="text" name="seo_index_desc" value="<?=htmlspecialchars(cfg($db, 'seo_index_desc', ''))?>"></div></div>
      <div class="row"><div class="fg"><label>О компании</label><input type="text" name="seo_about_desc" value="<?=htmlspecialchars(cfg($db, 'seo_about_desc', ''))?>"></div></div>
      <div class="row"><div class="fg"><label>Услуги</label><input type="text" name="seo_services_desc" value="<?=htmlspecialchars(cfg($db, 'seo_services_desc', ''))?>"></div></div>
      <div class="row"><div class="fg"><label>Контакты</label><input type="text" name="seo_contacts_desc" value="<?=htmlspecialchars(cfg($db, 'seo_contacts_desc', ''))?>"></div></div>
      <div class="row"><div class="fg"><label>Каталог</label><input type="text" name="seo_catalog_desc" value="<?=htmlspecialchars(cfg($db, 'seo_catalog_desc', ''))?>"></div></div>
    </div>

    <button type="submit" class="btn b-blue">Сохранить SEO</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
