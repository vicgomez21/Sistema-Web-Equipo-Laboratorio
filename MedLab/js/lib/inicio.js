// ================= PÁGINA DE INICIO: prueba de conexión a MySQL =================
let API_CONEXION = "api/admin/C_Conexion.php";

function marcarEstado(clase, texto) {
    let badge = $("#estadoBadge");
    badge.className = `badge badge--${clase}`;
    badge.textContent = texto;
}

async function verificarConexion() {
    try {
        let respuesta = await peticion(API_CONEXION);
        let datos = respuesta.data;

        marcarEstado("ok", "Conectado");
        $("#estadoMensaje").textContent = respuesta.message;
        $("#infoBase").textContent = datos.base_datos;
        $("#infoVersion").textContent = datos.version_mysql;
        $("#infoTablas").textContent = `${datos.total_existentes} de ${datos.total_esperadas}`;
        $("#estadoInfo").hidden = false;

        let filas = "";
        for (let i = 0; i < datos.tablas.length; i++) {
            let tabla = datos.tablas[i];
            filas += `
            <li>
                <span>${escapeHtml(tabla.nombre)}</span>
                <span class="${tabla.existe ? "ok" : "falta"}">${tabla.existe ? "✔" : "✘ falta"}</span>
            </li>`;
        }
        $("#listaTablas").innerHTML = filas;
    } catch (error) {
        marcarEstado("error", "Sin conexión");
        $("#estadoMensaje").textContent =
            "No se pudo conectar con MedLabDB. Revisa que MySQL esté iniciado en XAMPP y que api/core/.env sea correcto.";
    }
}

verificarConexion();
