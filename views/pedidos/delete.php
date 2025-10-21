<?php
// Arquivo: /views/pedidos/delete.php (VERSÃO CORRIGIDA)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pedido.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validação do ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de pedido inválido ou não fornecido.";
    header("Location: " . BASE_URL . "/views/pedidos/index.php");
    exit;
}

$id_pedido = (int)$_GET['id'];

// 2. Instanciar o modelo e conexão
$database = new Database();
$conn = $database->getConnection();
$pedidoModel = new Pedido($conn);

try {
    // 3. VERIFICAÇÕES ESPECÍFICAS DE PEDIDO
    
    // 3.1 Verificar se pedido existe e pegar dados
    $pedidoData = $pedidoModel->getById($id_pedido);
    if (!$pedidoData) {
        $_SESSION['error_message'] = "Pedido #" . $id_pedido . " não encontrado.";
        header('Location: ' . BASE_URL . '/views/pedidos/index.php');
        exit;
    }
    
    // 3.2 Verificar se pedido já foi entregue (se o campo status existir)
    if (isset($pedidoData['status']) && $pedidoData['status'] === 'entregue') {
        $_SESSION['error_message'] = "Não é possível excluir o pedido #" . $id_pedido . " pois já foi entregue.";
        header('Location: ' . BASE_URL . '/views/pedidos/index.php');
        exit;
    }
    
    // 3.3 Verificar se tem valor pago (ao invés de tabela pagamentos)
    if (isset($pedidoData['valor_pago']) && $pedidoData['valor_pago'] > 0) {
        $_SESSION['error_message'] = "Não é possível excluir o pedido #" . $id_pedido . " pois possui valores pagos registrados.";
        header('Location: ' . BASE_URL . '/views/pedidos/index.php');
        exit;
    }

    // 4. EXECUTAR A EXCLUSÃO
    if ($pedidoModel->delete($id_pedido)) {
        $_SESSION['success_message'] = "Pedido #" . $id_pedido . " e seus itens foram excluídos com sucesso.";
        
        // 4.1 Se o pedido veio de orçamento, reverter status do orçamento (se existir)
        if (isset($pedidoData['orcamento_id']) && !empty($pedidoData['orcamento_id'])) {
            $stmtRevertOrcamento = $conn->prepare("UPDATE orcamentos SET status = 'aprovado' WHERE id = :orcamento_id");
            $stmtRevertOrcamento->bindParam(':orcamento_id', $pedidoData['orcamento_id'], PDO::PARAM_INT);
            $stmtRevertOrcamento->execute();
        }
        
    } else {
        $_SESSION['error_message'] = "Não foi possível excluir o pedido #" . $id_pedido . ". O registro pode já ter sido removido.";
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Ocorreu um erro no servidor ao tentar excluir. Detalhes: " . $e->getMessage();
    error_log("Erro em delete.php para o pedido ID $id_pedido: " . $e->getMessage());
}

// 5. Redirecionar de volta para a lista
header("Location: " . BASE_URL . "/views/pedidos/index.php");
exit;
?>