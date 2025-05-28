<?php
$page_title = "Novo Orçamento";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../includes/functions.php'; // Certifique-se que setFlashMessage está aqui, ou remova se não usar

// Criar a conexão com o banco de dados UMA ÚNICA VEZ para o script principal
$database = new Database();
$db = $database->getConnection();

// Instanciar modelos
$clienteModel = new Cliente($db);
$produtoModel = new Produto($db);
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db);

// Gerar próximo número de orçamento (se necessário, conforme sua lógica)
$proximoNumero = $numeracaoModel->gerarProximoNumero('orcamento');
$numeroFormatado = $numeracaoModel->formatarNumeroOrcamento($proximoNumero);

// PARTE PHP: processamento AJAX para busca de clientes
// Este bloco é executado APENAS se a requisição for AJAX para buscar clientes
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_clientes') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // A conexão $db já está disponível aqui
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        
        if ($termo === '') {
            echo json_encode([]);
            exit;
        }
        
        // ATUALIZADO: Selecionando TODOS os campos do cliente
        $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes, data_cadastro FROM clientes WHERE nome LIKE ? OR cpf_cnpj LIKE ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $termo . '%', '%' . $termo . '%']);
        $clientes_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC); // Renomeado para evitar conflito
        
        echo json_encode($clientes_ajax);
        exit; // Importante: parar a execução aqui para não renderizar o HTML
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
        exit;
    }
}

// PARTE PHP: processamento AJAX para busca de produtos
// Este bloco é executado APENAS se a requisição for AJAX para buscar produtos
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_produtos') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // A conexão $db já está disponível aqui
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        
        if ($termo === '') {
            echo json_encode([]);
            exit;
        }
        
        $sql = "SELECT id, nome, codigo, preco_venda FROM produtos WHERE nome LIKE ? OR codigo LIKE ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $termo . '%', '%' . $termo . '%']);
        $produtos_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC); // Renomeado para evitar conflito
        
        echo json_encode($produtos_ajax);
        exit; // Importante: parar a execução aqui para não renderizar o HTML
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
        exit;
    }
}

// PARTE PHP: processamento do formulário (quando o formulário é enviado via POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $orcamento = new Orcamento($db);
        
        // Definir as propriedades do objeto Orcamento com base nos dados do formulário
        $orcamento->cliente_id = $_POST['cliente_id'];
        $orcamento->data_orcamento = $_POST['data_orcamento'];
        $orcamento->data_validade = date('Y-m-d', strtotime($_POST['data_orcamento'] . ' + ' . $_POST['validade'] . ' days'));
        $orcamento->status = isset($_POST['status']) ? $_POST['status'] : 'pendente';
        $orcamento->desconto = isset($_POST['desconto_total']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_total']) : 0;
        $orcamento->observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';
        // Definir valores padrão para outros campos obrigatórios ou necessários, conforme a lógica da classe
        $orcamento->tipo = 'locacao'; // Valor padrão, ajuste conforme sua necessidade
        $orcamento->valor_total_locacao = 0; // Será recalculado depois
        $orcamento->subtotal_locacao = 0; // Será recalculado depois
        $orcamento->valor_total_venda = 0; // Será recalculado depois
        $orcamento->subtotal_venda = 0; // Será recalculado depois
        $orcamento->valor_final = 0; // Será recalculado depois
        $orcamento->taxa_domingo_feriado = 0;
        $orcamento->taxa_madrugada = 0;
        $orcamento->taxa_horario_especial = 0;
        $orcamento->taxa_hora_marcada = 0;
        $orcamento->frete_elevador = 0;
        $orcamento->frete_escadas = 0;
        $orcamento->frete_terreo = 0;
        $orcamento->ajuste_manual = false;
        $orcamento->motivo_ajuste = '';
        $orcamento->condicoes_pagamento = '';
        $orcamento->local_evento = '';
        $orcamento->data_evento = null;
        $orcamento->hora_evento = null;
        $orcamento->data_devolucao_prevista = null;
        // Usuário ID (ajuste conforme sua lógica de autenticação)
        $orcamento->usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;

        // Criar o orçamento (sem passar argumentos, conforme a definição da classe)
        $result = $orcamento->create();
        if ($result !== false) {
            // Obter o ID do orçamento recém-criado
            $orcamentoId = $result; // O método create() retorna o ID

            // Preparar itens do orçamento
            $itens = [];
            if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
                foreach ($_POST['produto_id'] as $index => $produto_id) {
                    if (!empty($produto_id)) {
                        $quantidade = isset($_POST['quantidade'][$index]) ? $_POST['quantidade'][$index] : 1;
                        $preco_unitario = isset($_POST['valor_unitario'][$index]) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_unitario'][$index]) : 0;
                        $desconto_item = isset($_POST['desconto_item'][$index]) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_item'][$index]) : 0;
                        $preco_final = ($quantidade * $preco_unitario) - $desconto_item;

                        $itens[] = [
                            'produto_id' => $produto_id,
                            'quantidade' => $quantidade,
                            'tipo' => 'locacao', // Ajuste conforme necessário
                            'preco_unitario' => $preco_unitario,
                            'desconto' => $desconto_item,
                            'preco_final' => $preco_final,
                            'ajuste_manual' => false, // Ajuste conforme necessário
                            'motivo_ajuste' => '', // Ajuste conforme necessário
                            'observacoes' => '' // Ajuste conforme necessário
                        ];
                    }
                }
            }

            // Salvar os itens do orçamento, se houver
            if (!empty($itens)) {
                $itensSalvos = $orcamento->salvarItens($orcamentoId, $itens);
                if (!$itensSalvos) {
                    throw new Exception("Erro ao salvar os itens do orçamento.");
                }
            }

            // Recalcular os valores totais com base nos itens
            $orcamento->recalcularValores($orcamentoId);

            // Redirecionar ou exibir mensagem de sucesso
            // setFlashMessage('success', 'Orçamento criado com sucesso!'); // Descomente se tiver essa função
            header("Location: index.php"); // Redireciona para a lista de orçamentos
            exit;
        } else {
            // setFlashMessage('error', 'Erro ao criar orçamento.'); // Descomente se tiver essa função
        }
    } catch (Exception $e) {
        // setFlashMessage('error', 'Erro: ' . $e->getMessage()); // Descomente se tiver essa função
        error_log("Erro ao criar orçamento: " . $e->getMessage());
    }
}

// Buscar listas para os selects (se você ainda usar selects estáticos em algum lugar)
// Se você usa apenas a busca AJAX, essas linhas podem ser removidas para otimização
$clientes_lista = $clienteModel->listarTodos(); // Renomeado para evitar conflito com $clientes_ajax
$produtos_lista = $produtoModel->listarTodos(); // Renomeado para evitar conflito com $produtos_ajax

// Incluir o cabeçalho da página
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Novo Orçamento</h3>
                </div>
                <div class="card-body">
                    <form id="form-orcamento" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- Informações do Orçamento -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="numero_orcamento" class="form-label">Número do Orçamento</label>
                                <input type="text" class="form-control" id="numero_orcamento" name="numero_orcamento" 
                                       value="<?php echo htmlspecialchars($numeroFormatado); ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="data_orcamento" class="form-label">Data</label>
                                <input type="date" class="form-control" id="data_orcamento" name="data_orcamento" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="validade" class="form-label">Validade (dias)</label>
                                <input type="number" class="form-control" id="validade" name="validade" value="30" required>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="pendente">Pendente</option>
                                    <option value="aprovado">Aprovado</option>
                                    <option value="rejeitado">Rejeitado</option>
                                </select>
                            </div>
                        </div>

                        <!-- Seleção de Cliente -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="busca_cliente" class="form-label">Buscar Cliente</label>
                                <input type="text" class="form-control" id="busca_cliente" 
                                       placeholder="Digite o nome ou CPF/CNPJ do cliente" autocomplete="off" />
                                <div id="resultado_busca_cliente" class="list-group mt-1" 
                                     style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto; width: 95%;"></div>
                                <input type="hidden" id="cliente_id" name="cliente_id" required />
                            </div>
                            <div class="col-md-6">
                                <div id="info_cliente_selecionado" class="alert alert-info" style="display: none;">
                                    <strong>Cliente Selecionado:</strong>
                                    <span id="nome_cliente_selecionado"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Detalhes do Cliente (ATUALIZADO PARA EXIBIR TODOS OS ATRIBUTOS) -->
                        <div class="row mb-3" id="cliente_detalhes_display" style="display: none;">
                            <div class="col-md-3">
                                <label for="cliente_telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="cliente_telefone" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="cliente_email" class="form-label">Email</label>
                                <input type="text" class="form-control" id="cliente_email" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="cliente_cpf_cnpj" class="form-label">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="cliente_cpf_cnpj" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="cliente_cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cliente_cidade" readonly>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label for="cliente_endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="cliente_endereco" readonly>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label for="cliente_observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="cliente_observacoes" rows="2" readonly></textarea>
                            </div>
                        </div>

                        <!-- Botão para Adicionar Novo Cliente -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <a href="<?php echo BASE_URL; ?>/views/clientes/index.php" class="btn btn-info">
                                    <i class="fas fa-user-plus"></i> Adicionar Novo Cliente
                                </a>
                            </div>
                        </div>

                        <!-- Tabela de Produtos -->
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
                                                <th style="width: 15%">Desconto (%)</th>
                                                <th style="width: 15%">Total</th>
                                                <th style="width: 50px">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody id="produtos_tbody">
                                            <!-- Linhas de produtos serão adicionadas dinamicamente -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                                <td><strong id="subtotal">R$ 0,00</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Desconto Total:</strong></td>
                                                <td><input type="text" class="form-control money" id="desconto_total" name="desconto_total" value="0,00"></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Total Geral:</strong></td>
                                                <td><strong id="total_geral">R$ 0,00</strong></td>
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

                        <!-- Observações -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                                                       <i class="fas fa-save"></i> Salvar Orçamento
                                </button>
                                <a href="<?php echo BASE_URL; ?>/views/orcamentos/" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript customizado
$custom_js = <<<JS
$(document).ready(function() {
    // Variável para controlar timeout da busca
    let searchTimeout;
    
    // Busca de clientes
    $('#busca_cliente').on('input', function() {
        clearTimeout(searchTimeout);
        var termo = $(this).val().trim(); // Use var para escopo de função
        var resultadoDiv = $('#resultado_busca_cliente'); // Use var
        
        if(termo.length < 2) {
            resultadoDiv.empty().hide();
            // Limpa os detalhes do cliente se o termo for muito curto
            $('#cliente_id').val('');
            $('#nome_cliente_selecionado').text('');
            $('#info_cliente_selecionado').hide();
            $('#cliente_detalhes_display').hide();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: window.location.pathname, // O próprio arquivo (onde o PHP AJAX está)
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
                    console.error('Erro na busca de clientes:', error, xhr.responseText);
                }
            });
        }, 300);
    });
    
    // Função para selecionar cliente (ATUALIZADA PARA PREENCHER TODOS OS ATRIBUTOS)
    function selecionarCliente(cliente) {
        $('#cliente_id').val(cliente.id);
        $('#busca_cliente').val(cliente.nome); // Preenche o campo de busca com o nome do cliente
        
        // Exibe o nome do cliente selecionado na área de info
        $('#nome_cliente_selecionado').text(cliente.nome + ' (ID: ' + cliente.id + ')');
        $('#info_cliente_selecionado').show();
        
        // Preenche os campos de detalhes do cliente
        $('#cliente_telefone').val(cliente.telefone || 'Não informado');
        $('#cliente_email').val(cliente.email || 'Não informado');
        $('#cliente_cpf_cnpj').val(cliente.cpf_cnpj || 'Não informado');
        $('#cliente_endereco').val(cliente.endereco || 'Não informado');
        $('#cliente_cidade').val(cliente.cidade || 'Não informado');
        $('#cliente_observacoes').val(cliente.observacoes || 'Nenhuma observação');
        
        // Exibe a seção de detalhes do cliente
        $('#cliente_detalhes_display').show();

        // Esconde a lista de resultados da busca
        $('#resultado_busca_cliente').empty().hide();
    }
    
    // Esconder resultados ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busca_cliente, #resultado_busca_cliente').length) {
            $('#resultado_busca_cliente').empty().hide();
        }
    });
    
    // Adicionar linha de produto
    $('#btn_adicionar_produto').on('click', function() {
        adicionarLinhaProduto();
    });
    
    // Função para adicionar linha de produto
    function adicionarLinhaProduto() {
        var linha = $('<tr>'); // Use var
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
                <input type="number" class="form-control desconto" name="desconto_item[]" value="0" min="0" max="100" step="0.01">
            </td>
            <td>
                <input type="text" class="form-control total-linha" readonly value="R$ 0,00">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remover-produto">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `);
        
        $('#produtos_tbody').append(linha);
        
        // Aplicar máscara monetária
        linha.find('.money').inputmask('currency', {
            prefix: 'R$ ',
            groupSeparator: '.',
            radixPoint: ',',
            digits: 2,
            autoGroup: true,
            rightAlign: false
        });
        
        // Configurar busca de produto para esta linha
        configurarBuscaProduto(linha);
        
        // Configurar cálculos
        configurarCalculos(linha);
    }
    
    // Configurar busca de produto
    function configurarBuscaProduto(linha) {
        let searchTimeout;
        var inputBusca = linha.find('.busca-produto'); // Use var
        var resultadoDiv = linha.find('.resultado-busca-produto'); // Use var
        
        inputBusca.on('input', function() {
            clearTimeout(searchTimeout);
            var termo = $(this).val().trim(); // Use var
            
            if (termo.length < 2) {
                resultadoDiv.empty().hide();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: window.location.pathname, // O próprio arquivo (onde o PHP AJAX está)
                    type: 'GET',
                    data: { 
                        ajax: 'buscar_produtos', 
                        termo: termo 
                    },
                    dataType: 'json',
                    success: function(data) {
                        resultadoDiv.empty();
                        
                        if (data.error){
                            resultadoDiv.append('<div class="list-group-item text-danger">'+data.error+'</div>');
                        } else if (data.length === 0) {
                            resultadoDiv.append('<div class="list-group-item">Nenhum produto encontrado</div>');
                        } else {
                            data.forEach(function(produto) {
                                var item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
                                item.html('<strong>' + produto.nome + '</strong><br>' +
                                         '<small>Código: ' + produto.codigo + ' | Preço: R$ ' + 
                                         parseFloat(produto.preco_venda).toFixed(2).replace('.', ',') + '</small>');
                                item.on('click', function(e) {
                                    e.preventDefault();
                                    selecionarProduto(linha, produto);
                                });
                                resultadoDiv.append(item);
                            });
                        }
                        resultadoDiv.show();
                    },
                    error: function(xhr, status, error) {
                        resultadoDiv.html('<div class="list-group-item text-danger">Erro ao buscar produtos</div>').show();
                        console.error('Erro na busca de produtos:', error, xhr.responseText);
                    }
                });
            }, 300);
        });
    }
    
    // Selecionar produto
    function selecionarProduto(linha, produto) {
        linha.find('.busca-produto').val(produto.nome);
        linha.find('.produto-id').val(produto.id);
        linha.find('.valor-unitario').val(parseFloat(produto.preco_venda).toFixed(2).replace('.', ','));
        linha.find('.resultado-busca-produto').empty().hide();
        calcularTotalLinha(linha);
    }
    
    // Configurar cálculos
    function configurarCalculos(linha) {
        linha.find('.quantidade, .valor-unitario, .desconto').on('input change', function() {
            calcularTotalLinha(linha);
        });
    }
    
    // Calcular total da linha
    function calcularTotalLinha(linha) {
        var quantidade = parseFloat(linha.find('.quantidade').val()) || 0; // Use var
        var valorUnitario = parseFloat(linha.find('.valor-unitario').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.')) || 0; // Use var
        var desconto = parseFloat(linha.find('.desconto').val()) || 0; // Use var
        
        var subtotal = quantidade * valorUnitario; // Use var
        var valorDesconto = subtotal * (desconto / 100); // Use var
        var total = subtotal - valorDesconto; // Use var
        
        linha.find('.total-linha').val('R$ ' + total.toFixed(2).replace('.', ','));
        
        calcularTotais();
    }
    
    // Calcular totais gerais
    function calcularTotais() {
        var subtotal = 0; // Use var
        
        $('#produtos_tbody tr').each(function() {
            var totalLinha = parseFloat($(this).find('.total-linha').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.')) || 0; // Use var
            subtotal += totalLinha;
        });
        
        var descontoTotal = parseFloat($('#desconto_total').val().replace('R$ ', '').replace(/\\./g, '').replace(',', '.')) || 0; // Use var
        var totalGeral = subtotal - descontoTotal; // Use var
        
        $('#subtotal').text('R$ ' + subtotal.toFixed(2).replace('.', ','));
        $('#total_geral').text('R$ ' + totalGeral.toFixed(2).replace('.', ','));
    }
    
    // Remover produto
    $(document).on('click', '.remover-produto', function() {
        $(this).closest('tr').remove();
        calcularTotais();
    });
    
    // Calcular ao alterar desconto total
    $('#desconto_total').on('input change', function() {
        calcularTotais();
    });
    
    // Esconder resultados ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.busca-produto, .resultado-busca-produto').length) {
            $('.resultado-busca-produto').empty().hide();
        }
    });
    
    // Validar formulário antes de enviar
    $('#form-orcamento').on('submit', function(e) {
        if (!$('#cliente_id').val()) {
            e.preventDefault();
            alert('Por favor, selecione um cliente.');
            $('#busca_cliente').focus();
            return false;
        }
        
        if ($('#produtos_tbody tr').length === 0) {
            e.preventDefault();
            alert('Por favor, adicione pelo menos um produto ao orçamento.');
            return false;
        }
        
        var produtosValidos = true; // Use var
        $('#produtos_tbody tr').each(function() {
            if (!$(this).find('.produto-id').val()) {
                produtosValidos = false;
            }
        });
        
        if (!produtosValidos) {
            e.preventDefault();
            alert('Por favor, selecione produtos válidos para todas as linhas.');
            return false;
        }
    });
    
    // Aplicar máscara monetária ao desconto total
    $('#desconto_total').inputmask('currency', {
        prefix: 'R$ ',
        groupSeparator: '.',
        radixPoint: ',',
        digits: 2,
        autoGroup: true,
        rightAlign: false
    });
    
    // Adicionar primeira linha automaticamente
    adicionarLinhaProduto();
});
JS;

// Incluir o rodapé
include __DIR__ . '/../includes/footer.php';
?>