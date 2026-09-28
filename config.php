<?php
declare(strict_types=1);

$auto = [
    'www_root' => dirname(__DIR__),
    'laragon_exe' => dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'laragon.exe',
    'host_suffix' => '.test',
    'default_host' => '127.0.0.1',
    'php_bin' => null,
    'npm_bin' => null,
    'node_bin' => null,
    'composer_phar' => null,
    'flutter_root' => getenv('FLUTTER_ROOT') ?: null,
    'python_bin' => null,
    'allow_remote' => false,
];

$local = [];
$localFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

return array_merge($auto, $local);
