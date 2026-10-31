<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';

$db = (new Database())->getConnection();
$motor = new MotorCores($db);
$familia = null;
foreach ($motor->listarFamilias() as $item) {
    if ($item['slug'] === 'cama-thai' && (int)$item['ativo'] === 1) $familia = $item;
}
if (!$familia || $familia['nome'] !== 'Cama Thai' || (int)$familia['produto_generico_id'] !== 59) {
    fwrite(STDERR, "FALHA: Cama Thai não está disponível como tipo de mestre ligado ao produto genérico.\n");
    exit(1);
}

$produtos = array_values(array_filter($motor->listarCatalogoProdutos(), static fn($p) =>
    $p['tipo_produto'] === 'COMPOSTO' && str_starts_with($p['nome_produto'], 'Cama Thai Madeira 1,60 x 0,70 Ass.')
));
if (count($produtos) < 2) {
    fwrite(STDERR, "FALHA: produtos montados da Cama Thai não estão no catálogo do mestre.\n");
    exit(1);
}
foreach ($produtos as $produto) {
    if (stripos($produto['nome_produto'], $familia['nome']) === false) {
        fwrite(STDERR, "FALHA: o produto #{$produto['produto_id']} não seleciona a família Cama Thai.\n");
        exit(1);
    }
    if ($produto['categoria_nome'] !== 'Cama Thai' || $produto['subcategoria_nome'] !== 'Estofado cor') {
        fwrite(STDERR, "FALHA: o produto #{$produto['produto_id']} não está na categoria Cama Thai.\n");
        exit(1);
    }
}

$capas = $db->query("SELECT p.id AS produto_id, p.cor AS cor_produto, f.nome_produto AS nome_capa, f.cor AS cor_capa
    FROM produtos p JOIN produto_composicao pc ON pc.produto_pai_id=p.id
    JOIN produtos f ON f.id=pc.produto_filho_id
    WHERE p.tipo_produto='COMPOSTO' AND p.nome_produto LIKE 'Cama Thai Madeira 1,60 x 0,70 Ass.%'
      AND f.tipo_produto='COMPONENTE' AND f.nome_produto LIKE 'Capa %'")->fetchAll(PDO::FETCH_ASSOC);
$capasPorProduto = [];
$grupos = [];
foreach ($capas as $capa) {
    $id=(int)$capa['produto_id'];
    $capasPorProduto[$id][]=$capa;
    $cor=MotorCores::normalizar((string)$capa['cor_capa']);
    $grupo=MotorCores::normalizar((string)$capa['nome_capa']);
    if ($cor !== MotorCores::normalizar((string)$capa['cor_produto']) || !str_ends_with($grupo, '-'.$cor)) {
        fwrite(STDERR, "FALHA: a capa do produto #$id não corresponde à cor do produto.\n");
        exit(1);
    }
    $grupos[substr($grupo,0,-strlen($cor)-1)]=true;
}
foreach ($produtos as $produto) {
    if (trim((string)$produto['cor']) === '') continue; // Cor A Definir usa serviço de capa.
    if (count($capasPorProduto[(int)$produto['produto_id']] ?? []) !== 1) {
        fwrite(STDERR, "FALHA: o produto #{$produto['produto_id']} precisa de uma capa de cor.\n");
        exit(1);
    }
}
if (count($grupos)!==1) {
    fwrite(STDERR, "FALHA: capas da Cama Thai não compartilham o mesmo formato para o mestre.\n");
    exit(1);
}
echo '[OK] Cama Thai aparece no tipo de mestre e na categoria própria; ' . count($produtos) . ' produtos montados, ' . count($capas) . " capas compatíveis.\n";
