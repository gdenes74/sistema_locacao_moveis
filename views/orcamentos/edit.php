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

// Carregar itens do orçamento
$itens = $orcamentoModel->getItens($id);

// Carregar dados para autocomplete de clientes
$stmtClientes = $clienteModel->getAll();
$autocompleteData = [];
while ($row = $stmtClientes->fetch(PDO::FETCH_ASSOC)) {
    $autocompleteData[] = [
        'id' => $row['id'],
        'nome' => $row['nome'],
        'telefone' => $row['telefone'] ?? '',
        'cpf_cnpj' => $row['cpf_cnpj'] ?? '',
        'endereco' => $row['endereco'] ?? ''
    ];
}

// Carregar nome do cliente atual
$clienteModel->getById($orcamentoModel->cliente_id);
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
                    <form action="update.php" method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($orcamentoModel->id) ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cliente_nome">Cliente:</label>
                                    <input type="text" id="cliente_nome" class="form-control" value="<?= htmlspecialchars($clienteModel->nome) ?>" required>
                                    <input type="hidden" name="cliente_id" id="cliente_id" value="<?= htmlspecialchars($orcamentoModel->cliente_id) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="data_orcamento">Data do Orçamento:</label>
                                    <input type="text" id="data_orcamento" name="data_orcamento" class="form-control datepicker" value="<?= date('d/m/Y', strtotime($orcamentoModel->data_orcamento)) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="data_validade">Data de Validade:</label>
                                    <input type="text" id="data_validade" name="data_validade" class="form-control datepicker" value="<?= date('d/m/Y', strtotime($orcamentoModel->data_validade)) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="data_evento">Data do Evento:</label>
                                    <input type="text" id="data_evento" name="data_evento" class="form-control datepicker" value="<?= $orcamentoModel->data_evento ? date('d/m/Y', strtotime($orcamentoModel->data_evento)) : '' ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="hora_evento">Hora do Evento:</label>
                                    <input type="time" id="hora_evento" name="hora_evento" class="form-control" value="<?= $orcamentoModel->hora_evento ? htmlspecialchars($orcamentoModel->hora_evento) : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label for="local_evento">Local do Evento:</label>
                                    <textarea id="local_evento" name="local_evento" class="form-control" rows="2"><?= htmlspecialchars($orcamentoModel->local_evento) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="data_devolucao_prevista">Data de Devolução Prevista:</label>
                                    <input type="text" id="data_devolucao_prevista" name="data_devolucao_prevista" class="form-control datepicker" value="<?= $orcamentoModel->data_devolucao_prevista ? date('d/m/Y', strtotime($orcamentoModel->data_devolucao_prevista)) : '' ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="tipo">Tipo:</label>
                                    <select id="tipo" name="tipo" class="form-control" required>
                                        <option value="locacao" <?= $orcamentoModel->tipo === 'locacao' ? 'selected' : '' ?>>Locação</option>
                                        <option value="venda" <?= $orcamentoModel->tipo === 'venda' ? 'selected' : '' ?>>Venda</option>
                                        <option value="misto" <?= $orcamentoModel->tipo === 'misto' ? 'selected' : '' ?>>Misto</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="status">Status:</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="pendente" <?= $orcamentoModel->status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="aprovado" <?= $orcamentoModel->status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                        <option value="recusado" <?= $orcamentoModel->status === 'recusado' ? 'selected' : '' ?>>Recusado</option>
                                        <option value="expirado" <?= $orcamentoModel->status === 'expirado' ? 'selected' : '' ?>>Expirado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Valores</h5>
                                <div class="form-group">
                                    <label for="subtotal_locacao">Subtotal Locação (R$):</label>
                                    <input type="number" id="subtotal_locacao" name="subtotal_locacao" class="form-control" step="0.01" value="<?= $orcamentoModel->subtotal_locacao ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="subtotal_venda">Subtotal Venda (R$):</label>
                                    <input type="number" id="subtotal_venda" name="subtotal_venda" class="form-control" step="0.01" value="<?= $orcamentoModel->subtotal_venda ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="desconto">Desconto (R$):</label>
                                    <input type="number" id="desconto" name="desconto" class="form-control" step="0.01" value="<?= $orcamentoModel->desconto ?>">
                                </div>
                                <div class="form-group">
                                    <label for="taxa_domingo_feriado">Taxa Domingo/Feriado (R$):</label>
                                    <input type="number" id="taxa_domingo_feriado" name="taxa_domingo_feriado" class="form-control" step="0.01" value="<?= $orcamentoModel->taxa_domingo_feriado ?>">
                                </div>
                                <div class="form-group">
                                    <label for="taxa_madrugada">Taxa Madrugada (R$):</label>
                                    <input type="number" id="taxa_madrugada" name="taxa_madrugada" class="form-control" step="0.01" value="<?= $orcamentoModel->taxa_madrugada ?>">
                                </div>
                                <div class="form-group">
                                    <label for="taxa_horario_especial">Taxa Horário Especial (R$):</label>
                                    <input type="number" id="taxa_horario_especial" name="taxa_horario_especial" class="form-control" step="0.01" value="<?= $orcamentoModel->taxa_horario_especial ?>">
                                </div>
                                <div class="form-group">
                                    <label for="taxa_hora_marcada">Taxa Hora Marcada (R$):</label>
                                    <input type="number" id="taxa_hora_marcada" name="taxa_hora_marcada" class="form-control" step="0.01" value="<?= $orcamentoModel->taxa_hora_marcada ?>">
                                </div>
                                <div class="form-group">
                                    <label for="frete_terreo">Frete Térreo (R$):</label>
                                    <input type="number" id="frete_terreo" name="frete_terreo" class="form-control" step="0.01" value="<?= $orcamentoModel->frete_terreo ?>">
                                </div>
                                <div class="form-group">
                                    <label for="frete_elevador">Frete Elevador:</label>
                                    <input type="text" id="frete_elevador" name="frete_elevador" class="form-control" value="<?= htmlspecialchars($orcamentoModel->frete_elevador) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="frete_escadas">Frete Escadas:</label>
                                    <input type="text" id="frete_escadas" name="frete_escadas" class="form-control" value="<?= htmlspecialchars($orcamentoModel->frete_escadas) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="valor_final">Valor Final (R$):</label>
                                    <input type="number" id="valor_final" name="valor_final" class="form-control" step="0.01" value="<?= $orcamentoModel->valor_final ?>" readonly>
                                </div>
                                <div class="form-group form-check">
                                    <input type="checkbox" id="ajuste_manual" name="ajuste_manual" class="form-check-input" <?= $orcamentoModel->ajuste_manual ? 'checked' : '' ?>>
                                    <label for="ajuste_manual" class="form-check-label">Ajuste Manual</label>
                                </div>
                                <div class="form-group">
                                    <label for="motivo_ajuste">Motivo do Ajuste:</label>
                                    <input type="text" id="motivo_ajuste" name="motivo_ajuste" class="form-control" value="<?= htmlspecialchars($orcamentoModel->motivo_ajuste ?: '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <h5>Itens do Orçamento</h5>
                                <div id="itensContainer">
                                    <?php if (!empty($itens)): ?>
                                        <?php foreach ($itens as $index => $item): ?>
                                            <div class="form-group item-row" id="item_<?= $index ?>">
                                                <input type="hidden" name="itens[<?= $index ?>][id]" value="<?= htmlspecialchars($item['id']) ?>">
                                                <label>Produto <?= $index + 1 ?>:</label>
                                                <input type="text" name="itens[<?= $index ?>][produto_nome]" class="form-control produto_nome" value="<?php
                                                    $stmtProd = $conn->prepare("SELECT nome_produto FROM produtos WHERE id = :produto_id");
                                                    $stmtProd->bindParam(':produto_id', $item['produto_id']);
                                                    $stmtProd->execute();
                                                    echo htmlspecialchars($stmtProd->fetchColumn() ?: 'Desconhecido');
                                                ?>" required>
                                                <input type="hidden" name="itens[<?= $index ?>][produto_id]" class="produto_id" value="<?= htmlspecialchars($item['produto_id']) ?>" required>
                                                <input type="number" name="itens[<?= $index ?>][quantidade]" class="form-control mt-1 quantidade" value="<?= htmlspecialchars($item['quantidade']) ?>" placeholder="Quantidade" min="1" required>
                                                <input type="number" name="itens[<?= $index ?>][preco_unitario]" class="form-control mt-1 preco_unitario" value="<?= htmlspecialchars($item['preco_unitario']) ?>" placeholder="Preço Unitário" step="0.01" required>
                                                <input type="number" name="itens[<?= $index ?>][desconto]" class="form-control mt-1 desconto_item" value="<?= htmlspecialchars($item['desconto']) ?>" placeholder="Desconto" step="0.01">
                                                <input type="number" name="itens[<?= $index ?>][preco_final]" class="form-control mt-1 preco_final" value="<?= htmlspecialchars($item['preco_final']) ?>" placeholder="Preço Final" step="0.01" readonly>
                                                <input type="hidden" name="itens[<?= $index ?>][tipo]" value="<?= htmlspecialchars($item['tipo']) ?>">
                                                <div class="form-group mt-1">
                                                    <label>Observações:</label>
                                                    <textarea name="itens[<?= $index ?>][observacoes]" class="form-control"><?= htmlspecialchars($item['observacoes'] ?: '') ?></textarea>
                                                </div>
                                                <button type="button" class="btn btn-danger mt-2" onclick="removerItem(<?= $index ?>)"">Remover</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Container vazio, será preenchido dinamicamente -->
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-primary mt-3" onclick="adicionarItem()">Adicionar Item</button>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="observacoes">Observações:</label>
                                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($orcamentoModel->observacoes) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="condicoes_pagamento">Condições de Pagamento:</label>
                                    <textarea id="condicoes_pagamento" name="condicoes_pagamento" class="form-control" rows="3"><?= htmlspecialchars($orcamentoModel->condicoes_pagamento) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success mt-3">Salvar Alterações</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script>
$(document).ready(function() {
    // Inicializar datepicker
    $('.datepicker').datepicker({
        dateFormat: 'dd/mm/yy',
        changeMonth: true,
        changeYear: true,
        yearRange: '2023:2030',
        minDate: 0,
        showOn: 'button',
        buttonText: 'Selecionar Data'
    });

    // Autocomplete de cliente
    var clientes = <?= json_encode($autocompleteData) ?>;
    $('#cliente_nome').autocomplete({
        source: function(request, response) {
            var term = request.term.toLowerCase();
            var matches = clientes.filter(function(cliente) {
                return cliente.nome.toLowerCase().includes(term) || 
                       (cliente.cpf_cnpj && cliente.cpf_cnpj.toLowerCase().includes(term));
            });
            response(matches.map(function(cliente) {
                return {
                    label: cliente.nome + (cliente.cpf_cnpj ? ' - ' + cliente.cpf_cnpj : ''),
                    value: cliente.nome,
                    id: cliente.id,
                    endereco: cliente.endereco
                };
            }));
        },
        minLength: 2,
        select: function(event, ui) {
            $('#cliente_id').val(ui.item.id);
            $('#local_evento').val(ui.item.endereco);
            return false;
        }
    });

    // Variável para contar itens
    var itemCount = <?= count($itens) ? count($itens) - 1 : -1 ?>;
    window.adicionarItem = function() {
        itemCount++;
        let html = `
            <div class="form-group item-row" id="item_${itemCount}">
                <label>Produto ${itemCount + 1}:</label>
                <input type="text" name="itens[${itemCount}][produto_nome]" class="form-control produto_nome" placeholder="Nome do Produto" required>
                <input type="hidden" name="itens[${itemCount}][produto_id]" class="produto_id" required>
                <input type="number" name="itens[${itemCount}][quantidade]" class="form-control mt-1 quantidade" placeholder="Quantidade" min="1" required>
                <input type="number" name="itens[${itemCount}][preco_unitario]" class="form-control mt-1 preco_unitario" placeholder="Preço Unitário" step="0.01" value="0.00" required>
                <input type="number" name="itens[${itemCount}][desconto]" class="form-control mt-1 desconto_item" placeholder="Desconto" step="0.01" value="0.00">
                <input type="number" name="itens[${itemCount}][preco_final]" class="form-control mt-1 preco_final" placeholder="Preço Final" step="0.01" value="0.00" readonly>
                <input type="hidden" name="itens[${itemCount}][tipo]" value="locacao">
                <div class="form-group mt-1">
                    <label>Observações:</label>
                    <textarea name="itens[${itemCount}][observacoes]" class="form-control"></textarea>
                </div>
                <button type="button" class="btn btn-danger mt-2" onclick="removerItem(${itemCount})"">Remover</button>
            </div>
        `;
        $('#itensContainer').append(html);
    };

    window.removerItem = function(id) {
        $(`#item_${id}`).remove();
        atualizarTotais();
    };

    window.atualizarTotais = function() {
        let subtotalLocacao = 0;
        let subtotalVenda = 0;
        $('.item-row').each(function() {
            let quantidade = parseInt($(this).find('.quantidade').val()) || 0;
            let precoUnitario = parseFloat($(this).find('.preco_unitario').val()) || 0;
            let desconto = parseFloat($(this).find('.desconto_item').val()) || 0;
            let precoFinal = (quantidade * precoUnitario) - desconto;
            $(this).find('.preco_final').val(precoFinal.toFixed(2));
            let tipo = $(this).find('input[name*="tipo"]').val();
            if (tipo === 'locacao') {
                subtotalLocacao += precoFinal;
            } else if (tipo === 'venda') {
                subtotalVenda += precoFinal;
            }
        });
        $('#subtotal_locacao').val(subtotalLocacao.toFixed(2));
        $('#subtotal_venda').val(subtotalVenda.toFixed(2));
        let desconto = parseFloat($('#desconto').val()) || 0;
        let taxas = (parseFloat($('#taxa_domingo_feriado').val()) || 0) +
                    (parseFloat($('#taxa_madrugada').val()) || 0) +
                    (parseFloat($('#taxa_horario_especial').val()) || 0) +
                    (parseFloat($('#taxa_hora_marcada').val()) || 0);
        let frete = parseFloat($('#frete_terreo').val()) || 0;
        let valorFinal = (subtotalLocacao + subtotalVenda) - desconto + taxas + frete;
        $('#valor_final').val(valorFinal.toFixed(2));
    };

    // Atualiza totais ao mudar valores
    $(document).on('change', '.quantidade, .preco_unitario, .desconto_item, #desconto, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo', function() {
        atualizarTotais();
    });

    // Atualiza totais na carga da página
    atualizarTotais();
});
</script>