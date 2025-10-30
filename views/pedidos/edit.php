<?php
$page_title = "Editar Pedido";

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$clienteModel = new Cliente($db);
$pedidoModel = new Pedido($db);
$orcamentoModel = new Orcamento($db);

// --- 1. BUSCA DOS DADOS PARA EDIÇÃO ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "ID de pedido inválido ou não fornecido.";
    header("Location: index.php");
    exit;
}

$pedidoId = (int)$_GET['id'];
$pedidoDados = $pedidoModel->getById($pedidoId);

if ($pedidoDados === false) {
    $_SESSION['error_message'] = "Pedido com ID {$pedidoId} não encontrado.";
    header("Location: index.php");
    exit;
}

$page_title = "Editar Pedido #" . htmlspecialchars($pedidoDados['numero']);

// --- 2. BUSCA DOS ITENS DO PEDIDO ---
$itensPedido = $pedidoModel->getItens($pedidoId);
if ($itensPedido === false) {
    $itensPedido = [];
    $_SESSION['warning_message'] = "Atenção: não foi possível carregar os itens deste pedido.";
}

// Prepara o caminho completo da foto para cada item
$base_url_config = rtrim(BASE_URL, '/');
foreach ($itensPedido as &$item_processado) {
    if (!empty($item_processado['foto_path']) && $item_processado['foto_path'] !== "null" && trim($item_processado['foto_path']) !== "") {
        $foto_path_limpo = ltrim($item_processado['foto_path'], '/');
        $item_processado['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;
    } else {
        $item_processado['foto_path_completo'] = null;
    }
}
unset($item_processado);

// Textos padrão para observações e condições (se não houver no pedido)
$textoPadraoObservacoesDisplay = !empty($pedidoDados['observacoes']) ? $pedidoDados['observacoes'] : "# Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.\n* Não Inclui Posicionamento dos Móveis no Local.";
$textoPadraoCondicoesDisplay = !empty($pedidoDados['condicoes_pagamento']) ? $pedidoDados['condicoes_pagamento'] : "50% na aprovação para reserva em PIX ou Depósito.\nSaldo em PIX ou Depósito 7 dias antes do evento.\n* Consulte disponibilidade e preços para pagamento no cartão de crédito.";

// Valores padrão FIXOS para as taxas (para o data-valor-padrao)
$valorPadraoTaxaDomingo = 250.00;
$valorPadraoTaxaMadrugada = 800.00;
$valorPadraoTaxaHorarioEspecial = 500.00;
$valorPadraoTaxaHoraMarcada = 200.00;
$valorPadraoFreteTerreo = 180.00;
$valorPadraoFreteElevador = 100.00;
$valorPadraoFreteEscadas = 200.00;

// --- 3. Bloco AJAX para buscar clientes ---
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

// --- 4. Bloco AJAX para buscar produtos ---
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

// --- 5. Lógica de ATUALIZAÇÃO (Processamento do POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        $pedidoModel->id = $pedidoId;

        if (empty($_POST['cliente_id'])) {
            throw new Exception("Cliente é obrigatório.");
        }
        $pedidoModel->cliente_id = (int)$_POST['cliente_id'];

        // Manter número e código originais
        $pedidoModel->numero = $pedidoDados['numero'];
        $pedidoModel->codigo = $pedidoDados['codigo'];
        $pedidoModel->orcamento_id = $pedidoDados['orcamento_id'];

        // Data do pedido
        $data_pedido_input = $_POST['data_pedido'] ?? date('d/m/Y'); // Pega do POST ou usa data atual
        $data_pedido_dt = DateTime::createFromFormat('d/m/Y', $data_pedido_input);
        if (!$data_pedido_dt) {
            $data_pedido_dt = DateTime::createFromFormat('Y-m-d', $data_pedido_input);
        }
        if (!$data_pedido_dt) {
            $data_pedido_dt = new DateTime(); // Fallback para data atual se a formatação falhar
        }
        $pedidoModel->data_pedido = $data_pedido_dt->format('Y-m-d');

        // Datas e Horas de Evento, Entrega e Devolução
        $data_evento_dt = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $pedidoModel->data_evento = $data_evento_dt ? $data_evento_dt->format('Y-m-d') : null;
        $pedidoModel->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;

        $data_entrega_dt = !empty($_POST['data_entrega']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_entrega']) : null;
        $pedidoModel->data_entrega = $data_entrega_dt ? $data_entrega_dt->format('Y-m-d') : null;
        $pedidoModel->hora_entrega = !empty($_POST['hora_entrega']) ? $_POST['hora_entrega'] : null;

        $pedidoModel->local_evento = !empty($_POST['local_evento']) ? trim($_POST['local_evento']) : null;

        $data_devolucao_dt = !empty($_POST['data_devolucao_prevista']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_devolucao_prevista']) : null;
        $pedidoModel->data_devolucao_prevista = $data_devolucao_dt ? $data_devolucao_dt->format('Y-m-d') : null;
        $pedidoModel->hora_devolucao = !empty($_POST['hora_devolucao']) ? $_POST['hora_devolucao'] : null;

        $pedidoModel->turno_entrega = $_POST['turno_entrega'] ?? 'Manhã/Tarde (Horário Comercial)';
        $pedidoModel->turno_devolucao = $_POST['turno_devolucao'] ?? 'Manhã/Tarde (Horário Comercial)';
        $pedidoModel->tipo = $_POST['tipo'] ?? 'locacao';
        $pedidoModel->situacao_pedido = $_POST['status_pedido'] ?? 'confirmado'; // Usando situacao_pedido para alinhamento com DB

        // Função para converter valores monetários
        $fnConverterMoeda = function ($valorStr) {
            if (empty($valorStr)) return 0.0;
            $valor = str_replace('R$', '', $valorStr);
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return (float)$valor;
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
        $pedidoModel->usuario_id = $_SESSION['usuario_id'] ?? 1;

        // CAMPOS ESPECÍFICOS DE PEDIDOS
        $pedidoModel->valor_sinal = $fnConverterMoeda($_POST['valor_sinal'] ?? '0,00');
        $pedidoModel->valor_pago = $fnConverterMoeda($_POST['valor_pago'] ?? '0,00');
        $pedidoModel->valor_multas = $fnConverterMoeda($_POST['valor_multas'] ?? '0,00');

        // Datas de pagamento
        $data_pagamento_sinal_dt = !empty($_POST['data_pagamento_sinal']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_pagamento_sinal']) : null;
        $pedidoModel->data_pagamento_sinal = $data_pagamento_sinal_dt ? $data_pagamento_sinal_dt->format('Y-m-d') : null;

        $data_pagamento_final_dt = !empty($_POST['data_pagamento_final']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_pagamento_final']) : null;
        $pedidoModel->data_pagamento_final = $data_pagamento_final_dt ? $data_pagamento_final_dt->format('Y-m-d') : null;

        if (!$pedidoModel->update()) {
            throw new Exception("Falha ao atualizar o cabeçalho do pedido. Verifique os logs.");
        }

        // 🔥 LÓGICA DOS ITENS CORRIGIDA - SALVAMENTO SEGURO SEM PERDA DE DADOS
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
                    $item_data['tipo'] = null; // Tipo não se aplica a cabeçalho de seção
                } else if ($tipo_linha_atual === 'PRODUTO') {
                    $item_data['produto_id'] = isset($_POST['produto_id'][$index]) && !empty($_POST['produto_id'][$index]) ? (int) $_POST['produto_id'][$index] : null;

                    if ($item_data['produto_id'] === null) { // Se não tem produto_id, é manual
                        $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : null;
                    }

                    $item_data['quantidade'] = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 1;
                    if ($item_data['quantidade'] <= 0) $item_data['quantidade'] = 1;

                    $item_data['tipo'] = $_POST['tipo_item'][$index] ?? 'locacao';
                    $item_data['preco_unitario'] = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                    $item_data['desconto'] = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');
                    $item_data['preco_final'] = $item_data['quantidade'] * ($item_data['preco_unitario'] - $item_data['desconto']);

                } else {
                    error_log("Tipo de linha desconhecido ou inválido no índice {$index}: '{$tipo_linha_atual}' - Item ignorado.");
                    continue;
                }
                $itens[] = $item_data;
            }
        }

        // 🛡️ SALVAMENTO SEGURO DOS ITENS - SEM DELETAR ANTES DE CONFIRMAR O SUCESSO
        if (!empty($itens)) {
            // Preparar statement para inserir novos itens
            $stmtInserirItem = $db->prepare("
    INSERT INTO itens_pedido (
        pedido_id, produto_id, nome_produto_manual, quantidade, 
        tipo, preco_unitario, desconto, preco_final, observacoes, 
        tipo_linha, ordem
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

            // Array para armazenar IDs dos itens inseridos com sucesso
            $itensInseridosComSucesso = [];
            $todosItensInseridosComSucesso = true;
            
            // Tentar inserir cada item
            foreach ($itens as $item) {
                try {
                    $resultadoInsercao = $stmtInserirItem->execute([
    $pedidoId,
    $item['produto_id'],
    $item['nome_produto_manual'],
    $item['quantidade'],
    $item['tipo'],
    $item['preco_unitario'],
    $item['desconto'],
    $item['preco_final'],
    $item['observacoes'],
    $item['tipo_linha'],
    $item['ordem']
]);
                    
                    if ($resultadoInsercao) {
                        $itensInseridosComSucesso[] = $db->lastInsertId();
                        ;
                    } else {
                        $todosItensInseridosComSucesso = false;
                        error_log("❌ Falha ao inserir item: " . json_encode($item));
                        break;
                    }
                } catch (Exception $e) {
                    $todosItensInseridosComSucesso = false;
                    error_log("❌ Exceção ao inserir item: " . $e->getMessage() . " | Item: " . json_encode($item));
                    break;
                }
            }
            
            // 🎯 SÓ REMOVE OS ITENS ANTIGOS SE TODOS OS NOVOS FORAM INSERIDOS COM SUCESSO
            if ($todosItensInseridosComSucesso && !empty($itensInseridosComSucesso)) {
                try {
                    // Agora sim, podemos remover os itens antigos com segurança
                    $stmtRemoverAntigos = $db->prepare("DELETE FROM itens_pedido WHERE pedido_id = ? AND id NOT IN (" . implode(',', array_fill(0, count($itensInseridosComSucesso), '?')) . ")");
                    $parametrosRemocao = array_merge([$pedidoId], $itensInseridosComSucesso);
                    
                    if (!$stmtRemoverAntigos->execute($parametrosRemocao)) {
                        error_log("⚠️ Aviso: Novos itens salvos, mas falha ao remover itens antigos. Pode haver duplicatas.");
                    } else {
                        
                    }
                } catch (Exception $e) {
                    error_log("⚠️ Aviso: Novos itens salvos, mas erro ao remover antigos: " . $e->getMessage());
                }
            } else {
                // Se houve falha, remove os itens que foram inseridos parcialmente
                if (!empty($itensInseridosComSucesso)) {
                    try {
                       $stmtLimpezaParcial = $db->prepare("DELETE FROM itens_pedido WHERE id IN (" . implode(',', array_fill(0, count($itensInseridosComSucesso), '?')) . ")");
                        $stmtLimpezaParcial->execute($itensInseridosComSucesso);
                        error_log("🧹 Limpeza de itens parcialmente inseridos realizada.");
                    } catch (Exception $e) {
                        error_log("❌ Erro na limpeza de itens parciais: " . $e->getMessage());
                    }
                }
                throw new Exception("Falha ao salvar todos os itens do pedido. Os itens originais foram preservados. Verifique os logs para detalhes.");
            }
        } else {
            // Se não há itens para salvar, remove todos os itens existentes
            try {
                $stmtRemoverTodos = $db->prepare("DELETE FROM itens_pedido WHERE pedido_id = ?");
                $stmtRemoverTodos->execute([$pedidoId]);
                error_log("ℹ️ Todos os itens do pedido foram removidos (nenhum item enviado no formulário).");
            } catch (Exception $e) {
                error_log("❌ Erro ao remover todos os itens: " . $e->getMessage());
                throw new Exception("Erro ao processar remoção de itens do pedido.");
            }
        }

        // Recalcular valores para o pedido
        if (!$pedidoModel->recalcularValores($pedidoId)) {
            throw new Exception("Pedido atualizado, mas houve um problema ao recalcular os valores finais. Edite o pedido para corrigir.");
        }

        $db->commit();
        $_SESSION['success_message'] = "Pedido #" . htmlspecialchars($pedidoDados['numero']) . " atualizado com sucesso!";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = "Ocorreu um erro ao atualizar: " . $e->getMessage();
        error_log("[EXCEÇÃO NO PROCESSAMENTO DO PEDIDO]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        // Recarrega os dados para exibir o formulário com os valores originais em caso de erro
        $pedidoDados = $pedidoModel->getById($pedidoId); // Recarrega os dados para o formulário
        $itensPedido = $pedidoModel->getItens($pedidoId); // Recarrega os itens
        // É importante garantir que foto_path_completo seja recriado se os dados forem recarregados
        foreach ($itensPedido as &$item_processado) {
            if (!empty($item_processado['foto_path']) && $item_processado['foto_path'] !== "null" && trim($item_processado['foto_path']) !== "") {
                $foto_path_limpo = ltrim($item_processado['foto_path'], '/');
                $item_processado['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;
            } else {
                $item_processado['foto_path_completo'] = null;
            }
        }
        unset($item_processado);
    }
}
// Prepara os dados completos do cliente em JSON para o atributo data-
// Buscar dados completos do cliente
$stmt_cliente = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$pedidoDados['cliente_id']]);
$cliente_completo = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

$selected_client_full_data = json_encode([
    'id' => $cliente_completo['id'],
    'nome' => $cliente_completo['nome'],
    'telefone' => $cliente_completo['telefone'] ?? '',
    'email' => $cliente_completo['email'] ?? '',
    'cpf_cnpj' => $cliente_completo['cpf_cnpj'] ?? '',
    'endereco' => $cliente_completo['endereco'] ?? '',
    'cidade' => $cliente_completo['cidade'] ?? '',
    'observacoes' => $cliente_completo['observacoes'] ?? '',
], JSON_UNESCAPED_UNICODE);

// Define a variável JavaScript PEDIDO_ID que será injetada no footer.php
$inline_js_setup = "const PEDIDO_ID = " . $pedidoId . ";";

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
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php include_once __DIR__ . '/../includes/alert_messages.php'; ?>

            <form id="formEditarPedido" action="edit.php?id=<?= $pedidoId ?>" method="POST" novalidate>
                <input type="hidden" name="pedido_id" value="<?= $pedidoId ?>">

                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Dados do Pedido</h3>
                        <div class="card-tools">
                            <span class="badge badge-success">Nº Pedido: <?= htmlspecialchars($pedidoDados['numero']) ?></span>
                            <span class="badge badge-info ml-2"><?= htmlspecialchars($pedidoDados['codigo']) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-7">
                                <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control select2" id="cliente_id" name="cliente_id" required>
                                        <option value="<?= htmlspecialchars($pedidoDados['cliente_id']) ?>" selected
                                                data-cliente-full-data='<?= htmlspecialchars($selected_client_full_data, ENT_QUOTES, 'UTF-8') ?>'>
                                            <?= htmlspecialchars($pedidoDados['nome_cliente']) ?>
                                        </option>
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" data-toggle="modal" data-target="#modalNovoCliente" title="Novo Cliente"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                                <div id="cliente_info_selecionado" class="mt-2 text-muted small"></div>
                            </div>

                            <div class="col-md-3">
                                <label for="data_pedido" class="form-label">Data Pedido <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_pedido" name="data_pedido"
                                           value="<?= htmlspecialchars(date('d/m/Y', strtotime($pedidoDados['data_pedido']))) ?>" required>
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="status_pedido" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-control" id="status_pedido" name="status_pedido">
                                    <option value="confirmado" <?= ($pedidoDados['situacao_pedido'] ?? '') == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                                    <option value="em_separacao" <?= ($pedidoDados['situacao_pedido'] ?? '') == 'em_separacao' ? 'selected' : '' ?>>Em Separação</option>
                                    <option value="entregue" <?= ($pedidoDados['situacao_pedido'] ?? '') == 'entregue' ? 'selected' : '' ?>>Entregue</option>
                                    <option value="devolvido_parcial" <?= ($pedidoDados['situacao_pedido'] ?? '') == 'devolvido_parcial' ? 'selected' : '' ?>>Devolvido Parcial</option>
                                    <option value="finalizado" <?= ($pedidoDados['situacao_pedido'] ?? '') == 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                                    <option value="cancelado" <?= ($pedidoDados['situacao_pedido'] ?? '') == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
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
                                    <input type="text" class="form-control datepicker" id="data_evento" name="data_evento"
                                           placeholder="DD/MM/AAAA" value="<?= !empty($pedidoDados['data_evento']) ? htmlspecialchars(date('d/m/Y', strtotime($pedidoDados['data_evento']))) : '' ?>">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_evento" class="form-text text-muted"></small>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_evento" class="form-label">Hora do Evento</label>
                                <input type="time" class="form-control" id="hora_evento" name="hora_evento"
                                       value="<?= htmlspecialchars($pedidoDados['hora_evento'] ?? '') ?>">
                            </div>
                            <div class="col-md-7">
                                <label for="local_evento" class="form-label">Local do Evento/Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="local_evento" name="local_evento"
                                           placeholder="Ex: Salão de Festas Condomínio XYZ" value="<?= htmlspecialchars($pedidoDados['local_evento'] ?? '') ?>">
                                    <div class="input-group-append">
                                        <button type="button" id="btnUsarEnderecoCliente" class="btn btn-sm btn-outline-info"
                                                title="Usar endereço do cliente selecionado">
                                            <i class="fas fa-map-marker-alt"></i> Usar End. Cliente
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mt-md-3">
                                <label for="data_entrega" class="form-label">Data da Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_entrega" name="data_entrega"
                                           placeholder="DD/MM/AAAA" value="<?= !empty($pedidoDados['data_entrega']) ? htmlspecialchars(date('d/m/Y', strtotime($pedidoDados['data_entrega']))) : '' ?>">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_entrega" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2 mt-md-3">
                                <label for="hora_entrega" class="form-label">Hora da Entrega</label>
                                <input type="time" class="form-control" id="hora_entrega" name="hora_entrega"
                                       value="<?= htmlspecialchars($pedidoDados['hora_entrega'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 mt-md-3">
                                <label for="turno_entrega" class="form-label">Turno Entrega</label>
                                <select class="form-control" id="turno_entrega" name="turno_entrega">
                                    <option value="Manhã/Tarde (Horário Comercial)" <?= ($pedidoDados['turno_entrega'] ?? '') == 'Manhã/Tarde (Horário Comercial)' ? 'selected' : '' ?>>Manhã/Tarde (HC)</option>
                                    <option value="Manhã (Horário Comercial)" <?= ($pedidoDados['turno_entrega'] ?? '') == 'Manhã (Horário Comercial)' ? 'selected' : '' ?>>Manhã (HC)</option>
                                    <option value="Tarde (Horário Comercial)" <?= ($pedidoDados['turno_entrega'] ?? '') == 'Tarde (Horário Comercial)' ? 'selected' : '' ?>>Tarde (HC)</option>
                                    <option value="Noite (A Combinar)" <?= ($pedidoDados['turno_entrega'] ?? '') == 'Noite (A Combinar)' ? 'selected' : '' ?>>Noite (A Combinar)</option>
                                    <option value="Horário Específico" <?= ($pedidoDados['turno_entrega'] ?? '') == 'Horário Específico' ? 'selected' : '' ?>>Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-4 mt-md-3">
                                <label for="tipo" class="form-label">Tipo Pedido</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="locacao" <?= ($pedidoDados['tipo'] ?? '') == 'locacao' ? 'selected' : '' ?>>Locação</option>
                                    <option value="venda" <?= ($pedidoDados['tipo'] ?? '') == 'venda' ? 'selected' : '' ?>>Venda</option>
                                    <option value="misto" <?= ($pedidoDados['tipo'] ?? '') == 'misto' ? 'selected' : '' ?>>Misto (Locação e Venda)</option>
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
                                    <input type="text" class="form-control datepicker" id="data_devolucao_prevista" name="data_devolucao_prevista"
                                           placeholder="DD/MM/AAAA" value="<?= !empty($pedidoDados['data_devolucao_prevista']) ? htmlspecialchars(date('d/m/Y', strtotime($pedidoDados['data_devolucao_prevista']))) : '' ?>">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_devolucao" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_devolucao" class="form-label">Hora Devolução</label>
                                <input type="time" class="form-control" id="hora_devolucao" name="hora_devolucao"
                                       value="<?= htmlspecialchars($pedidoDados['hora_devolucao'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="turno_devolucao" class="form-label">Turno Devolução</label>
                                <select class="form-control" id="turno_devolucao" name="turno_devolucao">
                                    <option value="Manhã/Tarde (Horário Comercial)" <?= ($pedidoDados['turno_devolucao'] ?? '') == 'Manhã/Tarde (Horário Comercial)' ? 'selected' : '' ?>>Manhã/Tarde (HC)</option>
                                    <option value="Manhã (Horário Comercial)" <?= ($pedidoDados['turno_devolucao'] ?? '') == 'Manhã (Horário Comercial)' ? 'selected' : '' ?>>Manhã (HC)</option>
                                    <option value="Tarde (Horário Comercial)" <?= ($pedidoDados['turno_devolucao'] ?? '') == 'Tarde (Horário Comercial)' ? 'selected' : '' ?>>Tarde (HC)</option>
                                    <option value="Noite (A Combinar)" <?= ($pedidoDados['turno_devolucao'] ?? '') == 'Noite (A Combinar)' ? 'selected' : '' ?>>Noite (A Combinar)</option>
                                    <option value="Horário Específico" <?= ($pedidoDados['turno_devolucao'] ?? '') == 'Horário Específico' ? 'selected' : '' ?>>Horário Específico</option>
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

                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-hover" id="tabela_itens_pedido">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 35%;">Produto/Serviço/Seção <span class="text-danger">*</span></th>
                                        <th style="width: 10%;">Qtd. <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Vlr. Unit. (R$)</th>
                                        <th style="width: 15%;">Desc. Item (R$)</th>
                                        <th style="width: 15%;">Subtotal (R$)</th>
                                        <th style="width: 10%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
    <?php if (empty($itensPedido)): ?>
        <tr class="no-items-row">
            <td colspan="6" class="text-center text-muted">Nenhum item adicionado a este pedido ainda.</td>
        </tr>
    <?php else: ?>
        <?php
            // Ajustar o itemIndex inicial para o JS
            $lastItemIndex = 0;
            foreach ($itensPedido as $index => $item) {
                $lastItemIndex = max($lastItemIndex, $index + 1); // Usa o maior index para garantir
            }
            $inline_js_setup .= "let itemIndex = " . $lastItemIndex . ";"; // Define itemIndex para JS
        ?>
        <?php foreach ($itensPedido as $index => $item): ?>
            <?php
                $tipoLinha = htmlspecialchars($item['tipo_linha']);
                $nomeDisplay = htmlspecialchars($item['nome_produto_manual'] ?? $item['nome_produto_catalogo'] ?? 'Item sem nome');
                $precoUnitario = number_format($item['preco_unitario'] ?? 0, 2, ',', '.');
                $descontoItem = number_format($item['desconto'] ?? 0, 2, ',', '.');
                // O subtotal do item será recalculado pelo JS, mas preenchemos para visualização inicial
                $subtotalCalc = ($item['quantidade'] ?? 0) * (($item['preco_unitario'] ?? 0) - ($item['desconto'] ?? 0));
                $subtotalItem = number_format($subtotalCalc, 2, ',', '.');
            ?>

            <?php if ($tipoLinha === 'CABECALHO_SECAO'): ?>
                <tr class="item-pedido-row item-titulo-secao" data-index="<?= $index + 1 ?>" data-tipo-linha="<?= $tipoLinha ?>" style="background-color: #e7f1ff !important;">
                    <td colspan="5">
                        <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                        <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_titulo_secao" value="<?= $nomeDisplay ?>" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);">
                        <input type="hidden" name="produto_id[]" value="">
                        <input type="hidden" name="tipo_linha[]" value="<?= $tipoLinha ?>">
                        <input type="hidden" name="ordem[]" value="<?= $index + 1 ?>">
                        <input type="hidden" name="quantidade[]" value="0">
                        <input type="hidden" name="tipo_item[]" value="">
                        <input type="hidden" name="valor_unitario[]" value="0.00">
                        <input type="hidden" name="desconto_item[]" value="0.00">
                        <input type="hidden" name="observacoes_item[]" value="">
                    </td>
                    <td><button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button></td>
                </tr>
            <?php else: ?>
                <?php
                    $isItemManual = empty($item['produto_id']);
                    $observacoesEstilo = empty($item['observacoes']) ? 'display:none;' : 'display:block;';
                ?>
                <tr class="item-pedido-row" data-index="<?= $index + 1 ?>" data-tipo-linha="<?= $tipoLinha ?>" style="background-color: #ffffff !important;">
                    <td>
                        <?php if (!empty($item['foto_path_completo'])): ?>
                            <img src="<?= htmlspecialchars($item['foto_path_completo']) ?>" alt="Miniatura" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">
                        <?php endif; ?>
                        <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_produto_display" value="<?= $nomeDisplay ?>" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" <?= !$isItemManual ? 'readonly' : '' ?>>
                        <input type="hidden" name="produto_id[]" class="produto_id" value="<?= htmlspecialchars($item['produto_id'] ?? '') ?>">
                        <input type="hidden" name="tipo_linha[]" value="<?= $tipoLinha ?>">
                        <input type="hidden" name="ordem[]" value="<?= $index + 1 ?>">
                        <input type="hidden" name="tipo_item[]" value="<?= htmlspecialchars($item['tipo'] ?? 'locacao') ?>">
                        <small class="form-text text-muted observacoes_item_label" style="<?= $observacoesEstilo ?>">Obs. Item:</small>
                        <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="<?= $observacoesEstilo ?>" placeholder="Observação do item" value="<?= htmlspecialchars($item['observacoes'] ?? '') ?>">
                    </td>
                    <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantity-input item-qtd text-center" value="<?= htmlspecialchars($item['quantidade'] ?? 1) ?>" min="1" style="width: 70px;"></td>
                    <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="<?= $precoUnitario ?>"></td>
                    <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="<?= $descontoItem ?>"></td>
                    <td class="subtotal_item_display text-right font-weight-bold"><?= $subtotalItem ?></td>
                    <td>
                        <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                        <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button>
                        <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-right"><strong>Subtotal dos Itens:</strong></td>
                                        <td id="subtotal_geral_itens" class="text-right font-weight-bold">A confirmar</td>
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
                                        rows="3" placeholder="Ex: Cliente solicitou montagem especial..."><?= htmlspecialchars($pedidoDados['observacoes'] ?? $textoPadraoObservacoesDisplay) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="condicoes_pagamento">Condições de Pagamento</label>
                                    <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento"
                                        rows="3" placeholder="Ex: 50% na aprovação, 50% na entrega. PIX CNPJ ..."><?= htmlspecialchars($pedidoDados['condicoes_pagamento'] ?? $textoPadraoCondicoesDisplay) ?></textarea>
                                </div>

                                <!-- SEÇÃO ESPECÍFICA DE PEDIDOS - PAGAMENTOS -->
                                <hr>
                                <h5 class="text-primary"><i class="fas fa-money-bill-wave mr-2"></i>Controle de Pagamentos</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="valor_sinal">Valor do Sinal (R$)</label>
                                            <input type="text" class="form-control money-input text-right"
                                                id="valor_sinal" name="valor_sinal" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['valor_sinal'] ?? 0, 2, ',', '.')) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="data_pagamento_sinal">Data Pagto. Sinal</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control datepicker"
                                                    id="data_pagamento_sinal" name="data_pagamento_sinal"
                                                    placeholder="DD/MM/AAAA"
                                                    value="<?= !empty($pedidoDados['data_pagamento_sinal']) ? htmlspecialchars(date('d/m/Y', strtotime($pedidoDados['data_pagamento_sinal']))) : '' ?>">
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
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
                                                id="valor_pago" name="valor_pago" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['valor_pago'] ?? 0, 2, ',', '.')) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="data_pagamento_final">Data Pagto. Final</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control datepicker"
                                                    id="data_pagamento_final" name="data_pagamento_final"
                                                    placeholder="DD/MM/AAAA"
                                                    value="<?= !empty($pedidoDados['data_pagamento_final']) ? htmlspecialchars(date('d/m/Y', strtotime($pedidoDados['data_pagamento_final']))) : '' ?>">
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="valor_multas">Multas/Taxas Extras (R$)</label>
                                    <input type="text" class="form-control money-input text-right" id="valor_multas"
                                        name="valor_multas" placeholder="0,00"
                                        value="<?= htmlspecialchars(number_format($pedidoDados['valor_multas'] ?? 0, 2, ',', '.')) ?>">
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input"
                                            id="ajuste_manual_valor_final" name="ajuste_manual_valor_final"
                                            <?= (($pedidoDados['ajuste_manual'] ?? 0) == 1) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="ajuste_manual_valor_final">Ajustar
                                            Valor Final Manualmente?</label>
                                    </div>
                                </div>
                                <div class="form-group" id="campo_motivo_ajuste" style="display: <?= (($pedidoDados['ajuste_manual'] ?? 0) == 1) ? 'block' : 'none' ?>;">
                                    <label for="motivo_ajuste_valor_final">Motivo do Ajuste Manual</label>
                                    <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste"
                                        placeholder="Ex: Desconto especial concedido"
                                        value="<?= htmlspecialchars($pedidoDados['motivo_ajuste'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <hr>
                                <h5 class="text-muted">Taxas Adicionais</h5>

                                <div class="form-group row align-items-center">
    <div class="col-sm-1 pl-0 pr-0 text-center">
        <input type="checkbox" name="aplicar_taxa_domingo" id="aplicar_taxa_domingo"
            class="form-check-input taxa-frete-checkbox"
            data-target-input="taxa_domingo_feriado"
            <?= (($pedidoDados['taxa_domingo_feriado'] ?? 0) > 0) ? 'checked' : '' ?>>
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
                placeholder="0,00"
                value="<?= htmlspecialchars(number_format($pedidoDados['taxa_domingo_feriado'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="taxa_madrugada"
                                            <?= (($pedidoDados['taxa_madrugada'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_taxa_madrugada" class="col-sm-5 col-form-label pr-1">
                                        Taxa Madrugada <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoTaxaMadrugada, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="taxa_madrugada" name="taxa_madrugada" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['taxa_madrugada'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="taxa_horario_especial"
                                            <?= (($pedidoDados['taxa_horario_especial'] ?? 0) > 0) ? 'checked' : '' ?>>
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
                                                placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['taxa_horario_especial'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="taxa_hora_marcada"
                                            <?= (($pedidoDados['taxa_hora_marcada'] ?? 0) > 0) ? 'checked' : '' ?>>
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
                                                placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['taxa_hora_marcada'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="frete_terreo"
                                            <?= (($pedidoDados['frete_terreo'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_frete_terreo" class="col-sm-5 col-form-label pr-1">
                                        Frete Térreo <small class="text-muted">(Sob consulta)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="frete_terreo" name="frete_terreo" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['frete_terreo'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="frete_elevador"
                                            <?= (($pedidoDados['frete_elevador'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_frete_elevador" class="col-sm-5 col-form-label pr-1">
                                        Frete Elevador <small class="text-muted">(Sob consulta)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="frete_elevador" name="frete_elevador" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['frete_elevador'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="frete_escadas"
                                            <?= (($pedidoDados['frete_escadas'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_frete_escadas" class="col-sm-5 col-form-label pr-1">
                                        Frete Escadas <small
                                            class="text-muted">(R$<?= htmlspecialchars(number_format($valorPadraoFreteEscadas, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="frete_escadas" name="frete_escadas" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['frete_escadas'] ?? 0, 2, ',', '.')) ?>"
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
                                            data-target-input="desconto_total"
                                            <?= (($pedidoDados['desconto'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_desconto_geral" class="col-sm-5 col-form-label pr-1">
                                        Desconto Geral (-)
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                class="form-control money-input text-right taxa-frete-input"
                                                id="desconto_total" name="desconto_total" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['desconto'] ?? 0, 2, ',', '.')) ?>"
                                                <?= (($pedidoDados['desconto'] ?? 0) > 0) ? '' : 'disabled' ?>>
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
                                            id="valor_final_display" readonly
                                            placeholder="A confirmar"
                                            value="<?= htmlspecialchars(number_format($pedidoDados['valor_final'] ?? 0, 2, ',', '.')) ?>"
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
                </style>
            </form>
        </div>
    </section>
</div><!-- Modal Novo Cliente -->
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
    // Suprimir warnings do Select2
    $.fn.select2.defaults.set('theme', 'bootstrap4');
    $.fn.select2.defaults.set('width', '100%');
    $.fn.select2.defaults.set('escapeMarkup', function (markup) { return markup; });
       // ✅ DEFINIR itemIndex NO INÍCIO
    let itemIndex = 0;
    
    // Contar itens existentes para inicializar corretamente
    const linhasExistentes = document.querySelectorAll('#tabela_itens_pedido tbody tr.item-pedido-row');
    itemIndex = linhasExistentes.length;
    
    console.log('itemIndex inicializado:', itemIndex);
    // PEDIDO_ID e itemIndex são definidos via PHP antes deste bloco JS
    // BASE_URL também foi injetado pelo PHP
    
    // 🛡️ PROTEÇÃO GLOBAL CONTRA LOOP INFINITO
    window.calculandoTotais = false;
    
    // Variável para guardar os dados do cliente atual
    let dadosClienteAtual = null;

    // Função para carregar os dados completos do cliente a partir do `data-cliente-full-data`
    // do option selecionado no Select2.
    function carregarDadosClienteAtual() {
        const $selectedOption = $('#cliente_id option:selected');
        const dataString = $selectedOption.attr('data-cliente-full-data');
        
        if (dataString) {
            try {
                dadosClienteAtual = JSON.parse(dataString);
            } catch(e) {
                console.error("Falha ao ler dados do cliente na inicialização:", e);
                dadosClienteAtual = null;
            }
        } else {
            dadosClienteAtual = null;
        }
    }

    // Inicializa dadosClienteAtual na carga da página
    carregarDadosClienteAtual();

    // Função para exibir as informações detalhadas do cliente
    function exibirInfoCliente(clienteData) {
        if (clienteData) {
            $('#cliente_info_selecionado').html(
                '<strong>Telefone:</strong> ' + (clienteData.telefone || 'N/A') + '<br>' +
                '<strong>E-mail:</strong> ' + (clienteData.email || 'N/A') + '<br>' +
                '<strong>CPF/CNPJ:</strong> ' + (clienteData.cpf_cnpj || 'N/A') + '<br>' +
                '<strong>Endereço:</strong> ' + (clienteData.endereco || 'N/A') + ', ' + (clienteData.cidade || 'N/A')
            ).show();
            $('#btnUsarEnderecoCliente').show();
        } else {
            $('#cliente_info_selecionado').hide().empty();
            $('#btnUsarEnderecoCliente').hide();
        }
    }

    // Exibir info do cliente na carga inicial da página
    exibirInfoCliente(dadosClienteAtual);

    function unformatCurrency(value) {
        if (!value || typeof value !== 'string') { return 0; }
        // Remove "R$", pontos de milhar, e substitui vírgula decimal por ponto
        var number = parseFloat(value.replace(/R\$\s?/, '').replace(/\./g, '').replace(',', '.')) || 0;
        return isNaN(number) ? 0 : number;
    }

    function formatCurrency(value) {
        var number = parseFloat(value) || 0;
        // Garante que a formatação é sempre para moeda BRL
        return number.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ✅ FUNÇÃO CORRIGIDA COM PROTEÇÃO CONTRA LOOP INFINITO
    function calcularTotaisPedido() {
        // 🛡️ PROTEÇÃO CONTRA LOOP INFINITO
        if (window.calculandoTotais) {
            console.log('⚠️ Cálculo já em andamento, ignorando chamada duplicada...');
            return;
        }
        window.calculandoTotais = true;
        
        try {
            var subtotalGeralItens = 0;
            $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
                if ($(this).data('tipo-linha') !== 'CABECALHO_SECAO') {
                    subtotalGeralItens += calcularSubtotalItem($(this));
                }
            });

            // Função para pegar valor apenas se checkbox estiver marcado
            function getValorSeAtivo(inputId, checkboxId) {
                const $checkbox = $('#' + checkboxId);
                if ($checkbox.length > 0 && $checkbox.is(':checked')) {
                    return unformatCurrency($('#' + inputId).val());
                }
                return 0;
            }

            var descontoTotalGeral = getValorSeAtivo('desconto_total', 'aplicar_desconto_geral');
            var taxaDomingo = getValorSeAtivo('taxa_domingo_feriado', 'aplicar_taxa_domingo');
            var taxaMadrugada = getValorSeAtivo('taxa_madrugada', 'aplicar_taxa_madrugada');
            var taxaHorarioEspecial = getValorSeAtivo('taxa_horario_especial', 'aplicar_taxa_horario_especial');
            var taxaHoraMarcada = getValorSeAtivo('taxa_hora_marcada', 'aplicar_taxa_hora_marcada');
            var freteTerreo = getValorSeAtivo('frete_terreo', 'aplicar_frete_terreo');
            var freteElevador = getValorSeAtivo('frete_elevador', 'aplicar_frete_elevador');
            var freteEscadas = getValorSeAtivo('frete_escadas', 'aplicar_frete_escadas');
            
            var valorFinalCalculado = subtotalGeralItens - descontoTotalGeral + taxaDomingo + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada + freteTerreo + freteElevador + freteEscadas;

            // ✅ CÁLCULO CORRETO DO SALDO
            var valorSinal = unformatCurrency($('#valor_sinal').val());
            var valorPago = unformatCurrency($('#valor_pago').val());
            var valorMultas = unformatCurrency($('#valor_multas').val());
            
            var totalJaPago = valorSinal + valorPago;
            var valorFinalComMultas = valorFinalCalculado + valorMultas;
            var saldo = Math.max(0, valorFinalComMultas - totalJaPago);

            console.log('=== CÁLCULO SALDO ===');
            console.log('Valor Final:', valorFinalCalculado);
            console.log('Multas:', valorMultas);
            console.log('Valor Final + Multas:', valorFinalComMultas);
            console.log('Sinal:', valorSinal);
            console.log('Valor Pago:', valorPago);
            console.log('Total Já Pago:', totalJaPago);
            console.log('Saldo Final:', saldo);

            // Atualizar displays com formatação
            if (subtotalGeralItens === 0 && valorFinalCalculado === 0) {
                $('#subtotal_geral_itens').text('A confirmar');
                $('#valor_final_display').val('').attr('placeholder', 'A confirmar');
                $('#saldo_display').val('').attr('placeholder', 'A confirmar');
            } else {
                $('#subtotal_geral_itens').text(formatCurrency(subtotalGeralItens));
                $('#valor_final_display').val(formatCurrency(valorFinalComMultas));
                $('#saldo_display').val(formatCurrency(saldo));
            }
        } catch (error) {
            console.error('Erro no cálculo de totais:', error);
        } finally {
            // 🛡️ LIBERA A PROTEÇÃO APÓS UM PEQUENO DELAY
            setTimeout(() => {
                window.calculandoTotais = false;
            }, 100);
        }
    }

    function calcularSubtotalItem($row) {
        if ($row.data('tipo-linha') === 'CABECALHO_SECAO') { return 0; }
        var quantidade = parseFloat($row.find('.item-qtd').val()) || 0;
        var valorUnitario = unformatCurrency($row.find('.item-valor-unitario').val());
        var descontoUnitario = unformatCurrency($row.find('.desconto_item').val());
        var subtotal = quantidade * (valorUnitario - descontoUnitario);
        $row.find('.subtotal_item_display').text(formatCurrency(subtotal));
        return subtotal;
    }

    function carregarSugestoesProdutos() {
        var termoBusca = $('#busca_produto').val().trim();
        var categoriaSelecionada = $('#busca_categoria_produto').val();
        if (termoBusca.length < 2 && !categoriaSelecionada) {
            $('#sugestoes_produtos').empty().hide();
            return;
        }
        $.ajax({
            url: `edit.php?id=${PEDIDO_ID}&ajax=buscar_produtos`,
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
                        $('#sugestoes_produtos').append(`<a href="#" class="list-group-item list-group-item-action d-flex align-items-center item-sugestao-produto py-2" data-id="${produto.id}" data-nome="${produto.nome_produto || 'Sem nome'}" data-codigo="${produto.codigo || ''}" data-preco="${preco}" data-foto-completa="${fotoPathParaDataAttribute}">${fotoHtml}<div class="flex-grow-1"><strong>${produto.nome_produto || 'Sem nome'}</strong>${produto.codigo ? '<small class="d-block text-muted">Cód: ' + produto.codigo + '</small>' : ''}${produto.quantidade_total !== null ? '<small class="d-block text-info">Estoque: ' + produto.quantidade_total + '</small>' : ''}</div><span class="ml-auto text-primary font-weight-bold">R$ ${formatCurrency(preco)}</span></a>`);
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
            var quantidadeDefault = dadosItem ? (dadosItem.quantidade || 1) : 1;
            var descontoDefault = dadosItem ? (parseFloat(dadosItem.desconto) || 0) : 0;
            var subtotalDefault = quantidadeDefault * (precoUnitarioDefault - descontoDefault);
            var imagemHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">` : '';
            
            htmlLinha = `<tr class="item-pedido-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #ffffff !important;"><td>${imagemHtml}<input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}><input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}">` +
            `<input type="hidden" name="tipo_linha[]" value="${tipoLinha}" class="tipo_linha"><input type="hidden" name="ordem[]" value="${itemIndex}" class="ordem"><input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}"><small class="form-text text-muted observacoes_item_label" style="display:${dadosItem && dadosItem.observacoes ? 'block' : 'none'};">Obs. Item:</small><input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:${dadosItem && dadosItem.observacoes ? 'block' : 'none'};" placeholder="Observação do item" value="${dadosItem && dadosItem.observacoes ? dadosItem.observacoes : ''}"></td><td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeDefault}" min="1" style="width: 70px;"></td><td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${formatCurrency(precoUnitarioDefault)}"></td>` +
            `<td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${formatCurrency(descontoDefault)}"></td><td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault)}</td><td><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button> <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button></td></tr>`;
        } else if (tipoLinha === 'CABECALHO_SECAO') {
            htmlLinha = `<tr class="item-pedido-row item-titulo-secao" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #e7f1ff !important;"><td colspan="5"><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_titulo_secao" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);" value="${nomeDisplay}"><input type="hidden" name="produto_id[]" value=""><input type="hidden" name="tipo_linha[]" value="${tipoLinha}" class="tipo_linha"><input type="hidden" name="ordem[]" value="${itemIndex}" class="ordem"><input type="hidden" name="quantidade[]" value="0"><input type="hidden" name="tipo_item[]" value=""><input type="hidden" name="valor_unitario[]" value="0.00"><input type="hidden" name="desconto_item[]" value="0.00"><input type="hidden" name="observacoes_item[]" value=""></td><td><button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button></td></tr>`;
        }
        
        if (htmlLinha) {
            $('#tabela_itens_pedido tbody').append(htmlLinha);
            // ✅ CHAMA CÁLCULO APENAS UMA VEZ, SEM LOOP
            calcularTotaisPedido();
        }
    }

    // ✅ EVENTOS CORRIGIDOS COM DEBOUNCE E SEM LOOPS
    let debounceTimer;
    const calculateDebounced = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (!window.calculandoTotais) {
                calcularTotaisPedido();
            }
        }, 300);
    };

    // ✅ EVENTOS PRINCIPAIS COM PROTEÇÃO
    $('#valor_pago, #valor_sinal, #valor_multas, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo, #frete_elevador, #frete_escadas, #desconto_total').on('change', function() {
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
    }).on('keyup', calculateDebounced);

    // --- EVENTOS PARA SEÇÃO DE ITENS ---
    $('#busca_produto, #busca_categoria_produto').on('keyup change', carregarSugestoesProdutos);

    $('#sugestoes_produtos').on('click', '.item-sugestao-produto', function(e) {
        e.preventDefault();

        // Tratamento para clique na imagem (abrir modal/popup)
        if ($(e.target).closest('.foto-produto-sugestao').length > 0) {
            var fotoUrl = $(this).data('foto-completa');
            var nomeProduto = $(this).data('nome-produto');
            
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
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
        // Se não houver mais itens, adiciona a linha de "nenhum item"
        if ($('#tabela_itens_pedido tbody tr.item-pedido-row').length === 0) {
            $('#tabela_itens_pedido tbody').append('<tr class="no-items-row"><td colspan="6" class="text-center text-muted">Nenhum item adicionado a este pedido ainda.</td></tr>');
        }
    });

    // Remove a linha "Nenhum item adicionado" quando um item é adicionado
    $(document).on('click', '#btn_adicionar_item_manual, .item-sugestao-produto, #btn_adicionar_titulo_secao', function() {
        $('#tabela_itens_pedido tbody tr.no-items-row').remove();
    });

    $('#tabela_itens_pedido').on('click', '.btn_obs_item', function() {
        var $row = $(this).closest('tr');
        $row.find('.observacoes_item_label, .observacoes_item_input').toggle();
        if ($row.find('.observacoes_item_input').is(':visible')) {
            $row.find('.observacoes_item_input').focus();
        }
    });

    // ✅ EVENTOS DE ITENS COM PROTEÇÃO CONTRA LOOP
    $(document).on('change', '.item-qtd, .item-valor-unitario, .desconto_item', function() {
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
    });
    $(document).on('keyup', '.item-qtd, .item-valor-unitario, .desconto_item', calculateDebounced);

    // === Lógica para os botões "Usar Padrão" da varinha mágica ===
    $('.btn-usar-padrao').on('click', function() {
        var $button = $(this);
        var targetInputId = $button.data('target-input');
        var $targetInput = $('#' + targetInputId);
        if (!$targetInput.length) { return; }
        
        var valorSugeridoStr = $targetInput.data('valor-padrao');
        if (typeof valorSugeridoStr === 'undefined') { return; }
        
        var valorNumerico = unformatCurrency(valorSugeridoStr);
        $targetInput.val(formatCurrency(valorNumerico));
        
        var targetCheckboxId = $button.data('target-checkbox');
        if (targetCheckboxId) { 
            $('#' + targetCheckboxId).prop('checked', true); 
            $('#' + targetCheckboxId).trigger('change'); 
        } else {
            if (!window.calculandoTotais) {
                calcularTotaisPedido();
            }
        }
    });

    // === Lógica para checkboxes de taxas/fretes ===
    $('.taxa-frete-checkbox').on('change', function() {
        var $checkbox = $(this);
        var $targetInput = $('#' + $checkbox.data('target-input'));
        if ($targetInput.length) {
            if ($checkbox.is(':checked')) {
                $targetInput.prop('disabled', false);
                if (unformatCurrency($targetInput.val()) === 0) {
                    var valorPadraoStr = $targetInput.data('valor-padrao');
                    if (typeof valorPadraoStr !== 'undefined') {
                        $targetInput.val(formatCurrency(unformatCurrency(valorPadraoStr)));
                    }
                }
            } else { 
                $targetInput.val(formatCurrency(0)).prop('disabled', true);
            }
            if (!window.calculandoTotais) {
                calcularTotaisPedido();
            }
        }
    });

    // Inicializar Select2 para clientes
    if (typeof $.fn.select2 === 'function') {
        $('#cliente_id').select2({
            theme: 'bootstrap4',
            language: 'pt-BR',
            placeholder: 'Digite para buscar ou clique para ver os recentes',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: `edit.php?id=${PEDIDO_ID}&ajax=buscar_clientes`,
                dataType: 'json',
                delay: 250,
                data: function (params) { 
                    return { termo: params.term || '' }; 
                },
                processResults: function (data) {
                    return { 
                        results: $.map(data, function (cliente) {
                            return { 
                                id: cliente.id, 
                                text: cliente.nome + (cliente.cpf_cnpj ? ' - ' + cliente.cpf_cnpj : ''), 
                                clienteData: cliente 
                            };
                        }) 
                    };
                },
                cache: true
            }
        }).on('select2:select', function (e) {
            var data = e.params.data.clienteData;
            dadosClienteAtual = data;
            exibirInfoCliente(data);
        }).on('select2:unselect select2:clear', function(e) {
            dadosClienteAtual = null;
            exibirInfoCliente(null);
        });

        if (dadosClienteAtual) {
            exibirInfoCliente(dadosClienteAtual);
        }
    }

    // Datepicker
    if (typeof $.fn.datepicker === 'function') {
        $('.datepicker').datepicker({ 
            format: 'dd/mm/yyyy', 
            language: 'pt-BR', 
            autoclose: true, 
            todayHighlight: true, 
            orientation: "bottom auto" 
        });
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
                    if (dataObj.getDay() === 0 || dataObj.getDay() === 6) { 
                        displayEl.addClass('text-danger'); 
                    } else { 
                        displayEl.addClass('text-success'); 
                    }
                    return;
                }
            }
        }
    }
    
    // Dispara a exibição do dia da semana para datas existentes no formulário
    $('#data_evento').on('change dp.change', function() { 
        exibirDiaSemana('#data_evento', '#dia_semana_evento'); 
    }).trigger('change');
    
    $('#data_entrega').on('change dp.change', function() { 
        exibirDiaSemana('#data_entrega', '#dia_semana_entrega'); 
    }).trigger('change');
    
    $('#data_devolucao_prevista').on('change dp.change', function() { 
        exibirDiaSemana('#data_devolucao_prevista', '#dia_semana_devolucao'); 
    }).trigger('change'); 
    
    // Lógica revisada para o botão "Usar Endereço Cliente"
    let localEventoOriginal = '';

    // Capturar valor original na carga da página
    $(document).ready(function() {
        localEventoOriginal = $('#local_evento').val().trim();
    });

    $('#btnUsarEnderecoCliente').on('click', function() {
        if (!dadosClienteAtual) {
            alert('Por favor, selecione um cliente primeiro.');
            return;
        }
        
        let enderecoCompleto = (dadosClienteAtual.endereco || '').trim();
        if (dadosClienteAtual.cidade) {
            enderecoCompleto += (enderecoCompleto ? ', ' : '') + dadosClienteAtual.cidade.trim();
        }
        
        let $localEventoInput = $('#local_evento');
        let currentLocalEvento = $localEventoInput.val().trim();

        if (currentLocalEvento === enderecoCompleto) {
            // Se está mostrando endereço do cliente, volta ao original
            $localEventoInput.val(localEventoOriginal);
        } else {
            // Se não está mostrando endereço do cliente, guarda o atual como original e muda
            if (currentLocalEvento !== localEventoOriginal && currentLocalEvento !== '') {
                localEventoOriginal = currentLocalEvento;
            }
            $localEventoInput.val(enderecoCompleto);
        }
    });

    // Desabilitar submit ao pressionar Enter (exceto em textareas e botões)
    $('#formEditarPedido').on('keydown', function(e) {
        if (e.keyCode === 13 && !$(e.target).is('textarea') && !$(e.target).is(':button')) {
            e.preventDefault();
            return false;
        }
    });

    function atualizarOrdemDosItens() {
        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function(index) {
            $(this).attr('data-index', index + 1);
            $(this).find('input[name="ordem[]"]').val(index + 1);
        });
    }

    // Configuração do Sortable (arrastar e soltar itens)
    if (typeof $.fn.sortable === 'function') {
        $('#tabela_itens_pedido tbody').sortable({
            handle: '.drag-handle',
            placeholder: 'sortable-placeholder',
            helper: function(e, ui) { 
                ui.children().each(function() { 
                    $(this).width($(this).width()); 
                }); 
                return ui; 
            },
            stop: function(event, ui) { 
                atualizarOrdemDosItens(); 
            }
        }).disableSelection();
    }

    // Estilo para o placeholder do sortable
    $('head').append('<style>.sortable-placeholder { height: 50px; background-color: #f0f8ff; border: 2px dashed #cce5ff; }</style>');

    // Lógica para o switch "Ajustar Valor Final Manualmente?"
    $('#ajuste_manual_valor_final').on('change', function() {
        const isChecked = $(this).is(':checked');
        const $campoDesconto = $('#desconto_total');
        const $divMotivo = $('#campo_motivo_ajuste');
        const $inputMotivo = $('#motivo_ajuste');
        const $checkboxDesconto = $('#aplicar_desconto_geral');

        if (isChecked) {
            $campoDesconto.prop('disabled', false);
            $checkboxDesconto.prop('checked', true);
            $divMotivo.slideDown();
            $inputMotivo.prop('disabled', false);
        } else {
            $campoDesconto.prop('disabled', true);
            $checkboxDesconto.prop('checked', false);
            $divMotivo.slideUp();
            $inputMotivo.prop('disabled', true).val('');
        }
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
    });

    // Lógica para o checkbox "Desconto Geral (-)"
    $('#aplicar_desconto_geral').on('change', function() {
        const isChecked = $(this).is(':checked');
        const $campoDesconto = $('#desconto_total');
        const $ajusteManualSwitch = $('#ajuste_manual_valor_final');

        if (isChecked) {
            $campoDesconto.prop('disabled', false);
            $ajusteManualSwitch.prop('checked', true).trigger('change');
        } else {
            $campoDesconto.prop('disabled', true).val(formatCurrency(0));
            if ($ajusteManualSwitch.is(':checked') && unformatCurrency($campoDesconto.val()) === 0) {
                $ajusteManualSwitch.prop('checked', false).trigger('change');
            }
        }
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
    });

    // ✅ INICIALIZAÇÃO CORRIGIDA SEM LOOPS
    // Garante que o estado de todos os checkboxes seja processado na carga da página
    // MAS SEM TRIGGER QUE CAUSA LOOP!
    $('.taxa-frete-checkbox, #ajuste_manual_valor_final, #aplicar_desconto_geral').each(function() {
        var $this = $(this);
        var isChecked = $this.is(':checked');
        
        if ($this.hasClass('taxa-frete-checkbox')) {
            var $targetInput = $('#' + $this.data('target-input'));
            if ($targetInput.length) {
                if (isChecked) {
                    $targetInput.prop('disabled', false);
                } else {
                    $targetInput.prop('disabled', true);
                }
            }
        }
        
        if ($this.attr('id') === 'ajuste_manual_valor_final') {
            const $campoDesconto = $('#desconto_total');
            const $divMotivo = $('#campo_motivo_ajuste');
            const $inputMotivo = $('#motivo_ajuste');
            
            if (isChecked) {
                $campoDesconto.prop('disabled', false);
                $divMotivo.show();
                $inputMotivo.prop('disabled', false);
            } else {
                $campoDesconto.prop('disabled', true);
                $divMotivo.hide();
                $inputMotivo.prop('disabled', true);
            }
        }
        
        if ($this.attr('id') === 'aplicar_desconto_geral') {
            const $campoDesconto = $('#desconto_total');
            if (isChecked) {
                $campoDesconto.prop('disabled', false);
            } else {
                $campoDesconto.prop('disabled', true);
            }
        }
    });

    // ✅ CÁLCULO INICIAL ÚNICO (SEM LOOP)
    setTimeout(() => {
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
    }, 500);

    // Salvar Cliente Modal (AJAX)
    $('#btnSalvarClienteModal').on('click', function() {
        var formData = $('#formNovoClienteModal').serialize();
        $.ajax({
            url: `../clientes/processar_novo_cliente.php`,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message, 'Sucesso');
                    $('#modalNovoCliente').modal('hide');
                    var newOption = new Option(
                        response.cliente.nome + (response.cliente.cpf_cnpj ? ' - ' + response.cliente.cpf_cnpj : ''), 
                        response.cliente.id, 
                        true, 
                        true
                    );
                    $(newOption).data('clienteData', response.cliente);
                    $('#cliente_id').append(newOption).trigger('change');
                    $('#formNovoClienteModal')[0].reset();
                } else {
                    toastr.error(response.message, 'Erro');
                }
            },
            error: function(xhr) {
                toastr.error('Erro ao salvar cliente: ' + xhr.responseText, 'Erro de comunicação');
            }
        });
    });

    // Limpar busca de produtos
    $('#btnLimparBuscaProduto').on('click', function() {
        $('#busca_produto').val('');
        $('#sugestoes_produtos').empty().hide();
    });

    // Esconder sugestões ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busca_produto, #sugestoes_produtos').length) {
            $('#sugestoes_produtos').hide();
        }
    });

});
JS;

include_once __DIR__ . '/../includes/footer.php';
?>