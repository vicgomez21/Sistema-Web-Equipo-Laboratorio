// CRUD compartido por categorías, estados y condiciones.
const configuracionCatalogo = document.querySelector("#crudCatalogo").dataset;
const API_CATALOGO = configuracionCatalogo.api;
const ENTIDAD_CATALOGO = configuracionCatalogo.entidad;
const CAMPO_ID_CATALOGO = configuracionCatalogo.idCampo;
const TIENE_ACTIVO = configuracionCatalogo.tieneActivo === "1";
const OPCIONES_CATALOGO = JSON.parse(document.querySelector("#catalogoOpciones").textContent);
let registrosCatalogo = [];

function completarDescripcionCatalogo() {
    const seleccion = OPCIONES_CATALOGO.find((opcion) => opcion.nombre === $("#catalogoNombre").value);
    $("#catalogoDescripcion").value = seleccion ? seleccion.descripcion : "";
}

async function cargarCatalogo() {
    try {
        const respuesta = await peticion(API_CATALOGO);
        registrosCatalogo = respuesta.data;
        pintarCatalogo();
    } catch (error) {
        $("#tablaCatalogo").innerHTML = '<tr><td colspan="' + (TIENE_ACTIVO ? 6 : 5) +
            '" class="tabla-vacia">No se pudo cargar el catálogo</td></tr>';
        mostrarAviso(error.message);
    }
}

function pintarCatalogo() {
    const texto = $("#buscador").value.trim().toLowerCase();
    const lista = registrosCatalogo.filter((registro) =>
        (registro.nombre + " " + (registro.descripcion || "")).toLowerCase().includes(texto)
    );
    if (lista.length === 0) {
        $("#tablaCatalogo").innerHTML = '<tr><td colspan="' + (TIENE_ACTIVO ? 6 : 5) +
            '" class="tabla-vacia">No hay registros para mostrar</td></tr>';
        return;
    }
    let filas = "";
    for (const registro of lista) {
        const activa = Number(registro.activo) === 1;
        filas += '<tr><td>' + escapeHtml(registro[CAMPO_ID_CATALOGO]) + '</td>' +
            '<td>' + escapeHtml(registro.nombre) + '</td>' +
            '<td>' + escapeHtml(registro.descripcion || "—") + '</td>' +
            '<td>' + escapeHtml(registro.total_equipos) + '</td>';
        if (TIENE_ACTIVO) {
            filas += '<td><span class="badge ' + (activa ? "badge--ok" : "badge--error") + '">' +
                (activa ? "Activa" : "Inactiva") + '</span></td>';
        }
        filas += '<td class="tabla-acciones">' +
            '<button class="btn btn--secundario btn--sm" data-accion="editar" data-id="' +
                registro[CAMPO_ID_CATALOGO] + '">Editar</button>' +
            '<button class="btn btn--peligro btn--sm" data-accion="eliminar" data-id="' +
                registro[CAMPO_ID_CATALOGO] + '">Eliminar</button></td></tr>';
    }
    $("#tablaCatalogo").innerHTML = filas;
}

function abrirFormularioCatalogo(registro = null) {
    $("#formCatalogo").reset();
    limpiarError("#catalogoError");
    $("#catalogoId").value = registro ? registro[CAMPO_ID_CATALOGO] : "";
    $("#tituloDialogo").textContent = registro ? "Editar " + ENTIDAD_CATALOGO : "Nuevo " + ENTIDAD_CATALOGO;
    if (registro) {
        if (![...$("#catalogoNombre").options].some((opcion) => opcion.value === registro.nombre)) {
            const opcionAnterior = document.createElement("option");
            opcionAnterior.value = registro.nombre;
            opcionAnterior.textContent = registro.nombre + " (registro anterior)";
            $("#catalogoNombre").append(opcionAnterior);
        }
        $("#catalogoNombre").value = registro.nombre;
        $("#catalogoDescripcion").value = registro.descripcion || "";
    } else {
        $("#catalogoDescripcion").value = "";
    }
    if (TIENE_ACTIVO) {
        $("#catalogoActivo").checked = registro ? Number(registro.activo) === 1 : true;
    }
    $("#dialogoCatalogo").showModal();
    $("#catalogoNombre").focus();
}

async function guardarCatalogo(evento) {
    evento.preventDefault();
    limpiarError("#catalogoError");
    const id = $("#catalogoId").value;
    const datos = {
        nombre: $("#catalogoNombre").value,
        descripcion: $("#catalogoDescripcion").value,
    };
    if (TIENE_ACTIVO) datos.activo = $("#catalogoActivo").checked ? "1" : "0";
    try {
        if (id) await peticion(API_CATALOGO, "PUT", { [CAMPO_ID_CATALOGO]: id, ...datos });
        else await peticion(API_CATALOGO, "POST", datos);
        $("#dialogoCatalogo").close();
        mostrarAviso(id ? "Registro actualizado correctamente" : "Registro creado correctamente");
        await cargarCatalogo();
    } catch (error) {
        mostrarError("#catalogoError", error.message);
    }
}

async function eliminarCatalogo(id) {
    const registro = registrosCatalogo.find((item) => item[CAMPO_ID_CATALOGO] == id);
    if (!registro || !confirm('¿Eliminar "' + registro.nombre + '"?')) return;
    try {
        await peticion(API_CATALOGO, "DELETE", { [CAMPO_ID_CATALOGO]: id });
        mostrarAviso("Registro eliminado correctamente");
        await cargarCatalogo();
    } catch (error) {
        mostrarAviso(error.message);
    }
}

$("#botonNuevo").addEventListener("click", () => abrirFormularioCatalogo());
$("#botonCancelar").addEventListener("click", () => $("#dialogoCatalogo").close());
$("#formCatalogo").addEventListener("submit", guardarCatalogo);
$("#catalogoNombre").addEventListener("change", completarDescripcionCatalogo);
$("#buscador").addEventListener("input", pintarCatalogo);
$("#tablaCatalogo").addEventListener("click", (evento) => {
    const boton = evento.target.closest("button[data-accion]");
    if (!boton) return;
    if (boton.dataset.accion === "editar") {
        abrirFormularioCatalogo(registrosCatalogo.find(
            (item) => item[CAMPO_ID_CATALOGO] == boton.dataset.id
        ));
    } else {
        eliminarCatalogo(boton.dataset.id);
    }
});

cargarCatalogo();
