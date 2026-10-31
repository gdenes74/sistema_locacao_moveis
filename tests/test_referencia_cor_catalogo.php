<?php
/** Exercita a cópia real da almofada amarela; reverte o cadastro e remove somente a cópia do teste. */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';
$db = (new Database())->getConnection();
$produto = $db->query("SELECT id,foto_path FROM produtos WHERE id=43 AND nome_produto LIKE '%Amarela%'")->fetch(PDO::FETCH_ASSOC);
$cor = $db->query("SELECT id FROM cores_padrao WHERE nome='Amarelo' AND ativo=1")->fetchColumn();
if (!$produto || !$cor) throw new RuntimeException('Referências reais de Amarelo não encontradas.');
$original = realpath(__DIR__ . '/../' . $produto['foto_path']);
if (!$original) throw new RuntimeException('Foto original não encontrada.');
$hash = hash_file('sha256', $original);
$copia = null;
$id = null;
$db->beginTransaction();
try {
    $id = (new MotorCores($db))->criarAmostraDeProduto(['cor_padrao_id'=>$cor,'produto_origem_id'=>$produto['id'],'observacoes'=>'Teste transacional de referência'], null);
    $q = $db->prepare('SELECT * FROM amostras_cor WHERE id=?');
    $q->execute([$id]);
    $amostra = $q->fetch(PDO::FETCH_ASSOC);
    $copia = realpath(__DIR__ . '/../' . ($amostra['arquivo_path'] ?? ''));
    if (!$amostra || $amostra['tipo_origem'] !== 'FOTO_PRODUTO' || (int)$amostra['aprovada'] !== 0 || (int)$amostra['produto_origem_id'] !== (int)$produto['id']) throw new RuntimeException('Origem, vínculo ou estado pendente incorreto.');
    if (!$copia || $copia === $original || hash_file('sha256', $copia) !== $hash) throw new RuntimeException('Cópia da referência incorreta.');
    echo '[OK] Almofada amarela copiada como referência Amarelo pendente, com origem aceita pelo banco.' . PHP_EOL;
} finally {
    $db->rollBack();
    $pasta = realpath(__DIR__ . '/../assets/uploads/motor_cores/amostras');
    if ($copia && $pasta && dirname($copia) === $pasta && $copia !== $original) unlink($copia);
}
if ($db->query('SELECT COUNT(*) FROM amostras_cor WHERE id=' . (int)$id)->fetchColumn() != 0 || hash_file('sha256', $original) !== $hash) throw new RuntimeException('Reversão ou preservação do original falhou.');
echo '[OK] Teste revertido; foto original preservada e cópia de teste removida.' . PHP_EOL;
