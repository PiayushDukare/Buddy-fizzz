<?php
require __DIR__ . '/../config.php';
require_role('admin');
require_method('GET');

$sql = "SELECT u.id, u.name, u.email, u.phone, u.status,
               s.name AS store_name, s.district, s.state
        FROM users u
        LEFT JOIN stores s ON s.user_id = u.id
        WHERE u.role = 'partner'
        ORDER BY (u.status = 'active') DESC, u.name ASC";

$partners = db()->query($sql)->fetchAll();

$terr = db()->query('SELECT partner_user_id, level, value FROM partner_territories ORDER BY level, value')->fetchAll();
$byPartner = [];
foreach ($terr as $t) {
    $byPartner[(int) $t['partner_user_id']][] = ['level' => $t['level'], 'value' => $t['value']];
}
foreach ($partners as &$p) {
    $p['territories'] = $byPartner[(int) $p['id']] ?? [];
}
unset($p);

json_out(['ok' => true, 'partners' => $partners]);
