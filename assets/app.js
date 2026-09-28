const state = {
    projects: [],
    services: [],
    openLogs: new Set(),
    logs: {},
    filter: "",
    busy: new Set(),
    autoOpen: localStorage.getItem("upweb:autoOpen") !== "0",
    openedOnce: new Set(),
};

const PALETTE = [
    ["#5b8cff", "#7c5cff"],
    ["#3ddc84", "#1fa97a"],
    ["#ff8a5c", "#ff5c6c"],
    ["#ffb454", "#ff8a2b"],
    ["#4dd0e1", "#2f8fd8"],
    ["#c05cff", "#7c5cff"],
    ["#ff5c9d", "#c05cff"],
    ["#7ed957", "#3ddc84"],
];

const TECH_COLOR = {
    Laravel: "#ff2d55", PHP: "#777bb4", Node: "#3ddc84", Flutter: "#42a5f5",
    Django: "#092e20", Python: "#3572a5", Docker: "#2496ed", Rails: "#cc0000",
    "sin host": "#ffb454", "recarga Laragon": "#ffb454",
};

function esc(value) {
    return String(value == null ? "" : value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function colorFor(name) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
    return PALETTE[hash % PALETTE.length];
}

async function api(params) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch("api.php?" + qs, { headers: { "Accept": "application/json" } });
    let json;
    try {
        json = await res.json();
    } catch (e) {
        throw new Error("Respuesta no valida del servidor");
    }
    if (!json.ok) throw new Error(json.error || "Error desconocido");
    return json.data;
}

function toast(message, type = "ok") {
    const box = document.getElementById("toasts");
    const el = document.createElement("div");
    el.className = "toast " + type;
    el.textContent = message;
    box.appendChild(el);
    setTimeout(() => el.remove(), 4600);
}

function key(project, id) {
    return project + "::" + id;
}

function isBusy(project, id) {
    return state.busy.has(key(project, id));
}

async function run(fn, project, id, okMessage) {
    if (id) state.busy.add(key(project, id));
    render();
    try {
        await fn();
        if (okMessage) toast(okMessage, "ok");
    } catch (e) {
        toast(e.message, "err");
    } finally {
        if (id) state.busy.delete(key(project, id));
        await refresh();
    }
}

function badge(text, cls = "") {
    return `<span class="badge ${cls}">${esc(text)}</span>`;
}

function techBadge(t) {
    const color = TECH_COLOR[t] || "#5b8cff";
    return `<span class="badge tech" style="--tc:${color}">${esc(t)}</span>`;
}

function portKey(project, id) {
    return `upweb:port:${project}:${id}`;
}

function currentPort(project, server) {
    const saved = localStorage.getItem(portKey(project.name, server.id));
    if (saved !== null && saved !== "") return saved;
    return server.port != null ? String(server.port) : "";
}

function renderServer(project, server) {
    const busy = isBusy(project.name, server.id);
    const url = server.url;
    let statusHtml;
    if (server.error) {
        statusHtml = `<span class="error-line" title="${esc(server.error)}">Error: ${esc(server.error)}</span>`;
    } else if (server.running && url) {
        statusHtml = `<a href="${esc(url)}" target="_blank" rel="noopener">${esc(url)}</a>`;
    } else if (server.running) {
        statusHtml = `<span class="starting">Compilando / iniciando...</span>`;
    } else {
        statusHtml = `<span style="color:var(--muted);font-size:11.5px">Detenido</span>`;
    }

    const portHtml = server.supportsPort
        ? `<input type="number" class="port" min="1" max="65535" value="${esc(currentPort(project, server))}"
             data-project="${esc(project.name)}" data-id="${esc(server.id)}"
             title="Puerto" ${server.running ? "disabled" : ""}>`
        : `<span class="badge">puerto auto</span>`;

    const actionBtn = server.running
        ? `<button class="btn small danger" data-action="stop" data-project="${esc(project.name)}" data-id="${esc(server.id)}" ${busy ? "disabled" : ""}>${busy ? "..." : "Detener"}</button>`
        : `<button class="btn small primary" data-action="start" data-project="${esc(project.name)}" data-id="${esc(server.id)}" ${busy ? "disabled" : ""}>${busy ? "..." : "Iniciar"}</button>`;

    const logId = key(project.name, server.id);
    const logOpen = state.openLogs.has(logId);
    const logHtml = logOpen
        ? `<pre class="log">${esc(state.logs[logId] || "Sin salida todavia...")}</pre>`
        : "";

    const removeBtn = server.custom
        ? `<button class="btn small ghost danger" data-action="custom-remove" data-project="${esc(project.name)}" data-custom="${esc(server.id.replace(/^custom-/, ""))}" title="Eliminar comando">&times;</button>`
        : "";

    return `
        <div class="server" data-project="${esc(project.name)}" data-id="${esc(server.id)}">
            <div class="server-row">
                <span class="dot ${server.running ? "on" : ""} ${server.error ? "err" : ""}"></span>
                <div class="name">
                    <strong>${esc(server.label)}</strong>
                    ${statusHtml}
                </div>
                ${portHtml}
                ${actionBtn}
                <button class="btn small ghost" data-action="log" data-project="${esc(project.name)}" data-id="${esc(server.id)}">${logOpen ? "Ocultar log" : "Log"}</button>
                ${removeBtn}
            </div>
            ${logHtml}
        </div>`;
}

function renderCard(project) {
    const [c1, c2] = colorFor(project.name);
    const initial = project.name.charAt(0);
    const badges = [];
    (project.tech || []).forEach((t) => badges.push(techBadge(t)));
    if (project.hasHost) badges.push(badge(project.name + ".test", "ok"));
    else badges.push(badge("sin host - recarga Laragon", "warn"));
    if (project.isSelf) badges.push(badge("panel"));
    if (!project.servers.length) badges.push(badge("sitio servido por Laragon"));

    const anyRunning = project.servers.some((s) => s.running);
    const serversHtml = project.servers.length
        ? `<div class="servers">${project.servers.map((s) => renderServer(project, s)).join("")}</div>`
        : `<div class="no-servers">Sin dev server detectado. Usa "Abrir .test" servido por Laragon/Apache.</div>`;

    return `
        <article class="card ${project.isSelf ? "self" : ""}">
            <div class="card-head">
                <span class="avatar" style="background:linear-gradient(135deg, ${c1}, ${c2})">${esc(initial)}</span>
                <div class="card-title">
                    <h2>${esc(project.name)}</h2>
                    <div class="path">${esc(project.path)}</div>
                </div>
            </div>
            <div class="badges">${badges.join("")}</div>
            <div class="card-actions">
                <button class="btn small primary" data-action="up" data-project="${esc(project.name)}">Levantar</button>
                ${anyRunning ? `<button class="btn small danger" data-action="stop-project" data-project="${esc(project.name)}">Parar</button>` : ""}
                <button class="btn small ghost" data-action="open" data-url="${esc(project.prettyUrl)}">Abrir .test</button>
                <button class="btn small ghost" data-action="open" data-url="${esc(project.prettyUrlHttps)}">HTTPS</button>
                <button class="btn small ghost" data-action="folder" data-project="${esc(project.name)}">Carpeta</button>
                <button class="btn small ghost" data-action="terminal" data-project="${esc(project.name)}">Terminal</button>
                <button class="btn small ghost" data-action="custom-add" data-project="${esc(project.name)}" title="Agregar comando personalizado">+ Cmd</button>
            </div>
            ${serversHtml}
        </article>`;
}

function renderServices() {
    const box = document.getElementById("services");
    if (!state.services.length) { box.innerHTML = ""; return; }
    box.innerHTML = state.services.map((s) =>
        `<span class="svc ${s.running ? "on" : ""}" title="${esc(s.name)}">${esc(s.name)}</span>`
    ).join("");
}

function render() {
    const grid = document.getElementById("grid");
    const filter = state.filter.toLowerCase();
    const visible = state.projects.filter((p) => p.name.toLowerCase().includes(filter));

    grid.innerHTML = visible.map(renderCard).join("");
    document.getElementById("empty").hidden = visible.length > 0;
    renderServices();
}

function maybeAutoOpen(project, server) {
    if (!state.autoOpen) return;
    if (!server.url || !server.running) return;
    const k = key(project, server.id);
    if (state.openedOnce.has(k)) return;
    state.openedOnce.add(k);
    window.open(server.url, "_blank", "noopener");
}

async function refresh() {
    try {
        const [projects, services] = await Promise.all([
            api({ action: "list" }),
            api({ action: "services" }),
        ]);
        state.projects = projects;
        state.services = services || [];
        render();
        for (const p of state.projects) {
            for (const s of p.servers) maybeAutoOpen(p, s);
        }
        for (const logId of state.openLogs) {
            const [project, id] = logId.split("::");
            try {
                const data = await api({ action: "log", project, id, lines: 80 });
                state.logs[logId] = data.log || "Sin salida todavia...";
            } catch (e) {
                state.logs[logId] = "No se pudo leer el log: " + e.message;
            }
        }
        if (state.openLogs.size) render();
    } catch (e) {
        toast("No se pudo cargar la lista: " + e.message, "err");
    }
}

document.getElementById("grid").addEventListener("click", (event) => {
    const btn = event.target.closest("button[data-action]");
    if (!btn) return;
    const action = btn.dataset.action;
    const project = btn.dataset.project;
    const id = btn.dataset.id;

    if (action === "open") {
        window.open(btn.dataset.url, "_blank", "noopener");
        return;
    }

    if (action === "folder" || action === "terminal") {
        run(() => api({ action: "open", target: action, value: project }), project, "", null);
        return;
    }

    if (action === "start") {
        const row = btn.closest(".server");
        const portInput = row ? row.querySelector(".port") : null;
        const port = portInput ? portInput.value.trim() : "";
        const params = { action: "start", project, id };
        if (port) params.port = port;
        run(() => api(params), project, id, `Iniciando ${project} (${id})...`);
        return;
    }

    if (action === "stop") {
        run(() => api({ action: "stop", project, id }), project, id, `Detenido ${project} (${id})`);
        return;
    }

    if (action === "up") {
        state.openedOnce.clear();
        run(() => api({ action: "up", project }), project, "up", `Levantando ${project}...`);
        return;
    }

    if (action === "stop-project") {
        run(() => api({ action: "stop-project", project }), project, "sp", `Parado ${project}`);
        return;
    }

    if (action === "custom-add") {
        const label = prompt("Nombre del comando:");
        if (label === null || label.trim() === "") return;
        const command = prompt("Comando (usa {host} {port} {php} {npm} {flutter} {python} {log}):");
        if (command === null || command.trim() === "") return;
        const port = prompt("Puerto:", "8080");
        if (port === null) return;
        run(() => api({ action: "custom-add", project, label, command, port: port || "8080" }), project, "ca", `Comando agregado a ${project}`);
        return;
    }

    if (action === "custom-remove") {
        run(() => api({ action: "custom-remove", project, customId: btn.dataset.custom }), project, "cr", "Comando eliminado");
        return;
    }

    if (action === "log") {
        const logId = key(project, id);
        if (state.openLogs.has(logId)) {
            state.openLogs.delete(logId);
            render();
        } else {
            state.openLogs.add(logId);
            state.logs[logId] = "Cargando...";
            render();
            api({ action: "log", project, id, lines: 80 })
                .then((data) => { state.logs[logId] = data.log || "Sin salida todavia..."; render(); })
                .catch((e) => { state.logs[logId] = "Error: " + e.message; render(); });
        }
    }
});

document.getElementById("grid").addEventListener("input", (event) => {
    const input = event.target.closest("input.port");
    if (!input) return;
    localStorage.setItem(portKey(input.dataset.project, input.dataset.id), input.value.trim());
});

document.getElementById("search").addEventListener("input", (event) => {
    state.filter = event.target.value.trim();
    render();
});

document.querySelectorAll("[data-laragon]").forEach((btn) => {
    btn.addEventListener("click", () => {
        const action = btn.dataset.laragon;
        run(() => api({ action: "laragon", do: action }), "laragon", "", `Laragon: ${action}`);
    });
});

document.getElementById("stop-all").addEventListener("click", () => {
    run(() => api({ action: "stop-all" }), "all", "", "Dev servers detenidos");
});

document.getElementById("auto-open").addEventListener("change", (event) => {
    state.autoOpen = event.target.checked;
    localStorage.setItem("upweb:autoOpen", state.autoOpen ? "1" : "0");
});

document.getElementById("auto-open").checked = state.autoOpen;

refresh();
setInterval(refresh, 5000);
