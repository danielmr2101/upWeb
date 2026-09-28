<?php
declare(strict_types=1);

$remote = $argv[1] ?? '127.0.0.1';
$provided = $argv[2] ?? '';
$configToken = $argv[3] ?? '';
$allowRemote = $argv[4] ?? '0';

$_SERVER['REMOTE_ADDR'] = $remote;
$_REQUEST['token'] = $provided;

putenv('UPWEB_AUTH_TOKEN=' . $configToken);
putenv('UPWEB_ALLOW_REMOTE=' . $allowRemote);
putenv('UPWEB_WWW_ROOT=' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib.php';

echo authError() ?? 'OK';
