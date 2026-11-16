<?php
require_once __DIR__.'/api/core/Auth.php';Auth::proteger(false,false,'reportes');
$tituloPagina='Reportes';$paginaActiva='reportes';$estiloPagina='inventario.css';
try{
 $db=new Connection();
 $stock=$db->query('SELECT COALESCE(SUM(cantidad_total),0) AS total,COALESCE(SUM(cantidad_fuera_servicio),0) AS fuera,COALESCE(SUM(cantidad_reservada),0) AS reservada,COALESCE(SUM(cantidad_prestada),0) AS prestada,COALESCE(SUM(cantidad_disponible),0) AS disponible FROM disponibilidad_equipos')->fetch();
 $states=$db->query("SELECT CASE WHEN s.codigo='APROBADO' AND p.fecha_prestamo IS NULL AND p.fecha_reserva_hasta<=NOW() THEN 'RESERVA_VENCIDA' ELSE s.codigo END AS estado,COUNT(*) AS total FROM prestamos p JOIN estados_prestamo s ON s.id_estado_prestamo=p.id_estado_prestamo GROUP BY estado ORDER BY estado")->fetchAll();
 $late=$db->query('SELECT p.id_prestamo,CONCAT(u.nombres,\' \',u.apellidos) AS solicitante,p.fecha_limite,SUM(d.cantidad-d.cantidad_devuelta) AS pendientes FROM prestamos p JOIN usuarios u ON u.id_usuario=p.id_usuario JOIN detalle_prestamo d ON d.id_prestamo=p.id_prestamo WHERE p.fecha_prestamo IS NOT NULL AND p.fecha_limite<NOW() AND d.fecha_devolucion IS NULL GROUP BY p.id_prestamo,u.nombres,u.apellidos,p.fecha_limite HAVING pendientes>0 ORDER BY p.fecha_limite')->fetchAll();
}catch(Throwable $e){error_log('[MedLab] Reportes: '.$e->getMessage());http_response_code(503);$reportError=true;}
if (($_GET['descargar'] ?? '') === 'excel' && empty($reportError)) {
    try {
        require_once __DIR__.'/includes/PanelExcel.php';
        descargarPanelExcel($stock, $states, count($late));
        exit;
    } catch (Throwable $e) {
        error_log('[MedLab] Exportación: '.$e->getMessage());
        http_response_code(503);
        $exportError = true;
    }
}
require_once __DIR__.'/includes/PanelExcel.php';
require_once __DIR__.'/includes/layout_inicio.php';
function reportEscape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<header class="page-header"><div><h1>Reportes del laboratorio</h1><p class="page-header__texto">Resumen de las existencias y los préstamos al momento de abrir esta página.</p></div><?php if(empty($reportError)): ?><a href="reportes.php?descargar=excel" class="btn btn--primario">Descargar Excel</a><?php endif; ?><a href="reportes.php" class="btn btn--secundario">Actualizar</a></header><?php if(!empty($exportError)): ?><p role="alert">No se pudo generar el Excel. Intenta nuevamente.</p><?php endif; ?>
<?php if(!empty($reportError)): ?><p role="alert">No pudimos cargar los reportes. Intenta nuevamente.</p><?php else: ?>
<?php
[$inventario, $solicitudes] = seriesPanelMedLab($stock, $states);
$indicadores = [
    'Unidades registradas' => (int)$stock['total'],
    'Disponibles' => $inventario['Disponibles'],
    'Prestadas' => $inventario['Prestadas'],
    'Préstamos atrasados' => count($late),
];
?>
<div class="reporte-panel">
  <dl class="reporte-cifras">
    <?php foreach ($indicadores as $nombre => $cantidad): ?>
      <div><dt><?= reportEscape($nombre) ?></dt><dd><?= $cantidad ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <div class="reporte-graficas">
    <section class="card">
      <h2>Distribución del inventario</h2>
      <?php $total = array_sum($inventario); ?>
      <?php if (!$total): ?>
        <p>Todavía no hay unidades registradas.</p>
      <?php else: ?>
        <?php
        $colores = array_combine(array_keys($inventario), ['#168975','#67b9cb','#165b68','#d79b47','#acc5c1']);
        $inicio = 0;
        ?>
        <div class="reporte-pastel">
          <svg viewBox="0 0 100 100" aria-hidden="true" focusable="false">
            <?php foreach ($inventario as $nombre => $cantidad): ?>
              <?php if (!$cantidad) continue; ?>
              <?php if ($cantidad == $total): ?>
                <circle cx="50" cy="50" r="50" fill="<?= $colores[$nombre] ?>" />
              <?php else:
                $anguloInicial = 2 * M_PI * $inicio / $total;
                $inicio += $cantidad;
                $anguloFinal = 2 * M_PI * $inicio / $total;
                $sector = sprintf('M50 50 L%.8F %.8F A50 50 0 %d 1 %.8F %.8F Z',
                    50 + 50 * sin($anguloInicial), 50 - 50 * cos($anguloInicial),
                    2 * $cantidad > $total ? 1 : 0,
                    50 + 50 * sin($anguloFinal), 50 - 50 * cos($anguloFinal));
              ?>
                <path d="<?= $sector ?>" fill="<?= $colores[$nombre] ?>" />
              <?php endif; ?>
            <?php endforeach; ?>
          </svg>
          <ul>
            <?php foreach ($inventario as $nombre => $cantidad): ?>
              <li><svg width="12" height="12" aria-hidden="true"><circle cx="6" cy="6" r="6" fill="<?= $colores[$nombre] ?>"/></svg>
                <span><?= reportEscape($nombre) ?></span><strong><?= $cantidad ?></strong></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2>Solicitudes por estado</h2>
      <?php if (!array_sum($solicitudes)): ?>
        <p>Todavía no hay solicitudes registradas.</p>
      <?php else: ?>
        <div class="reporte-scroll" tabindex="0" role="region" aria-label="Gráfica de solicitudes por estado">
          <ul class="reporte-columnas">
            <?php foreach ($solicitudes as $nombre => $cantidad): ?>
              <?php $alto = 120 * $cantidad / max($solicitudes); ?>
              <li>
                <svg viewBox="0 0 60 150" aria-hidden="true" focusable="false">
                  <text x="30" y="<?= 140 - $alto ?>" text-anchor="middle" fill="#164752" font-size="15"><?= $cantidad ?></text>
                  <rect x="12" y="<?= 148 - $alto ?>" width="36" height="<?= $alto ?>" fill="#007b80"/>
                  <path d="M0 149h60" stroke="#aac6c6"/>
                </svg>
                <span><?= reportEscape($nombre) ?><span class="reporte-lector">: <?= $cantidad ?></span></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<section class="card"><h2>Devoluciones atrasadas</h2><div class="tabla-contenedor" tabindex="0" role="region" aria-label="Préstamos con devolución atrasada"><table class="tabla"><thead><tr><th scope="col">Préstamo</th><th scope="col">Solicitante</th><th scope="col">Fecha límite</th><th scope="col">Unidades pendientes</th></tr></thead><tbody><?php foreach($late as $row): ?><tr><td>#<?= reportEscape($row['id_prestamo']) ?></td><td><?= reportEscape($row['solicitante']) ?></td><td><?= reportEscape($row['fecha_limite']) ?></td><td><?= reportEscape($row['pendientes']) ?></td></tr><?php endforeach; ?><?php if(!$late): ?><tr><td colspan="4">No hay devoluciones atrasadas.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<?php require_once __DIR__.'/includes/layout_fin.php'; ?>
