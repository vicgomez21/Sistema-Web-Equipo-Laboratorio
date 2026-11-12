<?php
class M_Inventario {
    private Connection $db;
    private const CATALOGOS = [
        'categorias'=>['categorias','id_categoria',60],
        'estados'=>['estados_equipo','id_estado',30],
        'condiciones'=>['condiciones_equipo','id_condicion',30],
    ];
    public function __construct(Connection $db) {$this->db=$db;}
    private function text(array $d,string $k,int $max,bool $required=false):string {
        $v=$d[$k]??'';if(!is_string($v))throw new RuntimeException('El campo '.$k.' no es válido.',422);
        $v=trim($v);if(($required&&$v==='')||mb_strlen($v)>$max)throw new RuntimeException('Revisa '.$k.': '.($required?'es obligatorio y ':'').'admite hasta '.$max.' caracteres.',422);return $v;
    }
    private function integer($value,string $name,int $min=1,int $max=1000000):int {
        if(is_array($value)||is_bool($value)||!preg_match('/^\d+$/D',(string)$value))throw new RuntimeException('Revisa '.$name.': debe ser un número entero.',422);
        $number=filter_var($value,FILTER_VALIDATE_INT);if($number===false||$number<$min||$number>$max)throw new RuntimeException('Revisa '.$name.': valor fuera del rango permitido.',422);return $number;
    }
    private function flag($value):int {if(!in_array($value,['0','1',0,1],true))throw new RuntimeException('El estado activo no es válido.',422);return (int)$value;}
    public function catalogo(string $tipo,bool $admin):array {
        [$table,$id]=$this->definition($tipo);$where=$tipo==='categorias'&&!$admin?' WHERE activo=1':'';
        return $this->db->query("SELECT * FROM $table$where ORDER BY nombre,$id")->fetchAll();
    }
    private function definition(string $tipo):array {if(!isset(self::CATALOGOS[$tipo]))throw new RuntimeException('Catálogo no válido.',404);return self::CATALOGOS[$tipo];}
    public function guardarCatalogo(string $tipo,array $d,bool $update):array {
        [$table,$key,$max]=$this->definition($tipo);$name=$this->text($d,'nombre',$max,true);$desc=$this->text($d,'descripcion',150);$active=$tipo==='categorias'?$this->flag($d['activo']??'1'):1;
        $id=$update?$this->integer($d[$key]??null,$key):null;
        $this->db->beginTransaction();
        try{
            if($update){$q=$this->db->prepare("SELECT $key FROM $table WHERE $key=? FOR UPDATE");$q->execute([$id]);if(!$q->fetch())throw new RuntimeException('El registro ya no existe.',404);}
            if($update&&$tipo==='categorias'&&$active===0){$q=$this->db->prepare('SELECT id_equipo FROM equipos WHERE id_categoria=? AND activo=1 ORDER BY id_equipo LIMIT 1 FOR UPDATE');$q->execute([$id]);if($q->fetch())throw new RuntimeException('Desactiva primero los instrumentos de esta categoría.',409);}
            $params=[$name,$desc?:null];$extra=$tipo==='categorias'?',activo=?':'';if($tipo==='categorias')$params[]=$active;
            if($update){$params[]=$id;$q=$this->db->prepare("UPDATE $table SET nombre=?,descripcion=?$extra WHERE $key=?");}
            else{$cols=$tipo==='categorias'?',activo':'';$holders=$tipo==='categorias'?',?':'';$q=$this->db->prepare("INSERT INTO $table(nombre,descripcion$cols) VALUES(?,?$holders)");}
            $q->execute($params);if(!$update)$id=(int)$this->db->lastInsertId();$this->db->commit();return [$key=>$id];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function eliminarCatalogo(string $tipo,array $d):array {
        [$table,$key]=$this->definition($tipo);$id=$this->integer($d[$key]??null,$key);
        $q=$this->db->prepare("DELETE FROM $table WHERE $key=?");$q->execute([$id]);if(!$q->rowCount())throw new RuntimeException('El registro ya no existe.',404);return [];
    }
    public function equipos(bool $admin):array {
        $where=$admin?'':' WHERE e.activo=1 AND c.activo=1';
        return $this->db->query('SELECT e.*,c.nombre AS categoria,c.activo AS categoria_activa,s.nombre AS estado,k.nombre AS condicion,v.cantidad_reservada,v.cantidad_prestada,v.cantidad_disponible FROM equipos e JOIN categorias c ON c.id_categoria=e.id_categoria JOIN estados_equipo s ON s.id_estado=e.id_estado JOIN condiciones_equipo k ON k.id_condicion=e.id_condicion JOIN disponibilidad_equipos v ON v.id_equipo=e.id_equipo'.$where.' ORDER BY e.nombre,e.id_equipo')->fetchAll();
    }
    private function ocupadas(int $id):int {
        // Lectura actual bajo el mismo mutex de equipo que usa el módulo de préstamos.
        $q=$this->db->prepare('SELECT d.cantidad,d.cantidad_devuelta,d.fecha_devolucion,p.fecha_prestamo,p.fecha_reserva_hasta,s.codigo FROM detalle_prestamo d JOIN prestamos p ON p.id_prestamo=d.id_prestamo JOIN estados_prestamo s ON s.id_estado_prestamo=p.id_estado_prestamo WHERE d.id_equipo=? ORDER BY p.id_prestamo,d.id_detalle FOR UPDATE');$q->execute([$id]);
        $now=$this->db->query('SELECT NOW()')->fetchColumn();$occupied=0;
        foreach($q->fetchAll() as $row){if($row['fecha_prestamo']!==null&&$row['fecha_devolucion']===null)$occupied+=(int)$row['cantidad']-(int)$row['cantidad_devuelta'];elseif($row['codigo']==='APROBADO'&&$row['fecha_prestamo']===null&&$row['fecha_reserva_hasta']>$now)$occupied+=(int)$row['cantidad'];}return $occupied;
    }
    public function guardarEquipo(array $d,bool $update):array {
        $id=$update?$this->integer($d['id_equipo']??null,'instrumento'):null;
        $values=['codigo_inventario'=>$this->text($d,'codigo_inventario',30,true),'nombre'=>$this->text($d,'nombre',100,true),'descripcion'=>$this->text($d,'descripcion',3000),'marca'=>$this->text($d,'marca',50),'modelo'=>$this->text($d,'modelo',50),'numero_serie'=>$this->text($d,'numero_serie',80),'ubicacion'=>$this->text($d,'ubicacion',100), 'cantidad_total'=>$this->integer($d['cantidad_total']??null,'cantidad total',0),'cantidad_fuera_servicio'=>$this->integer($d['cantidad_fuera_servicio']??'0','cantidad fuera de servicio',0),'id_categoria'=>$this->integer($d['id_categoria']??null,'categoría'),'id_estado'=>$this->integer($d['id_estado']??null,'estado'),'id_condicion'=>$this->integer($d['id_condicion']??null,'condición'),'activo'=>$this->flag($d['activo']??'1')];
        if($values['cantidad_fuera_servicio']>$values['cantidad_total'])throw new RuntimeException('Las unidades fuera de servicio no pueden superar el total.',422);
        if($values['cantidad_total']>1&&$values['numero_serie']!=='')throw new RuntimeException('Un número de serie identifica una sola unidad. Déjalo vacío para un lote de varias unidades.',422);
        $date=$this->text($d,'fecha_adquisicion',10);$parsed=$date===''?null:DateTimeImmutable::createFromFormat('!Y-m-d',$date);if($date!==''&&(!$parsed||$parsed->format('Y-m-d')!==$date))throw new RuntimeException('La fecha de adquisición no es válida.',422);$values['fecha_adquisicion']=$date?:null;
        foreach(['descripcion','marca','modelo','numero_serie','ubicacion'] as $nullable)if($values[$nullable]==='')$values[$nullable]=null;
        $this->db->beginTransaction();
        try{
            if($update){$q=$this->db->prepare('SELECT id_equipo FROM equipos WHERE id_equipo=? FOR UPDATE');$q->execute([$id]);if(!$q->fetch())throw new RuntimeException('El instrumento ya no existe.',404);$occupied=$this->ocupadas($id);if($values['cantidad_total']-$values['cantidad_fuera_servicio']<$occupied)throw new RuntimeException('Hay unidades reservadas o prestadas. El stock utilizable no puede ser menor que esas unidades.',409);if(!$values['activo']&&$occupied>0)throw new RuntimeException('No puedes desactivar un instrumento con unidades reservadas o prestadas.',409);}
            foreach(self::CATALOGOS as $type=>[$table,$key]){$q=$this->db->prepare("SELECT * FROM $table WHERE $key=? FOR UPDATE");$q->execute([$values[$key]]);$ref=$q->fetch();if(!$ref)throw new RuntimeException('La categoría, estado o condición seleccionada ya no existe.',422);if($type==='categorias'&&!$ref['activo']&&$values['activo'])throw new RuntimeException('Selecciona una categoría activa para este instrumento.',422);}
            if($update){$sql='UPDATE equipos SET '.implode(',',array_map(fn($k)=>$k.'=:'.$k,array_keys($values))).' WHERE id_equipo=:id_equipo';$values['id_equipo']=$id;}
            else{$keys=array_keys($values);$sql='INSERT INTO equipos('.implode(',',$keys).') VALUES('.implode(',',array_map(fn($k)=>':'.$k,$keys)).')';}
            $q=$this->db->prepare($sql);$q->execute($values);if(!$update)$id=(int)$this->db->lastInsertId();$this->db->commit();return ['id_equipo'=>$id];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function eliminarEquipo(array $d):array {
        $id=$this->integer($d['id_equipo']??null,'instrumento');$this->db->beginTransaction();
        try{$q=$this->db->prepare('SELECT id_equipo FROM equipos WHERE id_equipo=? FOR UPDATE');$q->execute([$id]);if(!$q->fetch())throw new RuntimeException('El instrumento ya no existe.',404);$q=$this->db->prepare('SELECT id_detalle FROM detalle_prestamo WHERE id_equipo=? LIMIT 1 FOR UPDATE');$q->execute([$id]);if($q->fetch())throw new RuntimeException('Este instrumento tiene solicitudes o préstamos en su historial. Puedes desactivarlo cuando no tenga unidades ocupadas.',409);$q=$this->db->prepare('DELETE FROM equipos WHERE id_equipo=?');$q->execute([$id]);$this->db->commit();return [];}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
