<?php
/** Testa a sincronização no banco local e desfaz todas as escritas ao final. */
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/Database.php';
require_once __DIR__.'/../models/ProducaoPendente.php';

$pedidoId = (int)($argv[1] ?? 118);
$db = (new Database())->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (in_array('--pools',$argv,true)) {
    foreach ($db->query('SELECT pea.produto_pai_id,pea.produto_principal_id,pea.produto_adicional_id,p.nome_produto
        FROM produto_estoque_alternativo pea JOIN produtos p ON p.id=pea.produto_pai_id ORDER BY pea.produto_pai_id') as $r) {
        echo json_encode($r,JSON_UNESCAPED_UNICODE)."\n";
    }
    foreach ($db->query('SELECT pc.produto_pai_id,pc.produto_filho_id,pc.quantidade
        FROM produto_composicao pc WHERE pc.produto_pai_id IN (675,670,671)') as $r) {
        echo json_encode($r,JSON_UNESCAPED_UNICODE)."\n";
    }
}
$q = $db->prepare('SELECT * FROM producao_pendencias WHERE pedido_id=? ORDER BY id');
$q->execute([$pedidoId]);
$antes = $q->fetchAll(PDO::FETCH_ASSOC);
$q = $db->prepare('SELECT * FROM itens_pedido WHERE pedido_id=? ORDER BY id');
$q->execute([$pedidoId]);
$itensAntes = $q->fetchAll(PDO::FETCH_ASSOC);
$estruturaAntes = $db->query('SELECT id,quantidade_total FROM produtos WHERE id=398')->fetch(PDO::FETCH_ASSOC);
$pasta = 'C:/Users/larak/Documents/Codex/2026-10-07/ass/backups';
if (!is_dir($pasta)) mkdir($pasta, 0777, true);
$backup = $pasta.'/producao_pedido_'.$pedidoId.'_'.date('Ymd_His').'.json';
file_put_contents($backup, json_encode(['pedido_id'=>$pedidoId,'pendencias'=>$antes,
    'itens'=>$itensAntes,'estrutura_398'=>$estruturaAntes],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Backup: $backup\n";
$db->beginTransaction();
try {
    $motor = new ProducaoPendente($db);
    $novas = $motor->sincronizarCoresNovasDoPedido($pedidoId);
    $q = $db->prepare('SELECT id,produto_id,componente_id,descricao_item,cor,quantidade_faltante,status
        FROM producao_pendencias WHERE pedido_id=? ORDER BY id');
    $q->execute([$pedidoId]); $primeira = $q->fetchAll(PDO::FETCH_ASSOC);
    $motor->sincronizarCoresNovasDoPedido($pedidoId);
    $q->execute([$pedidoId]); $segunda = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($primeira !== $segunda) throw new RuntimeException('A segunda execução não foi idempotente.');
    echo "Novas: $novas; fichas: ".count($primeira)."; segunda execução idempotente.\n";
    foreach ($primeira as $linha) {
        echo "#{$linha['id']} {$linha['status']} produto={$linha['produto_id']} componente={$linha['componente_id']} falta={$linha['quantidade_faltante']} {$linha['descricao_item']}\n";
    }
    if ($pedidoId === 118) {
        $namoradeira = $db->prepare('SELECT id,quantidade FROM itens_pedido WHERE pedido_id=? AND produto_id=552 LIMIT 1');
        $namoradeira->execute([$pedidoId]); $linhaNamoradeira = $namoradeira->fetch(PDO::FETCH_ASSOC);
        if ($linhaNamoradeira && (float)$linhaNamoradeira['quantidade'] === 4.0) {
            $trocar = $db->prepare('UPDATE itens_pedido SET produto_id=?,quantidade=? WHERE id=?');
            $trocar->execute([444,1,$linhaNamoradeira['id']]);
            $motor->sincronizarCoresNovasDoPedido($pedidoId);
            $contar = $db->prepare("SELECT COUNT(*) FROM producao_pendencias WHERE pedido_id=?
                AND componente_id=398 AND status IN ('PENDENTE','PROGRAMADA')");
            $contar->execute([$pedidoId]);
            $estruturasAtivas = (int)$contar->fetchColumn();
            if ($estruturasAtivas > 1) throw new RuntimeException('Estrutura compartilhada gerou fichas duplicadas.');
            echo "Dois sofás de variantes diferentes: uma ficha ativa para estrutura compartilhada.\n";
            $trocar->execute([552,4,$linhaNamoradeira['id']]);
            $motor->sincronizarCoresNovasDoPedido($pedidoId);

            // Os cubos forrados usam um conjunto de 12 cubos de madeira e 8 exclusivos.
            // A ficha da estrutura só deve surgir quando o conjunto inteiro faltar.
            foreach ([13,30] as $quantidadeCubos) {
                $trocar->execute([675,$quantidadeCubos,$linhaNamoradeira['id']]);
                $motor->sincronizarCoresNovasDoPedido($pedidoId);
                $fisicas = $db->prepare("SELECT quantidade_faltante FROM producao_pendencias
                    WHERE pedido_id=? AND produto_id=675 AND componente_id=671
                      AND status IN ('PENDENTE','PROGRAMADA')");
                $fisicas->execute([$pedidoId]); $faltaFicha = $fisicas->fetchColumn();
                echo "Cubo forrado $quantidadeCubos unidade(s): estrutura a produzir="
                    .($faltaFicha === false ? '0' : $faltaFicha).".\n";
            }
            $trocar->execute([552,4,$linhaNamoradeira['id']]);
            $motor->sincronizarCoresNovasDoPedido($pedidoId);
        }
        $sofa = $db->prepare('SELECT id,quantidade FROM itens_pedido WHERE pedido_id=? AND produto_id=434 LIMIT 1');
        $sofa->execute([$pedidoId]); $linhaSofa = $sofa->fetch(PDO::FETCH_ASSOC);
        if ($linhaSofa && (float)$linhaSofa['quantidade'] === 8.0) {
            $alterar = $db->prepare('UPDATE itens_pedido SET quantidade=? WHERE id=?');
            $alterar->execute([7,$linhaSofa['id']]);
            $motor->sincronizarCoresNovasDoPedido($pedidoId);
            $checagem = $db->prepare("SELECT componente_id,status,quantidade_faltante FROM producao_pendencias
                WHERE pedido_id=? AND produto_id=434 ORDER BY id");
            $checagem->execute([$pedidoId]); $fichas7 = $checagem->fetchAll(PDO::FETCH_ASSOC);
            echo "Sofá reduzido a 7 (somente na transação): ".json_encode($fichas7)."\n";
            $alterar->execute([8,$linhaSofa['id']]);
            $motor->sincronizarCoresNovasDoPedido($pedidoId);
            $checagem->execute([$pedidoId]); $fichas8 = $checagem->fetchAll(PDO::FETCH_ASSOC);
            echo "Sofá restaurado a 8 (somente na transação): ".json_encode($fichas8)."\n";
        }
        if ($estruturaAntes) {
            $aumentar = $db->prepare('UPDATE produtos SET quantidade_total=? WHERE id=398');
            $aumentar->execute([(int)$estruturaAntes['quantidade_total']+1]);
            $reavaliados = $motor->sincronizarPedidosAfetadosPorProduto(398);
            echo "Estoque de estrutura +1 (somente na transação): $reavaliados pedido(s) reavaliado(s).\n";
        }
    }
} finally {
    $db->rollBack();
    echo "ROLLBACK concluído.\n";
}
