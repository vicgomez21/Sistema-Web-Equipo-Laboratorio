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
    let opciones = { method: metodo };
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
    $("#menuLateral").classList.add("abierto");
    $("#overlay").classList.add("visible");
    $("#botonMenu").setAttribute("aria-expanded", "true");
}

function cerrarMenu() {
    $("#menuLateral").classList.remove("abierto");
    $("#overlay").classList.remove("visible");
    $("#botonMenu").setAttribute("aria-expanded", "false");
}

function configurarMenu() {
    $("#botonMenu").addEventListener("click", () => {
        $("#menuLateral").classList.contains("abierto") ? cerrarMenu() : abrirMenu();
    });
    $("#overlay").addEventListener("click", cerrarMenu);
    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape") cerrarMenu();
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
