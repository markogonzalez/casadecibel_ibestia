<?php 
    include_once("config.inc.php");
	include_once 'utilidades.php';

    class home extends utilidades{
        public function __construct() {
            parent::__construct();
        } //function __construct
        
        public function getProyectos($params = null){

            $elementos = [];
            $codigo = "OK";

            $qry = "SELECT * FROM master_proyectos WHERE activo = 1 ORDER BY RAND() LIMIT 4";
            $result = $this->query($qry);
            if($result->num_rows > 0){
                while($proyecto = $result->fetch_assoc()) {
                    $elementos[] = $proyecto;
                }
            }

            return array(0 => $codigo, 1 => $elementos);

        }
    }

?>