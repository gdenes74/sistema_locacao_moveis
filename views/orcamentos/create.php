<?php
$page_title = "Novo Orçamento";
// Define $extra_css e $custom_js aqui se necessário para este arquivo específico
// Ex: $extra_css = [BASE_URL . '/assets/css/orcamentos_create.css'];

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php'; // Certifique-se que existe e está correto
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$clienteModel = new Cliente($db);
// $produtoModel = new Produto($db); // Instanciar se for usar métodos do modelo Produto aqui
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db);

$numeroFormatado = 'Gerado ao Salvar';

// Textos padrão para observações e condições
$textoPadraoObservacoes = "# Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.\n* Não Inclui Posicionamento dos Móveis no Local.";
$textoPadraoCondicoes = "50% na aprovação para reserva em PIX ou Depósito.\nSaldo em PIX ou Depósito 7 dias antes do evento.\n* Consulte disponibilidade e preços para pagamento no cartão de crédito.";

// Valores padrão para taxas (para exibição inicial no formulário)
$valorPadraoTaxaDomingo = 250.00;
$valorPadraoTaxaMadrugada = 800.00;
$valorPadraoTaxaHorarioEspecial = 150.00;
$valorPadraoTaxaHoraMarcada = 100.00;
$valorPadraoFreteTerreo = 0.00;


// --- Bloco AJAX para buscar clientes ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_clientes') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        if (empty($termo)) {
            echo json_encode([]);
            exit;
        }
        $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes 
                FROM clientes 
                WHERE nome LIKE :termo_nome 
                   OR REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :termo_cpf_cnpj 
                   OR email LIKE :termo_email";
        $stmt = $db->prepare($sql);
        $likeTerm = "%" . $termo . "%";
        $likeTermCpfCnpj = "%" . preg_replace('/[^0-9]/', '', $termo) . "%";

        $stmt->bindParam(':termo_nome', $likeTerm, PDO::PARAM_STR);
        $stmt->bindParam(':termo_cpf_cnpj', $likeTermCpfCnpj, PDO::PARAM_STR);
        $stmt->bindParam(':termo_email', $likeTerm, PDO::PARAM_STR);

        $stmt->execute();
        $clientes_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($clientes_ajax);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Erro AJAX buscar_clientes: " . $e->getMessage());
        echo json_encode(['error' => 'Erro no banco de dados ao buscar clientes.']);
        exit;
    }
}

// --- Bloco AJAX para buscar produtos (COM filtro de categoria e foto_path) ---
// --- Bloco AJAX para buscar produtos (COM filtro de categoria e foto_path COMPLETO) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_produtos') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        $categoria_principal_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;

        if (empty($termo) && $categoria_principal_id === 0) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT p.id, p.codigo, p.nome_produto, p.descricao_detalhada, p.preco_locacao, p.quantidade_total, p.foto_path
                FROM produtos p";
        
        $conditions = [];
        $executeParams = [];

        if (!empty($termo)) {
            $conditions[] = "(p.nome_produto LIKE ? OR p.codigo LIKE ?)";
            $executeParams[] = "%" . $termo . "%";
            $executeParams[] = "%" . $termo . "%";
        }

        if ($categoria_principal_id > 0) {
            $sqlSubcategorias = "SELECT id FROM subcategorias WHERE categoria_id = ?";
            $stmtSub = $db->prepare($sqlSubcategorias);
            $stmtSub->execute([$categoria_principal_id]);
            $subcategoriasIds = $stmtSub->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($subcategoriasIds)) {
                $placeholders = implode(',', array_fill(0, count($subcategoriasIds), '?'));
                $conditions[] = "p.subcategoria_id IN ({$placeholders})"; 
                $executeParams = array_merge($executeParams, $subcategoriasIds);
            } else {
                echo json_encode([]);
                exit;
            }
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        } else {
            echo json_encode([]);
            exit;
        }
        
        $sql .= " ORDER BY p.nome_produto LIMIT 15";

        $stmt = $db->prepare($sql);
        $stmt->execute($executeParams);
        $produtos_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // MODIFICAÇÃO CRUCIAL AQUI: Adicionar BASE_URL ao foto_path
        $base_url_config = rtrim(BASE_URL, '/'); // Pega BASE_URL da config.php

        foreach ($produtos_ajax as &$produto_item) { 
            if (!empty($produto_item['foto_path']) && $produto_item['foto_path'] !== "null" && trim($produto_item['foto_path']) !== "") {
                $foto_path_limpo = ltrim($produto_item['foto_path'], '/'); 
                
                // Verifica se a função build_url existe (como no seu produtos/index.php)
                if (function_exists('build_url')) {
                    $produto_item['foto_path_completo'] = build_url($foto_path_limpo);
                } else {
                    $produto_item['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;
                }
            } else {
                $produto_item['foto_path_completo'] = null; 
            }
        }
        unset($produto_item); 
        
        echo json_encode($produtos_ajax);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        $errorQueryDebug = isset($sql) ? $sql : 'SQL não disponível na captura da exceção.';
        $errorParamsDebug = isset($executeParams) ? json_encode($executeParams) : 'Parâmetros não disponíveis.';
        error_log("Erro AJAX buscar_produtos: " . $e->getMessage() . " | Query: " . $errorQueryDebug . " | Params: " . $errorParamsDebug);
        echo json_encode(['error' => 'Ocorreu um erro interno ao buscar produtos.']);
        exit;
    }
}
// --- FIM DO BLOCO AJAX ---
// --- Lógica de submissão do formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // error_log("[DEBUG CREATE ORCAMENTO POST DATA]: " . print_r($_POST, true));
    try {
        $proximoNumeroGerado = $numeracaoModel->gerarProximoNumero('orcamento');
        if ($proximoNumeroGerado === false || $proximoNumeroGerado === null) {
            throw new Exception("Falha crítica ao gerar o número sequencial do orçamento.");
        }
        $orcamentoModel->numero = $proximoNumeroGerado;

        if (empty($_POST['cliente_id'])) {
            throw new Exception("Cliente é obrigatório.");
        }
        $orcamentoModel->cliente_id = $_POST['cliente_id'];

        $data_orcamento_input = $_POST['data_orcamento'] ?? date('d/m/Y');
        $data_orcamento_dt = DateTime::createFromFormat('d/m/Y', $data_orcamento_input);
        if (!$data_orcamento_dt) {
            $data_orcamento_dt = DateTime::createFromFormat('Y-m-d', $data_orcamento_input) ?: new DateTime();
        }
        $orcamentoModel->data_orcamento = $data_orcamento_dt->format('Y-m-d');

        if (isset($_POST['data_validade_calculada_hidden']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data_validade_calculada_hidden'])) {
            $orcamentoModel->data_validade = $_POST['data_validade_calculada_hidden'];
        } else {
            $validade_dias = isset($_POST['validade_dias']) ? (int) $_POST['validade_dias'] : 7;
            $data_validade_dt_calc = clone $data_orcamento_dt;
            $data_validade_dt_calc->modify("+{$validade_dias} days");
            $orcamentoModel->data_validade = $data_validade_dt_calc->format('Y-m-d');
        }

        $data_evento_dt = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $orcamentoModel->data_evento = $data_evento_dt ? $data_evento_dt->format('Y-m-d') : null;
        $orcamentoModel->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;

        $data_entrega_dt = !empty($_POST['data_entrega']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_entrega']) : null;
        $orcamentoModel->data_entrega = $data_entrega_dt ? $data_entrega_dt->format('Y-m-d') : null;
        $orcamentoModel->hora_entrega = !empty($_POST['hora_entrega']) ? $_POST['hora_entrega'] : null;

        $orcamentoModel->local_evento = !empty($_POST['local_evento']) ? trim($_POST['local_evento']) : '';

        $data_devolucao_dt = !empty($_POST['data_devolucao_prevista']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_devolucao_prevista']) : null;
        $orcamentoModel->data_devolucao_prevista = $data_devolucao_dt ? $data_devolucao_dt->format('Y-m-d') : null;
        $orcamentoModel->hora_devolucao = !empty($_POST['hora_devolucao']) ? $_POST['hora_devolucao'] : null;

        $orcamentoModel->turno_entrega = $_POST['turno_entrega'] ?? 'Manhã/Tarde (Horário Comercial)';
        $orcamentoModel->turno_devolucao = $_POST['turno_devolucao'] ?? 'Manhã/Tarde (Horário Comercial)';
        $orcamentoModel->tipo = $_POST['tipo'] ?? 'locacao';
        $orcamentoModel->status = $_POST['status_orcamento'] ?? 'pendente';

        $fnConverterMoeda = function ($valorStr) {
            if (empty($valorStr))
                return 0.0;
            $valor = str_replace('R$', '', $valorStr);
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return (float) $valor;
        };

        $orcamentoModel->desconto = $fnConverterMoeda($_POST['desconto_total'] ?? '0,00');
        $orcamentoModel->taxa_domingo_feriado = $fnConverterMoeda($_POST['taxa_domingo_feriado'] ?? '0,00');
        $orcamentoModel->taxa_madrugada = $fnConverterMoeda($_POST['taxa_madrugada'] ?? '0,00');
        $orcamentoModel->taxa_horario_especial = $fnConverterMoeda($_POST['taxa_horario_especial'] ?? '0,00');
        $orcamentoModel->taxa_hora_marcada = $fnConverterMoeda($_POST['taxa_hora_marcada'] ?? '0,00');
        $orcamentoModel->frete_terreo = $fnConverterMoeda($_POST['frete_terreo'] ?? '0,00');
        $orcamentoModel->frete_elevador = $_POST['frete_elevador'] ?? '';
        $orcamentoModel->frete_escadas = $_POST['frete_escadas'] ?? '';

        $orcamentoModel->ajuste_manual = isset($_POST['ajuste_manual']) ? 1 : 0;
        $orcamentoModel->motivo_ajuste = $_POST['motivo_ajuste'] ?? '';
        $orcamentoModel->observacoes = $_POST['observacoes'] ?? '';
        $orcamentoModel->condicoes_pagamento = $_POST['condicoes_pagamento'] ?? '';
        $orcamentoModel->usuario_id = $_SESSION['usuario_id'] ?? 1;

        $orcamentoIdSalvo = $orcamentoModel->create();

        if ($orcamentoIdSalvo !== false && $orcamentoIdSalvo > 0) {
            $itens = [];
            if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
                foreach ($_POST['produto_id'] as $index => $produto_id) {
                    if (!empty($produto_id) || !empty(trim($_POST['nome_produto_display'][$index]))) {
                        $quantidade = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 1;
                        if ($quantidade <= 0)
                            $quantidade = 1;

                        $preco_unitario = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                        $desconto_item = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');
                        $preco_final = ($quantidade * $preco_unitario) - $desconto_item;

                        $itens[] = [
                            'produto_id' => !empty($produto_id) ? $produto_id : null,
                            'nome_produto_manual' => empty($produto_id) ? trim($_POST['nome_produto_display'][$index]) : null,
                            'quantidade' => $quantidade,
                            'tipo' => $_POST['tipo_item'][$index] ?? 'locacao',
                            'preco_unitario' => $preco_unitario,
                            'desconto' => $desconto_item,
                            'preco_final' => $preco_final,
                            'ajuste_manual' => false,
                            'motivo_ajuste' => '',
                            'observacoes' => $_POST['observacoes_item'][$index] ?? ''
                        ];
                    }
                }
            }

            if (!empty($itens)) {
                if (!$orcamentoModel->salvarItens($orcamentoIdSalvo, $itens)) {
                    $_SESSION['warning_message'] = "Orçamento principal salvo (Nº {$orcamentoModel->numero}), mas houve um erro ao salvar os itens. Verifique e edite o orçamento se necessário.";
                    error_log("[ERRO CREATE ORCAMENTO] Falha ao salvar itens para o orçamento ID: " . $orcamentoIdSalvo);
                }
            }

            $orcamentoModel->id = $orcamentoIdSalvo;
            $orcamentoModel->recalcularValores($orcamentoIdSalvo);

            $_SESSION['success_message'] = "Orçamento #" . htmlspecialchars($orcamentoModel->numero) . " (Código: " . htmlspecialchars($orcamentoModel->codigo) . ") criado com sucesso!";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Ocorreu um erro ao criar o orçamento principal. Verifique os logs.";
            $dbError = $db->errorInfo();
            error_log("[ERRO CREATE ORCAMENTO] OrcamentoModel::create() retornou falso ou ID inválido. Erro PDO: " . ($dbError[2] ?? 'Não disponível'));
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Ocorreu um erro inesperado: " . $e->getMessage();
        error_log("[EXCEÇÃO CREATE ORCAMENTO] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/views/dashboard/index.php">Início</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Orçamentos</a></li>
                        <li class="breadcrumb-item active">Novo</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['warning_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['warning_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['warning_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form id="formNovoOrcamento" action="create.php" method="POST" novalidate>
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Dados do Orçamento</h3>
                        <div class="card-tools">
                            <span class="badge badge-info">Nº Orçamento:
                                <?= htmlspecialchars($numeroFormatado) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-7">
                                <label for="cliente_id" class="form-label">Cliente <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control select2" id="cliente_id" name="cliente_id" required>
                                        <option value="">Selecione ou Busque um Cliente</option>
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" data-toggle="modal"
                                            data-target="#modalNovoCliente" title="Novo Cliente"><i
                                                class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                                <div id="cliente_info_selecionado" class="mt-2 text-muted small"></div>
                            </div>
                            <div class="col-md-3">
                                <label for="data_orcamento" class="form-label">Data Orçam. <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_orcamento"
                                        name="data_orcamento" value="<?= date('d/m/Y') ?>" required>
                                    <div class="input-group-append"><span class="input-group-text"><i
                                                class="fas fa-calendar-alt"></i></span></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="validade_dias" class="form-label">Validade (dias) <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="validade_dias" name="validade_dias"
                                    value="7" min="1" required>
                                <input type="hidden" id="data_validade_calculada_hidden"
                                    name="data_validade_calculada_hidden">
                                <small id="data_validade_display" class="form-text text-muted"></small>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-12">
                                <h5><i class="fas fa-calendar-check mr-2"></i>Detalhes do Evento e Logística</h5>
                            </div>
                            <div class="col-md-3">
                                <label for="data_evento" class="form-label">Data do Evento</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_evento"
                                        name="data_evento" placeholder="DD/MM/AAAA">
                                    <div class="input-group-append"><span class="input-group-text"><i
                                                class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_evento" class="form-text text-muted"></small>
                            </div>
                            <div class="col-md-2"><label for="hora_evento" class="form-label">Hora do
                                    Evento</label><input type="time" class="form-control" id="hora_evento"
                                    name="hora_evento"></div>
                            <div class="col-md-7">
                                <label for="local_evento" class="form-label">Local do Evento/Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="local_evento" name="local_evento"
                                        placeholder="Ex: Salão de Festas Condomínio XYZ">
                                    <div class="input-group-append"><button type="button" id="btnUsarEnderecoCliente"
                                            class="btn btn-sm btn-outline-info"
                                            title="Usar endereço do cliente selecionado" style="display: none;"><i
                                                class="fas fa-map-marker-alt"></i> Usar End. Cliente</button></div>
                                </div>
                            </div>
                            <div class="col-md-3 mt-md-3">
                                <label for="data_entrega" class="form-label">Data da Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_entrega"
                                        name="data_entrega" placeholder="DD/MM/AAAA">
                                    <div class="input-group-append"><span class="input-group-text"><i
                                                class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_entrega" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2 mt-md-3"><label for="hora_entrega" class="form-label">Hora da
                                    Entrega</label><input type="time" class="form-control" id="hora_entrega"
                                    name="hora_entrega"></div>
                            <div class="col-md-3 mt-md-3">
                                <label for="turno_entrega" class="form-label">Turno Entrega</label>
                                <select class="form-control" id="turno_entrega" name="turno_entrega">
                                    <option value="Manhã/Tarde (Horário Comercial)" selected>Manhã/Tarde (HC)</option>
                                    <option value="Manhã (Horário Comercial)">Manhã (HC)</option>
                                    <option value="Tarde (Horário Comercial)">Tarde (HC)</option>
                                    <option value="Noite (A Combinar)">Noite (A Combinar)</option>
                                    <option value="Horário Específico">Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-4 mt-md-3">
                                <label for="status_orcamento" class="form-label">Status do Orçamento</label>
                                <select class="form-control" id="status_orcamento" name="status_orcamento">
                                    <option value="pendente" selected>Pendente</option>
                                    <option value="aprovado">Aprovado</option>
                                    <option value="reprovado">Reprovado</option>
                                    <option value="cancelado">Cancelado</option>
                                    <option value="expirado">Expirado</option>
                                    <option value="finalizado">Finalizado (Evento Concluído)</option>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-12">
                                <h5><i class="fas fa-undo-alt mr-2"></i>Detalhes da Devolução/Coleta</h5>
                            </div>
                            <div class="col-md-3">
                                <label for="data_devolucao_prevista" class="form-label">Data Devolução (Prev.)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_devolucao_prevista"
                                        name="data_devolucao_prevista" placeholder="DD/MM/AAAA">
                                    <div class="input-group-append"><span class="input-group-text"><i
                                                class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_devolucao" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2"><label for="hora_devolucao" class="form-label">Hora
                                    Devolução</label><input type="time" class="form-control" id="hora_devolucao"
                                    name="hora_devolucao"></div>
                            <div class="col-md-3">
                                <label for="turno_devolucao" class="form-label">Turno Devolução</label>
                                <select class="form-control" id="turno_devolucao" name="turno_devolucao">
                                    <option value="Manhã/Tarde (Horário Comercial)" selected>Manhã/Tarde (HC)</option>
                                    <option value="Manhã (Horário Comercial)">Manhã (HC)</option>
                                    <option value="Tarde (Horário Comercial)">Tarde (HC)</option>
                                    <option value="Noite (A Combinar)">Noite (A Combinar)</option>
                                    <option value="Horário Específico">Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tipo" class="form-label">Tipo Orçamento</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="locacao" selected>Locação</option>
                                    <option value="venda">Venda</option>
                                    <option value="misto">Misto (Locação e Venda)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Itens do Orçamento -->
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list-ul mr-2"></i>Itens do Orçamento</h3>
                    </div>
                    <div class="card-body">
                        <!-- ========== INÍCIO DO NOVO HTML PARA ITENS DO ORÇAMENTO ========== -->
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label for="busca_categoria_produto">Filtrar por Categoria:</label>
                                <select id="busca_categoria_produto" class="form-control form-control-sm">
                                    <option value="">Todas as Categorias</option>
                                    <?php
                                    // Este PHP buscará as categorias principais para o dropdown
                                    try {
                                        // Assumindo que $db é sua conexão PDO já instanciada
                                        $stmt_main_categorias = $db->query("SELECT id, nome FROM categorias ORDER BY nome");
                                        if ($stmt_main_categorias) {
                                            $main_categorias_list = $stmt_main_categorias->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($main_categorias_list as $main_cat_item) {
                                                echo '<option value="' . htmlspecialchars($main_cat_item['id']) . '">' . htmlspecialchars($main_cat_item['nome']) . '</option>';
                                            }
                                        } else {
                                            echo '<option value="">Erro ao carregar categorias</option>';
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Erro ao buscar categorias principais para filtro: " . $e->getMessage());
                                        echo '<option value="">Erro DB ao carregar categorias</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label for="busca_produto" class="form-label">Buscar Produto por Nome ou Código:</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="busca_produto"
                                        placeholder="Digite para buscar...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="btnLimparBuscaProduto" title="Limpar busca"><i
                                                class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <div id="sugestoes_produtos" class="list-group mt-1"
                                    style="position: absolute; z-index: 1000; width: calc(100% - 40px); max-height: 260px; overflow-y: auto; display:none; border: 1px solid #ced4da; background-color: white;">
                                    <!-- Sugestões de produtos aparecerão aqui -->
                                </div>
                            </div>
                        </div>

                        <!-- Modal de foto ampliada -->
                        <div class="modal fade" id="modalFotoProduto" tabindex="-1"
                            aria-labelledby="modalFotoProdutoLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content bg-light shadow-lg">
                                    <div class="modal-header py-2">
                                        <h5 class="modal-title" id="modalFotoProdutoLabelText">Visualizar Imagem</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body text-center p-0">
                                        <img id="fotoProdutoAmpliada" src="" alt="Foto do produto" class="img-fluid"
                                            style="max-height:80vh; object-fit: contain;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-hover" id="tabela_itens_orcamento">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 35%;">Produto/Serviço <span class="text-danger">*</span></th>
                                        <th style="width: 10%;">Qtd. <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Vlr. Unit. (R$)</th>
                                        <th style="width: 15%;">Desc. Item (R$)</th>
                                        <th style="width: 15%;">Subtotal (R$)</th>
                                        <th style="width: 10%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Linhas de itens serão adicionadas aqui via JavaScript -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-right"><strong>Subtotal dos Itens:</strong></td>
                                        <td id="subtotal_geral_itens" class="text-right font-weight-bold">R$ 0,00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-success btn-sm mt-2" id="btn_adicionar_item_manual">
                            <i class="fas fa-plus"></i> Adicionar Item Manualmente
                        </button>
                        <!-- ========== FIM DO NOVO HTML PARA ITENS DO ORÇAMENTO ========== -->
                    </div> <!-- Fim do card-body (agora com o novo conteúdo) -->
                </div> <!-- Fim Card Itens do Orçamento -->



        </div>

        <!-- Valores, Taxas e Condições -->
        <div class="card card-warning card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calculator mr-2"></i>Totais, Taxas e Condições</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="observacoes">Observações Gerais</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="4"
                                placeholder="Ex: Cliente solicitou montagem especial..."><?= htmlspecialchars($textoPadraoObservacoes) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="condicoes_pagamento">Condições de Pagamento</label>
                            <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento" rows="4"
                                placeholder="Ex: 50% na aprovação, 50% na entrega. PIX CNPJ ..."><?= htmlspecialchars($textoPadraoCondicoes) ?></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="ajuste_manual"
                                    name="ajuste_manual">
                                <label class="custom-control-label" for="ajuste_manual">Ajustar Valor Final
                                    Manualmente?</label>
                            </div>
                        </div>
                        <div class="form-group" id="campo_motivo_ajuste" style="display: none;">
                            <label for="motivo_ajuste">Motivo do Ajuste Manual</label>
                            <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste"
                                placeholder="Ex: Desconto especial concedido">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group row">
                            <label for="desconto_total" class="col-sm-6 col-form-label">Desconto Total (R$):</label>
                            <div class="col-sm-6"><input type="text" class="form-control money text-right"
                                    id="desconto_total" name="desconto_total" value="0,00"></div>
                        </div>
                        <hr>
                        <h5 class="text-muted">Taxas Adicionais (Valores Informativos/Editáveis)</h5>
                        <div class="form-group row">
                            <label for="taxa_domingo_feriado" class="col-sm-6 col-form-label">Taxa Domingo/Feriado
                                (R$):</label>
                            <div class="col-sm-6"><input type="text" class="form-control money text-right"
                                    id="taxa_domingo_feriado" name="taxa_domingo_feriado"
                                    value="<?= number_format($valorPadraoTaxaDomingo, 2, ',', '.') ?>"></div>
                        </div>
                        <div class="form-group row">
                            <label for="taxa_madrugada" class="col-sm-6 col-form-label">Taxa Madrugada (R$):</label>
                            <div class="col-sm-6"><input type="text" class="form-control money text-right"
                                    id="taxa_madrugada" name="taxa_madrugada"
                                    value="<?= number_format($valorPadraoTaxaMadrugada, 2, ',', '.') ?>">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="taxa_horario_especial" class="col-sm-6 col-form-label">Taxa Horário Especial
                                (R$):</label>
                            <div class="col-sm-6"><input type="text" class="form-control money text-right"
                                    id="taxa_horario_especial" name="taxa_horario_especial"
                                    value="<?= number_format($valorPadraoTaxaHorarioEspecial, 2, ',', '.') ?>"></div>
                        </div>
                        <div class="form-group row">
                            <label for="taxa_hora_marcada" class="col-sm-6 col-form-label">Taxa Hora Marcada
                                (R$):</label>
                            <div class="col-sm-6"><input type="text" class="form-control money text-right"
                                    id="taxa_hora_marcada" name="taxa_hora_marcada"
                                    value="<?= number_format($valorPadraoTaxaHoraMarcada, 2, ',', '.') ?>"></div>
                        </div>
                        <hr>
                        <h5 class="text-muted">Frete</h5>
                        <div class="form-group row">
                            <label for="frete_terreo" class="col-sm-6 col-form-label">Frete Térreo (R$):</label>
                            <div class="col-sm-6"><input type="text" class="form-control money text-right"
                                    id="frete_terreo" name="frete_terreo"
                                    value="<?= number_format($valorPadraoFreteTerreo, 2, ',', '.') ?>">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="frete_elevador" class="col-sm-6 col-form-label">Frete Elevador:</label>
                            <div class="col-sm-6"><input type="text" class="form-control" id="frete_elevador"
                                    name="frete_elevador" placeholder="Ex: R$ 50,00 ou A Confirmar"></div>
                        </div>
                        <div class="form-group row">
                            <label for="frete_escadas" class="col-sm-6 col-form-label">Frete Escadas:</label>
                            <div class="col-sm-6"><input type="text" class="form-control" id="frete_escadas"
                                    name="frete_escadas" placeholder="Ex: R$ 100,00 por lance ou A Confirmar"></div>
                        </div>
                        <hr>
                        <div class="form-group row mt-3 bg-light p-2 rounded">
                            <label class="col-sm-6 col-form-label text-lg text-primary">VALOR FINAL (R$):</label>
                            <div class="col-sm-6">
                                <input type="text"
                                    class="form-control form-control-lg text-right font-weight-bold text-primary money-display"
                                    id="valor_final_display" readonly value="R$ 0,00"
                                    style="background-color: #e9ecef; border: none;">
                                <input type="hidden" id="valor_final_hidden" name="valor_final" value="0.00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <a href="index.php" class="btn btn-secondary mr-2">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save mr-1"></i> Salvar
                    Orçamento</button>
            </div>
        </div>
        </form>
</div>
</section>
</div>

<!-- Modal Novo Cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1" role="dialog" aria-labelledby="modalNovoClienteLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoClienteLabel">Novo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="formNovoClienteModal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_nome">Nome Completo / Razão Social <span
                                        class="text-danger">*</span></label><input type="text" class="form-control"
                                    id="modal_cliente_nome" name="nome" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_cpf_cnpj">CPF/CNPJ</label><input
                                    type="text" class="form-control" id="modal_cliente_cpf_cnpj" name="cpf_cnpj"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_email">E-mail</label><input type="email"
                                    class="form-control" id="modal_cliente_email" name="email"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_telefone">Telefone <span
                                        class="text-danger">*</span></label><input type="text"
                                    class="form-control telefone" id="modal_cliente_telefone" name="telefone" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label for="modal_cliente_endereco">Endereço (Rua, Nº, Bairro)</label><input
                            type="text" class="form-control" id="modal_cliente_endereco" name="endereco"></div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group"><label for="modal_cliente_cidade">Cidade</label><input type="text"
                                    class="form-control" id="modal_cliente_cidade" name="cidade" value="Porto Alegre">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label for="modal_cliente_cep">CEP</label><input type="text"
                                    class="form-control cep" id="modal_cliente_cep" name="cep"></div>
                        </div>
                    </div>
                    <div class="form-group"><label for="modal_cliente_observacoes">Observações do
                            Cliente</label><textarea class="form-control" id="modal_cliente_observacoes"
                            name="observacoes" rows="2"></textarea></div>
                    <div id="modalClienteFeedback" class="mt-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarClienteModal">Salvar Cliente</button>
            </div>
        </div>
    </div>
</div>


<?php
$custom_js = <<<'JS'
$(document).ready(function() {
    // Variável global para o índice dos itens da tabela de orçamento
    var itemIndex = 0; 
    // Definição de BASE_URL para caminhos de imagem no JavaScript
    //var BASE_URL_PATH = '<?= BASE_URL ?>'; 

    // =======================================================================
    // INÍCIO: LÓGICA PARA BUSCA DE PRODUTOS E MANIPULAÇÃO DE ITENS DO ORÇAMENTO
    // =======================================================================
    function carregarSugestoesProdutos() {
        var termoBusca = $('#busca_produto').val().trim();
        var categoriaSelecionada = $('#busca_categoria_produto').val();

        if (termoBusca.length < 2 && !categoriaSelecionada) {
            $('#sugestoes_produtos').empty().hide();
            return;
        }

        $.ajax({
            url: 'create.php?ajax=buscar_produtos',
            type: 'GET',
            dataType: 'json',
            data: { termo: termoBusca, categoria_id: categoriaSelecionada },
            success: function(produtos) {
                $('#sugestoes_produtos').empty().show();
                if (produtos && produtos.length > 0) {
                    $.each(produtos, function(i, produto) {
                        let preco = parseFloat(produto.preco_locacao) || 0;
                        let fotoHtml = '';
                        if (produto.foto_path_completo) { // MODIFICADO AQUI: Verifica se foto_path_completo existe
    fotoHtml = `<img src="${produto.foto_path_completo}"  // MODIFICADO AQUI
                     alt="Miniatura" 
                     class="img-thumbnail mr-2 foto-produto-sugestao" 
                     style="width: 40px; height: 40px; object-fit: cover; cursor:pointer;"
                     data-foto-completa="${produto.foto_path_completo}" // MODIFICADO AQUI
                     data-nome-produto="${produto.nome_produto || 'Produto sem nome'}">`;
} else {
                            fotoHtml = `<span class="mr-2 d-inline-block text-center text-muted" style="width: 40px; height: 40px; line-height:40px; border:1px solid #eee; font-size:0.8em;"><i class="fas fa-camera"></i></span>`;
                        }

                        $('#sugestoes_produtos').append(
                            `<a href="#" class="list-group-item list-group-item-action d-flex align-items-center item-sugestao-produto py-2" 
                               data-id="${produto.id}" 
                               data-nome="${produto.nome_produto || 'Produto sem nome especificado'}" 
                               data-codigo="${produto.codigo || ''}" 
                               data-preco="${preco}">
                                ${fotoHtml}
                                <div class="flex-grow-1">
                                    <strong>${produto.nome_produto || 'Produto sem nome'}</strong>
                                    ${produto.codigo ? '<small class="d-block text-muted">Cód: ' + produto.codigo + '</small>' : ''}
                                    ${produto.quantidade_total !== null ? '<small class="d-block text-info">Estoque: ' + produto.quantidade_total + '</small>' : ''}
                                </div>
                                <span class="ml-auto text-primary font-weight-bold">R$ ${preco.toFixed(2).replace('.', ',')}</span>
                            </a>`
                        );
                    });
                } else {
                    $('#sugestoes_produtos').append('<div class="list-group-item text-muted">Nenhum produto encontrado.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro AJAX buscar_produtos:", status, error, xhr.responseText);
                $('#sugestoes_produtos').empty().show().append('<div class="list-group-item text-danger">Erro ao buscar produtos. Tente novamente.</div>');
            }
        });
    }

    $('#busca_produto').on('keyup', carregarSugestoesProdutos);
    $('#busca_categoria_produto').on('change', carregarSugestoesProdutos);

    $('#btnLimparBuscaProduto').on('click', function() {
        $('#busca_produto').val('');
        $('#sugestoes_produtos').empty().hide();
        $('#busca_produto').focus();
    });

    $('#sugestoes_produtos').on('click', '.foto-produto-sugestao', function(e) {
        e.preventDefault(); 
        e.stopPropagation(); 
        var fotoSrc = $(this).data('foto-completa');
        var nomeProduto = $(this).data('nome-produto');
        if (fotoSrc) {
            $('#fotoProdutoAmpliada').attr('src', fotoSrc);
            $('#modalFotoProdutoLabelText').text(nomeProduto || 'Visualizar Imagem');
            $('#modalFotoProduto').modal('show');
        }
    });

    $('#sugestoes_produtos').on('click', '.item-sugestao-produto', function(e) {
        e.preventDefault();
        var produtoSelecionado = {
            id: $(this).data('id'),
            nome_produto: $(this).data('nome'),
            codigo: $(this).data('codigo'),
            preco_locacao: $(this).data('preco')
        };
        adicionarLinhaItem(produtoSelecionado); 
        $('#busca_produto').val('').focus();
        $('#sugestoes_produtos').empty().hide();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busca_produto, #busca_categoria_produto, #sugestoes_produtos').length) {
            $('#sugestoes_produtos').empty().hide();
        }
    });
    // =======================================================================
    // FIM: LÓGICA PARA BUSCA DE PRODUTOS
    // =======================================================================

    // Inicializar Select2 para busca de clientes
    if (typeof $.fn.select2 === 'function') {
        $('#cliente_id').select2({
            placeholder: 'Selecione ou Busque um Cliente',
            allowClear: true,
            width: '100%',
            theme: 'bootstrap4', 
            language: "pt-BR", 
            ajax: {
                url: 'create.php?ajax=buscar_clientes',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { termo: params.term }; },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (cliente) {
                            return {
                                id: cliente.id,
                                text: cliente.nome + (cliente.cpf_cnpj ? ' (' + cliente.cpf_cnpj.replace(/[^0-9]/g, '').replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5").replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4") + ')' : ''),
                                full_data: cliente
                            }
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 2
        }).on('select2:select', function (e) {
            var data = e.params.data.full_data;
            if (data) {
                $('#cliente_info_selecionado').html(
                    `<strong>Tel:</strong> ${data.telefone || '-'} | <strong>Email:</strong> ${data.email || '-'}<br>` +
                    `<strong>End.:</strong> ${data.endereco || '-'}, ${data.cidade || '-'}` +
                    (data.observacoes ? `<br><strong>Obs Cliente:</strong> ${data.observacoes}` : '')
                );
                $('#btnUsarEnderecoCliente').fadeIn();
            } else {
                $('#cliente_info_selecionado').html('');
                $('#btnUsarEnderecoCliente').fadeOut();
            }
        }).on('select2:unselect', function (e) {
            $('#cliente_info_selecionado').html('');
            $('#btnUsarEnderecoCliente').fadeOut();
        });
    }

    // Inicializar Datepickers
    if (typeof $.fn.datepicker === 'function' && typeof $.fn.datepicker.dates !== 'undefined' && $.fn.datepicker.dates['pt-BR']) {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            language: 'pt-BR',
            autoclose: true,
            todayHighlight: true,
            showOnFocus: true, 
            orientation: "bottom auto" 
        }).on('show', function(e){
             if ($('.modal.show').length > 0) {
                $(this).data('datepicker').picker.css('z-index', parseInt($('.modal.show').css('z-index')) + 10);
            }
        });
    } else {
        console.error("Bootstrap Datepicker ou locale pt-BR não carregado.");
    }

    function calcularDataValidade() {
        var dataOrcamentoStr = $('#data_orcamento').val();
        var validadeDias = parseInt($('#validade_dias').val());
        if (dataOrcamentoStr && validadeDias > 0) {
            var partesData = dataOrcamentoStr.split('/');
            if (partesData.length === 3) {
                var dataOrcamento = new Date(partesData[2], partesData[1] - 1, partesData[0]);
                if (!isNaN(dataOrcamento.valueOf())) { 
                    dataOrcamento.setDate(dataOrcamento.getDate() + validadeDias);
                    var dia = String(dataOrcamento.getDate()).padStart(2, '0');
                    var mes = String(dataOrcamento.getMonth() + 1).padStart(2, '0');
                    var ano = dataOrcamento.getFullYear();
                    var dataValidadeFormatadaUser = dia + '/' + mes + '/' + ano;
                    var dataValidadeFormatadaHidden = ano + '-' + mes + '-' + dia;
                    $('#data_validade_display').text('Validade até: ' + dataValidadeFormatadaUser);
                    $('#data_validade_calculada_hidden').val(dataValidadeFormatadaHidden);
                    return; 
                }
            }
        }
        $('#data_validade_display').text('Data de orçamento ou validade inválida.');
        $('#data_validade_calculada_hidden').val('');
    }
    $('#data_orcamento, #validade_dias').on('change keyup blur', calcularDataValidade);
    calcularDataValidade(); 

    const diasDaSemana = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
    function exibirDiaSemana(inputId, displayId) {
        var dataStr = $(inputId).val();
        var displayEl = $(displayId);
        displayEl.text('').removeClass('text-danger font-weight-bold text-success');
        if (dataStr) {
            var partes = dataStr.split('/');
            if (partes.length === 3) {
                var dataObj = new Date(partes[2], partes[1] - 1, partes[0]); 
                if (!isNaN(dataObj.valueOf())) { 
                    var diaSemana = diasDaSemana[dataObj.getDay()];
                    displayEl.text(diaSemana).addClass('font-weight-bold');
                    if (dataObj.getDay() === 0 || dataObj.getDay() === 6) { 
                        displayEl.addClass('text-danger');
                    } else {
                        displayEl.addClass('text-success');
                    }
                    return;
                }
            }
        }
        displayEl.text('Data inválida').addClass('text-danger');
    }
    $('#data_evento').on('change dp.change keyup blur', function() { exibirDiaSemana(this, '#dia_semana_evento'); }).trigger('change');
    $('#data_entrega').on('change dp.change keyup blur', function() { exibirDiaSemana(this, '#dia_semana_entrega'); }).trigger('change');
    $('#data_devolucao_prevista').on('change dp.change keyup blur', function() { exibirDiaSemana(this, '#dia_semana_devolucao'); }).trigger('change');

    $('#btnUsarEnderecoCliente').on('click', function() {
        var clienteSelecionado = $('#cliente_id').select2('data');
        if (clienteSelecionado && clienteSelecionado.length > 0 && clienteSelecionado[0].full_data) {
            var dadosCliente = clienteSelecionado[0].full_data;
            var enderecoCompleto = (dadosCliente.endereco || '') + (dadosCliente.cidade ? (dadosCliente.endereco ? ', ' : '') + dadosCliente.cidade : '');
            $('#local_evento').val(enderecoCompleto.trim() || 'Endereço não informado');
        } else {
            alert('Nenhum cliente selecionado ou dados do cliente incompletos.');
        }
    });

    // Funções de formatação/desformatação de moeda (globais para este script)
    function formatCurrency(value) {
        let val = parseFloat(value);
        if (isNaN(val)) return "R$ 0,00";
        // Ajuste para garantir que o separador de milhar seja ponto e decimal vírgula
        return "R$ " + val.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d{2}))/g, '$1.');
    }
    function unformatCurrency(valueStr) {
        if (typeof valueStr !== 'string' || valueStr.trim() === '') return 0.0;
        // Remove 'R$', espaços, pontos de milhar e substitui vírgula decimal por ponto
        let cleanedValue = valueStr.replace(/R\$\s*/g, '').replace(/\./g, '').replace(',', '.');
        return parseFloat(cleanedValue) || 0.0;
    }
    
    // Função para adicionar linha de item à tabela (chamada pela busca e pelo botão manual)
    function adicionarLinhaItem(produto = null) {
        itemIndex++; 
        var nomeProduto = produto ? produto.nome_produto : '';
        var idProduto = produto ? produto.id : '';
        var precoUnitario = produto ? (parseFloat(produto.preco_locacao) || 0) : 0;
        var quantidade = 1;
        var descontoItem = 0;
        var subtotalItem = (quantidade * precoUnitario) - descontoItem;

        if (idProduto && $(`#tabela_itens_orcamento tbody .produto_id[value="${idProduto}"]`).length > 0) {
            if (!confirm("Este produto já foi adicionado. Deseja adicionar novamente? (Use observações para diferenciar)")) {
                return; 
            }
        }

        var newRow = `
            <tr class="item-orcamento-row" data-index="${itemIndex}">
                <td>
                    <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_produto_display" value="${nomeProduto}" placeholder="Nome do Produto/Serviço" ${produto ? 'readonly' : ''}>
                    <input type="hidden" name="produto_id[]" class="produto_id" value="${idProduto}">
                    <input type="hidden" name="tipo_item[]" class="tipo_item" value="locacao">
                    <small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small>
                    <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação específica do item">
                </td>
                <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item" value="${quantidade}" min="1" style="width: 70px;"></td>
                <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input" value="${precoUnitario.toFixed(2).replace('.', ',')}" style="width: 100px;"></td>
                <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoItem.toFixed(2).replace('.', ',')}" style="width: 100px;"></td>
                <td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalItem).replace('R$ ','')}</td>
                <td>
                    <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Adicionar Observação ao Item"><i class="fas fa-comment-dots"></i></button>
                    <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Item"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#tabela_itens_orcamento tbody').append(newRow);
        // Se você usa jQuery Mask Plugin para formatar campos de moeda enquanto digita:
        // $('.money-input').mask('#.##0,00', {reverse: true}); 
        calcularTotaisOrcamento();
    }

    $('#btn_adicionar_item_manual').click(function() {
        adicionarLinhaItem();
    });

    $('#tabela_itens_orcamento').on('click', '.btn_remover_item', function() {
        $(this).closest('tr').remove();
        calcularTotaisOrcamento();
    });

    $('#tabela_itens_orcamento').on('click', '.btn_obs_item', function() {
        var $row = $(this).closest('tr');
        $row.find('.observacoes_item_label, .observacoes_item_input').toggle();
        if ($row.find('.observacoes_item_input').is(':visible')) {
             $row.find('.observacoes_item_input').focus();
        }
    });

    // Função para calcular o subtotal de uma linha de item
    function calcularSubtotalItem($row) {
        var quantidade = parseFloat($row.find('.quantidade_item').val()) || 0;
        var valorUnitario = unformatCurrency($row.find('.valor_unitario_item').val());
        var descontoItem = unformatCurrency($row.find('.desconto_item').val());
        var subtotal = (quantidade * valorUnitario) - descontoItem;
        $row.find('.subtotal_item_display').text(formatCurrency(subtotal).replace('R$ ','')); // Mostra apenas o valor
        return subtotal;
    }

    // Função principal para calcular todos os totais do orçamento
    function calcularTotaisOrcamento() {
        var subtotalGeralItens = 0;
        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            subtotalGeralItens += calcularSubtotalItem($(this));
        });
        $('#subtotal_geral_itens').text(formatCurrency(subtotalGeralItens));

        var descontoTotal = unformatCurrency($('#desconto_total').val());
        var taxaDomingo = unformatCurrency($('#taxa_domingo_feriado').val());
        var taxaMadrugada = unformatCurrency($('#taxa_madrugada').val());
        var taxaHorarioEspecial = unformatCurrency($('#taxa_horario_especial').val());
        var taxaHoraMarcada = unformatCurrency($('#taxa_hora_marcada').val());
        var freteTerreo = unformatCurrency($('#frete_terreo').val());
        // Adicionar outras taxas/fretes se houver (ex: frete_elevador, frete_escadas, se forem numéricos)

        var valorFinal = subtotalGeralItens - descontoTotal + 
                         taxaDomingo + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada +
                         freteTerreo;
        
        $('#valor_final_display').val(formatCurrency(valorFinal));
        $('#valor_final_hidden').val(valorFinal.toFixed(2));
    }

    // Eventos para recalcular totais quando itens ou taxas mudam
    $('#tabela_itens_orcamento').on('change keyup input', '.quantidade_item, .valor_unitario_item, .desconto_item', function() {
        // Não precisa chamar calcularSubtotalItem aqui, pois já é feito em calcularTotaisOrcamento
        calcularTotaisOrcamento();
    });
    $('#desconto_total, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo').on('change keyup input', function() {
        calcularTotaisOrcamento();
    });
    
    // Chamar uma vez no carregamento para inicializar os totais
    calcularTotaisOrcamento();

    $('#ajuste_manual').on('change', function() {
        if ($(this).is(':checked')) {
            $('#campo_motivo_ajuste').slideDown();
        } else {
            $('#campo_motivo_ajuste').slideUp();
            $('#motivo_ajuste').val('');
        }
    });

    $('#btnSalvarClienteModal').on('click', function() {
        var formData = $('#formNovoClienteModal').serialize();
        $.ajax({
            url: '../clientes/store_ajax.php', // Verifique este caminho
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.cliente) {
                    var newOption = new Option(response.cliente.nome + (response.cliente.cpf_cnpj ? ' (' + response.cliente.cpf_cnpj + ')' : ''), response.cliente.id, true, true);
                    $('#cliente_id').append(newOption).trigger('change');
                    $('#cliente_id').trigger({ type: 'select2:select', params: { data: { full_data: response.cliente } } });
                    $('#modalNovoCliente').modal('hide');
                    $('#formNovoClienteModal')[0].reset();
                    $('#modalClienteFeedback').html(''); // Limpa feedback anterior
                    // Idealmente, usar um sistema de notificação (toastr, etc.)
                    alert('Cliente salvo e selecionado!'); 
                } else {
                    $('#modalClienteFeedback').html('<div class="alert alert-danger">' + (response.message || 'Erro desconhecido ao salvar cliente.') + '</div>');
                }
            },
            error: function(xhr) {
                $('#modalClienteFeedback').html('<div class="alert alert-danger">Erro de comunicação ao salvar cliente. Verifique o console.</div>');
                console.error("Erro AJAX salvar cliente:", xhr.responseText);
            }
        });
    });

    $('#formNovoOrcamento').on('submit', function(event) {
        // Validação Bootstrap HTML5
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        $(this).addClass('was-validated');

        // Validação customizada do cliente
        if (!$('#cliente_id').val()) {
            alert('Por favor, selecione um cliente.');
            $('#cliente_id').select2('open');
            event.preventDefault();
            event.stopPropagation();
            return false;
        }

        // Validação de itens
        if ($('#tabela_itens_orcamento tbody tr.item-orcamento-row').length === 0) {
            alert('Adicione pelo menos um item ao orçamento.');
            $('#busca_produto').focus(); // Foca na busca para adicionar itens
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    });

}); // Fim do $(document).ready
JS;
include_once __DIR__ . '/../includes/footer.php'; // Certifique-se que este arquivo imprime $custom_js e carrega jQuery ANTES
?>