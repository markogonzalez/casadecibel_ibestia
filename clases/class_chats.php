<?php 
    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class chats extends utilidades{
        
        private $id_modulo = 0;
        private $permisos = [];
        private $permisosModulo = []; 
        private $whats = null;

        public function __construct() {
            parent::__construct();

            $this->id_modulo = 6;
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
            $_SESSION['permisos']                = $this->permisos;
            $_SESSION['permisosModulo']  = $this->permisosModulo;
            // error_log(print_r($this->permisos,true));
            $menu = $this->generarMenu($this->permisos);
            // error_log(print_r($menu,true));
            $_SESSION['menu'] = $menu[0]["estructura"];
            // Evita bloqueos si tienes muchas peticiones concurrentes
            session_write_close();
            $this->whats = new whats();

        }

        public function getChats($params = null){

            $codigo = "OK";
            // 1. Lista de chats
            $queryChats = "SELECT c.*,ch.fecha_envio,ch.mensaje,ch.id_mensaje
                FROM negocio_clientes c 
                LEFT JOIN (
                    SELECT nc1.id_cliente, nc1.fecha_envio, nc1.mensaje,nc1.id_mensaje
                    FROM negocio_chats nc1
                    INNER JOIN (
                        SELECT id_cliente, MAX(id_mensaje) AS max_id
                        FROM negocio_chats
                        GROUP BY id_cliente
                    ) nc2 ON nc1.id_cliente = nc2.id_cliente AND nc1.id_mensaje = nc2.max_id
                ) ch ON c.id_cliente = ch.id_cliente
            ORDER BY c.fecha_ultima_interaccion DESC";

            $resChats = $this->query($queryChats);
            if (!$resChats || $resChats->num_rows == 0) return ["ERR",["mensaje"=>"Sin chats"]];

            $queryNoLeidos = "SELECT id_cliente, COUNT(*) AS no_leidos 
                FROM negocio_chats 
                WHERE tipo = 'cliente' AND leido = 0 
                GROUP BY id_cliente";

            $resNoLeidos = $this->query($queryNoLeidos);
            $noLeidosPorCliente = [];

            if ($resNoLeidos && $resNoLeidos->num_rows > 0) {
                while ($row = $resNoLeidos->fetch_assoc()) {
                    $noLeidosPorCliente[$row["id_cliente"]] = $row["no_leidos"];
                }
            }
            while ($row = $resChats->fetch_assoc()) {
                $row["nombre_mostrar"] = $row["numero_whats"];
                if($row["actualizado"]==1){
                    $row["nombre_mostrar"] = $row["nombre_whats"];
                }
                $row["fecha_iso"] = date("c", strtotime($row['fecha_envio']));
                $row["no_leidos"] = $noLeidosPorCliente[$row["id_cliente"]] ?? 0;
                $chats[] = $row;
            }

            return [$codigo,$chats];
        }

        public function getChatsCliente($params = null){

            $codigo = "OK";
            $id_mensaje    = $this->cleanQuery($params["id_mensaje"] ?? 0);
            $id_cliente    = $this->cleanQuery($params["id_cliente"] ?? 0);
            $condicion = "";
            $chats = null;

            // 1. Lista de chats
            if($id_mensaje > 0){
                $condicion = " AND nc.id_mensaje < ".$id_mensaje;
            }
            $queryChats = "SELECT nc.id_mensaje,nc.mensaje,nc.tipo,nc.fecha_envio,
                cl.nombre_whats,numero_whats,nc.estado_salida,nc.metadata,nc.tipo_whats
                FROM negocio_chats nc INNER JOIN negocio_clientes cl ON nc.id_cliente = cl.id_cliente
                WHERE nc.id_cliente = ".$id_cliente.$condicion." ORDER BY nc.id_mensaje DESC LIMIT 10";

            $resChats = $this->query($queryChats);

            while ($row = $resChats->fetch_assoc()) {
                $row["fecha_iso"] = date("c", strtotime($row['fecha_envio']));
                $dt = new DateTime($row["fecha_iso"]);
                $row["fecha_mostrar"] = $dt->format('H:i');
                $chats[] = $row;
            }

            return [$codigo,$chats];
        }

        public function guardarChats($params = null){

            $codigo = "OK";
            $destinatario = isset($params["destinatario"]) ? $params["destinatario"] : "";
            $id_whats = WABA_ID;
            $mensaje = isset($params["mensaje"]) ? $params["mensaje"] : "";
            $id_cliente = isset($params["id_cliente"]) ? $params["id_cliente"] : 0;
            
            list($codigoMensaje,$response) = $this->whats->enviarRespuesta([
                "destinatario" => $destinatario,
                "tipo" => "text",
                "mensaje" => $mensaje,
                "id_whats" => $id_whats
            ]);

            if($codigoMensaje=="OK"){
                list($codigoGuardar,$id_mensaje) =$this->guardarRespuestaWhats([
                    "id_cliente" => $id_cliente,
                    "mensaje" => $mensaje,
                    "tipo" => "usuario",
                    "modulo_origen" => "usuario",
                    "tipo_whats" => "texto",
                    "mensaje_id_externo" => $response['messages'][0]['id'],0,
                    "metadata" => $response
                ]);
                if($codigoGuardar!="OK"){
                    $codigo = "ERR";
                    $response = "No se pudo guardar el mensaje";
                }
            }else{
                $codigo = "ERR";
                $response = "No se pudo enviar el mensaje";
            }
            

            return [$codigo,["id_mensaje"=>$id_mensaje,"mensaje"=>$response]];
        }

        public function guardarRespuestaWhats($params = null) {
            
            $codigo = "OK";
            $id_mensaje = 0;
            $id_cliente = isset($params["id_cliente"]) ? $this->cleanQuery($params["id_cliente"]) : 0;
            $mensaje = isset($params["mensaje"]) ? $this->cleanQuery($params["mensaje"]) : "";
            $tipo = isset($params["tipo"]) ? $this->cleanQuery($params["tipo"]) : "";
            $modulo_origen = isset($params["modulo_origen"]) ? $this->cleanQuery($params["modulo_origen"]) : "";
            $tipo_whats = isset($params["tipo_whats"]) ? $this->cleanQuery($params["tipo_whats"]) : "";
            $id_usuario = isset($params["id_usuario"]) ? $this->cleanQuery($params["id_usuario"]) : 0;
            $mensaje_id_externo = isset($params["mensaje_id_externo"]) ? $this->cleanQuery($params["mensaje_id_externo"]) : "";
            $estado_salida = isset($params["estado_salida"]) ? $this->cleanQuery($params["estado_salida"]) : "";
            $respuesta_interactiva = isset($params["respuesta_interactiva"]) ? $this->cleanQuery($params["respuesta_interactiva"]) : 0;
            $metadata = isset($params["metadata"]) ? json_encode($params["metadata"]) : null;

            if($mensaje!=""){
                $qry_insert = "INSERT INTO negocio_chats (
                    id_cliente,
                    mensaje,
                    tipo,
                    modulo_origen,
                    tipo_whats,
                    id_usuario,
                    mensaje_id_externo,
                    estado_salida,
                    respuesta_interactiva,
                    metadata,
                    fecha_envio
                ) VALUES (
                    $id_cliente, 
                    '$mensaje',
                    '$tipo',
                    '$modulo_origen',
                    '$tipo_whats', 
                    $id_usuario,
                    '$mensaje_id_externo',
                    '$estado_salida',
                    $respuesta_interactiva,
                    '$metadata',
                    '".date('Y-m-d H:i:s')."')";
    
                try {
                    $this->query($qry_insert);
                    $id_cliente = $this->conexMySQL->insert_id;
                } catch (Exception $e) {
                    error_log("Error al guardar la respuesta: " . $e->getMessage());
                    $codigo = "ERR";
                }
            }


            return[$codigo,$id_cliente];
        }

        public function marcarLeidos($params){
            $codigo = "OK";
            $data = "OK";
            $id_cliente = $this->cleanQuery($params["id_cliente"]);
            $sql = "UPDATE negocio_chats 
                    SET leido = 1 
                    WHERE id_cliente = $id_cliente AND tipo = 'cliente' AND leido = 0";
            if(!$this->query($sql)){
                $codigo = "ERR";
                $data = "Ocurrio un error al actualizar status de leido";
            }
            return [$codigo,$data];
        }

        public function intervenirChat($params){
            $codigo = "OK";
            $data = "OK";
            $id_cliente = $this->cleanQuery($params["id_cliente"]);
            $atencion = $this->cleanQuery($params["atencion"]);
            $sql = "UPDATE negocio_clientes 
                SET atencion = '$atencion' 
                WHERE id_cliente = $id_cliente";
            if(!$this->query($sql)){
                $codigo = "ERR";
                $data = "Ocurrio un error al actualizar status de atencion";
            }
            return [$codigo,$data];
        }

        public function asignarNombre($params = null) {
            
            $codigo = "OK";
            $mensaje = "";
            $id_cliente = isset($params["id_cliente"]) ? $this->cleanQuery($params["id_cliente"]) : 0;
            $nombre_contacto = isset($params["nombre_contacto"]) ? $this->cleanQuery($params["nombre_contacto"]) : "";

            if($nombre_contacto!=""){
                $qry = "UPDATE negocio_clientes SET nombre_whats = '$nombre_contacto', actualizado = 1 WHERE id_cliente = $id_cliente";
                try {
                    $this->query($qry);
                } catch (Exception $e) {
                    error_log("Error al actualizar el nombre del contacto: " . $e->getMessage());
                    $codigo = "ERR";
                    $mensaje = "Error al actualizar el nombre del contacto";
                }
            }


            return[$codigo,$mensaje];
        }

    }

?>