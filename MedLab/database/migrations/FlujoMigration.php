<?php
/** Migración explícita: no se ejecuta al iniciar la aplicación. MariaDB 10.4+. */
class FlujoMigration {
    public const STATES = ['PENDIENTE'=>'Pendiente','APROBADO'=>'Aprobado','RECHAZADO'=>'Rechazado','ENTREGADO'=>'Entregado','DEVUELTO'=>'Devuelto','RESERVA_VENCIDA'=>'Reserva vencida','CANCELADO'=>'Cancelado'];
    public static function preflight(PDO $db, array $mapping): array {
        $schema=$db->query('SELECT DATABASE()')->fetchColumn();
        $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND ((TABLE_NAME='estados_prestamo' AND COLUMN_NAME='codigo') OR (TABLE_NAME='prestamos' AND COLUMN_NAME='version_reglas'))");$q->execute([$schema]);
        if($q->fetchColumn())throw new RuntimeException('Se detectó una migración previa o parcial. Revisar el respaldo antes de continuar.');
        $tables=$db->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM);
        $expected=['roles','categorias','estados_equipo','condiciones_equipo','estados_prestamo','usuarios','equipos','prestamos','detalle_prestamo'];
        if(count($tables)!==count($expected))throw new RuntimeException('Este migrador está limitado al esquema original de nueve tablas, sin vistas adicionales.');
        foreach($tables as $table)if($table[1]!=='BASE TABLE'||!in_array($table[0],$expected,true))throw new RuntimeException('Esquema extendido no compatible con este respaldo. Usar una migración revisada para esa instalación.');
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=?');$q->execute([$schema]);if($q->fetchColumn())throw new RuntimeException('Existen triggers adicionales: se requiere un respaldo y migración específicos.');
        $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND GENERATION_EXPRESSION IS NOT NULL AND GENERATION_EXPRESSION<>'' AND NOT(TABLE_NAME='detalle_prestamo' AND COLUMN_NAME='equipo_pendiente')");$q->execute([$schema]);if($q->fetchColumn())throw new RuntimeException('Columnas generadas adicionales no compatibles con este respaldo.');
        $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='equipos' AND COLUMN_NAME='cantidad_total'");$q->execute([$schema]);
        if($q->fetchColumn()) throw new RuntimeException('El esquema ya está adaptado o parcialmente migrado. No se repite; revisar el respaldo y el registro antes de continuar.');
        $states=$db->query('SELECT id_estado_prestamo,nombre FROM estados_prestamo ORDER BY id_estado_prestamo')->fetchAll(PDO::FETCH_ASSOC);
        $seen=[];
        foreach($states as $state){$id=(string)$state['id_estado_prestamo'];$code=$mapping[$id]??null;
            if(!$code||!isset(self::STATES[$code])||isset($seen[$code]))throw new RuntimeException('Se requiere un mapeo explícito y sin duplicados para cada estado anterior. ID pendiente: '.$id);
            $seen[$code]=true;
        }
        if(count($mapping)!==count($states))throw new RuntimeException('El mapeo debe contener exactamente los estados existentes.');
        $collision=$db->prepare('SELECT COUNT(*) FROM estados_prestamo WHERE nombre=?');
        foreach(self::STATES as $code=>$name){if(isset($seen[$code]))continue;$collision->execute([$name]);if($collision->fetchColumn())throw new RuntimeException('El mapa de estados provoca un conflicto con el nombre reservado: '.$name);}
        // La estructura anterior representa una unidad por registro. No agrupar ni fusionar equipos históricos.
        $rows=$db->query('SELECT p.id_prestamo,p.id_estado_prestamo,p.fecha_prestamo,COUNT(d.id_detalle) AS detalles,SUM(d.fecha_devolucion IS NULL) AS abiertos FROM prestamos p LEFT JOIN detalle_prestamo d ON d.id_prestamo=p.id_prestamo GROUP BY p.id_prestamo,p.id_estado_prestamo,p.fecha_prestamo')->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){$code=$mapping[(string)$row['id_estado_prestamo']];
            if(!$row['detalles']||!in_array($code,['ENTREGADO','DEVUELTO'],true)||($code==='DEVUELTO'&&(int)$row['abiertos']>0)||($code==='ENTREGADO'&&(int)$row['abiertos']===0))throw new RuntimeException('Préstamo histórico incompatible: '.$row['id_prestamo'].'. Revisar antes de migrar; no se cambiará su significado automáticamente.');
        }
        return ['equipos'=>(int)$db->query('SELECT COUNT(*) FROM equipos')->fetchColumn(),'prestamos'=>count($rows),'estados'=>count($states)];
    }
    public static function backup(PDO $db, string $directory): string {
        $real=realpath($directory);$public=realpath(dirname(__DIR__,2));
        if(!$real||!is_dir($real)||!is_writable($real))throw new RuntimeException('La carpeta de respaldo debe existir y permitir escritura.');
        // Exigir que el respaldo no esté dentro de htdocs ni del código público.
        $normalized=strtolower(str_replace('\\','/',$real));
        if(str_contains($normalized,'/htdocs')||($public&&str_starts_with($normalized,strtolower(str_replace('\\','/',$public)))))throw new RuntimeException('Usa una carpeta fuera del directorio público para el respaldo.');
        $file=$real.'/MedLab-antes-cantidades-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
        $out=fopen($file,'xb');if(!$out)throw new RuntimeException('No se pudo crear el respaldo.');
        $write=function($text)use($out){if(fwrite($out,$text)!==strlen($text))throw new RuntimeException('No se pudo completar el respaldo.');};
        try{
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $write("-- Respaldo previo a migración; contiene datos privados.\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $entry){
                $table='`'.str_replace('`','``',$entry[0]).'`';$create=$db->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];$write('DROP TABLE IF EXISTS '.$table.";\n".$create.";\n");
                $rows=$db->query('SELECT * FROM '.$table);
                while($row=$rows->fetch(PDO::FETCH_ASSOC)){
                    // Columnas generadas se omiten: el servidor calcula sus valores al restaurar.
                    $columns=[];$values=[];
                    foreach($row as $column=>$value){if($column==='equipo_pendiente')continue;$columns[]='`'.str_replace('`','``',$column).'`';$values[]=$value===null?'NULL':$db->quote((string)$value);}
                    $write('INSERT INTO '.$table.'('.implode(',',$columns).') VALUES('.implode(',',$values).");\n");
                }
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");$db->commit();if(!fflush($out))throw new RuntimeException('No se pudo finalizar el respaldo.');
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{fclose($out);}
        return $file;
    }
    public static function apply(PDO $db,array $mapping,string $backupDirectory): array {
        if(!$db->query("SELECT GET_LOCK('medlab_migration_002',0)")->fetchColumn())throw new RuntimeException('Hay otra migración en curso.');
        try{
            $counts=self::preflight($db,$mapping);$backup=self::backup($db,$backupDirectory);
            $log=$backup.'.migration.log';self::log($log,"INICIO: ejecutar con la aplicación en mantenimiento. El DDL no es atómico.\n",false);
            try{
                $db->exec(file_get_contents(__DIR__.'/002_cantidades.sql'));self::log($log,"COLUMNAS_OK\n");
                $q=$db->prepare('UPDATE estados_prestamo SET codigo=? WHERE id_estado_prestamo=?');foreach($mapping as $id=>$code)$q->execute([$code,$id]);
                $q=$db->prepare('INSERT INTO estados_prestamo(codigo,nombre) SELECT ?,? WHERE NOT EXISTS(SELECT 1 FROM estados_prestamo WHERE codigo=?)');
                foreach(self::STATES as $code=>$name)$q->execute([$code,$name,$code]);
                $db->exec('ALTER TABLE estados_prestamo MODIFY codigo VARCHAR(24) NOT NULL');
                $db->exec('ALTER TABLE prestamos MODIFY version_reglas TINYINT NOT NULL DEFAULT 2, MODIFY fecha_solicitud DATETIME NULL DEFAULT CURRENT_TIMESTAMP');
                self::log($log,"DATOS_OK\n");
                $db->exec(file_get_contents(__DIR__.'/002_complementos.sql'));self::log($log,"COMPLETADA\n");
            }catch(Throwable $e){self::log($log,"ERROR: no reintentar a ciegas; restaurar respaldo en una base aparte y revisar.\n");throw $e;}
            return ['backup'=>$backup,'resumen'=>$counts];
        }finally{$db->query("SELECT RELEASE_LOCK('medlab_migration_002')");}
    }
    private static function log(string $file,string $line,bool $append=true):void {
        if(file_put_contents($file,$line,$append?FILE_APPEND:0)!==strlen($line))throw new RuntimeException('No se pudo escribir el registro de migración; detener y revisar antes de continuar.');
    }
}
