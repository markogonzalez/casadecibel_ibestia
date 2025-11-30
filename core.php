<?php
// Permitir que cualquier origen haga peticiones
header("Access-Control-Allow-Origin: *");
// Permitir estos métodos (POST, GET, OPTIONS)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Permitir estos encabezados (incluyendo Authorization si lo usas)
header("Access-Control-Allow-Headers: Content-Type, Authorization");
include_once("config.inc.php");
include_once(core."core.php");
$core = new Core;
$core->service();

?>