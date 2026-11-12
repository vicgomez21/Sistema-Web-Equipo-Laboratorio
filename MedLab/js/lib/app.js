// ================= UTILIDADES COMPARTIDAS (se cargan en todas las páginas) =================

// ATAJO PARA EL DOM
let $ = (selector) => document.querySelector(selector);

// Evita que el texto de la base de datos se interprete como HTML
function escapeHtml(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, (caracter) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[caracter]));
}

// PETICIÓN A LA API (GET, POST, PUT, DELETE). Lanza un Error con el mensaje del servidor si algo falla
async function peticion(url, metodo = "GET", datos = null) {
    let opciones = { method: metodo, credentials: "same-origin", headers: {} };
    if (metodo.toUpperCase() !== "GET") {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!token) throw new Error("Recarga la página para continuar.");
        opciones.headers["X-CSRF-Token"] = token;
    }
    if (datos !== null) {
        opciones.body = new URLSearchParams(datos);
    }
    let solicitud = await fetch(url, opciones);
    let respuesta;
    try {
        respuesta = await solicitud.json();
    } catch (error) {
        throw new Error("El servidor devolvió una respuesta no válida");
    }
    if (!solicitud.ok) {
        throw new Error(respuesta.message || "Ocurrió un error en la solicitud");
    }
    return respuesta;
}

// AVISO TEMPORAL (esquina inferior derecha)
let temporizadorToast;
function mostrarAviso(mensaje) {
    let toast = $("#toast");
    toast.textContent = mensaje;
    toast.classList.add("visible");
    clearTimeout(temporizadorToast);
    temporizadorToast = setTimeout(() => toast.classList.remove("visible"), 3000);
}

// MENSAJE DE ERROR DENTRO DE UN FORMULARIO (los avisos quedarían detrás del diálogo)
function mostrarError(selector, mensaje) {
    let elemento = $(selector);
    elemento.textContent = mensaje;
    elemento.hidden = false;
}
function limpiarError(selector) {
    $(selector).hidden = true;
}

// ================= MENÚ HAMBURGUESA =================
function abrirMenu() {
    document.querySelector('main').inert = true;
    document.querySelector('.topbar').inert = true;
    $("#menuLateral").classList.add("abierto");
    $("#overlay").classList.add("visible");
    $("#botonMenu").setAttribute("aria-expanded", "true");
    $("#botonMenu").setAttribute("aria-label", "Cerrar menú");
    $("#cerrarMenu").focus();
}

function cerrarMenu() {
    document.querySelector('main').inert = false;
    document.querySelector('.topbar').inert = false;
    const estabaAbierto = $("#menuLateral").classList.contains("abierto");
    $("#menuLateral").classList.remove("abierto");
    $("#overlay").classList.remove("visible");
    $("#botonMenu").setAttribute("aria-expanded", "false");
    $("#botonMenu").setAttribute("aria-label", "Abrir menú");
    if (estabaAbierto) $("#botonMenu").focus();
}

function configurarMenu() {
    $("#cerrarMenu").addEventListener("click", cerrarMenu);
    $("#botonMenu").addEventListener("click", () => {
        $("#menuLateral").classList.contains("abierto") ? cerrarMenu() : abrirMenu();
    });
    $("#overlay").addEventListener("click", cerrarMenu);
    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape") cerrarMenu();
        if (evento.key === "Tab" && $("#menuLateral").classList.contains("abierto")) {
            const focusable = [...$("#menuLateral").querySelectorAll('a[href],button,input,select,textarea,summary,[tabindex="0"]')].filter(el => el.getClientRects().length && !el.disabled && el.type !== 'hidden');
            const first=focusable[0], last=focusable[focusable.length-1];
            if(evento.shiftKey && document.activeElement===first){evento.preventDefault();last?.focus();}
            else if(!evento.shiftKey && document.activeElement===last){evento.preventDefault();first?.focus();}
        }
    });

    // Módulos que todavía no existen
    document.querySelectorAll("[data-pendiente]").forEach((enlace) => {
        enlace.addEventListener("click", (evento) => {
            evento.preventDefault();
            cerrarMenu();
            mostrarAviso(`Módulo "${enlace.dataset.pendiente}" en construcción`);
        });
    });
}

configurarMenu();

// LineSidebar: proximidad y suavizado exponencial, adaptado a enlaces PHP.
(() => {
    const nav = document.querySelector('.line-sidebar');
    if (!nav) return;
    const items = [...nav.querySelectorAll('.nav-link')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const targets = items.map(() => 0);
    const current = items.map((item) => item.matches('[aria-current="page"]') ? 1 : 0);
    let frame = null;
    let last = 0;
    function draw(now) {
        const dt = Math.min((now - last) / 1000, .05);
        last = now;
        const blend = reduced.matches ? 1 : 1 - Math.exp(-dt / .18);
        let moving = false;
        items.forEach((item, index) => {
            const selected = item.matches('[aria-current="page"], :focus-visible') ? 1 : 0;
            const target = Math.max(targets[index], selected);
            const next = current[index] + (target - current[index]) * blend;
            current[index] = Math.abs(target - next) < .0015 ? target : next;
            item.style.setProperty('--effect', current[index].toFixed(4));
            if (current[index] !== target) moving = true;
        });
        frame = moving ? requestAnimationFrame(draw) : null;
    }
    function start() {
        if (frame !== null) return;
        last = performance.now();
        frame = requestAnimationFrame(draw);
    }
    function clear() { targets.fill(0); start(); }
    nav.addEventListener('pointermove', (event) => {
        if (event.pointerType === 'touch' || reduced.matches) return;
        items.forEach((item, index) => {
            const rect = item.getBoundingClientRect();
            const p = Math.max(0, 1 - Math.abs(event.clientY - rect.top - rect.height / 2) / 145);
            targets[index] = p * p * (3 - 2 * p);
        });
        start();
    });
    nav.addEventListener('pointerleave', clear);
    nav.addEventListener('pointercancel', clear);
    nav.addEventListener('scroll', clear, { passive: true });
    nav.addEventListener('focusin', start);
    nav.addEventListener('focusout', () => requestAnimationFrame(start));
    document.querySelector('#cerrarMenu').addEventListener('click', clear);
    document.querySelector('#overlay').addEventListener('click', clear);
    reduced.addEventListener('change', clear);
    start();
})();
