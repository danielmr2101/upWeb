<?php
declare(strict_types=1);

putenv('UPWEB_WWW_ROOT=' . __DIR__ . DIRECTORY_SEPARATOR . 'fixtures');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib.php';

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
$GLOBALS['__failures'] = [];

function ok(bool $cond, string $label, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['__pass']++;
        echo "  ok   {$label}\n";
        return;
    }
    $GLOBALS['__fail']++;
    $GLOBALS['__failures'][] = $label . ($detail !== '' ? " — {$detail}" : '');
    echo "  FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function eq($expected, $actual, string $label): void
{
    ok($expected === $actual, $label, 'esperado ' . var_export($expected, true) . ', obtenido ' . var_export($actual, true));
}

function throws(callable $fn, string $label): void
{
    try {
        $fn();
        ok(false, $label, 'no lanzo excepcion');
    } catch (Throwable $e) {
        ok(true, $label, $e->getMessage());
    }
}

function tmpLog(string $content): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . uniqid('log_', true) . '.log';
    file_put_contents($file, $content);
    return $file;
}

echo "== detectJsFramework ==\n";
eq('vite', detectJsFramework(['dependencies' => ['vite' => '^5']]), 'detecta vite');
eq('vite', detectJsFramework(['devDependencies' => ['vite' => '^5']]), 'detecta vite en devDependencies');
eq('next', detectJsFramework(['dependencies' => ['next' => '^14']]), 'next tiene prioridad sobre vite');
eq('cra', detectJsFramework(['dependencies' => ['react-scripts' => '^5']]), 'detecta react-scripts');
eq('nuxt', detectJsFramework(['dependencies' => ['nuxt' => '^3']]), 'detecta nuxt');
eq('astro', detectJsFramework(['dependencies' => ['astro' => '^4']]), 'detecta astro');
eq('angular', detectJsFramework(['dependencies' => ['@angular/core' => '^17']]), 'detecta angular');
eq('node-server', detectJsFramework(['dependencies' => ['express' => '^4']]), 'detecta express');
eq('unknown', detectJsFramework(['dependencies' => ['lodash' => '^4']]), 'sin framework conocido');
eq('unknown', detectJsFramework(null), 'package.json ausente');

echo "== jsScript ==\n";
eq('dev', jsScript(['scripts' => ['dev' => 'vite', 'build' => 'vite build']]), 'prefiere dev');
eq('start', jsScript(['scripts' => ['start' => 'node server.js']]), 'usa start si no hay dev');
eq('serve', jsScript(['scripts' => ['serve' => 'serve']]), 'usa serve');
eq(null, jsScript(['scripts' => ['build' => 'vite build']]), 'null si solo hay build');
eq(null, jsScript(['name' => 'x']), 'null sin scripts');
eq(null, jsScript(null), 'null sin package.json');

echo "== npmCommand ==\n";
eq('{npm} run dev -- --host {host} --port {port}', npmCommand('vite', 'dev'), 'vite recibe host y port');
eq('{npm} run dev -- --port {port} --hostname {host}', npmCommand('next', 'dev'), 'next recibe hostname');
ok(strpos(npmCommand('cra', 'start'), 'PORT={port}') !== false, 'cra usa variable PORT');
ok(strpos(npmCommand('unknown', 'dev'), 'PORT={port}') !== false, 'desconocido usa variable PORT');
eq('{npm} run start -- --host {host} --port {port}', npmCommand('vite', 'start'), 'respeta el script indicado');

echo "== buildCommand ==\n";
$cmd = buildCommand('{php} -S {host}:{port} -t {log}', '127.0.0.1', 9000, '/tmp/x.log');
ok(strpos($cmd, '127.0.0.1:9000') !== false, 'sustituye host y port', $cmd);
ok(strpos($cmd, '/tmp/x.log') !== false, 'sustituye log', $cmd);
ok(strpos($cmd, '{') === false, 'no quedan tokens sin resolver', $cmd);

echo "== detectUrl ==\n";
eq('http://localhost:5173/', detectUrl(tmpLog("VITE ready\n  Local:   http://localhost:5173/\n")), 'vite Local');
eq('http://127.0.0.1:8000', detectUrl(tmpLog("INFO Server running on [http://127.0.0.1:8000]\n")), 'laravel artisan serve');
eq('http://localhost:8080', detectUrl(tmpLog("lib/main.dart is being served at http://localhost:8080\n")), 'flutter served at');
eq(null, detectUrl(tmpLog("no hay url aqui\n")), 'sin url devuelve null');
eq(null, detectUrl('/ruta/que/no/existe.log'), 'archivo inexistente devuelve null');

echo "== detectLogPort ==\n";
eq(8000, detectLogPort(tmpLog("listening on 127.0.0.1:8000\n")), 'extrae puerto');
eq(null, detectLogPort(tmpLog("sin puerto\n")), 'sin puerto devuelve null');

echo "== detectRunners por arquitectura ==\n";
function runnerIds(string $project): array
{
    $path = WWW_ROOT . DIRECTORY_SEPARATOR . $project;
    return array_map(static fn($r) => $r['id'], detectRunners($path));
}
eq(['laravel', 'composer-dev', 'npm'], runnerIds('laravel-app'), 'laravel detecta artisan + composer dev + npm');
eq(['flutter-release', 'flutter'], runnerIds('flutter-app'), 'flutter con carpeta web');
eq(['npm'], runnerIds('vite-app'), 'proyecto vite puro');
eq(['php'], runnerIds('static-app'), 'estatico cae a php -S');
eq(['django'], runnerIds('django-app'), 'django via manage.py');
eq(['fastapi'], runnerIds('fastapi-app'), 'fastapi via requirements');
eq(['npm'], runnerIds('node-next'), 'next detectado como framework');

echo "== detectRunners: tipos y puertos ==\n";
$laravel = detectRunners(WWW_ROOT . DIRECTORY_SEPARATOR . 'laravel-app');
$byId = [];
foreach ($laravel as $r) {
    $byId[$r['id']] = $r;
}
ok($byId['laravel']['supportsPort'] === true, 'laravel soporta puerto');
eq(8000, $byId['laravel']['defaultPort'], 'laravel usa 8000 por defecto');
ok($byId['composer-dev']['supportsPort'] === false, 'composer run dev no expone puerto');
eq(5173, $byId['npm']['defaultPort'], 'vite usa 5173 por defecto');

$next = detectRunners(WWW_ROOT . DIRECTORY_SEPARATOR . 'node-next');
eq(3000, $next[0]['defaultPort'], 'next usa 3000 por defecto');

echo "== recommendedRunnerIds ==\n";
eq(['laravel', 'npm'], recommendedRunnerIds($laravel), 'laravel recomienda artisan + npm');
eq(['flutter-release'], recommendedRunnerIds(detectRunners(WWW_ROOT . DIRECTORY_SEPARATOR . 'flutter-app')), 'flutter recomienda flutter-release');
eq(['npm'], recommendedRunnerIds(detectRunners(WWW_ROOT . DIRECTORY_SEPARATOR . 'vite-app')), 'vite recomienda npm');
eq(['django'], recommendedRunnerIds(detectRunners(WWW_ROOT . DIRECTORY_SEPARATOR . 'django-app')), 'django recomienda django');

echo "== detectTech ==\n";
eq(['Laravel', 'PHP', 'Node'], detectTech(WWW_ROOT . DIRECTORY_SEPARATOR . 'laravel-app'), 'tecnologias de laravel-app');
ok(in_array('Flutter', detectTech(WWW_ROOT . DIRECTORY_SEPARATOR . 'flutter-app'), true), 'detecta Flutter');
ok(in_array('Django', detectTech(WWW_ROOT . DIRECTORY_SEPARATOR . 'django-app'), true), 'detecta Django');

echo "== safeProject ==\n";
ok(is_dir(safeProject('vite-app')), 'acepta proyecto existente');
throws(fn() => safeProject('../evil'), 'rechaza path traversal');
throws(fn() => safeProject('no-existe-xyz'), 'rechaza proyecto inexistente');
throws(fn() => safeProject('a"b'), 'rechaza caracteres invalidos');

echo "== logError ==\n";
eq('Error: EADDRINUSE: address already in use :::5173', logError(tmpLog("starting\nError: EADDRINUSE: address already in use :::5173\n")), 'detecta EADDRINUSE');
eq(null, logError(tmpLog("Server running fine\nno errors\n")), 'sin error devuelve null');
eq(null, logError('/no/existe.log'), 'archivo inexistente devuelve null');

echo "== portInUse ==\n";
$sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($sock === false) {
    echo "  skip puerto (no se pudo abrir socket: {$errstr})\n";
} else {
    $name = stream_socket_get_name($sock, false);
    $boundPort = (int)substr((string)strrchr((string)$name, ':'), 1);
    ok($boundPort > 0, 'socket de prueba abierto en ' . $boundPort);
    $holder = portInUse($boundPort, '127.0.0.1', true);
    ok($holder !== null, 'portInUse detecta el puerto ocupado', 'holder=' . var_export($holder, true));
    eq(null, portInUse(65534, '127.0.0.1', true), 'portInUse devuelve null para puerto libre');
    ok(findFreePort($boundPort) !== $boundPort, 'findFreePort salta el puerto ocupado');
    fclose($sock);
}

echo "== isProcessTreeMember ==\n";
ok(isProcessTreeMember(getmypid(), getmypid()), 'todo proceso es miembro de si mismo');
ok(!isProcessTreeMember(1, getmypid()), 'PID 1 no es hijo de este proceso');
$parent = getmypid();
$childCmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('sleep(30);');
$child = @proc_open($childCmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (is_resource($child)) {
    $status = proc_get_status($child);
    $childPid = (int)$status['pid'];
    ok($childPid > 0, 'proceso hijo lanzado (' . $childPid . ')');
    usleep(200000);
    ok(isRunning($childPid), 'el hijo sigue vivo');
    ok(isProcessTreeMember($childPid, $parent, true), 'el hijo pertenece al arbol del padre');
    ok(!isProcessTreeMember($parent, $childPid), 'el padre no pertenece al arbol del hijo');
    proc_terminate($child, 1);
    foreach ($pipes as $p) {
        if (is_resource($p)) {
            fclose($p);
        }
    }
    proc_close($child);
} else {
    echo "  skip arbol de procesos (no se pudo lanzar hijo)\n";
}

echo "== isRunning ==\n";
ok(isRunning(getmypid()), 'el proceso actual esta corriendo');
ok(!isRunning(999999999), 'PID inexistente no esta corriendo');
ok(!isRunning(0), 'PID 0 no esta corriendo');

echo "== servicesStatus ==\n";
$svcs = servicesStatus();
eq(8, count($svcs), 'devuelve 8 servicios');
$allBool = true;
foreach ($svcs as $s) {
    if (!isset($s['name']) || !is_bool($s['running'])) {
        $allBool = false;
    }
}
ok($allBool, 'cada servicio tiene name y running bool');
ok(array_search('Apache', array_column($svcs, 'name'), true) !== false, 'incluye Apache');
ok(array_search('MySQL', array_column($svcs, 'name'), true) !== false, 'incluye MySQL');

echo "== processNames ==\n";
$names = processNames();
ok(count($names) > 10, 'lista procesos del sistema', 'n=' . count($names));
ok(in_array('php.exe', $names, true) || in_array('php', $names, true), 'incluye el proceso php actual');

echo "== shellQuote ==\n";
if (isWindows()) {
    eq('"C:\\ruta con espacios"', shellQuote('C:\\ruta con espacios'), 'windows envuelve en comillas dobles');
    ok(strpos(shellQuote('a"b'), '""') !== false, 'windows escapa comillas dobles');
} else {
    eq("'C:\\ruta con espacios'", shellQuote('C:\\ruta con espacios'), 'unix envuelve en comillas simples');
    ok(strpos(shellQuote("a'b"), "'\\''") !== false, "unix escapa comillas simples");
}

echo "== authError ==\n";
function authCase(string $remote, string $provided, string $configToken, string $allowRemote): string
{
    $cmd = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'auth_case.php')
        . ' ' . implode(' ', array_map('escapeshellarg', [$remote, $provided, $configToken, $allowRemote]))
        . ' 2>&1';
    $out = @shell_exec($cmd);
    return trim((string)$out);
}

eq('OK', authCase('127.0.0.1', '', '', '0'), 'local sin token configurado se admite');
eq('OK', authCase('::1', '', '', '0'), 'local IPv6 se admite');
eq('OK', authCase('127.0.0.1', '', '', '1'), 'allow_remote sin token no afecta al local');
eq('Token requerido', authCase('127.0.0.1', '', 'secreto', '0'), 'local con token configurado lo exige');
eq('OK', authCase('127.0.0.1', 'secreto', 'secreto', '0'), 'local con token correcto se admite');
eq('Token requerido', authCase('127.0.0.1', 'otro', 'secreto', '0'), 'local con token incorrecto se rechaza');
eq('Solo acceso local', authCase('192.168.1.50', 'secreto', 'secreto', '0'), 'remoto sin allow_remote se rechaza');
eq('Solo acceso local', authCase('192.168.1.50', '', '', '0'), 'remoto sin nada se rechaza');
ok(strpos(authCase('192.168.1.50', '', '', '1'), 'auth_token') !== false, 'allow_remote sin token pide configurarlo', authCase('192.168.1.50', '', '', '1'));
eq('Token invalido', authCase('192.168.1.50', '', 'secreto', '1'), 'remoto con token configurado pero ausente');
eq('Token invalido', authCase('192.168.1.50', 'malo', 'secreto', '1'), 'remoto con token incorrecto');
eq('OK', authCase('192.168.1.50', 'secreto', 'secreto', '1'), 'remoto con token correcto se admite');

echo "\n";
$total = $GLOBALS['__pass'] + $GLOBALS['__fail'];
echo "{$GLOBALS['__pass']}/{$total} assertions OK\n";
if ($GLOBALS['__fail'] > 0) {
    echo "Fallos:\n";
    foreach ($GLOBALS['__failures'] as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
exit(0);
