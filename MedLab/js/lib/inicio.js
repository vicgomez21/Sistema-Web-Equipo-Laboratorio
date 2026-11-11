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

if (document.querySelector('#estadoBadge')) verificarConexion();

// Exploración de categorías: independiente de la conexión a la base de datos.
const tarjetasCategoria = [...document.querySelectorAll('[data-category]')];
document.querySelector('#buscarCategoria').addEventListener('input', (evento) => {
    const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const consulta = normalizar(evento.target.value.trim());
    let visibles = 0;
    tarjetasCategoria.forEach((tarjeta) => {
        tarjeta.hidden = !normalizar(tarjeta.dataset.category + ' ' + tarjeta.dataset.description).includes(consulta);
        if (!tarjeta.hidden) visibles++;
    });
    document.querySelector('#catalogCount').textContent = `${visibles} ${visibles === 1 ? 'categoría' : 'categorías'}`;
    document.querySelector('#catalogEmpty').hidden = visibles !== 0;
});
tarjetasCategoria.forEach((tarjeta) => tarjeta.addEventListener('click', () => {
    document.querySelector('#categoriaTitulo').textContent = tarjeta.dataset.category;
    document.querySelector('#categoriaDescripcion').textContent = tarjeta.dataset.description;
    document.querySelector('#detalleCategoria').showModal();
}));
