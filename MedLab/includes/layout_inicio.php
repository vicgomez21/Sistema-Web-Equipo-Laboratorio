<?php
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
    ['clave' => 'categorias',  'nombre' => 'Categorías',            'archivo' => null],
    ['clave' => 'equipos',     'nombre' => 'Equipos',               'archivo' => null],
    ['clave' => 'estados_eq',  'nombre' => 'Estados de equipo',     'archivo' => null],
    ['clave' => 'condiciones', 'nombre' => 'Condiciones de equipo', 'archivo' => null],
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
    <title><?= htmlspecialchars($tituloPagina) ?> | MedLab</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header class="topbar">
        <button class="hamburguesa" id="botonMenu" type="button"
                aria-label="Abrir menú" aria-expanded="false" aria-controls="menuLateral">
            <span></span><span></span><span></span>
        </button>
        <h1 class="topbar__title">MedLab - Sistema de Gestión de Equipos de Laboratorio</h1>
    </header>

    <div class="overlay" id="overlay"></div>

    <aside class="drawer" id="menuLateral" aria-label="Menú principal">
        <div class="drawer__cabecera">
            <span class="drawer__logo">⚕</span>
            <span>MedLab</span>
        </div>
        <nav class="drawer__nav">
            <?php foreach ($modulos as $modulo): ?>
                <?php if ($modulo['archivo'] !== null): ?>
                    <a class="nav-link<?= $modulo['clave'] === $paginaActiva ? ' active' : '' ?>"
                       href="<?= htmlspecialchars($modulo['archivo']) ?>">
                        <?= htmlspecialchars($modulo['nombre']) ?>
                    </a>
                <?php else: ?>
                    <a class="nav-link nav-link--pendiente" href="#"
                       data-pendiente="<?= htmlspecialchars($modulo['nombre']) ?>">
                        <?= htmlspecialchars($modulo['nombre']) ?>
                        <span class="nav-link__etiqueta">Pronto</span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="content">
