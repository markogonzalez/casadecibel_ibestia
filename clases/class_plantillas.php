<?php 
    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class plantillas extends utilidades{
        
        private $id_modulo = 0;
        private $permisos = [];
        private $permisosModulo = [];
        private $whats = null;

        public function __construct() {
            parent::__construct();

            $this->id_modulo = 7;
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

        public function getPlantillas($params = null){
            
            $data = [];
            list($codigo,$response) = $this->whats->getPlantillas();
            if($codigo=="OK"){
                $plantillas = $response['data'];
                foreach ($plantillas as $plantilla) {
                    // Agregar condiciones de meta 
                    $boton_editar = "";
                    if($_SESSION['permisos'][$this->id_modulo]['u']){
                        $boton_editar = '<button data-id_plantilla="'.$plantilla["id"].'" class="btn btn-icon btn-primary btn-sm btn-editar"><i class="ki-solid ki-notepad-edit fs-1"></i></button>';
                    }
                    $data[] = [
                        "nombre"    => $plantilla['name'],
                        "categoria" => $plantilla["category"],
                        "lenguaje" => $plantilla["language"],
                        "estado" => $plantilla["status"],
                        "acciones" => $boton_editar,
                    ];

                }

            }
            return [$codigo,["plantillas"=>$data]];
        }

        public function guardarPlantilla($params = null){
            // Acepta JSON directo del router o POST normal (fallback)
            if ($params === null) {
                $raw = file_get_contents('php://input');
                $params = json_decode($raw, true) ?: $_POST;
            }
            $plantilla = json_decode($params["plantilla"],true) ?? '';
            // error_log("plantilla");
            // error_log(print_r($plantilla,true));
            $name = trim($plantilla['name'] ?? '');
            $category = trim($plantilla['category'] ?? '');
            $language = trim($plantilla['language'] ?? '');
            $components = $plantilla['components']    ?? [];

            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                return ['ERR', ['mensaje' => 'Nombre inválido. Usa minúsculas, números y guion bajo.']];
            }
            $validCats = ['AUTHENTICATION','MARKETING','UTILITY'];
            if (!in_array($category, $validCats, true)) {
                return ['ERR', ['mensaje' => 'Categoría inválida.']];
            }
            if ($language === '') {
                return ['ERR', ['mensaje' => 'Idioma requerido.']];
            }
            if (!is_array($components) || empty($components)) {
                return ['ERR', ['mensaje' => 'Debes incluir al menos BODY en components.']];
            }

            // (Opcional) Normalización o reglas adicionales
            // - BODY obligatorio
            $tieneBody = false;
            foreach ($components as $c) {
                if (isset($c['type']) && strtoupper($c['type']) === 'BODY') { $tieneBody = true; break; }
            }
            if (!$tieneBody) {
                return ['ERR', ['mensaje' => 'El componente BODY es obligatorio.']];
            }

            list($codigo,$response) = $this->whats->crearPlantilla([
                'name'       => $name,
                'category'   => $category,
                'language'   => $language,
                'components' => $components
            ]);

            return [$codigo,$response];
        }


    }

?>