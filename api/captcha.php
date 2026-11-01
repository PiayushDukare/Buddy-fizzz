<?php
require __DIR__ . '/config.php';
require_method('GET');

json_out(['ok' => true] + new_captcha());
