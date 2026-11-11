// ================= MÓDULO EQUIPOS (CRUD) =================
const API_EQUIPOS = "api/admin/C_Equipos.php";
let equipos = [];
let catalogosEquipos = { marcas: [], categorias: [], estados: [], condiciones: [] };

async function cargarEquipos() {
    try {
        const respuesta = await peticion(API_EQUIPOS);
        equipos = respuesta.data;
        catalogosEquipos = respuesta.catalogos || catalogosEquipos;
        cargarCatalogos(catalogosEquipos);
        pintarEquipos();
    } catch (error) {
        $("#tablaEquipos").innerHTML =
            '<tr><td colspan="7" class="tabla-vacia">No se pudieron cargar los equipos</td></tr>';
        mostrarAviso(error.message);
    }
}

function cargarCatalogos(catalogos) {
    $("#listaMarcas").replaceChildren(...(catalogos.marcas || []).map((valor) => {
        const opcion = document.createElement("option");
        opcion.value = valor;
        return opcion;
    }));
    cargarSelect("#equipoCategoria", "Selecciona una categoría", catalogos.categorias || [], "id_categoria", true);
    cargarSelect("#equipoEstado", "Selecciona un estado", catalogos.estados || [], "id_estado");
    cargarSelect("#equipoCondicion", "Selecciona una condición", catalogos.condiciones || [], "id_condicion");
}

function cargarSelect(selector, etiqueta, registros, campoId, tieneActivo = false) {
    const select = $(selector);
    select.replaceChildren();
    const inicial = document.createElement("option");
    inicial.value = "";
    inicial.textContent = etiqueta;
    select.append(inicial);
    for (const registro of registros) {
        const opcion = document.createElement("option");
        opcion.value = registro[campoId];
        opcion.textContent = registro.nombre + (tieneActivo && Number(registro.activo) !== 1 ? " (inactiva)" : "");
        opcion.disabled = tieneActivo && Number(registro.activo) !== 1;
        select.append(opcion);
    }
}

function pintarEquipos() {
    const texto = $("#buscador").value.trim().toLowerCase();
    const lista = equipos.filter((equipo) =>
        (equipo.codigo_inventario + " " + equipo.nombre + " " + (equipo.marca || "") + " " +
         (equipo.modelo || "") + " " + equipo.categoria + " " + equipo.estado + " " +
         equipo.condicion + " " + (equipo.ubicacion || "")).toLowerCase().includes(texto)
    );

    if (lista.length === 0) {
        $("#tablaEquipos").innerHTML =
            '<tr><td colspan="7" class="tabla-vacia">No hay equipos para mostrar</td></tr>';
        return;
    }

    let filas = "";
    for (const equipo of lista) {
        const activo = Number(equipo.activo) === 1;
        filas += '<tr>' +
            '<td>' + escapeHtml(equipo.codigo_inventario) + '</td>' +
            '<td><strong>' + escapeHtml(equipo.nombre) + '</strong><br><small>' +
                escapeHtml(equipo.numero_serie || "Sin serie") + '</small></td>' +
            '<td>' + escapeHtml(equipo.marca || "—") + '<br><small>' +
                escapeHtml(equipo.modelo || "—") + '</small></td>' +
            '<td>' + escapeHtml(equipo.categoria) + '<br><small>' +
                escapeHtml(equipo.estado) + ' · ' + escapeHtml(equipo.condicion) + '</small></td>' +
            '<td>' + escapeHtml(equipo.ubicacion || "—") + '</td>' +
            '<td><span class="badge ' + (activo ? "badge--ok" : "badge--error") + '">' +
                (activo ? "Activo" : "Inactivo") + '</span></td>' +
            '<td class="tabla-acciones">' +
                '<button class="btn btn--secundario btn--sm" data-accion="editar" data-id="' +
                    equipo.id_equipo + '">Editar</button>' +
                '<button class="btn btn--peligro btn--sm" data-accion="eliminar" data-id="' +
                    equipo.id_equipo + '">Eliminar</button>' +
            '</td></tr>';
    }
    $("#tablaEquipos").innerHTML = filas;
}

function abrirFormulario(equipo = null) {
    if (!equipo && (!catalogosEquipos.categorias.length || !catalogosEquipos.estados.length ||
        !catalogosEquipos.condiciones.length)) {
        mostrarAviso("Crea primero al menos una categoría, un estado y una condición");
        return;
    }
    $("#formEquipo").reset();
    limpiarError("#equipoError");
    $("#equipoId").value = equipo ? equipo.id_equipo : "";
    $("#tituloDialogo").textContent = equipo ? "Editar equipo" : "Nuevo equipo";
    $("#equipoActivo").checked = equipo ? Number(equipo.activo) === 1 : true;
    if (equipo) {
        $("#equipoCodigo").value = equipo.codigo_inventario;
        $("#equipoNombre").value = equipo.nombre;
        $("#equipoMarca").value = equipo.marca || "";
        $("#equipoModelo").value = equipo.modelo || "";
        $("#equipoSerie").value = equipo.numero_serie || "";
        $("#equipoCategoria").value = equipo.id_categoria;
        $("#equipoEstado").value = equipo.id_estado;
        $("#equipoCondicion").value = equipo.id_condicion;
        $("#equipoUbicacion").value = equipo.ubicacion || "";
        $("#equipoFecha").value = equipo.fecha_adquisicion || "";
        $("#equipoDescripcion").value = equipo.descripcion || "";
    }
    $("#dialogoEquipo").showModal();
    $("#equipoCodigo").focus();
}

async function guardarEquipo(evento) {
    evento.preventDefault();
    limpiarError("#equipoError");
    const id = $("#equipoId").value;
    const datos = {
        codigo_inventario: $("#equipoCodigo").value,
        nombre: $("#equipoNombre").value,
        marca: $("#equipoMarca").value,
        modelo: $("#equipoModelo").value,
        numero_serie: $("#equipoSerie").value,
        id_categoria: $("#equipoCategoria").value,
        id_estado: $("#equipoEstado").value,
        id_condicion: $("#equipoCondicion").value,
        ubicacion: $("#equipoUbicacion").value,
        fecha_adquisicion: $("#equipoFecha").value,
        descripcion: $("#equipoDescripcion").value,
        activo: $("#equipoActivo").checked ? "1" : "0",
    };
    try {
        if (id) await peticion(API_EQUIPOS, "PUT", { id_equipo: id, ...datos });
        else await peticion(API_EQUIPOS, "POST", datos);
        $("#dialogoEquipo").close();
        mostrarAviso(id ? "Equipo actualizado correctamente" : "Equipo creado correctamente");
        await cargarEquipos();
    } catch (error) {
        mostrarError("#equipoError", error.message);
    }
}

async function eliminarEquipo(id) {
    const equipo = equipos.find((item) => item.id_equipo == id);
    if (!equipo || !confirm('¿Eliminar el equipo "' + equipo.codigo_inventario + ' · ' + equipo.nombre + '"?')) return;
    try {
        await peticion(API_EQUIPOS, "DELETE", { id_equipo: id });
        mostrarAviso("Equipo eliminado correctamente");
        await cargarEquipos();
    } catch (error) {
        mostrarAviso(error.message);
    }
}

$("#botonNuevo").addEventListener("click", () => abrirFormulario());
$("#botonCancelar").addEventListener("click", () => $("#dialogoEquipo").close());
$("#formEquipo").addEventListener("submit", guardarEquipo);
$("#buscador").addEventListener("input", pintarEquipos);
$("#tablaEquipos").addEventListener("click", (evento) => {
    const boton = evento.target.closest("button[data-accion]");
    if (!boton) return;
    if (boton.dataset.accion === "editar") {
        abrirFormulario(equipos.find((item) => item.id_equipo == boton.dataset.id));
    } else {
        eliminarEquipo(boton.dataset.id);
    }
});

cargarEquipos();
