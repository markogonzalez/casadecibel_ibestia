<?php 

    include_once __DIR__ . '/class_whats.php';

    class bot_intermedica{

        private $whats;
        private $datos_cliente;
        private $interpretacion;
        
        public function __construct($datos_cliente,$interpretacion) {
            @$this->whats = new whats();
            $this->datos_cliente = $datos_cliente;
            $this->interpretacion = $interpretacion;
        } //function __construct

        public function despachar() {
            $intencion = $this->interpretacion;

            $handlers = [
                'Cliente' => fn() => $this->intencionCliente(),
            ];

            ($handlers[$intencion] ?? $handlers['otra'])();
        }

        private function intencionCliente($params = null) {

            list($codigo,$response) = $this->whats->enviarRespuesta([
                "destinatario" => $this->datos_cliente['numero_whats'],
                "tipo" => "text",
                "mensaje" => "Gracias por contactarnos, en breve te contactara un agente.",
                "id_whats" => WABA_ID
            ]);
            if($codigo=="OK"){
                $this->whats->guardarRespuestaWhats([
                    "id_cliente" => $this->datos_cliente['id_cliente'],
                    "mensaje" => $this->interpretacion['respuesta'],
                    "tipo" => "bot",
                    "tipo_whats" => "text",
                    "estado_salida" => "sent",
                    "modulo_origen" => "bot",
                    "mensaje_id_externo" => $response['messages'][0]['id'],
                    "metadata" => $response
                ]);
            }
        }

    }
?>