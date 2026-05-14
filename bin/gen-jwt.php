#!/usr/bin/env php
<?php
/**
 * bin/gen-jwt.php
 *
 * Generates the RSA keypair used by LexikJWTAuthenticationBundle without
 * depending on the `openssl` CLI (useful on Windows / WampServer hosts
 * where OpenSSL is not available on PATH).
 *
 * Usage:
 *     php bin/gen-jwt.php
 *
 * The passphrase is read from the JWT_PASSPHRASE environment variable
 * and falls back to the value configured in config/packages/lexik_jwt_authentication.yaml.
 */

$projectRoot = dirname(__DIR__);
$passphrase  = getenv('JWT_PASSPHRASE') ?: 'ChangeMe_JWT_2026';
$targetDir   = $projectRoot . '/config/jwt';

$opts = [
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

$key = @openssl_pkey_new($opts);
if ($key === false) {
    // OpenSSL config may not be discoverable on some Windows setups.
    $cnf = tempnam(sys_get_temp_dir(), 'ossl_');
    file_put_contents($cnf, "[req]\ndistinguished_name=req\n");
    putenv('OPENSSL_CONF=' . $cnf);
    $opts['config'] = $cnf;
    $key = openssl_pkey_new($opts);
}

if ($key === false) {
    while ($msg = openssl_error_string()) {
        fwrite(STDERR, $msg . PHP_EOL);
    }
    exit(1);
}

openssl_pkey_export($key, $privatePem, $passphrase);
$publicPem = openssl_pkey_get_details($key)['key'];

if (!is_dir($targetDir) && !mkdir($targetDir, 0770, true) && !is_dir($targetDir)) {
    fwrite(STDERR, sprintf('Unable to create %s%s', $targetDir, PHP_EOL));
    exit(1);
}

file_put_contents($targetDir . '/private.pem', $privatePem);
file_put_contents($targetDir . '/public.pem',  $publicPem);
@chmod($targetDir . '/private.pem', 0600);
@chmod($targetDir . '/public.pem',  0644);

fwrite(STDOUT, "JWT keypair generated in config/jwt/ (private.pem, public.pem).\n");
