<?php


function razorpay_api(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.razorpay.com/v1/' . ltrim($path, '/'));
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_TIMEOUT => 20,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $res === false ? null : json_decode($res, true), $err];
}

function razorpay_verify_signature(string $rzpOrderId, string $rzpPaymentId, string $signature): bool
{
    $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, RAZORPAY_KEY_SECRET);
    return hash_equals($expected, $signature);
}


function razorpay_refund(string $paymentId, ?int $amountPaise = null): bool
{
    $body = ['speed' => 'optimum'];
    if ($amountPaise !== null) {
        $body['amount'] = $amountPaise;
    }
    [$code, $data, $err] = razorpay_api('POST', 'payments/' . $paymentId . '/refund', $body);
    if ($code >= 300 || empty($data['id'])) {
        log_error('Razorpay refund failed (' . $code . '): ' . ($err ?: json_encode($data)));
        return false;
    }
    return true;
}
