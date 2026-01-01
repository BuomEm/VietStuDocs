<?php
require_once __DIR__ . '/vendor/autoload.php';
putenv('OPENSSL_CONF=D:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\extras\ssl\openssl.cnf');
use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
echo "Public Key: " . $keys['publicKey'] . "\n";
echo "Private Key: " . $keys['privateKey'] . "\n";
?>
