<?php 
include_once 'utilidades.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class whats extends utilidades {

    private $client;
    private $headers;

    public function __construct() {
        parent::__construct();

        $this->client = new \GuzzleHttp\Client();
        $this->headers = [
           'Authorization' => 'Bearer ' .WHATS_TOKEN,
            'Content-Type'  => 'application/json'
        ];
        
    }

    public function procesarWebhookWhatsApp($params = null) {
        $data = isset($params["data"]) ? json_decode($params["data"], true) : false;
        if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) return;

        $mensaje = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $mensaje_id = $mensaje['id'] ?? '';
        if ($this->yaFueProcesado($mensaje_id)) return;

        // Obtener número del negocio al que escribieron
        $numero_negocio = substr($data['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'],3) ?? '';
        // Datos del cliente que escribió
        $nombre = $data['entry'][0]['changes'][0]['value']['contacts'][0]["profile"]["name"] ?? '';
        $numero = $mensaje['from'];
        $texto = strtolower(trim($mensaje['text']['body'] ?? ''));

        // Detectar tipo de mensaje
        $tipo = $mensaje['type'] ?? 'text';
        $texto = '';
        $metadata_extra = [];

        switch ($tipo) {
            case 'text':
                $texto = $mensaje['text']['body'] ?? '';
                break;

            case 'image':
                $media_id = $mensaje['image']['id'] ?? null;
                $mime = $mensaje['image']['mime_type'] ?? 'image/jpeg';
                $caption = $mensaje['image']['caption'] ?? '';
                $url_descarga = $media_id ? $this->descargarMediaWhatsApp($media_id, $mime) : null;

                $texto = $caption ?: '[Imagen]';
                $metadata_extra = [
                    "media_id" => $media_id,
                    "mime_type" => $mime,
                    "url" => $url_descarga
                ];
                break;

            case 'document':
                $media_id = $mensaje['document']['id'] ?? null;
                $nombre_archivo = $mensaje['document']['filename'] ?? 'documento';
                $mime = $mensaje['document']['mime_type'] ?? 'application/octet-stream';
                $url_descarga = $media_id ? $this->descargarMediaWhatsApp($media_id, $mime) : null;

                $texto = "[Documento: {$nombre_archivo}]";
                $metadata_extra = [
                    "media_id" => $media_id,
                    "filename" => $nombre_archivo,
                    "mime_type" => $mime,
                    "url" => $url_descarga
                ];
                break;

            case 'video':
                $media_id = $mensaje['video']['id'] ?? null;
                $mime = $mensaje['video']['mime_type'] ?? 'video/mp4';
                $caption = $mensaje['video']['caption'] ?? '';
                $url_descarga = $media_id ? $this->descargarMediaWhatsApp($media_id, $mime) : null;

                $texto = $caption ?: '[Video]';
                $metadata_extra = [
                    "media_id" => $media_id,
                    "mime_type" => $mime,
                    "url" => $url_descarga
                ];
                break;

            case 'audio':
                $media_id = $mensaje['audio']['id'] ?? null;
                $mime = $mensaje['audio']['mime_type'] ?? 'audio/ogg';
                $url_descarga = $media_id ? $this->descargarMediaWhatsApp($media_id, $mime) : null;

                $texto = '[Audio]';
                $metadata_extra = [
                    "media_id" => $media_id,
                    "mime_type" => $mime,
                    "url" => $url_descarga
                ];
                break;

            case 'sticker':
                $media_id = $mensaje['sticker']['id'] ?? null;
                $mime = $mensaje['sticker']['mime_type'] ?? 'image/webp';
                $url_descarga = $media_id ? $this->descargarMediaWhatsApp($media_id, $mime) : null;

                $texto = '[Sticker]';
                $metadata_extra = [
                    "media_id" => $media_id,
                    "mime_type" => $mime,
                    "url" => $url_descarga
                ];
                break;

            default:
                $texto = "[Tipo de mensaje no soportado: {$tipo}]";
                break;
        }


        // 3. Consultar ChatGPT para interpretar el mensaje
        // $interpretacion = $this->openAI->interpretarConChatGPT(["mensaje" => $texto]);
        // if ($interpretacion[0]!="OK") return;

        // Buscar o crear cliente
        $datos_cliente = $this->obtenerOInsertarCliente([
            "numero" => $numero,
            "nombre" => $nombre, 
            "texto" => $texto,
            "intencion" => "Cliente",
        ]);

        // if ($mensaje['type'] === 'interactive' && $mensaje['interactive']['type'] === 'nfm_reply') {
        //     $this->guardarFlujo($datos_cliente, $mensaje);
        //     $estado = "Ejecutivo";
        // }

        list($codigo,$id_mensaje) =$this->guardarRespuestaWhats([
            "id_cliente" => $datos_cliente['id_cliente'],
            "mensaje" => $texto,
            "tipo" => "cliente",
            "modulo_origen" => "Encuestas",
            "tipo_whats" => $mensaje['type'],
            "mensaje_id_externo" => $mensaje_id,
            "respuesta_interactiva" => isset($mensaje['interactive']) ? 1 : 0,
            "metadata" => [
                "numero_contacto" => $numero,
                "raw" => $mensaje,
                "media" => $metadata_extra
            ]
        ]);
        $fechaISO = date("c", strtotime(date("Y-m-d H:i:s")));
        $now = new DateTime();
        // Día de la semana (1 = lunes, 7 = domingo)
        $dia = (int)$now->format('N');
        $hora_actual = (int)$now->format('H'); // solo la hora
        $minuto_actual = (int)$now->format('i');

        // Verificar si estamos fuera de horario (domingo o fuera de 8:00–18:00)
        if ($dia == 7 || $hora_actual < 8 || $hora_actual >= 18) {
            list($codigo_bot,$response) =$this->enviarRespuesta([
                "destinatario" => $datos_cliente['numero_whats'],
                "tipo" => "text",
                "mensaje" => "¡Hola! Gracias por comunicarte con *Inter Médica Solutions*.\n\nNuestro horario de atención es de 8:00 hrs a 18:00 hrs, de lunes a sábado.\n\nEn este momento estamos fuera de nuestra jornada, pero no te preocupes, hemos registrado tu mensaje y te responderemos a primera hora.\n\nAgradecemos tu paciencia.",
                "id_whats" => WABA_ID
            ]);
            $this->guardarRespuestaWhats([
                "id_cliente" => $datos_cliente['id_cliente'],
                "mensaje" => "¡Hola! Gracias por comunicarte con *Inter Médica Solutions*.\n\nNuestro horario de atención es de 8:00 hrs a 18:00 hrs, de lunes a sábado.\n\nEn este momento estamos fuera de nuestra jornada, pero no te preocupes, hemos registrado tu mensaje y te responderemos a primera hora.\n\nAgradecemos tu paciencia.",
                "tipo" => "bot",
                "modulo_origen" => "WhatsApp Fuera de horario",
                "tipo_whats" => "text",
                "metadata" => $response
            ]);

            return;
        }
        $horas = new DateTime($fechaISO);
        if($codigo=="OK" && $id_mensaje > 0){
            $this->emitirSocket(
                "mensajeRecibido",
                "sala_cliente_".$datos_cliente['id_cliente'],
                [
                    "id_mensaje" => $id_mensaje,
                    "id_cliente" => $datos_cliente['id_cliente'],
                    "mensaje" => $texto,
                    "fecha_iso" => $fechaISO,
                    "fecha_mostrar" => $horas->format('H:i'),
                    "tipo" => "cliente",
                    "numero_whats" => $datos_cliente['numero_whats'],
                    "tipo_whats" => $tipo,
                    "metadata" => [
                        "media" => $metadata_extra
                    ]
                ]
            );

            // if($datos_cliente['atencion']=="bot"){
            //     $clase_bot = "bot_intermedica";
            //     $archivo_clase = __DIR__ . "/class_bot_intermedica.php";

            //     if (file_exists($archivo_clase)) {
            //         include_once($archivo_clase);
            //         if(class_exists($clase_bot)){
            //             $bot = new $clase_bot($datos_cliente, "Cliente");
            //             $bot->despachar();
            //         }else{
            //             error_log("Clase no encontrada: $clase_bot");
            //         }
            //     }else{
            //         error_log("Archivo de clase no encontrado: $archivo_clase");
            //     }
            // }

        }
    }

    private function descargarMediaWhatsApp($media_id, $mime = 'application/octet-stream') {
        try {
            $mime = trim(explode(';', $mime)[0]); // 👈 elimina " ; codecs=opus"
            // 1️⃣ Obtener URL temporal de descarga desde Meta
            $urlMeta = "https://graph.facebook.com/" . WHATS_VERSION . "/{$media_id}";
            list($codigo, $response) = $this->request('GET', $urlMeta);

            if ($codigo !== "OK" || !isset($response['url'])) {
                error_log("❌ No se obtuvo la URL del media_id: {$media_id}");
                return null;
            }

            $urlDescarga = $response['url'];

            // 2️⃣ Descargar el binario directamente usando el mismo método request
            $responseFile = $this->client->get($urlDescarga, [
                'headers' => ['Authorization' => 'Bearer ' . WHATS_TOKEN],
                'stream'  => true,
                'allow_redirects' => [
                    'max'             => 5,
                    'strict'          => true,
                    'referer'         => true,
                    'protocols'       => ['https'],
                    'track_redirects' => true
                ]
            ]);

            // 3️⃣ Guardar archivo local
            $ext = explode('/', $mime)[1] ?? 'dat';
            $nombreArchivo = "media_" . uniqid() . "." . $ext;
            $rutaLocal = __DIR__ . "/../uploads/{$nombreArchivo}";
            $destino = fopen($rutaLocal, 'w');

            while (!$responseFile->getBody()->eof()) {
                fwrite($destino, $responseFile->getBody()->read(1024));
            }

            fclose($destino);

            // 4️⃣ Retornar URL pública relativa
            return "/uploads/{$nombreArchivo}";

        } catch (\Exception $e) {
            error_log("⚠️ Error al descargar media WhatsApp ({$media_id}): " . $e->getMessage());
            return null;
        }
    }

    public function procesarWebhookStatus($params = []) {
        $data_raw = $params["data"] ?? "";
        $json = json_decode($data_raw, true);
        // error_log("Procesando webhook de estado: " . print_r($json, true));
        $statusData = $json["entry"][0]["changes"][0]["value"]["statuses"] ?? [];
        // Obtener número del negocio al que escribieron
        $numero_negocio = substr($json['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'],3) ?? '';
        $negocio = $this->getNegocio(["numero_negocio"=>$numero_negocio]); // debes crear esta función

        $datos_cliente = $this->obtenerOInsertarCliente([
            "numero" => $statusData[0]['recipient_id'],
            "intencion" => "Cliente"
        ]);
        $id_mensaje_externo = $statusData[0]["id"] ?? null;
        $qry = "SELECT id_mensaje FROM negocio_chats WHERE mensaje_id_externo = '$id_mensaje_externo'";

        $res = $this->query($qry);
        $row = $res->fetch_assoc();
        $id_mensaje = $row['id_mensaje'] ?? null;

        foreach ($statusData as $status) {

            $status = $status["status"] ?? null;
            $timestamp = $status["timestamp"] ?? null;
            $conversation_id = $status["conversation"]["id"] ?? null;

            $error_code    = $status["errors"][0]["code"] ?? null;
            $error_title   = $status["errors"][0]["title"] ?? null;
            $error_details = $status["errors"][0]["details"] ?? null;

            // Armar detalle si hubo error
            $detalle_estado = null;
            if ($status === 'failed') {
                $detalle_estado = "Código: $error_code. Título: $error_title. Detalle: $error_details";
            }

            // Actualizar en BD
            if($id_mensaje !== null && $status !== null){
                $sql = "UPDATE negocio_chats 
                    SET estado_salida = '$status', 
                        detalle_estado = '$detalle_estado'
                    WHERE mensaje_id_externo = '$id_mensaje_externo'";

                try {
                    $this->query($sql);
                    $this->emitirSocket(
                        "actualizar_estado_mensaje",
                        "sala_cliente_".$datos_cliente['id_cliente'],
                        [
                            "id_mensaje" => $id_mensaje,
                            "id_cliente" => $datos_cliente['id_cliente'],
                            "estado_salida" => $status,
                        ]
                    );
                } catch (Exception $e) {
                    error_log("Error al guardar la respuesta: " . $e->getMessage());
                    $codigo = "ERR";
                }
            }
        }
    }

    private function request($method, $endpoint, $body = []) {
        $codigo = "OK";
        $data = [];

        try {
            $options = [];
            $options['headers'] = $this->headers;

            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $data = json_decode($response->getBody(), true);
            // error_log("respuesta");
            // error_log(print_r($data,true));

        } catch (RequestException $e) {
            return $this->ErrorWhats($e);
        }


        return [$codigo, $data];
    }

    private function ErrorWhats($e) {
        $codigo = "ERR";
        $mensaje = "Ocurrió un error inesperado al procesar la solicitud";
        $errorMeta = [];

        if ($e->hasResponse()) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            error_log("Error WhatsApp API: " . print_r($body, true));

            $mensaje = $body['error']['error_data']['details'];
            $errorMeta = $body['error'];
        }

        return [
            $codigo,
            [
                "mensaje_error" => $mensaje,
                "error_meta" => $errorMeta
            ]
        ];
    }

    public function enviarRespuesta($params = null) {

        $destinatario = isset($params["destinatario"]) ? $this->normalizarNumeroWhatsapp($params["destinatario"]) : false;
        $mensaje     = isset($params["mensaje"]) ? $params["mensaje"] : "";
        $tipo        = isset($params["tipo"]) ? $params["tipo"] : "";
        $template    = isset($params["template"]) ? $params["template"] : "";
        $variables   = isset($params["variables"]) && is_array($params["variables"]) ? $params["variables"] : [];
        $botones     = isset($params["botones"]) && is_array($params["botones"]) ? $params["botones"] : [];
        $id_whats = WHATS_PHONE_ID;
        $idioma_plantilla = isset($params["idioma_plantilla"]) ? $this->cleanQuery($params["idioma_plantilla"]) : "es_MX";

        $url = "https://graph.facebook.com/".WHATS_VERSION."/".$id_whats."/messages";
        $data = [
            "messaging_product" => "whatsapp",
            "to" => $destinatario,
        ];

        if($tipo === "text"){

            $data["type"] = "text";
            $data["text"] = ["body" => $mensaje];
        
        }elseif ($tipo === "template"){

            if (empty($template)) {
                $codigo = "ERR";
                return [$codigo, ["mensaje_error" => "El nombre del template está vacío"]];
            }

            $params_array = [];
            foreach ($variables as $valor) {
                $params_array[] = [
                    "type" => "text",
                    "text" => $valor
                ];
            }

            $componentes = [];

            if (!empty($params_array)) {
                $componentes[] = [
                    "type" => "body",
                    "parameters" => $params_array
                ];
            }

            if (!empty($botones)) {
                foreach ($botones as $i => $btn) {
                    if (!isset($btn['sub_type']) || !isset($btn['param'])) continue;

                    $subtype = $btn['sub_type'];
                    $param_value = $btn['param'];
                    $param_type = in_array($subtype, ['quick_reply', 'flow']) ? 'payload' : 'text';

                    $componentes[] = [
                        "type" => "button",
                        "sub_type" => $subtype,
                        "index" => (string) $i,
                        "parameters" => [
                            [
                                "type" => $param_type,
                                $param_type => $param_value
                            ]
                        ]
                    ];
                }
            }

            $data["type"] = "template";
            $data["template"] = [
                "name" => $template,
                "language" => ["code" => $idioma_plantilla],
            ];

            if (!empty($componentes)) {
                $data["template"]["components"] = $componentes;
            }

        }else{
            $codigo = "ERR";
            return [$codigo, ["mensaje_error" => "Tipo no valido"]];
        }

        // Enviar la petición con Guzzle
        // error_log(print_r($data,true));
        list($codigoApi,$response) = $this->request("POST", $url, $data);
        return [$codigoApi,$response];
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

    // Plantillas

    public function getPlantillas($params = null){
        
        $nombre_plantilla = isset($params["nombre_plantilla"]) ? $this->cleanQuery($params["nombre_plantilla"]) : "";

        $url = "https://graph.facebook.com/" . WHATS_VERSION . "/" . WABA_ID . "/message_templates";

        list($codigo,$response) = $this->request('GET', $url);
        return [$codigo,$response];
    }

    public function crearPlantilla($params = null){
        
        $name = isset($params["name"]) ? $this->cleanQuery($params["name"]) : "";
        $category = isset($params["category"]) ? $this->cleanQuery($params["category"]) : "";
        $language = isset($params["language"]) ? $this->cleanQuery($params["language"]) : "";
        $components = isset($params["components"]) ? $this->cleanQuery($params["components"]) : [];

        $url = "https://graph.facebook.com/" . WHATS_VERSION . "/" . WABA_ID . "/message_templates";

        list($codigo,$response) = $this->request('POST', $url,[
            'name'       => $name,
            'category'   => $category,
            'language'   => $language,
            'components' => $components
        ]);
        return [$codigo,$response];
    }


    public function actualizarPerfil($params = null){

        $id_whats = isset($params["id_whats"]) ? $this->cleanQuery($params["id_whats"]) : "";
        $about = isset($params["about"]) ? $this->cleanQuery($params["about"]) : "";
        $description = isset($params["description"]) ? $this->cleanQuery($params["description"]) : "";
        $address = isset($params["address"]) ? $this->cleanQuery($params["address"]) : "";
        $email = isset($params["email"]) ? $this->cleanQuery($params["email"]) : "";
        $website = isset($params["website"]) ? $this->cleanQuery($params["website"]) : "";

        $url = "https://graph.facebook.com/" . WHATS_VERSION . "/" . $id_whats . "/whatsapp_business_profile";

        list($codigo, $response) = $this->request('POST', $url, [
            "messaging_product" => "whatsapp",
            "description" => $description,
            "address" => $address,
            "email" => $email,
            "website" => $website,
        ]);

        return [$codigo,$response];
        
    }

    private function yaFueProcesado($mensaje_id) {
        $query = "SELECT 1 FROM mensajes_procesados WHERE mensaje_id = '$mensaje_id'";
        $res = $this->query($query);
        if ($res->num_rows > 0) return true;

        $this->query("INSERT INTO mensajes_procesados (mensaje_id) VALUES ('$mensaje_id')");
        return false;
    }

    public function obtenerOInsertarCliente($params = null) {

        $numero = isset($params["numero"]) ? $this->cleanQuery($params["numero"]) : "";
        $nombre = isset($params["nombre"]) ? $this->cleanQuery($params["nombre"]) : "";
        $intencion = $params["intencion"];
                
        $query = "SELECT activo, id_cliente, espera_flujo,nombre_whats,numero_whats,intencion FROM negocio_clientes WHERE numero_whats = '".$numero."'";
        $res = $this->query($query);

        if ($res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $this->actualizarIntencionWhats([
                "id_cliente" => $data['id_cliente'],
                "intencion" => $intencion,
                "espera_flujo" => ""
            ]);
            return $data;
        }

        $qry_insert = "INSERT INTO negocio_clientes (numero_whats, nombre_whats, intencion) VALUES ('".$numero."', '".$nombre."', '".$intencion."')";
        $this->query($qry_insert);
        $id_cliente = $this->conexMySQL->insert_id;
        return ['intencion' => $intencion, 'id_cliente' => $id_cliente, 'espera_flujo' => null,"nombre_whats"=>$nombre,"numero_whats"=>$numero];
    }

    public function actualizarIntencionWhats($params = null) {

        $id_cliente = $params['id_cliente'];
        $intencion = $params['intencion'];
        $espera_flujo = $params['espera_flujo'];

        $qry_update = "UPDATE negocio_clientes SET intencion = '".$intencion."', fecha_ultima_interaccion = '".date("Y-m-d H:i:s")."', espera_flujo = '".$espera_flujo."' WHERE id_cliente = ".$id_cliente;

        try {
            $this->query($qry_update);
        } catch (Exception $e) {
            error_log("Error al actualizar el estado: " . $e->getMessage());
        }
    }

    // Socket
    function emitirSocket($evento, $sala, $data, $namespace = "intermedica") {
        // error_log("🧪 Emitiendo evento: $evento a la sala $sala con datos: " . json_encode($data));

        // Asegúrate de que sea la ruta correcta
        $url = "https://chat.intermedica.org/api/emitir";

        $payload = json_encode([
            "evento"     => $evento,
            "datos"      => $data,
            "sala"       => $sala,
            "namespace"  => $namespace
        ]);

        // Usa la misma secret que el socket
        $tokenJWT = $this->generarToken([
            "id_cliente" => $data['id_cliente'],
            "perfil_id"  => 4 // Perfil cliente
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $tokenJWT"
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("❌ Error al emitir socket: " . $error);
        }
    }

}
?>
