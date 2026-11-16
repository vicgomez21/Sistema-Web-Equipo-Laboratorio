<?php
/** Genera una copia del panel con los agregados calculados por Reportes. */
function seriesPanelMedLab(array $stock, array $states): array
{
    $inventario = [
        'Disponibles' => (int)$stock['disponible'],
        'Reservadas' => (int)$stock['reservada'],
        'Prestadas' => (int)$stock['prestada'],
        'Fuera de servicio' => (int)$stock['fuera'],
    ];
    $inventario['Inactivas restantes'] = (int)$stock['total'] - array_sum($inventario);
    $etiquetas = ['PENDIENTE'=>'Pendiente', 'APROBADO'=>'Aprobado', 'ENTREGADO'=>'Entregado',
        'DEVUELTO'=>'Devuelto', 'RECHAZADO'=>'Rechazado', 'RESERVA_VENCIDA'=>'Reserva vencida', 'CANCELADO'=>'Cancelado'];
    $conteo = array_fill_keys(array_values($etiquetas), 0);
    foreach ($states as $estado) {
        if (!isset($etiquetas[$estado['estado']])) {
            throw new RuntimeException('Estado de préstamo desconocido.');
        }
        $conteo[$etiquetas[$estado['estado']]] = (int)$estado['total'];
    }
    return [$inventario, $conteo];
}

function descargarPanelExcel(array $stock, array $states, int $atrasados): void
{
    [$inventario, $conteo] = seriesPanelMedLab($stock, $states);
    $valores = [810001=>(int)$stock['total'], 810002=>$inventario['Disponibles'],
        810003=>$inventario['Prestadas'], 810004=>$atrasados];
    foreach (array_values($inventario) as $i=>$cantidad) $valores[810011+$i] = $cantidad;
    foreach (array_values($conteo) as $i=>$cantidad) $valores[810021+$i] = $cantidad;

    $base = new PharData(__DIR__.'/panel.zip');
    $partes = [];
        foreach (new RecursiveIteratorIterator($base) as $parte) {
            $nombre = substr($parte->getPathname(), strlen($base->getPath())+strlen('phar://')+1);
            $xml = $parte->getContent();
            if ($nombre === 'xl/worksheets/sheet1.xml') {
                $xml = preg_replace_callback('/<x:v>(8100[0-9]{2})<\/x:v>/', static fn($m)=>'<x:v>'.$valores[(int)$m[1]].'</x:v>', $xml);
                $xml = str_replace('CORTE_MEDLAB','Corte del laboratorio · '.date('d/m/Y H:i').' · Guatemala',$xml);
            }
            if (preg_match('~charts/chart([12])\.xml$~',$nombre,$m)) {
                $serie = $m[1] === '1' ? $inventario : $conteo;
                foreach (['strCache'=>array_keys($serie), 'numCache'=>array_values($serie)] as $tag=>$datos) {
                    $cache = '<c:'.$tag.'>'.($tag==='numCache'?'<c:formatCode>General</c:formatCode>':'');
                    $cache .= '<c:ptCount val="'.count($datos).'"/>';
                    foreach ($datos as $i=>$valor) {
                        $cache .= '<c:pt idx="'.$i.'"><c:v>'.htmlspecialchars((string)$valor,ENT_XML1,'UTF-8').'</c:v></c:pt>';
                    }
                    $xml = preg_replace('~<c:'.$tag.'>.*?</c:'.$tag.'>~s',$cache.'</c:'.$tag.'>',$xml);
                }
                if ($m[1]==='1') {
                    $puntos='';
                    foreach (['18AB91','6BBFD0','165B68','DA9F52','AAC5C2'] as $i=>$color) {
                        $puntos.='<c:dPt><c:idx val="'.$i.'"/><c:spPr><a:solidFill xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:srgbClr val="'.$color.'"/></a:solidFill></c:spPr></c:dPt>';
                    }
                    $xml=str_replace('<c:cat>',$puntos.'<c:cat>',$xml);
                }
            }
            $partes[$nombre] = $xml;
        }
    $xlsx = empaquetarPanel($partes);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="MedLab-panel-'.date('Y-m-d').'.xlsx"');
    header('Cache-Control: private, no-store');
    echo $xlsx;
}

/** ZIP convencional para Excel en servidores PHP sin la extensión ZipArchive. */
function empaquetarPanel(array $partes): string
{
    $contenido = '';
    $directorio = '';
    foreach ($partes as $nombre=>$xml) {
        $inicio = strlen($contenido);
        $comprimido = gzdeflate($xml);
        if ($comprimido === false) throw new RuntimeException('No se pudo comprimir el panel.');
        // Fecha DOS válida (1 de enero de 1980) para los componentes del documento.
        $metadatos = pack('vvvvvVVV', 20, 0, 8, 0, 33, crc32($xml), strlen($comprimido), strlen($xml));
        $contenido .= "PK\x03\x04".$metadatos.pack('vv',strlen($nombre),0).$nombre.$comprimido;
        $directorio .= "PK\x01\x02".pack('v',20).$metadatos.pack('vvvvvVV',strlen($nombre),0,0,0,0,0,$inicio).$nombre;
    }
    return $contenido.$directorio."PK\x05\x06".pack('vvvvVVv',0,0,count($partes),count($partes),strlen($directorio),strlen($contenido),0);
}