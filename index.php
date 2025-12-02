<?php
header("X-Frame-Options: DENY");
header("Content-Security-Policy: frame-ancestors 'none'");
header("Referrer-Policy: no-referrer");

include_once("config.inc.php");

if (is_string($smarty)) {
    die($smarty);
}

if (!isset($_SESSION)) {
    session_start();
}

include_once core . "database.php";
$daba = new database();

// Variables por GET
$modulo  = $_GET['modulo'] ?? null; // admin
$seccion = $_GET['seccion'] ?? null;
$item    = $_GET['item'] ?? null;
$opcion  = $_GET['op'] ?? "home";
// Validación de nombres
$validarNombre = function($str) {
    return preg_match("/^([a-zA-Z0-9_-]+)$/", $str);
};
$smarty->assign('anio', date("Y"));
$smarty->assign('time', time());
$smarty->assign('empresa', NOMBRE_EMPRESA);
$smarty->assign('logo', LOGO_EMPRESA);
$smarty->assign('ruta_js', ruta_js);
$smarty->assign('extencion_js', extencion_js);
$smarty->assign('entorno', entorno);
$smarty->assign('ruta_relativa', ruta_relativa);

$template = $opcion . ".tpl";

$titulos = [
    'home' => 'Inicio | Ibesia HL',
    '404' => 'Página no encontrada | Ibesia HL'
];

$descripciones = [
    'home' => 'ÚNICO, HÍBRIDO, BIOTECNOLÓGICO - AH con fórmula única de última generación en tratamientos intraarticulares',
    'nosotros' => '',
    'soluciones' => '',
    'contacto' => '',
    '404' => ''
];

// PÚBLICO
$opcion = $daba->cleanQuery($opcion ?? 'home');
$template = $opcion . ".tpl";
$plantillas = ['home', 'servicios', 'nosotros', 'contacto']; // Agrega aquí tus páginas públicas

if (!$validarNombre($opcion) || !in_array($opcion, $plantillas)) {
    http_response_code(404);
    $smarty->display("plantillas/pagina_web/header.tpl");
    $smarty->display("plantillas/pagina_web/404.tpl");
    $smarty->display("plantillas/pagina_web/footer.tpl");
    exit;
}

// Cargar archivos si existen
$php_file = $opcion . ".php";
if (file_exists($php_file)) {
    include($php_file);
}

$smarty->assign('page_title', $titulos[$opcion]);
$smarty->assign('page_description', $descripciones[$opcion]);
$smarty->display("plantillas/pagina_web/header.tpl");
$smarty->display($template);
$smarty->display("plantillas/pagina_web/footer.tpl");