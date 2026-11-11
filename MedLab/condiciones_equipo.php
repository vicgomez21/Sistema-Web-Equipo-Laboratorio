<?php
require_once __DIR__ . '/api/core/Auth.php';
Auth::administrador();
$tituloPagina = 'Condiciones de equipo';
$paginaActiva = 'condiciones';
$scriptPagina = 'catalogo_equipos.js';
$catalogoTitulo = 'Condiciones de equipo';
$catalogoEntidad = 'condición de equipo';
$catalogoApi = 'api/admin/C_CondicionesEquipo.php';
$catalogoIdCampo = 'id_condicion';
$catalogoLimiteNombre = 30;
$catalogoTieneActivo = false;
$catalogoOpciones = [
    ['nombre' => 'Excelente', 'descripcion' => 'El equipo funciona correctamente y no presenta daños visibles.'],
    ['nombre' => 'Buena', 'descripcion' => 'El equipo funciona correctamente y presenta desgaste mínimo.'],
    ['nombre' => 'Regular', 'descripcion' => 'El equipo funciona, pero presenta desgaste o fallas menores.'],
    ['nombre' => 'Dañado', 'descripcion' => 'El equipo presenta daños que impiden su uso normal.'],
    ['nombre' => 'En reparación', 'descripcion' => 'El equipo está pendiente o en proceso de reparación.'],
];
require_once __DIR__ . '/includes/layout_inicio.php';
require __DIR__ . '/includes/catalogo_equipo.php';
require_once __DIR__ . '/includes/layout_fin.php';
