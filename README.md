# UpWeb

[![CI](https://github.com/danielmr2101/upWeb/actions/workflows/ci.yml/badge.svg)](https://github.com/danielmr2101/upWeb/actions/workflows/ci.yml)
[Español](README.es.md)

A local web panel that lifts every one of your development projects with a single click.

If you keep dozens of projects in `www` (Laragon or any other docroot) and you are tired of
remembering which command runs in each one, this panel detects them, lists them and starts them.

```
http://localhost/upweb/
```

---

## Features

| | |
|---|---|
| **One-click lift** | Detects each folder's architecture and starts the recommended servers. |
| **Live logs** | Server-sent events stream the output in real time, with byte offsets so reconnects never duplicate or lose data. |
| **Live status** | Green/red dot per server, URL parsed from the log, errors surfaced inline. |
| **Any architecture** | Laravel, Flutter, Vite, Next, Angular, Nuxt, Astro, Django, Flask, FastAPI, Rails, Docker, plain PHP. |
| **Port conflicts** | Before starting, it checks whether the port is free and who owns it; conflicting ports are flagged red. |
| **Favorites & recency** | Star the projects you care about; the list sorts favorites first, then by last use. |
| **Custom commands** | Stack not detected? Add a command and it is persisted. |
| **Configurable ports** | You pick the port per server; it is remembered between sessions. |
| **System services** | Apache, nginx, MySQL, Redis, Node, Docker, Flutter — who is running. |
| **Auto-open browser** | When a server becomes ready its tab opens by itself. |
| **Cross-platform** | Windows, macOS and Linux for process, port and log handling. |

---

## Requirements

- PHP 8.1+
- Windows, macOS or Linux
- [Laragon](https://laragon.org) (or any docroot — set `www_root`)

Optional, depending on your projects: Node, Composer, Flutter, Python, Docker.

---

## Installation

1. Copy this folder into your docroot:

   ```
   C:\laragon\www\upweb
   ```

2. Reload Laragon (or wait for the auto-vhost) and open:

   ```
   http://upweb.test
   ```

   `http://localhost/upweb/` works too.

3. Done. The panel scans the sibling folders and shows a card for each project.

### Configuration

The defaults auto-detect Laragon. If your setup differs, copy
`config.local.php.example` to `config.local.php` (it is in `.gitignore`) and adjust it:

```php
<?php
return [
    'www_root'     => 'D:\\htdocs',
    'laragon_exe'  => 'D:\\laragon\\laragon.exe',
    'flutter_root' => 'C:\\flutter',
    'allow_remote' => false,
    'auth_token'   => null,
];
```

| Key | Description | Default |
|---|---|---|
| `www_root` | Folder that contains the projects | parent folder of `upweb` |
| `laragon_exe` | Path to `laragon.exe` | `../laragon.exe` |
| `flutter_root` | Flutter SDK (`FLUTTER_ROOT`) | auto-detection |
| `php_bin` / `npm_bin` / `node_bin` / `composer_phar` / `python_bin` | Binaries | auto-detection |
| `host_suffix` | Vhost suffix | `.test` |
| `hosts_file` | Hosts file used to detect vhosts | Windows hosts file |
| `allow_remote` | Allow access from the local network | `false` |
| `auth_token` | Access key | `null` (not required) |

Every key can also be set as an environment variable (`UPWEB_WWW_ROOT`,
`UPWEB_LARAGON_EXE`, `UPWEB_PHP_BIN`, `UPWEB_NPM_BIN`, `UPWEB_NODE_BIN`,
`UPWEB_COMPOSER_PHAR`, `UPWEB_PYTHON_BIN`, `UPWEB_FLUTTER_ROOT`,
`UPWEB_HOST_SUFFIX`, `UPWEB_HOSTS_FILE`, `UPWEB_ALLOW_REMOTE`,
`UPWEB_AUTH_TOKEN`). Environment variables win over `config.local.php`.

> **Security:** `api.php` only answers `127.0.0.1`/`::1`. Enabling `allow_remote`
> (or `UPWEB_ALLOW_REMOTE=1`) also requires a configured `auth_token` — without one
> remote access stays disabled. When a token is set it is required on **every**
> request, including local ones, and `index.php` asks for it on a login screen.
> The panel executes commands on your machine: never expose it to the internet.

---

## Usage

**Project card**

- **Lift** — starts the recommended servers for that architecture.
- **Stop** — stops everything in the project (only shown while something runs).
- **Star** — marks the project as favorite; favorites sort first and can be
  isolated with the *Favorites* filter in the header.
- **Open .test** / **HTTPS** — opens the Laragon vhost.
- **Folder** / **Terminal** — opens Explorer/Finder or a terminal in the project.
- **+ Cmd** — adds a custom command.

**Server row (inside the card)**

- **Start** / **Stop** — per-server control, with an editable port.
- **Log** — live process output (that's where you see the errors).

**Header**

- Service chips (Apache, MySQL, Node, …) with status.
- **Search** and **Favorites** filter.
- **Laragon:** Start / Restart / Stop.
- **Auto-open** — opens the browser when a server is ready.
- **Stop all dev** — kills every process the panel started.

---

## Detected architectures

| Detection | Runner | Port |
|---|---|---|
| `artisan` | `php artisan serve` | configurable (8000) |
| `composer.json` → `scripts.dev` | `composer run dev` | auto |
| `package.json` → `dev`/`start`/`serve` | `npm run <script>` | per framework |
| `pubspec.yaml` + `lib/main.dart` | `flutter build web && php -S` / `flutter run` | 8080 |
| `manage.py` | `python manage.py runserver` | 8000 |
| `main.py` + FastAPI | `uvicorn main:app` | 8000 |
| `app.py` + Flask | `flask run` | 5000 |
| `bin/rails` | `bin/rails server` | 3000 |
| `docker-compose.yml` | `docker compose up` | auto |
| `index.php` / `index.html` | `php -S` | 8000 |

**Custom commands** accept these tokens:

```
{php} {npm} {flutter} {python} {composer} {host} {port} {log}
```

---

## Structure

```
upweb/
├── .github/workflows/ci.yml   CI: tests on Linux, Windows and macOS + browser smoke test
├── .gitattributes             normalizes line endings to LF
├── index.php              UI shell + auth gate
├── api.php                JSON API (localhost only)
├── lib.php                detection, process/port/log handling, SSE
├── config.php             defaults + env overrides
├── config.local.php.example
├── assets/
│   ├── app.js             UI, SSE client, favorites
│   ├── style.css
│   └── favicon.svg
├── tests/
│   ├── run.php            unit/integration tests (no framework)
│   ├── auth_case.php      subprocess used by the auth tests
│   ├── browser-smoke.js   headless-Chrome end-to-end test
│   └── fixtures/          fake projects per architecture
└── storage/               runtime (ignored by git)
    ├── state.json         active PIDs and ports
    ├── custom.json        custom commands
    └── logs/              output of every server
```

---

## API

All responses: `{ "ok": true, "data": ... }` or `{ "ok": false, "error": "..." }`.

| `action` | Parameters | Description |
|---|---|---|
| `list` | — | projects + runners + status |
| `services` | — | system services |
| `up` | `project` | start the recommended runners |
| `start` | `project, id, port, host` | start one runner |
| `stop` | `project, id` | stop one runner |
| `stop-project` | `project` | stop everything in the project |
| `stop-all` | — | stop everything |
| `log` | `project, id, lines` | last lines of the log |
| `stream` | `project, id, from` | SSE stream of the log from byte offset `from` |
| `open` | `target, value` | `url` / `folder` / `terminal` |
| `laragon` | `do=start\|stop\|restart` | control Laragon (Windows) |
| `custom-add` | `project, label, command, port` | save a custom command |
| `custom-remove` | `project, customId` | delete a custom command |

`stream` emits `data`, `offset` and `eof` events, plus `reset` when the requested
offset is out of range. Pass `token` as a query parameter when `auth_token` is set
(`EventSource` cannot send headers).

---

## Testing

```bash
# Unit + integration tests (detection, ports, process trees, auth)
php tests/run.php

# End-to-end test in headless Chrome (render, favorites, API, console errors)
node tests/browser-smoke.js
```

No test framework is required — `tests/run.php` is a single self-contained script.

Both run on every push through GitHub Actions: the first job on **Linux,
Windows and macOS**, the second on Linux with headless Chrome.

Environment variables used by `tests/browser-smoke.js`:

| Variable | Purpose | Default |
|---|---|---|
| `PHP_BIN` | PHP executable used to serve the panel | Laragon PHP, then `php` from `PATH` |
| `CHROME_BIN` / `CHROME_PATH` | Chrome/Chromium executable | common install paths, then `PATH` |
| `SMOKE_PORT` / `SMOKE_CDP_PORT` | Ports for the panel and the DevTools protocol | `9114` / `9333` |

---

## Roadmap

- [ ] Light theme
- [ ] Per-project environment variable editor
- [ ] Show who occupies a port with a "kill" shortcut
- [ ] Windows-only helpers (Laragon control, vhost detection) behind a capability flag

---

## License

MIT — see [LICENSE](LICENSE).
