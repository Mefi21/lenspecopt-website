<?php
require_once __DIR__ . '/db.php';

$product_categories = $db->query("SELECT slug, name FROM categories WHERE slug <> 'rasprodazha-krepezha' ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$service_categories = $db->query("SELECT slug, name FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$service_rows = $db->query("SELECT slug, category_slug, title FROM services WHERE is_active = 1 ORDER BY category_slug, sort_order, title")->fetchAll(PDO::FETCH_ASSOC);

$services_by_category = [];
foreach ($service_rows as $row) {
    $services_by_category[$row['category_slug']][] = [
        'slug' => $row['slug'],
        'title' => $row['title'],
    ];
}

$prefill_request_type = trim($_GET['request_type'] ?? 'product');
$prefill_service_category = trim($_GET['service_category'] ?? '');
$prefill_service = trim($_GET['service'] ?? '');

$page_title = cfg($db, 'contacts_h1', 'Контакты') . ' — ' . cfg($db, 'seo_site_name', 'ЛЕНСПЕЦОПТ');
$page_desc  = cfg($db, 'seo_contacts_desc', cfg($db, 'seo_description', ''));
$contacts_h1  = cfg($db, 'contacts_h1', 'Контакты');
$contacts_sub = cfg($db, 'contacts_sub', 'Свяжитесь с нами любым удобным способом');
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
    .page-header { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: white; padding: 60px 0; text-align: center; }
    .page-header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 12px; color: white; }
    .page-header p { font-size: 1.1rem; color: rgba(255,255,255,0.85); max-width: 620px; margin: 0 auto; }
    .request-switch { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; }
    .request-switch label { display: block; cursor: pointer; border: 2px solid #dbeafe; background: #fff; color: #1e293b; border-radius: 10px; padding: 12px 14px; font-weight: 700; text-align: center; transition: all .2s ease; }
    .request-switch input { display: none; }
    .request-switch label.active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
    .field-hint { color: #64748b; font-size: 13px; margin-top: 6px; }
    @media (max-width: 768px) { .page-header h1 { font-size: 1.8rem; } .request-switch { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<section class="page-header">
  <div class="container">
    <h1><?=htmlspecialchars($contacts_h1)?></h1>
    <p><?=htmlspecialchars($contacts_sub)?></p>
  </div>
</section>

<section class="contacts-section">
  <div class="container">
    <div class="contacts-wrapper">
      <div class="contact-info-block">
        <h2>Наши контакты</h2>
        <div class="contact-item-block"><div class="contact-icon-block">📞</div><div><h4>Телефоны</h4><p><?=htmlspecialchars(cfg($db,'phone1'))?><br><?=htmlspecialchars(cfg($db,'phone2'))?></p></div></div>
        <div class="contact-item-block"><div class="contact-icon-block">📧</div><div><h4>Email</h4><p><?=htmlspecialchars(cfg($db,'email1'))?><br><?=htmlspecialchars(cfg($db,'email2'))?></p></div></div>
        <div class="contact-item-block"><div class="contact-icon-block">📍</div><div><h4>Адрес</h4><p><?=nl2br(htmlspecialchars(cfg($db,'address')))?></p></div></div>
        <div class="contact-item-block"><div class="contact-icon-block">🕒</div><div><h4>Режим работы</h4><p><?=htmlspecialchars(cfg($db,'hours1'))?><br><?=htmlspecialchars(cfg($db,'hours2'))?></p></div></div>
      </div>

      <div class="contact-form-block">
        <h2>Оставьте заявку</h2>
        <p>Наш менеджер свяжется с вами в течение 15 минут.</p>
        <div id="successMsg" class="success-msg" style="display:none;">✓ Спасибо! Ваша заявка отправлена.</div>

        <form id="contactFormMain" onsubmit="handleFormSubmit(event)">
          <div class="form-group-block">
            <label>Тип обращения</label>
            <div class="request-switch" id="requestSwitch">
              <label data-type="product"><input type="radio" name="request_type" value="product"><span>Товары</span></label>
              <label data-type="service"><input type="radio" name="request_type" value="service"><span>Услуги</span></label>
              <label data-type="other"><input type="radio" name="request_type" value="other"><span>Другое</span></label>
            </div>
          </div>

          <div class="form-group-block"><label>Ваше имя *</label><input type="text" name="name" required placeholder="Иван Иванов"></div>
          <div class="form-group-block"><label>Телефон *</label><input type="tel" name="phone" required placeholder="+7 (___) ___-__-__"></div>
          <div class="form-group-block"><label>Email</label><input type="email" name="email" placeholder="example@mail.ru"></div>

          <div id="productBlock" class="form-group-block">
            <label>Категория товаров</label>
            <select name="product" id="productSelect">
              <option value="">Выберите из списка</option>
              <?php foreach ($product_categories as $c): ?>
                <option value="<?=htmlspecialchars($c['slug'])?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="serviceCategoryBlock" class="form-group-block">
            <label>Категория услуг</label>
            <select name="service_category" id="serviceCategorySelect">
              <option value="">Выберите категорию</option>
              <?php foreach ($service_categories as $c): ?>
                <option value="<?=htmlspecialchars($c['slug'])?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="serviceBlock" class="form-group-block">
            <label>Услуга</label>
            <select name="service_name" id="serviceSelect">
              <option value="">Сначала выберите категорию</option>
            </select>
            <div class="field-hint">Список услуг меняется в зависимости от выбранной категории.</div>
          </div>

          <div class="form-group-block"><label>Комментарий</label><textarea name="message" rows="4" placeholder="Опишите ваш запрос, сроки, объем, материал и другие детали"></textarea></div>
          <button type="submit" class="btn btn-primary" style="width:100%;">Отправить заявку</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
<script src="script.js"></script>
<script>
(function() {
  const servicesByCategory = <?=json_encode($services_by_category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
  const requestSwitch = document.getElementById('requestSwitch');
  const typeLabels = requestSwitch.querySelectorAll('label[data-type]');
  const productBlock = document.getElementById('productBlock');
  const serviceCategoryBlock = document.getElementById('serviceCategoryBlock');
  const serviceBlock = document.getElementById('serviceBlock');
  const serviceCategorySelect = document.getElementById('serviceCategorySelect');
  const serviceSelect = document.getElementById('serviceSelect');
  const productSelect = document.getElementById('productSelect');
  let currentType = <?=json_encode($prefill_request_type ?: 'product', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
  let prefillServiceCategory = <?=json_encode($prefill_service_category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
  let prefillService = <?=json_encode($prefill_service, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;

  function fillServices(categorySlug, selectedSlug) {
    serviceSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    if (!categorySlug || !servicesByCategory[categorySlug] || !servicesByCategory[categorySlug].length) {
      placeholder.value = '';
      placeholder.textContent = 'Сначала выберите категорию';
      serviceSelect.appendChild(placeholder);
      return;
    }
    placeholder.value = '';
    placeholder.textContent = 'Выберите услугу';
    serviceSelect.appendChild(placeholder);
    servicesByCategory[categorySlug].forEach(item => {
      const option = document.createElement('option');
      option.value = item.slug;
      option.textContent = item.title;
      if (selectedSlug && selectedSlug === item.slug) option.selected = true;
      serviceSelect.appendChild(option);
    });
  }

  function updateType(type) {
    currentType = type;
    typeLabels.forEach(label => {
      const isActive = label.getAttribute('data-type') === type;
      label.classList.toggle('active', isActive);
      label.querySelector('input').checked = isActive;
    });
    productBlock.style.display = (type === 'product') ? 'block' : 'none';
    serviceCategoryBlock.style.display = (type === 'service') ? 'block' : 'none';
    serviceBlock.style.display = (type === 'service') ? 'block' : 'none';
    if (type !== 'product') productSelect.value = '';
    if (type !== 'service') {
      serviceCategorySelect.value = '';
      fillServices('', '');
    }
  }

  typeLabels.forEach(label => label.addEventListener('click', function() { updateType(this.getAttribute('data-type')); }));
  serviceCategorySelect.addEventListener('change', function() { fillServices(this.value, ''); });

  updateType(currentType);
  if (prefillServiceCategory) {
    serviceCategorySelect.value = prefillServiceCategory;
    fillServices(prefillServiceCategory, prefillService);
    updateType('service');
  } else {
    fillServices(serviceCategorySelect.value, prefillService);
  }
})();
</script>
</body>
</html>
