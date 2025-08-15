<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Pedido.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se o ID do orçamento foi fornecido
if (!isset($_POST['orcamento_id']) || !filter_var($_POST['orcamento_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'ID do orçamento inválido']);
    exit;
}

$orcamento_id = (int)$_POST['orcamento_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $orcamentoModel = new Orcamento($db);
    $pedidoModel = new Pedido($db);
    
    // Iniciar transação
    $db->beginTransaction();
    
    // 1. Buscar dados do orçamento
    $orcamento = $orcamentoModel->getById($orcamento_id);
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    // 2. Verificar se orçamento está aprovado
    if ($orcamentoModel->status !== 'aprovado') {
        throw new Exception('Apenas orçamentos aprovados podem ser convertidos em pedidos');
    }
    
    // 3. Verificar se já existe pedido para este orçamento
    $stmt_check = $db->prepare("SELECT id FROM pedidos WHERE orcamento_id = ?");
    $stmt_check->execute([$orcamento_id]);
    if ($stmt_check->fetchColumn()) {
        throw new Exception('Já existe um pedido para este orçamento');
    }
    
    // 4. Buscar itens do orçamento
    $itens_orcamento = $orcamentoModel->getItens($orcamento_id);
    if (empty($itens_orcamento)) {
        throw new Exception('Orçamento não possui itens para converter');
    }
    
    // 5. Criar o pedido mantendo o MESMO número do orçamento
    $pedidoModel->numero = $orcamentoModel->numero; // ✅ MESMO número
    $pedidoModel->codigo = str_replace('ORC-', 'PED-', $orcamentoModel->codigo); // ✅ Troca apenas o prefixo
    $pedidoModel->cliente_id = $orcamentoModel->cliente_id;
    $pedidoModel->orcamento_id = $orcamento_id;
    $pedidoModel->data_pedido = date('Y-m-d');
    $pedidoModel->data_evento = $orcamentoModel->data_evento;
    $pedidoModel->hora_evento = $orcamentoModel->hora_evento;
    $pedidoModel->local_evento = $orcamentoModel->local_evento;
    $pedidoModel->data_entrega = $orcamentoModel->data_entrega;
    $pedidoModel->data_retirada_prevista = $orcamentoModel->data_devolucao_prevista;
    $pedidoModel->tipo = $orcamentoModel->tipo;
    $pedidoModel->status = 'confirmado';
    
    // Valores financeiros
    $pedidoModel->valor_total_locacao = $orcamentoModel->valor_total_locacao;
    $pedidoModel->subtotal_locacao = $orcamentoModel->subtotal_locacao;
    $pedidoModel->valor_total_venda = $orcamentoModel->valor_total_venda;
    $pedidoModel->subtotal_venda = $orcamentoModel->subtotal_venda;
    $pedidoModel->desconto = $orcamentoModel->desconto;
    $pedidoModel->taxa_domingo_feriado = $orcamentoModel->taxa_domingo_feriado;
    $pedidoModel->taxa_madrugada = $orcamentoModel->taxa_madrugada;
    $pedidoModel->taxa_horario_especial = $orcamentoModel->taxa_horario_especial;
    $pedidoModel->taxa_hora_marcada = $orcamentoModel->taxa_hora_marcada;
    $pedidoModel->frete_elevador = $orcamentoModel->frete_elevador;
    $pedidoModel->frete_escadas = $orcamentoModel->frete_escadas;
    $pedidoModel->frete_terreo = $orcamentoModel->frete_terreo;
    $pedidoModel->valor_final = $orcamentoModel->valor_final;
    $pedidoModel->ajuste_manual = $orcamentoModel->ajuste_manual;
    $pedidoModel->motivo_ajuste = $orcamentoModel->motivo_ajuste;
    
    // Valores de pagamento (iniciais)
    $pedidoModel->valor_sinal = 0.00;
    $pedidoModel->valor_pago = 0.00;
    $pedidoModel->valor_multas = 0.00;
    
    $pedidoModel->observacoes = $orcamentoModel->observacoes;
    $pedidoModel->condicoes_pagamento = $orcamentoModel->condicoes_pagamento;
    $pedidoModel->usuario_id = $_SESSION['usuario_id'] ?? 1;
    
    // 6. Salvar o pedido
    if (!$pedidoModel->create()) {
        throw new Exception('Erro ao criar o pedido');
    }
    
    $pedido_id = $pedidoModel->id;
    
    // 7. Converter itens do orçamento para itens do pedido
    $itens_pedido = [];
    foreach ($itens_orcamento as $item) {
        // Apenas itens do tipo PRODUTO são convertidos
        if ($item['tipo_linha'] === 'PRODUTO' && !empty($item['produto_id'])) {
            $itens_pedido[] = [
                'produto_id' => $item['produto_id'],
                'quantidade' => $item['quantidade'],
                'tipo' => $item['tipo'],
                'preco_unitario' => $item['preco_unitario'],
                'desconto' => $item['desconto'],
                'preco_final' => $item['preco_final'],
                'ajuste_manual' => 0,
                'motivo_ajuste' => null,
                'observacoes' => $item['observacoes']
            ];
        }
    }
    
    // 8. Salvar itens do pedido
    if (!empty($itens_pedido)) {
        $pedidoModel->salvarItens(
            $pedido_id, 
            $itens_pedido, 
            $orcamentoModel->data_entrega, 
            $orcamentoModel->data_devolucao_prevista
        );
    }
    
    // 9. Atualizar histórico de numeração sequencial
    try {
        // Registra a conversão na tabela de histórico
        $stmt_update_historico = $db->prepare("
            UPDATE numeracao_sequencial_historico 
            SET tipo = 'pedido', 
                pedido_id = ?, 
                data_conversao = NOW() 
            WHERE numero = ? AND orcamento_id = ?
        ");
        $stmt_update_historico->execute([
            $pedido_id, 
            $orcamentoModel->numero, 
            $orcamento_id
        ]);
        
        // Se não existir registro no histórico, cria um
        if ($stmt_update_historico->rowCount() === 0) {
            $stmt_insert_historico = $db->prepare("
                INSERT INTO numeracao_sequencial_historico 
                (numero, tipo, orcamento_id, pedido_id, data_atribuicao, data_conversao) 
                VALUES (?, 'pedido', ?, ?, NOW(), NOW())
            ");
            $stmt_insert_historico->execute([
                $orcamentoModel->numero,
                $orcamento_id,
                $pedido_id
            ]);
        }
        
    } catch (Exception $e) {
        // Se der erro no histórico, apenas loga mas não falha a conversão
        error_log("Aviso: Erro ao atualizar histórico de numeração: " . $e->getMessage());
    }
    
    // 10. Confirmar transação
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pedido gerado com sucesso!',
        'pedido_id' => $pedido_id,
        'pedido_numero' => $pedidoModel->numero,
        'pedido_codigo' => $pedidoModel->codigo
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Erro ao converter orçamento em pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>