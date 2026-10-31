<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';
$pdo = (new Database())->getConnection();
$motor = new MotorCores($pdo);
$amostras = array_values(array_filter($motor->listarAmostras(), static fn($a) => str_contains($a['arquivo_path'], 'verde_bandeira_v3_e1421dc4c4a9.png')));
if (count($amostras) !== 1 || !$amostras[0]['aprovada'] || $amostras[0]['cor_nome'] !== 'Verde Bandeira') {
    throw new RuntimeException('Amostra aprovada ausente, duplicada ou associada à cor incorreta.');
}
$arquivo = __DIR__ . '/../' . $amostras[0]['arquivo_path'];
if (hash_file('sha256', $arquivo) !== 'e1421dc4c4a9a73187b8016a0b0a058a3f101e69d00f6e7df4848fa4c76adbc0') {
    throw new RuntimeException('O arquivo difere da referência aprovada.');
}
echo "[OK] Uma única amostra Verde Bandeira aprovada com arquivo íntegro.\n";
$antes = $motor->resumo()['cor_aliases'];
$preto = (int)$pdo->query("SELECT id FROM cores_padrao WHERE slug_normalizado='preto'")->fetchColumn();
try {
    $motor->criarAlias($preto, 'Preta', null, null);
    throw new RuntimeException('Alias duplicado aceito.');
} catch (DomainException $e) {
    if ($motor->resumo()['cor_aliases'] !== $antes || $pdo->inTransaction()) throw new RuntimeException('Falha no rollback de alias.');
    echo "[OK] Alias duplicado recusado sem erro de parâmetros nem resíduo.\n";
}
$branco = (int)$pdo->query("SELECT id FROM cores_padrao WHERE slug_normalizado='branco'")->fetchColumn();
try {
    $motor->criarAlias($branco, 'Preta', null, null);
    throw new RuntimeException('Alias conflitante aceito.');
} catch (DomainException $e) {
    echo "[OK] Alias de outra cor recusado.\n";
}
$stmt = $pdo->prepare("SELECT COUNT(*) FROM historico_motor_cores WHERE entidade='amostra' AND entidade_id=? AND acao='APROVACAO_IMPORTADA'");
$stmt->execute([$amostras[0]['id']]);
if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Histórico ausente ou duplicado.');
echo "[OK] Aprovação rastreável e importação idempotente.\n";

$finais = [
    'Verde Seco'=>'34c7307fbbaabf45732cc65966281d13fadfc31a75d30d106a98c9bcfa308f3c',
    'Verde Oliva'=>'f2cab61a2c6bc65a56fde43ffae9e8a835c91804e1fc544917307c2f493482f1',
];
foreach ($finais as $nome=>$hash) {
    $lista = array_values(array_filter($motor->listarAmostras(), static fn($a) => $a['cor_nome'] === $nome));
    if (count($lista) !== 1 || !$lista[0]['aprovada']) throw new RuntimeException('Referência final ausente ou duplicada: ' . $nome);
    if (hash_file('sha256', __DIR__ . '/../' . $lista[0]['arquivo_path']) !== $hash) throw new RuntimeException('Arquivo final incorreto: ' . $nome);
    $hist = $pdo->prepare("SELECT COUNT(*) FROM historico_motor_cores WHERE entidade='amostra' AND entidade_id=? AND acao='APROVACAO_FINAL_AB'");
    $hist->execute([$lista[0]['id']]);
    if ((int)$hist->fetchColumn() !== 1) throw new RuntimeException('Histórico final incorreto.');
    echo '[OK] Referência final aprovada e íntegra: ' . $nome . PHP_EOL;
}
$antiga = $pdo->query("SELECT ativo,aprovada,arquivo_path FROM amostras_cor WHERE arquivo_path LIKE '%verde_seco_v5_%'")->fetch(PDO::FETCH_ASSOC);
if (!$antiga || $antiga['ativo'] || $antiga['aprovada'] || !is_file(__DIR__ . '/../' . $antiga['arquivo_path'])) throw new RuntimeException('Referência histórica não preservada/arquivada.');
echo "[OK] V5 histórica arquivada; arquivo preservado e excluído das referências vigentes.\n";
