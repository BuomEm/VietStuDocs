<?php
// Generate VAPID keys using OpenSSL extension
$config = array(
    "curve_name" => "prime256v1",
    "private_key_type" => OPENSSL_KEYTYPE_EC,
);

// Point to openssl.cnf if on Windows
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $openssl_cnf = 'D:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\extras\ssl\openssl.cnf';
    if (file_exists($openssl_cnf)) {
        $config['config'] = $openssl_cnf;
    }
}

$res = openssl_pkey_new($config);
if (!$res) {
    echo "Error generating keys: " . openssl_error_string();
    exit;
}

openssl_pkey_export($res, $privKey, null, $config);
$details = openssl_pkey_get_details($res);
$pubKey = $details['ec']['public_key'];

// Extract the raw public key (65 bytes)
// The public key from openssl is in PEM/DER format. We need the raw point.
// Minishlink handles the conversion if we give it PEM, but let's try to get raw.

// Actually, Minishlink's VAPID::createVapidKeys() does all this.
// The reason it failed was just the config.

function base64url_encode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

// Extract private key (d)
$key_details = openssl_pkey_get_details($res);
$priv_raw = $key_details['ec']['d'];
$pub_x = $key_details['ec']['x'];
$pub_y = $key_details['ec']['y'];

$publicKey = "\x04" . $pub_x . $pub_y;
$privateKey = $priv_raw;

echo "Public Key: " . base64url_encode($publicKey) . "\n";
echo "Private Key: " . base64url_encode($privateKey) . "\n";
?>
