<?php
$page_title = "Novo Orçamento";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$clienteModel = new Cliente($db);
$produtoModel = new Produto($db);
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db);

$proximoNumero = null;
$numeroFormatado = 'Gerado ao Salvar';

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
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $termo . '%', '%' . $termo . '%']);
        $clientes_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($clientes_ajax);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
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
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $termo . '%', '%' . $termo . '%']);
        $produtos_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($produtos_ajax);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
        exit;
    }
}

// --- Lógica de submissão do formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("[DEBUG POST] Requisição POST recebida para criar orçamento.");
    try {
        $proximoNumero = null;
        try {
            $proximoNumero = $numeracaoModel->gerarProximoNumero('orcamento');
            error_log("[DEBUG POST] Número do orçamento gerado na submissão: " . $proximoNumero);
        } catch (Exception $e) {
            error_log("[ERRO CRÍTICO NA GERAÇÃO DE NÚMERO NO POST] Falha ao gerar número: " . $e->getMessage());
            throw new Exception("Erro ao gerar número do orçamento. Contate o suporte.");
        }

        $orcamento = new Orcamento($db);
        $orcamento->numero = $proximoNumero;
        $orcamento->cliente_id = $_POST['cliente_id'];

        $data_orcamento_formatada = DateTime::createFromFormat('d/m/Y', $_POST['data_orcamento']);
        $orcamento->data_orcamento = $data_orcamento_formatada ? $data_orcamento_formatada->format('Y-m-d') : null;

        $validade_dias = (int)$_POST['validade_dias'];
        if ($data_orcamento_formatada) {
            $data_validade_dt = clone $data_orcamento_formatada;
            $data_validade_dt->modify("+{$validade_dias} days");
            $orcamento->data_validade = $data_validade_dt->format('Y-m-d');
        } else {
            $orcamento->data_validade = null;
        }

        $data_evento_formatada = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $orcamento->data_evento = $data_evento_formatada ? $data_evento_formatada->format('Y-m-d') : null;

        $data_devolucao_prevista_formatada = !empty($_POST['data_devolucao_prevista']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_devolucao_prevista']) : null;
        $orcamento->data_devolucao_prevista = $data_devolucao_prevista_formatada ? $data_devolucao_prevista_formatada->format('Y-m-d') : null;

        $orcamento->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;
        $orcamento->local_evento = !empty($_POST['local_evento']) ? $_POST['local_evento'] : '';
        $orcamento->hora_devolucao = !empty($_POST['hora_devolucao']) ? $_POST['hora_devolucao'] : null;
        $orcamento->turno_entrega = isset($_POST['turno_entrega']) ? $_POST['turno_entrega'] : 'Manhã/Tarde (Horário Comercial)';
        $orcamento->turno_devolucao = isset($_POST['turno_devolucao']) ? $_POST['turno_devolucao'] : 'Manhã/Tarde (Horário Comercial)';
        $orcamento->tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'locacao';
        $orcamento->status = isset($_POST['status']) ? $_POST['status'] : 'pendente';

        $orcamento->desconto = isset($_POST['desconto_total']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['desconto_total']) : 0;
        $orcamento->taxa_domingo_feriado = isset($_POST['taxa_domingo_feriado']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_domingo_feriado']) : 0;
        $orcamento->taxa_madrugada = isset($_POST['taxa_madrugada']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_madrugada']) : 0;
        $orcamento->taxa_horario_especial = isset($_POST['taxa_horario_especial']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_horario_especial']) : 0;
        $orcamento->taxa_hora_marcada = isset($_POST['taxa_hora_marcada']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['taxa_hora_marcada']) : 0;
        $orcamento->frete_terreo = isset($_POST['frete_terreo']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['frete_terreo']) : 0;
        $orcamento->frete_elevador = isset($_POST['frete_elevador']) ? $_POST['frete_elevador'] : '';
        $orcamento->frete_escadas = isset($_POST['frete_escadas']) ? $_POST['frete_escadas'] : '';

        $orcamento->ajuste_manual = isset($_POST['ajuste_manual']) ? 1 : 0;
        $orcamento->motivo_ajuste = isset($_POST['motivo_ajuste']) ? $_POST['motivo_ajuste'] : '';

        $orcamento->observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';
        $orcamento->condicoes_pagamento = isset($_POST['condicoes_pagamento']) ? $_POST['condicoes_pagamento'] : '';
        $orcamento->usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1; // Defina um padrão ou obtenha do usuário logado

        error_log("[DEBUG POST] Tentando criar o orçamento no banco de dados.");
        $result = $orcamento->create();

        if ($result !== false) {
            $orcamentoId = $result;
            error_log("[DEBUG POST] Orçamento criado com ID: " . $orcamentoId);

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
                            'tipo' => 'locacao', // TODO: Obter tipo do item do formulário se necessário
                            'preco_unitario' => $preco_unitario,
                            'desconto' => $desconto_item,
                            'preco_final' => $preco_final,
                            'ajuste_manual' => false,
                            'motivo_ajuste' => '',
                            'observacoes' => ''
                        ];
                    }
                }
            }

            if (!empty($itens)) {
                error_log("[DEBUG POST] Tentando salvar itens do orçamento.");
                $itensSalvos = $orcamento->salvarItens($orcamentoId, $itens);
                if (!$itensSalvos) {
                    throw new Exception("Erro ao salvar os itens do orçamento.");
                }
                error_log("[DEBUG POST] Itens do orçamento salvos com sucesso.");
            } else {
                error_log("[DEBUG POST] Nenhum item para salvar no orçamento.");
            }

            $orcamento->recalcularValores($orcamentoId);
            error_log("[DEBUG POST] Valores do orçamento recalculados.");

            $_SESSION['success_message'] = "Orçamento #" . htmlspecialchars($orcamento->numero) . " criado com sucesso!";
            header("Location: index.php");
            exit;
        } else {
            error_log("[ERRO POST] Orcamento::create() retornou false. Verifique a classe Orcamento.");
            $_SESSION['error_message'] = "Ocorreu um erro ao salvar o orçamento principal. Verifique os logs.";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    } catch (Exception $e) {
        error_log("[ERRO NO FLUXO] Exceção capturada ao criar orçamento: " . $e->getMessage());
        $_SESSION['error_message'] = "Ocorreu um erro inesperado ao salvar o orçamento: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Novo Orçamento</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form id="form-orcamento" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- Dados do Orçamento (Número, Data, Validade) -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="numero_orcamento" class="form-label">Número do Orçamento</label>
                                <input type="text" class="form-control" id="numero_orcamento" name="numero"
                                       value="<?php echo htmlspecialchars($numeroFormatado); ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="data_orcamento" class="form-label">Data do Orçamento</label>
                                <input type="text" class="form-control datepicker" id="data_orcamento" name="data_orcamento"
                                       value="<?php echo date('d/m/Y'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="validade_dias" class="form-label">Validade (dias)</label>
                                <select class="form-control" id="validade_dias" name="validade_dias" required>
                                    <option value="7">7 dias</option>
                                    <option value="15">15 dias</option>
                                    <option value="30" selected>30 dias</option>
                                    <option value="60">60 dias</option>
                                    <option value="90">90 dias</option>
                                    <option value="180">180 dias</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="data_validade_final_display" class="form-label">Data de Validade Final</label>
                                <input type="text" class="form-control" id="data_validade_final_display" readonly>
                                <input type="hidden" name="data_validade" id="data_validade_hidden">
                            </div>
                        </div>

                        <!-- Tipo e Status do Orçamento -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="tipo" class="form-label">Tipo de Orçamento</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="locacao" selected>Locação</option>
                                    <option value="venda">Venda</option>
                                    <option value="misto">Misto</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="pendente" selected>Pendente</option>
                                    <option value="aprovado">Aprovado</option>
                                    <option value="recusado">Recusado</option>
                                    <option value="expirado">Expirado</option>
                                    <option value="finalizado">Finalizado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                        </div>

                        <!-- Dados do Cliente -->
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
                                <input type="text" class="form-control datepicker" id="data_evento" name="data_evento">
                                <small id="dia_semana_evento" class="form-text text-muted" style="font-size: larger;"></small>
                            </div>
                            <div class="col-md-3">
                                <label for="turno_entrega" class="form-label">Turno/Horário Entrega</label>
                                <select class="form-control" id="turno_entrega" name="turno_entrega">
                                    <option value="Manhã/Tarde (Horário Comercial)" selected>Manhã/Tarde (Horário Comercial)</option>
                                    <option value="Manhã (Horário Comercial)">Manhã (Horário Comercial)</option>
                                    <option value="Tarde (Horário Comercial)">Tarde (Horário Comercial)</option>
                                    <option value="Horário Específico">Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="hora_evento" class="form-label">Hora do Evento (se preferência)</label>
                                <input type="time" class="form-control" id="hora_evento" name="hora_evento">
                            </div>
                            <div class="col-md-3">
                                <label for="local_evento" class="form-label">Local do Evento/Entrega</label>
                                <input type="text" class="form-control" id="local_evento" name="local_evento" placeholder="Ex.: GNU - FOYER">
                                <button type="button" id="usar_endereco_cliente" class="btn btn-sm btn-info mt-2" style="display: none;">
                                    <i class="fas fa-map-marker-alt"></i> Usar Endereço do Cliente
                                </button>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="data_devolucao_prevista" class="form-label">Data da Coleta/Devolução</label>
                                <input type="text" class="form-control datepicker" id="data_devolucao_prevista" name="data_devolucao_prevista">
                                <small id="dia_semana_devolucao" class="form-text text-muted" style="font-size: larger;"></small>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="turno_devolucao" class="form-label">Turno/Horário Devolução</label>
                                <select class="form-control" id="turno_devolucao" name="turno_devolucao">
                                    <option value="Manhã/Tarde (Horário Comercial)" selected>Manhã/Tarde (Horário Comercial)</option>
                                    <option value="Manhã (Horário Comercial)">Manhã (Horário Comercial)</option>
                                    <option value="Tarde (Horário Comercial)">Tarde (Horário Comercial)</option>
                                    <option value="Horário Específico">Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="hora_devolucao" class="form-label">Hora da Devolução (se preferência)</label>
                                <input type="time" class="form-control" id="hora_devolucao" name="hora_devolucao">
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
                                            <!-- Linhas de produto serão adicionadas via JS -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Subtotal Locação:</strong></td>
                                                <td><strong id="subtotal_locacao_display">R$ 0,00</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Subtotal Venda:</strong></td>
                                                <td><strong id="subtotal_venda_display">R$ 0,00</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Valor Final:</strong></td>
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

                        <!-- Valores e Taxas -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Valores e Taxas</h4>
                            </div>
                            <div class="col-md-3">
                                <label for="desconto_total" class="form-label">Desconto Total (R$)</label>
                                <input type="text" class="form-control money" id="desconto_total" name="desconto_total" value="0,00">
                            </div>
                            <div class="col-md-3">
                                <label for="taxa_domingo_feriado" class="form-label">Taxa Domingo/Feriado (R$)</label>
                                <input type="text" class="form-control money" id="taxa_domingo_feriado" name="taxa_domingo_feriado" value="0,00">
                            </div>
                            <div class="col-md-3">
                                <label for="taxa_madrugada" class="form-label">Taxa Madrugada (R$)</label>
                                <input type="text" class="form-control money" id="taxa_madrugada" name="taxa_madrugada" value="0,00">
                            </div>
                            <div class="col-md-3">
                                <label for="taxa_horario_especial" class="form-label">Taxa Horário Especial (R$)</label>
                                <input type="text" class="form-control money" id="taxa_horario_especial" name="taxa_horario_especial" value="0,00">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="taxa_hora_marcada" class="form-label">Taxa Hora Marcada (R$)</label>
                                <input type="text" class="form-control money" id="taxa_hora_marcada" name="taxa_hora_marcada" value="0,00">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="frete_terreo" class="form-label">Frete Térreo (R$)</label>
                                <input type="text" class="form-control money" id="frete_terreo" name="frete_terreo" value="0,00">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="frete_elevador" class="form-label">Frete Elevador (Texto)</label>
                                <input type="text" class="form-control" id="frete_elevador" name="frete_elevador" value="">
                            </div>
                            <div class="col-md-3 mt-3">
                                <label for="frete_escadas" class="form-label">Frete Escadas (Texto)</label>
                                <input type="text" class="form-control" id="frete_escadas" name="frete_escadas" value="">
                            </div>
                        </div>

                        <!-- Ajuste Manual de Valores -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Ajuste Manual</h4>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-2">
                                    <input type="checkbox" id="ajuste_manual" name="ajuste_manual" class="form-check-input">
                                    <label for="ajuste_manual" class="form-check-label">Ajuste Manual de Valores</label>
                                </div>
                                <div class="form-group mt-2">
                                    <label for="motivo_ajuste">Motivo do Ajuste:</label>
                                    <input type="text" id="motivo_ajuste" name="motivo_ajuste" class="form-control" value="">
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

                        <!-- Condições de Pagamento -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="condicoes_pagamento" class="form-label">Condições de Pagamento</label>
                                <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento" rows="3"></textarea>
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
</div> <!-- Fecha o content-wrapper -->

<!-- SCRIPTS ESPECÍFICOS DA PÁGINA - Colocados ANTES do footer.php -->
<!-- Use as mesmas versões CDN que você tem no seu footer.php ou edit.php para consistência -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

<script>
$(document).ready(function() {
    let searchTimeout;
    let clienteSelecionado = null;

    // Inicializar datepicker com onSelect para dia da semana
    $('.datepicker').datepicker({
        dateFormat: 'dd/mm/yy',
        changeMonth: true,
        changeYear: true,
        yearRange: '2023:2035', // Ajuste o range conforme necessidade
        showOn: 'button',
        buttonText: 'Selecionar Data',
        onSelect: function(dateText, inst) {
            if ($(this).attr('id') === 'data_evento') {
                atualizarDiaSemana(dateText, '#dia_semana_evento');
            } else if ($(this).attr('id') === 'data_devolucao_prevista') {
                atualizarDiaSemana(dateText, '#dia_semana_devolucao');
            } else if ($(this).attr('id') === 'data_orcamento') {
                if (typeof calcularDataValidadeFinal === 'function') {
                    calcularDataValidadeFinal();
                }
            }
            $(this).trigger('input'); // Útil para inputmask ou outros listeners
        }
    });

    // Inicializar inputmask para campos 'money' DENTRO DESTE FORMULÁRIO ESPECÍFICO
    // O footer.php pode ter uma inicialização global, mas esta garante para os campos desta página.
    $('#form-orcamento .money').inputmask('currency', {
        prefix: 'R$ ',
        groupSeparator: '.',
        radixPoint: ',',
        digits: 2,
        autoGroup: true,
        rightAlign: false,
        allowMinus: true
    });

    // --- Lógica de Dia da Semana ---
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
    // Chama a função no carregamento para caso as datas já tenham valor (pouco provável no create, mas para consistência)
    atualizarDiaSemana($('#data_evento').val(), '#dia_semana_evento');
    atualizarDiaSemana($('#data_devolucao_prevista').val(), '#dia_semana_devolucao');

    // --- Lógica de Validade ---
    function calcularDataValidadeFinal() {
        var dataOrcamentoStr = $('#data_orcamento').val();
        var validadeDias = parseInt($('#validade_dias').val());

        if (dataOrcamentoStr && !isNaN(validadeDias)) {
            var partesData = dataOrcamentoStr.split('/');
            var dataOrcamento = new Date(partesData[2] + '-' + partesData[1] + '-' + partesData[0] + 'T00:00:00');
            dataOrcamento.setDate(dataOrcamento.getDate() + validadeDias);

            var dia = String(dataOrcamento.getDate()).padStart(2, '0');
            var mes = String(dataOrcamento.getMonth() + 1).padStart(2, '0');
            var ano = dataOrcamento.getFullYear();

            $('#data_validade_final_display').val(dia + '/' + mes + '/' + ano);
            $('#data_validade_hidden').val(ano + '-' + mes + '-' + dia); // Formato YYYY-MM-DD
        } else {
            $('#data_validade_final_display').val('');
            $('#data_validade_hidden').val('');
        }
    }
    $('#data_orcamento, #validade_dias').on('change keyup blur', calcularDataValidadeFinal); // Adicionado keyup e blur para cobrir mais casos
    calcularDataValidadeFinal(); // Chamada inicial

    // --- Lógica de Cliente ---
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
                url: window.location.pathname, // Usa o URL atual para a requisição AJAX
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
                        $('<div class="list-group-item text-danger">').text('Erro na busca de clientes: ' + error)
                    );
                     resultadoDiv.show();
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

    // --- Lógica de Produtos ---
    $('#btn_adicionar_produto').on('click', function() {
        adicionarLinhaProduto();
    });

    function adicionarLinhaProduto() {
        var linha = $('<tr class="item-row">'); // Adicionada class item-row para consistência
        linha.html(`
            <td>
                <input type="text" class="form-control busca-produto" placeholder="Digite para buscar produto">
                <div class="resultado-busca-produto list-group mt-1" style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto; width: 95%;"></div>
                <input type="hidden" class="produto-id" name="produto_id[]">
            </td>
            <td><input type="number" class="form-control quantidade" name="quantidade[]" value="1" min="1" step="0.01"></td>
            <td><input type="text" class="form-control money valor-unitario" name="valor_unitario[]" value="0,00"></td>
            <td><input type="text" class="form-control money desconto" name="desconto_item[]" value="0,00" min="0"></td>
            <td><input type="text" class="form-control total-linha money" readonly value="R$ 0,00"></td>
            <td><button type="button" class="btn btn-danger btn-sm remover-produto"><i class="fas fa-trash"></i></button></td>
        `);
        $('#produtos_tbody').append(linha);
        linha.find('.money').inputmask('currency', { prefix: 'R$ ', groupSeparator: '.', radixPoint: ',', digits: 2, autoGroup: true, rightAlign: false, allowMinus: true });
        configurarBuscaProduto(linha);
        configurarCalculos(linha);
        // calcularTotais(); // Não precisa chamar aqui, pois os valores são zero inicialmente
    }

    function configurarBuscaProduto(linha) {
        let searchTimeoutProduto; // Renomeado para evitar conflito com o searchTimeout do cliente
        var inputBusca = linha.find('.busca-produto');
        var resultadoDiv = linha.find('.resultado-busca-produto');

        inputBusca.autocomplete({
            source: function(request, response) {
                clearTimeout(searchTimeoutProduto);
                var termo = request.term.trim();
                if (termo.length < 2) {
                    response([]);
                    return;
                }
                searchTimeoutProduto = setTimeout(function() {
                    $.ajax({
                        url: window.location.pathname,
                        type: 'GET',
                        data: { ajax: 'buscar_produtos', termo: termo },
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
                event.preventDefault(); // Prevenir que o valor do label vá para o input
                linha.find('.busca-produto').val(ui.item.value); // Coloca o nome do produto no input
                linha.find('.produto-id').val(ui.item.id);
                linha.find('.valor-unitario').val(parseFloat(ui.item.preco_venda).toFixed(2).replace('.', ',')).trigger('input');
            },
            close: function() {
                resultadoDiv.empty().hide();
            }
        }).focus(function() {
            $(this).autocomplete('search', $(this).val());
        });

        $(document).on('click', function(e) { // Esconder resultados ao clicar fora
            if (!$(e.target).closest(inputBusca).length && !$(e.target).closest(resultadoDiv).length) {
                resultadoDiv.empty().hide();
            }
        });
    }

    function configurarCalculos(linha) {
        linha.find('.quantidade, .valor-unitario, .desconto').on('input change', function() {
            calcularTotalLinha(linha);
        });
    }

    function calcularTotalLinha(linha) {
        var quantidade = parseFloat(linha.find('.quantidade').val().replace(',', '.')) || 0; // Tratar vírgula se o input number permitir
        var valorUnitarioStr = linha.find('.valor-unitario').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var valorUnitario = parseFloat(valorUnitarioStr) || 0;
        var descontoStr = linha.find('.desconto').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var desconto = parseFloat(descontoStr) || 0;

        var subtotal = quantidade * valorUnitario;
        var total = subtotal - desconto;
        linha.find('.total-linha').val(total.toFixed(2).replace('.', ',')).trigger('input'); // Disparar input para inputmask
        calcularTotais();
    }

    function calcularTotais() {
        var subtotalLocacao = 0;
        var subtotalVenda = 0; // Implementar lógica se houver tipo de item

        $('#produtos_tbody tr.item-row').each(function() { // Usar .item-row para ser mais específico
            var totalLinhaStr = $(this).find('.total-linha').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
            var totalLinha = parseFloat(totalLinhaStr) || 0;
            subtotalLocacao += totalLinha; // Assumindo tudo locação por enquanto
        });

        $('#subtotal_locacao_display').text('R$ ' + subtotalLocacao.toFixed(2).replace('.', ','));
        $('#subtotal_venda_display').text('R$ ' + subtotalVenda.toFixed(2).replace('.', ','));

        var descontoTotalStr = $('#desconto_total').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var descontoTotal = parseFloat(descontoTotalStr) || 0;
        var taxaDomingoFeriadoStr = $('#taxa_domingo_feriado').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var taxaDomingoFeriado = parseFloat(taxaDomingoFeriadoStr) || 0;
        var taxaMadrugadaStr = $('#taxa_madrugada').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var taxaMadrugada = parseFloat(taxaMadrugadaStr) || 0;
        var taxaHorarioEspecialStr = $('#taxa_horario_especial').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var taxaHorarioEspecial = parseFloat(taxaHorarioEspecialStr) || 0;
        var taxaHoraMarcadaStr = $('#taxa_hora_marcada').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var taxaHoraMarcada = parseFloat(taxaHoraMarcadaStr) || 0;
        var freteTerreoStr = $('#frete_terreo').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.');
        var freteTerreo = parseFloat(freteTerreoStr) || 0;

        var totalTaxas = taxaDomingoFeriado + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada;
        var totalFrete = freteTerreo;
        var valorFinal = (subtotalLocacao + subtotalVenda + totalTaxas + totalFrete) - descontoTotal;
        $('#total_geral').text('R$ ' + valorFinal.toFixed(2).replace('.', ','));
    }

    $(document).on('click', '.remover-produto', function() {
        $(this).closest('tr.item-row').remove();
        calcularTotais();
    });

    $('#desconto_total, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo')
        .on('input change', calcularTotais);

    // Validação de cliente antes de submeter
    $('#form-orcamento').on('submit', function(e) {
        if (!$('#cliente_id').val()) {
            e.preventDefault();
            alert('Por favor, selecione um cliente.');
            $('#busca_cliente').focus();
            return false;
        }
        // Remover máscaras antes de submeter para garantir que os valores numéricos sejam enviados corretamente
        // $('.money').inputmask('remove'); // Cuidado: isso pode ser problemático se houver re-validação
    });

    // Adiciona uma linha de produto inicial
    adicionarLinhaProduto();
    // Calcula totais uma vez no início (já que uma linha é adicionada)
    calcularTotais();

});
</script>

<?php
// O $custom_js não será usado aqui, pois o script foi colocado diretamente acima.
// Se você tiver outras lógicas JS que vêm de $extra_js no footer, elas ainda funcionarão.
include __DIR__ . '/../includes/footer.php';
?>