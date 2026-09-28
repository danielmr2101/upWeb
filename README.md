# UpWeb

Panel gráfico local para levantar todos tus proyectos de desarrollo con un clic.

Si tienes muchos proyectos en `www` (Laragon, u otro docroot) y te cansaste de recordar
qué comando corre en cada uno, este panel los detecta, los lista y los arranca.

```
http://localhost/upweb/
```

---

## Qué hace

| | |
|---|---|
| **Levantar en un clic** | Detecta la arquitectura de cada carpeta y arranta los servidores recomendados. |
| **Estado en vivo** | Punto verde/rojo por servidor, URL detectada desde el log, errores mostrados. |
| **Cualquier arquitectura** | Laravel, Flutter, Vite, Next, Django, Flask, FastAPI, Rails, Docker, PHP puro. |
| **Comandos propios** | ¿Tu stack no está detectado? Agregas un comando y queda guardado. |
| **Puertos configurables** | Eliges el puerto por servidor; se recuerda entre sesiones. |
| **Servicios del sistema** | Apache, nginx, MySQL, Redis, Node, Docker, Flutter — quién está corriendo. |
| **Auto-abrir navegador** | Cuando un server queda listo, se abre la pestaña sola. |

---

## Requisitos

- Windows (usa `taskkill`, `tasklist`, PowerShell para procesos desprendidos)
- PHP 8.1+
- [Laragon](https://laragon.org) (o cualquier docroot; configura `www_root`)

Opcional, según tus proyectos: Node, Composer, Flutter, Python, Docker.

---

## Instalación

1. Copia esta carpeta dentro de tu docroot:

   ```
   C:\laragon\www\upweb
   ```

2. Recarga Laragon (o espera el auto-vhost) y abre:

   ```
   http://upweb.test
   ```

   También funciona con `http://localhost/upweb/`.

3. Listo. El panel escanea las carpetas hermanas y muestra cada proyecto con su tarjeta.

### Configuración

Los valores por defecto auto-detectan Laragon. Si tu instalación es distinta,
copia `config.local.php.example` a `config.local.php` (está en `.gitignore`) y ajústalo:

```php
<?php
return [
    'www_root'    => 'D:\\htdocs',
    'laragon_exe' => 'D:\\laragon\\laragon.exe',
    'flutter_root' => 'C:\\flutter',
    'allow_remote' => false,
];
```

| Clave | Descripción | Por defecto |
|---|---|---|
| `www_root` | Carpeta que contiene los proyectos | carpeta padre de `upweb` |
| `laragon_exe` | Ruta a `laragon.exe` | `../laragon.exe` |
| `flutter_root` | SDK de Flutter (`FLUTTER_ROOT`) | auto-detección |
| `php_bin` / `npm_bin` / `node_bin` / `composer_phar` | Binarios | auto-detección |
| `host_suffix` | Sufijo del vhost | `.test` |
| `allow_remote` | Permitir acceso desde la red local | `false` |

> **Seguridad:** `api.php` solo responde a `127.0.0.1`/`::1` salvo que actives
> `allow_remote` (o la env var `UPWEB_ALLOW_REMOTE=1`). El panel ejecuta comandos
> en tu máquina: no lo expongas a internet.

---

## Uso

**Tarjeta de proyecto**

- **Levantar** — arranca los servidores recomendados para esa arquitectura.
- **Parar** — detiene todo lo del proyecto (solo aparece si algo corre).
- **Abrir .test** / **HTTPS** — abre el vhost de Laragon.
- **Carpeta** / **Terminal** — abre Explorer o Cmder en el proyecto.
- **+ Cmd** — agrega un comando personalizado.

**Servidor (fila dentro de la tarjeta)**

- **Iniciar** / **Detener** — control por servidor, con puerto editable.
- **Log** — salida en vivo del proceso (ahí ves los errores).

**Header**

- Chips de servicios (Apache, MySQL, Node, ...) con estado.
- **Laragon:** Iniciar / Reiniciar / Detener.
- **Auto-abrir** — abre el navegador cuando un server queda listo.
- **Detener todos los dev** — mata todos los procesos del panel.

---

## Arquitecturas detectadas

| Detección | Runner | Puerto |
|---|---|---|
| `artisan` | `php artisan serve` | configurable (8000) |
| `composer.json` → `scripts.dev` | `composer run dev` | auto |
| `package.json` → `dev`/`start`/`serve` | `npm run <script>` | según framework |
| `pubspec.yaml` + `lib/main.dart` | `flutter build web && php -S` / `flutter run` | 8080 |
| `manage.py` | `python manage.py runserver` | 8000 |
| `main.py` + FastAPI | `uvicorn main:app` | 8000 |
| `app.py` + Flask | `flask run` | 5000 |
| `bin/rails` | `bin/rails server` | 3000 |
| `docker-compose.yml` | `docker compose up` | auto |
| `index.php` / `index.html` | `php -S` | 8000 |

**Comandos personalizados** aceptan estos tokens:

```
{php} {npm} {flutter} {python} {composer} {host} {port} {log}
```

---

## Estructura

```
upweb/
├── index.php              UI
├── api.php                JSON API (solo localhost)
├── lib.php                detección, arranque y parada de procesos
├── config.php             valores por defecto + auto-detección
├── config.local.php.example
├── assets/
│   ├── app.js
│   └── style.css
└── storage/               runtime (ignorado por git)
    ├── state.json         PIDs y puertos activos
    ├── custom.json        comandos personalizados
    └── logs/              salida de cada servidor
```

---

## API

Todas las respuestas: `{ "ok": true, "data": ... }` o `{ "ok": false, "error": "..." }`.

| `action` | Parámetros | Descripción |
|---|---|---|
| `list` | — | proyectos + runners + estado |
| `services` | — | servicios del sistema |
| `up` | `project` | arranca los runners recomendados |
| `start` | `project, id, port, host` | arranca un runner |
| `stop` | `project, id` | detiene un runner |
| `stop-project` | `project` | detiene todos los del proyecto |
| `stop-all` | — | detiene todo |
| `log` | `project, id, lines` | últimas líneas del log |
| `open` | `target, value` | `url` / `folder` / `terminal` |
| `laragon` | `do=start\|stop\|restart` | controla Laragon |
| `custom-add` | `project, label, command, port` | guarda comando propio |
| `custom-remove` | `project, customId` | borra comando propio |

---

## Roadmap

- [ ] Logs en vivo (SSE) en vez de refresco cada 5 s
- [ ] Favoritos / orden personalizado de proyectos
- [ ] Detección de puertos ocupados antes de arrancar
- [ ] Multi-usuario / acceso desde la red LAN (con token)
- [ ] Soporte Linux/macOS (sustituir `taskkill`/PowerShell)
- [ ] Tema claro

---

## Licencia

MIT — ver [LICENSE](LICENSE).
