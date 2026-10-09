// ================= MÓDULO USUARIOS (CRUD) =================
let API_USUARIOS = "api/admin/C_Usuarios.php";
let API_ROLES = "api/admin/C_Roles.php";
let usuarios = [];
let roles = [];

// ---------- CARGAR DATOS ----------
async function cargarRoles() {
    try {
        let respuesta = await peticion(API_ROLES);
        roles = respuesta.data;
        let opciones = `<option value="">Selecciona un rol</option>`;
        for (let i = 0; i < roles.length; i++) {
            opciones += `<option value="${roles[i].id_rol}">${escapeHtml(roles[i].nombre)}</option>`;
        }
        $("#usuarioRol").innerHTML = opciones;
    } catch (error) {
        mostrarAviso("No se pudieron cargar los roles");
    }
}

async function cargarUsuarios() {
    try {
        let respuesta = await peticion(API_USUARIOS);
        usuarios = respuesta.data;
        pintarUsuarios();
    } catch (error) {
        $("#tablaUsuarios").innerHTML =
            `<tr><td colspan="7" class="tabla-vacia">No se pudieron cargar los usuarios</td></tr>`;
        mostrarAviso(error.message);
    }
}

// ---------- LISTAR ----------
function pintarUsuarios() {
    let texto = $("#buscador").value.trim().toLowerCase();
    let lista = usuarios.filter((u) =>
        `${u.codigo_usuario} ${u.nombres} ${u.apellidos} ${u.correo} ${u.rol}`.toLowerCase().includes(texto)
    );

    if (lista.length === 0) {
        $("#tablaUsuarios").innerHTML =
            `<tr><td colspan="7" class="tabla-vacia">No hay usuarios para mostrar</td></tr>`;
        return;
    }

    let filas = "";
    for (let i = 0; i < lista.length; i++) {
        let u = lista[i];
        let activo = Number(u.activo) === 1;
        filas += `
        <tr>
            <td>${escapeHtml(u.codigo_usuario)}</td>
            <td>${escapeHtml(u.nombres)} ${escapeHtml(u.apellidos)}</td>
            <td>${escapeHtml(u.correo)}</td>
            <td>${escapeHtml(u.telefono ?? "—")}</td>
            <td>${escapeHtml(u.rol)}</td>
            <td><span class="badge ${activo ? "badge--ok" : "badge--error"}">${activo ? "Activo" : "Inactivo"}</span></td>
            <td class="tabla-acciones">
                <button class="btn btn--secundario btn--sm" data-accion="editar" data-id="${u.id_usuario}">Editar</button>
                <button class="btn btn--peligro btn--sm" data-accion="eliminar" data-id="${u.id_usuario}">Eliminar</button>
            </td>
        </tr>`;
    }
    $("#tablaUsuarios").innerHTML = filas;
}

// ---------- FORMULARIO ----------
function abrirFormulario(usuario = null) {
    if (roles.length === 0) {
        mostrarAviso("Primero crea al menos un rol en el módulo Roles");
        return;
    }

    $("#formUsuario").reset();
    limpiarError("#usuarioError");
    $("#usuarioId").value = usuario ? usuario.id_usuario : "";
    $("#tituloDialogo").textContent = usuario ? "Editar usuario" : "Nuevo usuario";

    // Al crear la contraseña es obligatoria; al editar solo si se quiere cambiar
    $("#usuarioPassword").required = !usuario;
    $("#etiquetaPassword").textContent = usuario
        ? "Contraseña (déjala vacía para no cambiarla)"
        : "Contraseña *";

    if (usuario) {
        $("#usuarioCodigo").value = usuario.codigo_usuario;
        $("#usuarioRol").value = usuario.id_rol;
        $("#usuarioNombres").value = usuario.nombres;
        $("#usuarioApellidos").value = usuario.apellidos;
        $("#usuarioCorreo").value = usuario.correo;
        $("#usuarioTelefono").value = usuario.telefono ?? "";
        $("#usuarioActivo").checked = Number(usuario.activo) === 1;
    }

    $("#dialogoUsuario").showModal();
    $("#usuarioCodigo").focus();
}

// ---------- GUARDAR (POST o PUT) ----------
async function guardarUsuario(evento) {
    evento.preventDefault();
    limpiarError("#usuarioError");

    let id = $("#usuarioId").value;
    let datos = {
        codigo_usuario: $("#usuarioCodigo").value,
        nombres: $("#usuarioNombres").value,
        apellidos: $("#usuarioApellidos").value,
        correo: $("#usuarioCorreo").value,
        telefono: $("#usuarioTelefono").value,
        password: $("#usuarioPassword").value,
        id_rol: $("#usuarioRol").value,
        activo: $("#usuarioActivo").checked ? "1" : "0",
    };

    try {
        if (id) {
            await peticion(API_USUARIOS, "PUT", { id_usuario: id, ...datos });
        } else {
            await peticion(API_USUARIOS, "POST", datos);
        }
        $("#dialogoUsuario").close();
        mostrarAviso(id ? "Usuario actualizado correctamente" : "Usuario creado correctamente");
        cargarUsuarios();
    } catch (error) {
        mostrarError("#usuarioError", error.message);
    }
}

// ---------- ELIMINAR ----------
async function eliminarUsuario(id) {
    let usuario = usuarios.find((u) => u.id_usuario == id);
    if (!confirm(`¿Eliminar al usuario "${usuario.codigo_usuario}"?`)) return;

    try {
        await peticion(API_USUARIOS, "DELETE", { id_usuario: id });
        mostrarAviso("Usuario eliminado correctamente");
        cargarUsuarios();
    } catch (error) {
        mostrarAviso(error.message);
    }
}

// ---------- EVENTOS ----------
$("#botonNuevo").addEventListener("click", () => abrirFormulario());
$("#botonCancelar").addEventListener("click", () => $("#dialogoUsuario").close());
$("#formUsuario").addEventListener("submit", guardarUsuario);
$("#buscador").addEventListener("input", pintarUsuarios);

$("#tablaUsuarios").addEventListener("click", (evento) => {
    let boton = evento.target.closest("button[data-accion]");
    if (!boton) return;
    let id = boton.dataset.id;
    if (boton.dataset.accion === "editar") {
        abrirFormulario(usuarios.find((u) => u.id_usuario == id));
    } else {
        eliminarUsuario(id);
    }
});

cargarRoles();
cargarUsuarios();
