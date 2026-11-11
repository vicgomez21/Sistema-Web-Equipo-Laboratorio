<?php
require_once __DIR__ . '/../api/core/Auth.php';
Auth::proteger();
/**
 * Encabezado común de todas las páginas (incluye el menú hamburguesa).
 * Cada página define antes: $tituloPagina y $paginaActiva.
 * Para habilitar un módulo nuevo: cambia 'archivo' => null por el nombre de su página.
 */
$tituloPagina = $tituloPagina ?? 'MedLab';
$paginaActiva = $paginaActiva ?? '';

$modulos = [
    ['clave' => 'inicio',      'nombre' => 'Inicio',                'archivo' => 'index.php'],
    ['clave' => 'usuarios',    'nombre' => 'Usuarios',              'archivo' => 'usuarios.php'],
    ['clave' => 'roles',       'nombre' => 'Roles',                 'archivo' => 'roles.php'],
    ['clave' => 'categorias',  'nombre' => 'Categorías',            'archivo' => 'categorias.php'],
    ['clave' => 'estados_eq',  'nombre' => 'Estados de equipo',     'archivo' => 'estados_equipo.php'],
    ['clave' => 'condiciones', 'nombre' => 'Condiciones de equipo', 'archivo' => 'condiciones_equipo.php'],
    ['clave' => 'equipos',     'nombre' => 'Equipos',               'archivo' => 'equipos.php'],
    ['clave' => 'prestamos',   'nombre' => 'Préstamos',             'archivo' => null],
    ['clave' => 'estados_pr',  'nombre' => 'Estados de préstamo',   'archivo' => null],
    ['clave' => 'reportes',    'nombre' => 'Reportes',              'archivo' => null],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Auth::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($tituloPagina) ?> | MedLab</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= filemtime(__DIR__ . '/../css/styles.css') ?>">
</head>
<body>
    <header class="topbar">
        <button class="hamburguesa" id="botonMenu" type="button"
                aria-label="Abrir menú" aria-expanded="false" aria-controls="menuLateral">
            <span></span><span></span><span></span>
        </button>
        <a class="brand topbar__brand" href="index.php"><img src="assets/logo.svg" alt="" width="44" height="44"><span>MedLab<small>LABORATORIO DE MEDICINA</small></span></a>
        <span class="topbar__description">Gestión de equipos de laboratorio</span>
    </header>

    <div class="overlay" id="overlay"></div>

    <aside class="drawer" id="menuLateral" aria-label="Menú principal">
        <div class="drawer__cabecera">
            <img src="assets/logo.svg" alt="" width="42" height="42">
            <span>MedLab</span>
            <button type="button" class="drawer__close" id="cerrarMenu" aria-label="Cerrar menú">×</button>
        </div>
        <nav class="drawer__nav line-sidebar" aria-label="Secciones de MedLab">
            <?php foreach ($modulos as $indice => $modulo): ?>
                <?php if (in_array($modulo['clave'], ['usuarios', 'roles', 'categorias', 'estados_eq', 'condiciones', 'equipos'], true) && !Auth::esAdministrador()) continue; ?>
                <?php if ($modulo['archivo'] !== null): ?>
                    <a class="nav-link<?= $modulo['clave'] === $paginaActiva ? ' active' : '' ?>"
                       href="<?= htmlspecialchars($modulo['archivo']) ?>"<?= $modulo['clave'] === $paginaActiva ? ' aria-current="page"' : '' ?>>
                        <span class="line-sidebar__marker" aria-hidden="true"></span>
                        <span class="line-sidebar__label"><span class="line-sidebar__index" aria-hidden="true"><?= sprintf('%02d', $indice + 1) ?></span><span><?= htmlspecialchars($modulo['nombre']) ?></span></span>
                    </a>
                <?php else: ?>
                    <a class="nav-link nav-link--pendiente" href="#"
                       data-pendiente="<?= htmlspecialchars($modulo['nombre']) ?>">
                        <span class="line-sidebar__marker" aria-hidden="true"></span>
                        <span class="line-sidebar__label"><span class="line-sidebar__index" aria-hidden="true"><?= sprintf('%02d', $indice + 1) ?></span><span><?= htmlspecialchars($modulo['nombre']) ?><small>En desarrollo</small></span></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <form action="api/admin/C_Auth.php" method="post" class="drawer__cabecera">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(Auth::token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="accion" value="salir">
            <button type="submit" class="btn btn--secundario">Cerrar sesión</button>
        </form>
    </aside>

    <main class="content">
