<?php
require_once __DIR__.'/api/core/Auth.php';Auth::proteger();
$tituloPagina='Inicio';$paginaActiva='inicio';$scriptPagina='inicio.js';require_once __DIR__.'/includes/layout_inicio.php';
?>
<header class="home-heading"><h1><?= Auth::esAdministrador()?'Administración del laboratorio':'Mi laboratorio' ?></h1><p><?= Auth::esAdministrador()?'Gestiona el inventario, revisa solicitudes y registra los movimientos de los instrumentos.':'Consulta los instrumentos disponibles y sigue tus solicitudes de préstamo.' ?></p></header>
<section class="home-management" aria-labelledby="tareasTitle"><h2 id="tareasTitle">Tareas del laboratorio</h2><div class="management-links">
<a href="equipos.php"><strong>Consultar inventario</strong><span>Existencias disponibles, reservadas y prestadas por instrumento.</span></a>
<a href="prestamos.php"><strong><?= Auth::esAdministrador()?'Gestionar préstamos':'Mis préstamos' ?></strong><span><?= Auth::esAdministrador()?'Aprobar o rechazar solicitudes, entregar equipos y recibir devoluciones.':'Solicitar instrumentos, revisar respuestas y consultar fechas de devolución.' ?></span></a>
<?php if(Auth::esAdministrador()): ?><a href="catalogos.php"><strong>Configurar catálogos</strong><span>Categorías, estados y condiciones de los instrumentos.</span></a><a href="usuarios.php"><strong>Gestionar usuarios</strong><span>Cuentas de estudiantes y personal del laboratorio.</span></a><a href="roles.php"><strong>Gestionar roles</strong><span>Perfiles de acceso al sistema.</span></a><?php endif; ?>
</div></section>
<?php if(Auth::esAdministrador()): ?>
<details class="connection-details"><summary>Diagnóstico del sistema</summary><section class="card"><div class="card__header"><h2>Conexión con MySQL</h2><span class="badge badge--wait" id="estadoBadge">Verificando...</span></div><p id="estadoMensaje">Consultando al servidor...</p><dl class="info" id="estadoInfo" hidden><div><dt>Base de datos</dt><dd id="infoBase">-</dd></div><div><dt>Versión de MySQL</dt><dd id="infoVersion">-</dd></div><div><dt>Tablas encontradas</dt><dd id="infoTablas">-</dd></div></dl><ul class="tables" id="listaTablas"></ul></section></details>
<?php endif; ?>
<?php require_once __DIR__.'/includes/layout_fin.php'; ?>
