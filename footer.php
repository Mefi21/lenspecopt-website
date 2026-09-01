<?php require_once __DIR__ . '/db.php'; ?>
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>ЛЕНСПЕЦОПТ</h3>
                <p>Надежный поставщик крепежных изделий с 2010 года</p>
            </div>
            <div class="footer-section">
                <h3>Навигация</h3>
                <a href="/">Главная</a>
                <a href="/about.php">О компании</a>
                <a href="/catalog.php">Каталог</a>
                <a href="/services.php">Услуги</a>
                <a href="/contacts.php">Контакты</a>
            </div>
            <div class="footer-section">
                <h3>Контакты</h3>
                <p>📞 <?php echo htmlspecialchars(cfg($db, 'phone1', '+7 (XXX) XXX-XX-XX')); ?></p>
                <p>📧 <?php echo htmlspecialchars(cfg($db, 'email1', 'info@lsopt.ru')); ?></p>
                <p>📍 <?php echo htmlspecialchars(cfg($db, 'address', 'Адрес вашего офиса')); ?></p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> ЛЕНСПЕЦОПТ. Все права защищены.</p>
        </div>
    </div>
</footer>