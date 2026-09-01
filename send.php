<?php
error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/db.php';
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод']);
    exit;
}

function clean_text($value): string {
    return trim((string)$value);
}

$name            = htmlspecialchars(clean_text($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$phone           = htmlspecialchars(clean_text($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$email           = clean_text($_POST['email'] ?? '');
$requestType     = clean_text($_POST['request_type'] ?? 'product');
$productSlug     = clean_text($_POST['product'] ?? '');
$serviceCategory = clean_text($_POST['service_category'] ?? '');
$serviceSlug     = clean_text($_POST['service_name'] ?? '');
$message         = htmlspecialchars(clean_text($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

if ($name === '' || $phone === '') {
    echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
    exit;
}

function findCategoryName(PDO $db, string $slug): string {
    if ($slug === '') return '';
    $st = $db->prepare("SELECT name FROM categories WHERE slug = ?");
    $st->execute([$slug]);
    return (string)$st->fetchColumn();
}
function findServiceCategoryName(PDO $db, string $slug): string {
    if ($slug === '') return '';
    $st = $db->prepare("SELECT name FROM service_categories WHERE slug = ?");
    $st->execute([$slug]);
    return (string)$st->fetchColumn();
}
function findServiceName(PDO $db, string $slug): string {
    if ($slug === '') return '';
    $st = $db->prepare("SELECT title FROM services WHERE slug = ?");
    $st->execute([$slug]);
    return (string)$st->fetchColumn();
}

$requestTypeMap = [
    'product' => 'Товары',
    'service' => 'Услуги',
    'other'   => 'Другое',
];

$requestTypeLabel = $requestTypeMap[$requestType] ?? 'Другое';
$productName = findCategoryName($db, $productSlug);
$serviceCategoryName = findServiceCategoryName($db, $serviceCategory);
$serviceName = findServiceName($db, $serviceSlug);

$summaryParts = ["Тип: {$requestTypeLabel}"];
if ($productName) $summaryParts[] = "Категория товаров: {$productName}";
if ($serviceCategoryName) $summaryParts[] = "Категория услуг: {$serviceCategoryName}";
if ($serviceName) $summaryParts[] = "Услуга: {$serviceName}";
$requestSummary = implode(' | ', $summaryParts);
$dbMessage = $message !== '' ? $message : 'Без комментария';

$tg_token = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$tg_recipients = array_values(array_filter(array_map('trim', explode(',', getenv('TELEGRAM_CHAT_IDS') ?: ''))));

$mysqlError = null;

$text = "🔔 Новая заявка с lsopt.ru\n\n"
      . "👤 Имя: {$name}\n"
      . "📞 Телефон: {$phone}\n"
      . ($email ? "📧 Email: {$email}\n" : '')
      . "🧾 Тип обращения: {$requestTypeLabel}\n"
      . ($productName ? "📦 Категория товаров: {$productName}\n" : '')
      . ($serviceCategoryName ? "🛠 Категория услуг: {$serviceCategoryName}\n" : '')
      . ($serviceName ? "🔧 Услуга: {$serviceName}\n" : '')
      . ($message ? "💬 Сообщение: {$message}\n" : '')
      . ($mysqlError ? "⚠️ База заявок: ошибка записи\n" : '')
      . "🕐 Время: " . date('d.m.Y H:i');

foreach ($tg_recipients as $chat_id) {
    $data = http_build_query(['chat_id' => $chat_id, 'text' => html_entity_decode($text, ENT_QUOTES, 'UTF-8')]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $data, 'timeout' => 10]]);
    @file_get_contents("https://api.telegram.org/bot{$tg_token}/sendMessage", false, $ctx);
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: '';
    $mail->SMTPAuth   = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) (getenv('SMTP_PORT') ?: 465);
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(getenv('SMTP_FROM_EMAIL') ?: '', getenv('SMTP_FROM_NAME') ?: 'Lenspecopt Website');
    $mail->addAddress(getenv('CONTACT_RECIPIENT_EMAIL') ?: '', getenv('CONTACT_RECIPIENT_NAME') ?: 'Website Requests');
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) $mail->addReplyTo($email, html_entity_decode($name, ENT_QUOTES, 'UTF-8'));
    $mail->isHTML(true);
    $mail->Subject = '🔔 Новая заявка с сайта lsopt.ru';
    $mail->Body = "<div style='font-family:Arial,sans-serif;max-width:620px;margin:0 auto;padding:20px'>
        <h2 style='color:#2563eb;border-bottom:2px solid #e2e8f0;padding-bottom:12px;margin-top:0'>📩 Новая заявка с lsopt.ru</h2>
        <table style='border-collapse:collapse;width:100%'>
            <tr style='background:#f8fafc'><td style='padding:10px 14px;font-weight:700;width:180px;color:#475569'>Имя</td><td style='padding:10px 14px'>{$name}</td></tr>
            <tr><td style='padding:10px 14px;font-weight:700;color:#475569'>Телефон</td><td style='padding:10px 14px'><a href='tel:{$phone}' style='color:#2563eb'>{$phone}</a></td></tr>
            <tr style='background:#f8fafc'><td style='padding:10px 14px;font-weight:700;color:#475569'>Тип обращения</td><td style='padding:10px 14px'>{$requestTypeLabel}</td></tr>"
            . ($email ? "<tr><td style='padding:10px 14px;font-weight:700;color:#475569'>Email</td><td style='padding:10px 14px'>{$email}</td></tr>" : '')
            . ($productName ? "<tr style='background:#f8fafc'><td style='padding:10px 14px;font-weight:700;color:#475569'>Категория товаров</td><td style='padding:10px 14px'>{$productName}</td></tr>" : '')
            . ($serviceCategoryName ? "<tr><td style='padding:10px 14px;font-weight:700;color:#475569'>Категория услуг</td><td style='padding:10px 14px'>{$serviceCategoryName}</td></tr>" : '')
            . ($serviceName ? "<tr style='background:#f8fafc'><td style='padding:10px 14px;font-weight:700;color:#475569'>Услуга</td><td style='padding:10px 14px'>{$serviceName}</td></tr>" : '')
            . ($message ? "<tr><td style='padding:10px 14px;font-weight:700;color:#475569'>Сообщение</td><td style='padding:10px 14px'>{$message}</td></tr>" : '')
            . ($mysqlError ? "<tr style='background:#fff7ed'><td style='padding:10px 14px;font-weight:700;color:#9a3412'>База заявок</td><td style='padding:10px 14px;color:#9a3412'>Не удалось записать в MySQL. Проверь кодировку таблицы requests.</td></tr>" : '')
            . "</table>
        <p style='color:#94a3b8;font-size:12px;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:12px'>Дата: " . date('d.m.Y H:i:s') . " &nbsp;|&nbsp; IP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "</p>
    </div>";
    $mail->AltBody = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $mail->send();
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/mail_errors.log', '[' . date('d.m.Y H:i:s') . '] ' . $mail->ErrorInfo . ' | ' . $name . ' | ' . $phone . PHP_EOL, FILE_APPEND);
}

echo json_encode(['success' => true, 'db_warning' => $mysqlError ? true : false]);