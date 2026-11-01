<?php
require __DIR__ . '/../config.php';
$user = require_role('partner');
require_method('POST');
verify_csrf();

$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$storeId = (int) ($_POST['store_id'] ?? 0);

if ($subject === '' || $message === '') {
    json_error('Subject and message are required', 422);
}
if (mb_strlen($subject) > 200) {
    json_error('Subject is too long', 422);
}

if ($storeId > 0) {
    $st = db()->prepare('SELECT u.email, u.name FROM stores s JOIN users u ON u.id = s.user_id WHERE s.id = ? AND s.partner_id = ? LIMIT 1');
    $st->execute([$storeId, (int) $user['id']]);
    $single = $st->fetch();
    if (!$single || !$single['email']) {
        json_error('That retailer is not yours or has no email', 422);
    }
    $recipients = [$single];
} else {
    $st = db()->prepare("SELECT u.email, u.name FROM stores s JOIN users u ON u.id = s.user_id WHERE s.partner_id = ? AND u.status = 'active' AND u.email <> ''");
    $st->execute([(int) $user['id']]);
    $recipients = $st->fetchAll();
}

$emails = [];
foreach ($recipients as $r) {
    if (filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
        $emails[strtolower($r['email'])] = [$r['email'], (string) ($r['name'] ?? '')];
    }
}
$emails = array_values($emails);
if (!$emails) {
    json_error('No retailers with a valid email were found', 422);
}

$attachments = collect_email_attachments();
$bodyHtml = mail_template($subject, '<div>' . nl2br(htmlspecialchars($message, ENT_QUOTES)) . '</div>');

if (count($emails) === 1) {
    $ok = send_mail($emails, $subject, $bodyHtml, ['attachments' => $attachments, 'context' => 'partner_compose', 'sent_by' => $user['id']]);
} else {
    $ok = send_mail(
        [[SMTP_FROM, (string) get_setting('company_name', 'Buddy Fizz')]],
        $subject,
        $bodyHtml,
        ['bcc' => $emails, 'attachments' => $attachments, 'context' => 'partner_compose', 'sent_by' => $user['id'], 'reply_to' => [[$user['email'], $user['name']]]]
    );
}

log_activity('email_compose', null, null, ['scope' => 'partner_retailers', 'recipients' => count($emails), 'sent' => $ok ? 1 : 0]);
json_out([
    'ok' => true,
    'message' => $ok ? ('Email sent to ' . count($emails) . ' retailer(s).') : 'Email could not be sent (check SMTP settings).',
    'recipients' => count($emails),
]);
