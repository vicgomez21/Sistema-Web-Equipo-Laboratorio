<?php
require_once __DIR__ . '/api/core/Auth.php';
Auth::administrador();
$tituloPagina = 'Categorías';
$paginaActiva = 'categorias';
$scriptPagina = 'catalogo_equipos.js';
$catalogoTitulo = 'Categorías';
$catalogoEntidad = 'categoría';
$catalogoApi = 'api/admin/C_Categorias.php';
$catalogoIdCampo = 'id_categoria';
$catalogoLimiteNombre = 60;
$catalogoTieneActivo = true;
$catalogoOpciones = [
    ['nombre' => 'Microscopios', 'descripcion' => 'Equipos utilizados para ampliar y observar muestras.'],
    ['nombre' => 'Centrífugas', 'descripcion' => 'Equipos utilizados para separar componentes mediante rotación.'],
    ['nombre' => 'Balanzas', 'descripcion' => 'Instrumentos para medir la masa de muestras y materiales.'],
    ['nombre' => 'Espectrofotómetros', 'descripcion' => 'Equipos para medir la absorbancia o transmisión de luz.'],
    ['nombre' => 'Autoclaves', 'descripcion' => 'Equipos para esterilizar materiales mediante vapor a presión.'],
    ['nombre' => 'Incubadoras', 'descripcion' => 'Equipos que mantienen condiciones controladas para cultivos.'],
    ['nombre' => 'Refrigeración', 'descripcion' => 'Equipos para conservar muestras y reactivos a baja temperatura.'],
    ['nombre' => 'Analizadores', 'descripcion' => 'Equipos automatizados para analizar muestras de laboratorio.'],
];
require_once __DIR__ . '/includes/layout_inicio.php';
require __DIR__ . '/includes/catalogo_equipo.php';
require_once __DIR__ . '/includes/layout_fin.php';
