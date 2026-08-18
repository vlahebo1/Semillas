<?php
/**
 * guardar.php
 * Recibe archivos (PDF de la ficha, audio del guardián, caja musical) enviados
 * desde index.html y los guarda en el servidor local, dentro de guardados/<guardian>/.
 *
 * Requiere un servidor con PHP corriendo (Apache/Nginx+PHP-FPM, o el servidor
 * embebido: php -S localhost:8000). No funciona si abres index.html como
 * archivo local (file://) — el navegador necesita hacer una petición HTTP real.
 */

header('Content-Type: application/json; charset=utf-8');

// GET simple: usado por la página para comprobar que el servidor PHP está activo.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'servicio' => 'guardar.php', 'estado' => 'activo']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió ningún archivo válido']);
    exit;
}

// --- Sanitizar el nombre de archivo solicitado ---
$nombreSolicitado = isset($_POST['nombre']) ? $_POST['nombre'] : $_FILES['archivo']['name'];
$nombreSolicitado = basename($nombreSolicitado);
$nombreLimpio = preg_replace('/[^A-Za-z0-9._-]/', '', $nombreSolicitado);

if ($nombreLimpio === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nombre de archivo inválido']);
    exit;
}

// --- Solo se permiten los tipos de archivo que esta aplicación genera ---
$extensionesPermitidas = ['pdf', 'webm', 'wav'];
$extension = strtolower(pathinfo($nombreLimpio, PATHINFO_EXTENSION));
if (!in_array($extension, $extensionesPermitidas, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Extensión de archivo no permitida: .' . $extension]);
    exit;
}

// --- Carpeta por guardián (para no mezclar expedientes de distintas personas) ---
$guardianSolicitado = isset($_POST['guardian']) ? strtolower($_POST['guardian']) : 'guardian';
$guardianLimpio = preg_replace('/[^a-z0-9-]/', '', $guardianSolicitado);
if ($guardianLimpio === '') {
    $guardianLimpio = 'guardian';
}

$baseDir = __DIR__ . '/guardados';
$targetDir = $baseDir . '/' . $guardianLimpio;

if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta de destino']);
        exit;
    }
}

// --- Si ya existe un archivo con ese nombre, se agrega un sufijo con fecha/hora ---
$rutaDestino = $targetDir . '/' . $nombreLimpio;
if (file_exists($rutaDestino)) {
    $info = pathinfo($nombreLimpio);
    $sufijo = date('Ymd-His');
    $nombreLimpio = $info['filename'] . '-' . $sufijo . '.' . $info['extension'];
    $rutaDestino = $targetDir . '/' . $nombreLimpio;
}

if (move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
    echo json_encode([
        'ok' => true,
        'archivo' => $nombreLimpio,
        'ruta' => 'guardados/' . $guardianLimpio . '/' . $nombreLimpio,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor']);
}
