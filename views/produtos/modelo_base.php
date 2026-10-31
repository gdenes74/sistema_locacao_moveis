<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
$tokenRecebido = (string)($_SERVER['HTTP_X_MOBEL_COLOR_TOKEN'] ?? $_GET['token'] ?? '');
$tokenSessao = (string)($_SESSION['mobel_color_catalog_token'] ?? '');
$tokenValido = $tokenRecebido !== '' && $tokenSessao !== '' && hash_equals($tokenSessao, $tokenRecebido);
if (!isLoggedIn() && !$tokenValido) { http_response_code(403); exit; }

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$parte = (string)($_GET['parte'] ?? 'base');
if (!$id || !in_array($parte, ['base', 'mascara'], true)) { http_response_code(404); exit; }

$db = (new Database())->getConnection();
$stmt = $db->prepare('SELECT arquivo_base, arquivo_mascara, ativo, status_geometria, status_recorte FROM modelos_imagem WHERE id = ?');
$stmt->execute([$id]);
$modelo = $stmt->fetch(PDO::FETCH_ASSOC);
$revisao = (string)($_GET['revisao'] ?? '') === '1';
if ($revisao && !hasAccess('admin')) { http_response_code(403); exit; }
if (!$modelo || !(int)$modelo['ativo'] || (!$revisao && ($modelo['status_geometria'] !== 'APROVADA' || $modelo['status_recorte'] !== 'APROVADO'))) {
    http_response_code(404); exit;
}

$relativo = (string)($parte === 'base' ? $modelo['arquivo_base'] : $modelo['arquivo_mascara']);
$prefixo = 'assets/uploads/motor_cores/modelos/';
if ($relativo === '' || !str_starts_with($relativo, $prefixo) || str_contains($relativo, '..')) {
    http_response_code(404); exit;
}
$raiz = realpath(__DIR__ . '/../../assets/uploads/motor_cores/modelos');
$arquivo = realpath(__DIR__ . '/../../' . $relativo);
if ($raiz === false || $arquivo === false || !str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR)) {
    http_response_code(404); exit;
}
$dados = getimagesize($arquivo);
if (!$dados || $dados['mime'] !== 'image/png') { http_response_code(404); exit; }

header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($arquivo);
