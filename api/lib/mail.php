<?php

require_once __DIR__ . '/Mailer.php';

function mail_log(string $to, string $subject, string $status, ?string $error = null, ?string $context = null, ?int $sentBy = null): void
{
    try {
        db()->prepare('INSERT INTO email_log (to_email, subject, context, status, error, sent_by) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([mb_substr($to, 0, 190), mb_substr($subject, 0, 255), $context, $status, $error !== null ? mb_substr($error, 0, 255) : null, $sentBy]);
    } catch (Throwable $e) {
        
    }
}

function mail_norm($r): array
{
    if (is_array($r)) {
        return [(string) ($r[0] ?? $r['email'] ?? ''), (string) ($r[1] ?? $r['name'] ?? '')];
    }
    return [(string) $r, ''];
}


function send_mail($to, string $subject, string $html, array $opts = []): bool
{
    $context = $opts['context'] ?? null;
    $sentBy = $opts['sent_by'] ?? ($_SESSION['user_id'] ?? null);
   
    $toList = is_string($to) ? [$to] : $to;

    $first = '';
    if (!empty($toList)) {
        [$first] = mail_norm($toList[0]);
    }

    if (!MAIL_ENABLED || SMTP_PASS === '') {
        mail_log($first, $subject, 'skipped', 'MAIL_ENABLED off', $context, $sentBy);
        return false;
    }

    try {
        $m = new Mailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE);
        $m->verifyPeer = MAIL_VERIFY_PEER;
        $m->setFrom(SMTP_FROM, SMTP_FROM_NAME);

        foreach ($toList as $r) {
            [$e, $n] = mail_norm($r);
            $m->addTo($e, $n);
        }
        foreach ($opts['cc'] ?? [] as $r) {
            [$e, $n] = mail_norm($r);
            $m->addCc($e, $n);
        }
        foreach ($opts['bcc'] ?? [] as $r) {
            [$e, $n] = mail_norm($r);
            $m->addBcc($e, $n);
        }
        if (empty($opts['no_business_bcc'])) {
            $biz = MAIL_BCC !== '' ? MAIL_BCC : (string) get_setting('company_email', '');
            if ($biz !== '' && $biz !== $first) {
                $m->addBcc($biz);
            }
        }
        foreach ($opts['reply_to'] ?? [] as $r) {
            [$e, $n] = mail_norm($r);
            $m->addReplyTo($e, $n);
        }
        foreach ($opts['attachments'] ?? [] as $a) {
            if (!empty($a['path'])) {
                $m->attach($a['path'], $a['name'] ?? '', $a['mime'] ?? 'application/octet-stream');
            } elseif (isset($a['data'])) {
                $m->attachData($a['data'], $a['name'] ?? 'file', $a['mime'] ?? 'application/octet-stream');
            }
        }
        foreach ($opts['inline'] ?? [] as $img) {
            if (isset($img['data'], $img['cid'])) {
                $m->attachInline($img['data'], $img['cid'], $img['mime'] ?? 'image/png');
            }
        }

        $m->send($subject, $html);
        mail_log($first, $subject, 'sent', null, $context, $sentBy);
        return true;
    } catch (Throwable $e) {
        mail_log($first, $subject, 'failed', $e->getMessage(), $context, $sentBy);
        return false;
    }
}


function mail_template(string $heading, string $bodyHtml): string
{
    $company = htmlspecialchars((string) get_setting('company_name', 'Buddy Fizz'), ENT_QUOTES);
    $address = htmlspecialchars((string) get_setting('company_address', ''), ENT_QUOTES);
    $phone = htmlspecialchars((string) get_setting('company_phone', ''), ENT_QUOTES);
    $email = htmlspecialchars((string) get_setting('company_email', ''), ENT_QUOTES);
    $year = date('Y');
    $head = htmlspecialchars($heading, ENT_QUOTES);

    return '<!doctype html><html><body style="margin:0;padding:0;background:#eef3e7;font-family:Arial,Helvetica,sans-serif;color:#233019">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3e7;padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(20,58,36,.12)">
<tr><td style="background:#143a24;padding:22px 28px">
<span style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:.5px">' . $company . '</span>
</td></tr>
<tr><td style="padding:28px">
<h1 style="margin:0 0 14px;font-size:20px;color:#143a24">' . $head . '</h1>
<div style="font-size:15px;line-height:1.6;color:#3a4a34">' . $bodyHtml . '</div>
</td></tr>
<tr><td style="padding:18px 28px;background:#f4f8ee;border-top:1px solid #e2ebd8;font-size:12px;color:#7c8a72">
<div style="font-weight:700;color:#143a24;margin-bottom:4px">' . $company . '</div>
' . ($address ? $address . '<br>' : '') . '
' . ($phone ? 'Phone: ' . $phone . ' &nbsp; ' : '') . ($email ? 'Email: ' . $email : '') . '
<div style="margin-top:10px;color:#9aa891">© ' . $year . ' ' . $company . '. This is an automated message.</div>
</td></tr>
</table></td></tr></table></body></html>';
}

function money_txt($n): string
{
    return '₹' . number_format((float) $n, 2);
}


function abs_url(string $path = ''): string
{
    $override = (string) get_setting('site_url', '');
    if ($override !== '') {
        return rtrim($override, '/') . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $scheme . '://' . $host . url($path);
}


function mail_button(string $label, string $href): string
{
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" style="display:inline-block;background:#2e7d32;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:700;font-size:14px">' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
}


function collect_email_attachments(string $field = 'attachments'): array
{
    $out = [];
    if (empty($_FILES[$field])) {
        return $out;
    }
    $f = $_FILES[$field];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    $names = (array) $f['name'];
    $tmp = (array) $f['tmp_name'];
    $err = (array) $f['error'];
    $size = (array) $f['size'];
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
        if (($err[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        if (($size[$i] ?? 0) > 5 * 1024 * 1024) {
            continue;
        }
        $ext = strtolower(pathinfo((string) $names[$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true) || !is_uploaded_file($tmp[$i])) {
            continue;
        }
        $data = @file_get_contents($tmp[$i]);
        if ($data === false) {
            continue;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($tmp[$i]) ?: 'application/octet-stream') : 'application/octet-stream';
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $names[$i]));
        $out[] = ['data' => $data, 'name' => $safe, 'mime' => $mime];
        if (count($out) >= 6) {
            break;
        }
    }
    return $out;
}


function pct_txt($n): string
{
    return rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
}

function mail_inline_image(string $relPath, array &$inline): ?string
{
    $rel = ltrim(str_replace('\\', '/', $relPath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return null;
    }
    $cid = 'img' . substr(md5($rel), 0, 16) . '@buddyfizz';
    foreach ($inline as $img) {
        if (($img['cid'] ?? '') === $cid) {
            return $cid;
        }
    }
    $full = dirname(__DIR__, 2) . '/' . $rel;
    if (!is_file($full) || !is_readable($full)) {
        return null;
    }
    $data = @file_get_contents($full);
    if ($data === false) {
        return null;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $inline[] = ['data' => $data, 'cid' => $cid, 'mime' => $mimes[$ext] ?? 'application/octet-stream'];
    return $cid;
}

function order_summary_html(int $orderId, ?array &$inline = null): string
{
    $o = db()->prepare(
        'SELECT o.*, s.name AS store_name, s.owner_name, s.gstin AS store_gstin,
                s.address, s.village, s.taluka, s.district, s.state, s.pincode
         FROM orders o JOIN stores s ON s.id = o.buyer_store_id WHERE o.id = ? LIMIT 1'
    );
    $o->execute([$orderId]);
    $order = $o->fetch();
    if (!$order) {
        return '';
    }
    $items = db()->prepare('SELECT oi.qty, oi.unit_price, oi.line_total, p.name, p.sku, p.size, p.image FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
    $items->execute([$orderId]);
    $rows = $items->fetchAll();

    $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
    $companyState = (string) get_setting('company_state', '');
    $gst = (float) $order['gst_amount'];
    $cess = (float) $order['cess_amount'];
    $intra = $companyState !== '' && strcasecmp(trim((string) $order['state']), trim($companyState)) === 0;
    $ref = $order['invoice_no'] ?: (get_setting('invoice_prefix', 'BF') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT));
    $balance = (float) $order['total'] - (float) $order['amount_paid'];
    $ps = (string) $order['payment_status'];
    $payBg = $ps === 'paid' ? '#dff3df' : ($ps === 'partial' ? '#fff3d6' : '#fbe0e0');
    $payFg = $ps === 'paid' ? '#1f7a34' : ($ps === 'partial' ? '#a86800' : '#b02a2a');
    $billLoc = implode(', ', array_filter([$order['address'], $order['village'], $order['taluka'], $order['district'], $order['state'], $order['pincode']]));

    $collectInline = is_array($inline);
    $itemRows = '';
    foreach ($rows as $it) {
        $img = '';
        if (!empty($it['image'])) {
            $src = $e(abs_url($it['image']));
            if ($collectInline) {
                $cid = mail_inline_image((string) $it['image'], $inline);
                if ($cid !== null) {
                    $src = 'cid:' . $cid;
                }
            }
            $img = '<img src="' . $src . '" width="40" height="48" alt="' . $e($it['name']) . '" style="width:40px;height:48px;object-fit:contain;background:#f7faf2;border-radius:4px;vertical-align:middle;margin-right:10px">';
        }
        $sub = trim(($it['sku'] ? $it['sku'] : '') . ($it['size'] ? ' · ' . $it['size'] : ''), ' ·');
        $itemRows .= '<tr>'
            . '<td style="padding:9px 8px;border-bottom:1px solid #eef2ea;font-size:14px">'
            . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td>' . $img . '</td>'
            . '<td style="font-size:14px"><b style="color:#143a24">' . $e($it['name']) . '</b>' . ($sub !== '' ? '<br><span style="color:#7c8a72;font-size:12px">' . $e($sub) . '</span>' : '') . '</td></tr></table>'
            . '</td>'
            . '<td align="center" style="padding:9px 8px;border-bottom:1px solid #eef2ea;font-size:14px">' . (int) $it['qty'] . '</td>'
            . '<td align="right" style="padding:9px 8px;border-bottom:1px solid #eef2ea;font-size:14px;color:#7c8a72">' . money_txt($it['unit_price']) . '</td>'
            . '<td align="right" style="padding:9px 8px;border-bottom:1px solid #eef2ea;font-size:14px;font-weight:600">' . money_txt($it['line_total']) . '</td>'
            . '</tr>';
    }

    $taxRows = '';
    if ($gst > 0) {
        if ($intra) {
            $taxRows .= '<tr><td style="padding:3px 0;color:#7c8a72;font-size:13px">CGST @ ' . pct_txt($order['gst_rate'] / 2) . '%</td><td align="right" style="padding:3px 0;color:#7c8a72;font-size:13px">' . money_txt($gst / 2) . '</td></tr>';
            $taxRows .= '<tr><td style="padding:3px 0;color:#7c8a72;font-size:13px">SGST @ ' . pct_txt($order['gst_rate'] / 2) . '%</td><td align="right" style="padding:3px 0;color:#7c8a72;font-size:13px">' . money_txt($gst / 2) . '</td></tr>';
        } else {
            $taxRows .= '<tr><td style="padding:3px 0;color:#7c8a72;font-size:13px">IGST @ ' . pct_txt($order['gst_rate']) . '%</td><td align="right" style="padding:3px 0;color:#7c8a72;font-size:13px">' . money_txt($gst) . '</td></tr>';
        }
    }
    if ($cess > 0) {
        $taxRows .= '<tr><td style="padding:3px 0;color:#7c8a72;font-size:13px">Cess @ ' . pct_txt($order['cess_rate']) . '%</td><td align="right" style="padding:3px 0;color:#7c8a72;font-size:13px">' . money_txt($cess) . '</td></tr>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2ebd8;border-radius:12px;overflow:hidden;margin:14px 0">'
        . '<tr><td style="padding:14px 16px;background:#143a24;color:#fff">'
        . '<table role="presentation" width="100%"><tr>'
        . '<td style="font-family:Arial,sans-serif;font-size:16px;font-weight:bold;letter-spacing:1px;color:#ffffff">INVOICE</td>'
        . '<td align="right" style="font-size:13px;color:#cfe6d0">' . $e($ref) . '<br>' . date('d M Y', strtotime((string) $order['created_at'])) . '</td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:14px 16px">'
        . '<table role="presentation" width="100%"><tr>'
        . '<td valign="top" style="font-size:13px;color:#4a5a4e;line-height:1.5">'
        . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9aa891;margin-bottom:3px">Billed To</div>'
        . '<b style="color:#143a24;font-size:14px">' . $e($order['store_name']) . '</b><br>'
        . ($order['owner_name'] ? $e($order['owner_name']) . '<br>' : '') . $e($billLoc)
        . (!empty($order['store_gstin']) ? '<br>GSTIN: ' . $e($order['store_gstin']) : '')
        . '</td>'
        . '<td valign="top" align="right"><span style="display:inline-block;background:' . $payBg . ';color:' . $payFg . ';font-size:12px;font-weight:bold;text-transform:uppercase;padding:4px 12px;border-radius:999px">' . $e($ps) . '</span></td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:0 16px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . '<tr style="background:#f2f6ec"><td style="padding:8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7a6e">Product</td><td align="center" style="padding:8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7a6e">Qty</td><td align="right" style="padding:8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7a6e">Rate</td><td align="right" style="padding:8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7a6e">Amount</td></tr>'
        . $itemRows
        . '</table></td></tr>'
        . '<tr><td style="padding:12px 16px 16px">'
        . '<table role="presentation" align="right" width="260" cellpadding="0" cellspacing="0" style="font-size:14px">'
        . '<tr><td style="padding:3px 0;color:#4a5a4e">Subtotal</td><td align="right" style="padding:3px 0">' . money_txt($order['subtotal']) . '</td></tr>'
        . ((float) ($order['discount_amount'] ?? 0) > 0 ? '<tr><td style="padding:3px 0;color:#1f7a34">Discount' . (!empty($order['coupon_code']) ? ' (' . $e($order['coupon_code']) . ')' : '') . '</td><td align="right" style="padding:3px 0;color:#1f7a34">− ' . money_txt($order['discount_amount']) . '</td></tr>' : '')
        . $taxRows
        . '<tr><td style="padding:8px 0 0;border-top:2px solid #143a24;font-weight:bold;color:#143a24;font-size:16px">Total</td><td align="right" style="padding:8px 0 0;border-top:2px solid #143a24;font-weight:bold;color:#143a24;font-size:16px">' . money_txt($order['total']) . '</td></tr>'
        . '<tr><td style="padding:6px 0 0;color:#7c8a72;font-size:12px">Paid</td><td align="right" style="padding:6px 0 0;color:#7c8a72;font-size:12px">' . money_txt($order['amount_paid']) . '</td></tr>'
        . ($balance > 0.001 ? '<tr><td style="padding:2px 0;color:#b02a2a;font-size:13px;font-weight:bold">Balance Due</td><td align="right" style="padding:2px 0;color:#b02a2a;font-size:13px;font-weight:bold">' . money_txt($balance) . '</td></tr>' : '')
        . '</table></td></tr>'
        . '</table>';
}
