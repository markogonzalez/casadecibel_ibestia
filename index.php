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

// ⚙️ ADMIN
if ($modulo === 'admin') {
    $seccion = $daba->cleanQuery($seccion ?? 'home');
    // Requiere login de administrador
    if (!isset($_SESSION['intermedica'])) {
        header("Location: ".ruta_relativa."login.php");
        exit;
    }
    $smarty->assign('permisos', $_SESSION['permisos']);
	$smarty->assign('usuario', $_SESSION['usuario']);
    $smarty->assign('menu', $_SESSION['menu']);
    // 
    $nombre = $_SESSION['usuario']["nombre"];
    $perfil_nombre = $_SESSION['usuario']["perfil"];
    $partes = explode(" ", $nombre);
    $iniciales = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
    $smarty->assign('iniciales', $iniciales);
    $smarty->assign('nombre', $nombre);
    $smarty->assign('perfil_nombre', $perfil_nombre);
    if (!$validarNombre($seccion)) {
        http_response_code(403);
        die('Sección inválida');
    }

    $ruta_tpl = "plantillas/admin/{$seccion}.tpl";

    if (!file_exists($ruta_tpl)) {
        http_response_code(404);
        $smarty->template_dir = 'plantillas/admin/';
        $smarty->display("404.tpl");
        exit;
    }

    // Cambiar ruta de plantillas a admin
    $smarty->template_dir = 'plantillas/admin/';
    $smarty->assign("item", $item);
    $smarty->display("plantillas/admin/header.tpl");
	$smarty->display($ruta_tpl);
	$smarty->display("plantillas/admin/footer.tpl");
    exit;
}

if($item || $opcion=="servicios"){

    $categorias = [];
    $qry = "SELECT * FROM web_catalogo_categorias WHERE activo = 1 ORDER BY orden ASC";
    $res = $daba->query($qry);

    while ($cat = $res->fetch_assoc()) {
        $cat['servicios'] = [];

        // 2️⃣ Obtener servicios por categoría
        $qryServ = "
            SELECT id_servicio, servicio, slug, descripcion, imagen 
            FROM web_catalogo_servicios 
            WHERE activo = 1 AND id_categoria = {$cat['id_categoria']} 
            ORDER BY servicio ASC
        ";
        $resServ = $daba->query($qryServ);
        while ($srv = $resServ->fetch_assoc()) {
            $cat['servicios'][] = $srv;
        }

        $categorias[] = $cat;
    }

    // 3️⃣ Si hay un item (slug), obtener detalle del servicio
    $servicio = null;
    $cat_abierta = null;
    if ($item) {
        $slug = $daba->cleanQuery($item);
        $qry = "
            SELECT s.*, c.categoria AS categoria_nombre,c.id_categoria
            FROM web_catalogo_servicios s 
            INNER JOIN web_catalogo_categorias c ON c.id_categoria = s.id_categoria 
            WHERE s.slug = '$slug' AND s.activo = 1
            LIMIT 1
        ";
        $res = $daba->query($qry);
        if ($res->num_rows > 0) {
            $servicio = $res->fetch_assoc();
        }
        $cat_abierta = $servicio['id_categoria'];
    }

    $smarty->assign('cat_abierta', $cat_abierta);
    $smarty->assign('categorias', $categorias);
    $smarty->assign('servicio', $servicio);
    $smarty->assign('item', $item);
    
}

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