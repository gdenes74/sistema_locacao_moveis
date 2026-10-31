<?php
/** Catálogo de variantes físicas, consultado sem alterar produtos ou estoque. */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$tokenRecebido = (string)($_SERVER['HTTP_X_MOBEL_COLOR_TOKEN'] ?? '');
$tokenSessao = (string)($_SESSION['mobel_color_catalog_token'] ?? '');
$tokenValido = $tokenRecebido !== '' && $tokenSessao !== '' && hash_equals($tokenSessao, $tokenRecebido);
if (!isLoggedIn() && !$tokenValido) { http_response_code(403); echo json_encode(['erro'=>'Acesso restrito.']); exit; }
$db = (new Database())->getConnection();

// A família legada do sofá sem braços ainda não possui produto_generico_id.
// Identifica-se esse genérico pelo nome exato, sem modificar o cadastro.
$familias = $db->query("SELECT f.id,f.nome,f.slug,COALESCE(f.produto_generico_id,
    CASE WHEN f.slug='sofa-madeira-sem-bracos' THEN
      (SELECT p0.id FROM produtos p0 WHERE p0.nome_produto='Sofá Madeira Sem Braços 4 Lugares Cor A Definir' AND p0.tipo_produto='COMPOSTO' LIMIT 1)
    END) AS generico_id
    FROM familias_variantes f WHERE f.ativo=1 AND f.modo_impacto_estoque='COMPONENTE_COR'")
    ->fetchAll(PDO::FETCH_ASSOC);
$produtos = $db->query("SELECT p.id,p.nome_produto,p.cor,p.foto_path,p.subcategoria_id
    FROM produtos p WHERE p.tipo_produto='COMPOSTO'
      AND EXISTS (SELECT 1 FROM produto_composicao pc JOIN produtos capa ON capa.id=pc.produto_filho_id
                  WHERE pc.produto_pai_id=p.id AND pc.quantidade>0 AND capa.tipo_produto='COMPONENTE' AND capa.nome_produto LIKE 'Capa %')")
    ->fetchAll(PDO::FETCH_ASSOC);
$cores = $db->query('SELECT id,nome FROM cores_padrao WHERE ativo=1')->fetchAll(PDO::FETCH_ASSOC);
$aliases = $db->query('SELECT cor_padrao_id,alias_original,familia_variante_id FROM cor_aliases WHERE ativo=1')->fetchAll(PDO::FETCH_ASSOC);
$porNome=[];
foreach ($cores as $cor) $porNome[MotorCores::normalizar((string)$cor['nome'])][]=(int)$cor['id'];
foreach ($aliases as $alias) if ($alias['familia_variante_id'] === null) $porNome[MotorCores::normalizar((string)$alias['alias_original'])][]=(int)$alias['cor_padrao_id'];
$obterGenerico=$db->prepare('SELECT id,nome_produto,subcategoria_id,foto_path FROM produtos WHERE id=? AND tipo_produto=\'COMPOSTO\'');
$resultado=[];
foreach ($familias as $familia) {
    if (!$familia['generico_id']) continue;
    $obterGenerico->execute([(int)$familia['generico_id']]);
    $generico=$obterGenerico->fetch(PDO::FETCH_ASSOC);
    if (!$generico) continue;
    $prefixo=preg_replace('/\s+cor\s+a\s+definir\s*$/iu','',(string)$generico['nome_produto']);
    $prefixo=MotorCores::normalizar($prefixo);
    if ($prefixo==='') continue;
    $variantes=[];
    foreach ($produtos as $produto) {
        if ((int)$produto['subcategoria_id'] !== (int)$generico['subcategoria_id']) continue;
        $nome=MotorCores::normalizar((string)$produto['nome_produto']);
        // MotorCores::normalizar separa as palavras com hífen. A variante
        // precisa começar pelo mesmo nome-base do produto "Cor A Definir".
        if (strpos($nome,$prefixo.'-') !== 0) continue;
        $corTexto=trim((string)$produto['cor']);
        if ($corTexto === '' || MotorCores::normalizar($corTexto)==='a-definir') continue;
        $ids=array_values(array_unique($porNome[MotorCores::normalizar($corTexto)] ?? []));
        $corId=count($ids)===1 ? $ids[0] : null;
        $nomes=[$corTexto];
        if ($corId) foreach ($aliases as $alias) {
            if ((int)$alias['cor_padrao_id']===$corId && ($alias['familia_variante_id']===null || (int)$alias['familia_variante_id']===(int)$familia['id'])) $nomes[]=$alias['alias_original'];
        }
        $variantes[]=['id'=>(int)$produto['id'],'nome'=>$produto['nome_produto'],'cor'=>$corTexto,
            'foto'=>$produto['foto_path'],'aliases'=>array_values(array_unique($nomes))];
    }
    usort($variantes,static fn($a,$b)=>strcoll($a['cor'],$b['cor']));
    $resultado[]=['id'=>(int)$familia['id'],'nome'=>$familia['nome'],
        'generico'=>['id'=>(int)$generico['id'],'nome'=>$generico['nome_produto'],'foto'=>$generico['foto_path']],
        'variantes'=>$variantes];
}
echo json_encode(['familias'=>$resultado],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
