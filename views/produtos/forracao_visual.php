<?php
/** Dados somente de leitura para cor ilustrativa de móveis forrados sem capa removível. */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$tokenRecebido = (string)($_SERVER['HTTP_X_MOBEL_COLOR_TOKEN'] ?? '');
$tokenSessao = (string)($_SESSION['mobel_color_catalog_token'] ?? '');
$tokenValido = $tokenRecebido !== '' && $tokenSessao !== '' && hash_equals($tokenSessao, $tokenRecebido);
if (!isLoggedIn() && !$tokenValido) { http_response_code(403); echo json_encode(['erro'=>'Acesso restrito.']); exit; }
$db = (new Database())->getConnection();
if (isset($_GET['catalogo'])) {
    $sql = "SELECT f.produto_generico_id AS produto_id, f.id AS familia_id, f.nome
            FROM familias_variantes f JOIN produtos p ON p.id=f.produto_generico_id
            WHERE f.ativo=1 AND f.modo_impacto_estoque='FORRACAO_PRODUCAO'
              AND p.tipo_produto='COMPOSTO' ORDER BY f.nome";
    echo json_encode(['produtos'=>$db->query($sql)->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}
$produtoId = filter_var($_GET['produto_id'] ?? null, FILTER_VALIDATE_INT);
if (!$produtoId) { http_response_code(400); echo json_encode(['erro'=>'Produto inválido.']); exit; }
$familia = $db->prepare("SELECT f.id,f.nome,p.id AS produto_id FROM familias_variantes f
    JOIN produtos p ON p.id=f.produto_generico_id
    WHERE f.ativo=1 AND f.modo_impacto_estoque='FORRACAO_PRODUCAO'
      AND p.tipo_produto='COMPOSTO' AND p.id=? LIMIT 1");
$familia->execute([$produtoId]);
$f = $familia->fetch(PDO::FETCH_ASSOC);
if (!$f) { http_response_code(404); echo json_encode(['erro'=>'Este produto não é uma forração sob demanda.']); exit; }

$modelo = $db->prepare("SELECT id,nome FROM modelos_imagem
    WHERE ativo=1 AND familia_variante_id=? AND produto_origem_id=?
      AND status_geometria='APROVADA' AND status_recorte='APROVADO'
      AND NULLIF(arquivo_mascara,'') IS NOT NULL ORDER BY id DESC LIMIT 1");
$modelo->execute([(int)$f['id'], $produtoId]);
$m = $modelo->fetch(PDO::FETCH_ASSOC) ?: null;

$amostras = $db->query("SELECT a.id,a.cor_padrao_id,a.familia_variante_id,a.area_referencia_json,
        c.nome,c.hex_referencia FROM amostras_cor a
        JOIN cores_padrao c ON c.id=a.cor_padrao_id AND c.ativo=1
        WHERE a.ativo=1 AND a.aprovada=1 ORDER BY a.aprovado_em DESC,a.id DESC")
    ->fetchAll(PDO::FETCH_ASSOC);
$cores = [];
foreach ($amostras as $a) {
    $corId = (int)$a['cor_padrao_id'];
    $escopo = $a['familia_variante_id'] === null ? 1 : ((int)$a['familia_variante_id'] === (int)$f['id'] ? 2 : 0);
    if (!isset($cores[$corId]) || $escopo > $cores[$corId]['escopo']) {
        $area = json_decode((string)($a['area_referencia_json'] ?? ''), true);
        $cores[$corId] = [
            'id'=>$corId, 'nome'=>$a['nome'], 'amostra_id'=>(int)$a['id'],
            'area_referencia'=>is_array($area) && count($area) === 4 ? array_map('floatval', $area) : null,
            'hex'=>$a['hex_referencia'], 'escopo'=>$escopo, 'aliases'=>[],
        ];
    }
}
$aliases = $db->prepare('SELECT cor_padrao_id,alias_original FROM cor_aliases
    WHERE ativo=1 AND (familia_variante_id IS NULL OR familia_variante_id=?)');
$aliases->execute([(int)$f['id']]);
foreach ($aliases as $alias) {
    $corId = (int)$alias['cor_padrao_id'];
    if (isset($cores[$corId])) $cores[$corId]['aliases'][] = $alias['alias_original'];
}
$lista = array_values($cores);
usort($lista, static fn(array $a,array $b): int => strcasecmp($a['nome'], $b['nome']));
foreach ($lista as &$cor) unset($cor['escopo']);
unset($cor);
echo json_encode(['produto_id'=>$produtoId, 'familia'=>$f['nome'], 'modelo'=>$m,
    'cores'=>$lista], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
