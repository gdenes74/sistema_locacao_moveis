<?php
$page_title = "Editar Orçamento";

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/EstoqueMovimentacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection(); // Conexão PDO

$clienteModel = new Cliente($db);
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db);
$estoqueModel = new EstoqueMovimentacao($db);

// --- 1. BUSCA DOS DADOS PARA EDIÇÃO ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "ID de orçamento inválido ou não fornecido.";
    header("Location: index.php");
    exit;
}

$orcamentoId = (int)$_GET['id'];
$orcamentoDados = $orcamentoModel->getById($orcamentoId);

// VERIFICAR SE JÁ FOI CONVERTIDO EM PEDIDO
$stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE orcamento_id = ?");
$stmt->execute([$orcamentoId]);
$ja_convertido = $stmt->fetchColumn() > 0;

if ($ja_convertido) {
    $_SESSION['error_message'] = "Este orçamento já foi convertido em pedido e não pode ser editado!";
    header("Location: index.php");
    exit;
}

// --- 2. BUSCA DOS ITENS DO ORÇAMENTO ---
$itensOrcamento = $orcamentoModel->getItens($orcamentoId);
if ($itensOrcamento === false) {
    $itensOrcamento = []; // Garante que é um array, mesmo em caso de erro
    $_SESSION['error_message'] = "Atenção: não foi possível carregar os itens deste orçamento.";
}

// Prepara o caminho completo da foto para cada item
$base_url_config = rtrim(BASE_URL, '/');
foreach ($itensOrcamento as &$item_processado) {
    if (!empty($item_processado['foto_path']) && $item_processado['foto_path'] !== "null" && trim($item_processado['foto_path']) !== "") {
        $foto_path_limpo = ltrim($item_processado['foto_path'], '/');
        $item_processado['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;
    } else {
        $item_processado['foto_path_completo'] = null;
    }
}
unset($item_processado); // Limpa a referência do loop

if ($orcamentoDados === false) {
    $_SESSION['error_message'] = "Orçamento com ID {$orcamentoId} não encontrado.";
    header("Location: index.php");
    exit;
}

$page_title = "Editar Orçamento #" . htmlspecialchars($orcamentoDados['numero']);

// Textos padrão para observações e condições
$textoPadraoObservacoes = !empty($orcamentoDados['observacoes']) ? $orcamentoDados['observacoes'] : "# Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.\n* Não Inclui Posicionamento dos Móveis no Local.";
$textoPadraoCondicoes = !empty($orcamentoDados['condicoes_pagamento']) ? $orcamentoDados['condicoes_pagamento'] : "50% na aprovação para reserva em PIX ou Depósito.\nSaldo em PIX ou Depósito 7 dias antes do evento.\n* Consulte disponibilidade e preços para pagamento no cartão de crédito.";

// Valores padrão FIXOS para as taxas
$valorPadraoTaxaDomingo = 250.00;
$valorPadraoTaxaMadrugada = 800.00;
$valorPadraoTaxaHorarioEspecial = 500.00;
$valorPadraoTaxaHoraMarcada = 200.00;
$valorPadraoFreteTerreo = 180.00;
$valorPadraoFreteElevador = 100.00;
$valorPadraoFreteEscadas = 200.00;

// --- 3. BLOCO AJAX PARA BUSCAR CLIENTES ---
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

// --- 4. BLOCO AJAX PARA BUSCAR PRODUTOS ---
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

// --- 5. AJAX PARA VERIFICAR ESTOQUE NO EDIT ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'verificar_estoque') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
        $quantidade = isset($_GET['quantidade']) ? (int)$_GET['quantidade'] : 1;
        $orcamento_id_atual = isset($_GET['orcamento_id']) ? (int)$_GET['orcamento_id'] : 0;
        
        if ($produto_id <= 0) {
            echo json_encode(['disponivel' => false, 'erro' => 'ID do produto inválido']);
            exit;
        }
        
        // Considera itens já existentes no orçamento atual
        $disponivel = $estoqueModel->verificarEstoqueSimples($produto_id, $quantidade);
        $estoque_total = $estoqueModel->obterEstoqueTotal($produto_id);
        
        echo json_encode([
            'disponivel' => $disponivel,
            'estoque_disponivel' => $estoque_total,
            'quantidade_solicitada' => $quantidade
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['disponivel' => true, 'erro' => $e->getMessage()]);
        exit;
    }
}// --- 6. LÓGICA DE ATUALIZAÇÃO (Processamento do POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // DEBUG: Verificar se os itens estão chegando
    error_log("=== DEBUG EDIT POST ===");
    error_log("POST recebido: " . print_r($_POST, true));
    error_log("Tipo_linha existe? " . (isset($_POST['tipo_linha']) ? 'SIM' : 'NÃO'));
    if (isset($_POST['tipo_linha'])) {
        error_log("Quantidade de itens: " . count($_POST['tipo_linha']));
    }
    error_log("=======================");

    // Validação para garantir que estamos editando o orçamento certo
    if (!isset($_POST['orcamento_id']) || (int)$_POST['orcamento_id'] !== $orcamentoId) {
        $_SESSION['error_message'] = "Erro de submissão: ID do orçamento inconsistente.";
        header("Location: index.php");
        exit;
    }

    try {
        $db->beginTransaction();

        $orcamentoModel->id = $orcamentoId;

        if (empty($_POST['cliente_id'])) {
            throw new Exception("Cliente é obrigatório.");
        }
        $orcamentoModel->cliente_id = (int)$_POST['cliente_id'];

        // data_orcamento não é mais alterada pelo usuário (apenas visualização)
        $orcamentoModel->data_orcamento = $orcamentoDados['data_orcamento'] ?? date('Y-m-d');

        // Data de Validade
        if (isset($_POST['data_validade_calculada_hidden']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data_validade_calculada_hidden'])) {
            $orcamentoModel->data_validade = $_POST['data_validade_calculada_hidden'];
        } else {
            $data_orcamento_dt = new DateTime($orcamentoModel->data_orcamento);
            $validade_dias = isset($_POST['validade_dias']) ? (int) $_POST['validade_dias'] : 7;
            $data_validade_dt_calc = clone $data_orcamento_dt;
            $data_validade_dt_calc->modify("+{$validade_dias} days");
            $orcamentoModel->data_validade = $data_validade_dt_calc->format('Y-m-d');
        }

        // Datas e Horas de Evento, Entrega e Devolução
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

        // Função para converter valores monetários
        $fnConverterMoeda = function ($valorStr) {
            if (empty($valorStr)) return 0.0;
            $valor = str_replace('R$', '', $valorStr);
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return (float)$valor;
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
        $orcamentoModel->motivo_ajuste = !empty($_POST['motivo_ajuste']) ? trim($_POST['motivo_ajuste']) : null;
        $orcamentoModel->observacoes = !empty($_POST['observacoes_gerais']) ? trim($_POST['observacoes_gerais']) : null;
        $orcamentoModel->condicoes_pagamento = !empty($_POST['condicoes_pagamento']) ? trim($_POST['condicoes_pagamento']) : null;
        $orcamentoModel->usuario_id = $_SESSION['usuario_id'] ?? 1;

        if (!$orcamentoModel->update()) {
            throw new Exception("Falha ao atualizar o cabeçalho do orçamento. Verifique os logs.");
        }

        // PROCESSAMENTO DOS ITENS
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
                    'preco_final' => 0.00, // Será calculado
                    'tipo' => null,
                    'observacoes' => isset($_POST['observacoes_item'][$index]) ? trim($_POST['observacoes_item'][$index]) : null,
                    'tipo_linha' => $tipo_linha_atual,
                    'ordem' => $ordem_atual
                ];

                if ($tipo_linha_atual === 'CABECALHO_SECAO') {
                    $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : 'Título não informado';
                    $item_data['tipo'] = null; // Seção não tem tipo de item (locação/venda)
                } else if ($tipo_linha_atual === 'PRODUTO') {
                    $item_data['produto_id'] = isset($_POST['produto_id'][$index]) && !empty($_POST['produto_id'][$index]) ? (int) $_POST['produto_id'][$index] : null;

                    if ($item_data['produto_id'] === null) {
                        $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim($_POST['nome_produto_display'][$index]) : null;
                    }

                    $item_data['quantidade'] = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 1;
                    if ($item_data['quantidade'] <= 0)
                        $item_data['quantidade'] = 1;

                    $item_data['tipo'] = $_POST['tipo_item'][$index] ?? 'locacao'; // Tipo de locação/venda
                    $item_data['preco_unitario'] = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                    $item_data['desconto'] = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');

                    // Calculo do preco_final para o item antes de salvar
                    $item_data['preco_final'] = $item_data['quantidade'] * ($item_data['preco_unitario'] - $item_data['desconto']);

                } else {
                    error_log("Tipo de linha desconhecido ou inválido no índice {$index}: '{$tipo_linha_atual}' - Item ignorado.");
                    continue;
                }
                $itens[] = $item_data;
            }
        }

        // SALVAMENTO DOS ITENS CORRIGIDO
        if (!empty($itens)) {
            error_log("=== TENTANDO SALVAR ITENS ===");
            error_log("Itens para salvar: " . print_r($itens, true));
            error_log("Quantidade de itens: " . count($itens));
            
            try {
                $resultado = $orcamentoModel->salvarItens($orcamentoId, $itens);
                
                error_log("Resultado do salvarItens(): " . ($resultado ? 'TRUE' : 'FALSE'));
                
                if (!$resultado) {
                    throw new Exception("Falha ao salvar itens do orçamento.");
                }
                
                error_log("SUCESSO: Itens salvos!");
                
            } catch (Exception $e) {
                error_log("ERRO ao salvar itens: " . $e->getMessage());
                throw $e;
            }
        } else {
            error_log("AVISO: Nenhum item para salvar (array vazio)");
        }

        // Recalcular valores para o orçamento
        if (!$orcamentoModel->recalcularValores($orcamentoId)) {
            throw new Exception("Orçamento atualizado, mas houve um problema ao recalcular os valores finais. Edite o orçamento para corrigir.");
        }

        $db->commit();
        $_SESSION['success_message'] = "Orçamento #" . htmlspecialchars($orcamentoDados['numero']) . " atualizado com sucesso!";
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_message'] = "Ocorreu um erro ao atualizar: " . $e->getMessage();
        error_log("[EXCEÇÃO NO PROCESSAMENTO DO ORÇAMENTO]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        // Recarrega os dados para exibir o formulário com os valores originais em caso de erro
        $orcamentoDados = $orcamentoModel->getById($orcamentoId);
    }
}

// Prepara os dados completos do cliente em JSON para o atributo data-
$selected_client_full_data = json_encode([
    'id' => $orcamentoDados['cliente_id'],
    'nome' => $orcamentoDados['nome_cliente'],
    'telefone' => $orcamentoDados['cliente_telefone'] ?? '',
    'email' => $orcamentoDados['cliente_email'] ?? '',
    'cpf_cnpj' => $orcamentoDados['cliente_cpf_cnpj'] ?? '',
    'endereco' => $orcamentoDados['cliente_endereco'] ?? '',
    'cidade' => $orcamentoDados['cliente_cidade'] ?? '',
    'observacoes' => $orcamentoDados['cliente_observacoes'] ?? '',
], JSON_UNESCAPED_UNICODE);

// Define a variável JavaScript ORCAMENTO_ID que será injetada no footer.php
$inline_js_setup = "const ORCAMENTO_ID = " . $orcamentoId . ";";

include_once __DIR__ . '/../includes/header.php';
?><div class="content-wrapper">
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
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php include_once __DIR__ . '/../includes/alert_messages.php'; ?>

            <form id="formEditarOrcamento" action="edit.php?id=<?= $orcamentoId ?>" method="POST" novalidate>
                <input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>">

                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Dados do Orçamento</h3>
                        <div class="card-tools">
                            <span class="badge badge-info">Nº Orçamento: <?= htmlspecialchars($orcamentoDados['numero']) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-7">
                                <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control select2" id="cliente_id" name="cliente_id" required>
                                        <option value="<?= htmlspecialchars($orcamentoDados['cliente_id']) ?>" selected
                                                data-cliente-full-data='<?= htmlspecialchars($selected_client_full_data, ENT_QUOTES, 'UTF-8') ?>'>
                                            <?= htmlspecialchars($orcamentoDados['nome_cliente']) ?>
                                        </option>
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" data-toggle="modal" data-target="#modalNovoCliente" title="Novo Cliente"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                                <div id="cliente_info_selecionado" class="mt-2 text-muted small"></div>
                            </div>

                            <div class="col-md-3">
                                <label for="data_orcamento" class="form-label">Data Orçam. <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="data_orcamento" name="data_orcamento"
                                           value="<?= htmlspecialchars(date('d/m/Y', strtotime($orcamentoDados['data_orcamento']))) ?>" required readonly style="background-color: #e9ecef;">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="validade_dias" class="form-label">Validade (dias)</label>
                                <?php
                                    try {
                                        $d1 = new DateTime($orcamentoDados['data_orcamento']);
                                        $d2 = new DateTime($orcamentoDados['data_validade']);
                                        $diff = $d2->diff($d1)->days;
                                    } catch (Exception $e) {
                                        $diff = 7; // Valor padrão em caso de erro
                                    }
                                ?>
                                <input type="number" class="form-control" id="validade_dias" name="validade_dias"
                                       value="<?= $diff ?>" min="1" required>
                                <input type="hidden" id="data_validade_calculada_hidden" name="data_validade_calculada_hidden"
                                       value="<?= htmlspecialchars($orcamentoDados['data_validade']) ?>">
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
                                    <input type="text" class="form-control datepicker" id="data_evento" name="data_evento"
                                           placeholder="DD/MM/AAAA" value="<?= !empty($orcamentoDados['data_evento']) ? htmlspecialchars(date('d/m/Y', strtotime($orcamentoDados['data_evento']))) : '' ?>">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_evento" class="form-text text-muted"></small>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_evento" class="form-label">Hora do Evento</label>
                                <input type="time" class="form-control" id="hora_evento" name="hora_evento"
                                       value="<?= htmlspecialchars($orcamentoDados['hora_evento'] ?? '') ?>">
                            </div>
                            <div class="col-md-7">
                                <label for="local_evento" class="form-label">Local do Evento/Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="local_evento" name="local_evento"
                                           placeholder="Ex: Salão de Festas Condomínio XYZ" value="<?= htmlspecialchars($orcamentoDados['local_evento'] ?? '') ?>">
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
                                           placeholder="DD/MM/AAAA" value="<?= !empty($orcamentoDados['data_entrega']) ? htmlspecialchars(date('d/m/Y', strtotime($orcamentoDados['data_entrega']))) : '' ?>">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_entrega" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2 mt-md-3">
                                <label for="hora_entrega" class="form-label">Hora da Entrega</label>
                                <input type="time" class="form-control" id="hora_entrega" name="hora_entrega"
                                       value="<?= htmlspecialchars($orcamentoDados['hora_entrega'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 mt-md-3">
                                <label for="turno_entrega" class="form-label">Turno Entrega</label>
                                <select class="form-control" id="turno_entrega" name="turno_entrega">
                                    <option value="Manhã/Tarde (Horário Comercial)" <?= ($orcamentoDados['turno_entrega'] ?? '') == 'Manhã/Tarde (Horário Comercial)' ? 'selected' : '' ?>>Manhã/Tarde (HC)</option>
                                    <option value="Manhã (Horário Comercial)" <?= ($orcamentoDados['turno_entrega'] ?? '') == 'Manhã (Horário Comercial)' ? 'selected' : '' ?>>Manhã (HC)</option>
                                    <option value="Tarde (Horário Comercial)" <?= ($orcamentoDados['turno_entrega'] ?? '') == 'Tarde (Horário Comercial)' ? 'selected' : '' ?>>Tarde (HC)</option>
                                    <option value="Noite (A Combinar)" <?= ($orcamentoDados['turno_entrega'] ?? '') == 'Noite (A Combinar)' ? 'selected' : '' ?>>Noite (A Combinar)</option>
                                    <option value="Horário Específico" <?= ($orcamentoDados['turno_entrega'] ?? '') == 'Horário Específico' ? 'selected' : '' ?>>Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="status_orcamento" class="form-label">Status do Orçamento</label>
                                <select class="form-control" id="status_orcamento" name="status_orcamento"
                                        <?= ($orcamentoDados['status'] === 'convertido' || $orcamentoDados['status'] === 'finalizado' || $orcamentoDados['status'] === 'recusado' || $orcamentoDados['status'] === 'expirado' || $orcamentoDados['status'] === 'cancelado') ? 'disabled' : '' ?>>
                                    <option value="pendente" <?= ($orcamentoDados['status'] ?? '') == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="aprovado" <?= ($orcamentoDados['status'] ?? '') == 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="reprovado" <?= ($orcamentoDados['status'] ?? '') == 'reprovado' ? 'selected' : '' ?>>Reprovado</option>
                                    <option value="cancelado" <?= ($orcamentoDados['status'] ?? '') == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                    <option value="expirado" <?= ($orcamentoDados['status'] ?? '') == 'expirado' ? 'selected' : '' ?>>Expirado</option>
                                    <option value="convertido" <?= ($orcamentoDados['status'] ?? '') == 'convertido' ? 'selected' : '' ?>>Convertido em Pedido</option>
                                    <option value="finalizado" <?= ($orcamentoDados['status'] ?? '') == 'finalizado' ? 'selected' : '' ?>>Finalizado (Evento Concluído)</option>
                                </select>
                                <?php if ($orcamentoDados['status'] === 'convertido'): ?>
                                    <small class="text-danger mt-1 d-block">
                                        <i class="fas fa-lock"></i> <strong>ORÇAMENTO BLOQUEADO:</strong> Convertido em pedido - não pode ser editado!
                                    </small>
                                <?php endif; ?>
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
                                           placeholder="DD/MM/AAAA" value="<?= !empty($orcamentoDados['data_devolucao_prevista']) ? htmlspecialchars(date('d/m/Y', strtotime($orcamentoDados['data_devolucao_prevista']))) : '' ?>">
                                    <div class="input-group-append"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                </div>
                                <small id="dia_semana_devolucao" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_devolucao" class="form-label">Hora Devolução</label>
                                <input type="time" class="form-control" id="hora_devolucao" name="hora_devolucao"
                                       value="<?= htmlspecialchars($orcamentoDados['hora_devolucao'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="turno_devolucao" class="form-label">Turno Devolução</label>
                                <select class="form-control" id="turno_devolucao" name="turno_devolucao">
                                    <option value="Manhã/Tarde (Horário Comercial)" <?= ($orcamentoDados['turno_devolucao'] ?? '') == 'Manhã/Tarde (Horário Comercial)' ? 'selected' : '' ?>>Manhã/Tarde (HC)</option>
                                    <option value="Manhã (Horário Comercial)" <?= ($orcamentoDados['turno_devolucao'] ?? '') == 'Manhã (Horário Comercial)' ? 'selected' : '' ?>>Manhã (HC)</option>
                                    <option value="Tarde (Horário Comercial)" <?= ($orcamentoDados['turno_devolucao'] ?? '') == 'Tarde (Horário Comercial)' ? 'selected' : '' ?>>Tarde (HC)</option>
                                    <option value="Noite (A Combinar)" <?= ($orcamentoDados['turno_devolucao'] ?? '') == 'Noite (A Combinar)' ? 'selected' : '' ?>>Noite (A Combinar)</option>
                                    <option value="Horário Específico" <?= ($orcamentoDados['turno_devolucao'] ?? '') == 'Horário Específico' ? 'selected' : '' ?>>Horário Específico</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tipo" class="form-label">Tipo Orçamento</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="locacao" <?= ($orcamentoDados['tipo'] ?? '') == 'locacao' ? 'selected' : '' ?>>Locação</option>
                                    <option value="venda" <?= ($orcamentoDados['tipo'] ?? '') == 'venda' ? 'selected' : '' ?>>Venda</option>
                                    <option value="misto" <?= ($orcamentoDados['tipo'] ?? '') == 'misto' ? 'selected' : '' ?>>Misto (Locação e Venda)</option>
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
                                    <input type="text" class="form-control" id="busca_produto" placeholder="Digite para buscar...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="btnLimparBuscaProduto" title="Limpar busca"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <div id="sugestoes_produtos" class="list-group mt-1" style="position: absolute; z-index: 1000; width: calc(100% - 30px); max-height: 260px; overflow-y: auto; display:none; border: 1px solid #ced4da; background-color: white;"></div>
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-hover" id="tabela_itens_orcamento">
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
                                    <?php if (empty($itensOrcamento)): ?>
                                        <tr class="no-items-row">
                                            <td colspan="6" class="text-center text-muted">Nenhum item adicionado a este orçamento ainda.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($itensOrcamento as $index => $item): ?>
                                            <?php
                                                $itemIndex = $index + 1;
                                                $tipoLinha = htmlspecialchars($item['tipo_linha']);
                                                $nomeDisplay = htmlspecialchars($item['nome_produto_manual'] ?? $item['nome_produto_catalogo'] ?? 'Item sem nome');
                                                $precoUnitario = number_format($item['preco_unitario'] ?? 0, 2, ',', '.');
                                                $descontoItem = number_format($item['desconto'] ?? 0, 2, ',', '.');
                                                $subtotalItem = number_format($item['preco_final'] ?? 0, 2, ',', '.');
                                            ?>

                                            <?php if ($tipoLinha === 'CABECALHO_SECAO'): ?>
                                                <tr class="item-orcamento-row item-titulo-secao" data-index="<?= $itemIndex ?>" data-tipo-linha="<?= $tipoLinha ?>" style="background-color: #e7f1ff !important;">
                                                    <td colspan="5">
                                                        <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                                                        <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_titulo_secao" value="<?= $nomeDisplay ?>" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);">
                                                        <input type="hidden" name="produto_id[]" value="">
                                                        <input type="hidden" name="tipo_linha[]" value="<?= $tipoLinha ?>">
                                                        <input type="hidden" name="ordem[]" value="<?= $itemIndex ?>">
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
                                                    $observacoesEstilo = empty($item['observacoes']) ? 'display:none;' : '';
                                                ?>
                                                <tr class="item-orcamento-row" data-index="<?= $itemIndex ?>" data-tipo-linha="<?= $tipoLinha ?>" style="background-color: #ffffff !important;">
                                                    <td>
                                                        <?php if (!empty($item['foto_path_completo'])): ?>
                                                            <img src="<?= htmlspecialchars($item['foto_path_completo']) ?>" alt="Miniatura" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">
                                                        <?php endif; ?>
                                                        <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_produto_display" value="<?= $nomeDisplay ?>" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" <?= !$isItemManual ? 'readonly' : '' ?>>
                                                        <input type="hidden" name="produto_id[]" class="produto_id" value="<?= htmlspecialchars($item['produto_id'] ?? '') ?>">
                                                        <input type="hidden" name="tipo_linha[]" value="<?= $tipoLinha ?>">
                                                        <input type="hidden" name="ordem[]" value="<?= $itemIndex ?>">
                                                        <input type="hidden" name="tipo_item[]" value="<?= htmlspecialchars($item['tipo'] ?? 'locacao') ?>">
                                                        <small class="form-text text-muted observacoes_item_label" style="<?= $observacoesEstilo ?>">Obs. Item:</small>
                                                        <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="<?= $observacoesEstilo ?>" placeholder="Observação do item" value="<?= htmlspecialchars($item['observacoes'] ?? '') ?>">
                                                    </td>
                                                    <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantity-input item-qtd text-center" value="<?= htmlspecialchars($item['quantidade'] ?? 1) ?>" min="1" style="width: 70px;" data-valor-original="<?= htmlspecialchars($item['quantidade'] ?? 1) ?>"></td>
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
                                    <textarea class="form-control" id="observacoes_gerais" name="observacoes_gerais" rows="3" placeholder="Ex: Cliente solicitou montagem especial..."><?= htmlspecialchars($orcamentoDados['observacoes'] ?? $textoPadraoObservacoes) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="condicoes_pagamento">Condições de Pagamento</label>
                                    <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento" rows="3" placeholder="Ex: 50% na aprovação, 50% na entrega. PIX CNPJ ..."><?= htmlspecialchars($orcamentoDados['condicoes_pagamento'] ?? $textoPadraoCondicoes) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="ajuste_manual_valor_final" name="ajuste_manual_valor_final" <?= ($orcamentoDados['ajuste_manual'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="ajuste_manual_valor_final">Ajustar Valor Final Manualmente?</label>
                                    </div>
                                </div>
                                <div class="form-group" id="campo_motivo_ajuste" style="display: <?= ($orcamentoDados['ajuste_manual'] ?? 0) ? 'block' : 'none' ?>;">
                                    <label for="motivo_ajuste_valor_final">Motivo do Ajuste Manual</label>
                                    <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste" placeholder="Ex: Desconto especial concedido" value="<?= htmlspecialchars($orcamentoDados['motivo_ajuste'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <hr>
                                <h5 class="text-muted">Taxas Adicionais</h5>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_domingo" id="aplicar_taxa_domingo" class="form-check-input taxa-frete-checkbox" data-target-input="taxa_domingo_feriado" <?= (($orcamentoDados['taxa_domingo_feriado'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_taxa_domingo" class="col-sm-5 col-form-label pr-1">
                                        Taxa Dom./Feriado <small class="text-muted">(R$ <?= htmlspecialchars(number_format(250.00, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="taxa_domingo_feriado" name="taxa_domingo_feriado" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['taxa_domingo_feriado'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= htmlspecialchars($valorPadraoTaxaDomingo) ?>" data-valor-original="<?= htmlspecialchars(number_format($orcamentoDados['taxa_domingo_feriado'] ?? 0, 2, ',', '.')) ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="taxa_domingo_feriado" data-target-checkbox="aplicar_taxa_domingo" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(250.00, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_madrugada" id="aplicar_taxa_madrugada" class="form-check-input taxa-frete-checkbox" data-target-input="taxa_madrugada" <?= (($orcamentoDados['taxa_madrugada'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_taxa_madrugada" class="col-sm-5 col-form-label pr-1">
                                        Taxa Madrugada <small class="text-muted">(R$ <?= htmlspecialchars(number_format(800.00, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="taxa_madrugada" name="taxa_madrugada" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['taxa_madrugada'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= $valorPadraoTaxaMadrugada ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="taxa_madrugada" data-target-checkbox="aplicar_taxa_madrugada" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(800.00, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_horario_especial" id="aplicar_taxa_horario_especial" class="form-check-input taxa-frete-checkbox" data-target-input="taxa_horario_especial" <?= (($orcamentoDados['taxa_horario_especial'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_taxa_horario_especial" class="col-sm-5 col-form-label pr-1">
                                        Taxa Hor. Especial <small class="text-muted">(R$ <?= htmlspecialchars(number_format(500.00, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="taxa_horario_especial" name="taxa_horario_especial" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['taxa_horario_especial'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= $valorPadraoTaxaHorarioEspecial ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="taxa_horario_especial" data-target-checkbox="aplicar_taxa_horario_especial" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(500.00, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_taxa_hora_marcada" id="aplicar_taxa_hora_marcada" class="form-check-input taxa-frete-checkbox" data-target-input="taxa_hora_marcada" <?= (($orcamentoDados['taxa_hora_marcada'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_taxa_hora_marcada" class="col-sm-5 col-form-label pr-1">
                                        Taxa Hora Marcada <small class="text-muted">(R$ <?= htmlspecialchars(number_format(200.00, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="taxa_hora_marcada" name="taxa_hora_marcada" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['taxa_hora_marcada'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= $valorPadraoTaxaHoraMarcada ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="taxa_hora_marcada" data-target-checkbox="aplicar_taxa_hora_marcada" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(200.00, 2, ',', '.')) ?>">
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
                                        <input type="checkbox" name="aplicar_frete_terreo" id="aplicar_frete_terreo" class="form-check-input taxa-frete-checkbox" data-target-input="frete_terreo" <?= (($orcamentoDados['frete_terreo'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_frete_terreo" class="col-sm-5 col-form-label pr-1">
                                        Frete Térreo <small class="text-muted">(Sob consulta)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="frete_terreo" name="frete_terreo" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['frete_terreo'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= $valorPadraoFreteTerreo ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="frete_terreo" data-target-checkbox="aplicar_frete_terreo" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(180.00, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_frete_elevador" id="aplicar_frete_elevador" class="form-check-input taxa-frete-checkbox" data-target-input="frete_elevador" <?= (($orcamentoDados['frete_elevador'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_frete_elevador" class="col-sm-5 col-form-label pr-1">
                                        Frete Elevador <small class="text-muted">(Sob consulta)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="frete_elevador" name="frete_elevador" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['frete_elevador'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= $valorPadraoFreteElevador ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="frete_elevador" data-target-checkbox="aplicar_frete_elevador" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(100.00, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_frete_escadas" id="aplicar_frete_escadas" class="form-check-input taxa-frete-checkbox" data-target-input="frete_escadas" <?= (($orcamentoDados['frete_escadas'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_frete_escadas" class="col-sm-5 col-form-label pr-1">
                                        Frete Escadas <small class="text-muted">(R$ <?= htmlspecialchars(number_format(200.00, 2, ',', '.')) ?>)</small>
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="frete_escadas" name="frete_escadas" placeholder="a confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['frete_escadas'] ?? 0, 2, ',', '.')) ?>" data-valor-padrao="<?= $valorPadraoFreteEscadas ?>">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-usar-padrao" data-target-input="frete_escadas" data-target-checkbox="aplicar_frete_escadas" title="Usar Padrão: R$ <?= htmlspecialchars(number_format(200.00, 2, ',', '.')) ?>">
                                                    <i class="fas fa-magic"></i> Usar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row align-items-center">
                                    <div class="col-sm-1 pl-0 pr-0 text-center">
                                        <input type="checkbox" name="aplicar_desconto_geral" id="aplicar_desconto_geral" class="form-check-input taxa-frete-checkbox" data-target-input="desconto_total" <?= (($orcamentoDados['desconto'] ?? 0) > 0) ? 'checked' : '' ?>>
                                    </div>
                                    <label for="aplicar_desconto_geral" class="col-sm-5 col-form-label pr-1">
                                        Desconto Geral (-)
                                    </label>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control money-input text-right taxa-frete-input" id="desconto_total" name="desconto_total" placeholder="0,00" value="<?= htmlspecialchars(number_format($orcamentoDados['desconto'] ?? 0, 2, ',', '.')) ?>" <?= (($orcamentoDados['desconto'] ?? 0) > 0) ? '' : 'disabled' ?>>
                                        </div>
                                    </div>
                                </div>
                                <hr>

                                <div class="form-group row mt-3 bg-light p-2 rounded">
                                    <label class="col-sm-6 col-form-label text-lg text-primary">VALOR FINAL (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control form-control-lg text-right font-weight-bold text-primary money-display" id="valor_final_display" readonly placeholder="A confirmar" value="<?= htmlspecialchars(number_format($orcamentoDados['valor_final'] ?? 0, 2, ',', '.')) ?>" style="background-color: #e9ecef !important; border: none !important;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <?php
                        $status_atual = $orcamentoDados['status'] ?? 'pendente';
                        $orcamento_finalizado_ou_irreversivel = in_array($status_atual, ['convertido', 'finalizado', 'recusado', 'expirado', 'cancelado']);
                        ?>

                        <?php if (!$orcamento_finalizado_ou_irreversivel): ?>
                            <button type="button" class="btn btn-success btn-lg mr-2" id="btnConverterPedido" data-orcamento-id="<?= $orcamentoId ?>" title="Converter este Orçamento em um Pedido">
                                <i class="fas fa-arrow-alt-circle-right mr-1"></i> Converter para Pedido
                            </button>
                        <?php elseif ($status_atual === 'convertido'): ?>
                            <span class="badge badge-info badge-lg mr-2"><i class="fas fa-check-circle"></i> Convertido em Pedido</span>
                        <?php endif; ?>

                        <a href="index.php" class="btn btn-secondary mr-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save mr-1"></i> Salvar Orçamento</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<!-- Modal Novo Cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1" role="dialog" aria-labelledby="modalNovoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
              <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoClienteLabel">Novo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="formNovoClienteModal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_nome">Nome Completo / Razão Social <span class="text-danger">*</span></label><input type="text" class="form-control" id="modal_cliente_nome" name="nome" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_cpf_cnpj">CPF/CNPJ</label><input type="text" class="form-control" id="modal_cliente_cpf_cnpj" name="cpf_cnpj"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_email">E-mail</label><input type="email" class="form-control" id="modal_cliente_email" name="email"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label for="modal_cliente_telefone">Telefone <span class="text-danger">*</span></label><input type="text" class="form-control telefone" id="modal_cliente_telefone" name="telefone" required></div>
                        </div>
                    </div>
                    <div class="form-group"><label for="modal_cliente_endereco">Endereço (Rua, Nº, Bairro)</label><input type="text" class="form-control" id="modal_cliente_endereco" name="endereco"></div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group"><label for="modal_cliente_cidade">Cidade</label><input type="text" class="form-control" id="modal_cliente_cidade" name="cidade" value="Porto Alegre"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label for="modal_cliente_cep">CEP</label><input type="text" class="form-control cep" id="modal_cliente_cep" name="cep"></div>
                        </div>
                    </div>
                    <div class="form-group"><label for="modal_cliente_observacoes">Observações do Cliente</label><textarea class="form-control" id="modal_cliente_observacoes" name="observacoes" rows="2"></textarea></div>
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

<?php
// === JAVASCRIPT CORRIGIDO COMPLETO ===
$custom_js = <<<'JS'
$(document).ready(function() {
    // Variáveis para guardar os estados atuais
    let dadosClienteAtual = null;
    let localEventoOriginal = $('#local_evento').val(); // Memoriza o valor original do campo na carga

    // Função para ler os dados do cliente que está selecionado
    function carregarDadosClienteAtual() {
        const $selectedOption = $('#cliente_id option:selected');
        const dataString = $selectedOption.attr('data-cliente-full-data');
        
        if (dataString && typeof dataString === 'string') {
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

    // Executa a função assim que a página carrega
    carregarDadosClienteAtual();

    // Se o usuário editar o campo manualmente, o novo valor se torna o "original" para o toggle
    $('#local_evento').on('input', function() {
        localEventoOriginal = $(this).val();
    });

    var itemIndex = 0; 
    function unformatCurrency(value) {
        if (value === null || typeof value === 'undefined' || value === '') return 0;
        if (typeof value !== 'string') value = String(value);
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
            url: `edit.php?id=${ORCAMENTO_ID}&ajax=buscar_produtos`,
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
                        $('#sugestoes_produtos').append(`<a href="#" class="list-group-item list-group-item-action d-flex align-items-center item-sugestao-produto py-2" data-id="${produto.id}" data-nome="${produto.nome_produto || 'Sem nome'}" data-codigo="${produto.codigo || ''}" data-preco="${preco}" data-foto-completa="${fotoPathParaDataAttribute}">${fotoHtml}<div class="flex-grow-1"><strong>${produto.nome_produto || 'Sem nome'}</strong>${produto.codigo ? '<small class="d-block text-muted">Cód: ' + produto.codigo + '</small>' : ''}${produto.quantidade_total !== null ? '<small class="d-block text-info">Estoque: ' + produto.quantidade_total + '</small>' : ''}</div><span class="ml-auto text-primary font-weight-bold">R\$ ${preco.toFixed(2).replace('.', ',')}</span></a>`);
                    });
                } else {
                    $('#sugestoes_produtos').append('<div class="list-group-item text-muted">Nenhum produto encontrado.</div>');
                }
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
            var quantidadeDefault = 1; 
            var descontoDefault = 0;
            var subtotalDefault = quantidadeDefault * (precoUnitarioDefault - descontoDefault);
            var imagemHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">` : '';
            htmlLinha = `<tr class="item-orcamento-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #ffffff !important;"><td>${imagemHtml}<input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}><input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}"><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}"><small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small><input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do item"></td><td><input type="number" name="quantidade[]" class="form-control form-control-sm quantity-input item-qtd text-center" value="${quantidadeDefault}" min="1" style="width: 70px;" data-valor-original="${quantidadeDefault}"></td><td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoUnitarioDefault.toFixed(2).replace('.', ',')}"></td><td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoDefault.toFixed(2).replace('.', ',')}"></td><td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault).replace('R\$ ', '')}</td><td><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button> <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button></td></tr>`;
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
        $row.find('.subtotal_item_display').text(formatCurrency(subtotal).replace('R\$ ', ''));
        return subtotal;
    }

    function calcularTotaisOrcamento() {
        var subtotalGeralItens = 0;
        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            subtotalGeralItens += calcularSubtotalItem($(this));
        });

        function getValorTaxaSeAtiva(inputId) {
            const $checkbox = $('.taxa-frete-checkbox[data-target-input="' + inputId + '"]');
            if ($checkbox.length > 0 && $checkbox.is(':checked')) {
                return unformatCurrency($('#' + inputId).val());
            }
            return 0;
        }

        var taxaDomingo = getValorTaxaSeAtiva('taxa_domingo_feriado');
        var taxaMadrugada = getValorTaxaSeAtiva('taxa_madrugada');
        var taxaHorarioEspecial = getValorTaxaSeAtiva('taxa_horario_especial');
        var taxaHoraMarcada = getValorTaxaSeAtiva('taxa_hora_marcada');
        var freteTerreo = getValorTaxaSeAtiva('frete_terreo');
        var freteElevador = getValorTaxaSeAtiva('frete_elevador');
        var freteEscadas = getValorTaxaSeAtiva('frete_escadas');
        var descontoTotalGeral = getValorTaxaSeAtiva('desconto_total');

        var valorFinalCalculado = subtotalGeralItens - descontoTotalGeral + taxaDomingo + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada + freteTerreo + freteElevador + freteEscadas;

        if (subtotalGeralItens === 0 && valorFinalCalculado === 0) {
            $('#subtotal_geral_itens').text('A confirmar');
            $('#valor_final_display').val('').attr('placeholder', 'A confirmar');
        } else {
            $('#subtotal_geral_itens').text(formatCurrency(subtotalGeralItens));
            $('#valor_final_display').val(formatCurrency(valorFinalCalculado));
        }
    }

    // ✅ VALIDAÇÃO DE ESTOQUE NO EDIT
    function verificarEstoqueAntes(produto) {
        var quantidadeJaAdicionada = 0;
        $('#tabela_itens_orcamento .produto_id').each(function() {
            if ($(this).val() == produto.id) {
                var $row = $(this).closest('tr');
                quantidadeJaAdicionada += parseInt($row.find('.item-qtd').val()) || 0;
            }
        });
        
        var quantidadeTotal = quantidadeJaAdicionada + 1;
        
        $.ajax({
            url: `edit.php?id=${ORCAMENTO_ID}`,
            type: 'GET',
            dataType: 'json',
            data: { 
                ajax: 'verificar_estoque',
                produto_id: produto.id,
                quantidade: quantidadeTotal,
                orcamento_id: ORCAMENTO_ID
            },
            success: function(response) {
                if (response.disponivel) {
                    adicionarLinhaItemTabela(produto, 'PRODUTO');
                    $('#busca_produto').val('').focus();
                    $('#sugestoes_produtos').empty().hide();
                } else {
                    Swal.fire({
                        title: 'Estoque Insuficiente!',
                        text: `Produto: ${produto.nome_produto}\nEstoque disponível: ${response.estoque_disponivel}\nQuantidade solicitada: ${quantidadeTotal}`,
                        icon: 'warning',
                        confirmButtonText: 'Entendi'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro na verificação de estoque:", error);
                adicionarLinhaItemTabela(produto, 'PRODUTO');
                $('#busca_produto').val('').focus();
                $('#sugestoes_produtos').empty().hide();
            }
        });
    }

    function validarEstoqueQuantidade($row) {
        var produtoId = $row.find('.produto_id').val();
        var quantidadeAtual = parseInt($row.find('.item-qtd').val()) || 0;
        
        if (!produtoId || quantidadeAtual <= 0) return;
        
        var chaveValidacao = `${produtoId}_${quantidadeAtual}`;
        if ($row.data('ultima-validacao') === chaveValidacao) {
            return;
        }
        
        var quantidadeTotal = 0;
        $('#tabela_itens_orcamento .produto_id').each(function() {
            if ($(this).val() == produtoId) {
                var $outraRow = $(this).closest('tr');
                if ($outraRow[0] === $row[0]) {
                    quantidadeTotal += quantidadeAtual;
                } else {
                    quantidadeTotal += parseInt($outraRow.find('.item-qtd').val()) || 0;
                }
            }
        });
        
        $.ajax({
            url: `edit.php?id=${ORCAMENTO_ID}`,
            type: 'GET',
            dataType: 'json',
            data: { 
                ajax: 'verificar_estoque',
                produto_id: produtoId,
                quantidade: quantidadeTotal,
                orcamento_id: ORCAMENTO_ID
            },
            success: function(response) {
                $row.data('ultima-validacao', chaveValidacao);
                
                if (!response.disponivel) {
                    Swal.fire({
                        title: 'Estoque Insuficiente!',
                        text: `Estoque disponível: ${response.estoque_disponivel}\nQuantidade solicitada: ${quantidadeTotal}`,
                        icon: 'warning',
                        confirmButtonText: 'Entendi'
                    }).then(() => {
                        var quantidadeOriginal = $row.find('.item-qtd').data('valor-original') || 1;
                        $row.find('.item-qtd').val(quantidadeOriginal).focus();
                        $row.removeData('ultima-validacao');
                        calcularTotaisOrcamento();
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro na validação de estoque:", error);
            }
        });
    }

    // Event Listeners
    $('#busca_produto, #busca_categoria_produto').on('keyup change', carregarSugestoesProdutos);
    
    $('#sugestoes_produtos').on('click', '.item-sugestao-produto', function(e) { 
        e.preventDefault(); 
        if ($(e.target).closest('.foto-produto-sugestao').length > 0) { 
            var fotoUrl = $(this).data('foto-completa'); 
            if (fotoUrl) { 
                Swal.fire({ 
                    title: $(this).data('nome'), 
                    imageUrl: fotoUrl, 
                    imageAlt: 'Foto', 
                    imageWidth: '90%', 
                    confirmButtonText: 'Fechar' 
                }); 
            } 
            return; 
        } 
        var produtoId = $(this).data('id'); 
        var produtoJaExiste = false; 
        $('#tabela_itens_orcamento .produto_id').each(function() { 
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
            verificarEstoqueAntes(produto); 
        }; 
        if (produtoJaExiste) { 
            Swal.fire({ 
                title: 'Produto Repetido', 
                text: "Deseja adicionar este item novamente?", 
                icon: 'question', 
                showCancelButton: true, 
                confirmButtonText: 'Sim', 
                cancelButtonText: 'Não' 
            }).then((result) => { 
                if (result.isConfirmed) { 
                    adicionarItem(); 
                } 
            }); 
        } else { 
            adicionarItem(); 
        } 
    });

    $('#btn_adicionar_titulo_secao').click(function() { adicionarLinhaItemTabela(null, 'CABECALHO_SECAO'); });
    $('#btn_adicionar_item_manual').click(function() { adicionarLinhaItemTabela(null, 'PRODUTO'); });
    $('#tabela_itens_orcamento').on('click', '.btn_remover_item', function() { $(this).closest('tr').remove(); atualizarOrdemDosItens(); calcularTotaisOrcamento(); });
        $('#tabela_itens_orcamento').on('click', '.btn_obs_item', function() { 
        var $row = $(this).closest('tr'); 
        $row.find('.observacoes_item_label, .observacoes_item_input').toggle(); 
        if ($row.find('.observacoes_item_input').is(':visible')) { 
            $row.find('.observacoes_item_input').focus(); 
        } 
    });

    // ✅ EVENTO: Validar quando quantidade muda
    $('#tabela_itens_orcamento').on('input keyup change blur', '.item-qtd', function(e) {
        var $input = $(this);
        var $row = $input.closest('tr');
        
        clearTimeout($input.data('validacao-timeout'));
        
        $input.data('validacao-timeout', setTimeout(function() {
            var valorAtual = parseInt($input.val()) || 0;
            
            if (valorAtual > 0) {
                validarEstoqueQuantidade($row);
            }
        }, 800));
    });

    // === CONFIGURAÇÃO DOS CAMPOS DE TAXAS E FRETES ===
    const campoIdsTaxasFretes = [
        'taxa_domingo_feriado', 'taxa_madrugada', 'taxa_horario_especial',
        'taxa_hora_marcada', 'frete_terreo', 'frete_elevador', 'frete_escadas'
    ];

    function getAssociatedButton($campo) {
        return $campo.closest('.input-group').find('.btn-usar-padrao');
    }

    function atualizarEstadoVarinha($campo) {
        const $button = getAssociatedButton($campo);
        if (!$button.length) return;

        const usandoPadrao = $campo.data('usandoPadrao') === 'true';
        
        if (usandoPadrao) {
            $button.removeClass('btn-outline-secondary').addClass('btn-primary');
            $button.attr('title', 'Clique para voltar ao valor anterior/digitado');
        } else {
            $button.removeClass('btn-primary').addClass('btn-outline-secondary');
            $button.attr('title', 'Clique para usar valor padrão');
        }
    }

    function alternarValor($campo) {
        let usandoPadrao = $campo.data('usandoPadrao') === 'true';
        let valorPadraoNumerico = unformatCurrency($campo.data('valor-padrao'));

        if (usandoPadrao) {
            let valorOriginalArmazenado = $campo.data('valorOriginal');
            $campo.val(formatCurrency(valorOriginalArmazenado));
            $campo.data('usandoPadrao', 'false');
        } else {
            $campo.data('valorOriginal', unformatCurrency($campo.val()));
            $campo.val(formatCurrency(valorPadraoNumerico));
            $campo.data('usandoPadrao', 'true');
        }
        atualizarEstadoVarinha($campo);
        calcularTotaisOrcamento();
    }

    function toggleCampo($campo, $checkbox) {
        if ($checkbox.is(':checked')) {
            $campo.prop('disabled', false);

            let valorOriginalArmazenado = $campo.data('valorOriginal');
            let valorPadraoNumerico = unformatCurrency($campo.data('valor-padrao'));
            
            if (valorOriginalArmazenado > 0 && valorOriginalArmazenado !== valorPadraoNumerico) {
                $campo.val(formatCurrency(valorOriginalArmazenado));
                $campo.data('usandoPadrao', 'false');
            } else if (valorPadraoNumerico > 0) {
                $campo.val(formatCurrency(valorPadraoNumerico));
                $campo.data('usandoPadrao', 'true');
            } else {
                $campo.val(formatCurrency(0));
                $campo.data('usandoPadrao', 'false');
            }
            $campo.focus();
        } else {
            const currentValNum = unformatCurrency($campo.val());
            const valorPadraoNum = unformatCurrency($campo.data('valor-padrao'));

            if (currentValNum > 0 && $campo.data('usandoPadrao') === 'false' && currentValNum !== valorPadraoNum) {
                 $campo.data('valorOriginal', currentValNum);
            }
            
            $campo.prop('disabled', true);
            $campo.val(formatCurrency(0));
            $campo.data('usandoPadrao', 'false');
        }
        atualizarEstadoVarinha($campo);
        calcularTotaisOrcamento();
    }

    // Inicialização dos campos de taxa/frete
    campoIdsTaxasFretes.forEach(function(campoId) {
        const $campo = $('#' + campoId);
        const $checkbox = $('.taxa-frete-checkbox[data-target-input="' + campoId + '"]'); 

        if ($campo.length) {
            const initialValFromHTML = unformatCurrency($campo.val());
            $campo.data('valorOriginal', initialValFromHTML); 
            $campo.val(formatCurrency(initialValFromHTML));

            $campo.off('input').on('input', function() {
                $campo.data('usandoPadrao', 'false');
                atualizarEstadoVarinha($campo);
            }).off('blur').on('blur', function() {
                $campo.val(formatCurrency(unformatCurrency($campo.val())));
                calcularTotaisOrcamento(); 
            });
        }

        const $button = getAssociatedButton($campo);
        if ($button.length) {
            $button.off('click').on('click', function() {
                const targetInputId = $(this).data('target-input');
                const $targetCampo = $('#' + targetInputId);
                const targetCheckboxId = $(this).data('target-checkbox');
                const $targetCheckbox = $('#' + targetCheckboxId);
                
                if ($targetCampo.prop('disabled')) {
                    $targetCheckbox.prop('checked', true);
                    $targetCheckbox.trigger('change');
                } else {
                    alternarValor($targetCampo);
                }
            });
        }

        if ($checkbox.length) {
            $checkbox.off('change').on('change', function() {
                toggleCampo($campo, $checkbox);
            });
            toggleCampo($campo, $checkbox);
        }
        
        atualizarEstadoVarinha($campo);
    });

    // === Lógica para Ajuste Manual do Valor Final ===
    $('#ajuste_manual_valor_final').on('change', function() {
        const $motivoAjusteCampo = $('#campo_motivo_ajuste');
        const $descontoGeralCheckbox = $('#aplicar_desconto_geral');
        const $descontoGeralInput = $('#desconto_total');

        if ($(this).is(':checked')) {
            $motivoAjusteCampo.show();
            $descontoGeralCheckbox.prop('checked', true);
            $descontoGeralInput.prop('disabled', false);
            
            if (unformatCurrency($descontoGeralInput.val()) === 0) {
                $descontoGeralInput.val(formatCurrency(0));
            }
            $descontoGeralInput.focus();
        } else {
            $motivoAjusteCampo.hide();
            $descontoGeralCheckbox.prop('checked', false);
            $descontoGeralInput.prop('disabled', true);
            $descontoGeralInput.val(formatCurrency(0));
        }
        calcularTotaisOrcamento();
    }).trigger('change');

    // === Lógica para Desconto Geral ===
    $('#aplicar_desconto_geral').off('change').on('change', function() {
        const $descontoInput = $('#desconto_total');
        if ($(this).is(':checked')) {
            $descontoInput.prop('disabled', false);
            if (unformatCurrency($descontoInput.val()) === 0) {
                 $descontoInput.val(formatCurrency(0)); 
            }
            $descontoInput.focus();
        } else {
            $descontoInput.prop('disabled', true);
            $descontoInput.val(formatCurrency(0));
        }
        calcularTotaisOrcamento();
    });

    $('#desconto_total').off('blur').on('blur', function() {
        $(this).val(formatCurrency(unformatCurrency($(this).val())));
        calcularTotaisOrcamento();
    }).off('input').on('input', function() {
        calcularTotaisOrcamento();
    });

    const $descontoInput = $('#desconto_total');
    if ($('#aplicar_desconto_geral').is(':checked')) {
        $descontoInput.prop('disabled', false);
    } else {
        $descontoInput.prop('disabled', true);
        $descontoInput.val(formatCurrency(0));
    }
    $descontoInput.val(formatCurrency(unformatCurrency($descontoInput.val())));

    $(document).on('change keyup blur', '.item-qtd, .item-valor-unitario, .desconto_item', calcularTotaisOrcamento);

    // === INICIALIZAÇÕES GERAIS ===
    if (typeof $.fn.select2 === 'function') { 
        $('#cliente_id').select2({ 
            theme: 'bootstrap4', 
            language: 'pt-BR', 
            placeholder: 'Digite para buscar...', 
            allowClear: true, 
            minimumInputLength: 0, 
            ajax: { 
                url: `edit.php?id=${ORCAMENTO_ID}&ajax=buscar_clientes`, 
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
            dadosClienteAtual = e.params.data.clienteData; 
            $(this).find('option:selected').attr('data-cliente-full-data', JSON.stringify(dadosClienteAtual)); 
        }); 
    }

    if (typeof $.fn.datepicker === 'function') { 
        $('.datepicker').datepicker({ 
            format: 'dd/mm/yyyy', 
            language: 'pt-BR', 
            autoclose: true, 
            todayHighlight: true, 
            orientation: "bottom auto" 
        }); 
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
                    $('#data_validade_display').text('Validade: ' + dia + '/' + mes + '/' + ano); 
                    $('#data_validade_calculada_hidden').val(ano + '-' + mes + '-' + dia); 
                } 
            } 
        } else { 
            $('#data_validade_display').text(''); 
            $('#data_validade_calculada_hidden').val(''); 
        } 
    }

    $('#validade_dias').on('change keyup blur', calcularDataValidade);

    const diasDaSemana = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO']; 
    function exibirDiaSemana(inputId, displayId) { 
        var dataStr = $(inputId).val(); 
        if(dataStr) { 
            var partes = dataStr.split('/'); 
            if (partes.length === 3) { 
                var dataObj = new Date(partes[2], partes[1] - 1, partes[0]); 
                if (!isNaN(dataObj.valueOf())) { 
                    var diaSemana = diasDaSemana[dataObj.getDay()]; 
                    $(displayId).text(diaSemana).addClass('font-weight-bold').removeClass('text-danger text-success').addClass(dataObj.getDay() === 0 || dataObj.getDay() === 6 ? 'text-danger' : 'text-success'); 
                } 
            } 
        } 
    }

    $('#data_evento, #data_entrega, #data_devolucao_prevista').on('change dp.change', function() { 
        exibirDiaSemana('#' + $(this).attr('id'), '#' + $(this).attr('id').replace('data_', 'dia_semana_')); 
    }).trigger('change');
    
    $('#btnUsarEnderecoCliente').on('click', function() {
        if (!dadosClienteAtual) { carregarDadosClienteAtual(); }
        if (!dadosClienteAtual) { toastr.warning('Não foi possível obter os dados do cliente selecionado.'); return; }
        let enderecoCliente = (dadosClienteAtual.endereco || '').trim();
        if (dadosClienteAtual.cidade) { enderecoCliente += (enderecoCliente ? ', ' : '') + dadosClienteAtual.cidade.trim(); }
        if (enderecoCliente === '') { toastr.info('O cliente selecionado não possui um endereço em seu cadastro.'); return; }
        const $localEventoInput = $('#local_evento');
        const localEventoAtual = $localEventoInput.val().trim();
        if (localEventoAtual === enderecoCliente) {
            $localEventoInput.val(localEventoOriginal);
        } else {
            $localEventoInput.val(enderecoCliente);
        }
    });

    $('#formEditarOrcamento').on('keydown', function(e) { 
        if (e.keyCode === 13 && !$(e.target).is('textarea') && !$(e.target).is('[type=submit]')) { 
            e.preventDefault(); 
        } 
    });

    function atualizarOrdemDosItens() { 
        $('#tabela_itens_orcamento tbody tr').each(function(index) { 
            $(this).attr('data-index', index + 1); 
            $(this).find('input[name="ordem[]"]').val(index + 1); 
        }); 
    }

    $('#tabela_itens_orcamento tbody').sortable({ 
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
    
    calcularTotaisOrcamento();
    
    $('#tabela_itens_orcamento .item-qtd').each(function() {
        var valorOriginal = $(this).val();
        $(this).data('valor-original', valorOriginal);
    });

    // --- Lógica para o botão "Converter para Pedido" ---
    $('#btnConverterPedido').on('click', function(e) {
        e.preventDefault();
        const orcamentoIdParaConverter = $(this).data('orcamento-id');

        Swal.fire({
            title: 'Confirmar Conversão?',
            text: "Deseja realmente converter este Orçamento em um Pedido? Esta ação marcará o Orçamento como 'Convertido'.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Sim, converter!',
            cancelButtonText: 'Não, cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#btnConverterPedido').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Convertendo...');
                
                $.ajax({
                    url: `${BASE_URL}/views/orcamentos/converter_pedido.php`,
                    type: 'POST',
                    dataType: 'json',
                    data: { orcamento_id: orcamentoIdParaConverter },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: response.message + (response.pedido_numero ? ' Pedido #' + response.pedido_numero + ' criado.' : ''),
                                confirmButtonText: 'OK'
                            }).then(() => {
                                if (response.pedido_id) {
                                    window.location.href = `${BASE_URL}/views/pedidos/show.php?id=${response.pedido_id}`;
                                } else {
                                    location.reload(); 
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: response.message
                            }).then(() => {
                                $('#btnConverterPedido').prop('disabled', false).html('<i class="fas fa-arrow-alt-circle-right mr-1"></i> Converter para Pedido');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro na requisição AJAX:", status, error, xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro de Conexão!',
                            text: 'Não foi possível converter o orçamento. Verifique sua conexão ou tente novamente.'
                        }).then(() => {
                            $('#btnConverterPedido').prop('disabled', false).html('<i class="fas fa-arrow-alt-circle-right mr-1"></i> Converter para Pedido');
                        });
                    }
                });
            }
        });
    });
});
JS;

include_once __DIR__ . '/../includes/footer.php';
?>