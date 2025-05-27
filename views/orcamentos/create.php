<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php'; 

$database = new Database();
$db = $database->getConnection();

$cliente = new Cliente($db);
$produto = new Produto($db);
$numeracao = new NumeracaoSequencial($db);

// Gerar número do orçamento
$numeroSequencial = $numeracao->gerarProximoNumero('orcamento');
$numeroOrcamento = $numeracao->formatarNumeroOrcamento($numeroSequencial);

$clientes = $cliente->listarTodos()->fetchAll(PDO::FETCH_ASSOC);
$produtos = $produto->listarTodos()->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("
            INSERT INTO orcamentos (numero, cliente_id, data_evento, local_evento, observacoes, valor_total, status) 
            VALUES (:numero, :cliente_id, :data_evento, :local_evento, :observacoes, :valor_total, :status)
        ");
        
        $stmt->bindParam(':numero', $numeroOrcamento);
        $stmt->bindParam(':cliente_id', $_POST['cliente_id']);
        $stmt->bindParam(':data_evento', $_POST['data_evento']);
        $stmt->bindParam(':local_evento', $_POST['local_evento']);
        $stmt->bindParam(':observacoes', $_POST['observacoes']);
        $stmt->bindParam(':valor_total', $_POST['valor_total']);
        $stmt->bindParam(':status', $_POST['status']);
        
        $stmt->execute();
        $orcamento_id = $db->lastInsertId();
        
        // Inserir itens do orçamento
        if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
            foreach ($_POST['produtos'] as $item) {
                $stmt_item = $db->prepare("
                    INSERT INTO orcamento_itens (orcamento_id, produto_id, quantidade, valor_unitario, valor_total) 
                    VALUES (:orcamento_id, :produto_id, :quantidade, :valor_unitario, :valor_total)
                ");
                
                $stmt_item->bindParam(':orcamento_id', $orcamento_id);
                $stmt_item->bindParam(':produto_id', $item['produto_id']);
                $stmt_item->bindParam(':quantidade', $item['quantidade']);
                $stmt_item->bindParam(':valor_unitario', $item['valor_unitario']);
                $stmt_item->bindParam(':valor_total', $item['valor_total']);
                
                $stmt_item->execute();
            }
        }
        
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $erro = "Erro ao criar orçamento: " . $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Novo Orçamento</h4>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($erro)): ?>
                        <div class="alert alert-danger"><?php echo $erro; ?></div>
                    <?php endif; ?>

                    <form method="POST" id="form-orcamento">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Número do Orçamento</label>
                                    <input type="text" class="form-control" value="<?php echo $numeroOrcamento; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control" required>
                                        <option value="pendente">Pendente</option>
                                        <option value="aprovado">Aprovado</option>
                                        <option value="rejeitado">Rejeitado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cliente</label>
                                    <select name="cliente_id" id="cliente_select" class="form-control" required>
                                        <option value="">Selecione um cliente</option>
                                        <?php foreach ($clientes as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" 
                                                    data-endereco="<?php echo htmlspecialchars($c['endereco']); ?>">
                                                <?php echo htmlspecialchars($c['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Data do Evento</label>
                                    <input type="date" name="data_evento" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Local do Evento</label>
                                    <div class="input-group">
                                        <input type="text" name="local_evento" id="local_evento" class="form-control" required>
                                        <div class="input-group-append">
                                            <button type="button" id="usar_endereco_cliente" class="btn btn-outline-secondary">
                                                Usar endereço do cliente
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="cliente_endereco">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3"></textarea>
                        </div>

                        <hr>

                        <h5>Produtos</h5>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select id="produto_select" class="form-control">
                                    <option value="">Selecione um produto</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                data-nome="<?php echo htmlspecialchars($p['nome']); ?>"
                                                data-preco="<?php echo $p['preco']; ?>">
                                            <?php echo htmlspecialchars($p['nome']); ?> - R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" id="quantidade_produto" class="form-control" placeholder="Qtd" min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <input type="number" id="preco_produto" class="form-control" placeholder="Preço" step="0.01" min="0">
                            </div>
                            <div class="col-md-2">
                                <button type="button" id="adicionar_produto" class="btn btn-primary">Adicionar</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="tabela_produtos">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Quantidade</th>
                                        <th>Valor Unitário</th>
                                        <th>Valor Total</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">Total Geral:</th>
                                        <th id="total_geral">R$ 0,00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <input type="hidden" name="valor_total" id="valor_total_hidden" value="0">

                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Salvar Orçamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let contadorProdutos = 0;

    $('#cliente_select').change(function() {
        var endereco = $(this).find('option:selected').data('endereco');
        $('#cliente_endereco').val(endereco || '');
    });

    $('#produto_select').change(function() {
        var preco = $(this).find('option:selected').data('preco');
        $('#preco_produto').val(preco || '');
    });

    $('#adicionar_produto').click(function() {
        var produtoId = $('#produto_select').val();
        var produtoNome = $('#produto_select option:selected').data('nome');
        var quantidade = $('#quantidade_produto').val();
        var precoUnitario = $('#preco_produto').val();

        if (!produtoId || !quantidade || !precoUnitario) {
            alert('Por favor, preencha todos os campos do produto.');
            return;
        }

        var valorTotal = parseFloat(quantidade) * parseFloat(precoUnitario);

        var linha = `
            <tr>
                <td>
                    ${produtoNome}
                    <input type="hidden" name="produtos[${contadorProdutos}][produto_id]" value="${produtoId}">
                    <input type="hidden" name="produtos[${contadorProdutos}][quantidade]" value="${quantidade}">
                    <input type="hidden" name="produtos[${contadorProdutos}][valor_unitario]" value="${precoUnitario}">
                    <input type="hidden" name="produtos[${contadorProdutos}][valor_total]" value="${valorTotal.toFixed(2)}">
                </td>
                <td>${quantidade}</td>
                <td>R$ ${parseFloat(precoUnitario).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                <td>R$ ${valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger btn-remover-produto">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#tabela_produtos tbody').append(linha);
        contadorProdutos++;

        // Limpar campos
        $('#produto_select').val('');
        $('#quantidade_produto').val('1');
        $('#preco_produto').val('');

        atualizarTotal();
    });

    function atualizarTotal() {
        var total = 0;
        $('#tabela_produtos tbody tr').each(function() {
            var valorLinha = parseFloat($(this).find('input[name*="valor_total"]').val()) || 0;
            total += valorLinha;
        });

        $('#total_geral').text('R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
        $('#valor_total_hidden').val(total.toFixed(2));
    }

    $(document).on('click', '.btn-remover-produto', function() {
        $(this).closest('tr').remove();
        atualizarTotal();
    });

    $('#usar_endereco_cliente').click(function() {
        var endereco = $('#cliente_endereco').val();
        if (endereco) {
            $('#local_evento').val(endereco);
        } else {
            alert('Endereço do cliente não disponível.');
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>