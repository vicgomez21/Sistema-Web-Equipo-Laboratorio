<?php
require_once __DIR__ . '/api/core/Auth.php';
Auth::proteger(false, false);
$tituloPagina = 'Préstamos';
$paginaActiva = 'prestamos';
$scriptPagina = 'prestamos.js';
$estiloPagina = 'prestamos.css';
require_once __DIR__ . '/includes/layout_inicio.php';
?>
<div class="prestamos-page" id="prestamosApp" data-administrador="<?= Auth::esAdministrador() ? '1' : '0' ?>" data-usuario="<?= (int)$_SESSION['usuario']['id_usuario'] ?>">
    <header class="page-header">
        <div><h1>Préstamos</h1><p class="page-header__texto"><?= Auth::esAdministrador() ? 'Revisa solicitudes, registra entregas y recibe las devoluciones.' : 'Solicita equipos y consulta el estado de tus préstamos.' ?></p></div>
        <button class="btn btn--primario" type="button" id="solicitarPrestamo">Solicitar préstamo</button>
    </header>
    <p class="form-error" id="prestamosError" role="alert" tabindex="-1" hidden></p>
    <section class="card" aria-labelledby="listaPrestamosTitulo">
        <div class="loan-toolbar"><h2 id="listaPrestamosTitulo"><?= Auth::esAdministrador() ? 'Solicitudes y préstamos' : 'Mis solicitudes y préstamos' ?></h2><button class="btn btn--secundario" type="button" id="refrescarPrestamos">Actualizar</button></div>
        <div class="loan-filters">
            <div class="campo"><label for="buscarPrestamo">Buscar por número, solicitante o equipo</label><input type="search" id="buscarPrestamo" autocomplete="off"></div>
            <div class="campo"><label for="filtrarEstadoPrestamo">Estado</label><select id="filtrarEstadoPrestamo"><option value="">Todos los estados</option><option value="PENDIENTE">Pendiente</option><option value="APROBADO">Aprobado</option><option value="ENTREGADO">Entregado</option><option value="DEVUELTO">Devuelto</option><option value="RECHAZADO">Rechazado</option><option value="CANCELADO">Cancelado</option><option value="RESERVA_VENCIDA">Reserva vencida</option></select></div>
        </div>
        <p class="loan-status" id="prestamosConteo" role="status">Cargando préstamos…</p>
        <div class="tabla-contenedor" role="region" aria-label="Solicitudes y préstamos; desplázate para ver todas las columnas" tabindex="0"><table class="tabla loan-table"><caption class="loan-visually-hidden">Estado y acciones disponibles de los préstamos</caption><thead><tr><th scope="col">Solicitud</th><th scope="col">Solicitante</th><th scope="col">Equipos y fechas</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody id="tablaPrestamos"><tr><td colspan="5" class="tabla-vacia">Cargando…</td></tr></tbody></table></div>
    </section>
    <details class="card loan-notifications" id="panelNotificaciones"><summary>Notificaciones <span id="notificacionesConteo" role="status"></span></summary><p class="form-error" id="notificacionesError" role="alert" hidden></p><ul id="listaNotificaciones"><li>Cargando notificaciones…</li></ul></details>
</div>
<dialog id="dialogoSolicitud" class="loan-dialog" aria-labelledby="tituloSolicitud" aria-describedby="ayudaSolicitud">
    <form id="formSolicitud" class="dialogo">
        <div class="dialogo__cabecera"><h2 id="tituloSolicitud">Solicitar préstamo</h2></div>
        <div class="dialogo__cuerpo">
            <p id="ayudaSolicitud">Selecciona los equipos y las cantidades que necesitas. La solicitud requiere aprobación; aún no representa una entrega.</p>
            <div id="filasSolicitud" class="loan-request-lines"></div>
            <button class="btn btn--secundario" type="button" id="agregarEquipoSolicitud">Añadir equipo</button>
            <div class="campo"><label for="fechaSolicitada">Fecha de devolución solicitada</label><input type="date" id="fechaSolicitada" required></div>
            <div class="campo"><label for="observacionesSolicitud">Observaciones (opcional)</label><textarea id="observacionesSolicitud" rows="3" maxlength="500"></textarea></div>
            <p class="form-error" id="solicitudError" role="alert" tabindex="-1" hidden></p>
        </div>
        <div class="dialogo__pie"><button class="btn btn--secundario" type="button" data-cerrar-dialogo="dialogoSolicitud">Cancelar</button><button class="btn btn--primario" type="submit">Enviar solicitud</button></div>
    </form>
</dialog>
<dialog id="dialogoAccionPrestamo" class="loan-dialog" aria-labelledby="tituloAccionPrestamo">
    <form id="formAccionPrestamo" class="dialogo">
        <div class="dialogo__cabecera"><h2 id="tituloAccionPrestamo"></h2></div>
        <div class="dialogo__cuerpo"><div id="camposAccionPrestamo"></div><p class="form-error" id="accionPrestamoError" role="alert" tabindex="-1" hidden></p></div>
        <div class="dialogo__pie"><button class="btn btn--secundario" type="button" data-cerrar-dialogo="dialogoAccionPrestamo">Volver</button><button class="btn btn--primario" type="submit" id="confirmarAccionPrestamo">Confirmar</button></div>
    </form>
</dialog>
<?php require_once __DIR__ . '/includes/layout_fin.php'; ?>
