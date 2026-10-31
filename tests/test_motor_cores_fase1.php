<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';

$pdo = (new Database())->getConnection();
$falhas = [];
$ok = static function (bool $condicao, string $mensagem) use (&$falhas): void {
    echo ($condicao ? '[OK] ' : '[FALHA] ') . $mensagem . PHP_EOL;
    if (!$condicao) $falhas[] = $mensagem;
};

$tabelas = ['cor_aliases','familias_variantes','produto_variantes','amostras_cor','modelos_imagem','variantes_imagem','historico_motor_cores'];
foreach ($tabelas as $tabela) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:tabela');
    $stmt->execute([':tabela'=>$tabela]);
    $ok((int)$stmt->fetchColumn() === 1, "Tabela {$tabela} existe");
}

$colunas = ['slug_normalizado','familia_operacional','hex_referencia','automatico_permitido','observacoes','atualizado_em'];
foreach ($colunas as $coluna) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name="cores_padrao" AND column_name=:coluna');
    $stmt->execute([':coluna'=>$coluna]);
    $ok((int)$stmt->fetchColumn() === 1, "cores_padrao.{$coluna} existe");
}

$ok(MotorCores::normalizar('  Azul Bebê  ') === 'azul-bebe', 'Normaliza acento e espaços');
$ok(MotorCores::normalizar('Off White') === 'off-white', 'Normaliza nome composto');

$mapa = $pdo->query("SELECT a.alias_normalizado, c.slug_normalizado
                     FROM cor_aliases a INNER JOIN cores_padrao c ON c.id=a.cor_padrao_id
                     WHERE a.alias_normalizado IN ('preta','branca','areia','azul-claro')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
$ok(($mapa['preta'] ?? null) === 'preto', 'Preta resolve para Preto');
$ok(($mapa['branca'] ?? null) === 'branco', 'Branca resolve para Branco');
$ok(($mapa['areia'] ?? null) === 'bege', 'Areia resolve para Bege');
$ok(($mapa['azul-claro'] ?? null) === 'azul-bebe', 'Azul Claro resolve para Azul Bebê');

$distintas = $pdo->query("SELECT COUNT(DISTINCT slug_normalizado) FROM cores_padrao WHERE slug_normalizado IN ('azul-bebe','azul-celeste')")->fetchColumn();
$ok((int)$distintas === 2, 'Azul Bebê e Azul Celeste permanecem distintos');
$fendi = $pdo->query("SELECT automatico_permitido FROM cores_padrao WHERE slug_normalizado='fendi'")->fetchColumn();
$ok((int)$fendi === 0, 'Fendi exige revisão e não é automatizado');

// Escrita de integração sempre revertida: comprova FKs e ausência de resíduo.
$marcador = 'teste-motor-cores-' . bin2hex(random_bytes(4));
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO familias_variantes
        (nome,slug,modo_impacto_estoque,politica_preco,permite_cor_livre,exige_revisao)
        VALUES ('Teste automatizado',:slug,'SEM_IMPACTO_FISICO','MESMO_PRECO',0,1)");
    $stmt->execute([':slug'=>$marcador]);
    $familiaId = (int)$pdo->lastInsertId();
    $corId = (int)$pdo->query("SELECT id FROM cores_padrao WHERE slug_normalizado='preto'")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO cor_aliases (cor_padrao_id,alias_original,alias_normalizado,familia_variante_id)
                           VALUES (:cor,'Teste Preto',:alias,:familia)");
    $stmt->execute([':cor'=>$corId, ':alias'=>$marcador, ':familia'=>$familiaId]);
    $ok((int)$pdo->query("SELECT COUNT(*) FROM cor_aliases WHERE alias_normalizado=" . $pdo->quote($marcador))->fetchColumn() === 1, 'Escrita relacionada funciona dentro de transação');
} finally {
    $pdo->rollBack();
}
$stmt = $pdo->prepare('SELECT COUNT(*) FROM familias_variantes WHERE slug=:slug');
$stmt->execute([':slug'=>$marcador]);
$ok((int)$stmt->fetchColumn() === 0, 'Teste de escrita foi totalmente revertido');

$motor = new MotorCores($pdo);
$resumo = $motor->resumo();
$ok(($resumo['cores_padrao'] ?? 0) >= 17, 'Resumo administrativo lê as cores confirmadas');

if ($falhas) {
    fwrite(STDERR, PHP_EOL . count($falhas) . " teste(s) falharam.\n");
    exit(1);
}
echo PHP_EOL . "MATRIZ_FASE1_APROVADA\n";
