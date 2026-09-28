<?php
declare(strict_types=1);

$__upwebConfig = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
if (!is_array($__upwebConfig)) {
    $__upwebConfig = [];
}
define('UPWEB_CONFIG', $__upwebConfig);
define('WWW_ROOT', rtrim((string)($__upwebConfig['www_root'] ?? dirname(__DIR__)), '\\/'));
define('LARAGON_EXE', (string)($__upwebConfig['laragon_exe'] ?? 'C:\\laragon\\laragon.exe'));
define('STORAGE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'storage');
define('LOG_DIR', STORAGE_DIR . DIRECTORY_SEPARATOR . 'logs');
define('STATE_FILE', STORAGE_DIR . DIRECTORY_SEPARATOR . 'state.json');
define('SELF_PROJECT', 'upweb');
define('HOSTS_FILE', (string)($__upwebConfig['hosts_file'] ?? 'C:\\Windows\\System32\\drivers\\etc\\hosts'));
define('UPWEB_ALLOW_REMOTE', !empty($__upwebConfig['allow_remote']));
define('LARAGON_BIN', dirname(LARAGON_EXE) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR);
unset($__upwebConfig);

function config(string $key, $default = null)
{
    return UPWEB_CONFIG[$key] ?? $default;
}

function authToken(): ?string
{
    $token = (string)config('auth_token', '');
    return $token !== '' ? $token : null;
}

function providedToken(): string
{
    $token = (string)($_REQUEST['token'] ?? '');
    if ($token === '') {
        $token = (string)($_SERVER['HTTP_X_UPWEB_TOKEN'] ?? '');
    }
    return $token;
}

function isLocalRequest(): bool
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '') {
        return true;
    }
    return in_array($remote, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

/**
 * Devuelve null si la peticion esta autorizada, o un mensaje de error.
 * Reglas: el token (si esta configurado) es obligatorio para todos;
 * sin token configurado solo se admite acceso local.
 */
function authError(): ?string
{
    $token = authToken();
    $provided = providedToken();

    if (isLocalRequest()) {
        if ($token === null) {
            return null;
        }
        return hash_equals($token, $provided) ? null : 'Token requerido';
    }

    if (!UPWEB_ALLOW_REMOTE) {
        return 'Solo acceso local';
    }
    if ($token === null) {
        return 'Acceso remoto deshabilitado: configura "auth_token" en config.local.php';
    }
    return hash_equals($token, $provided) ? null : 'Token invalido';
}

function composerPhar(): string
{
    $configured = config('composer_phar');
    if (is_string($configured) && $configured !== '' && is_file($configured)) {
        return $configured;
    }
    foreach ([
        'C:\\laragon\\bin\\composer\\composer.phar',
        'C:\\ProgramData\\ComposerSetup\\bin\\composer.phar',
    ] as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return 'composer';
}

function ensureDirs(): void
{
    foreach ([STORAGE_DIR, LOG_DIR] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

function newestGlob(string $pattern): ?string
{
    $hits = glob($pattern) ?: [];
    if (!$hits) {
        return null;
    }
    rsort($hits, SORT_STRING);
    return $hits[0];
}

function phpBinary(): string
{
    $configured = config('php_bin');
    if (is_string($configured) && $configured !== '' && (is_file($configured) || $configured === 'php')) {
        return $configured;
    }
    return newestGlob(LARAGON_BIN . 'php\\php-*\\php.exe')
        ?? newestGlob('C:\\laragon\\bin\\php\\php-*\\php.exe')
        ?? 'php';
}

function nodeBinary(): string
{
    $configured = config('node_bin');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }
    return newestGlob(LARAGON_BIN . 'nodejs\\node-v*\\node.exe')
        ?? newestGlob('C:\\laragon\\bin\\nodejs\\node-v*\\node.exe')
        ?? 'node';
}

function npmBinary(): string
{
    $configured = config('npm_bin');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }
    return newestGlob(LARAGON_BIN . 'nodejs\\node-v*\\npm.cmd')
        ?? newestGlob('C:\\laragon\\bin\\nodejs\\node-v*\\npm.cmd')
        ?? 'npm';
}

function flutterBinary(): string
{
    $root = config('flutter_root') ?: getenv('FLUTTER_ROOT');
    $candidates = [
        is_string($root) && $root !== '' ? $root . '\\bin\\flutter.bat' : '',
        'C:\\flutter\\bin\\flutter.bat',
        'C:\\src\\flutter\\bin\\flutter.bat',
        'C:\\laragon\\bin\\flutter\\bin\\flutter.bat',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }
    return 'flutter';
}

function pythonBinary(): string
{
    return newestGlob('C:\\laragon\\bin\\python\\python-*\\python.exe')
        ?? newestGlob('C:\\laragon\\bin\\python\\*\\python.exe')
        ?? 'python';
}

function dockerAvailable(): bool
{
    static $available = null;
    if ($available === null) {
        $out = @shell_exec('docker --version 2>NUL');
        $available = is_string($out) && stripos($out, 'docker') !== false;
    }
    return $available;
}

function composeFile(string $path): ?string
{
    foreach (['docker-compose.yml', 'docker-compose.yaml', 'compose.yaml', 'compose.yml'] as $name) {
        if (is_file($path . DIRECTORY_SEPARATOR . $name)) {
            return $path . DIRECTORY_SEPARATOR . $name;
        }
    }
    return null;
}

function loadState(): array
{
    if (!is_file(STATE_FILE)) {
        return [];
    }
    $data = json_decode((string)@file_get_contents(STATE_FILE), true);
    return is_array($data) ? $data : [];
}

function saveState(array $state): void
{
    ensureDirs();
    @file_put_contents(STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function safeProject(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
        throw new RuntimeException('Nombre de proyecto invalido');
    }
    if ($name === '.' || $name === '..') {
        throw new RuntimeException('Nombre de proyecto invalido');
    }
    $path = WWW_ROOT . DIRECTORY_SEPARATOR . $name;
    if (!is_dir($path)) {
        throw new RuntimeException('Proyecto no encontrado');
    }
    return $path;
}

function listProjectNames(): array
{
    $names = [];
    foreach (glob(WWW_ROOT . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
        $names[] = basename($dir);
    }
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    return $names;
}

function hostExists(string $name): bool
{
    $hosts = @file_get_contents(HOSTS_FILE);
    if ($hosts === false) {
        return true;
    }
    return stripos($hosts, $name . '.test') !== false;
}

function readJsonFile(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $raw = (string)@file_get_contents($file);
    if ($raw === '') {
        return null;
    }
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function packageJson(string $path): ?array
{
    return readJsonFile($path . DIRECTORY_SEPARATOR . 'package.json');
}

function composerJson(string $path): ?array
{
    return readJsonFile($path . DIRECTORY_SEPARATOR . 'composer.json');
}

function detectJsFramework(?array $pkg): string
{
    if ($pkg === null) {
        return 'unknown';
    }
    $deps = array_merge(
        is_array($pkg['dependencies'] ?? null) ? $pkg['dependencies'] : [],
        is_array($pkg['devDependencies'] ?? null) ? $pkg['devDependencies'] : []
    );
    $has = static fn(string $name): bool => array_key_exists($name, $deps);

    if ($has('next')) {
        return 'next';
    }
    if ($has('@angular/cli') || $has('@angular/core')) {
        return 'angular';
    }
    if ($has('react-scripts')) {
        return 'cra';
    }
    if ($has('nuxt') || $has('nuxt3')) {
        return 'nuxt';
    }
    if ($has('astro')) {
        return 'astro';
    }
    if ($has('@remix-run/dev')) {
        return 'remix';
    }
    if ($has('@sveltejs/kit') || $has('svelte')) {
        return 'svelte';
    }
    if ($has('vite')) {
        return 'vite';
    }
    if ($has('express') || $has('fastify') || $has('koa') || $has('nest')) {
        return 'node-server';
    }
    return 'unknown';
}

function jsScript(?array $pkg): ?string
{
    if ($pkg === null || !is_array($pkg['scripts'] ?? null)) {
        return null;
    }
    foreach (['dev', 'start', 'serve', 'develop'] as $candidate) {
        if (isset($pkg['scripts'][$candidate])) {
            return $candidate;
        }
    }
    return null;
}

function defaultPortForFramework(string $framework): int
{
    return match ($framework) {
        'next', 'cra', 'nuxt', 'remix', 'node-server' => 3000,
        'angular' => 4200,
        'astro' => 4321,
        'svelte', 'vite' => 5173,
        default => 3000,
    };
}

function npmCommand(string $framework, string $script): string
{
    return match ($framework) {
        'vite', 'svelte', 'astro', 'nuxt', 'angular', 'remix'
            => '{npm} run ' . $script . ' -- --host {host} --port {port}',
        'next'
            => '{npm} run ' . $script . ' -- --port {port} --hostname {host}',
        'cra'
            => 'set "PORT={port}"&& set "HOST={host}"&& {npm} run ' . $script,
        default
            => 'set "PORT={port}"&& {npm} run ' . $script,
    };
}

function detectRunners(string $path): array
{
    $runners = [];
    $hasArtisan = is_file($path . DIRECTORY_SEPARATOR . 'artisan');
    $composer = composerJson($path);
    $pkg = packageJson($path);
    $composerScripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];

    if ($hasArtisan) {
        $runners[] = [
            'id' => 'laravel',
            'label' => 'Laravel (artisan serve)',
            'kind' => 'backend',
            'supportsPort' => true,
            'defaultPort' => 8000,
            'command' => '{php} artisan serve --host={host} --port={port}',
        ];
    }

    if (isset($composerScripts['dev'])) {
        $runners[] = [
            'id' => 'composer-dev',
            'label' => 'Composer (composer run dev)',
            'kind' => 'all',
            'supportsPort' => false,
            'defaultPort' => null,
            'command' => '{php} {composer} run dev',
        ];
    }
    foreach (['serve', 'start', 'server'] as $script) {
        if (isset($composerScripts[$script])) {
            $runners[] = [
                'id' => 'composer-' . $script,
                'label' => 'Composer (' . $script . ')',
                'kind' => 'backend',
                'supportsPort' => false,
                'defaultPort' => null,
                'command' => '{php} {composer} run ' . $script,
            ];
        }
    }

    $script = jsScript($pkg);
    if ($script !== null) {
        $framework = detectJsFramework($pkg);
        $runners[] = [
            'id' => 'npm',
            'label' => 'Node ' . $framework . ' (npm run ' . $script . ')',
            'kind' => 'frontend',
            'supportsPort' => true,
            'defaultPort' => defaultPortForFramework($framework),
            'command' => npmCommand($framework, $script),
        ];
    }

    if (is_file($path . DIRECTORY_SEPARATOR . 'pubspec.yaml')
        && is_file($path . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'main.dart')) {
        $flutter = flutterBinary();
        if (is_dir($path . DIRECTORY_SEPARATOR . 'web')) {
            $runners[] = [
                'id' => 'flutter-release',
                'label' => 'Flutter web release (build + serve)',
                'kind' => 'frontend',
                'supportsPort' => true,
                'defaultPort' => 8080,
                'command' => $flutter . ' build web --release --base-href / > {log} 2>&1 && {php} -S {host}:{port} -t '
                    . $path . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'web' . ' >> {log} 2>&1',
            ];
            $runners[] = [
                'id' => 'flutter',
                'label' => 'Flutter web debug (flutter run, caliente)',
                'kind' => 'frontend',
                'supportsPort' => true,
                'defaultPort' => 8080,
                'command' => $flutter . ' run -d web-server --web-hostname={host} --web-port={port}',
            ];
        } else {
            $runners[] = [
                'id' => 'flutter',
                'label' => 'Flutter (flutter run)',
                'kind' => 'frontend',
                'supportsPort' => false,
                'defaultPort' => null,
                'command' => $flutter . ' run',
            ];
        }
        if (is_file($path . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'index.html')) {
            $runners[] = [
                'id' => 'flutter-static',
                'label' => 'Flutter build/web (estatico)',
                'kind' => 'frontend',
                'supportsPort' => true,
                'defaultPort' => 8080,
                'command' => '{php} -S {host}:{port} -t ' . $path . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'web',
            ];
        }
    }

    if (is_file($path . DIRECTORY_SEPARATOR . 'manage.py')) {
        $runners[] = [
            'id' => 'django',
            'label' => 'Django (manage.py runserver)',
            'kind' => 'backend',
            'supportsPort' => true,
            'defaultPort' => 8000,
            'command' => '{python} manage.py runserver {host}:{port}',
        ];
    } else {
        $pyDeps = '';
        if (is_file($path . DIRECTORY_SEPARATOR . 'requirements.txt')) {
            $pyDeps .= (string)@file_get_contents($path . DIRECTORY_SEPARATOR . 'requirements.txt');
        }
        if (is_file($path . DIRECTORY_SEPARATOR . 'pyproject.toml')) {
            $pyDeps .= (string)@file_get_contents($path . DIRECTORY_SEPARATOR . 'pyproject.toml');
        }
        $pyDeps = strtolower($pyDeps);
        if (is_file($path . DIRECTORY_SEPARATOR . 'main.py') && str_contains($pyDeps, 'fastapi')) {
            $runners[] = [
                'id' => 'fastapi',
                'label' => 'FastAPI (uvicorn)',
                'kind' => 'backend',
                'supportsPort' => true,
                'defaultPort' => 8000,
                'command' => '{python} -m uvicorn main:app --host {host} --port {port}',
            ];
        } elseif (is_file($path . DIRECTORY_SEPARATOR . 'app.py') && str_contains($pyDeps, 'flask')) {
            $runners[] = [
                'id' => 'flask',
                'label' => 'Flask (flask run)',
                'kind' => 'backend',
                'supportsPort' => true,
                'defaultPort' => 5000,
                'command' => 'set "FLASK_RUN_HOST={host}"&& set "FLASK_RUN_PORT={port}"&& {python} -m flask --app app run',
            ];
        }
    }

    if (is_file($path . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rails')) {
        $runners[] = [
            'id' => 'rails',
            'label' => 'Rails (bin/rails server)',
            'kind' => 'backend',
            'supportsPort' => true,
            'defaultPort' => 3000,
            'command' => 'ruby bin/rails server -b {host} -p {port}',
        ];
    }

    if (composeFile($path) !== null && dockerAvailable()) {
        $runners[] = [
            'id' => 'docker',
            'label' => 'Docker (docker compose up)',
            'kind' => 'all',
            'supportsPort' => false,
            'defaultPort' => null,
            'command' => 'docker compose up',
        ];
    }

    if (!$runners) {
        $docroot = $path;
        if (is_file($path . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php')) {
            $docroot = $path . DIRECTORY_SEPARATOR . 'public';
        }
        if (is_file($path . DIRECTORY_SEPARATOR . 'index.php')
            || is_file($docroot . DIRECTORY_SEPARATOR . 'index.php')
            || is_file($path . DIRECTORY_SEPARATOR . 'index.html')) {
            $runners[] = [
                'id' => 'php',
                'label' => 'PHP built-in server',
                'kind' => 'backend',
                'supportsPort' => true,
                'defaultPort' => 8000,
                'command' => '{php} -S {host}:{port} -t ' . $docroot,
            ];
        }
    }

    return $runners;
}

function freshMapFor(int ...$pids): array
{
    $map = processParentMap();
    if ($map === []) {
        return $map;
    }
    static $tried = [];
    static $refreshes = 0;
    if ($refreshes >= 5) {
        return $map;
    }
    foreach ($pids as $pid) {
        if ($pid <= 0 || isset($map[$pid]) || isset($tried[$pid])) {
            continue;
        }
        $tried[$pid] = true;
        $refreshes++;
        $map = processParentMap(true);
        break;
    }
    return $map;
}

function isRunning(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }
    $map = freshMapFor($pid);
    if ($map !== []) {
        return isset($map[$pid]);
    }
    if (isWindows()) {
        $out = @shell_exec('tasklist /FI "PID eq ' . $pid . '" /NH /FO CSV 2>NUL');
        return is_string($out) && strpos($out, (string)$pid) !== false;
    }
    return @posix_kill($pid, 0) || (bool)@shell_exec('ps -p ' . $pid . ' -o pid= 2>/dev/null');
}

function isWindows(): bool
{
    return DIRECTORY_SEPARATOR === '\\';
}

function isMac(): bool
{
    return !isWindows() && PHP_OS_FAMILY === 'Darwin';
}

function shellQuote(string $value): string
{
    if (isWindows()) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return escapeshellarg($value);
}

function processParentMap(bool $refresh = false): array
{
    static $map = null;
    if ($map !== null && !$refresh) {
        return $map;
    }
    $map = [];
    if (isWindows()) {
        $ps = 'Get-CimInstance Win32_Process | ForEach-Object { @($_.ProcessId, $_.ParentProcessId) -join [char]58 }';
        $out = (string)@shell_exec('powershell -NoProfile -Command "' . $ps . '"');
    } else {
        $out = (string)@shell_exec('ps -eo pid=,ppid= 2>/dev/null');
    }
    foreach (preg_split('/\r\n|\n|\r/', $out) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (isWindows()) {
            if (preg_match('/^(\d+):(\d+)$/', $line, $m)) {
                $map[(int)$m[1]] = (int)$m[2];
            }
        } else {
            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $m)) {
                $map[(int)$m[1]] = (int)$m[2];
            }
        }
    }
    return $map;
}

function isProcessTreeMember(int $pid, int $root, bool $refresh = false): bool
{
    if ($pid <= 0 || $root <= 0) {
        return false;
    }
    if ($pid === $root) {
        return true;
    }
    $map = $refresh ? processParentMap(true) : freshMapFor($pid);
    $current = $pid;
    for ($i = 0; $i < 50; $i++) {
        $ppid = $map[$current] ?? 0;
        if ($ppid <= 0 || $ppid === $current) {
            return false;
        }
        if ($ppid === $root) {
            return true;
        }
        $current = $ppid;
    }
    return false;
}

function portRows(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $rows = [];

    if (isWindows()) {
        $out = (string)@shell_exec('netstat -ano -p tcp 2>NUL');
        foreach (preg_split('/\r\n|\n|\r/', $out) ?: [] as $line) {
            if (preg_match('/^\s*TCP\s+(\S+):(\d+)\s+\S+\s+LISTENING\s+(\d+)/i', $line, $m)) {
                $rows[] = [trim($m[1], '[]'), (int)$m[2], (int)$m[3]];
            }
        }
    } else {
        $out = isMac()
            ? (string)@shell_exec('netstat -anv -p tcp 2>/dev/null || true')
            : (string)@shell_exec('ss -ltnpH 2>/dev/null || netstat -ltnp 2>/dev/null || true');
        foreach (preg_split('/\r\n|\n|\r/', $out) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $addr = null;
            $port = 0;
            // ss:   LISTEN 0 511 127.0.0.1:8000 0.0.0.0:* users:(("php",pid=123,fd=6))
            // net:  tcp 0 0 127.0.0.1:8000 0.0.0.0:* LISTEN 1234/php
            if (preg_match('/^LISTEN\s+\d+\s+\d+\s+(\S+):(\d+)\s/', $line, $m)
                || preg_match('/^tcp\S*\s+\d+\s+\d+\s+(\S+):(\d+)\s+\S+\s+LISTEN\b/', $line, $m)
                // macOS: tcp4 0 0 127.0.0.1.8000 *.* LISTEN 131072 131072 1234
                || preg_match('/^tcp[46]\s+\d+\s+\d+\s+(\S+)\.(\d+)\s+\S+\s+LISTEN\b/', $line, $m)) {
                $addr = trim($m[1], '[]');
                $port = (int)$m[2];
            }
            if ($addr === null || $port < 1 || $port > 65535) {
                continue;
            }
            $pid = 0;
            if (preg_match('/pid=(\d+)/', $line, $pm)) {
                $pid = (int)$pm[1];
            } elseif (preg_match('/\s(\d+)\/\S+\s*$/', $line, $pm)) {
                $pid = (int)$pm[1];
            } elseif (preg_match('/\bLISTEN\b.*\s(\d+)\s*$/', $line, $pm)) {
                $pid = (int)$pm[1];
            }
            $rows[] = [$addr, $port, $pid];
        }
    }

    $cache = $rows;
    return $rows;
}

/**
 * Devuelve el PID que escucha en $port, 0 si esta ocupado por un proceso cuyo
 * PID no se pudo determinar, o null si el puerto esta libre.
 */
function portInUse(int $port, string $host = '127.0.0.1', bool $refresh = false): ?int
{
    if ($port < 1 || $port > 65535) {
        return null;
    }
    $anyHost = $host === '0.0.0.0' || $host === '::';
    foreach (portRows($refresh) as [$addr, $p, $pid]) {
        if ($p !== $port) {
            continue;
        }
        if (!$anyHost && !in_array($addr, ['127.0.0.1', '::1', '::', '0.0.0.0'], true)) {
            continue;
        }
        return $pid > 0 ? $pid : 0;
    }
    return null;
}

function findFreePort(int $preferred, string $host = '127.0.0.1'): int
{
    $port = $preferred;
    for ($i = 0; $i < 40; $i++, $port++) {
        if ($port > 65535) {
            $port = 1024;
        }
        if (portInUse($port, $host) === null) {
            return $port;
        }
    }
    return $preferred;
}

function killProcess(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }
    if (isWindows()) {
        @shell_exec('taskkill /PID ' . $pid . ' /T /F 2>NUL');
    } else {
        @shell_exec('kill -9 ' . $pid . ' 2>/dev/null');
        @shell_exec('pkill -9 -P ' . $pid . ' 2>/dev/null');
    }
    usleep(300000);
    processParentMap(true);
    return !isRunning($pid);
}

function detectUrl(string $logFile): ?string
{
    if (!is_file($logFile)) {
        return null;
    }
    $size = (int)filesize($logFile);
    $fp = @fopen($logFile, 'r');
    if (!$fp) {
        return null;
    }
    $chunks = [];
    if ($size > 16000) {
        fseek($fp, $size - 16000);
    }
    while (!feof($fp)) {
        $chunks[] = (string)fread($fp, 8192);
    }
    fclose($fp);
    $tail = implode('', $chunks);

    $logDir = dirname($logFile);
    $patterns = [
        '/Local:\s+(https?:\/\/[^\s]+)/i',
        '/Server running on \[(https?:\/\/[^\]\s]+)\]/i',
        '/served at\s+(https?:\/\/[^\s]+)/i',
        '/A Dart VM Service.*?is available at:\s*(https?:\/\/[^\s]+)/i',
        '/Debug service listening on\s*(https?:\/\/[^\s]+)/i',
        '/https?:\/\/127\.0\.0\.1:\d+(?![0-9])/i',
        '/https?:\/\/localhost:\d+(?![0-9])/i',
        '/https?:\/\/[a-z0-9.-]+\.test/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $tail, $m) && !empty($m[0])) {
            $groups = $m[1] ?? $m[0];
            foreach (array_reverse($groups) as $candidate) {
                $candidate = rtrim(trim((string)$candidate), '.,;');
                if ($candidate === '' || stripos($candidate, 'http') !== 0) {
                    continue;
                }
                $avoid = static function (string $needle) use ($logDir): bool {
                    return is_file($logDir . DIRECTORY_SEPARATOR . $needle);
                };
                if ($avoid(basename(parse_url($candidate, PHP_URL_PATH) ?: ''))) {
                    continue;
                }
                return $candidate;
            }
        }
    }
    return null;
}

function detectLogPort(string $logFile): ?int
{
    if (!is_file($logFile)) {
        return null;
    }
    $content = (string)@file_get_contents($logFile, false, null, 0, 32000);
    if (preg_match_all('/127\.0\.0\.1:(\d{2,5})/', $content, $m) && !empty($m[1])) {
        return (int)end($m[1]);
    }
    return null;
}

function fallbackUrl($port, bool $supportsPort): ?string
{
    return ($supportsPort && $port) ? 'http://127.0.0.1:' . (int)$port : null;
}

function buildCommand(string $template, string $host, int $port, string $log = ''): string
{
    return strtr($template, [
        '{php}' => phpBinary(),
        '{npm}' => npmBinary(),
        '{composer}' => composerPhar(),
        '{flutter}' => flutterBinary(),
        '{python}' => pythonBinary(),
        '{host}' => $host,
        '{port}' => (string)$port,
        '{log}' => $log,
    ]);
}

function customRunners(string $name): array
{
    $file = STORAGE_DIR . DIRECTORY_SEPARATOR . 'custom.json';
    $all = readJsonFile($file) ?? [];
    if (!is_array($all) || !isset($all[$name]) || !is_array($all[$name])) {
        return [];
    }
    $runners = [];
    foreach ($all[$name] as $custom) {
        if (!is_array($custom) || empty($custom['command'])) {
            continue;
        }
        $runners[] = [
            'id' => 'custom-' . ($custom['id'] ?? uniqid()),
            'label' => (string)($custom['label'] ?? $custom['command']),
            'kind' => 'custom',
            'supportsPort' => !empty($custom['supportsPort']),
            'defaultPort' => (int)($custom['defaultPort'] ?? 8080),
            'command' => (string)$custom['command'],
            'custom' => true,
        ];
    }
    return $runners;
}

function addCustomCommand(string $name, string $label, string $command, int $port): void
{
    safeProject($name);
    if (trim($label) === '' || trim($command) === '') {
        throw new RuntimeException('Label y comando son obligatorios');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Puerto invalido');
    }
    $file = STORAGE_DIR . DIRECTORY_SEPARATOR . 'custom.json';
    $all = readJsonFile($file) ?? [];
    if (!is_array($all)) {
        $all = [];
    }
    if (!isset($all[$name]) || !is_array($all[$name])) {
        $all[$name] = [];
    }
    $all[$name][] = [
        'id' => uniqid('c', false),
        'label' => trim($label),
        'command' => trim($command),
        'supportsPort' => true,
        'defaultPort' => $port,
    ];
    ensureDirs();
    @file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function removeCustomCommand(string $name, string $customId): void
{
    $file = STORAGE_DIR . DIRECTORY_SEPARATOR . 'custom.json';
    $all = readJsonFile($file) ?? [];
    if (!isset($all[$name]) || !is_array($all[$name])) {
        return;
    }
    $all[$name] = array_values(array_filter($all[$name], function ($c) use ($customId) {
        return !is_array($c) || ($c['id'] ?? '') !== $customId;
    }));
    @file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function allRunners(string $name, string $path): array
{
    return array_merge(detectRunners($path), customRunners($name));
}

function detectTech(string $path): array
{
    $tech = [];
    if (is_file($path . DIRECTORY_SEPARATOR . 'artisan')) {
        $tech[] = 'Laravel';
    }
    if (is_file($path . DIRECTORY_SEPARATOR . 'composer.json')) {
        $tech[] = 'PHP';
    }
    if (is_file($path . DIRECTORY_SEPARATOR . 'package.json')) {
        $tech[] = 'Node';
    }
    if (is_file($path . DIRECTORY_SEPARATOR . 'pubspec.yaml')) {
        $tech[] = 'Flutter';
    }
    if (is_file($path . DIRECTORY_SEPARATOR . 'manage.py')) {
        $tech[] = 'Django';
    }
    if (is_file($path . DIRECTORY_SEPARATOR . 'requirements.txt')) {
        $tech[] = 'Python';
    }
    if (composeFile($path) !== null) {
        $tech[] = 'Docker';
    }
    if (is_file($path . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rails')) {
        $tech[] = 'Rails';
    }
    return $tech;
}

function processNames(): array
{
    static $names = null;
    if ($names !== null) {
        return $names;
    }
    $names = [];
    if (isWindows()) {
        $out = (string)@shell_exec('tasklist /NH /FO CSV 2>NUL');
        foreach (preg_split('/\r\n|\n|\r/', $out) ?: [] as $line) {
            if (preg_match('/^"([^"]+)"/', $line, $m)) {
                $names[] = strtolower($m[1]);
            }
        }
    } else {
        $out = (string)@shell_exec('ps -eo comm= 2>/dev/null');
        foreach (preg_split('/\r\n|\n|\r/', $out) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $names[] = strtolower(basename($line));
            }
        }
    }
    return $names;
}

function servicesStatus(): array
{
    $services = [
        'Apache' => ['httpd', 'apache2', 'httpd2'],
        'nginx' => ['nginx'],
        'MySQL' => ['mysqld', 'mysqld-nt'],
        'Redis' => ['redis-server'],
        'Memcached' => ['memcached'],
        'Node' => ['node'],
        'Docker' => ['dockerd', 'docker'],
        'Flutter' => ['flutter', 'dart'],
    ];
    $names = processNames();
    $status = [];
    foreach ($services as $label => $procesos) {
        $running = false;
        foreach ($procesos as $proc) {
            foreach ($names as $name) {
                if ($name === $proc || strpos($name, $proc . '.') === 0) {
                    $running = true;
                    break 2;
                }
            }
        }
        $status[] = ['name' => $label, 'running' => $running];
    }
    return $status;
}

function recommendedRunnerIds(array $runners): array
{
    $byId = [];
    foreach ($runners as $r) {
        $byId[$r['id']] = $r;
    }
    $recommended = [];
    if (isset($byId['laravel'])) {
        $recommended[] = 'laravel';
        if (isset($byId['npm'])) {
            $recommended[] = 'npm';
        }
        return $recommended;
    }
    if (isset($byId['flutter-release'])) {
        $recommended[] = 'flutter-release';
        return $recommended;
    }
    if (isset($byId['flutter'])) {
        $recommended[] = 'flutter';
        if (isset($byId['flutter-static'])) {
            $recommended[] = 'flutter-static';
        }
        return $recommended;
    }
    if (isset($byId['npm'])) {
        $recommended[] = 'npm';
        if (isset($byId['laravel'])) {
            $recommended[] = 'laravel';
        }
        return $recommended;
    }
    foreach (['django', 'fastapi', 'flask', 'rails', 'php', 'docker'] as $preferred) {
        if (isset($byId[$preferred])) {
            $recommended[] = $preferred;
        }
    }
    if ($recommended) {
        return $recommended;
    }
    foreach ($runners as $r) {
        $recommended[] = $r['id'];
    }
    return $recommended;
}

function upProject(string $name): array
{
    $path = safeProject($name);
    $runners = allRunners($name, $path);
    if (!$runners) {
        throw new RuntimeException('Este proyecto no tiene ningun servidor detectado');
    }
    $ids = recommendedRunnerIds($runners);
    $started = [];
    foreach ($ids as $id) {
        $runner = null;
        foreach ($runners as $r) {
            if ($r['id'] === $id) {
                $runner = $r;
                break;
            }
        }
        if ($runner === null) {
            continue;
        }
        $state = loadState();
        $key = $name . '::' . $id;
        $pid = (int)($state[$key]['pid'] ?? 0);
        if ($pid > 0 && isRunning($pid)) {
            $started[] = ['id' => $id, 'label' => $runner['label'], 'already' => true];
            continue;
        }
        $started[] = ['id' => $id] + startServer($name, $id, null, '127.0.0.1');
    }
    return ['started' => $started];
}

function stopProject(string $name): int
{
    safeProject($name);
    $state = loadState();
    $stopped = 0;
    foreach (array_keys($state) as $key) {
        if (strpos($key, $name . '::') !== 0) {
            continue;
        }
        $pid = (int)($state[$key]['pid'] ?? 0);
        if ($pid > 0) {
            killProcess($pid);
            unset($state[$key]);
            $stopped++;
        }
    }
    saveState($state);
    return $stopped;
}

function logError(string $logFile): ?string
{
    if (!is_file($logFile)) {
        return null;
    }
    $content = (string)@file_get_contents($logFile, false, null, 0, 16000);
    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
    foreach (array_reverse($lines) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/\b(error|exception|fatal|cannot|not found|EADDRINUSE|failed)\b/i', $line)) {
            return substr($line, 0, 240);
        }
    }
    return null;
}

function projectInfo(string $name): array
{
    $path = WWW_ROOT . DIRECTORY_SEPARATOR . $name;
    $state = loadState();
    $servers = [];

    foreach (allRunners($name, $path) as $server) {
        $key = $name . '::' . $server['id'];
        $log = (string)($state[$key]['log'] ?? '');
        $pid = (int)($state[$key]['pid'] ?? 0);
        $running = isRunning($pid);
        $port = (int)($state[$key]['port'] ?? 0);
        $detectedPort = $running ? detectLogPort($log) : null;
        $server['pid'] = $pid;
        $server['running'] = $running;
        $server['port'] = $detectedPort ?? ($port > 0 ? $port : $server['defaultPort']);
        $server['url'] = $running
            ? (detectUrl($log) ?? fallbackUrl($server['port'], $server['supportsPort']))
            : null;
        $server['startedAt'] = $running ? (int)($state[$key]['started'] ?? 0) : 0;
        $server['error'] = $running && $server['url'] === null ? logError($log) : null;
        if ($server['supportsPort']) {
            $candidatePort = $port > 0 ? $port : (int)$server['defaultPort'];
            $holder = portInUse($candidatePort, '127.0.0.1');
            $ours = $holder !== null && $holder > 0 && $pid > 0 && isProcessTreeMember($holder, $pid);
            $server['portBusy'] = ($holder !== null && !$ours) ? $holder : null;
        } else {
            $server['portBusy'] = null;
        }
        $servers[] = $server;
    }

    return [
        'name' => $name,
        'path' => $path,
        'prettyUrl' => 'http://' . $name . '.test',
        'prettyUrlHttps' => 'https://' . $name . '.test',
        'hasHost' => hostExists($name),
        'isSelf' => strcasecmp($name, SELF_PROJECT) === 0,
        'tech' => detectTech($path),
        'servers' => $servers,
    ];
}

function startDetached(string $dir, string $command, string $logFile): int
{
    ensureDirs();
    $needsRedirect = strpos($command, '>') === false;

    if ($needsRedirect) {
        @file_put_contents($logFile, '');
        $inner = $command . ' > ' . $logFile . ' 2>&1';
    } else {
        @file_put_contents($logFile, '');
        $inner = $command;
    }

    if (!isWindows()) {
        $full = 'cd ' . escapeshellarg($dir) . ' && ( ' . $inner . ' )';
        $output = @shell_exec('setsid sh -c ' . escapeshellarg($full) . ' > /dev/null 2>&1 & echo $!');
        return (int)trim((string)$output);
    }

    $ps = '$p = Start-Process -FilePath cmd.exe -ArgumentList \'/c\',\'' . $inner
        . '\' -WorkingDirectory \'' . $dir . '\' -WindowStyle Hidden -PassThru; $p.Id';

    $output = @shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -Command "' . $ps . '"');
    return (int)trim((string)$output);
}

function startServer(string $name, string $id, ?int $port = null, string $host = '127.0.0.1'): array
{
    $path = safeProject($name);
    $server = null;
    foreach (allRunners($name, $path) as $candidate) {
        if ($candidate['id'] === $id) {
            $server = $candidate;
            break;
        }
    }
    if ($server === null) {
        throw new RuntimeException('Este proyecto no tiene ese servidor disponible');
    }

    if (!in_array($host, ['127.0.0.1', '0.0.0.0', 'localhost'], true)) {
        $host = '127.0.0.1';
    }

    if ($server['supportsPort']) {
        $port = $port ?: (int)$server['defaultPort'];
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Puerto invalido (usa 1-65535)');
        }
        $state = loadState();
        $key = $name . '::' . $id;
        $ownPid = (int)($state[$key]['pid'] ?? 0);
        $holder = portInUse($port, $host, true);
        $ownedByUs = $holder !== null && $holder > 0 && $ownPid > 0 && isProcessTreeMember($holder, $ownPid);
        if ($holder !== null && !$ownedByUs) {
            $ours = $ownPid > 0 && isRunning($ownPid);
            throw new RuntimeException(
                'El puerto ' . $port . ' ya esta en uso'
                . ($holder > 0 ? ' (PID ' . $holder . ')' : ' (PID no disponible)')
                . ($ours ? ' y no pertenece a este servidor' : '')
                . '. Cambia el puerto o deten ese proceso.'
            );
        }
    } else {
        $port = 0;
        $state = loadState();
        $key = $name . '::' . $id;
    }

    $pid = (int)($state[$key]['pid'] ?? 0);
    if ($pid > 0 && isRunning($pid)) {
        return $state[$key] + ['already' => true];
    }

    $log = LOG_DIR . DIRECTORY_SEPARATOR . $name . '-' . $id . '.log';
    $command = buildCommand($server['command'], $host, $port, $log);
    $newPid = startDetached($path, $command, $log);
    if ($newPid <= 0) {
        throw new RuntimeException('No se pudo iniciar el proceso (revisa que npm/php/composer/flutter esten disponibles)');
    }

    $state[$key] = [
        'pid' => $newPid,
        'started' => time(),
        'log' => $log,
        'command' => $command,
        'label' => $server['label'],
        'host' => $host,
        'port' => $port,
    ];
    saveState($state);

    return $state[$key];
}

function stopServer(string $name, string $id): bool
{
    safeProject($name);
    $state = loadState();
    $key = $name . '::' . $id;
    $pid = (int)($state[$key]['pid'] ?? 0);
    if ($pid <= 0) {
        return false;
    }
    killProcess($pid);
    unset($state[$key]);
    saveState($state);
    return true;
}

function stopAllServers(): int
{
    $state = loadState();
    $stopped = 0;
    foreach (array_keys($state) as $key) {
        $pid = (int)($state[$key]['pid'] ?? 0);
        if ($pid > 0) {
            killProcess($pid);
            $stopped++;
        }
    }
    saveState([]);
    return $stopped;
}

function logFilePath(string $name, string $id): string
{
    return LOG_DIR . DIRECTORY_SEPARATOR . $name . '-' . $id . '.log';
}

function streamLog(string $name, string $id, int $from = 0): void
{
    safeProject($name);
    $file = logFilePath($name, $id);
    $isCliServer = PHP_SAPI === 'cli-server';

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    @ini_set('zlib.output_compression', '0');
    if (!$isCliServer) {
        @set_time_limit(0);
        ignore_user_abort(true);
    }

    $emit = static function (string $data, string $event = ''): void {
        if ($event !== '') {
            echo 'event: ' . $event . "\n";
        }
        foreach (preg_split('/\r\n|\n|\r/', $data) ?: [] as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    };

    clearstatcache(true, $file);
    $size = is_file($file) ? (int)filesize($file) : 0;
    if ($from < 0 || $from > $size) {
        $from = 0;
        $emit((string)$from, 'reset');
    }

    $fp = is_file($file) ? @fopen($file, 'rb') : false;
    if ($fp) {
        fseek($fp, $from);
        $chunk = (string)stream_get_contents($fp);
        $from = ftell($fp);
        if ($chunk !== '') {
            $emit($chunk, 'data');
            $emit((string)$from, 'offset');
        }
    }

    if ($isCliServer || !is_resource($fp)) {
        if (is_resource($fp)) {
            fclose($fp);
        }
        $emit((string)$from, 'eof');
        return;
    }

    $deadline = time() + 25;
    $idle = 0;
    while (time() < $deadline && !connection_aborted()) {
        clearstatcache(true, $file);
        $current = is_file($file) ? (int)filesize($file) : 0;
        if ($current < $from) {
            $from = 0;
            $emit((string)$from, 'reset');
        }
        if ($current > $from) {
            fseek($fp, $from);
            $chunk = (string)stream_get_contents($fp);
            $from = ftell($fp);
            if ($chunk !== '') {
                $emit($chunk, 'data');
                $emit((string)$from, 'offset');
                $idle = 0;
            }
        } else {
            $idle++;
            if ($idle % 12 === 0) {
                echo ": ping\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }
        usleep(450000);
    }

    fclose($fp);
    $emit((string)$from, 'eof');
}

function serverLog(string $name, string $id, int $lines = 80): string
{
    safeProject($name);
    $file = LOG_DIR . DIRECTORY_SEPARATOR . $name . '-' . $id . '.log';
    if (!is_file($file)) {
        return '';
    }
    $content = (string)@file_get_contents($file);
    $all = preg_split('/\r\n|\r|\n/', $content) ?: [];
    return implode("\n", array_slice($all, -$lines));
}

function laragonControl(string $action): string
{
    if (!in_array($action, ['start', 'stop', 'restart'], true)) {
        throw new RuntimeException('Accion no permitida');
    }
    if (!is_file(LARAGON_EXE)) {
        throw new RuntimeException('No se encontro laragon.exe');
    }
    @shell_exec('"' . LARAGON_EXE . '" ' . $action);
    return $action;
}

function openTarget(string $target, string $value): void
{
    if ($target === 'url') {
        if (!preg_match('#^https?://#i', $value)) {
            throw new RuntimeException('URL invalida');
        }
        if (isWindows()) {
            @shell_exec('start "" ' . shellQuote($value));
        } elseif (isMac()) {
            @shell_exec('open ' . shellQuote($value));
        } else {
            @shell_exec('setsid xdg-open ' . shellQuote($value) . ' >/dev/null 2>&1 &');
        }
        return;
    }

    if ($target === 'folder') {
        $path = safeProject($value);
        if (isWindows()) {
            @shell_exec('explorer ' . shellQuote($path));
        } elseif (isMac()) {
            @shell_exec('open ' . shellQuote($path));
        } else {
            @shell_exec('setsid xdg-open ' . shellQuote($path) . ' >/dev/null 2>&1 &');
        }
        return;
    }

    if ($target === 'terminal') {
        $path = safeProject($value);
        if (isWindows()) {
            $cmder = newestGlob('C:\\laragon\\bin\\cmder\\*\\cmder.exe')
                ?? newestGlob('C:\\laragon\\bin\\cmder\\cmder.exe');
            if ($cmder !== null && is_file($cmder)) {
                @shell_exec('start "" ' . shellQuote($cmder) . ' /START ' . shellQuote($path));
            } else {
                @shell_exec('start cmd /K cd /d ' . shellQuote($path));
            }
            return;
        }
        if (isMac()) {
            @shell_exec('open -a Terminal ' . shellQuote($path));
            return;
        }
        foreach ([
            ['gnome-terminal', '--working-directory='],
            ['konsole', '--workdir '],
            ['xfce4-terminal', '--working-directory='],
            ['kitty', '--directory '],
        ] as [$bin, $flag]) {
            if (trim((string)@shell_exec('command -v ' . $bin . ' 2>/dev/null')) === '') {
                continue;
            }
            @shell_exec('setsid ' . $bin . ' ' . $flag . shellQuote($path) . ' >/dev/null 2>&1 &');
            return;
        }
        throw new RuntimeException('No se encontro un emulador de terminal instalado');
    }

    throw new RuntimeException('Accion no soportada');
}
