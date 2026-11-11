<?php
$tituloPagina = 'Catálogo';
$paginaActiva = 'inicio';
$scriptPagina = 'inicio.js';
require_once __DIR__ . '/includes/layout_inicio.php';
$categorias = [
    ['microscopia', 'Microscopía', 'Observación y análisis de muestras.', 'M55 28l24 16-25 36-24-16z M68 51c39 14 34 61-3 63 M24 90h47 M47 113v14 M24 128h76'],
    ['centrifugacion', 'Centrifugación', 'Separación y preparación de muestras.', 'M24 70h87v54H24z M24 70l17-31h53l17 31 M46 92h20 M83 91h10 M82 105h12'],
    ['medicion', 'Medición y análisis', 'Precisión para cada procedimiento.', 'M30 94h79v31H30z M69 94V44 M45 44h48 M36 34h67 M48 108h22 M87 108h9'],
    ['general', 'Equipo general', 'Instrumentos para el trabajo diario.', 'M47 25h32 M53 25v45l-26 44q-5 10 8 10h58q13 0 8-10L74 70V25 M43 94h44 M57 106h1 M75 115h1'],
];
?>
<section class="catalog-hero">
    <div><span class="eyebrow">MEDLAB / EQUIPAMIENTO DE LABORATORIO</span><h1>El equipo indicado.<br>Para tu próximo avance.</h1><p>Explora las áreas de nuestro catálogo y encuentra el punto de partida para tu trabajo en el laboratorio.</p><a class="btn btn--primario" href="#catalogo">Explorar categorías <span aria-hidden="true">↓</span></a></div>
</section>
<section id="catalogo" class="catalog-section" aria-labelledby="catalogTitle">
    <div class="catalog-heading"><div><span class="eyebrow">EXPLORA EL CATÁLOGO</span><h2 id="catalogTitle">Equipos por categoría</h2></div><p>Encuentra tu área de trabajo.</p></div>
    <div class="catalog-toolbar"><label for="buscarCategoria">Buscar categoría<input id="buscarCategoria" type="search" placeholder="Ej. microscopía, medición…"></label><span id="catalogCount" role="status">4 categorías</span></div>
    <div class="catalog-grid">
    <?php foreach ($categorias as [$clave, $nombre, $descripcion, $trazo]): ?>
        <button type="button" class="equipment-category" data-category="<?= htmlspecialchars($nombre) ?>" data-description="<?= htmlspecialchars($descripcion) ?>">
            <span class="equipment-category__art" aria-hidden="true"><svg viewBox="0 0 140 150" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $trazo ?>"/></svg><span>MEDLAB / <?= strtoupper($clave) ?></span></span>
            <span class="equipment-category__body"><strong><?= $nombre ?> <span aria-hidden="true">↗</span></strong><span><?= $descripcion ?></span><span class="equipment-category__action">Explorar categoría →</span></span>
        </button>
    <?php endforeach; ?>
    </div>
    <p id="catalogEmpty" class="catalog-empty" hidden>No encontramos esa categoría. Prueba con otra búsqueda.</p>
    <p class="catalog-note">Vista inicial de categorías. El inventario se administra desde el módulo de equipos; las solicitudes de préstamo siguen pendientes.</p>
</section>
<?php if (Auth::esAdministrador()): ?>
<section class="catalog-management"><div><span class="eyebrow">ADMINISTRACIÓN</span><h2>Prepara los catálogos y después registra el inventario.</h2></div><div class="accesos"><a href="categorias.php" class="btn btn--secundario">1. Categorías →</a><a href="estados_equipo.php" class="btn btn--secundario">2. Estados →</a><a href="condiciones_equipo.php" class="btn btn--secundario">3. Condiciones →</a><a href="equipos.php" class="btn btn--primario">4. Equipos →</a></div></section>
<details class="connection-details"><summary>Diagnóstico del sistema</summary><section class="card"><div class="card__header"><h2>Conexión con MySQL</h2><span class="badge badge--wait" id="estadoBadge">Verificando...</span></div><p id="estadoMensaje">Consultando al servidor...</p><dl class="info" id="estadoInfo" hidden><div><dt>Base de datos</dt><dd id="infoBase">-</dd></div><div><dt>Versión de MySQL</dt><dd id="infoVersion">-</dd></div><div><dt>Tablas encontradas</dt><dd id="infoTablas">-</dd></div></dl><ul class="tables" id="listaTablas"></ul></section></details>
<?php endif; ?>
<dialog id="detalleCategoria" aria-labelledby="categoriaTitulo"><div class="dialogo__cabecera"><span class="eyebrow">CATÁLOGO MEDLAB</span><h3 id="categoriaTitulo"></h3></div><div class="dialogo__cuerpo"><p id="categoriaDescripcion"></p><p>El listado de instrumentos de esta categoría estará disponible cuando se incorpore el inventario. Aún no se pueden realizar reservas.</p></div><form method="dialog" class="dialogo__pie"><button class="btn btn--primario">Volver al catálogo</button></form></dialog>
<?php require_once __DIR__ . '/includes/layout_fin.php'; ?>
