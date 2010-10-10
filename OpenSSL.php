<?php

// Playing with OpenSSL!

$purposes = array('X509_PURPOSE_SSL_CLIENT',
                  'X509_PURPOSE_SSL_SERVER',
                  'X509_PURPOSE_NS_SSL_SERVER',
                  'X509_PURPOSE_SMIME_SIGN',
                  'X509_PURPOSE_SMIME_ENCRYPT',
                  'X509_PURPOSE_CRL_SIGN',
                  'X509_PURPOSE_ANY');


$pemFile = '/usr/share/pyshared/twisted/test/server.pem';
//$pemFile = '/etc/ssl/certs/ssl-cert-snakeoil.pem';
$cert = file_get_contents($pemFile);
$public = openssl_get_publickey($cert);
$private = openssl_get_privatekey($cert);

$data = "I'm a lumberjack and I'm okay.";

echo "Data before: {$data}\n";
openssl_seal($data, $cipher, $e, array($public));

echo "Ciphertext: {$cipher}\n";

openssl_open($cipher, $open, $e[0], $private);
echo "Decrypted: {$open}\n";

showCertPurposes($cert);

var_dump(openssl_get_cipher_methods());
var_dump(openssl_get_md_methods());

function showCertPurposes($cert) {
    global $purposes;

    foreach ($purposes as $purp) {
        $result = openssl_x509_checkpurpose($cert, constant($purp), array('/etc/ssl/certs/'));
        if ($result === -1) {
            printf("Purpose %s - Error!\n", $purp);
        } else if ($result) {
            printf("Purpose %s - YES!\n", $purp);
        } else {
            printf("Purpose %s\tNO (%d)\n", $purp, $result);
        }
    }
}

// Find me, I'm eeezay