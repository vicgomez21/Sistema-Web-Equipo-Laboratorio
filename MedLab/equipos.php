<?php
require_once __DIR__ . '/api/core/Auth.php';
Auth::administrador();
$tituloPagina = 'Equipos';
$paginaActiva = 'equipos';
$scriptPagina = 'equipos.js';
require_once __DIR__ . '/includes/layout_inicio.php';
?>

<div class="page-header">
    <div>
        <h2>Equipos</h2>
        <p class="page-header__texto">Administra el inventario y relaciona cada equipo con su marca y clasificación.</p>
    </div>
    <button class="btn btn--primario" id="botonNuevo" type="button">+ Nuevo equipo</button>
</div>

<section class="card">
    <input class="buscador" id="buscador" type="search"
           placeholder="Buscar por código, equipo, marca, categoría o ubicación...">
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Equipo</th><th>Marca / modelo</th><th>Clasificación</th><th>Ubicación</th><th>Estado</th><th></th></tr></thead>
            <tbody id="tablaEquipos"><tr><td colspan="7" class="tabla-vacia">Cargando...</td></tr></tbody>
        </table>
    </div>
</section>

<dialog id="dialogoEquipo">
    <form id="formEquipo" class="dialogo" autocomplete="off">
        <div class="dialogo__cabecera"><h3 id="tituloDialogo">Nuevo equipo</h3></div>
        <div class="dialogo__cuerpo">
            <input type="hidden" id="equipoId">
            <div class="form-grid">
                <div class="campo"><label for="equipoCodigo">Código de inventario *</label><input id="equipoCodigo" maxlength="30" required></div>
                <div class="campo"><label for="equipoNombre">Nombre *</label><input id="equipoNombre" maxlength="100" required></div>
                <div class="campo"><label for="equipoMarca">Marca</label><input id="equipoMarca" maxlength="50" list="listaMarcas" placeholder="Ej. Thermo Fisher"><datalist id="listaMarcas"></datalist></div>
                <div class="campo"><label for="equipoModelo">Modelo</label><input id="equipoModelo" maxlength="50"></div>
                <div class="campo"><label for="equipoSerie">Número de serie</label><input id="equipoSerie" maxlength="80"></div>
                <div class="campo"><label for="equipoCategoria">Categoría *</label><select id="equipoCategoria" required></select></div>
                <div class="campo"><label for="equipoEstado">Estado *</label><select id="equipoEstado" required></select></div>
                <div class="campo"><label for="equipoCondicion">Condición *</label><select id="equipoCondicion" required></select></div>
                <div class="campo"><label for="equipoUbicacion">Ubicación</label><input id="equipoUbicacion" maxlength="100"></div>
                <div class="campo"><label for="equipoFecha">Fecha de adquisición</label><input id="equipoFecha" type="date"></div>
                <div class="campo campo--completo"><label for="equipoDescripcion">Descripción</label><textarea id="equipoDescripcion" maxlength="1000" rows="3"></textarea></div>
                <div class="campo campo--check campo--completo"><input id="equipoActivo" type="checkbox" checked><label for="equipoActivo">Equipo activo</label></div>
            </div>
            <p class="form-help">Crea primero la categoría, el estado y la condición. Si escribes una marca nueva, se incorporará al guardar el equipo.</p>
            <p class="form-error" id="equipoError" hidden></p>
        </div>
        <div class="dialogo__pie">
            <button class="btn btn--secundario" id="botonCancelar" type="button">Cancelar</button>
            <button class="btn btn--primario" type="submit">Guardar</button>
        </div>
    </form>
</dialog>

<?php require_once __DIR__ . '/includes/layout_fin.php'; ?>
