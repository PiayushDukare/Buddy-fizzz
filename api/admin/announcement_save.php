<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('POST');
verify_csrf();

$data = body();
$id = (int) field($data, 'id', 0);
$title = (string) field($data, 'title', '');
$bodyText = (string) field($data, 'body', '');
$audience = field($data, 'audience', 'all');
$audience = in_array($audience, ['all', 'retailer', 'partner'], true) ? $audience : 'all';
$active = (int) !!field($data, 'is_active', 1);
$starts = field($data, 'starts_at') ?: null;
$ends = field($data, 'ends_at') ?: null;

if ($title === '') {
    json_error('Title is required', 422);
}

if ($id > 0) {
    db()->prepare('UPDATE announcements SET title=?, body=?, audience=?, is_active=?, starts_at=?, ends_at=? WHERE id=?')
        ->execute([$title, $bodyText ?: null, $audience, $active, $starts, $ends, $id]);
    log_activity('update_announcement', 'announcement', $id);
    json_out(['ok' => true, 'message' => 'Announcement updated']);
}

db()->prepare('INSERT INTO announcements (title, body, audience, is_active, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$title, $bodyText ?: null, $audience, $active, $starts, $ends]);
$newId = (int) db()->lastInsertId();
log_activity('create_announcement', 'announcement', $newId);

json_out(['ok' => true, 'message' => 'Announcement posted', 'id' => $newId], 201);
