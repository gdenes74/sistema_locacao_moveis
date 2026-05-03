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
require_once __DIR__ . '/../../models/EstoqueMovimentacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection(); // Conexão PDO

$clienteModel = new Cliente($db);
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db); // Instância do nosso model ajustado
$estoqueModel = new EstoqueMovimentacao($db);
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

       $sql = "SELECT p.id, p.codigo, p.nome_produto, p.descricao_detalhada, p.preco_locacao, p.quantidade_total, p.foto_path, p.tipo_produto
        FROM produtos p";

$conditions = [];
$executeParams = [];

// Não mostrar componentes internos na busca operacional de orçamento.
// Ex.: Pufe Estrutura, Capa Pufe Azul, Recheio Almofada etc.
// Eles continuam existindo no banco e continuam sendo usados no cálculo de estoque composto.
$conditions[] = "(p.tipo_produto IS NULL OR p.tipo_produto IN ('SIMPLES', 'COMPOSTO'))";

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
// ✅ AJAX - para verificar estoque / disponibilidade temporal
if (isset($_GET['ajax']) && $_GET['ajax'] == 'verificar_estoque') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $produto_id = isset($_GET['produto_id']) ? (int) $_GET['produto_id'] : 0;
        $quantidade = isset($_GET['quantidade']) ? (int) $_GET['quantidade'] : 0;
        $data_inicio = $_GET['data_inicio'] ?? null;
        $hora_inicio = $_GET['hora_inicio'] ?? null;
        $turno_inicio = $_GET['turno_inicio'] ?? null;
        $data_fim = $_GET['data_fim'] ?? null;
        $hora_fim = $_GET['hora_fim'] ?? null;
        $turno_fim = $_GET['turno_fim'] ?? null;
        $ignorar_pedido_id = isset($_GET['ignorar_pedido_id']) ? (int) $_GET['ignorar_pedido_id'] : null;

        if ($produto_id <= 0) {
            echo json_encode([
                'success' => false,
                'disponivel' => false,
                'erro' => 'ID do produto inválido.'
            ]);
            exit;
        }

        $resultado = $estoqueModel->consultarDisponibilidadePeriodo(
            $produto_id,
            $data_inicio,
            $hora_inicio,
            $turno_inicio,
            $data_fim,
            $hora_fim,
            $turno_fim,
            max(0, $quantidade),
            $ignorar_pedido_id
        );

        if (!isset($resultado['estoque_disponivel']) && isset($resultado['livre_periodo'])) {
            $resultado['estoque_disponivel'] = $resultado['livre_periodo'];
        }

        echo json_encode($resultado);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'disponivel' => false,
            'erro' => $e->getMessage()
        ]);
        exit;
    }
}

// --- Bloco AJAX para salvar cliente do modal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'salvar_cliente_modal') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $clienteModal = new Cliente($db);

        $clienteModal->nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $clienteModal->telefone = trim(filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS) ?? '') ?: null;
        $clienteModal->email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $clienteModal->cpf_cnpj = trim(filter_input(INPUT_POST, 'cpf_cnpj', FILTER_SANITIZE_SPECIAL_CHARS) ?? '') ?: null;
        $clienteModal->endereco = trim(filter_input(INPUT_POST, 'endereco', FILTER_SANITIZE_SPECIAL_CHARS) ?? '') ?: null;
        $clienteModal->cidade = trim(filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_SPECIAL_CHARS) ?? '') ?: null;
        $clienteModal->observacoes = trim(filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS) ?? '') ?: null;

        if ($clienteModal->nome === '') {
            throw new Exception("O nome do cliente é obrigatório.");
        }

        if ($clienteModal->email === false && !empty($_POST['email'])) {
            throw new Exception("O formato do e-mail informado é inválido.");
        }

        if (!$clienteModal->create()) {
            throw new Exception("Não foi possível cadastrar o cliente.");
        }

        echo json_encode([
            'success' => true,
            'message' => "Cliente cadastrado com sucesso!",
            'cliente' => [
                'id' => $clienteModal->id,
                'nome' => $clienteModal->nome,
                'telefone' => $clienteModal->telefone,
                'email' => $clienteModal->email,
                'cpf_cnpj' => $clienteModal->cpf_cnpj,
                'endereco' => $clienteModal->endereco,
                'cidade' => $clienteModal->cidade,
                'observacoes' => $clienteModal->observacoes
            ]
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
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

                    $item_data['quantidade'] = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 0;

                    if ($item_data['quantidade'] <= 0) {
                        $nomeProdutoErro = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : 'Produto sem nome';
                        throw new Exception("Há produto com quantidade zero: " . $nomeProdutoErro . ". Ajuste a quantidade ou remova a linha antes de salvar.");
                    }

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

                        </div>

                        <div id="painel_consulta_disponibilidade" class="card painel-disponibilidade painel-neutro mt-2" style="display:none;">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0"><i class="fas fa-bolt mr-2"></i>Disponibilidade Temporal</h3>
                                <button type="button" class="btn btn-xs btn-light btn-fechar-painel-disponibilidade" title="Fechar painel">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="card-body p-2 small" id="conteudo_consulta_disponibilidade">
                                <span class="text-white-50">Clique no resumo de uma linha para visualizar a consulta temporal.</span>
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

                    #painel_consulta_disponibilidade {
                        position: relative;
                        width: 100%;
                        margin-top: 12px;
                        border-radius: 14px;
                        overflow: hidden;
                        border: 2px solid rgba(0,0,0,0.08);
                        box-shadow: 0 14px 30px rgba(0,0,0,0.16);
                    }
                    #painel_consulta_disponibilidade .card-header,
                    #painel_consulta_disponibilidade .card-body {
                        color: #fff;
                    }
                    #painel_consulta_disponibilidade .card-body {
                        max-height: none;
                        overflow: visible;
                    }
                    #painel_consulta_disponibilidade.painel-neutro .card-header,
                    #painel_consulta_disponibilidade.painel-neutro .card-body {
                        background: linear-gradient(135deg, #4b5563 0%, #1f2937 100%);
                    }
                    #painel_consulta_disponibilidade.painel-ok .card-header,
                    #painel_consulta_disponibilidade.painel-ok .card-body {
                        background: linear-gradient(135deg, #0b6fd3 0%, #10b981 100%);
                    }
                    #painel_consulta_disponibilidade.painel-atencao .card-header,
                    #painel_consulta_disponibilidade.painel-atencao .card-body {
                        background: linear-gradient(135deg, #c77700 0%, #ff9f1c 100%);
                    }
                    #painel_consulta_disponibilidade.painel-indisponivel .card-header,
                    #painel_consulta_disponibilidade.painel-indisponivel .card-body {
                        background: linear-gradient(135deg, #a10f2b 0%, #e11d48 100%);
                    }
                    .painel-status-badge {
                        display: inline-block;
                        padding: 4px 10px;
                        border-radius: 999px;
                        font-size: 0.75rem;
                        font-weight: 800;
                        letter-spacing: 0.04em;
                        background: rgba(255,255,255,0.18);
                        border: 1px solid rgba(255,255,255,0.28);
                    }
                    .painel-disponibilidade .painel-box {
                        background: rgba(255,255,255,0.14);
                        border: 1px solid rgba(255,255,255,0.18);
                        border-radius: 10px;
                        padding: 8px 10px;
                        margin-bottom: 8px;
                    }
                    .painel-disponibilidade-grid {
                        display: grid;
                        grid-template-columns: repeat(4, minmax(0, 1fr));
                        gap: 8px;
                    }
                    .painel-disponibilidade .painel-box strong {
                        display: block;
                        font-size: 0.72rem;
                        text-transform: uppercase;
                        letter-spacing: 0.03em;
                        opacity: 0.95;
                        margin-bottom: 3px;
                    }
                    .painel-disponibilidade .painel-valor-principal {
                        font-size: 1.15rem;
                        font-weight: 800;
                        line-height: 1.1;
                    }
                    .painel-disponibilidade .painel-subtexto {
                        font-size: 0.78rem;
                        opacity: 0.92;
                        margin-top: 2px;
                    }
                    .btn-fechar-painel-disponibilidade {
                        border-radius: 999px;
                        padding: 2px 8px;
                        font-size: 0.75rem;
                    }
                    .disponibilidade-contexto {
                        cursor: pointer;
                    }
                    @media (max-width: 991.98px) {
                        .painel-disponibilidade-grid {
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                        }
                    }
                    @media (max-width: 575.98px) {
                        .painel-disponibilidade-grid {
                            grid-template-columns: 1fr;
                        }
                    }
                    .disponibilidade-contexto {
                        display: block;
                        margin-top: 8px;
                        padding: 8px 10px;
                        border-radius: 8px;
                        font-size: 0.82rem;
                        font-weight: 700;
                        line-height: 1.35;
                    }
                    .disponibilidade-contexto.status-ok {
                        background: #e8f4ff;
                        border: 1px solid #1d78d6;
                        color: #0a4d8c;
                    }
                    .item-orcamento-row.row-status-ok td {
                        background: #f4fbff !important;
                    }
                    .disponibilidade-contexto.status-atencao {
                        background: #fff5df;
                        border: 1px solid #ff9f1c;
                        color: #9a5b00;
                    }
                    .item-orcamento-row.row-status-atencao td {
                        background: #fffaf0 !important;
                    }
                    .disponibilidade-contexto.status-indisponivel {
                        background: #ffe8ee;
                        border: 1px solid #e11d48;
                        color: #a10f2b;
                    }
                    .item-orcamento-row.row-status-indisponivel td {
                        background: #fff4f6 !important;
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
                            <div class="form-group"><label for="modal_cliente_telefone">Telefone</label><input type="text"
                                    class="form-control telefone" id="modal_cliente_telefone" name="telefone">
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label for="modal_cliente_endereco">Endereço (Rua, Nº, Bairro)</label><input
                            type="text" class="form-control" id="modal_cliente_endereco" name="endereco"></div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group"><label for="modal_cliente_cidade">Cidade</label><input type="text"
                                    class="form-control" id="modal_cliente_cidade" name="cidade" value="Porto Alegre">
                            </div>
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


    function escapeHtml(text) {
        if (text === null || text === undefined) { return ''; }
        return $('<div>').text(text).html();
    }

    function obterPeriodoConsultaAtual() {
        return {
            data_inicio: $('#data_entrega').val() || '',
            hora_inicio: $('#hora_entrega').val() || '',
            turno_inicio: $('#turno_entrega').val() || '',
            data_fim: $('#data_devolucao_prevista').val() || '',
            hora_fim: $('#hora_devolucao').val() || '',
            turno_fim: $('#turno_devolucao').val() || ''
        };
    }

    function limparStatusLinhaDisponibilidade($row) {
        $row.removeClass('table-danger table-warning table-success row-status-ok row-status-atencao row-status-indisponivel');
    }

    function obterClasseStatusDisponibilidade(response) {
        if (!response || response.success === false || response.disponivel === false) {
            return 'indisponivel';
        }
        if (response.nivel_alerta === 'atencao') {
            return 'atencao';
        }
        return 'ok';
    }

    function obterTextoStatusDisponibilidade(response) {
        const classe = obterClasseStatusDisponibilidade(response);
        if (classe === 'indisponivel') return 'INDISPONÍVEL';
        if (classe === 'atencao') return 'ATENÇÃO';
        return 'DISPONÍVEL';
    }

    function montarResumoDisponibilidadeHtml(response) {
        if (!response) {
            return '<span class="text-white">Não foi possível consultar a disponibilidade.</span>';
        }

        const estoqueTotal = parseInt(response.estoque_total || 0, 10);
        const comprometido = parseInt(response.comprometido_periodo || 0, 10);
        const reservadoAtual = parseInt((response.reservado_orcamento_atual ?? response.quantidade_solicitada ?? 0), 10);
        const livreApos = parseInt(response.livre_apos_orcamento !== undefined ? response.livre_apos_orcamento : 0, 10);
        const faltante = parseInt(response.faltante_orcamento || 0, 10);
        const statusTexto = obterTextoStatusDisponibilidade(response);

        let html = `<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap"><span class="painel-status-badge">${statusTexto}</span><small class="ml-2" style="opacity:.9;">Clique no resumo da linha para reabrir este painel.</small></div>`;
        html += '<div class="painel-disponibilidade-grid">';
        html += `<div class="painel-box"><strong>Estoque total</strong><div class="painel-valor-principal">${estoqueTotal}</div><div class="painel-subtexto">Quantidade cadastrada</div></div>`;
        html += `<div class="painel-box"><strong>Pedidos no período</strong><div class="painel-valor-principal">${comprometido}</div><div class="painel-subtexto">Já comprometido fora deste orçamento</div></div>`;
        html += `<div class="painel-box"><strong>Neste orçamento</strong><div class="painel-valor-principal">${reservadoAtual}</div><div class="painel-subtexto">Incluindo esta linha e as repetidas</div></div>`;
        html += `<div class="painel-box"><strong>Livre após orçamento</strong><div class="painel-valor-principal">${livreApos}</div>${faltante > 0 ? `<div class="painel-subtexto font-weight-bold">Faltando ${faltante}</div>` : '<div class="painel-subtexto">Saldo projetado</div>'}</div>`;
        html += '</div>';

        if (response.consulta_periodo_valida === false) {
            html += '<div class="painel-box mt-2">Informe data de entrega e devolução para análise temporal completa.</div>';
        }

        if (response.conflitos && response.conflitos.length > 0) {
            let conflitosHtml = response.conflitos.map(function(item) {
                return `<li><strong>${escapeHtml(item.cliente || 'Cliente')}</strong> — ${parseInt(item.quantidade || 0, 10)} un. <span style="opacity:.85;">(${escapeHtml(item.inicio_formatado || '')} → ${escapeHtml(item.fim_formatado || '')})</span></li>`;
            }).join('');
            html += `<div class="painel-box mt-2"><strong>Pedidos confirmados no período</strong><ul class="mb-0 pl-3 mt-1">${conflitosHtml}</ul></div>`;
        }

        let extras = [];
        if (response.ultimo_retorno) {
            extras.push(`<div><strong>Último retorno:</strong> ${escapeHtml(response.ultimo_retorno.cliente || 'Cliente')} <span style="opacity:.85;">(${escapeHtml(response.ultimo_retorno.fim_formatado || '')})</span></div>`);
        }
        if (response.proxima_saida) {
            extras.push(`<div><strong>Próxima saída:</strong> ${escapeHtml(response.proxima_saida.cliente || 'Cliente')} <span style="opacity:.85;">(${escapeHtml(response.proxima_saida.inicio_formatado || '')})</span></div>`);
        }
        if (extras.length) {
            html += `<div class="painel-box mt-2">${extras.join('<div class="mt-1"></div>')}</div>`;
        }
if (response.produto_composto && response.componentes && response.componentes.length > 0) {
    let componentesHtml = response.componentes.map(function(comp) {
        const nome = comp.nome_produto || comp.produto_nome || comp.nome || 'Componente';
        const estoqueTotal = parseInt(comp.estoque_total || comp.quantidade_total || 0, 10);
        const livrePeriodo = parseInt(
            comp.livre_periodo !== undefined
                ? comp.livre_periodo
                : (comp.estoque_disponivel !== undefined ? comp.estoque_disponivel : estoqueTotal),
            10
        );
        const qtdPorUnidade = parseFloat(comp.quantidade_por_unidade || comp.quantidade || 1);

        return `<li>
            <strong>${escapeHtml(nome)}</strong>
            — usa ${qtdPorUnidade} por unidade
            · estoque total: ${estoqueTotal}
            · livre no período: ${livrePeriodo}
        </li>`;
    }).join('');

    html += `<div class="painel-box mt-2">
        <strong>Componentes do produto composto</strong>
        <ul class="mb-0 pl-3 mt-1">${componentesHtml}</ul>
    </div>`;
}
        if (response.observacoes_produto) {
            html += `<div class="painel-box mt-2"><strong>Observações do produto</strong><div class="mt-1">${escapeHtml(response.observacoes_produto)}</div></div>`;
        }

        if (response.alertas && response.alertas.length > 0) {
            let alertasHtml = response.alertas.map(function(alerta) {
                return `<li>${escapeHtml(alerta)}</li>`;
            }).join('');
            html += `<div class="painel-box mb-0 mt-2"><strong>Alertas</strong><ul class="mb-0 pl-3 mt-1">${alertasHtml}</ul></div>`;
        }

        return html;
    }

    function montarResumoLinhaDisponibilidade(response) {
        if (!response) {
            return '';
        }
        const comprometido = parseInt(response.comprometido_periodo || 0, 10);
        const reservadoAtual = parseInt((response.reservado_orcamento_atual ?? response.quantidade_solicitada ?? 0), 10);
        const livreApos = parseInt(response.livre_apos_orcamento !== undefined ? response.livre_apos_orcamento : 0, 10);
        const statusTexto = obterTextoStatusDisponibilidade(response);
        return `<strong>${statusTexto}</strong> · Pedidos: ${comprometido} · Neste orçamento: ${reservadoAtual} · Livre após: ${livreApos} <span class="ml-1 text-muted">(abrir painel)</span>`;
    }

    function atualizarPainelConsultaDisponibilidade(nomeProduto, response) {
        const $painel = $('#painel_consulta_disponibilidade');
        const $conteudo = $('#conteudo_consulta_disponibilidade');
        const classe = obterClasseStatusDisponibilidade(response);

        $painel.removeClass('painel-neutro painel-ok painel-atencao painel-indisponivel').addClass('painel-' + classe);

        let titulo = nomeProduto ? `<div class="font-weight-bold mb-2" style="font-size:1rem;">${escapeHtml(nomeProduto)}</div>` : '';
        $conteudo.html(titulo + montarResumoDisponibilidadeHtml(response));
        $painel.show();
    }

    function aplicarContextoDisponibilidadeNaLinha($row, response) {
        if (!$row || !$row.length) { return; }

        const $contexto = $row.find('.disponibilidade-contexto');
        if (!$contexto.length) { return; }

        limparStatusLinhaDisponibilidade($row);
        const classe = obterClasseStatusDisponibilidade(response);
        $contexto.removeClass('status-ok status-atencao status-indisponivel').addClass('status-' + classe).html(montarResumoLinhaDisponibilidade(response)).show();
        $row.data('disponibilidade-response', response);

        if (classe === 'indisponivel') {
            $row.addClass('table-danger row-status-indisponivel');
        } else if (classe === 'atencao') {
            $row.addClass('table-warning row-status-atencao');
        } else {
            $row.addClass('table-success row-status-ok');
        }
    }

function consultarDisponibilidadeAjax(produtoId, quantidade, callbackSucesso, callbackErro) {
        const periodo = obterPeriodoConsultaAtual();

        $.ajax({
            url: 'create.php',
            type: 'GET',
            dataType: 'json',
            data: {
                ajax: 'verificar_estoque',
                produto_id: produtoId,
                quantidade: quantidade,
                data_inicio: periodo.data_inicio,
                hora_inicio: periodo.hora_inicio,
                turno_inicio: periodo.turno_inicio,
                data_fim: periodo.data_fim,
                hora_fim: periodo.hora_fim,
                turno_fim: periodo.turno_fim
            },
            success: function(response) {
                if (typeof callbackSucesso === 'function') {
                    callbackSucesso(response);
                }
            },
            error: function(xhr) {
                if (typeof callbackErro === 'function') {
                    callbackErro(xhr);
                }
            }
        });
    }

    function atualizarContextoDisponibilidadeLinha($row, exibirAlertaSeIndisponivel = false) {
        const produtoId = parseInt($row.find('.produto_id').val(), 10) || 0;
        if (produtoId <= 0) { return; }

        let quantidade = 0;
        $('#tabela_itens_orcamento .produto_id').each(function() {
            if ($(this).val() == produtoId) {
                quantidade += parseInt($(this).closest('tr').find('.quantidade_item').val(), 10) || 0;
            }
        });
        if (quantidade < 0) { quantidade = 0; }

        const nomeProduto = $row.find('.nome_produto_display').val() || '';

        consultarDisponibilidadeAjax(produtoId, quantidade, function(response) {
            aplicarContextoDisponibilidadeNaLinha($row, response);

            if (exibirAlertaSeIndisponivel && response && response.disponivel === false) {
                Swal.fire({
                    title: 'Atenção no período consultado',
                    html: montarResumoDisponibilidadeHtml(response),
                    icon: 'warning',
                    confirmButtonText: 'Entendi'
                });
            }
        }, function() {
            const response = {
                success: false,
                disponivel: false,
                alertas: ['Erro ao consultar disponibilidade temporal.']
            };
            aplicarContextoDisponibilidadeNaLinha($row, response);
        });
    }

    function revalidarTodasAsLinhasDisponibilidade() {
        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            const $row = $(this);
            if (($row.data('tipo-linha') || '') === 'PRODUTO' && $row.find('.produto_id').val()) {
                atualizarContextoDisponibilidadeLinha($row, false);
            }
        });
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
            var quantidadeDefault = 0; var descontoDefault = 0;
            var subtotalDefault = quantidadeDefault * (precoUnitarioDefault - descontoDefault);
            var imagemHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">` : '';
            htmlLinha = `<tr class="item-orcamento-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #ffffff !important;"><td>${imagemHtml}<input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}><input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}"><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}"><div class="disponibilidade-contexto mt-2" style="display:none;"></div><small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small><input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do item"></td><td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeDefault}" min="0" style="width: 70px;"></td><td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoUnitarioDefault.toFixed(2).replace('.', ',')}"></td><td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoDefault.toFixed(2).replace('.', ',')}"></td><td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault).replace('R$ ', '')}</td><td><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button> <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button></td></tr>`;
        } else if (tipoLinha === 'CABECALHO_SECAO') {
            htmlLinha = `<tr class="item-orcamento-row item-titulo-secao" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #e7f1ff !important;"><td colspan="5"><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_titulo_secao" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);"><input type="hidden" name="produto_id[]" value=""><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="quantidade[]" value="0"><input type="hidden" name="tipo_item[]" value=""><input type="hidden" name="valor_unitario[]" value="0.00"><input type="hidden" name="desconto_item[]" value="0.00"><input type="hidden" name="observacoes_item[]" value=""></td><td><button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button></td></tr>`;
        }
        if (htmlLinha) {
            $('#tabela_itens_orcamento tbody').append(htmlLinha);
            var $novaLinha = $('#tabela_itens_orcamento tbody tr:last-child');
            if (tipoLinha === 'CABECALHO_SECAO') {
                $novaLinha.find('.nome_titulo_secao').focus();
            } else if (tipoLinha === 'PRODUTO' && dadosItem && dadosItem.id) {
                atualizarContextoDisponibilidadeLinha($novaLinha, false);
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
    
    // ✅ NOVA VALIDAÇÃO DE ESTOQUE AQUI
    verificarEstoqueAntes(produto);
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
        revalidarTodasAsLinhasDisponibilidade();
    });

    $('#tabela_itens_orcamento').on('click', '.btn_obs_item', function() {
        var $row = $(this).closest('tr');
        $row.find('.observacoes_item_label, .observacoes_item_input').toggle();
        if ($row.find('.observacoes_item_input').is(':visible')) {
            $row.find('.observacoes_item_input').focus();
        }
    });

    $('#tabela_itens_orcamento').on('click', '.disponibilidade-contexto', function() {
        var $row = $(this).closest('tr');
        var response = $row.data('disponibilidade-response');
        var nomeProduto = $row.find('.nome_produto_display').val() || 'Produto';
        if (response) {
            atualizarPainelConsultaDisponibilidade(nomeProduto, response);
            $('html, body').animate({ scrollTop: $('#painel_consulta_disponibilidade').offset().top - 90 }, 200);
        }
    });

    $(document).on('click', '.btn-fechar-painel-disponibilidade', function() {
        $('#painel_consulta_disponibilidade').hide();
    });
    // =========================================================================
    // >>>>> FIM DO CÓDIGO RESTAURADO <<<<<
    // =========================================================================
function verificarEstoqueAntes(produto) {
    adicionarLinhaItemTabela(produto, 'PRODUTO');
    $('#busca_produto').val('').focus();
    $('#sugestoes_produtos').empty().hide();
}

// ✅ FUNÇÃO MELHORADA: Evita mensagens duplicadas e considera o período consultado
function validarEstoqueQuantidade($row) {
    var produtoId = $row.find('.produto_id').val();
    var quantidadeAtual = parseInt($row.find('.quantidade_item').val()) || 0;

    if (!produtoId) return;

    // Calcula a quantidade total deste produto somando linhas repetidas no orçamento atual
    var quantidadeTotal = 0;
    $('#tabela_itens_orcamento .produto_id').each(function() {
        if ($(this).val() == produtoId) {
            var $outraRow = $(this).closest('tr');
            quantidadeTotal += parseInt($outraRow.find('.quantidade_item').val(), 10) || 0;
        }
    });

    consultarDisponibilidadeAjax(produtoId, quantidadeTotal, function(response) {
        revalidarTodasAsLinhasDisponibilidadeProduto(produtoId, response);

        if (response.success === false) {
            return;
        }

        const chaveAlerta = [produtoId, quantidadeTotal, $('#data_entrega').val(), $('#hora_entrega').val(), $('#turno_entrega').val(), $('#data_devolucao_prevista').val(), $('#hora_devolucao').val(), $('#turno_devolucao').val()].join('|');
        const jaAlertado = $row.data('alerta-indisponivel-chave');

        if (!response.disponivel) {
            if (jaAlertado !== chaveAlerta) {
                $row.data('alerta-indisponivel-chave', chaveAlerta);
                Swal.fire({
                    title: 'Quantidade indisponível no período',
                    html: montarResumoDisponibilidadeHtml(response),
                    icon: 'warning',
                    width: 760,
                    confirmButtonText: 'Entendi'
                });
            }
        } else {
            $row.removeData('alerta-indisponivel-chave');
        }
    });
}

function revalidarTodasAsLinhasDisponibilidadeProduto(produtoId, responseCompartilhada) {
    $('#tabela_itens_orcamento .produto_id').each(function() {
        if ($(this).val() == produtoId) {
            aplicarContextoDisponibilidadeNaLinha($(this).closest('tr'), responseCompartilhada);
        }
    });
}

// ✅ EVENTO INTELIGENTE: Valida automaticamente quando para de digitar
$('#tabela_itens_orcamento').on('input change', '.quantidade_item', function(e) {
    var $input = $(this);
    var $row = $input.closest('tr');

    clearTimeout($input.data('validacao-timeout'));

    $input.data('validacao-timeout', setTimeout(function() {
        validarEstoqueQuantidade($row);
    }, 800));
});

// Revalida todas as linhas quando o período logístico muda
$('#data_entrega, #hora_entrega, #turno_entrega, #data_devolucao_prevista, #hora_devolucao, #turno_devolucao').on('change keyup blur', function() {
    $('#tabela_itens_orcamento tbody tr.item-orcamento-row').removeData('alerta-indisponivel-chave');
    revalidarTodasAsLinhasDisponibilidade();
});

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

    $('#btnSalvarClienteModal').on('click', function() {
        var $btn = $(this);
        var $feedback = $('#modalClienteFeedback');
        var nome = $('#modal_cliente_nome').val().trim();

        $feedback.html('');

        if (nome === '') {
            $feedback.html('<div class="alert alert-danger mb-0">O nome do cliente é obrigatório.</div>');
            $('#modal_cliente_nome').focus();
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        var dados = $('#formNovoClienteModal').serialize() + '&ajax=salvar_cliente_modal';

        $.ajax({
            url: 'create.php',
            type: 'POST',
            dataType: 'json',
            data: dados,
            success: function(response) {
                if (!response || !response.success || !response.cliente) {
                    $feedback.html('<div class="alert alert-danger mb-0">Resposta inválida ao salvar cliente.</div>');
                    return;
                }

                var cliente = response.cliente;
                var textoOpcao = cliente.nome + (cliente.cpf_cnpj ? ' - ' + cliente.cpf_cnpj : '');
                var novaOption = new Option(textoOpcao, cliente.id, true, true);
                $(novaOption).data('clienteData', cliente);

                $('#cliente_id').append(novaOption).trigger('change');
                $('#cliente_id').trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: cliente.id,
                            text: textoOpcao,
                            clienteData: cliente
                        }
                    }
                });

                $feedback.html('<div class="alert alert-success mb-0">Cliente cadastrado com sucesso!</div>');

                setTimeout(function() {
                    $('#modalNovoCliente').modal('hide');
                    $('#formNovoClienteModal')[0].reset();
                    $('#modalClienteFeedback').html('');
                    $('#modal_cliente_cidade').val('Porto Alegre');
                }, 700);
            },
            error: function(xhr) {
                var mensagem = 'Não foi possível cadastrar o cliente.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    mensagem = xhr.responseJSON.message;
                }
                $feedback.html('<div class="alert alert-danger mb-0">' + mensagem + '</div>');
            },
            complete: function() {
                $btn.prop('disabled', false).html('Salvar Cliente');
            }
        });
    });

    $('#modalNovoCliente').on('hidden.bs.modal', function() {
        $('#formNovoClienteModal')[0].reset();
        $('#modalClienteFeedback').html('');
        $('#modal_cliente_cidade').val('Porto Alegre');
    });

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

    function validarItensQuantidadeZeroCreate() {
        let mensagemErro = '';
        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            const $row = $(this);
            if (($row.data('tipo-linha') || '') === 'CABECALHO_SECAO') {
                return;
            }

            const produtoId = String($row.find('.produto_id').val() || '').trim();
            const nomeProduto = String($row.find('.nome_produto_display').val() || '').trim();
            const quantidade = parseInt($row.find('.item-qtd').val() || '0', 10);

            const linhaTemProduto = produtoId !== '' || nomeProduto !== '';

            if (linhaTemProduto && quantidade <= 0) {
                mensagemErro = 'Há produto cadastrado com quantidade zero. Ajuste a quantidade ou remova a linha antes de salvar.';
                return false;
            }
        });

        if (mensagemErro) {
            if (typeof toastr !== 'undefined') {
                toastr.error(mensagemErro);
            } else {
                alert(mensagemErro);
            }
            return false;
        }

        return true;
    }

    $('#formNovoOrcamento').on('submit', function(e) {
        if (!validarItensQuantidadeZeroCreate()) {
            e.preventDefault();
            return false;
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