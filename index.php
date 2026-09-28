<?php
declare(strict_types=1);
require __DIR__ . DIRECTORY_SEPARATOR . 'lib.php';
ensureDirs();
$authError = authError();
if ($authError !== null) {
    http_response_code(strpos($authError, 'local') !== false ? 403 : 401);
    $token = authToken();
    ?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UpWeb - Acceso</title>
<link rel="stylesheet" href="assets/style.css"></head>
<body class="lock">
<div class="lockbox">
    <span class="logo">U</span>
    <h1><?= htmlspecialchars($authError) ?></h1>
    <?php if ($token !== null && strpos($authError, 'local') === false): ?>
    <form method="get">
        <input type="password" name="token" placeholder="Token de acceso" autofocus>
        <button class="btn" type="submit">Entrar</button>
    </form>
    <?php else: ?>
    <p>Este panel solo responde a <code>127.0.0.1</code>. Activa <code>allow_remote</code> y un <code>auth_token</code> en <code>config.local.php</code> para acceder desde otra maquina.</p>
    <?php endif; ?>
</div>
</body></html><?php
    exit;
}
$wwwRoot = WWW_ROOT;
$token = (string)(authToken() ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UpWeb - Panel de proyectos</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="stylesheet" href="assets/style.css">
<script>window.UPWEB_TOKEN = <?= json_encode($token, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
</head>
<body>
<header class="topbar">
    <div class="brand">
        <span class="logo">U</span>
        <div>
            <h1>UpWeb</h1>
            <p>Panel de proyectos en <code><?= htmlspecialchars($wwwRoot) ?></code></p>
        </div>
    </div>
    <div class="tools">
        <div id="services" class="services"></div>
        <input id="search" type="search" placeholder="Buscar proyecto...">
        <label class="autoopen" title="Mostrar solo los proyectos marcados con estrella">
            <input type="checkbox" id="fav-only"> <span class="star">&#9733;</span> Favoritos
        </label>
        <label class="autoopen" title="Abrir navegador automaticamente cuando el server este listo">
            <input type="checkbox" id="auto-open"> Auto-abrir
        </label>
        <div class="laragon">
            <span class="laragon-label">Laragon</span>
            <button class="btn ghost" data-laragon="start">Iniciar</button>
            <button class="btn ghost" data-laragon="restart">Reiniciar</button>
            <button class="btn ghost danger" data-laragon="stop">Detener</button>
        </div>
        <button class="btn ghost danger" id="stop-all">Detener todos los dev</button>
    </div>
</header>

<main>
    <div id="grid" class="grid"></div>
    <p id="empty" class="empty" hidden>No se encontraron proyectos.</p>
</main>

<div id="toasts" class="toasts"></div>
<script src="assets/app.js"></script>
</body>
</html>
