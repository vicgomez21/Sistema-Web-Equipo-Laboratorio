// ================= MÓDULO ROLES (CRUD) =================
let API_ROLES = "api/admin/C_Roles.php";
let roles = [];

// ---------- LISTAR ----------
async function cargarRoles() {
    try {
        let respuesta = await peticion(API_ROLES);
        roles = respuesta.data;
        pintarRoles();
    } catch (error) {
        $("#tablaRoles").innerHTML =
            `<tr><td colspan="5" class="tabla-vacia">No se pudieron cargar los roles</td></tr>`;
        mostrarAviso(error.message);
    }
}

function pintarRoles() {
    let texto = $("#buscador").value.trim().toLowerCase();
    let lista = roles.filter((rol) =>
        `${rol.nombre} ${rol.descripcion ?? ""}`.toLowerCase().includes(texto)
    );

    if (lista.length === 0) {
        $("#tablaRoles").innerHTML =
            `<tr><td colspan="5" class="tabla-vacia">No hay roles para mostrar</td></tr>`;
        return;
    }

    let filas = "";
    for (let i = 0; i < lista.length; i++) {
        let rol = lista[i];
        filas += `
        <tr>
            <td>${escapeHtml(rol.id_rol)}</td>
            <td>${escapeHtml(rol.nombre)}</td>
            <td>${escapeHtml(rol.descripcion ?? "—")}</td>
            <td>${escapeHtml(rol.total_usuarios)}</td>
            <td class="tabla-acciones">
                <button class="btn btn--secundario btn--sm" data-accion="editar" data-id="${rol.id_rol}">Editar</button>
                <button class="btn btn--peligro btn--sm" data-accion="eliminar" data-id="${rol.id_rol}">Eliminar</button>
            </td>
        </tr>`;
    }
    $("#tablaRoles").innerHTML = filas;
}

// ---------- FORMULARIO ----------
function abrirFormulario(rol = null) {
    $("#formRol").reset();
    limpiarError("#rolError");
    $("#rolId").value = rol ? rol.id_rol : "";
    $("#tituloDialogo").textContent = rol ? "Editar rol" : "Nuevo rol";
    if (rol) {
        $("#rolNombre").value = rol.nombre;
        $("#rolDescripcion").value = rol.descripcion ?? "";
    }
    $("#dialogoRol").showModal();
    $("#rolNombre").focus();
}

// ---------- GUARDAR (POST o PUT) ----------
async function guardarRol(evento) {
    evento.preventDefault();
    limpiarError("#rolError");

    let id = $("#rolId").value;
    let datos = {
        nombre: $("#rolNombre").value,
        descripcion: $("#rolDescripcion").value,
    };

    try {
        if (id) {
            await peticion(API_ROLES, "PUT", { id_rol: id, ...datos });
        } else {
            await peticion(API_ROLES, "POST", datos);
        }
        $("#dialogoRol").close();
        mostrarAviso(id ? "Rol actualizado correctamente" : "Rol creado correctamente");
        cargarRoles();
    } catch (error) {
        mostrarError("#rolError", error.message);
    }
}

// ---------- ELIMINAR ----------
async function eliminarRol(id) {
    let rol = roles.find((r) => r.id_rol == id);
    if (!confirm(`¿Eliminar el rol "${rol.nombre}"?`)) return;

    try {
        await peticion(API_ROLES, "DELETE", { id_rol: id });
        mostrarAviso("Rol eliminado correctamente");
        cargarRoles();
    } catch (error) {
        mostrarAviso(error.message);
    }
}

// ---------- EVENTOS ----------
$("#botonNuevo").addEventListener("click", () => abrirFormulario());
$("#botonCancelar").addEventListener("click", () => $("#dialogoRol").close());
$("#formRol").addEventListener("submit", guardarRol);
$("#buscador").addEventListener("input", pintarRoles);

$("#tablaRoles").addEventListener("click", (evento) => {
    let boton = evento.target.closest("button[data-accion]");
    if (!boton) return;
    let id = boton.dataset.id;
    if (boton.dataset.accion === "editar") {
        abrirFormulario(roles.find((r) => r.id_rol == id));
    } else {
        eliminarRol(id);
    }
});

cargarRoles();
