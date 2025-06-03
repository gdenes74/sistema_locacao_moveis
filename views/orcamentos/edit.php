<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Cliente.php'; 
require_once __DIR__ . '/../../models/Produto.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);
$clienteModel = new Cliente($conn);
$produtoModel = new Produto($conn); 

// --- Bloco AJAX para buscar clientes ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_clientes') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        
        if ($termo === '') {
            echo json_encode([]);
            exit;
        }
        
        $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes FROM clientes WHERE nome LIKE ? OR cpf_cnpj LIKE ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['%' . $termo . '%', '%' . $termo . '%']);
        $clientes_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($clientes_ajax);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados ao buscar clientes: ' . $e->getMessage()]);
        exit;
    }
}

// --- Bloco AJAX para buscar produtos ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_produtos') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        if ($termo === '') {
            echo json_encode([]);
            exit;
        }
        $sql = "SELECT id, nome, codigo, preco_venda FROM produtos WHERE nome LIKE ? OR codigo LIKE ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['%' . $termo . '%', '%' . $termo . '%']);
        $produtos_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($produtos_ajax);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados ao buscar produtos: ' . $e->getMessage()]);
        exit;
    }
}


$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de orçamento inválido.";
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];
$orcamentoModel->getById($id);

if (!$orcamentoModel->numero) {
    $_SESSION['error_message'] = "Orçamento não encontrado.";
    header('Location: index.php');
    exit;
}

$itens = $orcamentoModel->getItens($id);

?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Editar Orçamento #<?= htmlspecialchars($orcamentoModel->numero) ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <a href="show.php?id=<?= htmlspecialchars($orcamentoModel->id) ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> Ver Detalhes
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
                    <form id="form-orcamento" action="update.php" method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($orcamentoModel->id) ?>">
                        
                        <!-- Dados do Orçamento (Número, Data, Validade) -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="numero_orcamento" class="form-label">Número do Orçamento</label>
                                <input type="text" class="form-control" id="numero_orcamento" name="numero" 
                                       value="<?= htmlspecialchars($orcamentoModel->numero) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="data_orcamento" class="form-label">Data do Orçamento</label>
                                <input type="text" class="form-control datepicker" id="data_orcamento" name="data_orcamento" 
                                       value="<?= date('d/m/Y', strtotime($orcamentoModel->data_orcamento)); ?>" required>
                            </div>
                            <!-- Data de Validade Pré-seleções -->
                            <div class="col-md-3">
                                <label for="validade_dias" class="form-label">Validade (dias)</label>
                                <select class="form-control" id="validade_dias" name="validade_dias" required>
                                    <?php
                                        $data_orcamento_dt = new DateTime($orcamentoModel->data_orcamento);
                                        $data_validade_dt = new DateTime($orcamentoModel->data_validade);
                                        $interval = $data_orcamento_dt->diff($data_validade_dt);
                                        $diff_days = $interval->days; 
                                    ?>
                                    <option value="7" <?= $diff_days == 7 ? 'selected' : '' ?>>7 dias</option>
                                    <option value="15" <?= $diff_days == 15 ? 'selected' : '' ?>>15 dias</option>
                                    <option value="30" <?= $diff_days == 30 ? 'selected' : '' ?>>30 dias</option>
                                    <option value="60" <?= $diff_days == 60 ? 'selected' : '' ?>>60 dias</option>
                                    <option value="90" <?= $diff_days == 90 ? 'selected' : '' ?>>90 dias</option>
                                    <option value="180" <?= $diff_days == 180 ? 'selected' : '' ?>>180 dias</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="data_validade_final_display" class="form-label">Data de Validade Final</label>
                                <input type="text" class="form-control" id="data_validade_final_display" value="<?= date('d/m/Y', strtotime($orcamentoModel->data_validade)); ?>" readonly>
                                <input type="hidden" name="data_validade" id="data_validade_hidden" value="<?= date('Y-m-d', strtotime($orcamentoModel->data_validade)); ?>">
                            </div>
                            <!-- Fim Data de Validade Pré-seleções -->
                        </div>
                        
                        <!-- Tipo e Status do Orçamento -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="tipo" class="form-label">Tipo de Orçamento</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="locacao" <?= $orcamentoModel->tipo === 'locacao' ? 'selected' : '' ?>>Locação</option>
                                    <option value="venda" <?= $orcamentoModel->tipo === 'venda' ? 'selected' : '' ?>>Venda</option>
                                    <option value="misto" <?= $orcamentoModel->tipo === 'misto' ? 'selected' : '' ?>>Misto</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="pendente" <?= $orcamentoModel->status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="aprovado" <?= $orcamentoModel->status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="recusado" <?= $orcamentoModel->status === 'recusado' ? 'selected' : '' ?>>Recusado</option>
                                    <option value="expirado" <?= $orcamentoModel->status === 'expirado' ? 'selected' : '' ?>>Expirado</option>
                                    <option value="finalizado" <?= $orcamentoModel->status === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                                    <option value="cancelado" <?= $orcamentoModel->status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Dados do Cliente -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="busca_cliente" class="form-label">Buscar Cliente</label>
                                <input type="text" class="form-control" id="busca_cliente" 
                                       placeholder="Digite para buscar ou alterar o cliente" autocomplete="off" value="<?= htmlspecialchars($orcamentoModel->nome_cliente); ?>" />
                                <div id="resultado_busca_cliente" class="list-group mt-1" 
                                     style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto; width: 95%;"></div>
                                <input type="hidden" id="cliente_id" name="cliente_id" value="<?= htmlspecialchars($orcamentoModel->cliente_id); ?>" required />
                            </div>
                            <div class="col-md-6">
                                <div id="info_cliente_selecionado" class="alert alert-info" style="<?= $orcamentoModel->cliente_id ? 'display: block;' : 'display: none;' ?>">
                                    <strong>Cliente Selecionado:</strong>
                                    <span id="nome_cliente_selecionado"><?= htmlspecialchars($orcamentoModel->nome_cliente); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3" id="cliente_detalhes_display" style="<?= $orcamentoModel->cliente_id ? 'display: flex;' : 'display: none;' ?>">
                            <div class="col-md-3">
                                <label for="cliente_telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="cliente_telefone" readonly value="<?= htmlspecialchars($orcamentoModel->cliente_telefone ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="cliente_email" class="form-label">Email</label>
                                <input type="text" class="form-control" id="cliente_email" readonly value="<?= htmlspecialchars($orcamentoModel->cliente_email ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="cliente_cpf_cnpj" class="form-label">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="cliente_cpf_cnpj" readonly value="<?= htmlspecialchars($orcamentoModel->cliente_cpf_cnpj ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="cliente_cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cliente_cidade" readonly value="<?= htmlspecialchars($orcamentoModel->cliente_cidade ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mt-3">
                                <label for="cliente_endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="cliente_endereco" readonly value="<?= htmlspecialchars($orcamentoModel->cliente_endereco ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mt-3">
                                <label for="cliente_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="cliente_observacoes" rows="2" readonly><?= htmlspecialchars($orcamentoModel->cliente_observacoes ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <a href="<?php echo BASE_URL; ?>/views/clientes/index.php" class="btn btn-info">
                                    <i class="fas fa-user-plus"></i> Adicionar Novo Cliente
                                </a>
                            </div>
                        </div>
                        
                        <!-- Dados do Evento -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Dados do Evento</h4>
                            </div>
                            <div class="col-md-3">
                                <label for="data_evento" class="form-label">Data do Evento</label>
                                <input type="text" class="form-control datepicker" id="data_evento" name="data_evento" value="<?= $orcamentoModel->data_evento ? date('d/m/Y', strtotime($orcamentoModel->data_evento)) : '' ?>">
                                <small id="dia_semana_evento" class="form-text text-muted" style="font-size: larger;"></small>
                            </div>
                            <div class="col-md-3">
                                <label for="turno_entrega" class="form-label">Turno/Horário Entrega</label>
                                <select class="form-control" id="turno_entrega" name="turno_entrega">
                                    <option value="Manhã/Tarde (Horário Comercial)" <?= $orcamentoModel->turno_entrega === 'Manhã/Tarde (Horário Comercial)' ? 'selected' : '' ?>>Manhã/Tarde (Horário Comercial)</option>
                                    <option value="Manhã (Horário Comercial)" <?= $orcamentoModel->turno_entrega === 'Manhã (Horário Comercial)' ? 'selected' : '' ?>>Manhã (Horário Comercial)</option>
                                    <option value="Tarde (Horário Comercial)" <?= $orcamentoModel->turno_entrega === 'Tarde (Horário Comercial)' ? 'selected' : '' ?>>Tarde (Horário Comercial)</option>
                                    <option value="Horário Específico" <?= $orcamentoModel->turno_entrega === 'Horário Específico' ? 'selected' : '' ?>>Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="hora_evento" class="form-label">Hora do Evento (se preferência)</label>
                                <input type="time" class="form-control" id="hora_evento" name="hora_evento" value="<?= $orcamentoModel->hora_evento ? htmlspecialchars($orcamentoModel->hora_evento) : '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="local_evento" class="form-label">Local do Evento/Entrega</label>
                                <input type="text" class="form-control" id="local_evento" name="local_evento" placeholder="Ex.: GNU - FOYER" value="<?= htmlspecialchars($orcamentoModel->local_evento) ?>">
                                <button type="button" id="usar_endereco_cliente" class="btn btn-sm btn-info mt-2" style="<?= !empty($orcamentoModel->cliente_endereco) ? 'display: inline-block;' : 'display: none;' ?>"><i class="fas fa-map-marker-alt"></i> Usar Endereço do Cliente</button>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="data_devolucao_prevista" class="form-label">Data da Coleta/Devolução</label>
                                <input type="text" class="form-control datepicker" id="data_devolucao_prevista" name="data_devolucao_prevista" value="<?= $orcamentoModel->data_devolucao_prevista ? date('d/m/Y', strtotime($orcamentoModel->data_devolucao_prevista)) : '' ?>">
                                <small id="dia_semana_devolucao" class="form-text text-muted" style="font-size: larger;"></small>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="turno_devolucao" class="form-label">Turno/Horário Devolução</label>
                                <select class="form-control" id="turno_devolucao" name="turno_devolucao">
                                    <option value="Manhã/Tarde (Horário Comercial)" <?= $orcamentoModel->turno_devolucao === 'Manhã/Tarde (Horário Comercial)' ? 'selected' : '' ?>>Manhã/Tarde (Horário Comercial)</option>
                                    <option value="Manhã (Horário Comercial)" <?= $orcamentoModel->turno_devolucao === 'Manhã (Horário Comercial)' ? 'selected' : '' ?>>Manhã (Horário Comercial)</option>
                                    <option value="Tarde (Horário Comercial)" <?= $orcamentoModel->turno_devolucao === 'Tarde (Horário Comercial)' ? 'selected' : '' ?>>Tarde (Horário Comercial)</option>
                                    <option value="Horário Específico" <?= $orcamentoModel->turno_devolucao === 'Horário Específico' ? 'selected' : '' ?>>Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="hora_devolucao" class="form-label">Hora da Devolução (se preferência)</label>
                                <input type="time" class="form-control" id="hora_devolucao" name="hora_devolucao" value="<?= $orcamentoModel->hora_devolucao ? htmlspecialchars($orcamentoModel->hora_devolucao) : '' ?>">
                            </div>
                        </div>

                        <!-- Produtos/Serviços -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Produtos/Serviços</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="tabela_produtos">
                                        <thead>
                                            <tr>
                                                <th style="width: 40%">Produto/Serviço</th>
                                                <th style="width: 15%">Quantidade</th>
                                                <th style="width: 15%">Valor Unitário</th>
                                                <th style="width: 15%">Desconto (R$)</th>
                                                <th style="width: 15%">Total</th>
                                                <th style="width: 50px">Ação</th>
                                            </tr>
                                                                                </thead>
                                        <tbody id="produtos_tbody">
                                            <?php if (!empty($itens)): ?>
                                                <?php foreach ($itens as $index => $item): ?>
                                                    <tr class="item-row" data-item-id="<?= htmlspecialchars($item['id']) ?>">
                                                        <td>
                                                            <input type="text" class="form-control busca-produto" placeholder="Digite para buscar produto" value="<?= htmlspecialchars($item['nome_produto']) ?>">
                                                            <input type="hidden" class="produto-id" name="produto_id[]" value="<?= htmlspecialchars($item['produto_id']) ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control quantidade" name="quantidade[]" value="<?= htmlspecialchars($item['quantidade']) ?>" min="1" step="0.01">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control money valor-unitario" name="valor_unitario[]" value="<?= number_format($item['preco_unitario'], 2, ',', '.') ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control money desconto" name="desconto_item[]" value="<?= number_format($item['desconto'], 2, ',', '.') ?>" min="0">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control total-linha money" readonly value="<?= number_format(($item['quantidade'] * $item['preco_unitario']) - $item['desconto'], 2, ',', '.') ?>">
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm remover-produto"><i class="fas fa-trash"></i></button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Subtotal Locação:</strong></td>
                                                <td><strong id="subtotal_locacao_display">R$ <?= number_format($orcamentoModel->subtotal_locacao, 2, ',', '.') ?></strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Subtotal Venda:</strong></td>
                                                <td><strong id="subtotal_venda_display">R$ <?= number_format($orcamentoModel->subtotal_venda, 2, ',', '.') ?></strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Valor Final:</strong></td>
                                                <td><strong id="total_geral">R$ <?= number_format($orcamentoModel->valor_final, 2, ',', '.') ?></strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <button type="button" class="btn btn-primary" id="btn_adicionar_produto">
                                        <i class="fas fa-plus"></i> Adicionar Produto
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Valores e Taxas (MOVIDO AQUI) -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Valores e Taxas</h4>
                            </div>
                            <div class="col-md-3">
                                <label for="desconto_total" class="form-label">Desconto Total (R$)</label>
                                <input type="text" class="form-control money" id="desconto_total" name="desconto_total" value="<?= number_format($orcamentoModel->desconto, 2, ',', '.') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="taxa_domingo_feriado" class="form-label">Taxa Domingo/Feriado (R$)</label>
                                <input type="text" class="form-control money" id="taxa_domingo_feriado" name="taxa_domingo_feriado" value="<?= number_format($orcamentoModel->taxa_domingo_feriado, 2, ',', '.') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="taxa_madrugada" class="form-label">Taxa Madrugada (R$)</label>
                                <input type="text" class="form-control money" id="taxa_madrugada" name="taxa_madrugada" value="<?= number_format($orcamentoModel->taxa_madrugada, 2, ',', '.') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="taxa_horario_especial" class="form-label">Taxa Horário Especial (R$)</label>
                                <input type="text" class="form-control money" id="taxa_horario_especial" name="taxa_horario_especial" value="<?= number_format($orcamentoModel->taxa_horario_especial, 2, ',', '.') ?>">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="taxa_hora_marcada" class="form-label">Taxa Hora Marcada (R$)</label>
                                <input type="text" class="form-control money" id="taxa_hora_marcada" name="taxa_hora_marcada" value="<?= number_format($orcamentoModel->taxa_hora_marcada, 2, ',', '.') ?>">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="frete_terreo" class="form-label">Frete Térreo (R$)</label>
                                <input type="text" class="form-control money" id="frete_terreo" name="frete_terreo" value="<?= number_format($orcamentoModel->frete_terreo, 2, ',', '.') ?>">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="frete_elevador" class="form-label">Frete Elevador (Texto)</label>
                                <input type="text" class="form-control" id="frete_elevador" name="frete_elevador" value="<?= htmlspecialchars($orcamentoModel->frete_elevador); ?>">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="frete_escadas" class="form-label">Frete Escadas (Texto)</label>
                                <input type="text" class="form-control" id="frete_escadas" name="frete_escadas" value="<?= htmlspecialchars($orcamentoModel->frete_escadas); ?>">
                            </div>
                        </div>

                        <!-- Ajuste Manual de Valores (MOVIDO AQUI) -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Ajuste Manual</h4>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-2">
                                    <input type="checkbox" id="ajuste_manual" name="ajuste_manual" class="form-check-input" <?= $orcamentoModel->ajuste_manual ? 'checked' : '' ?>>
                                    <label for="ajuste_manual" class="form-check-label">Ajuste Manual de Valores</label>
                                </div>
                                <div class="form-group mt-2">
                                    <label for="motivo_ajuste">Motivo do Ajuste:</label>
                                    <input type="text" id="motivo_ajuste" name="motivo_ajuste" class="form-control" value="<?= htmlspecialchars($orcamentoModel->motivo_ajuste ?: '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Observações -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?= htmlspecialchars($orcamentoModel->observacoes) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Condições de Pagamento -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="condicoes_pagamento" class="form-label">Condições de Pagamento</label>
                                <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento" rows="3"><?= htmlspecialchars($orcamentoModel->condicoes_pagamento) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Botões de Ação -->
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>
                                <a href="<?php echo BASE_URL; ?>/views/orcamentos/" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-info">
                                    <i class="fas fa-home"></i> Voltar para Início
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script>
$(document).ready(function() {
    let searchTimeout;
    let clienteSelecionado = null; 
    <?php if ($orcamentoModel->cliente_id): ?>
        clienteSelecionado = {
            id: <?= $orcamentoModel->cliente_id ?>,
            nome: '<?= htmlspecialchars($orcamentoModel->nome_cliente) ?>',
            telefone: '<?= htmlspecialchars($orcamentoModel->cliente_telefone ?? '') ?>',
            email: '<?= htmlspecialchars($orcamentoModel->cliente_email ?? '') ?>',
            cpf_cnpj: '<?= htmlspecialchars($orcamentoModel->cliente_cpf_cnpj ?? '') ?>',
            endereco: '<?= htmlspecialchars($orcamentoModel->cliente_endereco ?? '') ?>',
            cidade: '<?= htmlspecialchars($orcamentoModel->cliente_cidade ?? '') ?>',
            observacoes: '<?= htmlspecialchars($orcamentoModel->cliente_observacoes ?? '') ?>'
        };
        $('#cliente_telefone').val(clienteSelecionado.telefone || 'Não informado');
        $('#cliente_email').val(clienteSelecionado.email || 'Não informado');
        $('#cliente_cpf_cnpj').val(clienteSelecionado.cpf_cnpj || 'Não informado');
        $('#cliente_endereco').val(clienteSelecionado.endereco || 'Não informado');
        $('#cliente_cidade').val(clienteSelecionado.cidade || 'Não informado');
        $('#cliente_observacoes').val(clienteSelecionado.observacoes || 'Nenhuma observação');
        $('#cliente_detalhes_display').show();
        if(clienteSelecionado.endereco) { 
            $('#usar_endereco_cliente').show();
        } else {
            $('#usar_endereco_cliente').hide();
        }
    <?php endif; ?>

    $('.datepicker').datepicker({
        dateFormat: 'dd/mm/yy',
        changeMonth: true,
        changeYear: true,
        yearRange: '2023:2030',
        showOn: 'button',
        buttonText: 'Selecionar Data',
        onSelect: function(dateText, inst) {
            if ($(this).attr('id') === 'data_evento') {
                atualizarDiaSemana(dateText, '#dia_semana_evento');
            } else if ($(this).attr('id') === 'data_devolucao_prevista') {
                atualizarDiaSemana(dateText, '#dia_semana_devolucao');
            } else if ($(this).attr('id') === 'data_orcamento') { 
                calcularDataValidadeFinal(); 
            }
            $(this).trigger('input'); 
        }
    });

    $('.money').inputmask('currency', {
        prefix: 'R$ ',
        groupSeparator: '.',
        radixPoint: ',',
        digits: 2,
        autoGroup: true,
        rightAlign: false,
        allowMinus: true 
    });

    function atualizarDiaSemana(dataStr, elemento) {
        if (dataStr) {
            var partes = dataStr.split('/');
            var dataFormatada = partes[2] + '-' + partes[1] + '-' + partes[0];
            var date = new Date(dataFormatada + 'T00:00:00'); 
            var diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
            var diaSemana = diasSemana[date.getUTCDay()]; 
            $(elemento).text('Dia da semana: ' + diaSemana);
        } else {
            $(elemento).text('');
        }
    }
    atualizarDiaSemana($('#data_evento').val(), '#dia_semana_evento');
    atualizarDiaSemana($('#data_devolucao_prevista').val(), '#dia_semana_devolucao');

    function calcularDataValidadeFinal() {
        var dataOrcamentoStr = $('#data_orcamento').val();
        var validadeDias = parseInt($('#validade_dias').val());

        if (dataOrcamentoStr && !isNaN(validadeDias)) {
            var partes = dataOrcamentoStr.split('/');
            var dataOrcamento = new Date(partes[2] + '-' + partes[1] + '-' + partes[0] + 'T00:00:00'); 
            
            dataOrcamento.setDate(dataOrcamento.getDate() + validadeDias);

            var dia = String(dataOrcamento.getDate()).padStart(2, '0');
            var mes = String(dataOrcamento.getMonth() + 1).padStart(2, '0');
            var ano = dataOrcamento.getFullYear();
            
            $('#data_validade_final_display').val(dia + '/' + mes + '/' + ano);
            $('#data_validade_hidden').val(dataOrcamento.toISOString().slice(0,10)); 
        } else {
            $('#data_validade_final_display').val('');
            $('#data_validade_hidden').val('');
        }
    }
    $('#data_orcamento').on('change', calcularDataValidadeFinal);
    $('#validade_dias').on('change', calcularDataValidadeFinal);
    calcularDataValidadeFinal();

    $('#busca_cliente').on('input', function() {
        clearTimeout(searchTimeout);
        var termo = $(this).val().trim();
        var resultadoDiv = $('#resultado_busca_cliente');
        
        if(termo.length < 2) {
            resultadoDiv.empty().hide();
            $('#cliente_id').val('');
            $('#nome_cliente_selecionado').text('');
            $('#info_cliente_selecionado').hide();
            $('#cliente_detalhes_display').hide();
            $('#usar_endereco_cliente').hide();
            clienteSelecionado = null;
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: window.location.pathname,
                data: { ajax: 'buscar_clientes', termo: termo },
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    resultadoDiv.empty();
                    
                    if(data.error){
                        resultadoDiv.append('<div class="list-group-item text-danger">'+data.error+'</div>');
                    } else if(data.length === 0){
                        resultadoDiv.append('<div class="list-group-item">Nenhum cliente encontrado.</div>');
                    } else {
                        data.forEach(function(cliente) {
                            var item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
                            item.html('<strong>' + cliente.nome + '</strong><br>' +
                                     '<small>CPF/CNPJ: ' + (cliente.cpf_cnpj || 'Não informado') + '</small>');
                            item.on('click', function(e) {
                                e.preventDefault();
                                selecionarCliente(cliente);
                            });
                            resultadoDiv.append(item);
                        });
                    }
                    resultadoDiv.show();
                },
                error: function(xhr, status, error) {
                    resultadoDiv.empty().append(
                        $('<div class="list-group-item text-danger">').text('Erro na busca de clientes.')
                    );
                }
            });
        }, 300);
    });
    
    function selecionarCliente(cliente) {
        $('#cliente_id').val(cliente.id);
        $('#busca_cliente').val(cliente.nome);
        
        $('#nome_cliente_selecionado').text(cliente.nome + ' (ID: ' + cliente.id + ')');
        $('#info_cliente_selecionado').show();
        
        $('#cliente_telefone').val(cliente.telefone || 'Não informado');
        $('#cliente_email').val(cliente.email || 'Não informado');
        $('#cliente_cpf_cnpj').val(cliente.cpf_cnpj || 'Não informado');
        $('#cliente_endereco').val(cliente.endereco || 'Não informado');
        $('#cliente_cidade').val(cliente.cidade || 'Não informado');
        $('#cliente_observacoes').val(cliente.observacoes || 'Nenhuma observação');
        
        $('#cliente_detalhes_display').show();

        if (cliente.endereco) { 
            $('#usar_endereco_cliente').show();
        } else {
            $('#usar_endereco_cliente').hide();
        }
        
        clienteSelecionado = cliente;

        $('#resultado_busca_cliente').empty().hide();
    }
    
    $('#usar_endereco_cliente').on('click', function() {
        if (clienteSelecionado && clienteSelecionado.endereco) {
            let enderecoCompleto = clienteSelecionado.endereco;
            if (clienteSelecionado.cidade) {
                enderecoCompleto += ' - ' + clienteSelecionado.cidade;
            }
            $('#local_evento').val(enderecoCompleto);
        } else {
            alert('Endereço do cliente não disponível.');
        }
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busca_cliente, #resultado_busca_cliente').length) {
            $('#resultado_busca_cliente').empty().hide();
        }
    });
    
    $('#btn_adicionar_produto').on('click', function() {
        adicionarLinhaProduto();
    });
    
    function adicionarLinhaProduto() {
        var linha = $('<tr>');
        linha.html(`
            <td>
                <input type="text" class="form-control busca-produto" placeholder="Digite para buscar produto">
                <div class="resultado-busca-produto list-group mt-1" style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto; width: 95%;"></div>
                <input type="hidden" class="produto-id" name="produto_id[]">
            </td>
            <td>
                <input type="number" class="form-control quantidade" name="quantidade[]" value="1" min="1" step="0.01">
            </td>
            <td>
                <input type="text" class="form-control money valor-unitario" name="valor_unitario[]" value="0,00">
            </td>
            <td>
                <input type="text" class="form-control money desconto" name="desconto_item[]" value="0,00" min="0">
            </td>
            <td>
                <input type="text" class="form-control total-linha money" readonly value="R$ 0,00">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remover-produto">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `);
        
        $('#produtos_tbody').append(linha);
        
        linha.find('.money').inputmask('currency', {
            prefix: 'R$ ',
            groupSeparator: '.',
            radixPoint: ',',
            digits: 2,
            autoGroup: true,
            rightAlign: false,
            allowMinus: true
        });
        
        configurarBuscaProduto(linha);
        configurarCalculos(linha);
        calcularTotais();
    }
    
    function configurarBuscaProduto(linha) {
        let searchTimeout;
        var inputBusca = linha.find('.busca-produto');
        var resultadoDiv = linha.find('.resultado-busca-produto');
        
        inputBusca.autocomplete({
            source: function(request, response) {
                clearTimeout(searchTimeout);
                var termo = request.term.trim();
                
                if (termo.length < 2) {
                    response([]);
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: window.location.pathname,
                        type: 'GET',
                        data: { 
                            ajax: 'buscar_produtos',
                            termo: termo 
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.error){
                                console.error(data.error);
                                response([]);
                            } else {
                                response(data.map(function(produto) {
                                    return {
                                        label: produto.nome + ' (Cód: ' + produto.codigo + ')',
                                        value: produto.nome,
                                        id: produto.id,
                                        preco_venda: produto.preco_venda
                                    };
                                }));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro na busca de produtos:', status, error);
                            response([]);
                        }
                    });
                }, 300);
            },
            minLength: 2,
            select: function(event, ui) {
                linha.find('.produto-id').val(ui.item.id);
                linha.find('.valor-unitario').val(parseFloat(ui.item.preco_venda).toFixed(2).replace('.', ',')).trigger('input'); 
                return false;
            },
            close: function() {
                resultadoDiv.empty().hide();
            }
        }).focus(function() {
            $(this).autocomplete('search', $(this).val());
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest(inputBusca).length && !$(e.target).closest(resultadoDiv).length) {
                resultadoDiv.empty().hide();
            }
        });
    }

    $('#produtos_tbody .item-row').each(function() {
        configurarBuscaProduto($(this));
    });

    function configurarCalculos(linha) {
        linha.find('.quantidade, .valor-unitario, .desconto').on('input change', function() {
            calcularTotalLinha(linha);
        });
    }
    
    function calcularTotalLinha(linha) {
        var quantidade = parseFloat(linha.find('.quantidade').val()) || 0;
        var valorUnitarioStr = linha.find('.valor-unitario').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var valorUnitario = parseFloat(valorUnitarioStr) || 0;
        var descontoStr = linha.find('.desconto').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var desconto = parseFloat(descontoStr) || 0;
        
        var subtotal = quantidade * valorUnitario;
        var total = subtotal - desconto;
        
        linha.find('.total-linha').val(total.toFixed(2).replace('.', ',')).trigger('input');
        
        calcularTotais();
    }
    
    function calcularTotais() {
        var subtotalLocacao = 0;
        var subtotalVenda = 0;

        $('#produtos_tbody tr').each(function() {
            var totalLinhaStr = $(this).find('.total-linha').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
            var totalLinha = parseFloat(totalLinhaStr) || 0;
            subtotalLocacao += totalLinha; 
        });
        
        $('#subtotal_locacao_display').text('R$ ' + subtotalLocacao.toFixed(2).replace('.', ','));
        $('#subtotal_venda_display').text('R$ ' + subtotalVenda.toFixed(2).replace('.', ',')); 
        
        var descontoTotalStr = $('#desconto_total').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var descontoTotal = parseFloat(descontoTotalStr) || 0;

        var taxaDomingoFeriadoStr = $('#taxa_domingo_feriado').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var taxaDomingoFeriado = parseFloat(taxaDomingoFeriadoStr) || 0;
        
        var taxaMadrugadaStr = $('#taxa_madrugada').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var taxaMadrugada = parseFloat(taxaMadrugadaStr) || 0;
        
        var taxaHorarioEspecialStr = $('#taxa_horario_especial').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var taxaHorarioEspecial = parseFloat(taxaHorarioEspecialStr) || 0;
        
        var taxaHoraMarcadaStr = $('#taxa_hora_marcada').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var taxaHoraMarcada = parseFloat(taxaHoraMarcadaStr) || 0;
        
        var freteTerreoStr = $('#frete_terreo').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.');
        var freteTerreo = parseFloat(freteTerreoStr) || 0;

        var totalTaxas = taxaDomingoFeriado + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada;
        var totalFrete = freteTerreo; 

        var valorFinal = (subtotalLocacao + subtotalVenda + totalTaxas + totalFrete) - descontoTotal;
        $('#total_geral').text('R$ ' + valorFinal.toFixed(2).replace('.', ','));
    }
    
    $(document).on('click', '.remover-produto', function() {
        $(this).closest('tr').remove();
        calcularTotais();
    });
    
    $('#desconto_total, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo').on('input change', function() {
        calcularTotais();
    });
    
    $('#produtos_tbody tr').each(function() { 
        configurarCalculos($(this));
        calcularTotalLinha($(this));
    });
    calcularTotais();

    $('#form-orcamento').on('submit', function(e) {
        if (!$('#cliente_id').val()) {
            e.preventDefault();
            alert('Por favor, selecione um cliente.');
            $('#busca_cliente').focus();
            return false;
        }
    });
});
</script>