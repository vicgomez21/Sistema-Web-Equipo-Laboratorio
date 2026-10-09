<?php
$tituloPagina = 'Roles';
$paginaActiva = 'roles';
$scriptPagina = 'roles.js';
require_once __DIR__ . '/includes/layout_inicio.php';
?>

<div class="page-header">
    <div>
        <h2>Roles</h2>
        <p class="page-header__texto">Define los perfiles que pueden tener los usuarios del sistema.</p>
    </div>
    <button class="btn btn--primario" id="botonNuevo" type="button">+ Nuevo rol</button>
</div>

<section class="card">
    <input class="buscador" id="buscador" type="search" placeholder="Buscar rol...">
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Usuarios</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tablaRoles">
                <tr><td colspan="5" class="tabla-vacia">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<dialog id="dialogoRol">
    <form id="formRol" class="dialogo">
        <div class="dialogo__cabecera">
            <h3 id="tituloDialogo">Nuevo rol</h3>
        </div>
        <div class="dialogo__cuerpo">
            <input type="hidden" id="rolId">
            <div class="form-grid">
                <div class="campo campo--completo">
                    <label for="rolNombre">Nombre *</label>
                    <input id="rolNombre" maxlength="30" required>
                </div>
                <div class="campo campo--completo">
                    <label for="rolDescripcion">Descripción</label>
                    <textarea id="rolDescripcion" maxlength="150" rows="3"></textarea>
                </div>
            </div>
            <p class="form-error" id="rolError" hidden></p>
        </div>
        <div class="dialogo__pie">
            <button class="btn btn--secundario" id="botonCancelar" type="button">Cancelar</button>
            <button class="btn btn--primario" type="submit">Guardar</button>
        </div>
    </form>
</dialog>

<?php require_once __DIR__ . '/includes/layout_fin.php'; ?>
