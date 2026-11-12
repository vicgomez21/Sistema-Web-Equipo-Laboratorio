<?php
/** Flujo transaccional de solicitudes, reservas, entregas y devoluciones. */
class M_Prestamos {
    private Connection $db;
    public function __construct(Connection $db) { $this->db = $db; }
    private function fail(string $message, int $status = 422): void { throw new RuntimeException($message, $status); }
    private function integer($value, string $label, int $minimum = 1, int $maximum = 1000000): int {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$value)) $this->fail($label . ' debe ser un entero válido.');
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $minimum || $number > $maximum) $this->fail($label . ' está fuera del rango permitido.');
        return $number;
    }
    private function text($value, string $label, int $max = 500, bool $required = false): string {
        if (!is_string($value)) $this->fail($label . ' no es válido.');
        $value = trim($value);
        if (mb_strlen($value) > $max || ($required && $value === '')) $this->fail($label . ' es obligatorio o supera el máximo permitido.');
        return $value;
    }
    private function date($value, string $label): string {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) $this->fail($label . ' debe tener formato AAAA-MM-DD.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') $this->fail($label . ' no es una fecha válida.');
        return $value;
    }
    private function rows($value, string $label): array {
        if (!is_string($value)) $this->fail($label . ' debe ser una lista JSON.');
        try { $rows = json_decode($value, true, 8, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { $this->fail($label . ' contiene JSON no válido.'); }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 50) $this->fail($label . ' debe contener entre 1 y 50 elementos.');
        foreach ($rows as $row) if (!is_array($row) || array_is_list($row)) $this->fail($label . ' contiene un elemento no válido.');
        return $rows;
    }
    private function query(string $sql, array $values = []): PDOStatement {
        $stmt = $this->db->prepare($sql); $stmt->execute($values); return $stmt;
    }
    private function state(string $code): int {
        $id = $this->query('SELECT id_estado_prestamo FROM estados_prestamo WHERE codigo = ?', [$code])->fetchColumn();
        if ($id === false) $this->fail('El esquema de préstamos no está preparado. Contacta al administrador.', 409);
        return (int)$id;
    }
    private function clock(): array {
        return $this->query('SELECT CURRENT_TIMESTAMP AS ahora, CURRENT_DATE AS hoy, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 MONTH) AS maximo')->fetch();
    }
    /** Each writer locks equipment IDs in ascending order before any loan/detail lock. */
    private function lockEquipment(array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids))); sort($ids, SORT_NUMERIC); $equipment = [];
        foreach ($ids as $id) {
            $row = $this->query('SELECT * FROM equipos WHERE id_equipo = ? FOR UPDATE', [$id])->fetch();
            if (!$row) $this->fail('Uno de los equipos ya no existe.', 404);
            $equipment[$id] = $row;
        }
        return $equipment;
    }
    private function available(array $equipment): void {
        foreach ($equipment as $row) {
            if (!(int)$row['activo']) $this->fail('El equipo seleccionado está inactivo.', 409);
            $active = $this->query('SELECT activo FROM categorias WHERE id_categoria = ? FOR UPDATE', [$row['id_categoria']])->fetchColumn();
            if (!$active) $this->fail('La categoría del equipo está inactiva.', 409);
        }
    }
    /** Current locking reads: do not use a repeatable-read snapshot to allocate stock. */
    private function occupied(int $equipmentId, string $now, int $excludeLoan = 0): int {
        $rows = $this->query('SELECT d.cantidad, d.cantidad_devuelta, d.fecha_devolucion, p.id_prestamo, p.fecha_prestamo, p.fecha_reserva_hasta, s.codigo FROM detalle_prestamo d INNER JOIN prestamos p ON p.id_prestamo=d.id_prestamo INNER JOIN estados_prestamo s ON s.id_estado_prestamo=p.id_estado_prestamo WHERE d.id_equipo = ? ORDER BY p.id_prestamo, d.id_detalle FOR UPDATE', [$equipmentId])->fetchAll();
        $occupied = 0;
        foreach ($rows as $row) {
            if ((int)$row['id_prestamo'] === $excludeLoan) continue;
            if ($row['fecha_prestamo'] !== null && $row['fecha_devolucion'] === null) $occupied += (int)$row['cantidad'] - (int)$row['cantidad_devuelta'];
            elseif ($row['codigo'] === 'APROBADO' && $row['fecha_prestamo'] === null && $row['fecha_reserva_hasta'] !== null && $row['fecha_reserva_hasta'] > $now) $occupied += (int)$row['cantidad'];
        }
        return $occupied;
    }
    private function checkCapacity(array $equipment, array $details, string $now, int $excludeLoan = 0): void {
        $this->available($equipment);
        foreach ($details as $detail) {
            $row = $equipment[(int)$detail['id_equipo']];
            $free = (int)$row['cantidad_total'] - (int)$row['cantidad_fuera_servicio'] - $this->occupied((int)$row['id_equipo'], $now, $excludeLoan);
            if ((int)$detail['cantidad'] > $free) $this->fail('No hay cantidad disponible suficiente para uno de los equipos.', 409);
        }
    }
    private function notify(int $user, int $loan, string $event, string $message): void {
        $this->query('INSERT INTO notificaciones (id_usuario,id_prestamo,evento,mensaje) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id_notificacion=id_notificacion', [$user,$loan,$event,$message]);
    }
    private function notifyAdmins(int $loan): void {
        $this->query("INSERT INTO notificaciones (id_usuario,id_prestamo,evento,mensaje) SELECT u.id_usuario,?,'SOLICITUD','Hay una nueva solicitud de préstamo pendiente.' FROM usuarios u INNER JOIN roles r ON r.id_rol=u.id_rol WHERE u.activo=1 AND LOWER(TRIM(r.nombre))='administrador' ON DUPLICATE KEY UPDATE id_notificacion=id_notificacion", [$loan]);
    }
    public function listar(int $user, bool $admin): array {
        $sql = "SELECT p.*,s.codigo AS estado,s.nombre AS estado_nombre,u.nombres,u.apellidos,u.codigo_usuario,CONCAT(u.nombres,' ',u.apellidos) AS solicitante,DATE_FORMAT(DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 MONTH),'%Y-%m-%d') AS fecha_maxima_devolucion FROM prestamos p INNER JOIN estados_prestamo s ON s.id_estado_prestamo=p.id_estado_prestamo INNER JOIN usuarios u ON u.id_usuario=p.id_usuario";
        $loans = $this->query($sql . ($admin ? '' : ' WHERE p.id_usuario=?') . ' ORDER BY p.id_prestamo DESC', $admin ? [] : [$user])->fetchAll();
        $now = $this->clock()['ahora'];
        foreach ($loans as &$loan) {
            if ($loan['estado'] === 'APROBADO' && $loan['fecha_prestamo'] === null && $loan['fecha_reserva_hasta'] !== null && $loan['fecha_reserva_hasta'] <= $now) {
                $loan['estado'] = 'RESERVA_VENCIDA'; $loan['estado_nombre'] = 'Reserva vencida';
            }
            $loan['detalles'] = $this->query('SELECT d.*,d.cantidad-d.cantidad_devuelta AS cantidad_pendiente,e.nombre,e.codigo_inventario,e.id_condicion AS id_condicion_actual FROM detalle_prestamo d INNER JOIN equipos e ON e.id_equipo=d.id_equipo WHERE d.id_prestamo=? ORDER BY d.id_equipo', [$loan['id_prestamo']])->fetchAll();
        }
        unset($loan); return $loans;
    }
    public function ejecutar(string $action, array $data, int $user, bool $admin): array {
        if (!in_array($action, ['solicitar','aprobar','rechazar','entregar','devolver','cancelar'], true)) $this->fail('Acción de préstamo no válida.', 400);
        if (!in_array($action, ['solicitar','cancelar'], true) && !$admin) $this->fail('No tienes permiso para realizar esta acción.', 403);
        if ($action === 'solicitar') return $this->request($data, $user);
        $loanId = $this->integer($data['id_prestamo'] ?? null, 'El préstamo', 1, 2147483647);
        // Immutable contents: discover IDs without locks, then acquire the common equipment mutex.
        $seed = $this->query('SELECT p.id_usuario,d.id_equipo FROM prestamos p LEFT JOIN detalle_prestamo d ON d.id_prestamo=p.id_prestamo WHERE p.id_prestamo=?', [$loanId])->fetchAll();
        if (!$seed) $this->fail('El préstamo no existe.', 404);
        if (!$admin && (int)$seed[0]['id_usuario'] !== $user) $this->fail('No tienes permiso para acceder a este préstamo.', 403);
        $ids=[]; foreach ($seed as $row) if ($row['id_equipo'] !== null) $ids[]=(int)$row['id_equipo'];
        if (!$ids) $this->fail('El préstamo no tiene equipos registrados.', 409);
        $this->db->beginTransaction();
        try {
            $equipment = $this->lockEquipment($ids);
            $loan = $this->query('SELECT p.*,s.codigo AS estado FROM prestamos p INNER JOIN estados_prestamo s ON s.id_estado_prestamo=p.id_estado_prestamo WHERE p.id_prestamo=? FOR UPDATE', [$loanId])->fetch();
            if (!$loan) $this->fail('El préstamo no existe.', 404);
            if (!$admin && (int)$loan['id_usuario'] !== $user) $this->fail('No tienes permiso para acceder a este préstamo.', 403);
            $details=$this->query('SELECT * FROM detalle_prestamo WHERE id_prestamo=? ORDER BY id_equipo FOR UPDATE', [$loanId])->fetchAll();
            foreach ($details as $detail) if (!isset($equipment[(int)$detail['id_equipo']])) $this->fail('El préstamo cambió. Recarga la página.', 409);
            $clock=$this->clock(); $now=$clock['ahora']; $owner=(int)$loan['id_usuario'];
            if ($action==='aprobar') {
                if ($loan['estado']!=='PENDIENTE' || $loan['fecha_prestamo']!==null) $this->fail('Solo se pueden aprobar solicitudes pendientes.',409);
                if (!$this->query('SELECT activo FROM usuarios WHERE id_usuario=?',[$owner])->fetchColumn()) $this->fail('El solicitante está inactivo.',409);
                $this->checkCapacity($equipment,$details,$now,$loanId);
                $this->query('UPDATE prestamos SET id_estado_prestamo=?,id_encargado_aprobacion=?,fecha_aprobacion=?,fecha_decision=? WHERE id_prestamo=?',[$this->state('APROBADO'),$user,$now,$now,$loanId]);
                $this->notify($owner,$loanId,'APROBADO','Tu solicitud fue aprobada. Recoge los equipos dentro de los próximos 14 días.');
            } elseif ($action==='rechazar') {
                if ($loan['estado']!=='PENDIENTE' || $loan['fecha_prestamo']!==null) $this->fail('Solo se pueden rechazar solicitudes pendientes.',409);
                $reason=$this->text($data['motivo']??$data['motivo_rechazo']??'', 'El motivo de rechazo',500,true);
                $this->query('UPDATE prestamos SET id_estado_prestamo=?,fecha_decision=?,motivo_rechazo=? WHERE id_prestamo=?',[$this->state('RECHAZADO'),$now,$reason,$loanId]);
                $this->notify($owner,$loanId,'RECHAZADO','Tu solicitud fue rechazada. Consulta el motivo en tus préstamos.');
            } elseif ($action==='entregar') {
                if ($loan['estado']!=='APROBADO' || $loan['fecha_prestamo']!==null || $loan['fecha_reserva_hasta']===null || $loan['fecha_reserva_hasta'] <= $now) $this->fail('La reserva no está aprobada o su plazo de recogida terminó.',409);
                $this->checkCapacity($equipment,$details,$now,$loanId);
                $limit=$data['fecha_limite']??$loan['fecha_devolucion_solicitada']??'';
                if (is_string($limit) && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$limit)) $limit=$this->date($limit,'La fecha límite').' '.substr($now,11);
                elseif (is_string($limit) && preg_match('/^(\d{4}-\d{2}-\d{2}) ([0-2]\d):([0-5]\d):([0-5]\d)$/D',$limit,$match)) {
                    $this->date($match[1],'La fecha límite'); if ((int)$match[2]>23) $this->fail('La hora de devolución no es válida.');
                } else $this->fail('La fecha límite no es válida.');
                if ($limit < $now || $limit > $clock['maximo']) $this->fail('La devolución debe ser desde hoy y hasta un mes calendario después de la entrega.');
                $this->query('UPDATE prestamos SET id_estado_prestamo=?,id_encargado_entrega=?,fecha_prestamo=?,fecha_limite=? WHERE id_prestamo=?',[$this->state('ENTREGADO'),$user,$now,$limit,$loanId]);
                foreach ($details as $detail) $this->query('UPDATE detalle_prestamo SET id_condicion_entrega=? WHERE id_detalle=?',[$equipment[(int)$detail['id_equipo']]['id_condicion'],$detail['id_detalle']]);
                $this->notify($owner,$loanId,'ENTREGADO','La entrega de tus equipos quedó registrada. Consulta tu fecha límite de devolución.');
            } elseif ($action==='devolver') {
                if ($loan['fecha_prestamo']===null || !in_array($loan['estado'],['ENTREGADO'],true)) $this->fail('Solo se puede devolver un préstamo entregado y pendiente de devolución.',409);
                $returns=$this->rows($data['devoluciones']??null,'Las devoluciones'); $byId=[]; foreach($details as $detail) $byId[(int)$detail['id_detalle']]=$detail;
                $seen=[];$events=[];$receipts=[];
                $receiver=$this->query('SELECT nombres,apellidos FROM usuarios WHERE id_usuario=?',[$user])->fetch();
                if(!$receiver)$this->fail('El responsable de recepción ya no existe.',409);
                // JSON-quoted text stays readable while separating user-entered line breaks from audit entries.
                $quoted=function(string $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);};
                $receiverName=trim($receiver['nombres'].' '.$receiver['apellidos']);
                foreach($returns as $returned) {
                    $detailId=$this->integer($returned['id_detalle']??null,'El detalle',1,2147483647);
                    if(isset($seen[$detailId])||!isset($byId[$detailId])) $this->fail('Cada devolución debe corresponder a un detalle distinto de este préstamo.');
                    $seen[$detailId]=true;$detail=$byId[$detailId];
                    $quantity=$this->integer($returned['cantidad']??null,'La cantidad devuelta');
                    $damaged=$this->integer($returned['cantidad_fuera_servicio']??0,'La cantidad fuera de servicio',0);
                    if($damaged>$quantity) $this->fail('La cantidad dañada no puede superar la cantidad devuelta.');
                    $newQuantity=(int)$detail['cantidad_devuelta']+$quantity;
                    if($newQuantity>(int)$detail['cantidad']||$detail['fecha_devolucion']!==null) $this->fail('La devolución supera la cantidad pendiente.',409);
                    $condition=$this->integer($returned['id_condicion_recepcion']??null,'La condición de recepción',1,2147483647);
                    $conditionName=$this->query('SELECT nombre FROM condiciones_equipo WHERE id_condicion=?',[$condition])->fetchColumn();
                    if($conditionName===false) $this->fail('La condición de recepción no existe.');
                    $memo=$this->text($returned['observaciones']??'','Las observaciones de devolución');
                    $closed=$newQuantity===(int)$detail['cantidad'];
                    $this->query('UPDATE detalle_prestamo SET cantidad_devuelta=?,fecha_devolucion=?,id_encargado_recepcion=?,id_condicion_recepcion=?,observaciones_devolucion=? WHERE id_detalle=?',[$newQuantity,$closed?$now:null,$closed?$user:null,$closed?$condition:null,$closed?($memo===''?null:$memo):null,$detailId]);
                    if($damaged>0) {
                        $id=(int)$detail['id_equipo'];$equipment[$id]['cantidad_fuera_servicio']=(int)$equipment[$id]['cantidad_fuera_servicio']+$damaged;
                        if($equipment[$id]['cantidad_fuera_servicio']>(int)$equipment[$id]['cantidad_total']) $this->fail('La cantidad fuera de servicio excedería el inventario.',409);
                        $this->query('UPDATE equipos SET cantidad_fuera_servicio=? WHERE id_equipo=?',[$equipment[$id]['cantidad_fuera_servicio'],$id]);
                    }
                    $receipts[]='['.$now.'] Recepción; responsable: '.$quoted($receiverName).'; equipo: '.$quoted($equipment[(int)$detail['id_equipo']]['nombre']).' (#'.$detail['id_equipo'].'); cantidad recibida: '.$quantity.'; fuera de servicio: '.$damaged.'; condición: '.$quoted((string)$conditionName).'; observaciones: '.$quoted($memo).'.';
                    $byId[$detailId]['cantidad_devuelta']=$newQuantity;$events[]=$detailId.':'.$newQuantity;
                }
                $previous=(string)($loan['observaciones']??'');
                $audit=$previous.($previous===''?'':"\n\n").implode("\n",$receipts);
                if(strlen($audit)>65535)$this->fail('El historial de recepciones alcanzaría el límite de almacenamiento. No se registró esta devolución; contacta al administrador.',409);
                $this->query('UPDATE prestamos SET observaciones=? WHERE id_prestamo=?',[$audit,$loanId]);
                $remaining=0;foreach($byId as $detail)$remaining+=(int)$detail['cantidad']-(int)$detail['cantidad_devuelta'];
                if($remaining===0)$this->query('UPDATE prestamos SET id_estado_prestamo=? WHERE id_prestamo=?',[$this->state('DEVUELTO'),$loanId]);
                sort($events);$this->notify($owner,$loanId,'DEVOLUCION_'.substr(hash('sha256',implode(',',$events)),0,20),$remaining===0?'Todos los equipos del préstamo fueron devueltos.':'Se registró una devolución parcial de tus equipos.');
            } else {
                if(!in_array($loan['estado'],['PENDIENTE','APROBADO'],true)||$loan['fecha_prestamo']!==null) $this->fail('Solo se pueden cancelar solicitudes pendientes o reservas sin entregar.',409);
                if($loan['estado']==='APROBADO' && ($loan['fecha_reserva_hasta']===null || $loan['fecha_reserva_hasta'] <= $now)) $this->fail('La reserva ya venció y no se puede cancelar.',409);
                $this->query('UPDATE prestamos SET id_estado_prestamo=?,fecha_decision=? WHERE id_prestamo=?',[$this->state('CANCELADO'),$now,$loanId]);
                $this->notify($owner,$loanId,'CANCELADO','La solicitud de préstamo fue cancelada.');
            }
            $this->db->commit(); return ['id_prestamo'=>$loanId,'accion'=>$action];
        } catch(Throwable $e) { if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }
    private function request(array $data,int $user): array {
        $rows=$this->rows($data['detalles']??null,'Los equipos');$details=[];
        foreach($rows as $row){$id=$this->integer($row['id_equipo']??null,'El equipo',1,2147483647);if(isset($details[$id]))$this->fail('No repitas equipos en una solicitud.');$details[$id]=['id_equipo'=>$id,'cantidad'=>$this->integer($row['cantidad']??null,'La cantidad solicitada')];}
        $date=$this->date($data['fecha_devolucion_solicitada']??null,'La devolución solicitada');$memo=$this->text($data['observaciones']??'','Las observaciones');
        $this->db->beginTransaction();
        try {
            $equipment=$this->lockEquipment(array_keys($details));$clock=$this->clock();
            if($date<$clock['hoy'])$this->fail('La devolución solicitada no puede ser anterior a hoy.');
            $this->checkCapacity($equipment,array_values($details),$clock['ahora']);
            $this->query('INSERT INTO prestamos(id_usuario,id_estado_prestamo,observaciones,fecha_solicitud,fecha_devolucion_solicitada,version_reglas) VALUES(?,?,?,?,?,2)',[$user,$this->state('PENDIENTE'),$memo===''?null:$memo,$clock['ahora'],$date]);
            $id=(int)$this->db->lastInsertId();
            foreach($details as $detail)$this->query('INSERT INTO detalle_prestamo(id_prestamo,id_equipo,cantidad,cantidad_devuelta) VALUES(?,?,?,0)',[$id,$detail['id_equipo'],$detail['cantidad']]);
            $this->notifyAdmins($id);$this->db->commit();return ['id_prestamo'=>$id,'accion'=>'solicitar'];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
