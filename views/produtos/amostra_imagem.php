<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
$tokenRecebido = (string)($_SERVER['HTTP_X_MOBEL_COLOR_TOKEN'] ?? $_GET['token'] ?? '');
$tokenSessao = (string)($_SESSION['mobel_color_catalog_token'] ?? '');
$tokenValido = $tokenRecebido !== '' && $tokenSessao !== '' && hash_equals($tokenSessao, $tokenRecebido);
if (!isLoggedIn() && !$tokenValido) {
    http_response_code(403);
    exit;
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$pdo = (new Database())->getConnection();
$stmt = $pdo->prepare('SELECT arquivo_path, aprovada FROM amostras_cor WHERE id = ? AND ativo = 1');
$stmt->execute([$id ?: 0]);
$amostra = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$amostra || (!(int)$amostra['aprovada'] && !hasAccess('admin'))) {
    http_response_code(404);
    exit;
}
$relativo = $amostra['arquivo_path'];
$raiz = realpath(__DIR__ . '/../../assets/uploads/motor_cores/amostras');
$arquivo = $relativo ? realpath(__DIR__ . '/../../' . $relativo) : false;
if (!$raiz || !$arquivo || strncmp($arquivo, $raiz . DIRECTORY_SEPARATOR, strlen($raiz) + 1) !== 0 || !is_file($arquivo)) {
    http_response_code(404);
    exit;
}
$info = getimagesize($arquivo);
if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $info['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($arquivo);
