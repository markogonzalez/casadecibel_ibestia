<?php 
	include_once 'utilidades.php';

    class encuestas extends utilidades{
        
        private $id_modulo = 0;
        private $permisos = [];
        private $permisosModulo = [];

        public function __construct() {
            parent::__construct();

            $this->id_modulo = 4;
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

        public function resumenGeneral() {
            $resumen = [
                'total_enviadas' => 0,
                'total_respondidas' => 0,
                'porcentaje_respuesta' => 0,
                'satisfaccion' => [
                    'Insumos' => 0,
                    'Entrega' => 0,
                    'Instrumentista' => 0,
                    'Vendedor' => 0
                ],
                'top_vendedores' => [],
                'ultimas_respuestas' => [],
                'historico' => [],
            ];

            // Totales
            $q1 = "SELECT COUNT(*) as total FROM cirugias_encuestas WHERE intentos > 0";
            $q2 = "SELECT COUNT(*) as total FROM cirugias_encuestas WHERE respondida = 1";

            $resumen['total_enviadas'] = $this->query($q1)->fetch_assoc()['total'] ?? 0;
            $resumen['total_respondidas'] = $this->query($q2)->fetch_assoc()['total'] ?? 0;
            $resumen['porcentaje_respuesta'] = $resumen['total_enviadas'] > 0
                ? round(($resumen['total_respondidas'] / $resumen['total_enviadas']) * 100, 2)
                : 0;

            // Promedios de satisfacción 
            foreach (['pregunta_1', 'pregunta_2', 'pregunta_3', 'pregunta_4'] as $campo) {
                $q = "SELECT AVG(CAST(LEFT($campo, 1) AS UNSIGNED)) as promedio FROM encuestas_respuestas";
                if($campo=="pregunta_1"){
                    $campo_texto = "Insumos";
                }elseif($campo=="pregunta_2"){
                    $campo_texto = "Entrega";
                }elseif($campo=="pregunta_3"){
                    $campo_texto = "Instrumentista";
                }elseif($campo=="pregunta_4"){
                    $campo_texto = "Vendedor";
                }
                $resumen['satisfaccion'][$campo_texto] = round($this->query($q)->fetch_assoc()['promedio'] ?? 0, 2);
            }

            // Top hospitales por satisfacción (puedes afinar con joins)
            $top = "SELECT
                a.nombre AS vendedor,
                COUNT(*) AS total_encuestas,
                AVG(CAST(LEFT(er.pregunta_2, 1) AS UNSIGNED)) AS promedio
                FROM encuestas_respuestas er
                JOIN cirugias_encuestas ce ON ce.id_cirugia_encuesta = er.id_cirugia_encuesta
                JOIN catalogo_asesor a ON ce.id_asesor = a.id_asesor
                GROUP BY a.id_asesor
                ORDER BY promedio ASC
                LIMIT 5";
            $resumen['top_vendedores'] = $this->query($top)->fetch_all(MYSQLI_ASSOC);

            // Últimas respuestas
            $ultimas = "SELECT e.*, c.hospital, m.nombre AS medico
                        FROM encuestas_respuestas r
                        JOIN cirugias_encuestas e ON e.id_cirugia_encuesta = r.id_cirugia_encuesta
                        JOIN master_cirugias c ON c.id_cirugia = e.id_cirugia
                        JOIN catalogo_medico m ON m.id_medico = c.id_medico
                        ORDER BY e.fecha_respuesta DESC
                        LIMIT 5";
            $resumen['ultimas_respuestas'] = $this->query($ultimas)->fetch_all(MYSQLI_ASSOC);
            $promedioGeneral = round(array_sum($resumen['satisfaccion']) / count($resumen['satisfaccion']), 2);
            $resumen["promedio"] = $promedioGeneral;

            // Historico
            $historico = $this->historicoEncuestas();
            $resumen["historico"] = $historico[1];
            return ['OK', $resumen];
        }

        public function guardarEncuesta($params=null){
            $codigo = "OK";
            $mensaje = "";
            $elementos = [];
            $errores = 0;
            $detalle_errores ="";

            $hash = isset($params["hash"]) ? $this->cleanQuery($params["hash"]) : "";
            $pregunta_1 = isset($params["pregunta_1"]) ? $this->cleanQuery($params["pregunta_1"]) : "";
            $detalle_pregunta_1 = isset($params["detalle_pregunta_1"]) ? $this->cleanQuery($params["detalle_pregunta_1"]) : "";
            $pregunta_2 = isset($params["pregunta_2"]) ? $this->cleanQuery($params["pregunta_2"]) : "";
            $pregunta_3 = isset($params["pregunta_3"]) ? $this->cleanQuery($params["pregunta_3"]) : "";
            $pregunta_4 = isset($params["pregunta_4"]) ? $this->cleanQuery($params["pregunta_4"]) : "";
            $detalle_pregunta_4 = isset($params["detalle_pregunta_4"]) ? $this->cleanQuery($params["detalle_pregunta_4"]) : "";
            $comentarios = isset($params["comentarios"]) ? $this->cleanQuery($params["comentarios"]) : "";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $navegador = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $query = "SELECT * FROM cirugias_encuestas WHERE hash = '".$hash."' AND respondida = 0";
            $res = $this->query($query);
            if($res && $res->num_rows > 0){
                $encuesta = $res->fetch_assoc();
                $qry_insert = "INSERT INTO `encuestas_respuestas` (
                    `id_cirugia_encuesta`,
                    `pregunta_1`,
                    `detalle_pregunta_1`,
                    `pregunta_2`,
                    `pregunta_3`,
                    `pregunta_4`,
                    `detalle_pregunta_4`,
                    `comentarios`,
                    `ip_respuesta`,
                    `navegador`) VALUES (
                    ".$encuesta['id_cirugia_encuesta'].",
                    '".$pregunta_1."',
                    '".$detalle_pregunta_1."',
                    '".$pregunta_2."',
                    '".$pregunta_3."',
                    '".$pregunta_4."',
                    '".$detalle_pregunta_4."',
                    '".$comentarios."',
                    '".$ip."',
                    '".$navegador."')";
                try {
                    $res_insert = $this->query($qry_insert);
                    if ($res_insert) {
                        $qry_update = "UPDATE cirugias_encuestas SET respondida = 1, estado ='respondida' WHERE id_cirugia_encuesta = " . $encuesta['id_cirugia_encuesta'];
                        $res_update = $this->query($qry_update);
                        if (!$res_update) {
                            $errores++;
                            $detalle_errores .= "Error al actualizar la encuesta como respondida.\n";
                        }
                    } else {
                        $errores++;
                        $detalle_errores .= "Error al insertar las respuestas.\n";
                    }
                } catch (\Throwable $th) {
                    $errores++;
                    $detalle_errores .= "Excepción capturada: " . $th->getMessage() . "\n";
                }

            }else{
                $codigo = "ERR";
                $mensaje = "Esta encuesta no existe o ya ha sido respondida.";
            }

            if ($errores > 0) {
                $codigo = "ERR";
                if (empty($mensaje)) {
                    $mensaje = "Se produjeron errores al guardar la encuesta.";
                }
            }
            
            return [$codigo,["mensaje"=>$mensaje,"errores"=>$detalle_errores]];

        }

        public function obtenerIdCatalogo($tabla, $columna, $valor) {
            
            $res=$this->query("SELECT id_$tabla as id_tabla FROM catalogo_$tabla WHERE $columna = '".$valor."'");
            if($res->num_rows > 0){
                $response = $res->fetch_assoc();
                $id = $response["id_tabla"];
            }else{
                $res=$this->query("INSERT INTO catalogo_$tabla ($columna) VALUES ('".$valor."')");
                $id = $this->conexMySQL->insert_id;
            }

            return $id;
        }

        public function historicoEncuestas($params = null) {
            // Default 7 días
            $dias = isset($params["dias"]) ? $this->cleanQuery($params["dias"]) : 7;

            $query = "
                SELECT 
                    DATE(fecha_envio) AS fecha,
                    COUNT(*) AS enviadas,
                    SUM(CASE WHEN respondida = 1 THEN 1 ELSE 0 END) AS respondidas
                FROM cirugias_encuestas
                WHERE fecha_envio >= DATE_SUB(CURDATE(), INTERVAL {$dias} DAY)
                GROUP BY DATE(fecha_envio)
                ORDER BY fecha DESC
            ";

            $res = $this->query($query);
            $datos = [];
            while($row = $res->fetch_assoc()) {
                $datos[] = $row;
            }

            return [
                "OK",
                $datos
            ];
        }


    }

?>