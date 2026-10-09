<?php
$tituloPagina = 'Inicio';
$paginaActiva = 'inicio';
$scriptPagina = 'inicio.js';
require_once __DIR__ . '/includes/layout_inicio.php';
?>

<section class="card">
    <h2>Bienvenido</h2>
    <p>Abre el menú ☰ para navegar. Los módulos marcados como "Pronto" se irán habilitando en las siguientes etapas.</p>
    <div class="accesos">
        <a class="btn btn--primario" href="roles.php">Gestionar roles</a>
        <a class="btn btn--secundario" href="usuarios.php">Gestionar usuarios</a>
    </div>
</section>

<section class="card">
    <div class="card__header">
        <h2>Estado de la conexión con MySQL</h2>
        <span class="badge badge--wait" id="estadoBadge">Verificando...</span>
    </div>
    <p id="estadoMensaje">Consultando al servidor...</p>

    <dl class="info" id="estadoInfo" hidden>
        <div><dt>Base de datos</dt><dd id="infoBase">-</dd></div>
        <div><dt>Versión de MySQL</dt><dd id="infoVersion">-</dd></div>
        <div><dt>Tablas encontradas</dt><dd id="infoTablas">-</dd></div>
    </dl>

    <ul class="tables" id="listaTablas"></ul>
</section>

<?php require_once __DIR__ . '/includes/layout_fin.php'; ?>
