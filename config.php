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
    'auth_token' => null,
];

$local = [];
$localFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

$config = array_merge($auto, $local);

$envMap = [
    'UPWEB_WWW_ROOT' => 'www_root',
    'UPWEB_LARAGON_EXE' => 'laragon_exe',
    'UPWEB_HOST_SUFFIX' => 'host_suffix',
    'UPWEB_PHP_BIN' => 'php_bin',
    'UPWEB_NPM_BIN' => 'npm_bin',
    'UPWEB_NODE_BIN' => 'node_bin',
    'UPWEB_COMPOSER_PHAR' => 'composer_phar',
    'UPWEB_PYTHON_BIN' => 'python_bin',
    'UPWEB_FLUTTER_ROOT' => 'flutter_root',
    'UPWEB_HOSTS_FILE' => 'hosts_file',
    'UPWEB_AUTH_TOKEN' => 'auth_token',
    'UPWEB_ALLOW_REMOTE' => 'allow_remote',
];

foreach ($envMap as $env => $key) {
    $value = getenv($env);
    if ($value === false || $value === '') {
        continue;
    }
    if ($key === 'allow_remote') {
        $config[$key] = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    } else {
        $config[$key] = $value;
    }
}

return $config;
