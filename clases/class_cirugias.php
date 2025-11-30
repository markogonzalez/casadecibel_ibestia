<?php 
	include_once 'utilidades.php';

    class cirugias extends utilidades{
        
        private $id_modulo = 0;
        private $permisos = [];
        private $permisosModulo = [];

        public function __construct() {
            parent::__construct();

            $this->id_modulo = 8;
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $perfilId = $_SESSION['usuario']['perfil_id'] ?? 0;
            // Obtener permisos desde DB
            $perm = $this->setPermisosPerfil([
                "id_modulo" => $this->id_modulo,
                "perfil_id" => $perfilId
            ]);
            $this->permisos = $perm['permisos'] ?? [];
            $this->permisosModulo = $perm['modulo'] ?? [];
            $_SESSION['permisos'] = $this->permisos;
            $_SESSION['permisosModulo']  = $this->permisosModulo;
            session_write_close();

        }

        public function getCirugias($params=null){

            $codigo = "OK";
            $mensaje = "";
            $elementos = [];

            $query = "SELECT 
                e.id_cirugia_encuesta,
                c.proyecto,
                a.nombre AS asesor,
                m.nombre AS medico,
                m.telefono,
                c.paciente,
                c.hospital,
                DATE_FORMAT(c.hora_inicio, '%d/%m/%y %H:%i') AS hora_inicio,
                DATE_FORMAT(c.hora_fin, '%d/%m/%y %H:%i') AS hora_cierre,
                e.estado,
                e.intentos,
                e.error_msg,
                r.id_encuesta_respuesta
            FROM cirugias_encuestas e
            INNER JOIN master_cirugias c ON c.id_cirugia = e.id_cirugia
            INNER JOIN catalogo_asesor a ON c.id_asesor = a.id_asesor
            INNER JOIN catalogo_medico m ON c.id_medico = m.id_medico
            LEFT JOIN encuestas_respuestas r ON e.id_cirugia_encuesta = r.id_cirugia_encuesta
            ORDER BY e.hora_programada ASC";

            $result = $this->query($query);
            if($result->num_rows > 0){
                while ($cirugias = $result->fetch_assoc()) {
                    $btn_reenviar = "";
                    if ($cirugias['estado'] == 'error') {
                        $btn_reenviar = '<button class="btn btn-sm btn-warning reenviar-encuesta" data-id_cirugia_encuesta="'.$cirugias['id_cirugia_encuesta'].'">Reintentar</button>';
                    }
                    $btn_respuesta = "";
                    if($cirugias["id_encuesta_respuesta"] != null){
                        $btn_respuesta = '<button class="btn btn-sm btn-primary btn_respuesta" data-medico="'.$cirugias['medico'].'" data-id_encuesta_respuesta="'.$cirugias['id_encuesta_respuesta'].'"><i class="fa fa-eye"></i></button>';
                    }



                    $estado = strtolower($cirugias['estado']); // en minúsculas por seguridad
                    $badge = '';

                    switch ($estado) {
                        case 'enviando':
                            $badge = '<span class="badge badge-light-warning">Esperando respuesta</span>';
                            break;
                        case 'pendiente':
                            $badge = '<span class="badge badge-light-warning">Pendiente</span>';
                            break;
                        case 'error':
                            $badge = '<span class="badge badge-light-danger">Error</span>';
                            break;
                        case 'respondida':
                            $badge = '<span class="badge badge-light-success">Respondida</span>';
                            break;
                        case 'intentos_superados':
                            $badge = '<span class="badge badge-light-dark">Cancelada</span>';
                            break;
                        default:
                            $badge = '<span class="badge badge-light-secondary">Desconocido</span>';
                            break;
                    }

                    $cirugias['estado'] = $badge;

                    $cirugias['acciones'] = $btn_respuesta.$btn_reenviar;
                    $elementos[] = $cirugias;
                }
            }else{
                $codigo = "ERR";
                $mensaje = "Sin resultados que mostrar";
            }
            
            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje,"cirugias"=>$elementos));

        }

        public function guardarCirugias($params=null){
            $codigo = "OK";
            $mensaje = "";
            $elementos = [];
            $errores = 0;
            $detalle_errores ="";

            $data = json_decode($params['cirugias'], true);

            foreach ($data as $fila) {
                
                $medico = $this->cleanQuery($fila['medico']);
                $asesor = $this->cleanQuery($fila['asesor']);
                $id_asesor = $this->obtenerIdCatalogo('asesor', 'nombre', $asesor);
                $id_medico = $this->obtenerIdCatalogo('medico', 'nombre', $medico,$fila['telefono']);
                $paciente = $this->cleanQuery($fila['paciente']);
                $proyecto= $this->cleanQuery($fila['proyecto']);
                $hospital = $this->cleanQuery($fila['hospital']);
                $hora_inicio = $this->cleanQuery($fila['hora_inicio']);
                $hora_cierre = $this->cleanQuery($fila['hora_cierre']);
                $dt = new DateTime($hora_cierre);
                // Sumamos 1 hora
                $dt->modify('+1 hour');
                $hora_envio = $dt->format('Y-m-d H:i:s');
                
                $query = "INSERT INTO `master_cirugias` (`proyecto`, `id_asesor`, `id_medico`, `paciente`, `hospital`, `hora_inicio`, `hora_fin`) VALUES ('".$proyecto."', '".$id_asesor."', '".$id_medico."', '".$paciente."', '".$hospital."', '".$hora_inicio."', '".$hora_cierre."')";
                if(!$this->query($query)){
                    $errores++;
                    $detalle_errores.="Error al guardar ".$proyecto."\n.";
                }else{

                    $id_cirugia = $this->conexMySQL->insert_id;
                    $query_encuesta = "INSERT INTO `cirugias_encuestas` (`id_cirugia`, `id_medico`,`id_asesor`,`hora_programada`) VALUES ($id_cirugia, $id_medico, $id_asesor,'$hora_envio')";

                    if(!$this->query($query_encuesta)){
                        $errores++;
                        $detalle_errores.="Error al guardar la encuesta de ".$proyecto."\n.";
                    }else{
                        $id_encuesta = $this->conexMySQL->insert_id;

                        $hash = $this->generarHashEncuesta($id_encuesta);
                        // Actualizar registro con el hash
                        $update = "UPDATE cirugias_encuestas SET hash = '$hash' WHERE id_cirugia_encuesta = $id_encuesta";
                        $this->query($update);
                    }
                }
                
            }

            if($errores>0){
                $codigo="ERR";
            }
            
            return [$codigo,["mensaje"=>$mensaje,"errores"=>$detalle_errores]];

        }

        public function obtenerIdCatalogo($tabla, $columna, $valor, $telefono = null) {
            // Buscar si ya existe
            $res = $this->query("SELECT id_$tabla as id_tabla FROM catalogo_$tabla WHERE $columna = '$valor'");
            if ($res->num_rows > 0) {
                $response = $res->fetch_assoc();
                $id = $response["id_tabla"];
            } else {
                // Insertar nuevo
                if ($tabla === 'medico' && $telefono !== null) {
                    $this->query("INSERT INTO catalogo_medico ($columna, telefono) VALUES ('$valor', '$telefono')");
                } else {
                    $this->query("INSERT INTO catalogo_$tabla ($columna) VALUES ('$valor')");
                }

                $id = $this->conexMySQL->insert_id;
            }

            return $id;
        }

        public function getRespuestas($params=null){
            $codigo = "OK";
            $mensaje = "";
            $respuestas = [];
            $errores = 0;
            $detalle_errores ="";

            $id_encuesta_respuesta = isset($params["id_encuesta_respuesta"]) ? $this->cleanQuery($params["id_encuesta_respuesta"]) : 0;
            
            $query = "SELECT * FROM encuestas_respuestas WHERE id_encuesta_respuesta = $id_encuesta_respuesta";
            $res = $this->query($query);
            if($res && $res->num_rows > 0){
                $respuestas = $res->fetch_assoc();
            }else{
                $codigo = "ERR";
                $mensaje = "Error al obtener las respuestas de la encuesta.";
            }
            
            return [$codigo,["respuestas"=>$respuestas,"mensaje"=>$mensaje]];

        }


    }

?>