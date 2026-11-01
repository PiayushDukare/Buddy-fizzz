<?php
require __DIR__ . '/../config.php';
$admin = require_role('admin');
require_method('POST');
verify_csrf();

$audience = (string) ($_POST['audience'] ?? '');
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$storeId = (int) ($_POST['store_id'] ?? 0);
$userId = (int) ($_POST['user_id'] ?? 0);
$customEmails = (string) ($_POST['custom_emails'] ?? '');

if ($subject === '' || $message === '') {
    json_error('Subject and message are required', 422);
}
if (mb_strlen($subject) > 200) {
    json_error('Subject is too long', 422);
}

$recipients = [];
$single = null;

switch ($audience) {
    case 'retailers':
        $recipients = db()->query("SELECT email, name FROM users WHERE role = 'retailer' AND status = 'active' AND email <> ''")->fetchAll();
        break;
    case 'partners':
        $recipients = db()->query("SELECT email, name FROM users WHERE role = 'partner' AND status = 'active' AND email <> ''")->fetchAll();
        break;
    case 'all':
        $recipients = db()->query("SELECT email, name FROM users WHERE role IN ('retailer','partner') AND status = 'active' AND email <> ''")->fetchAll();
        break;
    case 'subscribers':
        $recipients = db()->query('SELECT email, NULL AS name FROM subscribers')->fetchAll();
        break;
    case 'store':
        $st = db()->prepare('SELECT u.email, u.name FROM stores s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1');
        $st->execute([$storeId]);
        $single = $st->fetch();
        if (!$single || !$single['email']) {
            json_error('That store has no email on file', 422);
        }
        break;
    case 'user':
        $st = db()->prepare("SELECT email, name FROM users WHERE id = ? AND role IN ('retailer','partner') AND status = 'active' LIMIT 1");
        $st->execute([$userId]);
        $single = $st->fetch();
        if (!$single || !$single['email']) {
            json_error('That person has no email on file', 422);
        }
        break;
    case 'custom':
        $parts = preg_split('/[\s,;]+/', $customEmails, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts as $p) {
            if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = ['email' => $p, 'name' => ''];
            }
        }
        if (!$recipients) {
            json_error('Enter at least one valid email address', 422);
        }
        break;
    default:
        json_error('Choose who to send to', 422);
}

$attachments = collect_email_attachments();

$bodyHtml = mail_template(
    $subject,
    '<div>' . nl2br(htmlspecialchars($message, ENT_QUOTES)) . '</div>'
);

if ($single) {
    $ok = send_mail(
        [[$single['email'], (string) $single['name']]],
        $subject,
        $bodyHtml,
        ['attachments' => $attachments, 'context' => 'admin_compose', 'sent_by' => $admin['id']]
    );
    log_activity('email_compose', 'store', $storeId, ['audience' => 'store', 'sent' => $ok ? 1 : 0]);
    json_out(['ok' => true, 'message' => $ok ? 'Email sent.' : 'Email could not be sent (check SMTP settings).', 'recipients' => 1]);
}

$emails = [];
foreach ($recipients as $r) {
    if (filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
        $emails[strtolower($r['email'])] = [$r['email'], (string) ($r['name'] ?? '')];
    }
}
$emails = array_values($emails);
if (!$emails) {
    json_error('No recipients with a valid email were found', 422);
}
if (count($emails) > 300) {
    json_error('Too many recipients for one send (max 300). Narrow the audience.', 422);
}

$ok = send_mail(
    [[SMTP_FROM, (string) get_setting('company_name', 'Buddy Fizz')]],
    $subject,
    $bodyHtml,
    ['bcc' => $emails, 'attachments' => $attachments, 'context' => 'admin_broadcast', 'sent_by' => $admin['id']]
);

log_activity('email_compose', null, null, ['audience' => $audience, 'recipients' => count($emails), 'sent' => $ok ? 1 : 0]);
json_out([
    'ok' => true,
    'message' => $ok ? ('Email sent to ' . count($emails) . ' recipient(s).') : 'Email could not be sent (check SMTP settings).',
    'recipients' => count($emails),
]);
