<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    if (empty($_POST['orcamento_id'])) {
        throw new Exception('ID do orçamento é obrigatório');
    }

    $orcamento_id = (int)$_POST['orcamento_id'];

    $database = new Database();
    $db = $database->getConnection();
    
    $orcamentoModel = new Orcamento($db);
    $pedidoModel = new Pedido($db);
    $numeracaoModel = new NumeracaoSequencial($db);

    $db->beginTransaction();

    // Buscar dados do orçamento
    $orcamento = $orcamentoModel->getById($orcamento_id);
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }

    // Verificar se já foi convertido
    $stmt_check = $db->prepare("SELECT id FROM pedidos WHERE orcamento_id = ?");
    $stmt_check->execute([$orcamento_id]);
    if ($stmt_check->fetchColumn()) {
        throw new Exception('Este orçamento já foi convertido em pedido');
    }

    // Buscar itens do orçamento
    $itens = $orcamentoModel->getItens($orcamento_id);
    if ($itens === false) {
        throw new Exception('Erro ao buscar itens do orçamento');
    }

    // Criar pedido com MESMO número do orçamento
    $pedidoModel->numero = $orcamento['numero'];
    $pedidoModel->codigo = str_replace('ORC-', 'PED-', $orcamento['codigo']);
    $pedidoModel->cliente_id = $orcamento['cliente_id'];
    $pedidoModel->orcamento_id = $orcamento_id;
    $pedidoModel->data_pedido = date('Y-m-d');
    $pedidoModel->data_evento = $orcamento['data_evento'];
    $pedidoModel->hora_evento = $orcamento['hora_evento'];
    $pedidoModel->data_entrega = $orcamento['data_entrega'];
    $pedidoModel->hora_entrega = $orcamento['hora_entrega'];
    $pedidoModel->turno_entrega = $orcamento['turno_entrega'];
    $pedidoModel->data_retirada_prevista = $orcamento['data_devolucao_prevista'];
    $pedidoModel->hora_devolucao = $orcamento['hora_devolucao'];
    $pedidoModel->turno_devolucao = $orcamento['turno_devolucao'];
    $pedidoModel->local_evento = $orcamento['local_evento'];
    $pedidoModel->tipo = $orcamento['tipo'];
    $pedidoModel->status = 'confirmado'; // Status inicial de pedido
    $pedidoModel->desconto = $orcamento['desconto'];
    $pedidoModel->taxa_domingo_feriado = $orcamento['taxa_domingo_feriado'];
    $pedidoModel->taxa_madrugada = $orcamento['taxa_madrugada'];
    $pedidoModel->taxa_horario_especial = $orcamento['taxa_horario_especial'];
    $pedidoModel->taxa_hora_marcada = $orcamento['taxa_hora_marcada'];
    $pedidoModel->frete_terreo = $orcamento['frete_terreo'];
    $pedidoModel->frete_elevador = $orcamento['frete_elevador'];
    $pedidoModel->frete_escadas = $orcamento['frete_escadas'];
    $pedidoModel->ajuste_manual = $orcamento['ajuste_manual'];
    $pedidoModel->motivo_ajuste = $orcamento['motivo_ajuste'];
    $pedidoModel->observacoes = $orcamento['observacoes'];
    $pedidoModel->condicoes_pagamento = $orcamento['condicoes_pagamento'];
    $pedidoModel->usuario_id = $_SESSION['usuario_id'] ?? 1;

    // Campos específicos de pedidos (zerados inicialmente)
    $pedidoModel->valor_sinal = 0.00;
    $pedidoModel->valor_pago = 0.00;
    $pedidoModel->valor_multas = 0.00;
    $pedidoModel->data_pagamento_sinal = null;
    $pedidoModel->data_pagamento_final = null;

    // Salvar pedido
    $pedido_id = $pedidoModel->create();
    if (!$pedido_id) {
        throw new Exception('Erro ao criar pedido');
    }

    // Copiar itens do orçamento para o pedido
    if (!empty($itens)) {
        $itens_pedido = [];
        foreach ($itens as $item) {
            $itens_pedido[] = [
                'produto_id' => $item['produto_id'],
                'nome_produto_manual' => $item['nome_produto_manual'],
                'quantidade' => $item['quantidade'],
                'preco_unitario' => $item['preco_unitario'],
                'desconto' => $item['desconto'],
                'preco_final' => $item['preco_final'],
                'tipo' => $item['tipo'],
                'observacoes' => $item['observacoes'],
                'tipo_linha' => $item['tipo_linha'],
                'ordem' => $item['ordem']
            ];
        }

        if (!$pedidoModel->salvarItens($pedido_id, $itens_pedido)) {
            throw new Exception('Erro ao copiar itens do orçamento');
        }
    }

    // Recalcular valores do pedido
    $pedidoModel->id = $pedido_id;
    if (!$pedidoModel->recalcularValores($pedido_id)) {
        throw new Exception('Erro ao recalcular valores do pedido');
    }

    // Atualizar status do orçamento para 'convertido'
    $stmt_update = $db->prepare("UPDATE orcamentos SET status = 'convertido' WHERE id = ?");
    if (!$stmt_update->execute([$orcamento_id])) {
        throw new Exception('Erro ao atualizar status do orçamento');
    }

    $db->commit();

    // MUDANÇA PRINCIPAL: Redirecionar para EDIT em vez de SHOW
    echo json_encode([
        'success' => true,
        'message' => 'Orçamento convertido com sucesso! Faça os ajustes necessários no pedido.',
        'pedido_id' => $pedido_id,
        'pedido_numero' => $pedidoModel->numero,
        'redirect_to' => 'edit' // Flag para indicar redirecionamento para edit
    ]);

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Erro na conversão de orçamento para pedido: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>