<?php
// Arquivo: /views/orcamentos/delete.php (VERSÃO CORRIGIDA)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validação do ID (continua igual)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de orçamento inválido ou não fornecido.";
    header("Location: " . BASE_URL . "/views/orcamentos/index.php");
    exit;
}

$id_orcamento = (int)$_GET['id'];

// 2. Instanciar o modelo e conexão
$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);

try {
    // 3. VERIFICAÇÃO DE VÍNCULO COM PEDIDO (Sua regra de negócio, excelente!)
    $stmtCheckPedido = $conn->prepare("SELECT numero FROM pedidos WHERE orcamento_id = :orcamento_id LIMIT 1");
    $stmtCheckPedido->bindParam(':orcamento_id', $id_orcamento, PDO::PARAM_INT);
    $stmtCheckPedido->execute();
    $pedidoVinculado = $stmtCheckPedido->fetchColumn();

    if ($pedidoVinculado) {
        $_SESSION['error_message'] = "Não é possível excluir o orçamento, pois ele já foi convertido no pedido #$pedidoVinculado.";
        header('Location: ' . BASE_URL . '/views/orcamentos/index.php');
        exit;
    }

    // 4. TENTAR EXECUTAR A EXCLUSÃO
    // O método delete() no Modelo cuidará de apagar o orçamento e seus itens
    if ($orcamentoModel->delete($id_orcamento)) {
        $_SESSION['success_message'] = "Orçamento #" . $id_orcamento . " e seus itens foram excluídos com sucesso.";
    } else {
        $_SESSION['error_message'] = "Não foi possível excluir o orçamento #" . $id_orcamento . ". O registro pode já ter sido removido.";
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Ocorreu um erro no servidor ao tentar excluir. Detalhes: " . $e->getMessage();
    error_log("Erro em delete.php para o ID $id_orcamento: " . $e->getMessage());
}

// 5. Redirecionar de volta para a lista
header("Location: " . BASE_URL . "/views/orcamentos/index.php");
exit;
?>