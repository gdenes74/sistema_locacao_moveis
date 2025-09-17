<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

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
    
    // Iniciar transação para garantir atomicidade de todo o processo
    $db->beginTransaction(); 
    
    // 1. Buscar dados do orçamento
    $orcamento = $orcamentoModel->getById($orcamento_id);
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    // 2. Verificar o status do orçamento e agir de acordo
    if ($orcamento['status'] === 'recusado' || $orcamento['status'] === 'expirado') {
        throw new Exception('Orçamentos recusados ou expirados não podem ser convertidos em pedidos.');
    }
    if ($orcamento['status'] === 'convertido') {
        throw new Exception('Este orçamento já foi convertido em pedido.');
    }

    // 3. Verificar se já existe pedido para este orçamento (duplicação)
    $stmt_check = $db->prepare("SELECT id, numero FROM pedidos WHERE orcamento_id = ?");
    $stmt_check->execute([$orcamento_id]);
    $pedido_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if ($pedido_existente) {
        throw new Exception('Já existe um pedido para este orçamento: #' . $pedido_existente['numero']);
    }
    
    // 4. Buscar itens do orçamento para validação
    $itens_orcamento = $orcamentoModel->getItens($orcamento_id);
    if (empty($itens_orcamento)) {
        throw new Exception('Orçamento não possui itens para converter');
    }
    
    // 4.1. Verificar se tem pelo menos um produto (não só seções)
    $tem_produtos = false;
    foreach ($itens_orcamento as $item) {
        if ($item['tipo_linha'] === 'PRODUTO') {
            $tem_produtos = true;
            break;
        }
    }
    if (!$tem_produtos) {
        throw new Exception('Orçamento não possui produtos para converter');
    }
    
    // 5. Atualizar status do orçamento para 'aprovado' se estiver pendente, antes de converter
    // Isso garante que o orçamento passa por um estado de 'aprovado' antes de 'convertido'
    if ($orcamento['status'] === 'pendente') {
        $stmt_update_orc_aprovado = $db->prepare("UPDATE orcamentos SET status = 'aprovado' WHERE id = ?");
        $stmt_update_orc_aprovado->execute([$orcamento_id]);
    }
    // O status agora é 'aprovado' ou já era 'aprovado'.
    
    // 6. Criar o pedido utilizando o método do modelo Pedido
    // Este método já busca os dados do orçamento, cria o pedido e copia os itens corretamente.
    $pedido_id = $pedidoModel->criarDePedidoOrcamento($orcamento_id);

    if (!$pedido_id) {
        throw new Exception('Erro desconhecido ao criar o pedido.');
    }
    
    // 7. Atualizar status final do orçamento para "convertido"
    $stmt_update_orc = $db->prepare("UPDATE orcamentos SET status = 'convertido' WHERE id = ?");
    $stmt_update_orc->execute([$orcamento_id]);
    
    // 8. Atualizar histórico de numeração sequencial
    try {
        $stmt_update_historico = $db->prepare("
            UPDATE numeracao_sequencial 
            SET tipo = 'pedido', 
                pedido_id = :pedido_id, 
                data_conversao = NOW() 
            WHERE numero = :numero AND orcamento_id = :orcamento_id
        ");
        $stmt_update_historico->bindParam(':pedido_id', $pedido_id);
        $stmt_update_historico->bindParam(':numero', $orcamento['numero']);
        $stmt_update_historico->bindParam(':orcamento_id', $orcamento_id);
        $stmt_update_historico->execute();
        
        // Se não existir registro, cria um
        if ($stmt_update_historico->rowCount() === 0) {
            $stmt_insert_historico = $db->prepare("
                INSERT INTO numeracao_sequencial 
                (numero, tipo, orcamento_id, pedido_id, data_atribuicao, data_conversao) 
                VALUES (:numero, 'pedido', :orcamento_id, :pedido_id, NOW(), NOW())
            ");
            $stmt_insert_historico->bindParam(':numero', $orcamento['numero']);
            $stmt_insert_historico->bindParam(':orcamento_id', $orcamento_id);
            $stmt_insert_historico->bindParam(':pedido_id', $pedido_id);
            $stmt_insert_historico->execute();
        }
        
    } catch (Exception $e) {
        error_log("Aviso: Erro ao atualizar histórico de numeração: " . $e->getMessage());
        // Não jogamos a exceção para não dar rollback no pedido já criado, apenas logamos
    }
    
    // Confirmar transação
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