const { spawn } = require("child_process");
const http = require("http");
const os = require("os");
const path = require("path");
const fs = require("fs");

const PHP = process.env.PHP_BIN || "C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe";
const CHROME = [
    "C:/Program Files/Google/Chrome/Application/chrome.exe",
    "C:/Program Files (x86)/Google/Chrome/Application/chrome.exe",
].find((p) => fs.existsSync(p));
const ROOT = path.resolve(__dirname, "..");
const PAGE = "http://127.0.0.1:9114/";
const CDP_PORT = 9333;
const USER_DATA = path.join(os.tmpdir(), "upweb-smoke-" + Date.now());

const children = [];
const errors = [];
let ws;

function get(url) {
    return new Promise((resolve, reject) => {
        http.get(url, (res) => {
            let body = "";
            res.on("data", (c) => (body += c));
            res.on("end", () => resolve(body));
        }).on("error", reject);
    });
}

function waitFor(fn, ms, label) {
    const start = Date.now();
    return (async () => {
        while (Date.now() - start < ms) {
            try {
                const v = await fn();
                if (v) return v;
            } catch (e) {}
            await new Promise((r) => setTimeout(r, 250));
        }
        throw new Error("timeout esperando: " + label);
    })();
}

function send(id, method, params) {
    return new Promise((resolve) => {
        const handler = (ev) => {
            const msg = JSON.parse(ev.data);
            if (msg.id !== id) return;
            ws.removeEventListener("message", handler);
            resolve(msg.result);
        };
        ws.addEventListener("message", handler);
        ws.send(JSON.stringify({ id, method, params: params || {} }));
    });
}

async function evaluate(expression) {
    const res = await send(1, "Runtime.evaluate", {
        expression,
        returnByValue: true,
        awaitPromise: true,
    });
    if (res.exceptionDetails) {
        throw new Error("evaluacion fallo: " + JSON.stringify(res.exceptionDetails.exception));
    }
    return res.result.value;
}

const checks = [];
function check(label, ok, detail) {
    checks.push({ label, ok: !!ok, detail });
    console.log((ok ? "  ok   " : "  FAIL ") + label + (detail ? " — " + detail : ""));
}

(async () => {
    if (!CHROME) {
        console.log("  skip: no se encontro Chrome");
        process.exit(0);
    }

    const php = spawn(PHP, ["-S", "127.0.0.1:9114", "-t", ROOT], { stdio: "ignore" });
    children.push(php);
    await waitFor(async () => {
        const b = await get(PAGE);
        return b.includes('id="grid"');
    }, 10000, "servidor PHP");

    const chrome = spawn(CHROME, [
        "--headless=new",
        "--disable-gpu",
        "--no-first-run",
        "--no-default-browser-check",
        "--remote-debugging-port=" + CDP_PORT,
        "--user-data-dir=" + USER_DATA,
        "about:blank",
    ], { stdio: "ignore" });
    children.push(chrome);

    const targets = await waitFor(async () => {
        const list = JSON.parse(await get("http://127.0.0.1:" + CDP_PORT + "/json/list"));
        return list.find((t) => t.type === "page" && t.webSocketDebuggerUrl);
    }, 15000, "CDP de Chrome");

    ws = new WebSocket(targets.webSocketDebuggerUrl);
    await new Promise((r) => ws.addEventListener("open", r, { once: true }));

    ws.addEventListener("message", (ev) => {
        const msg = JSON.parse(ev.data);
        if (msg.method === "Runtime.exceptionThrown") {
            errors.push("exception: " + (msg.params.exceptionDetails.text || "") +
                " " + (msg.params.exceptionDetails.exception?.description || ""));
        }
        if (msg.method === "Runtime.consoleAPICalled" && msg.params.type === "error") {
            errors.push("console.error: " + msg.params.args.map((a) => a.value ?? a.description).join(" "));
        }
        if (msg.method === "Log.entryAdded" && msg.params.entry.level === "error") {
            errors.push("log: " + msg.params.entry.text + (msg.params.entry.url ? " @ " + msg.params.entry.url : ""));
        }
    });

    await send(2, "Runtime.enable");
    await send(3, "Log.enable");
    await send(4, "Page.enable");
    await send(5, "Page.navigate", { url: PAGE });

    await waitFor(() => evaluate("document.querySelectorAll('#grid .card').length > 0"), 12000, "render inicial");
    await new Promise((r) => setTimeout(r, 5500));

    const total = await evaluate("document.querySelectorAll('#grid .card').length");
    check("renderiza tarjetas", total > 0, total + " tarjetas");

    const hasStar = await evaluate("!!document.querySelector('#grid .fav[data-action=fav]')");
    check("cada tarjeta tiene estrella de favorito", hasStar);

    const names = await evaluate("Array.from(document.querySelectorAll('#grid .card h2')).map(function (h) { return h.textContent; })");
    const target = names[1] || names[0];
    await evaluate(`(() => {
        const cards = Array.from(document.querySelectorAll('#grid .card'));
        const card = cards.find(function (x) { return x.querySelector('h2').textContent === ${JSON.stringify(target)}; });
        card.querySelector('.fav').click();
        return true;
    })()`);

    const favorited = await evaluate("!!document.querySelector('#grid .fav.on')");
    check("clic en estrella marca favorito", favorited);

    const reordered = await evaluate("document.querySelector('#grid .card h2').textContent");
    check("el favorito se mueve al principio", reordered === target, "primero=" + reordered + " esperado=" + target);

    const persisted = await evaluate("JSON.parse(localStorage.getItem('upweb:favorites') || '[]').length");
    check("favorito persistido en localStorage", persisted === 1, "items=" + persisted);

    await evaluate("(() => { const c = document.getElementById('fav-only'); c.checked = true; c.dispatchEvent(new Event('change')); })()");
    const favCount = await evaluate("document.querySelectorAll('#grid .card').length");
    check("filtro Favoritos deja solo 1 tarjeta", favCount === 1, "visibles=" + favCount);

    await evaluate("document.querySelector('#grid .fav').click()");
    const emptyVisible = await evaluate("!document.getElementById('empty').hidden");
    check("sin favoritos muestra mensaje vacio", emptyVisible);

    await evaluate("(() => { const c = document.getElementById('fav-only'); c.checked = false; c.dispatchEvent(new Event('change')); })()");
    const restored = await evaluate("document.querySelectorAll('#grid .card').length");
    check("desactivar filtro restaura la lista", restored === total, restored + " vs " + total);

    const apiOk = await evaluate("fetch('api.php?action=services').then(r => r.json()).then(j => j.ok)");
    check("API responde desde el navegador", apiOk === true);

    await new Promise((r) => setTimeout(r, 1500));
    check("sin errores de consola ni excepciones", errors.length === 0, errors.join(" | "));

    const failed = checks.filter((c) => !c.ok).length;
    console.log("\n" + (checks.length - failed) + "/" + checks.length + " comprobaciones OK");
    if (errors.length) console.log(errors.join("\n"));
    process.exitCode = failed > 0 || errors.length > 0 ? 1 : 0;
})()
    .catch((e) => {
        console.error("ERROR: " + e.message);
        if (errors.length) console.error(errors.join("\n"));
        process.exitCode = 1;
    })
    .finally(() => {
        try {
            if (ws) ws.close();
        } catch (e) {}
        for (const c of children) {
            try { c.kill(); } catch (e) {}
        }
        setTimeout(() => {
            try { fs.rmSync(USER_DATA, { recursive: true, force: true }); } catch (e) {}
            process.exit(process.exitCode || 0);
        }, 1200);
    });
