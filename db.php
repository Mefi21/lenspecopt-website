<?php
$db_dir = __DIR__ . '/db';
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0755, true);
}

try {
    $db = new PDO('sqlite:' . $db_dir . '/products.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL");
} catch (Exception $e) {
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

function ensureColumn(PDO $db, string $table, string $column, string $definition): void {
    try {
        $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    } catch (Exception $e) {
    }
}

function cfg(PDO $db, string $key, $default = '') {
    try {
        $st = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $st->execute([$key]);
        $value = $st->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setCfg(PDO $db, string $key, string $value): void {
    $st = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
    $st->execute([$key, $value]);
}

function slugify(string $text): string {
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'і'=>'i','ї'=>'yi','є'=>'e',
    ];
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

function getPlaceholderImage(): string {
    foreach ([
        'images/placeholder.jpg',
        'images/placeholder.png',
        'uploads/placeholder.jpg',
        'uploads/placeholder.png',
    ] as $path) {
        if (file_exists(__DIR__ . '/' . $path) || file_exists($path)) {
            return '/' . ltrim($path, '/');
        }
    }

    return 'data:image/svg+xml;utf8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">'
        . '<rect width="600" height="400" fill="#e2e8f0"/>'
        . '<rect x="205" y="115" width="190" height="130" rx="18" fill="#cbd5e1"/>'
        . '<circle cx="262" cy="170" r="18" fill="#94a3b8"/>'
        . '<path d="M225 230l58-42 42 30 28-22 45 34H225z" fill="#94a3b8"/>'
        . '<text x="300" y="292" text-anchor="middle" font-family="Arial,sans-serif" font-size="24" fill="#64748b">Нет фото</text>'
        . '</svg>'
    );
}

function getCatImg(string $slug): string {
    foreach (['jpg','jpeg','png','webp','gif','svg'] as $ext) {
        $path = "uploads/categories/{$slug}.{$ext}";
        if (file_exists($path)) return '/' . $path;
    }
    return getPlaceholderImage();
}

function getServiceCatImg(string $slug): string {
    foreach (['jpg','jpeg','png','webp','gif','svg'] as $ext) {
        $path = "uploads/service_categories/{$slug}.{$ext}";
        if (file_exists($path)) return '/' . $path;
    }
    return getPlaceholderImage();
}

function uniqueServiceSlug(PDO $db, string $base, int $excludeId = 0): string {
    $slug = slugify($base);
    $candidate = $slug;
    $i = 2;
    while (true) {
        if ($excludeId > 0) {
            $st = $db->prepare("SELECT id FROM services WHERE slug = ? AND id != ? LIMIT 1");
            $st->execute([$candidate, $excludeId]);
        } else {
            $st = $db->prepare("SELECT id FROM services WHERE slug = ? LIMIT 1");
            $st->execute([$candidate]);
        }
        if (!$st->fetchColumn()) {
            return $candidate;
        }
        $candidate = $slug . '-' . $i;
        $i++;
    }
}

function normalizeServiceData(PDO $db): void {
    if (cfg($db, 'services_sync_v3_done', '') === '1') {
        return;
    }

    $db->beginTransaction();
    try {
        // Удаляем старые мусорные услуги без категории.
        $db->exec("DELETE FROM services WHERE TRIM(COALESCE(category_slug, '')) = ''");

        $rows = $db->query("SELECT * FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $best = [];
        $deleteIds = [];

        foreach ($rows as $row) {
            $cat = trim((string)($row['category_slug'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            if ($cat === '' || $title === '') {
                $deleteIds[] = (int)$row['id'];
                continue;
            }
            $key = mb_strtolower($cat . '|' . $title, 'UTF-8');
            if (!isset($best[$key])) {
                $best[$key] = $row;
                continue;
            }

            $current = $best[$key];
            $scoreCurrent = (!empty($current['image']) ? 3 : 0) + ((int)($current['sort_order'] ?? 0) > 0 ? 1 : 0);
            $scoreRow = (!empty($row['image']) ? 3 : 0) + ((int)($row['sort_order'] ?? 0) > 0 ? 1 : 0);

            if ($scoreRow > $scoreCurrent) {
                $deleteIds[] = (int)$current['id'];
                $best[$key] = $row;
            } else {
                $deleteIds[] = (int)$row['id'];
            }
        }

        if ($deleteIds) {
            $del = $db->prepare("DELETE FROM services WHERE id = ?");
            foreach ($deleteIds as $id) $del->execute([$id]);
        }

        $seedMap = [
            'metalloobrabotka' => [
                'Холодная штамповка','Холодная высадка под заказ','Лазерная резка','Гибка металла','Сварочные работы','Слесарные работы','Токарно-фрезерные работы','Электроэрозионная резка, EDM обработка','Изготовление и ремонт штампов','Лазерная и фрезерная гравировка','Шлифовка металла','Покраска','Ленточнопильная резка металла','Услуги по накатке резьбы','Термообработка металла','Виброгалтовка','Зиговка металла','Гальваническое покрытие металла','Услуги формовки (2D гибка проволоки)','Запрессовка крепежа','Конденсаторная сварка','Конструкторский отдел','Услуга отбортовки по ОСТ 1 03728-74 (пуклёвка)','Производство гидравлических и пневматических плит, блоков по техническому заданию','Нанесение ПВХ покрытия на металлические изделия (пластизоль)','Художественные изделия из металла','Виброполировка металлических деталей','Холодная штамповка вытяжкой','Буквы, фигуры, вывески и таблички из металла',
            ],
            'galvanika' => [
                'Цинкование','Олово-висмут','Оловянирование','Никелирование матовое','Хроматирование','Меднение','Химическое никелирование','Анодирование','Эматалирование','Фосфатирование','Химическое оксидирование','Кадмирование','Черное цинкование','Пассивация','Нанесение покрытия Гор.ПОС H1-3','Нанесение покрытия ГорПОС 06',
            ],
        ];

        $find = $db->prepare("SELECT id FROM services WHERE category_slug = ? AND title = ? LIMIT 1");
        $insert = $db->prepare("INSERT INTO services (title, subtitle, icon, sort_order, image, slug, category_slug, content, keywords, is_active) VALUES (?, '', '⚙️', ?, '', ?, ?, '', '', 1)");
        foreach ($seedMap as $catSlug => $titles) {
            $order = 1;
            foreach ($titles as $title) {
                $find->execute([$catSlug, $title]);
                $id = (int)$find->fetchColumn();
                if ($id > 0) {
                    $db->prepare("UPDATE services SET sort_order = CASE WHEN sort_order <= 0 THEN ? ELSE sort_order END, is_active = 1 WHERE id = ?")
                       ->execute([$order, $id]);
                } else {
                    $slug = uniqueServiceSlug($db, $title . '-' . $catSlug);
                    $insert->execute([$title, $order, $slug, $catSlug]);
                }
                $order++;
            }
        }

        $rows = $db->query("SELECT id, title, category_slug, slug, sort_order FROM services ORDER BY category_slug, sort_order, title, id")->fetchAll(PDO::FETCH_ASSOC);
        $upd = $db->prepare("UPDATE services SET slug = ?, sort_order = ?, is_active = 1 WHERE id = ?");
        $orders = [];
        foreach ($rows as $row) {
            $cat = trim((string)$row['category_slug']);
            if ($cat === '') continue;
            if (!isset($orders[$cat])) $orders[$cat] = 1;
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                $slug = uniqueServiceSlug($db, $row['title'] . '-' . $cat, (int)$row['id']);
            } else {
                $slug = uniqueServiceSlug($db, $slug, (int)$row['id']);
            }
            $upd->execute([$slug, $orders[$cat], (int)$row['id']]);
            $orders[$cat]++;
        }

        setCfg($db, 'services_sync_v3_done', '1');
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
    }
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        name TEXT NOT NULL,
        image TEXT DEFAULT '',
        is_popular INTEGER DEFAULT 0,
        featured INTEGER DEFAULT 0,
        is_sale INTEGER DEFAULT 0,
        description TEXT DEFAULT '',
        price TEXT DEFAULT ''
    )");
    ensureColumn($db, 'products', 'image', "TEXT DEFAULT ''");
    ensureColumn($db, 'products', 'is_popular', "INTEGER DEFAULT 0");
    ensureColumn($db, 'products', 'featured', "INTEGER DEFAULT 0");
    ensureColumn($db, 'products', 'is_sale', "INTEGER DEFAULT 0");
    ensureColumn($db, 'products', 'description', "TEXT DEFAULT ''");
    ensureColumn($db, 'products', 'price', "TEXT DEFAULT ''");

    $db->exec("UPDATE products SET is_sale = featured WHERE is_sale IS NULL OR is_sale = 0");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        slug TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0
    )");
    ensureColumn($db, 'categories', 'sort_order', "INTEGER DEFAULT 0");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT DEFAULT ''
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS advantages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        subtitle TEXT DEFAULT '',
        icon TEXT DEFAULT '📦',
        image TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 0
    )");
    ensureColumn($db, 'advantages', 'subtitle', "TEXT DEFAULT ''");
    ensureColumn($db, 'advantages', 'icon', "TEXT DEFAULT '📦'");
    ensureColumn($db, 'advantages', 'image', "TEXT DEFAULT ''");
    ensureColumn($db, 'advantages', 'sort_order', "INTEGER DEFAULT 0");

    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        number TEXT NOT NULL,
        label TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0
    )");
    ensureColumn($db, 'stats', 'sort_order', "INTEGER DEFAULT 0");

    $db->exec("CREATE TABLE IF NOT EXISTS service_categories (
        slug TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        short_desc TEXT DEFAULT '',
        keywords TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1
    )");
    ensureColumn($db, 'service_categories', 'short_desc', "TEXT DEFAULT ''");
    ensureColumn($db, 'service_categories', 'keywords', "TEXT DEFAULT ''");
    ensureColumn($db, 'service_categories', 'sort_order', "INTEGER DEFAULT 0");
    ensureColumn($db, 'service_categories', 'is_active', "INTEGER DEFAULT 1");

    $db->exec("CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        icon TEXT DEFAULT '⚙️',
        sort_order INTEGER DEFAULT 0,
        subtitle TEXT DEFAULT '',
        image TEXT DEFAULT '',
        slug TEXT DEFAULT '',
        category_slug TEXT DEFAULT '',
        content TEXT DEFAULT '',
        keywords TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1
    )");
    ensureColumn($db, 'services', 'description', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'icon', "TEXT DEFAULT '⚙️'");
    ensureColumn($db, 'services', 'sort_order', "INTEGER DEFAULT 0");
    ensureColumn($db, 'services', 'subtitle', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'image', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'slug', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'category_slug', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'content', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'keywords', "TEXT DEFAULT ''");
    ensureColumn($db, 'services', 'is_active', "INTEGER DEFAULT 1");
    $db->exec("DROP INDEX IF EXISTS idx_services_slug");

    $defaultCategories = [
        ['ankery','Анкеры',1],['bolty','Болты',2],['vinty','Винты',3],['gayki','Гайки',4],['zaglushki','Заглушки и пробки',5],['zaklepki','Заклёпки',6],['kolca-stopornye','Кольца стопорные',7],['kryuki-kolca','Крюки и кольца',8],['latun','Латунь, бронза, медь',9],['maslenki','Маслёнки',10],['mikrokrepezh','Микрокрепёж',11],['nestandart','Нестандартный крепёж',12],['pruzhiny','Пружины тарельчатые',13],['samorezy','Саморезы',14],['takelazh','Такелаж',15],['shaiba-gost','Шайба ГОСТ 6402-70',16],['shaiba','Шайбы',17],['shpilki','Шпильки',18],['shplinty','Шплинты',19],['shponki','Шпонки',20],['shponochny','Шпоночный прокат',21],['shtangi','Штанги и шпильки',22],['shtifty','Штифты',23],
    ];
    $catStmt = $db->prepare("INSERT OR IGNORE INTO categories (slug, name, sort_order) VALUES (?, ?, ?)");
    foreach ($defaultCategories as $row) $catStmt->execute($row);

    $svcCatSeed = [
        ['metalloobrabotka','Металлообработка',1],
        ['galvanika','Гальваника',2],
    ];
    $svcCatStmt = $db->prepare("INSERT OR IGNORE INTO service_categories (slug, name, short_desc, keywords, sort_order, is_active) VALUES (?, ?, '', '', ?, 1)");
    $svcCatUpd = $db->prepare("UPDATE service_categories SET name = ?, sort_order = ?, is_active = 1 WHERE slug = ?");
    foreach ($svcCatSeed as $row) {
        $svcCatStmt->execute([$row[0], $row[1], $row[2]]);
        $svcCatUpd->execute([$row[1], $row[2], $row[0]]);
    }

    $defaultSettings = [
        'phone1' => '+7 (XXX) XXX-XX-XX',
        'phone2' => '+7 (XXX) XXX-XX-XX',
        'email1' => 'info@lsopt.ru',
        'email2' => 'sales@lsopt.ru',
        'address' => 'Санкт-Петербург, укажите ваш адрес',
        'hours1' => 'Пн-Пт: 9:00-18:00',
        'hours2' => 'Сб-Вс: выходной',
        'logo' => '',
        'about' => '',
        'hero_h1' => 'Крепеж и метизы оптом и в розницу',
        'hero_sub' => 'Болты, гайки, шайбы, саморезы — более 10 000 наименований на складе',
        'hero_bg' => '',
        'about_h1' => 'О компании',
        'about_sub' => 'Ваш надежный партнер в поставках крепежа',
        'services_h1' => 'Услуги',
        'services_sub' => 'Металлообработка и гальваника для вашего производства',
        'contacts_h1' => 'Контакты',
        'contacts_sub' => 'Свяжитесь с нами любым удобным способом',
        'seo_site_name' => 'ЛЕНСПЕЦОПТ',
        'seo_city' => 'Санкт-Петербург',
        'seo_description' => 'ЛЕНСПЕЦОПТ — крепеж, метизы, металлообработка и гальваника в Санкт-Петербурге.',
        'seo_keywords' => 'ленспецопт, ленспейопт, lsopt, крепеж, метизы, металлообработка, гальваника, Санкт-Петербург',
        'seo_index_desc' => 'ЛЕНСПЕЦОПТ: поставка крепежа, метизов, металлообработка и гальваника в Санкт-Петербурге.',
        'seo_about_desc' => 'Информация о компании ЛЕНСПЕЦОПТ.',
        'seo_services_desc' => 'Металлообработка и гальваника в Санкт-Петербурге.',
        'seo_contacts_desc' => 'Контакты ЛЕНСПЕЦОПТ.',
        'seo_catalog_desc' => 'Каталог крепежа и метизов ЛЕНСПЕЦОПТ.',
        'favicon' => '',
        'seo_og_image' => '',
        'google_verification' => '',
    ];
    foreach ($defaultSettings as $key => $value) {
        if (cfg($db, $key, null) === null) {
            setCfg($db, $key, $value);
        }
    }

    if ((int)$db->query("SELECT COUNT(*) FROM advantages")->fetchColumn() === 0) {
        $ins = $db->prepare("INSERT INTO advantages (title, subtitle, icon, image, sort_order) VALUES (?, ?, ?, '', ?)");
        foreach ([
            ['Большой склад','Более 10 000 наименований всегда в наличии','📦',1],
            ['Низкие цены','Работаем напрямую с производителями','💰',2],
            ['Быстрая доставка','Доставка по России в кратчайшие сроки','🚚',3],
            ['Гарантия качества','Сертифицированная продукция и контроль качества','✅',4],
        ] as $row) $ins->execute($row);
    }

    if ((int)$db->query("SELECT COUNT(*) FROM stats")->fetchColumn() === 0) {
        $ins = $db->prepare("INSERT INTO stats (number, label, sort_order) VALUES (?, ?, ?)");
        foreach ([['15+','лет на рынке',1],['10000+','наименований',2],['5000+','клиентов',3],['24/7','прием заказов',4]] as $row) $ins->execute($row);
    }

    normalizeServiceData($db);
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_services_slug ON services(slug) WHERE slug IS NOT NULL AND slug <> ''");
} catch (Exception $e) {
    die('Ошибка инициализации БД: ' . $e->getMessage());
}
