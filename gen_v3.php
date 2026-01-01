<?php
$c = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'config' => 'D:\\laragon\\bin\\php\\php-8.3.28-Win32-vs16-x64\\extras\\ssl\\openssl.cnf'
];
$r = openssl_pkey_new($c);
$d = openssl_pkey_get_details($r);
$pub = chr(4) . $d['ec']['x'] . $d['ec']['y'];
$priv = $d['ec']['d'];
function b64($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}
echo "VAPID_PUBLIC_KEY=" . b64($pub) . "\n";
echo "VAPID_PRIVATE_KEY=" . b64($priv) . "\n";
?>
