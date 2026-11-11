<?php
require_once __DIR__ . '/api/core/Auth.php';
Auth::administrador();
$tituloPagina = 'Estados de equipo';
$paginaActiva = 'estados_eq';
$scriptPagina = 'catalogo_equipos.js';
$catalogoTitulo = 'Estados de equipo';
$catalogoEntidad = 'estado de equipo';
$catalogoApi = 'api/admin/C_EstadosEquipo.php';
$catalogoIdCampo = 'id_estado';
$catalogoLimiteNombre = 30;
$catalogoTieneActivo = false;
$catalogoOpciones = [
    ['nombre' => 'Disponible', 'descripcion' => 'El equipo está disponible para utilizarse o prestarse.'],
    ['nombre' => 'En uso', 'descripcion' => 'El equipo está siendo utilizado actualmente.'],
    ['nombre' => 'Prestado', 'descripcion' => 'El equipo se encuentra asignado mediante un préstamo.'],
    ['nombre' => 'En mantenimiento', 'descripcion' => 'El equipo está recibiendo mantenimiento preventivo o correctivo.'],
    ['nombre' => 'Fuera de servicio', 'descripcion' => 'El equipo no está disponible para utilizarse.'],
];
require_once __DIR__ . '/includes/layout_inicio.php';
require __DIR__ . '/includes/catalogo_equipo.php';
require_once __DIR__ . '/includes/layout_fin.php';
