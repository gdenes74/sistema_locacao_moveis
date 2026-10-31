<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';

$pdo = (new Database())->getConnection();
$opcoes = (new MotorCores($pdo))->listarOpcoesPrevia();
$fila = (new MotorCores($pdo))->listarFilaMontagem();
if (count($fila) < count($opcoes)) throw new RuntimeException('Fila perdeu variantes cadastradas.');
$idsProntos = array_map(static fn($item) => (int)$item['variante_imagem_id'], $opcoes);
$filaPorId = [];
foreach ($fila as $item) $filaPorId[(int)$item['variante_imagem_id']] = $item;
foreach ($idsProntos as $id) {
    if (!isset($filaPorId[$id]) || $filaPorId[$id]['pendencias']) {
        throw new RuntimeException("Prévia #{$id} não consta como pronta na fila.");
    }
}
$porId = [];
foreach ($opcoes as $opcao) $porId[(int)$opcao['produto_id']] = $opcao;
$amostraCeleste = (int)$pdo->query("SELECT id FROM amostras_cor WHERE cor_padrao_id = 7 AND produto_origem_id = 293 AND aprovada = 1 AND ativo = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
if (!$amostraCeleste) throw new RuntimeException('Azul Celeste aprovado não aparece nas amostras.');
$esperados = [34 => ['pequeno', 1], 314 => ['pequeno', 4], 36 => ['pequeno', 3], 293 => ['pequeno', $amostraCeleste], 499 => ['thai', 3]];
if (count($opcoes) < count($esperados)) throw new RuntimeException('Opções previamente aprovadas desapareceram.');
$verificarAmostra = $pdo->prepare('SELECT cor_padrao_id FROM amostras_cor WHERE id=? AND aprovada=1 AND ativo=1');
foreach ($opcoes as $opcao) {
    $verificarAmostra->execute([(int)$opcao['amostra_id']]);
    if ((int)$verificarAmostra->fetchColumn() !== (int)$opcao['cor_id']) throw new RuntimeException('Prévia liberada sem amostra ativa aprovada para a mesma cor.');
}
foreach ($esperados as $id => [$modelo, $amostra]) {
    if (!isset($porId[$id]) || $porId[$id]['modelo'] !== $modelo || (int)$porId[$id]['amostra_id'] !== $amostra) {
        throw new RuntimeException("Associação incorreta no produto #{$id}");
    }
    echo "[OK] Produto #{$id}: {$modelo}, amostra #{$amostra}\n";
}
if ((int)$porId[499]['capas_cadastradas'] !== 4 || (int)$porId[34]['capas_cadastradas'] !== 15) {
    throw new RuntimeException('A quantidade exibida deve vir da capa componente, não do produto composto.');
}
$modeloThaiId = (int)$porId[499]['modelo_id'];
$coresThai = array_values(array_filter($opcoes, static fn($item) => (int)$item['modelo_id'] === $modeloThaiId));
if (!array_filter($coresThai, static fn($item) => $item['cor_nome'] === 'Verde Seco')) throw new RuntimeException('O mestre Thai perdeu o Verde Seco aprovado.');
// Novas referências aprovadas pela administração podem liberar mais cores;
// cada opção continua obrigada a constar sem pendências na fila acima.
$modelos = $pdo->query("SELECT slug, arquivo_base FROM modelos_imagem WHERE slug IN ('pufe-azul-celeste-limpo','pufe-thai-madeira-v2')")->fetchAll(PDO::FETCH_ASSOC);
if (count($modelos) !== 2) throw new RuntimeException('Mestres insuficientes no banco.');
foreach ($modelos as $modelo) {
    $relativo = $modelo['arquivo_base'];
    if (!str_starts_with($relativo, 'assets/uploads/motor_cores/modelos/')) throw new RuntimeException('Caminho mestre não portátil.');
    $dados = getimagesize(__DIR__ . '/../' . $relativo);
    if (!$dados || $dados['mime'] !== 'image/png') throw new RuntimeException("Mestre inválido: {$modelo['slug']}");
    echo "[OK] Mestre {$modelo['slug']}: {$dados[0]}x{$dados[1]}\n";
}
echo "Prévia: fonte e vínculos aprovados conferidos.\n";
