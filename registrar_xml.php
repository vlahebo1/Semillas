<?php
/**
 * registrar_xml.php
 * Recibe el nombre del guardián, el nombre de la semilla y los códigos
 * hexadecimales de ambas paletas, y agrega un <registro> al archivo
 * compartido registros.xml en esta misma carpeta. Se llama una vez por
 * cada ficha generada, junto al guardado del PDF y los audios.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

function limpiarTexto($v, $max = 200) {
    $v = is_string($v) ? $v : '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

function limpiarHex($lista) {
    $out = [];
    if (is_array($lista)) {
        foreach ($lista as $h) {
            $h = is_string($h) ? strtoupper(trim($h)) : '';
            if (preg_match('/^#[0-9A-F]{6}$/', $h)) {
                $out[] = $h;
            }
        }
    }
    return $out;
}

$guardianName = limpiarTexto($data['guardianName'] ?? '');
$seedName     = limpiarTexto($data['seedName'] ?? '');
$guardianHex  = limpiarHex($data['guardianHex'] ?? []);
$seedHex      = limpiarHex($data['seedHex'] ?? []);

if ($guardianName === '' && $seedName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan nombre de guardián y de semilla']);
    exit;
}

$xmlFile = __DIR__ . '/registros.xml';

$doc = null;
if (file_exists($xmlFile)) {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $loaded = @$doc->load($xmlFile);
    if (!$loaded || !$doc->documentElement || $doc->documentElement->tagName !== 'registros') {
        // archivo inexistente, vacío o corrupto: se reconstruye para no perder el servicio
        $doc = null;
    }
}
if ($doc === null) {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement('registros'));
}

$root = $doc->documentElement;

$registro = $doc->createElement('registro');
$registro->setAttribute('fecha', date('c'));

$gEl = $doc->createElement('guardian');
$gEl->setAttribute('nombre', $guardianName);
foreach ($guardianHex as $hex) {
    $gEl->appendChild($doc->createElement('color', $hex));
}
$registro->appendChild($gEl);

$sEl = $doc->createElement('semilla');
$sEl->setAttribute('nombre', $seedName);
foreach ($seedHex as $hex) {
    $sEl->appendChild($doc->createElement('color', $hex));
}
$registro->appendChild($sEl);

$root->appendChild($registro);

$bytesWritten = $doc->save($xmlFile);

if ($bytesWritten === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo escribir registros.xml']);
    exit;
}

echo json_encode([
    'ok' => true,
    'totalRegistros' => $root->getElementsByTagName('registro')->length
]);
