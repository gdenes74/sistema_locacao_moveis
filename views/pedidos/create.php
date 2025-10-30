<?php
$page_title = "Pedido";

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$clienteModel = new Cliente($db);
$numeracaoModel = new NumeracaoSequencial($db);
$pedidoModel = new Pedido($db);
$orcamentoModel = new Orcamento($db);

$numeroFormatado = 'Gerado ao Salvar';

// Textos padrão
$textoPadraoObservacoes = "# Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.\n* Não Inclui Posicionamento dos Móveis no Local.";
$textoPadraoCondicoes = "50% na aprovação para reserva em PIX ou Depósito.\nSaldo em PIX ou Depósito 7 dias antes do evento.\n* Consulte disponibilidade e preços para pagamento no cartão de crédito.";

// Valores padrão para taxas
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
            $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes
                    FROM clientes
                    ORDER BY id DESC
                    LIMIT 10";
            $stmt = $db->prepare($sql);
        } else {
            $sql = "SELECT id, nome, telefone, email, cpf_cnpj, endereco, cidade, observacoes
                    FROM clientes
                    WHERE nome LIKE :termo_nome
                       OR REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :termo_cpf_cnpj
                       OR email LIKE :termo_email
                    ORDER BY 
                        CASE
                            WHEN nome LIKE :termo_inicio THEN 1
                            ELSE 2
                        END, 
                        nome ASC
                    LIMIT 15";

            $stmt = $db->prepare($sql);
            $likeTerm = "%" . $termo . "%";
            $likeTermInicio = $termo . "%";
            $likeTermCpfCnpj = "%" . preg_replace('/[^0-9]/', '', $termo) . "%";

            $stmt->bindParam(':termo_nome', $likeTerm, PDO::PARAM_STR);
            $stmt->bindParam(':termo_inicio', $likeTermInicio, PDO::PARAM_STR);
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

// --- Bloco AJAX para buscar orçamentos ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_orcamentos') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        error_log("🔍 FILTROS RECEBIDOS: " . print_r($_GET, true));
        
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        $cliente_id = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;
        $status_filtro = isset($_GET['status']) ? trim($_GET['status']) : '';
        $cliente_filtro = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
        $numero_filtro = isset($_GET['numero']) ? trim($_GET['numero']) : '';
        $data_orc_de = isset($_GET['data_orcamento_de']) ? trim($_GET['data_orcamento_de']) : '';
        $data_orc_ate = isset($_GET['data_orcamento_ate']) ? trim($_GET['data_orcamento_ate']) : '';
        $data_evt_de = isset($_GET['data_evento_de']) ? trim($_GET['data_evento_de']) : '';
        $data_evt_ate = isset($_GET['data_evento_ate']) ? trim($_GET['data_evento_ate']) : '';

        $sql = "SELECT o.id, o.numero, o.codigo, o.data_orcamento, o.data_evento, 
                       o.valor_final, o.status, c.nome as nome_cliente
                FROM orcamentos o
                LEFT JOIN clientes c ON o.cliente_id = c.id
                WHERE o.status NOT IN ('convertido')";
        $params = [];

        $filtros_aplicados = [];

        if (!empty($status_filtro)) {
            $sql .= " AND o.status = :status";
            $params[':status'] = $status_filtro;
            $filtros_aplicados[] = "Status: " . $status_filtro;
        }

        if (!empty($cliente_filtro)) {
            $sql .= " AND c.nome LIKE :cliente_nome";
            $params[':cliente_nome'] = "%" . $cliente_filtro . "%";
            $filtros_aplicados[] = "Cliente: " . $cliente_filtro;
        }

        if (!empty($numero_filtro)) {
            $sql .= " AND (o.numero = :numero_exato OR CAST(o.numero AS CHAR) LIKE :numero_like OR o.codigo LIKE :codigo_like)";
            $params[':numero_exato'] = $numero_filtro;
            $params[':numero_like'] = "%" . $numero_filtro . "%";
            $params[':codigo_like'] = "%" . $numero_filtro . "%";
            $filtros_aplicados[] = "Número: " . $numero_filtro;
        }

        if (!empty($data_orc_de)) {
            $sql .= " AND DATE(o.data_orcamento) >= :data_orc_de";
            $params[':data_orc_de'] = $data_orc_de;
            $filtros_aplicados[] = "Data Orçamento DE: " . $data_orc_de;
        }
        if (!empty($data_orc_ate)) {
            $sql .= " AND DATE(o.data_orcamento) <= :data_orc_ate";
            $params[':data_orc_ate'] = $data_orc_ate;
            $filtros_aplicados[] = "Data Orçamento ATÉ: " . $data_orc_ate;
        }

        if (!empty($data_evt_de)) {
            $sql .= " AND DATE(o.data_evento) >= :data_evt_de";
            $params[':data_evt_de'] = $data_evt_de;
            $filtros_aplicados[] = "Data Evento DE: " . $data_evt_de;
        }
        if (!empty($data_evt_ate)) {
            $sql .= " AND DATE(o.data_evento) <= :data_evt_ate";
            $params[':data_evt_ate'] = $data_evt_ate;
            $filtros_aplicados[] = "Data Evento ATÉ: " . $data_evt_ate;
        }

        if (!empty($termo) && empty($cliente_filtro) && empty($numero_filtro)) {
            $sql .= " AND (CAST(o.numero AS CHAR) LIKE :termo OR o.codigo LIKE :termo_codigo OR c.nome LIKE :termo_cliente)";
            $params[':termo'] = "%" . $termo . "%";
            $params[':termo_codigo'] = "%" . $termo . "%";
            $params[':termo_cliente'] = "%" . $termo . "%";
            $filtros_aplicados[] = "Termo geral: " . $termo;
        }

        if ($cliente_id > 0) {
            $sql .= " AND o.cliente_id = :cliente_id";
            $params[':cliente_id'] = $cliente_id;
            $filtros_aplicados[] = "Cliente ID: " . $cliente_id;
        }

        $sql .= " ORDER BY o.id DESC LIMIT 50";

        error_log("📋 SQL FINAL: " . $sql);
        error_log("🎯 PARÂMETROS: " . print_r($params, true));
        error_log("✅ FILTROS APLICADOS: " . implode(", ", $filtros_aplicados));

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("📊 RESULTADOS ENCONTRADOS: " . count($resultados));
        
        echo json_encode($resultados);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("❌ ERRO AJAX buscar_orcamentos: " . $e->getMessage());
        echo json_encode(['error' => 'Erro no banco de dados ao buscar orçamentos.']);
        exit;
    }
}

// --- AJAX para carregar dados do orçamento ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'carregar_orcamento') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $orcamento_id = isset($_GET['orcamento_id']) ? (int) $_GET['orcamento_id'] : 0;

        if ($orcamento_id <= 0) {
            echo json_encode(['error' => 'ID do orçamento inválido']);
            exit;
        }

        $orcamento = $orcamentoModel->getById($orcamento_id);
        if (!$orcamento) {
            echo json_encode(['error' => 'Orçamento não encontrado']);
            exit;
        }

        $itens = $orcamentoModel->getItens($orcamento_id);

        $base_url_config = rtrim(BASE_URL, '/');
        foreach ($itens as &$item_processado) {
            if (!empty($item_processado['foto_path']) && $item_processado['foto_path'] !== "null" && trim($item_processado['foto_path']) !== "") {
                $foto_path_limpo = ltrim($item_processado['foto_path'], '/');
                $item_processado['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;
            } else {
                $item_processado['foto_path_completo'] = null;
            }
        }
        unset($item_processado);

        echo json_encode([
            'success' => true,
            'orcamento' => $orcamento,
            'itens' => $itens
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erro AJAX carregar_orcamento: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao carregar dados do orçamento']);
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
}// --- CÓDIGO PHP - PROCESSAMENTO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Verificar se é conversão de orçamento ou pedido novo
        $origem_pedido = isset($_POST['origem_pedido']) ? $_POST['origem_pedido'] : 'novo';
        $orcamento_origem_id = null;

        if ($origem_pedido === 'orcamento' && !empty($_POST['orcamento_id'])) {
            $orcamento_origem_id = (int) $_POST['orcamento_id'];

            // Buscar dados do orçamento
            $orcamento = $orcamentoModel->getById($orcamento_origem_id);
            if (!$orcamento) {
                throw new Exception("Orçamento selecionado não encontrado.");
            }

            if (in_array($orcamento['status'], ['convertido'])) {
                throw new Exception("Este orçamento já foi convertido em pedido.");
            }

            // Verificar se já existe pedido para este orçamento
            $stmt_check = $db->prepare("SELECT id FROM pedidos WHERE orcamento_id = ?");
            $stmt_check->execute([$orcamento_origem_id]);
            if ($stmt_check->fetchColumn()) {
                throw new Exception("Já existe um pedido para este orçamento.");
            }

            // Usar MESMO número do orçamento
            $pedidoModel->numero = $orcamento['numero'];
            $pedidoModel->codigo = str_replace('ORC-', 'PED-', $orcamento['codigo']);
        } else {
            // Pedido novo - gerar próximo número
            $proximoNumeroGerado = $numeracaoModel->gerarProximoNumero('pedido');
            if ($proximoNumeroGerado === false || $proximoNumeroGerado === null) {
                throw new Exception("Falha crítica ao gerar o número sequencial do pedido.");
            }
            $pedidoModel->numero = $proximoNumeroGerado;
        }

        // Dados básicos
        if (empty($_POST['cliente_id'])) {
            throw new Exception("Cliente é obrigatório.");
        }
        $pedidoModel->cliente_id = (int) $_POST['cliente_id'];
        $pedidoModel->orcamento_id = $orcamento_origem_id;

        // Datas e Horas
        $data_pedido_input = $_POST['data_pedido'] ?? date('d/m/Y');
        $data_pedido_dt = DateTime::createFromFormat('d/m/Y', $data_pedido_input) ?: DateTime::createFromFormat('Y-m-d', $data_pedido_input) ?: new DateTime();
        $pedidoModel->data_pedido = $data_pedido_dt->format('Y-m-d');

        $data_evento_dt = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $pedidoModel->data_evento = $data_evento_dt ? $data_evento_dt->format('Y-m-d') : null;
        $pedidoModel->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;

        $data_entrega_dt = !empty($_POST['data_entrega']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_entrega']) : null;
        $pedidoModel->data_entrega = $data_entrega_dt ? $data_entrega_dt->format('Y-m-d') : null;
        $pedidoModel->hora_entrega = !empty($_POST['hora_entrega']) ? $_POST['hora_entrega'] : null;

        $pedidoModel->local_evento = !empty($_POST['local_evento']) ? trim($_POST['local_evento']) : null;
$pedidoModel->data_devolucao_prevista = $data_retirada_dt ? $data_retirada_dt->format('Y-m-d') : null;
        $pedidoModel->data_retirada_prevista = $data_retirada_dt ? $data_retirada_dt->format('Y-m-d') : null;
        $pedidoModel->hora_devolucao = !empty($_POST['hora_devolucao']) ? $_POST['hora_devolucao'] : null;

        $pedidoModel->turno_entrega = $_POST['turno_entrega'] ?? 'Manhã/Tarde (Horário Comercial)';
        $pedidoModel->turno_devolucao = $_POST['turno_devolucao'] ?? 'Manhã/Tarde (Horário Comercial)';
        $pedidoModel->tipo = $_POST['tipo'] ?? 'locacao';
        $pedidoModel->situacao_pedido = $_POST['status_pedido'] ?? 'confirmado';

        $fnConverterMoeda = function ($valorStr) {
            if (empty($valorStr))
                return 0.0;
            $valor = str_replace('R$', '', $valorStr);
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return (float) $valor;
        };

        // Valores financeiros do cabeçalho
        $pedidoModel->desconto = $fnConverterMoeda($_POST['desconto_total'] ?? '0,00');
        $pedidoModel->taxa_domingo_feriado = $fnConverterMoeda($_POST['taxa_domingo_feriado'] ?? '0,00');
        $pedidoModel->taxa_madrugada = $fnConverterMoeda($_POST['taxa_madrugada'] ?? '0,00');
        $pedidoModel->taxa_horario_especial = $fnConverterMoeda($_POST['taxa_horario_especial'] ?? '0,00');
        $pedidoModel->taxa_hora_marcada = $fnConverterMoeda($_POST['taxa_hora_marcada'] ?? '0,00');
        $pedidoModel->frete_terreo = $fnConverterMoeda($_POST['frete_terreo'] ?? '0,00');
        $pedidoModel->frete_elevador = $fnConverterMoeda($_POST['frete_elevador'] ?? '0,00');
        $pedidoModel->frete_escadas = $fnConverterMoeda($_POST['frete_escadas'] ?? '0,00');
        $pedidoModel->ajuste_manual = isset($_POST['ajuste_manual_valor_final']) ? 1 : 0;
        $pedidoModel->motivo_ajuste = !empty($_POST['motivo_ajuste']) ? trim($_POST['motivo_ajuste']) : null;
        $pedidoModel->observacoes = !empty($_POST['observacoes_gerais']) ? trim($_POST['observacoes_gerais']) : null;
        $pedidoModel->condicoes_pagamento = !empty($_POST['condicoes_pagamento']) ? trim($_POST['condicoes_pagamento']) : null;
        $pedidoModel->usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;

        // CAMPOS ESPECÍFICOS DE PEDIDOS
        $pedidoModel->valor_sinal = $fnConverterMoeda($_POST['valor_sinal'] ?? '0,00');
        $pedidoModel->valor_pago = $fnConverterMoeda($_POST['valor_pago'] ?? '0,00');
        $pedidoModel->valor_multas = $fnConverterMoeda($_POST['valor_multas'] ?? '0,00');

        // Datas de pagamento
        $data_pagamento_sinal_dt = !empty($_POST['data_pagamento_sinal']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_pagamento_sinal']) : null;
        $pedidoModel->data_pagamento_sinal = $data_pagamento_sinal_dt ? $data_pagamento_sinal_dt->format('Y-m-d') : null;

        $data_pagamento_final_dt = !empty($_POST['data_pagamento_final']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_pagamento_final']) : null;
        $pedidoModel->data_pagamento_final = $data_pagamento_final_dt ? $data_pagamento_final_dt->format('Y-m-d') : null;

        $pedidoIdSalvo = $pedidoModel->create();

        if ($pedidoIdSalvo === false || $pedidoIdSalvo <= 0) {
            throw new Exception("Falha ao salvar o cabeçalho do pedido. Verifique os logs.");
        }

        // ---- MONTAGEM DO ARRAY $itens (TRADUTOR CORRIGIDO) ----
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
                    'tipo' => 'locacao', // Padrão
                    'observacoes' => isset($_POST['observacoes_item'][$index]) ? trim($_POST['observacoes_item'][$index]) : null,
                    'tipo_linha' => $tipo_linha_atual,
                    'ordem' => $ordem_atual
                ];

                if ($tipo_linha_atual === 'CABECALHO_SECAO') {
                    $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : 'Título';
                } else if ($tipo_linha_atual === 'PRODUTO') {
                    $item_data['produto_id'] = isset($_POST['produto_id'][$index]) && !empty($_POST['produto_id'][$index]) ? (int) $_POST['produto_id'][$index] : null;

                    if ($item_data['produto_id'] === null) {
                        $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : 'Item manual';
                    }

                    $item_data['quantidade'] = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 1;
                    $item_data['tipo'] = $_POST['tipo_item'][$index] ?? 'locacao';
                    $item_data['preco_unitario'] = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                    $item_data['desconto'] = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');
                    $item_data['preco_final'] = $item_data['quantidade'] * ($item_data['preco_unitario'] - $item_data['desconto']);
                } else {
                    continue; // Ignora tipos de linha desconhecidos
                }
                $itens[] = $item_data;
            }
        }
        // ---- FIM DA MONTAGEM DO ARRAY $itens ----

        if (!empty($itens)) {
            if (!$pedidoModel->salvarItens($pedidoIdSalvo, $itens)) {
                throw new Exception("Falha ao salvar um ou mais itens do pedido. Verifique os logs do servidor.");
            }
        }

        $pedidoModel->id = $pedidoIdSalvo;
        if (!$pedidoModel->recalcularValores($pedidoIdSalvo)) {
            throw new Exception("Pedido salvo, mas houve um problema ao recalcular os valores finais. Edite o pedido para corrigir.");
        }

        // Se conversão de orçamento, atualizar status do orçamento
        if ($orcamento_origem_id) {
            $stmt_update_orc = $db->prepare("UPDATE orcamentos SET status = 'convertido' WHERE id = ?");
            $stmt_update_orc->execute([$orcamento_origem_id]);
        }

        $db->commit();
        $_SESSION['success_message'] = "Pedido #" . htmlspecialchars($pedidoModel->numero) . " (Código: " . htmlspecialchars($pedidoModel->codigo) . ") criado com sucesso!";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = "Ocorreu um erro: " . $e->getMessage();
        error_log("[EXCEÇÃO NO PROCESSAMENTO DO PEDIDO]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
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
                        <li class="breadcrumb-item"><a href="index.php">Pedidos</a></li>
                        <li class="breadcrumb-item active">Pedido</li>
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

            <form id="formPedido" action="create.php" method="POST" novalidate>
                <!-- Card de Origem do Pedido -->
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-route mr-2"></i>Origem do Pedido</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" id="origem_novo"
                                        name="origem_pedido" value="novo" checked>
                                    <label for="origem_novo" class="custom-control-label">
                                        <strong>Pedido completo</strong><br>
                                        <small class="text-muted">Criar um pedido completo</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" id="origem_orcamento"
                                        name="origem_pedido" value="orcamento">
                                    <label for="origem_orcamento" class="custom-control-label">
                                        <strong>A partir de Orçamento</strong><br>
                                        <small class="text-muted">Converter orçamento em PEDIDO</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Seleção de Orçamento (oculto inicialmente) -->
                        <div id="secao_selecao_orcamento" class="mt-3" style="display: none;">
                            <!-- Filtros de Orçamentos -->
                            <div class="row">
                                <!-- Status -->
                                <div class="col-md-3">
                                    <label for="filtro_status_orcamento">Status:</label>
                                    <select id="filtro_status_orcamento" class="form-control form-control-sm">
                                        <option value="">Todos os Status</option>
                                        <option value="pendente">Pendentes</option>
                                        <option value="aprovado">Aprovados</option>
                                        <option value="recusado">Recusados</option>
                                        <option value="expirado">Expirados</option>
                                    </select>
                                </div>
                                
                                <!-- Cliente -->
                                <div class="col-md-3">
                                    <label for="filtro_cliente">Cliente:</label>
                                    <select id="filtro_cliente" class="form-control form-control-sm select2-cliente-filtro">
                                        <option value="">Todos os clientes</option>
                                    </select>
                                </div>
                                
                                <!-- Número -->
                                <div class="col-md-2">
                                    <label for="filtro_numero">Número:</label>
                                    <input type="text" id="filtro_numero" class="form-control form-control-sm" placeholder="Ex: 123">
                                </div>
                                
                                <!-- Botões -->
                                <div class="col-md-2">
                                    <label>&nbsp;</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <button type="button" id="btnPesquisar" class="btn btn-primary btn-sm btn-block">🔍 Buscar</button>
                                        </div>
                                        <div class="col-6">
                                            <button type="button" id="btnLimparFiltros" class="btn btn-secondary btn-sm btn-block">🗑️ Limpar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <!-- Data Orçamento -->
                                <div class="col-md-3">
                                    <label for="filtro_data_orcamento_de">Data Orçamento:</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="date" id="filtro_data_orcamento_de" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <input type="date" id="filtro_data_orcamento_ate" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Data Evento -->
                                <div class="col-md-3">
                                    <label for="filtro_data_evento_de">Data Evento:</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="date" id="filtro_data_evento_de" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <input type="date" id="filtro_data_evento_ate" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-2">
                                <!-- Select de orçamentos -->
                                <div class="col-md-8">
                                    <label for="orcamento_id" class="form-label">Selecionar Orçamento <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control select2" id="orcamento_id" name="orcamento_id">
                                        <option value="">Selecione um orçamento disponível</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-info btn-block" id="btnCarregarOrcamento"
                                        disabled>
                                        <i class="fas fa-download"></i> Carregar Dados
                                    </button>
                                </div>
                            </div>
                            <div id="info_orcamento_selecionado" class="mt-2 text-muted small"></div>
                        </div>
                    </div>
                </div>                <!-- Dados do Pedido -->
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Dados do Pedido</h3>
                        <div class="card-tools">
                            <span class="badge badge-success">Nº Pedido:
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
                                <label for="data_pedido" class="form-label">Data Pedido <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_pedido"
                                        name="data_pedido" value="<?= date('d/m/Y') ?>" required>
                                    <div class="input-group-append"><span class="input-group-text"><i
                                                class="fas fa-calendar-alt"></i></span></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="status_pedido" class="form-label">Status <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="status_pedido" name="status_pedido">
                                    <option value="confirmado" selected>Confirmado</option>
                                    <option value="em_preparacao">Em Preparação</option>
                                    <option value="entregue">Entregue</option>
                                    <option value="devolvido">Devolvido</option>
                                    <option value="cancelado">Cancelado</option>
                                    <option value="finalizado">Finalizado</option>
                                </select>
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
                                <label for="tipo" class="form-label">Tipo Pedido</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="locacao" selected>Locação</option>
                                    <option value="venda">Venda</option>
                                    <option value="misto">Misto (Locação e Venda)</option>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-12">
                                <h5><i class="fas fa-undo-alt mr-2"></i>Detalhes da Devolução/Coleta</h5>
                            </div>
                            <div class="col-md-3">
                                <label for="data_retirada_prevista" class="form-label">Data Devolução (Prev.)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_retirada_prevista"
                                        name="data_retirada_prevista" placeholder="DD/MM/AAAA">
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
                        </div>
                    </div>
                </div>

                <!-- Itens do Pedido -->
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list-ul mr-2"></i>Itens do Pedido</h3>
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
                            <table class="table table-bordered table-hover" id="tabela_itens_pedido">
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

                                <!-- SEÇÃO ESPECÍFICA DE PEDIDOS - PAGAMENTOS -->
                                <hr>
                                <h5 class="text-primary"><i class="fas fa-money-bill-wave mr-2"></i>Controle de
                                    Pagamentos</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="valor_sinal">Valor do Sinal (R$)</label>
                                            <input type="text" class="form-control money-input text-right"
                                                id="valor_sinal" name="valor_sinal" placeholder="0,00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="data_pagamento_sinal">Data Pagto. Sinal</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control datepicker"
                                                    id="data_pagamento_sinal" name="data_pagamento_sinal"
                                                    placeholder="DD/MM/AAAA">
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i
                                                            class="fas fa-calendar-alt"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="valor_pago">Valor Total Pago (R$)</label>
                                            <input type="text" class="form-control money-input text-right"
                                                id="valor_pago" name="valor_pago" placeholder="0,00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="data_pagamento_final">Data Pagto. Final</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control datepicker"
                                                    id="data_pagamento_final" name="data_pagamento_final"
                                                    placeholder="DD/MM/AAAA">
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i
                                                            class="fas fa-calendar-alt"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="valor_multas">Multas/Taxas Extras (R$)</label>
                                    <input type="text" class="form-control money-input text-right" id="valor_multas"
                                        name="valor_multas" placeholder="0,00">
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

                                <!-- Taxa Dom./Feriado -->
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
                                                    title="Usar Padrão: R$<?= htmlspecialchars(number_format($valorPadraoTaxaDomingo, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Taxa Madrugada -->
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
                                                id="taxa_madrugada" name="taxa_madrugada"
                                                placeholder="a confirmar" value=""
                                                data-valor-padrao="<?= htmlspecialchars(number_format($valorPadraoTaxaMadrugada, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-usar-padrao"
                                                    data-target-input="taxa_madrugada"
                                                    data-target-checkbox="aplicar_taxa_madrugada"
                                                    title="Usar Padrão: R$<?= htmlspecialchars(number_format($valorPadraoTaxaMadrugada, 2, ',', '.')) ?>">
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
                                    <label class="col-sm-6 col-form-label text-lg text-success">VALOR FINAL
                                        (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text"
                                            class="form-control form-control-lg text-right font-weight-bold text-success money-display"
                                            id="valor_final_display" readonly placeholder="A confirmar"
                                            style="background-color: #e9ecef !important; border: none !important;">
                                    </div>
                                </div>

                                <!-- Indicador de Saldo -->
                                <div class="form-group row bg-info p-2 rounded">
                                    <label class="col-sm-6 col-form-label text-white">SALDO A PAGAR:</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control text-right font-weight-bold text-white"
                                            id="saldo_display" readonly placeholder="A confirmar"
                                            style="background-color: transparent !important; border: none !important;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="index.php" class="btn btn-secondary mr-2">Cancelar</a>
                        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save mr-1"></i> Salvar
                            Pedido</button>
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
                    
                    .select2-dropdown-small {
                        max-height: 200px !important;
                    }

                    .select2-dropdown-small .select2-results__options {
                        max-height: 150px !important;
                        overflow-y: auto;
                    }

                    .select2-container--bootstrap4 .select2-dropdown {
                        border-radius: 0.25rem;
                        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
                    }

                    #secao_selecao_orcamento .select2-container {
                        position: relative;
                        z-index: 1;
                    }

                    #secao_selecao_orcamento .select2-dropdown {
                        position: absolute;
                        z-index: 1050;
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
    $('#btnUsarEnderecoCliente').hide();
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

    function formatarData(dataStr) {
        if (!dataStr) return '';
        var data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR');
    }

    // Lógica específica de pedidos - origem
    $('input[name="origem_pedido"]').change(function() {
        if ($(this).val() === 'orcamento') {
            $('#secao_selecao_orcamento').slideDown();
            carregarOrcamentosDisponiveis();
        } else {
            $('#secao_selecao_orcamento').slideUp();
            limparFormulario();
        }
    });

    // Carregar orçamentos disponíveis (COM FILTRO)
    function carregarOrcamentosDisponiveis() {
        var filtros = {
            status: $('#filtro_status_orcamento').val(),
            cliente: $('#filtro_cliente').val(),
            numero: $('#filtro_numero').val(),
            data_orcamento_de: $('#filtro_data_orcamento_de').val(),
            data_orcamento_ate: $('#filtro_data_orcamento_ate').val(),
            data_evento_de: $('#filtro_data_evento_de').val(),
            data_evento_ate: $('#filtro_data_evento_ate').val()
        };

        console.log('🔍 Filtros enviados:', filtros);
        
        $.ajax({
            url: 'create.php?ajax=buscar_orcamentos',
            type: 'GET',
            dataType: 'json',
            data: filtros,
            success: function(orcamentos) {
                var $select = $('#orcamento_id');
                $select.empty().append('<option value="">Selecione um orçamento disponível</option>');
                
                if (orcamentos && orcamentos.length > 0) {
                    $.each(orcamentos, function(i, orc) {
                        var statusBadge = '';
                        switch(orc.status) {
                            case 'pendente': statusBadge = '🟡'; break;
                            case 'aprovado': statusBadge = '🟢'; break;
                            case 'recusado': statusBadge = '🔴'; break;
                            case 'expirado': statusBadge = '⚫'; break;
                        }
                        
                        $select.append(`<option value="${orc.id}">${statusBadge} ${orc.codigo} - ${orc.nome_cliente} - R$ ${parseFloat(orc.valor_final || 0).toFixed(2).replace('.', ',')}</option>`);
                    });
                }
                
                $select.select2('destroy');
                $select.select2({
                    theme: 'bootstrap4',
                    placeholder: 'Selecione um orçamento disponível',
                    allowClear: true,
                    dropdownAutoWidth: true,
                    width: '100%',
                    dropdownCssClass: 'select2-dropdown-small',
                    dropdownParent: $('#secao_selecao_orcamento')
                });
            },
            error: function() {
                console.error('Erro ao carregar orçamentos');
            }
        });
    }

    // Event listeners para os filtros
    $('#btnPesquisar').click(function() {
        carregarOrcamentosDisponiveis();
    });

    $('#filtro_status_orcamento').change(function() {
        carregarOrcamentosDisponiveis();
    });

    // Configurar Select2 para o filtro de cliente
    $('#filtro_cliente').select2({
        theme: 'bootstrap4',
        placeholder: 'Digite para buscar cliente...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'create.php?ajax=buscar_clientes',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { termo: params.term || '' };
            },
            processResults: function (data) {
                return {
                    results: $.map(data, function (cliente) {
                        return {
                            id: cliente.nome,
                            text: cliente.nome + (cliente.cpf_cnpj ? ' - ' + cliente.cpf_cnpj : '')
                        };
                    })
                };
            },
            cache: true
        }
    });

    // Botão limpar filtros
    $('#btnLimparFiltros').click(function() {
        $('#filtro_status_orcamento').val('');
        $('#filtro_cliente').val(null).trigger('change');
        $('#filtro_numero').val('');
        $('#filtro_data_orcamento_de').val('');
        $('#filtro_data_orcamento_ate').val('');
        $('#filtro_data_evento_de').val('');
        $('#filtro_data_evento_ate').val('');
        carregarOrcamentosDisponiveis();
    });

    $('#btnCarregarOrcamento').click(function() {
        var orcamentoId = $('#orcamento_id').val();
        if (!orcamentoId) return;

        $.ajax({
            url: 'create.php?ajax=carregar_orcamento',
            type: 'GET',
            dataType: 'json',
            data: { orcamento_id: orcamentoId },
            success: function(response) {
                if (response.success) {
                    preencherFormularioComOrcamento(response.orcamento, response.itens);
                } else {
                    alert('Erro: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao carregar dados do orçamento');
            }
        });
    });

    function preencherFormularioComOrcamento(orcamento, itens) {
        if (orcamento.cliente_id && orcamento.nome_cliente) {
            var clienteOption = new Option(orcamento.nome_cliente, orcamento.cliente_id, true, true);
            $('#cliente_id').empty().append(clienteOption).trigger('change');
        }
        $('#data_evento').val(orcamento.data_evento ? formatarData(orcamento.data_evento) : '');
        $('#hora_evento').val(orcamento.hora_evento || '');
        $('#local_evento').val(orcamento.local_evento || '');
        $('#data_entrega').val(orcamento.data_entrega ? formatarData(orcamento.data_entrega) : '');
        $('#hora_entrega').val(orcamento.hora_entrega || '');
        $('#data_retirada_prevista').val(orcamento.data_devolucao_prevista ? formatarData(orcamento.data_devolucao_prevista) : '');
        $('#hora_devolucao').val(orcamento.hora_devolucao || '');
        $('#turno_entrega').val(orcamento.turno_entrega || '');
        $('#turno_devolucao').val(orcamento.turno_devolucao || '');
        $('#tipo').val(orcamento.tipo || 'locacao');
        $('#observacoes_gerais').val(orcamento.observacoes || '');
        $('#condicoes_pagamento').val(orcamento.condicoes_pagamento || '');

        // Preencher TODAS as taxas
        if (orcamento.taxa_domingo_feriado > 0) {
            $('#aplicar_taxa_domingo').prop('checked', true);
            $('#taxa_domingo_feriado').val(formatCurrency(orcamento.taxa_domingo_feriado));
        }
        if (orcamento.taxa_madrugada > 0) {
            $('#aplicar_taxa_madrugada').prop('checked', true);
            $('#taxa_madrugada').val(formatCurrency(orcamento.taxa_madrugada));
        }
        if (orcamento.taxa_horario_especial > 0) {
            $('#aplicar_taxa_horario_especial').prop('checked', true);
            $('#taxa_horario_especial').val(formatCurrency(orcamento.taxa_horario_especial));
        }
        if (orcamento.taxa_hora_marcada > 0) {
            $('#aplicar_taxa_hora_marcada').prop('checked', true);
            $('#taxa_hora_marcada').val(formatCurrency(orcamento.taxa_hora_marcada));
        }
        if (orcamento.frete_terreo > 0) {
            $('#aplicar_frete_terreo').prop('checked', true);
            $('#frete_terreo').val(formatCurrency(orcamento.frete_terreo));
        }
        if (orcamento.frete_elevador > 0) {
            $('#aplicar_frete_elevador').prop('checked', true);
            $('#frete_elevador').val(formatCurrency(orcamento.frete_elevador));
        }
        if (orcamento.frete_escadas > 0) {
            $('#aplicar_frete_escadas').prop('checked', true);
            $('#frete_escadas').val(formatCurrency(orcamento.frete_escadas));
        }
        if (orcamento.desconto > 0) {
            $('#aplicar_desconto_geral').prop('checked', true);
            $('#desconto_total').val(formatCurrency(orcamento.desconto)).prop('disabled', false);
        }
        if (orcamento.ajuste_manual == 1) {
            $('#ajuste_manual_valor_final').prop('checked', true);
            $('#motivo_ajuste').val(orcamento.motivo_ajuste || '');
            $('#campo_motivo_ajuste').show();
        }

        // Limpar e preencher itens
        $('#tabela_itens_pedido tbody').empty();
        itemIndex = 0;
        if (itens && itens.length > 0) {
            $.each(itens, function(i, item) {
                var itemPedido = {
                    id: item.produto_id,
                    nome_produto: item.nome_produto_catalogo || item.nome_produto_manual || 'Produto não identificado',
                    preco_locacao: item.preco_unitario,
                    quantidade: item.quantidade,
                    desconto: item.desconto,
                    observacoes: item.observacoes,
                    foto_path_completo: item.foto_path_completo || null
                };
                adicionarLinhaItemTabela(itemPedido, item.tipo_linha);
                
                var $ultimaLinha = $('#tabela_itens_pedido tbody tr:last');
                if (item.tipo_linha === 'PRODUTO') {
                    $ultimaLinha.find('.quantidade_item').val(item.quantidade);
                    $ultimaLinha.find('.item-valor-unitario').val(formatCurrency(item.preco_unitario).replace('R$ ', ''));
                    $ultimaLinha.find('.desconto_item').val(formatCurrency(item.desconto).replace('R$ ', ''));
                    if (item.observacoes) {
                        $ultimaLinha.find('.observacoes_item_input').val(item.observacoes).show();
                        $ultimaLinha.find('.observacoes_item_label').show();
                    }
                } else if (item.tipo_linha === 'CABECALHO_SECAO') {
                    $ultimaLinha.find('.nome_titulo_secao').val(item.nome_produto_manual);
                }
            });
        }

        calcularTotaisPedido();
        toastr.success('Dados do orçamento carregados com sucesso!', 'Sucesso');
    }

    function limparFormulario() {
        $('#cliente_id').val('').trigger('change');
        $('#tabela_itens_pedido tbody').empty();
        calcularTotaisPedido();
    }

    function calcularTotaisPedido() {
        var subtotalGeralItens = 0;
        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
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

        var valorSinal = unformatCurrency($('#valor_sinal').val());
        var valorPago = unformatCurrency($('#valor_pago').val());
        var totalPago = valorSinal + valorPago;
        var saldo = Math.max(0, valorFinalCalculado - totalPago);

        if (subtotalGeralItens === 0 && valorFinalCalculado === 0) {
            $('#subtotal_geral_itens').text('A confirmar');
            $('#valor_final_display').val('').attr('placeholder', 'A confirmar');
            $('#saldo_display').val('').attr('placeholder', 'A confirmar');
        } else {
            $('#subtotal_geral_itens').text(formatCurrency(subtotalGeralItens));
            $('#valor_final_display').val(formatCurrency(valorFinalCalculado));
            $('#saldo_display').val(formatCurrency(saldo));
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
            htmlLinha = `<tr class="item-pedido-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #ffffff !important;"><td>${imagemHtml}<input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}><input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}"><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}"><small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small><input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do item"></td><td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeDefault}" min="1" style="width: 70px;"></td><td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoUnitarioDefault.toFixed(2).replace('.', ',')}"></td><td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoDefault.toFixed(2).replace('.', ',')}"></td><td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault).replace('R$ ', '')}</td><td><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button> <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button></td></tr>`;
        } else if (tipoLinha === 'CABECALHO_SECAO') {
            htmlLinha = `<tr class="item-pedido-row item-titulo-secao" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #e7f1ff !important;"><td colspan="5"><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_titulo_secao" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);"><input type="hidden" name="produto_id[]" value=""><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="quantidade[]" value="0"><input type="hidden" name="tipo_item[]" value=""><input type="hidden" name="valor_unitario[]" value="0.00"><input type="hidden" name="desconto_item[]" value="0.00"><input type="hidden" name="observacoes_item[]" value=""></td><td><button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button></td></tr>`;
        }
        
        if (htmlLinha) {
            $('#tabela_itens_pedido tbody').append(htmlLinha);
            if (tipoLinha === 'CABECALHO_SECAO') {
                $('#tabela_itens_pedido tbody tr:last-child .nome_titulo_secao').focus();
            }
            calcularTotaisPedido();
        }
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

    // Eventos para recalcular saldo e taxas
    $('#valor_pago, #valor_sinal, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo, #frete_elevador, #frete_escadas').on('change keyup', calcularTotaisPedido);

    // Habilitar/desabilitar botão carregar orçamento
    $('#orcamento_id').change(function() {
        $('#btnCarregarOrcamento').prop('disabled', !$(this).val());
    });

    // --- EVENTOS PARA SEÇÃO DE ITENS ---
    $('#busca_produto, #busca_categoria_produto').on('keyup change', carregarSugestoesProdutos);

    $('#sugestoes_produtos').on('click', '.item-sugestao-produto', function(e) {
        e.preventDefault();

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
            return; 
        }

        var produtoId = $(this).data('id');
        var produtoJaExiste = false;
        $('#tabela_itens_pedido .produto_id').each(function() {
            if ($(this).val() == produtoId) {
                produtoJaExiste = true;
                return false;
            }
        });

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
            Swal.fire({
                title: 'Produto Repetido',
                text: "Este item já foi adicionado. Deseja adicioná-lo novamente em outra linha?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, adicionar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    adicionarItem();
                }
            });
        } else {
            adicionarItem();
        }
    });

    $('#btn_adicionar_titulo_secao').click(function() {
        adicionarLinhaItemTabela(null, 'CABECALHO_SECAO');
    });

    $('#btn_adicionar_item_manual').click(function() {
        adicionarLinhaItemTabela(null, 'PRODUTO');
    });

    $('#tabela_itens_pedido').on('click', '.btn_remover_item', function() {
        $(this).closest('tr').remove();
        atualizarOrdemDosItens();
        calcularTotaisPedido();
    });

    $('#tabela_itens_pedido').on('click', '.btn_obs_item', function() {
        var $row = $(this).closest('tr');
        $row.find('.observacoes_item_label, .observacoes_item_input').toggle();
        if ($row.find('.observacoes_item_input').is(':visible')) {
            $row.find('.observacoes_item_input').focus();
        }
    });

    $(document).on('change keyup blur', '.item-qtd, .item-valor-unitario, .desconto_item, #desconto_total, .taxa-frete-input', function() {
        calcularTotaisPedido();
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
            $('#btnUsarEnderecoCliente').show();
        });

        $('#cliente_id').on('select2:unselect select2:clear', function(e) {
            $('#btnUsarEnderecoCliente').hide();
        });
    }

    if (typeof $.fn.datepicker === 'function') {
        $('.datepicker').datepicker({ format: 'dd/mm/yyyy', language: 'pt-BR', autoclose: true, todayHighlight: true, orientation: "bottom auto" });
    }

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
    $('#data_retirada_prevista').on('change dp.change', function() { exibirDiaSemana(this, '#dia_semana_devolucao'); }).trigger('change');
    
    $('#btnUsarEnderecoCliente').on('click', function() {
        var clienteSelecionado = $('#cliente_id').select2('data');

        if (!clienteSelecionado || clienteSelecionado.length === 0 || !clienteSelecionado[0].clienteData) {
            toastr.info('Por favor, selecione um cliente primeiro.', 'Aviso');
            return;
        }
        
        let data = clienteSelecionado[0].clienteData;
        let enderecoCompleto = (data.endereco || '') + (data.cidade ? ((data.endereco ? ', ' : '') + data.cidade) : '');
        let localEventoInput = $('#local_evento');

        if (localEventoInput.val().trim() === enderecoCompleto.trim()) {
            localEventoInput.val('');
        } else {
            localEventoInput.val(enderecoCompleto.trim());
        }
    });

    $('#formPedido').on('keydown', function(e) {
        if (e.keyCode === 13) {
            if (!$(e.target).is('textarea') && !$(e.target).is('[type=submit]')) {
                e.preventDefault();
                return false;
            }
        }
    });

    function atualizarOrdemDosItens() {
        $('#tabela_itens_pedido tbody tr').each(function(index) {
            $(this).attr('data-index', index + 1);
            $(this).find('input[name="ordem[]"]').val(index + 1);
        });
    }

    $('#tabela_itens_pedido tbody').sortable({
        handle: '.drag-handle',
        placeholder: 'sortable-placeholder',
        helper: function(e, ui) { ui.children().each(function() { $(this).width($(this).width()); }); return ui; },
        stop: function(event, ui) { atualizarOrdemDosItens(); }
    }).disableSelection();

    $('head').append('<style>.sortable-placeholder { height: 50px; background-color: #f0f8ff; border: 2px dashed #cce5ff; }</style>');

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
        calcularTotaisPedido();
    });

    $('.taxa-frete-input').each(function() {
        var valorNumerico = unformatCurrency($(this).val());
        if (valorNumerico === 0) {
            $(this).val('');
        }
    });

    calcularTotaisPedido();
});
JS;

include_once __DIR__ . '/../includes/footer.php';
?>