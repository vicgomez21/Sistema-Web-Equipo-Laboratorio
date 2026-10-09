<?php
$tituloPagina = 'Usuarios';
$paginaActiva = 'usuarios';
$scriptPagina = 'usuarios.js';
require_once __DIR__ . '/includes/layout_inicio.php';
?>

<div class="page-header">
    <div>
        <h2>Usuarios</h2>
        <p class="page-header__texto">Administra las personas que usan el laboratorio. Crea primero los roles.</p>
    </div>
    <button class="btn btn--primario" id="botonNuevo" type="button">+ Nuevo usuario</button>
</div>

<section class="card">
    <input class="buscador" id="buscador" type="search" placeholder="Buscar por código, nombre, correo o rol...">
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tablaUsuarios">
                <tr><td colspan="7" class="tabla-vacia">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<dialog id="dialogoUsuario">
    <form id="formUsuario" class="dialogo" autocomplete="off">
        <div class="dialogo__cabecera">
            <h3 id="tituloDialogo">Nuevo usuario</h3>
        </div>
        <div class="dialogo__cuerpo">
            <input type="hidden" id="usuarioId">
            <div class="form-grid">
                <div class="campo">
                    <label for="usuarioCodigo">Código de usuario *</label>
                    <input id="usuarioCodigo" maxlength="30" required>
                </div>
                <div class="campo">
                    <label for="usuarioRol">Rol *</label>
                    <select id="usuarioRol" required></select>
                </div>
                <div class="campo">
                    <label for="usuarioNombres">Nombres *</label>
                    <input id="usuarioNombres" maxlength="60" required>
                </div>
                <div class="campo">
                    <label for="usuarioApellidos">Apellidos *</label>
                    <input id="usuarioApellidos" maxlength="60" required>
                </div>
                <div class="campo">
                    <label for="usuarioCorreo">Correo *</label>
                    <input id="usuarioCorreo" type="email" maxlength="150" required>
                </div>
                <div class="campo">
                    <label for="usuarioTelefono">Teléfono</label>
                    <input id="usuarioTelefono" maxlength="20">
                </div>
                <div class="campo campo--completo">
                    <label for="usuarioPassword" id="etiquetaPassword">Contraseña *</label>
                    <input id="usuarioPassword" type="password" minlength="8" autocomplete="new-password">
                </div>
                <div class="campo campo--check campo--completo">
                    <input id="usuarioActivo" type="checkbox" checked>
                    <label for="usuarioActivo">Usuario activo</label>
                </div>
            </div>
            <p class="form-error" id="usuarioError" hidden></p>
        </div>
        <div class="dialogo__pie">
            <button class="btn btn--secundario" id="botonCancelar" type="button">Cancelar</button>
            <button class="btn btn--primario" type="submit">Guardar</button>
        </div>
    </form>
</dialog>

<?php require_once __DIR__ . '/includes/layout_fin.php'; ?>
