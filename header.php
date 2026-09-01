<?php
require_once __DIR__ . '/db.php';
$cur = basename($_SERVER['PHP_SELF']);

$_cats = $db->query("SELECT slug, name FROM categories WHERE slug <> 'rasprodazha-krepezha' ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$_serviceCats = $db->query("SELECT slug, name FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$_logo = cfg($db, 'logo');

$isCatalogActive = ($cur === 'catalog.php' && !isset($_GET['sale']));
$isServicesActive = ($cur === 'services.php');
$isSaleActive = ($cur === 'catalog.php' && isset($_GET['sale']) && $_GET['sale'] == '1');
?>
<style>
.nav-dropdown-wrapper { position: relative; display: flex; align-items: center; }
.nav-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    width: 340px;
    display: none;
    flex-direction: column;
    background: #ff8c00;
    box-shadow: 0 8px 24px rgba(0,0,0,.22);
    border-radius: 0 0 10px 10px;
    z-index: 9999;
    max-height: 72vh;
    overflow-y: auto;
}
.nav-dropdown.open { display: flex; }
.nav-dropdown a {
    padding: 13px 18px;
    color: #fff !important;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: .2px;
    border-bottom: 1px solid rgba(255,255,255,.14);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.nav-dropdown a::after { content: '›'; opacity: .75; font-size: 18px; }
.nav-dropdown a:hover { background: #e67700; }
.nav-dropdown a:last-child { border-bottom: none; }
.logo a img { max-height: 80px; width: auto; display: block; }
.header-main { padding: 10px 0; }
@media (max-width: 768px) {
    .nav-dropdown-wrapper { width: 100%; align-items: flex-start; }
    .nav-dropdown { position: static; width: 100%; box-shadow: none; border-radius: 8px; margin-top: 8px; }
}
</style>

<header class="header">
    <div class="header-top">
        <div class="container">
            <div class="header-contacts">
                <span>📞 <?=htmlspecialchars(cfg($db, 'phone1', '+7 (XXX) XXX-XX-XX'))?></span>
                <span>📧 <?=htmlspecialchars(cfg($db, 'email1', 'info@lsopt.ru'))?></span>
                <span>🕒 <?=htmlspecialchars(cfg($db, 'hours1', 'Пн-Пт: 9:00-18:00'))?></span>
            </div>
        </div>
    </div>

    <div class="header-main">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="/">
                        <?php if ($_logo && file_exists(ltrim($_logo, '/'))): ?>
                            <img src="<?=htmlspecialchars($_logo)?>" alt="<?=htmlspecialchars(cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ'))?>">
                        <?php else: ?>
                            <?=htmlspecialchars(cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ'))?>
                        <?php endif; ?>
                    </a>
                </div>

                <nav class="nav">
                    <a href="/" <?=in_array($cur, ['index.php', '']) ? 'class="active"' : ''?>>Главная</a>
                    <a href="/about.php" <?=$cur === 'about.php' ? 'class="active"' : ''?>>О компании</a>

                    <div class="nav-dropdown-wrapper" id="catalogWrapper">
                        <a href="/catalog.php" id="catalogToggle" <?=$isCatalogActive ? 'class="active"' : ''?>>Каталог</a>
                        <div class="nav-dropdown" id="catalogDropdown">
                            <?php foreach ($_cats as $c): ?>
                                <a href="/catalog.php?cat=<?=htmlspecialchars($c['slug'])?>"><?=htmlspecialchars($c['name'])?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="nav-dropdown-wrapper" id="servicesWrapper">
                        <a href="/services.php" id="servicesToggle" <?=$isServicesActive ? 'class="active"' : ''?>>Услуги</a>
                        <div class="nav-dropdown" id="servicesDropdown">
                            <?php foreach ($_serviceCats as $c): ?>
                                <a href="/services.php?cat=<?=htmlspecialchars($c['slug'])?>"><?=htmlspecialchars($c['name'])?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <a href="/catalog.php?sale=1" <?=$isSaleActive ? 'class="active"' : ''?>>Распродажа</a>
                    <a href="/contacts.php" <?=$cur === 'contacts.php' ? 'class="active"' : ''?>>Контакты</a>
                </nav>

                <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
            </div>
        </div>
    </div>
</header>

<script>
(function() {
    function initDropdown(wrapperId, toggleId, dropdownId) {
        var wrapper = document.getElementById(wrapperId);
        var toggle = document.getElementById(toggleId);
        var dropdown = document.getElementById(dropdownId);
        if (!wrapper || !toggle || !dropdown) return;

        wrapper.addEventListener('mouseenter', function() {
            if (window.innerWidth > 768) dropdown.classList.add('open');
        });
        wrapper.addEventListener('mouseleave', function() {
            if (window.innerWidth > 768) dropdown.classList.remove('open');
        });

        toggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 || !dropdown.classList.contains('open')) {
                e.preventDefault();
                dropdown.classList.toggle('open');
            }
        });

        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) dropdown.classList.remove('open');
        });
    }

    initDropdown('catalogWrapper', 'catalogToggle', 'catalogDropdown');
    initDropdown('servicesWrapper', 'servicesToggle', 'servicesDropdown');
})();
</script>
