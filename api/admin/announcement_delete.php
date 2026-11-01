<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$id = (int) field(body(), 'id', 0);
if ($id <= 0) {
    json_error('Invalid request', 422);
}

db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
log_activity('delete_announcement', 'announcement', $id);

json_out(['ok' => true, 'message' => 'Announcement deleted']);
