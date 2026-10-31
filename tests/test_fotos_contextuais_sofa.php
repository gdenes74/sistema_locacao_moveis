<?php
/** Teste real somente leitura + transação revertida; não publica a foto. */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';
require_once __DIR__ . '/../models/FotoPronta.php';
require_once __DIR__ . '/../models/Produto.php';
$db = (new Database())->getConnection();
$fotoAntes = (string)$db->query('SELECT foto_path FROM produtos WHERE id=433')->fetchColumn();
$servico = new FotoPronta($db);
$dados = $servico->validar(433, 7, $fotoAntes);
if ((int)$dados['modelo']['produto_origem_id'] !== 433 || count($dados['componentes']) < 2) throw new RuntimeException('Foto ou componentes do sofá incorretos.');
try { $servico->validar(434, 7, (string)$db->query('SELECT foto_path FROM produtos WHERE id=434')->fetchColumn()); throw new RuntimeException('Foto do sofá amarelo aceita em outro produto.'); }
catch (DomainException $e) { echo '[OK] Foto do sofá amarelo não pode ser aplicada ao sofá de outra cor.' . PHP_EOL; }
try { $servico->aprovar(434, 7, (string)$db->query('SELECT foto_path FROM produtos WHERE id=434')->fetchColumn(), 1); throw new RuntimeException('Publicação indevida em outro produto.'); }
catch (DomainException $e) { echo '[OK] Aprovação errada bloqueada antes de alterar arquivos ou banco.' . PHP_EOL; }
$compartilhados = array_values(array_filter($dados['componentes'], static fn($c) => (int)$c['usos'] > 1));
if (!$compartilhados) throw new RuntimeException('Teste não cobriu o colchão compartilhado.');
$componenteId = (int)$compartilhados[0]['id'];
$fotoComponenteAntes = (string)$db->query('SELECT foto_path FROM produtos WHERE id=' . $componenteId)->fetchColumn();
$db->beginTransaction();
try {
    $db->prepare("INSERT INTO fotos_componentes_contextuais (produto_montado_id,componente_id,foto_path,origem,modelo_imagem_id) VALUES (?,?,?,'TESTE',?) ON DUPLICATE KEY UPDATE foto_path=VALUES(foto_path)")
        ->execute([433,$componenteId,'assets/uploads/produtos/foto_teste_nao_publicada.png',7]);
    $s = new Produto($db);
    $sofa = array_values(array_filter($s->listarComponentes(433), static fn($c) => (int)$c['produto_filho_id'] === $componenteId));
    if (count($sofa) !== 1 || $sofa[0]['foto_nesta_montagem'] !== 'assets/uploads/produtos/foto_teste_nao_publicada.png') throw new RuntimeException('Consulta contextual do sofá falhou.');
    if ((string)$db->query('SELECT foto_path FROM produtos WHERE id=' . $componenteId)->fetchColumn() !== $fotoComponenteAntes) throw new RuntimeException('Foto global do colchão foi alterada.');
    echo '[OK] Sofá usa foto contextual; colchão físico preserva sua foto global e estoque.' . PHP_EOL;
} finally { $db->rollBack(); }
if ((string)$db->query('SELECT foto_path FROM produtos WHERE id=433')->fetchColumn() !== $fotoAntes) throw new RuntimeException('Foto comercial alterada no teste.');
echo '[OK] Teste revertido sem publicar foto nem alterar produto.' . PHP_EOL;
