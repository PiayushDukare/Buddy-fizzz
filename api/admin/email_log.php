<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$status = (string) ($_GET['status'] ?? 'all');
$limit = max(20, min((int) ($_GET['limit'] ?? 100), 500));

$sql = 'SELECT e.id, e.to_email, e.subject, e.context, e.status, e.error, e.created_at, u.name AS sender
        FROM email_log e LEFT JOIN users u ON u.id = e.sent_by';
$params = [];
if (in_array($status, ['sent', 'failed', 'skipped'], true)) {
    $sql .= ' WHERE e.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY e.id DESC LIMIT ' . $limit;

$stmt = db()->prepare($sql);
$stmt->execute($params);

$counts = db()->query(
    "SELECT
       COUNT(*) AS total,
       SUM(status = 'sent') AS sent,
       SUM(status = 'failed') AS failed,
       SUM(status = 'skipped') AS skipped
     FROM email_log"
)->fetch();

json_out([
    'ok' => true,
    'logs' => $stmt->fetchAll(),
    'counts' => [
        'total' => (int) ($counts['total'] ?? 0),
        'sent' => (int) ($counts['sent'] ?? 0),
        'failed' => (int) ($counts['failed'] ?? 0),
        'skipped' => (int) ($counts['skipped'] ?? 0),
    ],
]);
