<?php
declare(strict_types=1);
require __DIR__ . DIRECTORY_SEPARATOR . 'lib.php';
ensureDirs();
$wwwRoot = WWW_ROOT;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UpWeb - Panel de proyectos</title>
<link rel="stylesheet" href="assets/style.css">
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
