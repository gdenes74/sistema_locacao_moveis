<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);

// Verificar se o ID foi fornecido e é válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Erro: ID de orçamento inválido ou não fornecido.";
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];

// Buscar o orçamento para verificar se existe
if (!$orcamentoModel->getById($id)) {
    $_SESSION['error_message'] = "Erro: Orçamento com ID #$id não encontrado no sistema.";
    header('Location: index.php');
    exit;
}

// Verificar se a requisição é POST (confirmação de exclusão) ou GET (exibição de página de confirmação)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao']) && $_POST['confirmar_exclusao'] === 'sim') {
    // Verificar se o orçamento está vinculado a um pedido (opcional, se aplicável)
    $stmtCheckPedido = $conn->prepare("SELECT COUNT(*) FROM pedidos WHERE orcamento_id = :orcamento_id");
    $stmtCheckPedido->bindParam(':orcamento_id', $id);
    $stmtCheckPedido->execute();
    $countPedidos = $stmtCheckPedido->fetchColumn();

    if ($countPedidos > 0) {
        $_SESSION['error_message'] = "Erro: Não é possível excluir o orçamento #$orcamentoModel->numero porque ele está vinculado a um pedido. Por favor, exclua ou desvincule o pedido primeiro.";
        header('Location: index.php');
        exit;
    }

    // Tentar excluir o orçamento
    if ($orcamentoModel->delete($id)) {
        // Excluir itens associados (já implementado no método delete da classe Orcamento, se houver)
        $_SESSION['success_message'] = "Sucesso: Orçamento #$orcamentoModel->numero excluído com sucesso do sistema.";
    } else {
        $_SESSION['error_message'] = "Erro: Falha ao excluir o orçamento #$orcamentoModel->numero. Tente novamente ou contate o suporte.";
    }
    header('Location: index.php');
    exit;
} else {
    // Se não for POST, exibir uma página de confirmação (ou redirecionar para index com mensagem, dependendo do fluxo desejado)
    // Para simplicidade, vamos assumir que o modal de confirmação já foi exibido em index.php ou show.php
    // Caso prefira uma página de confirmação separada, descomente o código abaixo e ajuste conforme necessário
    /*
    include_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="content-wrapper">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        <section class="content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h1>Confirmar Exclusão</h1>
                    </div>
                </div>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5>Atenção!</h5>
                            <p>Você está prestes a excluir o orçamento #<?= htmlspecialchars($orcamentoModel->numero) ?>. Esta ação é irreversível e removerá todos os dados associados, como itens do orçamento. Deseja continuar?</p>
                        </div>
                        <form action="delete.php?id=<?= htmlspecialchars($id) ?>" method="POST">
                            <input type="hidden" name="confirmar_exclusao" value="sim">
                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    <?php
    exit;
    */
    // Como alternativa, se o modal já foi confirmado em index.php, redirecionar como erro por segurança
    $_SESSION['error_message'] = "Erro: Ação de exclusão não confirmada. Por favor, use o botão de exclusão na listagem.";
    header('Location: index.php');
    exit;
}
?>