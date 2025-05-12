<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Cliente.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);
$clienteModel = new Cliente($conn);

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de orçamento inválido.";
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];
if (!$orcamentoModel->getById($id)) {
    $_SESSION['error_message'] = "Orçamento não encontrado.";
    header('Location: index.php');
    exit;
}

// Buscar cliente associado
$clienteModel->getById($orcamentoModel->cliente_id);

// Buscar itens do orçamento
$itens = $orcamentoModel->getItens($id);
?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Orçamento #<?= htmlspecialchars($orcamentoModel->numero) ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <a href="edit.php?id=<?= htmlspecialchars($orcamentoModel->id) ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="../pedidos/create.php?orcamento_id=<?= htmlspecialchars($orcamentoModel->id) ?>" class="btn btn-success">
                        <i class="fas fa-arrow-right"></i> Converter para Pedido
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Dados do Orçamento</h5>
                            <p><strong>Número:</strong> <?= htmlspecialchars($orcamentoModel->numero) ?></p>
                            <p><strong>Código:</strong> <?= htmlspecialchars($orcamentoModel->codigo) ?></p>
                            <p><strong>Data do Orçamento:</strong> <?= date('d/m/Y', strtotime($orcamentoModel->data_orcamento)) ?></p>
                            <p><strong>Data de Validade:</strong> <?= date('d/m/Y', strtotime($orcamentoModel->data_validade)) ?></p>
                            <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst($orcamentoModel->status)) ?></p>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars(ucfirst($orcamentoModel->tipo)) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Dados do Cliente</h5>
                            <p><strong>Nome:</strong> <?= htmlspecialchars($clienteModel->nome) ?></p>
                            <p><strong>Telefone:</strong> <?= htmlspecialchars($clienteModel->telefone ?: '-') ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($clienteModel->email ?: '-') ?></p>
                            <p><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($clienteModel->cpf_cnpj ?: '-') ?></p>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Dados do Evento</h5>
                            <p><strong>Data do Evento:</strong> <?= $orcamentoModel->data_evento ? date('d/m/Y', strtotime($orcamentoModel->data_evento)) : '-' ?></p>
                            <p><strong>Hora do Evento:</strong> <?= $orcamentoModel->hora_evento ?: '-' ?></p>
                            <p><strong>Local do Evento:</strong> <?= htmlspecialchars($orcamentoModel->local_evento ?: '-') ?></p>
                            <p><strong>Data de Devolução Prevista:</strong> <?= $orcamentoModel->data_devolucao_prevista ? date('d/m/Y', strtotime($orcamentoModel->data_devolucao_prevista)) : '-' ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Valores</h5>
                            <p><strong>Subtotal Locação:</strong> R$ <?= number_format($orcamentoModel->subtotal_locacao, 2, ',', '.') ?></p>
                            <p><strong>Subtotal Venda:</strong> R$ <?= number_format($orcamentoModel->subtotal_venda, 2, ',', '.') ?></p>
                            <p><strong>Desconto:</strong> R$ <?= number_format($orcamentoModel->desconto, 2, ',', '.') ?></p>
                            <p><strong>Taxa Domingo/Feriado:</strong> R$ <?= number_format($orcamentoModel->taxa_domingo_feriado, 2, ',', '.') ?></p>
                            <p><strong>Taxa Madrugada:</strong> R$ <?= number_format($orcamentoModel->taxa_madrugada, 2, ',', '.') ?></p>
                            <p><strong>Taxa Horário Especial:</strong> R$ <?= number_format($orcamentoModel->taxa_horario_especial, 2, ',', '.') ?></p>
                            <p><strong>Taxa Hora Marcada:</strong> R$ <?= number_format($orcamentoModel->taxa_hora_marcada, 2, ',', '.') ?></p>
                            <p><strong>Frete Térreo:</strong> R$ <?= number_format($orcamentoModel->frete_terreo, 2, ',', '.') ?></p>
                            <p><strong>Frete Elevador:</strong> <?= htmlspecialchars($orcamentoModel->frete_elevador) ?></p>
                            <p><strong>Frete Escadas:</strong> <?= htmlspecialchars($orcamentoModel->frete_escadas) ?></p>
                            <p><strong>Valor Final:</strong> R$ <?= number_format($orcamentoModel->valor_final, 2, ',', '.') ?></p>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Itens do Orçamento</h5>
                            <?php if (!empty($itens)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Quantidade</th>
                                            <th>Tipo</th>
                                            <th>Preço Unitário</th>
                                            <th>Desconto</th>
                                            <th>Preço Final</th>
                                            <th>Observações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($itens as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $stmtProd = $conn->prepare("SELECT nome_produto FROM produtos WHERE id = :produto_id");
                                                    $stmtProd->bindParam(':produto_id', $item['produto_id']);
                                                    $stmtProd->execute();
                                                    echo htmlspecialchars($stmtProd->fetchColumn() ?: 'Desconhecido');
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($item['quantidade']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($item['tipo'])) ?></td>
                                                <td>R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($item['desconto'], 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($item['preco_final'], 2, ',', '.') ?></td>
                                                <td><?= htmlspecialchars($item['observacoes'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>Nenhum item encontrado neste orçamento.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Observações e Condições</h5>
                            <p><strong>Observações:</strong> <?= htmlspecialchars($orcamentoModel->observacoes ?: '-') ?></p>
                            <p><strong>Condições de Pagamento:</strong> <?= htmlspecialchars($orcamentoModel->condicoes_pagamento ?: '-') ?></p>
                            <?php if ($orcamentoModel->ajuste_manual): ?>
                                <p><strong>Ajuste Manual:</strong> Sim - Motivo: <?= htmlspecialchars($orcamentoModel->motivo_ajuste ?: '-') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>