<?php
$page_title = "Novo Orçamento";
// Define $extra_css e $custom_js aqui se necessário para este arquivo específico
// Ex: $extra_css = [BASE_URL . '/assets/css/orcamentos_create.css'];

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Orcamento.php'; // Model que acabamos de ajustar

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection(); // Conexão PDO

$clienteModel = new Cliente($db);
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db); // Instância do nosso model ajustado

$numeroFormatado = 'Gerado ao Salvar';

// Textos padrão para observações e condições
$textoPadraoObservacoes = "# Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.\n* Não Inclui Posicionamento dos Móveis no Local.";
$textoPadraoCondicoes = "50% na aprovação para reserva em PIX ou Depósito.\nSaldo em PIX ou Depósito 7 dias antes do evento.\n* Consulte disponibilidade e preços para pagamento no cartão de crédito.";

// Valores padrão para taxas (para exibição inicial no formulário)
$valorPadraoTaxaDomingo = 250.00;
$valorPadraoTaxaMadrugada = 800.00;
$valorPadraoTaxaHorarioEspecial = 500.00;
$valorPadraoTaxaHoraMarcada = 200.00;
$valorPadraoFreteTerreo = 180.00;
$valorPadraoFreteElevador = 100.00;
$valorPadraoFreteEscadas = 200.00;


// --- Bloco AJAX para buscar clientes ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_clientes') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';

        if (empty($termo)) {
            // Se o termo de busca for vazio, retorna os últimos clientes cadastrados
            $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes
                    FROM clientes
                    ORDER BY id DESC
                    LIMIT 10";
            $stmt = $db->prepare($sql);
        } else {
            // Se houver um termo, faz a busca inteligente
            $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes
                    FROM clientes
                    WHERE nome LIKE :termo_nome
                       OR REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :termo_cpf_cnpj
                       OR email LIKE :termo_email
                    -- A MÁGICA ACONTECE AQUI:
                    ORDER BY 
                        CASE
                            WHEN nome LIKE :termo_inicio THEN 1 -- Prioridade 1: Nome começa com o termo
                            ELSE 2 -- Prioridade 2: Nome contém o termo em outro lugar
                        END, 
                        nome ASC -- Ordem alfabética como desempate
                    LIMIT 15";

            $stmt = $db->prepare($sql);
            $likeTerm = "%" . $termo . "%";
            $likeTermInicio = $termo . "%"; // Termo para busca de 'começa com'
            $likeTermCpfCnpj = "%" . preg_replace('/[^0-9]/', '', $termo) . "%";

            $stmt->bindParam(':termo_nome', $likeTerm, PDO::PARAM_STR);
            $stmt->bindParam(':termo_inicio', $likeTermInicio, PDO::PARAM_STR); // Novo parâmetro
            $stmt->bindParam(':termo_cpf_cnpj', $likeTermCpfCnpj, PDO::PARAM_STR);
            $stmt->bindParam(':termo_email', $likeTerm, PDO::PARAM_STR);
        }

        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Erro AJAX buscar_clientes: " . $e->getMessage());
        echo json_encode(['error' => 'Erro no banco de dados ao buscar clientes.']);
        exit;
    }
}
// --- Bloco AJAX para buscar produtos ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_produtos') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        $categoria_principal_id = isset($_GET['categoria_id']) ? (int) $_GET['categoria_id'] : 0;

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

        $base_url_config = rtrim(BASE_URL, '/');

        foreach ($produtos_ajax as &$produto_item) {
            if (!empty($produto_item['foto_path']) && $produto_item['foto_path'] !== "null" && trim($produto_item['foto_path']) !== "") {
                $foto_path_limpo = ltrim($produto_item['foto_path'], '/');
                $produto_item['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;

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

// --- CÓDIGO PHP- PROCESSAMENTO FORMULÁRIO (submissão do formulário) (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        $proximoNumeroGerado = $numeracaoModel->gerarProximoNumero('orcamento');
        if ($proximoNumeroGerado === false || $proximoNumeroGerado === null) {
            throw new Exception("Falha crítica ao gerar o número sequencial do orçamento.");
        }
        $orcamentoModel->numero = $proximoNumeroGerado;

        if (empty($_POST['cliente_id'])) {
            throw new Exception("Cliente é obrigatório.");
        }
        $orcamentoModel->cliente_id = (int) $_POST['cliente_id'];

        // Datas e Horas
        $data_orcamento_input = $_POST['data_orcamento'] ?? date('d/m/Y');
        $data_orcamento_dt = DateTime::createFromFormat('d/m/Y', $data_orcamento_input) ?: DateTime::createFromFormat('Y-m-d', $data_orcamento_input) ?: new DateTime();
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

        $orcamentoModel->local_evento = !empty($_POST['local_evento']) ? trim($_POST['local_evento']) : null;

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

        // Valores financeiros do cabeçalho
        $orcamentoModel->desconto = $fnConverterMoeda($_POST['desconto_total'] ?? '0,00');
        $orcamentoModel->taxa_domingo_feriado = $fnConverterMoeda($_POST['taxa_domingo_feriado'] ?? '0,00');
        $orcamentoModel->taxa_madrugada = $fnConverterMoeda($_POST['taxa_madrugada'] ?? '0,00');
        $orcamentoModel->taxa_horario_especial = $fnConverterMoeda($_POST['taxa_horario_especial'] ?? '0,00');
        $orcamentoModel->taxa_hora_marcada = $fnConverterMoeda($_POST['taxa_hora_marcada'] ?? '0,00');
        $orcamentoModel->frete_terreo = $fnConverterMoeda($_POST['frete_terreo'] ?? '0,00');
        $orcamentoModel->frete_elevador = $fnConverterMoeda($_POST['frete_elevador'] ?? '0,00');
        $orcamentoModel->frete_escadas = $fnConverterMoeda($_POST['frete_escadas'] ?? '0,00');
        $orcamentoModel->ajuste_manual = isset($_POST['ajuste_manual_valor_final']) ? 1 : 0;
        // CÓDIGO CORRIGIDO (DEPOIS)
        $orcamentoModel->motivo_ajuste = !empty($_POST['motivo_ajuste']) ? trim($_POST['motivo_ajuste']) : null;
        $orcamentoModel->observacoes = !empty($_POST['observacoes_gerais']) ? trim($_POST['observacoes_gerais']) : null;
        $orcamentoModel->condicoes_pagamento = !empty($_POST['condicoes_pagamento']) ? trim($_POST['condicoes_pagamento']) : null;
        $orcamentoModel->usuario_id = $_SESSION['usuario_id'] ?? 1;

        $orcamentoIdSalvo = $orcamentoModel->create();

        if ($orcamentoIdSalvo === false || $orcamentoIdSalvo <= 0) {
            throw new Exception("Falha ao salvar o cabeçalho do orçamento. Verifique os logs.");
        }

        // ---- MONTAGEM DO ARRAY $itens ----
        $itens = [];
        if (isset($_POST['tipo_linha']) && is_array($_POST['tipo_linha'])) {
            foreach ($_POST['tipo_linha'] as $index => $tipo_linha_post) {
                $tipo_linha_atual = trim($tipo_linha_post);
                $ordem_atual = isset($_POST['ordem'][$index]) ? (int) $_POST['ordem'][$index] : ($index + 1);

                $item_data = [
                    'produto_id' => null,
                    'nome_produto_manual' => null,
                    'quantidade' => 0,
                    'preco_unitario' => 0.00,
                    'desconto' => 0.00,
                    'preco_final' => 0.00,
                    'tipo' => null,
                    'observacoes' => isset($_POST['observacoes_item'][$index]) ? trim($_POST['observacoes_item'][$index]) : null,
                    'tipo_linha' => $tipo_linha_atual,
                    'ordem' => $ordem_atual
                ];

                if ($tipo_linha_atual === 'CABECALHO_SECAO') {
                    $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : 'Título não informado';
                    $item_data['tipo'] = null;
                } else if ($tipo_linha_atual === 'PRODUTO') {
                    $item_data['produto_id'] = isset($_POST['produto_id'][$index]) && !empty($_POST['produto_id'][$index]) ? (int) $_POST['produto_id'][$index] : null;

                    if ($item_data['produto_id'] === null) {
                        $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : null;
                    }

                    $item_data['quantidade'] = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 1;
                    if ($item_data['quantidade'] <= 0)
                        $item_data['quantidade'] = 1;

                    $item_data['tipo'] = $_POST['tipo_item'][$index] ?? 'locacao';
                    $item_data['preco_unitario'] = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                    $item_data['desconto'] = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');

                    // ===== PONTO CRÍTICO CORRIGIDO =====
                    // A fórmula correta, com parênteses, para calcular o preço final.
                    $item_data['preco_final'] = $item_data['quantidade'] * ($item_data['preco_unitario'] - $item_data['desconto']);

                } else {
                    error_log("Tipo de linha desconhecido ou inválido no índice {$index}: '{$tipo_linha_atual}' - Item ignorado.");
                    continue;
                }
                $itens[] = $item_data;
            }
        }
        // ---- FIM DA MONTAGEM DO ARRAY $itens ----

        if (!empty($itens)) {
            if (!$orcamentoModel->salvarItens($orcamentoIdSalvo, $itens)) {
                throw new Exception("Falha ao salvar um ou mais itens do orçamento. Verifique os logs do servidor.");
            }
        }

        $orcamentoModel->id = $orcamentoIdSalvo;
        if (!$orcamentoModel->recalcularValores($orcamentoIdSalvo)) {
            throw new Exception("Orçamento salvo, mas houve um problema ao recalcular os valores finais. Edite o orçamento para corrigir.");
        }

        $db->commit();
        $_SESSION['success_message'] = "Orçamento #" . htmlspecialchars($orcamentoModel->numero) . " (Código: " . htmlspecialchars($orcamentoModel->codigo) . ") criado com sucesso!";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = "Ocorreu um erro: " . $e->getMessage();
        error_log("[EXCEÇÃO NO PROCESSAMENTO DO ORÇAMENTO]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
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
                                    <div class="input-group-append">
                                        <button type="button" id="btnUsarEnderecoCliente"
                                            class="btn btn-sm btn-outline-info"
                                            title="Usar endereço do cliente selecionado">
                                            <i class="fas fa-map-marker-alt"></i> Usar End. Cliente
                                        </button>
                                    </div>
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
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label for="busca_categoria_produto">Filtrar por Categoria:</label>
                                <select id="busca_categoria_produto" class="form-control form-control-sm">
                                    <option value="">Todas as Categorias</option>
                                    <?php
                                    try {
                                        $stmt_main_categorias = $db->query("SELECT id, nome FROM categorias ORDER BY nome");
                                        if ($stmt_main_categorias) {
                                            $main_categorias_list = $stmt_main_categorias->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($main_categorias_list as $main_cat_item) {
                                                echo '<option value="' . htmlspecialchars($main_cat_item['id']) . '">' . htmlspecialchars($main_cat_item['nome']) . '</option>';
                                            }
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
                                    style="position: absolute; z-index: 1000; width: calc(100% - 30px); max-height: 260px; overflow-y: auto; display:none; border: 1px solid #ced4da; background-color: white;">
                                </div>
                            </div>
                        </div>

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
                                        <th style="width: 35%;">Produto/Serviço/Seção <span class="text-danger">*</span>
                                        </th>
                                        <th style="width: 10%;">Qtd. <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Vlr. Unit. (R$)</th>
                                        <th style="width: 15%;">Desc. Item (R$)</th>
                                        <th style="width: 15%;">Subtotal (R$)</th>
                                        <th style="width: 10%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Linhas de itens e títulos serão adicionadas aqui via JavaScript -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-right"><strong>Subtotal dos Itens:</strong></td>
                                        <td id="subtotal_geral_itens" class="text-right font-weight-bold">A confirmar
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-info btn-sm mr-2" id="btn_adicionar_titulo_secao">
                                <i class="fas fa-heading"></i> Adicionar Título de Seção
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="btn_adicionar_item_manual">
                                <i class="fas fa-plus"></i> Adicionar Item Manualmente
                            </button>
                        </div>
                    </div>
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
                                    <label for="observacoes_gerais">Observações Gerais</label>
                                    <textarea class="form-control" id="observacoes_gerais" name="observacoes_gerais"
                                        rows="3"
                                        placeholder="Ex: Cliente solicitou montagem especial..."><?= htmlspecialchars($textoPadraoObservacoes ?? '') ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="condicoes_pagamento">Condições de Pagamento</label>
                                    <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento"
                                        rows="3"
                                        placeholder="Ex: 50% na aprovação, 50% na entrega. PIX CNPJ ..."><?= htmlspecialchars($textoPadraoCondicoes ?? '') ?></textarea>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input"
                                            id="ajuste_manual_valor_final" name="ajuste_manual_valor_final">
                                        <label class="custom-control-label" for="ajuste_manual_valor_final">Ajustar
                                            Valor Final
                                            Manualmente?</label>
                                    </div>
                                </div>
                                <div class="form-group" id="campo_motivo_ajuste" style="display: none;">
                                    <label for="motivo_ajuste_valor_final">Motivo do Ajuste Manual</label>
                                    <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste"
                                        placeholder="Ex: Desconto especial concedido">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <hr>
                                <h5 class="text-muted">Taxas Adicionais</h5>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_domingo" id="aplicar_taxa_domingo"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="taxa_domingo_feriado">
                                    </div>
                                    <label for="aplicar_taxa_domingo" class="col-sm-5 col-form-label pr-1">
                                        Taxa Dom./Feriado <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoTaxaDomingo, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="taxa_domingo_feriado" name="taxa_domingo_feriado"
                                                placeholder="a confirmar" value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoTaxaDomingo, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="taxa_domingo_feriado"
                                                    data-target-checkbox="aplicar_taxa_domingo"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoTaxaDomingo, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_madrugada" id="aplicar_taxa_madrugada"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="taxa_madrugada">
                                    </div>
                                    <label for="aplicar_taxa_madrugada" class="col-sm-5 col-form-label pr-1">
                                        Taxa Madrugada <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoTaxaMadrugada, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="taxa_madrugada" name="taxa_madrugada" placeholder="a confirmar"
                                                value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoTaxaMadrugada, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="taxa_madrugada"
                                                    data-target-checkbox="aplicar_taxa_madrugada"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoTaxaMadrugada, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_horario_especial"
                                            id="aplicar_taxa_horario_especial"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="taxa_horario_especial">
                                    </div>
                                    <label for="aplicar_taxa_horario_especial" class="col-sm-5 col-form-label pr-1">
                                        Taxa Hor. Especial <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoTaxaHorarioEspecial, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="taxa_horario_especial" name="taxa_horario_especial"
                                                placeholder="a confirmar" value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoTaxaHorarioEspecial, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="taxa_horario_especial"
                                                    data-target-checkbox="aplicar_taxa_horario_especial"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoTaxaHorarioEspecial, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_hora_marcada"
                                            id="aplicar_taxa_hora_marcada" class="form-check-input taxa-frete-checkbox"
                                            data-target-input="taxa_hora_marcada">
                                    </div>
                                    <label for="aplicar_taxa_hora_marcada" class="col-sm-5 col-form-label pr-1">
                                        Taxa Hora Marcada <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoTaxaHoraMarcada, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="taxa_hora_marcada" name="taxa_hora_marcada"
                                                placeholder="a confirmar" value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoTaxaHoraMarcada, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="taxa_hora_marcada"
                                                    data-target-checkbox="aplicar_taxa_hora_marcada"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoTaxaHoraMarcada, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5 class="text-muted">Frete</h5>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_frete_terreo" id="aplicar_frete_terreo"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="frete_terreo">
                                    </div>
                                    <label for="aplicar_frete_terreo" class="col-sm-5 col-form-label pr-1">
                                        Frete Térreo <small class="text-muted">(Sob consulta)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="frete_terreo" name="frete_terreo" placeholder="a confirmar" value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoFreteTerreo, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="frete_terreo"
                                                    data-target-checkbox="aplicar_frete_terreo"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoFreteTerreo, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_frete_elevador" id="aplicar_frete_elevador"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="frete_elevador">
                                    </div>
                                    <label for="aplicar_frete_elevador" class="col-sm-5 col-form-label pr-1">
                                        Frete Elevador <small class="text-muted">(Sob consulta)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="frete_elevador" name="frete_elevador" placeholder="a confirmar"
                                                value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoFreteElevador, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="frete_elevador"
                                                    data-target-checkbox="aplicar_frete_elevador"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoFreteElevador, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_frete_escadas" id="aplicar_frete_escadas"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="frete_escadas">
                                    </div>
                                    <label for="aplicar_frete_escadas" class="col-sm-5 col-form-label pr-1">
                                        Frete Escadas <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoFreteEscadas, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="frete_escadas" name="frete_escadas" placeholder="a confirmar"
                                                value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoFreteEscadas, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="frete_escadas"
                                                    data-target-checkbox="aplicar_frete_escadas"
                                                    title="Usar Padrão: R$ <?= htmlspecialchars(number_format($valorPadraoFreteEscadas, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_desconto_geral" id="aplicar_desconto_geral"
                                            class="form-check-input taxa-frete-checkbox"
                                            data-target-input="desconto_total">
                                    </div>
                                    <label for="aplicar_desconto_geral" class="col-sm-5 col-form-label pr-1">
                                        Desconto Geral (-)
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="desconto_total" name="desconto_total" placeholder="0,00" value=""
                                                disabled>
                                        </div>
                                    </div>
                                </div>
                                <hr>

                                <div class="form-group row mt-3 bg-light p-2 rounded">
                                    <label class="col-sm-6 col-form-label text-lg text-primary">VALOR FINAL
                                        (R$):</label>
                                    <div class="col-sm-6">
                                        <!-- CÓDIGO CORRIGIDO (DEPOIS) -->
                                        <input type="text"
                                            class="form-control form-control-lg text-right font-weight-bold text-primary money-display"
                                            id="valor_final_display" readonly placeholder="A confirmar"
                                            style="background-color: #e9ecef !important; border: none !important;">
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

                <style>
                    .form-group.row.align-items-center .col-form-label {
                        padding-top: calc(0.375rem + 1px);
                        padding-bottom: calc(0.375rem + 1px);
                        line-height: 1.5;
                    }

                    .form-check-input.taxa-frete-checkbox {
                        margin-top: 0.5rem !important;
                        margin-left: auto !important;
                        margin-right: auto !important;
                        display: block !important;
                        transform: scale(1.2);
                    }

                    .input-group-sm .btn-xs {
                        padding: .2rem .4rem;
                        font-size: .75rem;
                        line-height: 1.5;
                    }

                    .input-group-sm .btn-xs .fas {
                        margin-right: 3px;
                    }

                    .btn-group-xs.d-flex .btn {
                        flex-grow: 1;
                    }

                    .form-group.row .col-sm-1+.col-form-label {
                        padding-left: 0;
                    }
                </style>
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
    $('#btnUsarEnderecoCliente').hide(); // <-- ESCONDE O BOTAO ADIC ENDERECO CLIENTE
    var itemIndex = 0; 
    function unformatCurrency(value) {
        if (!value || typeof value !== 'string') { return 0; }
        var number = parseFloat(value.replace(/R\$\s?/, '').replace(/\./g, '').replace(',', '.')) || 0;
        return isNaN(number) ? 0 : number;
    }

    function formatCurrency(value) {
        var number = parseFloat(value) || 0;
        return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

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
                        let fotoHtml = produto.foto_path_completo ? `<img src="${produto.foto_path_completo}" alt="Miniatura" class="img-thumbnail mr-2 foto-produto-sugestao" style="width: 40px; height: 40px; object-fit: cover; cursor:pointer;" data-foto-completa="${produto.foto_path_completo}" data-nome-produto="${produto.nome_produto || 'Produto'}">` : `<span class="mr-2 d-inline-block text-center text-muted" style="width: 40px; height: 40px; line-height:40px; border:1px solid #eee; font-size:0.8em;"><i class="fas fa-camera"></i></span>`;
                        let fotoPathParaDataAttribute = produto.foto_path_completo ? produto.foto_path_completo : '';
                        $('#sugestoes_produtos').append(`<a href="#" class="list-group-item list-group-item-action d-flex align-items-center item-sugestao-produto py-2" data-id="${produto.id}" data-nome="${produto.nome_produto || 'Sem nome'}" data-codigo="${produto.codigo || ''}" data-preco="${preco}" data-foto-completa="${fotoPathParaDataAttribute}">${fotoHtml}<div class="flex-grow-1"><strong>${produto.nome_produto || 'Sem nome'}</strong>${produto.codigo ? '<small class="d-block text-muted">Cód: ' + produto.codigo + '</small>' : ''}${produto.quantidade_total !== null ? '<small class="d-block text-info">Estoque: ' + produto.quantidade_total + '</small>' : ''}</div><span class="ml-auto text-primary font-weight-bold">R$ ${preco.toFixed(2).replace('.', ',')}</span></a>`);
                    });
                } else {
                    $('#sugestoes_produtos').append('<div class="list-group-item text-muted">Nenhum produto encontrado.</div>');
                }
            },
            error: function(xhr) {
                console.error("Erro AJAX buscar_produtos:", xhr.responseText);
                $('#sugestoes_produtos').empty().show().append('<div class="list-group-item text-danger">Erro ao buscar.</div>');
            }
        });
    }

    function adicionarLinhaItemTabela(dadosItem = null, tipoLinhaParam) {
        itemIndex++;
        var tipoLinha = tipoLinhaParam;
        var htmlLinha = '';
        var nomeDisplay = dadosItem ? dadosItem.nome_produto : '';
        var produtoIdInput = dadosItem ? dadosItem.id : '';
        var precoUnitarioDefault = dadosItem ? (parseFloat(dadosItem.preco_locacao) || 0) : 0;
        var tipoItemLocVend = dadosItem ? (dadosItem.tipo_item_loc_vend || 'locacao') : 'locacao';
        var nomeInputName = "nome_produto_display[]";
        if (tipoLinha === 'PRODUTO') {
            var quantidadeDefault = 1; var descontoDefault = 0;
            var subtotalDefault = quantidadeDefault * (precoUnitarioDefault - descontoDefault);
            var imagemHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">` : '';
            htmlLinha = `<tr class="item-orcamento-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #ffffff !important;"><td>${imagemHtml}<input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}><input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}"><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}"><small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small><input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do item"></td><td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeDefault}" min="1" style="width: 70px;"></td><td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoUnitarioDefault.toFixed(2).replace('.', ',')}"></td><td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoDefault.toFixed(2).replace('.', ',')}"></td><td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault).replace('R$ ', '')}</td><td><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button> <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button></td></tr>`;
        } else if (tipoLinha === 'CABECALHO_SECAO') {
            htmlLinha = `<tr class="item-orcamento-row item-titulo-secao" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #e7f1ff !important;"><td colspan="5"><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_titulo_secao" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);"><input type="hidden" name="produto_id[]" value=""><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="quantidade[]" value="0"><input type="hidden" name="tipo_item[]" value=""><input type="hidden" name="valor_unitario[]" value="0.00"><input type="hidden" name="desconto_item[]" value="0.00"><input type="hidden" name="observacoes_item[]" value=""></td><td><button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button></td></tr>`;
        }
        if (htmlLinha) {
            $('#tabela_itens_orcamento tbody').append(htmlLinha);
            if (tipoLinha === 'CABECALHO_SECAO') {
                $('#tabela_itens_orcamento tbody tr:last-child .nome_titulo_secao').focus();
            }
            calcularTotaisOrcamento();
        }
    }

    function calcularSubtotalItem($row) {
        if ($row.data('tipo-linha') === 'CABECALHO_SECAO') { return 0; }
        var quantidade = parseFloat($row.find('.item-qtd').val()) || 0;
        var valorUnitario = unformatCurrency($row.find('.item-valor-unitario').val());
        var descontoUnitario = unformatCurrency($row.find('.desconto_item').val());
        var subtotal = quantidade * (valorUnitario - descontoUnitario);
        $row.find('.subtotal_item_display').text(formatCurrency(subtotal).replace('R$ ', ''));
        return subtotal;
    }

    // Versão robusta que calcula sempre e decide a exibição no final

function calcularTotaisOrcamento() {
    var subtotalGeralItens = 0;
    $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
        subtotalGeralItens += calcularSubtotalItem($(this));
    });

    var descontoTotalGeral = unformatCurrency($('#desconto_total').val());
    var taxaDomingo = unformatCurrency($('#taxa_domingo_feriado').val());
    var taxaMadrugada = unformatCurrency($('#taxa_madrugada').val());
    var taxaHorarioEspecial = unformatCurrency($('#taxa_horario_especial').val());
    var taxaHoraMarcada = unformatCurrency($('#taxa_hora_marcada').val());
    var freteTerreo = unformatCurrency($('#frete_terreo').val());
    var freteElevador = unformatCurrency($('#frete_elevador').val());
    var freteEscadas = unformatCurrency($('#frete_escadas').val());

    var valorFinalCalculado = subtotalGeralItens - descontoTotalGeral + taxaDomingo + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada + freteTerreo + freteElevador + freteEscadas;

    // Lógica de exibição que controla o "a confirmar"
    if (subtotalGeralItens === 0 && valorFinalCalculado === 0 && !$('#ajuste_manual_valor_final').is(':checked')) {
        $('#subtotal_geral_itens').text('A confirmar');
        $('#valor_final_display').val('').attr('placeholder', 'A confirmar');
    } else {
        $('#subtotal_geral_itens').text(formatCurrency(subtotalGeralItens));
        $('#valor_final_display').val(formatCurrency(valorFinalCalculado));
    }
}
    
    // =========================================================================
    // >>>>> CÓDIGO RESTAURADO AQUI <<<<<
    // Este é o bloco que conecta os botões da seção de itens às suas funções.
    // =========================================================================
    
    // --- EVENTOS PARA SEÇÃO DE ITENS ---
    $('#busca_produto, #busca_categoria_produto').on('keyup change', carregarSugestoesProdutos);

// AJUSTE GERAL: LÓGICA DE CLIQUE INTELIGENTE (ZOOM vs ADICIONAR) E CONFIRMAÇÃO DE DUPLICADOS
$('#sugestoes_produtos').on('click', '.item-sugestao-produto', function(e) {
    e.preventDefault();

    // --- Parte 1: Lógica do Zoom Inteligente ---
    // Verifica se o elemento clicado foi a imagem ou um ícone dentro dela.
    if ($(e.target).closest('.foto-produto-sugestao').length > 0) {
        var fotoUrl = $(this).data('foto-completa');
        var nomeProduto = $(this).data('nome');
        
        if (fotoUrl) {
            Swal.fire({
                title: nomeProduto,
                imageUrl: fotoUrl,
                imageAlt: 'Foto ampliada de ' + nomeProduto,
                imageWidth: '90%',
                confirmButtonText: 'Fechar'
            });
        }
        // IMPORTANTE: Encerra a função aqui para não adicionar o item.
        // A lista de sugestões permanecerá visível.
        return; 
    }

    // --- Parte 2: Lógica de Adicionar o Item (com confirmação de duplicidade) ---
    var produtoId = $(this).data('id');
    var produtoJaExiste = false;
    $('#tabela_itens_orcamento .produto_id').each(function() {
        if ($(this).val() == produtoId) {
            produtoJaExiste = true;
            return false; // Interrompe o loop
        }
    });

    // Função para adicionar o item, será chamada diretamente ou após confirmação
    const adicionarItem = () => {
        var produto = {
            id: produtoId,
            nome_produto: $(this).data('nome'),
            preco_locacao: $(this).data('preco'),
            foto_path_completo: $(this).data('foto-completa')
        };
        adicionarLinhaItemTabela(produto, 'PRODUTO');
        $('#busca_produto').val('').focus();
        $('#sugestoes_produtos').empty().hide();
    };

    if (produtoJaExiste) {
        // Se o produto já existe, PERGUNTA ao invés de bloquear
        Swal.fire({
            title: 'Produto Repetido',
            text: "Este item já foi adicionado. Deseja adicioná-lo novamente em outra linha?",
            icon: 'question', // Ícone de pergunta
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, adicionar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Se o usuário confirmar, adiciona o item
                adicionarItem();
            }
        });
    } else {
        // Se não existe, adiciona diretamente
        adicionarItem();
    }
});


    $('#btn_adicionar_titulo_secao').click(function() {
        adicionarLinhaItemTabela(null, 'CABECALHO_SECAO');
    });

    $('#btn_adicionar_item_manual').click(function() {
        adicionarLinhaItemTabela(null, 'PRODUTO');
    });

    $('#tabela_itens_orcamento').on('click', '.btn_remover_item', function() {
        $(this).closest('tr').remove();
        atualizarOrdemDosItens();
        calcularTotaisOrcamento();
    });

    $('#tabela_itens_orcamento').on('click', '.btn_obs_item', function() {
        var $row = $(this).closest('tr');
        $row.find('.observacoes_item_label, .observacoes_item_input').toggle();
        if ($row.find('.observacoes_item_input').is(':visible')) {
            $row.find('.observacoes_item_input').focus();
        }
    });
    // =========================================================================
    // >>>>> FIM DO CÓDIGO RESTAURADO <<<<<
    // =========================================================================


    $(document).on('change keyup blur', '.item-qtd, .item-valor-unitario, .desconto_item, #desconto_total, .taxa-frete-input', function() {
        calcularTotaisOrcamento();
    });

    $('.btn-usar-padrao').on('click', function() {
        var $button = $(this);
        var targetInputId = $button.data('target-input');
        var $targetInput = $('#' + targetInputId);
        if (!$targetInput.length) { return; }
        var valorSugeridoStr = $targetInput.data('valor-padrao');
        if (typeof valorSugeridoStr === 'undefined') { return; }
        var valorNumerico = unformatCurrency(valorSugeridoStr.toString());
        $targetInput.val(formatCurrency(valorNumerico));
        var targetCheckboxId = $button.data('target-checkbox');
        if (targetCheckboxId) { $('#' + targetCheckboxId).prop('checked', true); }
        $targetInput.trigger('change');
    });

    $('.taxa-frete-input').on('blur', function() {
        var $input = $(this);
        var $checkbox = $('.taxa-frete-checkbox[data-target-input="' + $input.attr('id') + '"]');
        if ($checkbox.length) {
            if (unformatCurrency($input.val()) > 0) { $checkbox.prop('checked', true); } 
            else { $checkbox.prop('checked', false); $input.val(''); }
        }
    });

    $('.taxa-frete-checkbox').on('change', function() {
        var $checkbox = $(this);
        var $targetInput = $('#' + $checkbox.data('target-input'));
        if ($targetInput.length) {
            if ($checkbox.is(':checked')) {
                if (unformatCurrency($targetInput.val()) <= 0) {
                    var valorPadraoStr = $targetInput.data('valor-padrao');
                    if (typeof valorPadraoStr !== 'undefined') {
                        $targetInput.val(formatCurrency(unformatCurrency(valorPadraoStr.toString())));
                    }
                }
            } else { $targetInput.val(''); }
            $targetInput.trigger('change');
        }
    });

    if (typeof $.fn.select2 === 'function') {
        $('#cliente_id').select2({
            theme: 'bootstrap4',
            language: 'pt-BR',
            placeholder: 'Digite para buscar ou clique para ver os recentes',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: 'create.php?ajax=buscar_clientes',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { termo: params.term || '' }; },
                processResults: function (data) {
                    return { results: $.map(data, function (cliente) {
                        return { id: cliente.id, text: cliente.nome + (cliente.cpf_cnpj ? ' - ' + cliente.cpf_cnpj : ''), clienteData: cliente };
                    }) };
                },
                cache: true
            }
        }).on('select2:select', function (e) {
            var data = e.params.data.clienteData;
            if (data) {
                $('#cliente_telefone').val(data.telefone || '');
                $('#cliente_email').val(data.email || '');
                $('#cliente_cpf_cnpj').val(data.cpf_cnpj || '');
                $('#cliente_endereco').val(data.endereco || '');
                $('#cliente_cidade').val(data.cidade || '');
                
            }
            $('#btnUsarEnderecoCliente').show(); // <-- MOSTRA ENDERECO CLIENTE AO CLICAR BOTAO USAR END CLIENTE
            $('#cliente_id').on('select2:unselect select2:clear', function(e) {
    $('#btnUsarEnderecoCliente').hide(); // <-- ADICIONE ESTE NOVO BLOCO
});
        });
    }

    if (typeof $.fn.datepicker === 'function') {
        $('.datepicker').datepicker({ format: 'dd/mm/yyyy', language: 'pt-BR', autoclose: true, todayHighlight: true, orientation: "bottom auto" });
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
                    $('#data_validade_display').text('Validade até: ' + dia + '/' + mes + '/' + ano);
                    $('#data_validade_calculada_hidden').val(ano + '-' + mes + '-' + dia);
                    return;
                }
            }
        }
        $('#data_validade_display').text('');
        $('#data_validade_calculada_hidden').val('');
    }
    $('#data_orcamento, #validade_dias').on('change keyup blur dp.change', calcularDataValidade);

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
                    if (dataObj.getDay() === 0 || dataObj.getDay() === 6) { displayEl.addClass('text-danger'); } 
                    else { displayEl.addClass('text-success'); }
                    return;
                }
            }
        }
    }
    $('#data_evento').on('change dp.change', function() { exibirDiaSemana(this, '#dia_semana_evento'); }).trigger('change');
    $('#data_entrega').on('change dp.change', function() { exibirDiaSemana(this, '#dia_semana_entrega'); }).trigger('change');
    $('#data_devolucao_prevista').on('change dp.change', function() { exibirDiaSemana(this, '#dia_semana_devolucao'); }).trigger('change');
    
    $('#btnUsarEnderecoCliente').on('click', function() {
    // 1. Pega os dados do cliente selecionado
    var clienteSelecionado = $('#cliente_id').select2('data');

    // 2. Garante que há um cliente selecionado
    if (!clienteSelecionado || clienteSelecionado.length === 0 || !clienteSelecionado[0].clienteData) {
        toastr.info('Por favor, selecione um cliente primeiro.', 'Aviso');
        return;
    }
    
    let data = clienteSelecionado[0].clienteData;
    let enderecoCompleto = (data.endereco || '') + (data.cidade ? ((data.endereco ? ', ' : '') + data.cidade) : '');
    let localEventoInput = $('#local_evento');

    // 3. NOVA LÓGICA DE TOGGLE (LIGA/DESLIGA)
    // Se o campo já contém o endereço EXATO do cliente, o clique vai LIMPAR o campo.
    if (localEventoInput.val().trim() === enderecoCompleto.trim()) {
        localEventoInput.val(''); // Limpa o campo
    } else {
        // Senão (se o campo está vazio ou tem outra coisa), ele vai PREENCHER com o endereço.
        localEventoInput.val(enderecoCompleto.trim());
    }
});

    $('#formNovoOrcamento').on('keydown', function(e) {
        if (e.keyCode === 13) {
            if (!$(e.target).is('textarea') && !$(e.target).is('[type=submit]')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // ... (resto do seu código, como a parte do modal, etc) ...

    calcularDataValidade();
    calcularTotaisOrcamento();
    
    function atualizarOrdemDosItens() {
        $('#tabela_itens_orcamento tbody tr').each(function(index) {
            $(this).attr('data-index', index + 1);
            $(this).find('input[name="ordem[]"]').val(index + 1);
        });
    }

    $('#tabela_itens_orcamento tbody').sortable({
        handle: '.drag-handle',
        placeholder: 'sortable-placeholder',
        helper: function(e, ui) { ui.children().each(function() { $(this).width($(this).width()); }); return ui; },
        stop: function(event, ui) { atualizarOrdemDosItens(); }
    }).disableSelection();

    $('head').append('<style>.sortable-placeholder { height: 50px; background-color: #f0f8ff; border: 2px dashed #cce5ff; }</style>');

    // --- INÍCIO DO CÓDIGO FINAL E SINCRONIZADO ---

// ▼▼▼ COLE ESTES DOIS BLOCOS NOVOS NO LUGAR ▼▼▼

// Bloco 1: O evento de change, agora limpo e correto.
$('#ajuste_manual_valor_final, #aplicar_desconto_geral').on('change', function() {
    const isChecked = $(this).is(':checked');
    $('#ajuste_manual_valor_final, #aplicar_desconto_geral').prop('checked', isChecked);

    const $campoDesconto = $('#desconto_total');
    const $divMotivo = $('#campo_motivo_ajuste');
    const $inputMotivo = $('#motivo_ajuste');

    if (isChecked) {
        $campoDesconto.prop('disabled', false);
        $divMotivo.slideDown();
        $inputMotivo.prop('disabled', false);
        $campoDesconto.focus();
    } else {
        $campoDesconto.prop('disabled', true).val('');
        $divMotivo.slideUp();
        $inputMotivo.prop('disabled', true).val('');
    }
    calcularTotaisOrcamento();
});


// Bloco 2: A lógica do "a confirmar", agora no lugar certo para rodar na carga da página.
// CÓDIGO PARA GARANTIR O "A CONFIRMAR" NA CARGA DA PÁGINA
$('.taxa-frete-input').each(function() {
    var valorNumerico = unformatCurrency($(this).val());
    if (valorNumerico === 0) {
        $(this).val(''); // Força o campo a ficar vazio para o placeholder aparecer
    }
});

// Roda o cálculo final uma última vez para garantir que todos os totais estejam corretos
calcularTotaisOrcamento();

// ▲▲▲ FIM DOS BLOCOS PARA COLAR ▲▲▲

});
JS;

include_once __DIR__ . '/../includes/footer.php';
?>