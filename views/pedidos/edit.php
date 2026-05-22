<?php
$page_title = "Editar Pedido";

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/EstoqueMovimentacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$clienteModel = new Cliente($db);
$pedidoModel = new Pedido($db);
$orcamentoModel = new Orcamento($db);
$estoqueModel = new EstoqueMovimentacao($db);

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

// Metadados dos produtos para manter a montagem de conjuntos no edit
$produtoMetaPedido = [];
$produtoIdsPedido = [];
foreach ($itensPedido as $itemMetaPedido) {
    if (!empty($itemMetaPedido['produto_id'])) {
        $produtoIdsPedido[(int)$itemMetaPedido['produto_id']] = true;
    }
}
if (!empty($produtoIdsPedido)) {
    try {
        $idsMeta = array_keys($produtoIdsPedido);
        $placeholdersMeta = implode(',', array_fill(0, count($idsMeta), '?'));
        $stmtMeta = $db->prepare("SELECT p.id, COALESCE(p.eh_conjunto, 0) AS eh_conjunto, p.subcategoria_id, c.id AS categoria_id
                                  FROM produtos p
                                  LEFT JOIN subcategorias s ON s.id = p.subcategoria_id
                                  LEFT JOIN categorias c ON c.id = s.categoria_id
                                  WHERE p.id IN ($placeholdersMeta)");
        foreach ($idsMeta as $idxMeta => $produtoIdMeta) {
            $stmtMeta->bindValue($idxMeta + 1, (int)$produtoIdMeta, PDO::PARAM_INT);
        }
        $stmtMeta->execute();
        foreach ($stmtMeta->fetchAll(PDO::FETCH_ASSOC) as $linhaMetaProduto) {
            $produtoMetaPedido[(int)$linhaMetaProduto['id']] = $linhaMetaProduto;
        }
    } catch (PDOException $e) {
        error_log('[pedidos/edit.php] Falha ao carregar metadados de produtos: ' . $e->getMessage());
    }
}

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


// --- AJAX para salvar cliente via modal ---
if (isset($_POST['ajax']) && $_POST['ajax'] === 'salvar_cliente_modal') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $clienteModel->nome = trim($_POST['nome'] ?? '');
        $clienteModel->telefone = !empty($_POST['telefone']) ? trim($_POST['telefone']) : null;
        $clienteModel->email = !empty($_POST['email']) ? trim($_POST['email']) : null;
        $clienteModel->cpf_cnpj = !empty($_POST['cpf_cnpj']) ? trim($_POST['cpf_cnpj']) : null;
        $clienteModel->endereco = !empty($_POST['endereco']) ? trim($_POST['endereco']) : null;
        $clienteModel->cidade = !empty($_POST['cidade']) ? trim($_POST['cidade']) : null;
        $clienteModel->observacoes = !empty($_POST['observacoes']) ? trim($_POST['observacoes']) : null;

        if ($clienteModel->nome === '') {
            echo json_encode(['success' => false, 'message' => 'O nome do cliente é obrigatório.']);
            exit;
        }

        if (!empty($clienteModel->email) && !filter_var($clienteModel->email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'O e-mail informado é inválido.']);
            exit;
        }

        if ($clienteModel->create()) {
            echo json_encode([
                'success' => true,
                'message' => "Cliente cadastrado com sucesso!",
                'cliente' => [
                    'id' => $clienteModel->id,
                    'nome' => $clienteModel->nome,
                    'telefone' => $clienteModel->telefone,
                    'email' => $clienteModel->email,
                    'cpf_cnpj' => $clienteModel->cpf_cnpj,
                    'endereco' => $clienteModel->endereco,
                    'cidade' => $clienteModel->cidade,
                    'observacoes' => $clienteModel->observacoes
                ]
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Não foi possível cadastrar o cliente.']);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erro AJAX salvar_cliente_modal: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar cliente.']);
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

        $sql = "SELECT
                    p.id,
                    p.codigo,
                    p.nome_produto,
                    p.descricao_detalhada,
                    p.preco_locacao,
                    p.quantidade_total,
                    p.foto_path,
                    p.tipo_produto,
                    COALESCE(p.eh_conjunto, 0) AS eh_conjunto,
                    p.subcategoria_id,
                    c.id AS categoria_id
                FROM produtos p
                LEFT JOIN subcategorias sc ON sc.id = p.subcategoria_id
                LEFT JOIN categorias c ON c.id = sc.categoria_id";

        $conditions = [];
        $executeParams = [];

        // Não mostrar componentes internos na busca operacional do pedido.
        // Ex.: Pufe Estrutura, Capa Pufe Azul, Recheio Almofada etc.
        // Eles continuam existindo no banco e são usados no cálculo de estoque composto.
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

        foreach ($produtos_ajax as &$produto_item) {
            if (!empty($produto_item['foto_path']) && $produto_item['foto_path'] !== "null" && trim($produto_item['foto_path']) !== "") {
                $foto_path_limpo = ltrim($produto_item['foto_path'], '/');
                $produto_item['foto_path_completo'] = $base_url_config . '/' . $foto_path_limpo;
            } else {
                $produto_item['foto_path_completo'] = null;
            }

            $produto_item['eh_conjunto'] = isset($produto_item['eh_conjunto']) ? (int)$produto_item['eh_conjunto'] : 0;
            $produto_item['categoria_id'] = isset($produto_item['categoria_id']) ? (int)$produto_item['categoria_id'] : null;
            $produto_item['subcategoria_id'] = isset($produto_item['subcategoria_id']) ? (int)$produto_item['subcategoria_id'] : null;
            $produto_item['regras_conjunto'] = [];

            if ($produto_item['eh_conjunto'] === 1) {
                $stmtRegras = $db->prepare("SELECT id, nome_grupo, categoria_id, subcategoria_id, quantidade_por_conjunto, obrigatorio, ordem, observacoes
                                            FROM produto_conjunto_grupos
                                            WHERE produto_conjunto_id = ?
                                            ORDER BY ordem ASC, id ASC");
                $stmtRegras->execute([(int)$produto_item['id']]);
                $produto_item['regras_conjunto'] = $stmtRegras->fetchAll(PDO::FETCH_ASSOC);
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



// --- AJAX para buscar grupos/regras de um conjunto comercial ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_grupos_conjunto') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $produto_conjunto_id = isset($_GET['produto_id']) ? (int) $_GET['produto_id'] : 0;

        if ($produto_conjunto_id <= 0) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT
                    pcg.id,
                    pcg.nome_grupo,
                    pcg.categoria_id,
                    c.nome AS categoria_nome,
                    pcg.subcategoria_id,
                    s.nome AS subcategoria_nome,
                    pcg.quantidade_por_conjunto,
                    pcg.obrigatorio,
                    pcg.ordem,
                    pcg.observacoes
                FROM produto_conjunto_grupos pcg
                LEFT JOIN categorias c ON c.id = pcg.categoria_id
                LEFT JOIN subcategorias s ON s.id = pcg.subcategoria_id
                WHERE pcg.produto_conjunto_id = :produto_conjunto_id
                ORDER BY pcg.ordem ASC, pcg.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':produto_conjunto_id', $produto_conjunto_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Erro AJAX buscar_grupos_conjunto pedido/edit: " . $e->getMessage());
        echo json_encode(['error' => 'Erro no banco de dados ao buscar regras do conjunto.']);
        exit;
    }
}

// --- 5. AJAX para verificar estoque / disponibilidade temporal no edit do pedido ---
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
        $ignorar_pedido_id = isset($_GET['ignorar_pedido_id']) ? (int) $_GET['ignorar_pedido_id'] : $pedidoId;

        $itens_contexto_atual = [];
        if (isset($_GET['itens_contexto'])) {
            $jsonContexto = $_GET['itens_contexto'];
            if (is_string($jsonContexto) && trim($jsonContexto) !== '') {
                $dadosContexto = json_decode($jsonContexto, true);
                if (is_array($dadosContexto)) {
                    foreach ($dadosContexto as $itemCtx) {
                        if (!is_array($itemCtx)) {
                            continue;
                        }
                        $produtoCtxId = isset($itemCtx['produto_id']) ? (int) $itemCtx['produto_id'] : 0;
                        $quantidadeCtx = isset($itemCtx['quantidade']) ? (int) $itemCtx['quantidade'] : 0;
                        if ($produtoCtxId > 0 && $quantidadeCtx > 0) {
                            $itens_contexto_atual[] = [
                                'produto_id' => $produtoCtxId,
                                'quantidade' => $quantidadeCtx
                            ];
                        }
                    }
                }
            }
        }

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
            $ignorar_pedido_id,
            $itens_contexto_atual
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

        // Itens do pedido com suporte a CONJUNTO e ITEM_CONJUNTO.
        // Importante: Pedido::salvarItens() faz o vínculo item_pai_id dos filhos com o último CONJUNTO anterior.
        $itens = [];
        if (isset($_POST['tipo_linha']) && is_array($_POST['tipo_linha'])) {
            foreach ($_POST['tipo_linha'] as $index => $tipo_linha_post) {
                $tipo_linha_atual = trim((string)$tipo_linha_post);
                $ordem_atual = isset($_POST['ordem'][$index]) ? (int) $_POST['ordem'][$index] : ($index + 1);

                $item_data = [
                    'produto_id' => null,
                    'nome_produto_manual' => null,
                    'quantidade' => 0,
                    'preco_unitario' => 0.00,
                    'desconto' => 0.00,
                    'preco_final' => 0.00,
                    'usa_preco_no_total' => 1,
                    'exibir_cliente' => 1,
                    'exibir_producao' => 1,
                    'grupo_conjunto' => isset($_POST['grupo_conjunto'][$index]) ? trim((string)$_POST['grupo_conjunto'][$index]) : null,
                    'tipo' => 'locacao',
                    'observacoes' => isset($_POST['observacoes_item'][$index]) ? trim((string)$_POST['observacoes_item'][$index]) : null,
                    'tipo_linha' => $tipo_linha_atual,
                    'ordem' => $ordem_atual
                ];

                if ($tipo_linha_atual === 'CABECALHO_SECAO') {
                    $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim((string)$_POST['nome_produto_display'][$index]) : 'Título não informado';
                    $item_data['tipo'] = null;
                    $item_data['usa_preco_no_total'] = 0;
                } elseif (in_array($tipo_linha_atual, ['PRODUTO', 'CONJUNTO', 'ITEM_CONJUNTO'], true)) {
                    $item_data['produto_id'] = isset($_POST['produto_id'][$index]) && !empty($_POST['produto_id'][$index]) ? (int) $_POST['produto_id'][$index] : null;

                    if ($item_data['produto_id'] === null) {
                        $item_data['nome_produto_manual'] = isset($_POST['nome_produto_display'][$index]) ? trim((string)$_POST['nome_produto_display'][$index]) : 'Item manual';
                    }

                    $item_data['quantidade'] = isset($_POST['quantidade'][$index]) ? (int) $_POST['quantidade'][$index] : 0;
                    if ($item_data['quantidade'] <= 0) {
                        $nomeProdutoErro = isset($_POST['nome_produto_display'][$index]) ? trim((string)$_POST['nome_produto_display'][$index]) : 'Produto sem nome';
                        throw new Exception("Há produto com quantidade zero no pedido: " . $nomeProdutoErro . ". Ajuste a quantidade ou remova a linha antes de salvar.");
                    }

                    $item_data['tipo'] = $_POST['tipo_item'][$index] ?? 'locacao';

                    if ($tipo_linha_atual === 'ITEM_CONJUNTO') {
                        // Filho de conjunto consulta estoque, mas não soma valor.
                        $item_data['preco_unitario'] = 0.00;
                        $item_data['desconto'] = 0.00;
                        $item_data['preco_final'] = 0.00;
                        $item_data['usa_preco_no_total'] = 0;
                    } else {
                        $item_data['preco_unitario'] = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                        $item_data['desconto'] = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');
                        $item_data['preco_final'] = $item_data['quantidade'] * ($item_data['preco_unitario'] - $item_data['desconto']);
                        $item_data['usa_preco_no_total'] = 1;
                    }
                } else {
                    error_log("Tipo de linha desconhecido ou inválido no índice {$index}: '{$tipo_linha_atual}' - Item ignorado.");
                    continue;
                }

                $itens[] = $item_data;
            }
        }

        if (!$pedidoModel->salvarItens($pedidoId, $itens)) {
            throw new Exception("Falha ao salvar os itens do pedido. Os dados foram preservados pela transação.");
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

                        <div class="itens-scroll-container mt-3">
                            <div class="table-responsive mb-0">
                            <table class="table table-bordered table-hover mb-0" id="tabela_itens_pedido">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 34%;">Produto/Serviço/Seção <span class="text-danger">*</span></th>
                                        <th style="width: 20%;">Status</th>
                                        <th style="width: 6%;">Qtd. <span class="text-danger">*</span></th>
                                        <th style="width: 11%;">Vlr. Unit. (R$)</th>
                                        <th style="width: 8%;">Desc. Item (R$)</th>
                                        <th style="width: 9%;">Subtotal (R$)</th>
                                        <th style="width: 12%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
    <?php if (empty($itensPedido)): ?>
        <tr class="no-items-row">
            <td colspan="7" class="text-center text-muted">Nenhum item adicionado a este pedido ainda.</td>
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
                if (!empty($item['nome_produto_manual'])) {
                    $nomeBase = $item['nome_produto_manual'];
                } elseif (!empty($item['nome_produto_catalogo'])) {
                    $nomeBase = $item['nome_produto_catalogo'];
                } else {
                    $nomeBase = 'Item sem nome';
                }
                $nomeDisplay = htmlspecialchars($nomeBase);
                $precoUnitario = number_format($item['preco_unitario'] ?? 0, 2, ',', '.');
                $descontoItem = number_format($item['desconto'] ?? 0, 2, ',', '.');
                // O subtotal do item será recalculado pelo JS, mas preenchemos para visualização inicial
                $subtotalCalc = ($item['quantidade'] ?? 0) * (($item['preco_unitario'] ?? 0) - ($item['desconto'] ?? 0));
                $subtotalItem = number_format($subtotalCalc, 2, ',', '.');
            ?>

            <?php if ($tipoLinha === 'CABECALHO_SECAO'): ?>
                <tr class="item-pedido-row item-titulo-secao" data-index="<?= $index + 1 ?>" data-tipo-linha="<?= $tipoLinha ?>" style="background-color: #e7f1ff !important;">
                    <td colspan="6">
                        <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                        <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_titulo_secao" value="<?= $nomeDisplay ?>" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);">
                        <input type="hidden" name="produto_id[]" value="">
                        <input type="hidden" name="tipo_linha[]" value="<?= $tipoLinha ?>">
                        <input type="hidden" name="ordem[]" value="<?= $index + 1 ?>">
                        <input type="hidden" name="quantidade[]" value="0">
                        <input type="hidden" name="tipo_item[]" value="">
                        <input type="hidden" name="grupo_conjunto[]" value="">
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
                    $isLinhaConjunto = ($tipoLinha === 'CONJUNTO');
                    $isLinhaItemConjunto = ($tipoLinha === 'ITEM_CONJUNTO');
                    $classeConjunto = $isLinhaConjunto ? ' item-conjunto-row' : ($isLinhaItemConjunto ? ' item-conjunto-filho-row' : '');
                    $estiloLinha = $isLinhaConjunto
                        ? 'background-color: #eef6ff !important; border-left: 4px solid #0d6efd;'
                        : ($isLinhaItemConjunto ? 'background-color: #f8fbff !important;' : 'background-color: #ffffff !important;');
                    $precoReadonly = $isLinhaItemConjunto ? 'readonly' : '';
                    if ($isLinhaItemConjunto) {
                        $precoUnitario = '0,00';
                        $descontoItem = '0,00';
                        $subtotalItem = '0,00';
                    }
                    $produtoMetaLinha = !empty($item['produto_id']) && isset($produtoMetaPedido[(int)$item['produto_id']]) ? $produtoMetaPedido[(int)$item['produto_id']] : [];
                    $categoriaIdLinha = $produtoMetaLinha['categoria_id'] ?? '';
                    $subcategoriaIdLinha = $produtoMetaLinha['subcategoria_id'] ?? '';
                    $ehConjuntoLinha = $isLinhaConjunto ? 1 : (int)($produtoMetaLinha['eh_conjunto'] ?? 0);
                ?>
                <tr class="item-pedido-row<?= $classeConjunto ?>" data-index="<?= $index + 1 ?>" data-tipo-linha="<?= $tipoLinha ?>" data-categoria-id="<?= htmlspecialchars((string)$categoriaIdLinha) ?>" data-subcategoria-id="<?= htmlspecialchars((string)$subcategoriaIdLinha) ?>" data-eh-conjunto="<?= $ehConjuntoLinha ?>" style="<?= $estiloLinha ?>">
                    <td style="<?= $isLinhaItemConjunto ? 'padding-left: 28px;' : '' ?>">
                        <?php if ($isLinhaItemConjunto): ?>
                            <span class="text-primary mr-1" title="Item interno do conjunto"><i class="fas fa-level-up-alt fa-rotate-90"></i></span>
                        <?php endif; ?>
                        <?php if (!empty($item['foto_path_completo'])): ?>
                            <img src="<?= htmlspecialchars($item['foto_path_completo']) ?>" alt="Miniatura" class="foto-produto-linha" data-foto-completa="<?= htmlspecialchars($item['foto_path_completo']) ?>" data-nome-produto="<?= $nomeDisplay ?>" title="Clique para ampliar a foto" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle; cursor: zoom-in;">
                        <?php endif; ?>
                        <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_produto_display <?= $isLinhaConjunto ? 'font-weight-bold text-primary' : '' ?>" value="<?= $nomeDisplay ?>" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: <?= $isLinhaItemConjunto ? 'calc(100% - 86px)' : 'calc(100% - 65px)' ?>; vertical-align: middle;" <?= !$isItemManual ? 'readonly' : '' ?>>
                        <input type="hidden" name="produto_id[]" class="produto_id" value="<?= htmlspecialchars($item['produto_id'] ?? '') ?>">
                        <input type="hidden" name="tipo_linha[]" value="<?= $tipoLinha ?>">
                        <input type="hidden" name="ordem[]" value="<?= $index + 1 ?>">
                        <input type="hidden" name="tipo_item[]" value="<?= htmlspecialchars($item['tipo'] ?? 'locacao') ?>">
                        <input type="hidden" name="grupo_conjunto[]" value="<?= htmlspecialchars($item['grupo_conjunto'] ?? '') ?>">
                        <?php if ($isLinhaConjunto): ?>
                            <small class="text-primary font-weight-bold ml-1"><i class="fas fa-layer-group"></i> Conjunto comercial</small>
                        <?php endif; ?>
                        <?php if ($isLinhaItemConjunto): ?>
                            <small class="form-text text-muted">Item interno do conjunto · sem preço</small>
                        <?php endif; ?>
                        <small class="form-text text-muted observacoes_item_label" style="<?= $observacoesEstilo ?>">Obs. Item:</small>
                        <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="<?= $observacoesEstilo ?>" placeholder="Observação do item" value="<?= htmlspecialchars($item['observacoes'] ?? '') ?>">
                    </td>
                    <td class="status-disponibilidade-cell text-center"><div class="disponibilidade-contexto status-neutro" style="display:none;"></div></td>
                    <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantity-input item-qtd text-center" value="<?= htmlspecialchars($item['quantidade'] ?? 0) ?>" min="0" style="width: 70px;" data-valor-original="<?= htmlspecialchars($item['quantidade'] ?? 0) ?>"></td>
                    <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="<?= $precoUnitario ?>" <?= $precoReadonly ?>></td>
                    <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="<?= $descontoItem ?>" <?= $precoReadonly ?>></td>
                    <td class="subtotal_item_display text-right font-weight-bold"><?= $subtotalItem ?></td>
                    <td>
                        <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                        <?php if ($isLinhaConjunto): ?>
                            <button type="button" class="btn btn-xs btn-primary btn_montar_conjunto" title="Montar/continuar conjunto"><i class="fas fa-plus-circle"></i></button>
                            <button type="button" class="btn btn-xs btn-outline-primary btn_trocar_produto" title="Trocar conjunto desta linha"><i class="fas fa-exchange-alt"></i></button>
                            <button type="button" class="btn btn-xs btn-secondary btn_encerrar_conjunto" title="Encerrar montagem"><i class="fas fa-check"></i></button>
                        <?php else: ?>
                            <button type="button" class="btn btn-xs btn-outline-primary btn_trocar_produto" title="Trocar produto desta linha"><i class="fas fa-exchange-alt"></i></button>
                        <?php endif; ?>
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
                                        <td colspan="5" class="text-right"><strong>Subtotal dos Itens:</strong></td>
                                        <td id="subtotal_geral_itens" class="text-right font-weight-bold">A confirmar</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
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
                                            <label for="valor_pago">Complemento Pago (R\$)</label>
                                            <input type="text" class="form-control money-input text-right"
                                                id="valor_pago" name="valor_pago" placeholder="0,00"
                                                value="<?= htmlspecialchars(number_format($pedidoDados['valor_pago'] ?? 0, 2, ',', '.')) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="data_pagamento_final">Data Pagto. Complemento</label>
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


                    .painel-disponibilidade {
                        border: none;
                        overflow: hidden;
                        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
                    }
                    .painel-disponibilidade .card-header {
                        border-bottom: none;
                    }
                    .painel-disponibilidade.painel-neutro { background: linear-gradient(135deg, #475569, #334155); color: #fff; }
                    .painel-disponibilidade.painel-ok { background: linear-gradient(135deg, #0f766e, #0ea5a4); color: #fff; }
                    .painel-disponibilidade.painel-atencao { background: linear-gradient(135deg, #b45309, #f59e0b); color: #fff; }
                    .painel-disponibilidade.painel-indisponivel { background: linear-gradient(135deg, #be123c, #e11d48); color: #fff; }
                    .painel-status-badge {
                        display: inline-flex;
                        align-items: center;
                        padding: 4px 10px;
                        border-radius: 999px;
                        font-size: 0.74rem;
                        font-weight: 800;
                        letter-spacing: 0.04em;
                        text-transform: uppercase;
                        background: rgba(255,255,255,0.18);
                        border: 1px solid rgba(255,255,255,0.28);
                    }
                    .painel-disponibilidade-grid {
                        display: grid;
                        grid-template-columns: repeat(4, minmax(0, 1fr));
                        gap: 10px;
                    }
                    .painel-disponibilidade .painel-box {
                        background: rgba(255,255,255,0.12);
                        border: 1px solid rgba(255,255,255,0.18);
                        border-radius: 12px;
                        padding: 10px 12px;
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
                    .item-pedido-row.row-status-ok td {
                        background: #f4fbff !important;
                    }
                    .disponibilidade-contexto.status-atencao {
                        background: #fff5df;
                        border: 1px solid #ff9f1c;
                        color: #9a5b00;
                    }
                    .item-pedido-row.row-status-atencao td {
                        background: #fffaf0 !important;
                    }
                    .disponibilidade-contexto.status-indisponivel {
                        background: #ffe8ee;
                        border: 1px solid #e11d48;
                        color: #a10f2b;
                    }
                    .item-pedido-row.row-status-indisponivel td {
                        background: #fff4f6 !important;
                    }
                    @media (max-width: 991.98px) {
                        .painel-disponibilidade-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                    }
                    @media (max-width: 575.98px) {
                        .painel-disponibilidade-grid { grid-template-columns: 1fr; }
                    }

                
                    /* Área de itens com busca normal e rolagem própria na tabela */
                    .pedido-busca-barra {
                        position: relative;
                        z-index: 1020;
                        background: #ffffff;
                    }
                    #sugestoes_produtos { z-index: 1060 !important; }
                    .row-em-troca-produto td {
                        outline: 2px solid #007bff;
                        outline-offset: -2px;
                        background-color: #eef6ff !important;
                    }
                    .itens-scroll-container {
    height: clamp(480px, calc(100vh - 155px), 840px);
    min-height: 420px;
    overflow-y: auto;
    overflow-x: auto;
    scrollbar-gutter: stable;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #ffffff;
}
                    
                    .itens-scroll-container thead th {
                        position: sticky;
                        top: 0;
                        z-index: 5;
                        background: #e9ecef;
                        vertical-align: middle !important;
                    }
                    .itens-scroll-container tfoot td {
                        position: sticky;
                        bottom: 0;
                        z-index: 4;
                        background: #ffffff;
                    }
                    @media (max-height: 760px) {
    .itens-scroll-container {
        height: clamp(420px, calc(100vh - 135px), 700px);
        min-height: 380px;
    }
}

                    /* Status compacto de disponibilidade na coluna própria */
                    #tabela_itens_pedido th,
                    #tabela_itens_pedido td { vertical-align: middle !important; }
                    .status-disponibilidade-cell {
                        min-width: 210px;
                        vertical-align: middle !important;
                    }
                    .status-disponibilidade-cell .disponibilidade-contexto {
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 100%;
                        min-height: 30px;
                        margin-top: 0 !important;
                        padding: 5px 8px;
                        border-radius: 8px;
                        font-size: 0.69rem;
                        font-weight: 800;
                        line-height: 1.1;
                        text-align: center;
                        white-space: nowrap;
                    }
                    .disponibilidade-contexto .status-principal {
                        display: inline;
                        font-size: 0.69rem;
                        letter-spacing: 0.02em;
                        text-transform: uppercase;
                        font-weight: 900;
                    }
                    .disponibilidade-contexto .status-detalhe {
                        display: inline;
                        margin-left: 4px;
                        font-size: 0.68rem;
                        font-weight: 800;
                        opacity: 0.96;
                    }
                    .disponibilidade-contexto.status-neutro {
                        background: #eef2f7;
                        border: 1px solid #cbd5e1;
                        color: #475569;
                    }
                    #tabela_itens_pedido td:last-child {
                        white-space: nowrap;
                        min-width: 105px;
                    }
                    #tabela_itens_pedido td:last-child .btn,
                    #tabela_itens_pedido td:last-child .drag-handle {
                        display: inline-block;
                        margin-right: 5px !important;
                        vertical-align: middle;
                    }
                    #tabela_itens_pedido .desconto_item { min-width: 76px; }
                    #tabela_itens_pedido .subtotal_item_display { min-width: 82px; }

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
    // Suprimir warnings do Select2
    $.fn.select2.defaults.set('theme', 'bootstrap4');
    $.fn.select2.defaults.set('width', '100%');
    $.fn.select2.defaults.set('escapeMarkup', function (markup) { return markup; });
       // ✅ DEFINIR itemIndex NO INÍCIO
    let itemIndex = 0;
    
    // Contar itens existentes para inicializar corretamente
    const linhasExistentes = document.querySelectorAll('#tabela_itens_pedido tbody tr.item-pedido-row');
    itemIndex = linhasExistentes.length;
    let disponibilidadeRequestSeq = 0;
    let conjuntoAtivoIndex = null;
    let conjuntoAtivoNome = '';
    
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
                if (!['CABECALHO_SECAO', 'ITEM_CONJUNTO'].includes($(this).data('tipo-linha') || '')) {
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
        if (['CABECALHO_SECAO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '')) {
            $row.find('.subtotal_item_display').text(formatCurrency(0));
            return 0;
        }
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
                        let fotoHtml = produto.foto_path_completo
                            ? `<img src="${escapeHtml(produto.foto_path_completo)}" alt="Miniatura" onerror="this.style.display='none';" class="img-thumbnail mr-2 foto-produto-sugestao" style="width: 40px; height: 40px; object-fit: cover; cursor:pointer;" data-foto-completa="${escapeHtml(produto.foto_path_completo)}" data-nome-produto="${escapeHtml(produto.nome_produto || 'Produto')}">`
                            : `<span class="mr-2 d-inline-block text-center text-muted" style="width: 40px; height: 40px; line-height:40px; border:1px solid #eee; font-size:0.8em;"><i class="fas fa-camera"></i></span>`;

                        let fotoPathParaDataAttribute = produto.foto_path_completo ? produto.foto_path_completo : '';
                        let ehConjunto = parseInt(produto.eh_conjunto || 0, 10) === 1;
                        let textoEstoque = ehConjunto
                            ? '<small class="d-block text-primary"><i class="fas fa-layer-group"></i> Conjunto comercial</small><small class="d-block text-muted">Selecione para montar os itens internos</small>'
                            : (produto.tipo_produto === 'COMPOSTO'
                                ? '<small class="d-block text-info">Estoque por componentes</small><small class="d-block text-muted">Selecione para consultar capa e estrutura</small>'
                                : (produto.quantidade_total !== null ? '<small class="d-block text-info">Estoque: ' + produto.quantidade_total + '</small>' : ''));

                        $('#sugestoes_produtos').append(
                            `<a href="#" class="list-group-item list-group-item-action d-flex align-items-center item-sugestao-produto py-2"
                                data-id="${produto.id}"
                                data-nome="${escapeHtml(produto.nome_produto || 'Sem nome')}"
                                data-codigo="${escapeHtml(produto.codigo || '')}"
                                data-preco="${preco}"
                                data-foto-completa="${escapeHtml(fotoPathParaDataAttribute)}"
                                data-eh-conjunto="${ehConjunto ? 1 : 0}"
                                data-categoria-id="${produto.categoria_id || ''}"
                                data-subcategoria-id="${produto.subcategoria_id || ''}">
                                ${fotoHtml}
                                <div class="flex-grow-1">
                                    <strong>${escapeHtml(produto.nome_produto || 'Sem nome')}</strong>
                                    ${produto.codigo ? '<small class="d-block text-muted">Cód: ' + escapeHtml(produto.codigo) + '</small>' : ''}
                                    ${textoEstoque}
                                </div>
                                <span class="ml-auto text-primary font-weight-bold">R$ ${preco.toFixed(2).replace('.', ',')}</span>
                            </a>`
                        );
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


    function escapeHtml(text) {
        if (text === null || text === undefined) { return ''; }
        return $('<div>').text(text).html();
    }

    function regraCasaComProduto(regra, produto) {
        if (!regra || !produto) { return false; }
        const regraSubcategoria = parseInt(regra.subcategoria_id || 0, 10) || 0;
        const regraCategoria = parseInt(regra.categoria_id || 0, 10) || 0;
        const produtoSubcategoria = parseInt(produto.subcategoria_id || 0, 10) || 0;
        const produtoCategoria = parseInt(produto.categoria_id || 0, 10) || 0;
        if (regraSubcategoria > 0) { return produtoSubcategoria === regraSubcategoria; }
        if (regraCategoria > 0) { return produtoCategoria === regraCategoria; }
        return false;
    }

    function carregarRegrasConjunto(produtoId, $rowConjunto, callback) {
        if (!$rowConjunto || !$rowConjunto.length || !produtoId) {
            if (typeof callback === 'function') { callback([]); }
            return;
        }

        $.ajax({
            url: `edit.php?id=${PEDIDO_ID}`,
            type: 'GET',
            dataType: 'json',
            data: {
                ajax: 'buscar_grupos_conjunto',
                produto_id: produtoId
            },
            success: function(grupos) {
                if (!Array.isArray(grupos)) { grupos = []; }

                grupos = grupos.map(function(grupo) {
                    grupo.categoria_id = parseInt(grupo.categoria_id || 0, 10) || 0;
                    grupo.subcategoria_id = parseInt(grupo.subcategoria_id || 0, 10) || 0;
                    grupo.quantidade_por_conjunto = parseFloat(grupo.quantidade_por_conjunto || 0) || 0;
                    return grupo;
                });

                $rowConjunto.data('regras-conjunto', grupos);
                $rowConjunto.attr('data-regras-carregadas', '1');
                $rowConjunto.find('.regras-conjunto-resumo').remove();

                const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
                $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').remove();

                if (!grupos.length) {
                    $rowConjunto.find('td:first small.text-primary').after('<small class="form-text text-warning regras-conjunto-resumo">Sem grupos de montagem cadastrados.</small>');
                    if (typeof callback === 'function') { callback(grupos); }
                    return;
                }

                let resumo = '<small class="form-text text-muted regras-conjunto-resumo">';
                resumo += grupos.map(function(grupo) {
                    return escapeHtml(grupo.nome_grupo || 'Grupo') + ': ' + (grupo.quantidade_por_conjunto || 0) + ' por conjunto';
                }).join('<br>');
                resumo += '</small>';
                $rowConjunto.find('td:first small.text-primary').after(resumo);

                let $referencia = $rowConjunto;
                grupos.forEach(function(grupo) {
                    const qtdNecessaria = (parseFloat(grupo.quantidade_por_conjunto || 0) || 0) * (parseInt($rowConjunto.find('.item-qtd').val(), 10) || 0);
                    const guiaHtml = `<tr class="conjunto-grupo-guia" data-conjunto-pai-index="${conjuntoIndex}" data-categoria-id="${grupo.categoria_id || ''}" data-subcategoria-id="${grupo.subcategoria_id || ''}" data-grupo-id="${grupo.id || ''}" style="cursor:pointer;">
                        <td colspan="6" style="padding-left: 28px;">
                            <span class="text-primary font-weight-bold"><i class="fas fa-layer-group"></i> ${escapeHtml(grupo.nome_grupo || 'Grupo')}</span>
                            <span class="ml-2 text-muted">${grupo.quantidade_por_conjunto || 0} por conjunto</span>
                        </td>
                        <td class="text-right"><span class="badge badge-pill badge-warning grupo-status-badge">Faltam ${qtdNecessaria}</span></td>
                    </tr>`;
                    const $guia = $(guiaHtml);
                    $guia.insertAfter($referencia);
                    $referencia = $guia;
                });

                atualizarGuiasConjunto($rowConjunto);

                if (typeof callback === 'function') { callback(grupos); }
            },
            error: function() {
                Swal.fire('Erro', 'Não foi possível carregar as regras do conjunto.', 'error');
                if (typeof callback === 'function') { callback([]); }
            }
        });
    }

    function obterRegraSelecionadaDoConjunto($rowConjunto) {
        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        const $guia = $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia.table-active[data-conjunto-pai-index="' + conjuntoIndex + '"]').first();
        if (!$guia.length) { return null; }

        return {
            id: $guia.data('grupo-id') || null,
            categoria_id: parseInt($guia.data('categoria-id') || 0, 10) || 0,
            subcategoria_id: parseInt($guia.data('subcategoria-id') || 0, 10) || 0
        };
    }

    function selecionarGrupoConjunto($guia, abrirSugestoes = true) {
        const conjuntoIndex = parseInt($guia.data('conjunto-pai-index') || 0, 10) || 0;
        const categoriaId = $guia.data('categoria-id') || '';
        const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoIndex);

        if ($rowConjunto.length) {
            ativarMontagemConjunto($rowConjunto, false);
        }

        $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia').removeClass('table-active');
        $guia.addClass('table-active');

        if (categoriaId) {
            $('#busca_categoria_produto').val(String(categoriaId));
        }

        $('#busca_produto').val('').focus();

        if (abrirSugestoes) {
            carregarSugestoesProdutos();
        }
    }

    function ativarMontagemConjunto($rowConjunto, mostrarMensagem = true) {
        conjuntoAtivoIndex = parseInt($rowConjunto.data('index'), 10) || null;
        conjuntoAtivoNome = $rowConjunto.find('.nome_produto_display').val() || 'Conjunto';

        $('#tabela_itens_pedido tbody tr.item-conjunto-row').removeClass('table-primary');
        $rowConjunto.addClass('table-primary');

        const produtoConjuntoId = parseInt($rowConjunto.find('.produto_id').val(), 10) || 0;
        const regras = $rowConjunto.data('regras-conjunto') || [];
        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        const temGuias = $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').length > 0;

        if ((!temGuias || !regras.length) && produtoConjuntoId > 0) {
            carregarRegrasConjunto(produtoConjuntoId, $rowConjunto);
        }

        if (mostrarMensagem) {
            Swal.fire({
                title: 'Montagem do conjunto ativa',
                html: 'Agora selecione os produtos internos pela busca normal.<br><strong>' + escapeHtml(conjuntoAtivoNome) + '</strong><br><small>Os itens internos entrarão com valor zero e continuarão consultando estoque.</small>',
                icon: 'info',
                confirmButtonText: 'Entendi'
            });
        }
    }

    function encerrarMontagemConjunto() {
        if (conjuntoAtivoIndex !== null) {
            const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoAtivoIndex);
            if ($rowConjunto.length && !validarFechamentoConjunto($rowConjunto, true)) {
                return false;
            }
        }

        conjuntoAtivoIndex = null;
        conjuntoAtivoNome = '';
        $('#tabela_itens_pedido tbody tr.item-conjunto-row').removeClass('table-primary');
        $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia').removeClass('table-active');
        return true;
    }

    function adicionarConjuntoAoPedido(produto) {
        var $rowConjunto = adicionarLinhaItemTabela(produto, 'CONJUNTO');
        $('#busca_produto').val('').blur();
        $('#sugestoes_produtos').empty().hide();

        if ($rowConjunto && $rowConjunto.length) {
            carregarRegrasConjunto(produto.id, $rowConjunto, function() {
                ativarMontagemConjunto($rowConjunto, true);
            });
        }
    }

    function obterRegraCompletaDoConjunto($rowConjunto, regraBase) {
        const regras = $rowConjunto && $rowConjunto.length ? ($rowConjunto.data('regras-conjunto') || []) : [];
        if (!regraBase) { return null; }

        const regraId = parseInt(regraBase.id || 0, 10) || 0;
        if (regraId > 0) {
            const encontradaPorId = regras.find(function(regra) {
                return parseInt(regra.id || 0, 10) === regraId;
            });
            if (encontradaPorId) { return encontradaPorId; }
        }

        return regras.find(function(regra) {
            return regraCasaComProduto(regra, regraBase);
        }) || regraBase;
    }

    function selecionarGuiaDoConjuntoPorRegra($rowConjunto, regra) {
        if (!$rowConjunto || !$rowConjunto.length || !regra) { return; }

        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        const regraId = parseInt(regra.id || 0, 10) || 0;
        let $guia = $();

        if (regraId > 0) {
            $guia = $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"][data-grupo-id="' + regraId + '"]').first();
        }

        if (!$guia.length) {
            $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').each(function() {
                const $candidata = $(this);
                const regraGuia = {
                    categoria_id: parseInt($candidata.data('categoria-id') || 0, 10) || 0,
                    subcategoria_id: parseInt($candidata.data('subcategoria-id') || 0, 10) || 0
                };
                if (regraCasaComProduto(regraGuia, regra)) {
                    $guia = $candidata;
                    return false;
                }
            });
        }

        if ($guia.length) {
            selecionarGrupoConjunto($guia, false);
        }
    }

    function calcularQuantidadeLancadaNoGrupo($rowConjunto, regra) {
        if (!$rowConjunto || !$rowConjunto.length || !regra) { return 0; }

        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        let lancado = 0;

        $('#tabela_itens_pedido tbody tr.item-conjunto-filho-row[data-conjunto-pai-index="' + conjuntoIndex + '"]').each(function() {
            const produtoLinha = {
                categoria_id: parseInt($(this).data('categoria-id') || 0, 10) || 0,
                subcategoria_id: parseInt($(this).data('subcategoria-id') || 0, 10) || 0
            };

            if (regraCasaComProduto(regra, produtoLinha)) {
                lancado += parseInt($(this).find('.item-qtd').val(), 10) || 0;
            }
        });

        return lancado;
    }

    function adicionarItemAoConjuntoAtivo(produto) {
        const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoAtivoIndex);
        const regras = $rowConjunto.length ? ($rowConjunto.data('regras-conjunto') || []) : [];

        if (!$rowConjunto.length) {
            Swal.fire('Conjunto não localizado', 'Ative novamente a montagem do conjunto antes de adicionar itens internos.', 'warning');
            return false;
        }

        if (!regras.length) {
            Swal.fire('Regras não carregadas', 'Aguarde carregar as regras do conjunto e tente novamente.', 'warning');
            carregarRegrasConjunto(parseInt($rowConjunto.find('.produto_id').val(), 10) || 0, $rowConjunto);
            return false;
        }

        let regraSelecionada = obterRegraSelecionadaDoConjunto($rowConjunto);

        // Mantém o fluxo do orçamento: se o usuário está com um conjunto ativo e escolhe
        // um produto pela busca/categoria, tenta encaixar automaticamente no grupo compatível.
        // Assim não obriga clicar na guia Bistrô/Pufes quando só há um grupo possível para aquele produto.
        if (!regraSelecionada) {
            const regrasCompativeis = regras.filter(function(regra) {
                return regraCasaComProduto(regra, produto);
            });

            if (regrasCompativeis.length === 1) {
                regraSelecionada = regrasCompativeis[0];
                selecionarGuiaDoConjuntoPorRegra($rowConjunto, regraSelecionada);
            } else if (regrasCompativeis.length === 0) {
                Swal.fire('Produto fora da montagem', 'Este produto não pertence a nenhum grupo deste conjunto. Nada foi alterado.', 'warning');
                return false;
            } else {
                Swal.fire('Selecione o grupo', 'Este produto pode servir para mais de um grupo. Clique primeiro na guia correta do conjunto e selecione novamente.', 'warning');
                return false;
            }
        }

        if (!regraCasaComProduto(regraSelecionada, produto)) {
            // Se havia uma guia selecionada (ex.: Bistrô), mas o produto escolhido pertence a outra guia
            // do mesmo conjunto (ex.: Pufes), troca automaticamente para a guia correta.
            // Isso preserva o fluxo prático do create/orçamento: pode escolher pela categoria/busca sem ficar preso
            // na última guia ativa.
            const regrasCompativeisComProduto = regras.filter(function(regra) {
                return regraCasaComProduto(regra, produto);
            });

            if (regrasCompativeisComProduto.length === 1) {
                regraSelecionada = regrasCompativeisComProduto[0];
                selecionarGuiaDoConjuntoPorRegra($rowConjunto, regraSelecionada);
            } else if (regrasCompativeisComProduto.length === 0) {
                Swal.fire('Produto fora da montagem', 'Este produto não pertence a nenhum grupo deste conjunto. Nada foi alterado.', 'warning');
                return false;
            } else {
                Swal.fire('Selecione o grupo', 'Este produto pode servir para mais de um grupo. Clique primeiro na guia correta do conjunto e selecione novamente.', 'warning');
                return false;
            }
        }

        const quantidadeConjunto = parseInt($rowConjunto.find('.item-qtd').val(), 10) || 0;
        const regraCompleta = obterRegraCompletaDoConjunto($rowConjunto, regraSelecionada);
        const quantidadePorConjunto = parseFloat(regraCompleta.quantidade_por_conjunto || 1) || 1;
        const quantidadeNecessaria = Math.round(quantidadeConjunto * quantidadePorConjunto);
        const quantidadeLancada = calcularQuantidadeLancadaNoGrupo($rowConjunto, regraCompleta);
        const quantidadeFaltante = Math.max(0, quantidadeNecessaria - quantidadeLancada);

        // Em vez de jogar sempre a quantidade total do grupo, abre a linha com o que falta.
        // Ex.: conjunto pede 4 pufes; se já lançou 2 azuis, o próximo pufe entra com 2.
        // Se o grupo já estiver completo, entra com 1 para permitir acréscimo manual consciente.
        produto.quantidade = quantidadeFaltante > 0 ? quantidadeFaltante : 1;
        produto.tipo_item_loc_vend = 'locacao';

        const $rowItem = adicionarLinhaItemTabela(produto, 'ITEM_CONJUNTO', $rowConjunto);
        if ($rowItem && $rowItem.length) {
            revalidarTodasLinhasPedido($rowItem);
            atualizarGuiasConjunto($rowConjunto);
        }

        $('#busca_produto').val('').focus();
        $('#sugestoes_produtos').empty().hide();
        return true;
    }

    function atualizarGuiasConjunto($rowConjunto) {
        if (!$rowConjunto || !$rowConjunto.length) { return; }

        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        const quantidadeConjunto = parseInt($rowConjunto.find('.item-qtd').val(), 10) || 0;
        const regras = $rowConjunto.data('regras-conjunto') || [];

        $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').each(function() {
            const $guia = $(this);
            const regraId = parseInt($guia.data('grupo-id') || 0, 10) || 0;
            const regra = regras.find(function(r) { return parseInt(r.id || 0, 10) === regraId; }) || null;
            const qtdPorConjunto = regra ? (parseFloat(regra.quantidade_por_conjunto || 0) || 0) : 0;
            const necessario = quantidadeConjunto * qtdPorConjunto;
            let lancado = 0;

            $('#tabela_itens_pedido tbody tr.item-conjunto-filho-row[data-conjunto-pai-index="' + conjuntoIndex + '"]').each(function() {
                const produto = {
                    categoria_id: parseInt($(this).data('categoria-id') || 0, 10) || 0,
                    subcategoria_id: parseInt($(this).data('subcategoria-id') || 0, 10) || 0
                };
                if (regra && regraCasaComProduto(regra, produto)) {
                    lancado += parseInt($(this).find('.item-qtd').val(), 10) || 0;
                }
            });

            let texto = '';
            let classe = '';

            if (lancado < necessario) {
                texto = 'Faltam ' + (necessario - lancado) + ' · ' + lancado + '/' + necessario;
                classe = 'badge-warning';
            } else if (lancado > necessario) {
                texto = 'Excesso ' + (lancado - necessario) + ' · ' + lancado + '/' + necessario;
                classe = 'badge-danger';
            } else {
                texto = 'OK: ' + lancado + '/' + necessario;
                classe = 'badge-success';
            }

            $guia.find('.grupo-status-badge')
                .removeClass('badge-success badge-warning badge-danger')
                .addClass(classe)
                .text(texto);
        });
    }

    function validarFechamentoConjunto($rowConjunto, mostrarAlerta = true) {
        if (!$rowConjunto || !$rowConjunto.length) { return true; }

        atualizarGuiasConjunto($rowConjunto);

        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        let incompleto = false;
        const mensagens = [];

        $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').each(function() {
            const texto = $(this).find('.grupo-status-badge').text() || '';
            if (texto.indexOf('Faltam') === 0 || texto.indexOf('Excesso') === 0) {
                incompleto = true;
                mensagens.push($(this).find('td:first .font-weight-bold').text() + ': ' + texto);
            }
        });

        if (incompleto && mostrarAlerta) {
            Swal.fire({
                title: 'Conjunto incompleto ou inválido',
                html: 'Ajuste a montagem do conjunto <strong>' + escapeHtml($rowConjunto.find('.nome_produto_display').val() || 'Conjunto') + '</strong> antes de salvar.<br><br>' + mensagens.map(m => '• ' + escapeHtml(m)).join('<br>'),
                icon: 'warning',
                confirmButtonText: 'Entendi'
            });
        }

        return !incompleto;
    }

    function validarTodosConjuntosAntesSalvar(mostrarAlerta = true) {
        let ok = true;
        $('#tabela_itens_pedido tbody tr.item-conjunto-row').each(function() {
            if (!validarFechamentoConjunto($(this), mostrarAlerta)) {
                ok = false;
                return false;
            }
        });
        return ok;
    }


    let linhaSelecionadaParaTrocaProduto = null;

    function normalizarTextoComparacaoJS(texto) {
        return String(texto || '')
            .trim()
            .toUpperCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function limparSelecaoTrocaProduto() {
        if (linhaSelecionadaParaTrocaProduto && linhaSelecionadaParaTrocaProduto.length) {
            linhaSelecionadaParaTrocaProduto.removeClass('row-em-troca-produto');
        }
        linhaSelecionadaParaTrocaProduto = null;
    }

    function selecionarLinhaParaTrocaProduto($row) {
        limparSelecaoTrocaProduto();
        linhaSelecionadaParaTrocaProduto = $row;
        $row.addClass('row-em-troca-produto');

        const nomeAtual = $row.find('.nome_produto_display').val() || 'produto atual';
        $('#busca_produto').focus().select();

        if (typeof toastr !== 'undefined') {
            toastr.info('Escolha na busca o novo produto para substituir: ' + nomeAtual);
        }
    }

    function substituirProdutoNaLinha($row, produto, opcoes = {}) {
        if (!$row || !$row.length || !produto || !produto.id) {
            return;
        }

        const tipoLinhaFinal = opcoes.tipoLinha || 'PRODUTO';
        const nomeAnterior = $row.find('.nome_produto_display').val() || '';
        const nomeNovo = produto.nome_produto || produto.nome || 'Sem nome';
        const precoNovo = tipoLinhaFinal === 'ITEM_CONJUNTO' ? 0 : (parseFloat(produto.preco_locacao) || 0);
        const fotoNova = produto.foto_path_completo || '';
        const ehConjuntoFinal = tipoLinhaFinal === 'CONJUNTO';
        const ehItemConjuntoFinal = tipoLinhaFinal === 'ITEM_CONJUNTO';

        const $tdProduto = $row.children('td').first();
        $tdProduto.children('img.foto-produto-linha').remove();

        if (fotoNova) {
            const imgHtml = `<img src="${escapeHtml(fotoNova)}" alt="Miniatura" class="foto-produto-linha" data-foto-completa="${escapeHtml(fotoNova)}" data-nome-produto="${escapeHtml(nomeNovo)}" title="Clique para ampliar a foto" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle; cursor: zoom-in;">`;
            const $marcadorFilho = $tdProduto.children('span.text-primary[title="Item interno do conjunto"]').first();
            if ($marcadorFilho.length) {
                $marcadorFilho.after(imgHtml);
            } else {
                $tdProduto.prepend(imgHtml);
            }
        }

        $row.find('.nome_produto_display')
            .val(nomeNovo)
            .prop('readonly', true)
            .attr('readonly', 'readonly')
            .toggleClass('font-weight-bold text-primary', ehConjuntoFinal);

        $row.find('.produto_id').val(produto.id);
        $row.find('input[name="tipo_linha[]"]').val(tipoLinhaFinal);
        $row.attr('data-tipo-linha', tipoLinhaFinal).data('tipo-linha', tipoLinhaFinal);
        $row.attr('data-categoria-id', produto.categoria_id || '').data('categoria-id', produto.categoria_id || '');
        $row.attr('data-subcategoria-id', produto.subcategoria_id || '').data('subcategoria-id', produto.subcategoria_id || '');
        $row.attr('data-eh-conjunto', ehConjuntoFinal ? 1 : 0).data('eh-conjunto', ehConjuntoFinal ? 1 : 0);

        $row.removeClass('item-conjunto-row item-conjunto-filho-row table-primary');
        if (ehConjuntoFinal) {
            $row.addClass('item-conjunto-row');
            $row.css({'background-color': '#eef6ff', 'border-left': '4px solid #0d6efd'});
            $tdProduto.css('padding-left', '');
        } else if (ehItemConjuntoFinal) {
            $row.addClass('item-conjunto-filho-row');
            $row.css({'background-color': '#f8fbff', 'border-left': ''});
            $tdProduto.css('padding-left', '28px');
        } else {
            $row.css({'background-color': '#ffffff', 'border-left': ''});
            $tdProduto.css('padding-left', '');
        }

        $tdProduto.children('small.text-primary.font-weight-bold').remove();
        $tdProduto.children('small.form-text.text-muted').filter(function() {
            return ($(this).text() || '').indexOf('Item interno do conjunto') !== -1;
        }).remove();
        $tdProduto.children('span.text-primary[title="Item interno do conjunto"]').remove();

        if (ehConjuntoFinal) {
            $row.find('.nome_produto_display').after('<small class="text-primary font-weight-bold ml-1"><i class="fas fa-layer-group"></i> Conjunto comercial</small>');
        } else if (ehItemConjuntoFinal) {
            $tdProduto.prepend('<span class="text-primary mr-1" title="Item interno do conjunto"><i class="fas fa-level-up-alt fa-rotate-90"></i></span>');
            $row.find('.nome_produto_display').after('<small class="form-text text-muted">Item interno do conjunto · sem preço</small>');
        }

        const $acoes = $row.children('td').last();
        $acoes.find('.btn_montar_conjunto, .btn_encerrar_conjunto, .btn_trocar_produto').remove();
        const trocarHtml = '<button type="button" class="btn btn-xs btn-outline-primary btn_trocar_produto" title="Trocar produto desta linha"><i class="fas fa-exchange-alt"></i></button>';
        if (ehConjuntoFinal) {
            $acoes.find('.drag-handle').after('<button type="button" class="btn btn-xs btn-primary btn_montar_conjunto" title="Montar/continuar conjunto"><i class="fas fa-plus-circle"></i></button>' + trocarHtml + '<button type="button" class="btn btn-xs btn-secondary btn_encerrar_conjunto" title="Encerrar montagem"><i class="fas fa-check"></i></button>');
        } else {
            $acoes.find('.drag-handle').after(trocarHtml);
        }

        $row.find('.item-valor-unitario').val(formatCurrency(precoNovo)).prop('readonly', ehItemConjuntoFinal);
        $row.find('.desconto_item').val(formatCurrency(0)).prop('readonly', ehItemConjuntoFinal);

        const obsAtual = ($row.find('.observacoes_item_input').val() || '').trim();
        const nomeAnteriorNorm = normalizarTextoComparacaoJS(nomeAnterior);
        const nomeNovoNorm = normalizarTextoComparacaoJS(nomeNovo);

        if (obsAtual !== '' && nomeAnteriorNorm.indexOf('COR A DEFINIR') !== -1 && nomeNovoNorm.indexOf('COR A DEFINIR') === -1) {
            $row.find('.observacoes_item_input').val('').hide();
            $row.find('.observacoes_item_label').hide();
        }

        $row.removeData('disponibilidade-response');
        $row.removeData('alerta-indisponivel-chave');
        $row.find('.disponibilidade-contexto')
            .removeClass('status-ok status-atencao status-indisponivel')
            .addClass('status-neutro')
            .hide()
            .html('');

        limparStatusLinhaDisponibilidade($row);
        calcularSubtotalItem($row);
        calcularTotaisPedido();
        atualizarOrdemDosItens();
        if (!ehConjuntoFinal) {
            revalidarTodasAsLinhasDisponibilidade($row);
        }
    }

    function removerBlocoDoConjunto($rowConjunto) {
        if (!$rowConjunto || !$rowConjunto.length) { return; }
        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').remove();
        $('#tabela_itens_pedido tbody tr.item-conjunto-filho-row[data-conjunto-pai-index="' + conjuntoIndex + '"]').remove();
        $rowConjunto.find('.regras-conjunto-resumo').remove();
        $rowConjunto.removeData('regras-conjunto');
    }

    function encontrarRegraCompativelParaProduto($rowConjunto, produto) {
        const regras = $rowConjunto && $rowConjunto.length ? ($rowConjunto.data('regras-conjunto') || []) : [];
        if (!regras.length) { return null; }
        return regras.find(function(regra) { return regraCasaComProduto(regra, produto); }) || null;
    }

    function trocarConjuntoInteiro($rowConjunto, produto) {
        if (parseInt(produto.eh_conjunto || 0, 10) !== 1) {
            Swal.fire('Escolha um conjunto', 'Para trocar esta linha, selecione outro conjunto comercial. Nada foi alterado.', 'warning');
            return false;
        }

        removerBlocoDoConjunto($rowConjunto);
        substituirProdutoNaLinha($rowConjunto, produto, { tipoLinha: 'CONJUNTO' });
        carregarRegrasConjunto(produto.id, $rowConjunto, function() {
            ativarMontagemConjunto($rowConjunto, true);
        });
        return true;
    }

    function trocarItemInternoConjunto($rowItem, produto) {
        if (parseInt(produto.eh_conjunto || 0, 10) === 1) {
            Swal.fire('Produto inválido', 'Item interno de conjunto deve ser produto, não outro conjunto. Nada foi alterado.', 'warning');
            return false;
        }

        const conjuntoIndex = parseInt($rowItem.data('conjunto-pai-index') || 0, 10) || 0;
        const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoIndex);
        if (!$rowConjunto.length) {
            Swal.fire('Conjunto não localizado', 'Não foi possível localizar o conjunto pai desta linha. Nada foi alterado.', 'warning');
            return false;
        }

        const aplicarTroca = function() {
            const regra = encontrarRegraCompativelParaProduto($rowConjunto, produto);
            if (!regra) {
                Swal.fire('Produto fora da montagem', 'Este produto não pertence aos grupos deste conjunto. Nada foi alterado.', 'warning');
                return false;
            }

            substituirProdutoNaLinha($rowItem, produto, { tipoLinha: 'ITEM_CONJUNTO' });
            selecionarGuiaDoConjuntoPorRegra($rowConjunto, regra);
            atualizarGuiasConjunto($rowConjunto);
            return true;
        };

        if (!$rowConjunto.data('regras-conjunto') || !($rowConjunto.data('regras-conjunto') || []).length) {
            const produtoConjuntoId = parseInt($rowConjunto.find('.produto_id').val(), 10) || 0;
            carregarRegrasConjunto(produtoConjuntoId, $rowConjunto, aplicarTroca);
            return true;
        }

        return aplicarTroca();
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
        const produtoConsultadoId = parseInt(response.produto_id || 0, 10);
        const produtoConsultadoNome = response.produto_nome || '';

        let html = `<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap"><span class="painel-status-badge">${statusTexto}</span><small class="ml-2" style="opacity:.9;">Clique no resumo da linha para reabrir este painel.</small></div>`;
        html += '<div class="painel-disponibilidade-grid">';
        html += `<div class="painel-box"><strong>Estoque total</strong><div class="painel-valor-principal">${estoqueTotal}</div><div class="painel-subtexto">Quantidade cadastrada</div></div>`;
        html += `<div class="painel-box"><strong>Pedidos no período</strong><div class="painel-valor-principal">${comprometido}</div><div class="painel-subtexto">Já comprometido fora deste pedido</div></div>`;
        html += `<div class="painel-box"><strong>Neste pedido</strong><div class="painel-valor-principal">${reservadoAtual}</div><div class="painel-subtexto">Incluindo esta linha e as repetidas</div></div>`;
        html += `<div class="painel-box"><strong>Livre após pedido</strong><div class="painel-valor-principal">${livreApos}</div>${faltante > 0 ? `<div class="painel-subtexto font-weight-bold">Faltando ${faltante}</div>` : '<div class="painel-subtexto">Saldo projetado</div>'}</div>`;
        html += '</div>';

        if (response.consulta_periodo_valida === false) {
            html += '<div class="painel-box mt-2">Informe data de entrega e devolução para análise temporal completa.</div>';
        }

        if (response.conflitos && response.conflitos.length > 0) {
            let conflitosHtml = response.conflitos.map(function(item) {
                const quantidade = parseInt(item.quantidade || 0, 10);
                const produtoOrigemId = parseInt(item.produto_origem_id || 0, 10);
                const produtoOrigemNome = item.produto_origem_nome || item.componente_nome || produtoConsultadoNome || 'Produto';
                const destaque = produtoOrigemId > 0 && produtoOrigemId === produtoConsultadoId;
                const classeDestaque = destaque ? ' style="background:rgba(255,255,255,.18); border-radius:8px; padding:4px 6px; margin:3px 0;"' : '';
                const badge = destaque ? ' <span class="badge badge-light text-dark ml-1">produto consultado</span>' : '';
                const linkPedido = item.pedido_id ? ` <a href="show.php?id=${parseInt(item.pedido_id, 10)}" target="_blank" class="btn btn-xs btn-light ml-1" title="Abrir pedido"><i class="fas fa-eye"></i></a>` : '';

                return `<li${classeDestaque}><strong>${escapeHtml(item.cliente || 'Cliente')}</strong> — ${quantidade} un. de <strong>${escapeHtml(produtoOrigemNome)}</strong>${badge} <span style="opacity:.85;">(${escapeHtml(item.inicio_formatado || '')} → ${escapeHtml(item.fim_formatado || '')})</span>${linkPedido}</li>`;
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
                const estoqueTotalComp = parseInt(comp.estoque_total || comp.quantidade_total || 0, 10);
                const pedidosPeriodo = parseInt(comp.comprometido_periodo || 0, 10);
                const reservadoComp = parseInt((comp.reservado_orcamento_atual ?? comp.quantidade_necessaria ?? 0), 10);
                const livreAposComp = parseInt(comp.livre_apos_orcamento !== undefined ? comp.livre_apos_orcamento : (comp.estoque_disponivel !== undefined ? comp.estoque_disponivel : 0), 10);
                const faltanteComp = parseInt(comp.faltante_orcamento || 0, 10);
                const qtdPorUnidade = parseFloat(comp.quantidade_por_unidade || comp.quantidade || 1);

                return `<li>
                    <strong>${escapeHtml(nome)}</strong>
                    — usa ${qtdPorUnidade} por unidade
                    · estoque total: ${estoqueTotalComp}
                    · pedidos no período: ${pedidosPeriodo}
                    · neste pedido: ${reservadoComp}
                    · livre após pedido: ${livreAposComp}${faltanteComp > 0 ? ` · faltando ${faltanteComp}` : ''}
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
            return '<span class="status-principal">CONSULTAR</span><span class="status-detalhe">· abrir painel</span>';
        }

        const comprometido = parseInt(response.comprometido_periodo || 0, 10);
        const reservadoAtual = parseInt((response.reservado_orcamento_atual ?? response.quantidade_solicitada ?? 0), 10);
        const livreApos = parseInt(response.livre_apos_orcamento !== undefined ? response.livre_apos_orcamento : 0, 10);
        const faltante = parseInt(response.faltante_orcamento || 0, 10);
        const statusTexto = obterTextoStatusDisponibilidade(response);

        let detalhe = `· Ped. ${comprometido} · Ped. atual ${reservadoAtual} · Livre ${livreApos}`;
        if (faltante > 0) {
            detalhe = `· Faltando ${faltante}`;
        }

        return `<span class="status-principal">${statusTexto}</span><span class="status-detalhe">${detalhe}</span>`;
    }

    function atualizarPainelConsultaDisponibilidade(nomeProduto, response, exibirPainel = true) {
        const $painel = $('#painel_consulta_disponibilidade');
        const $conteudo = $('#conteudo_consulta_disponibilidade');
        const classe = obterClasseStatusDisponibilidade(response);

        $painel.removeClass('painel-neutro painel-ok painel-atencao painel-indisponivel').addClass('painel-' + classe);

        let titulo = nomeProduto ? `<div class="font-weight-bold mb-2" style="font-size:1rem;">${escapeHtml(nomeProduto)}</div>` : '';
        $conteudo.html(titulo + montarResumoDisponibilidadeHtml(response));

        if (exibirPainel) {
            $painel.show();
        }
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

    function coletarItensContextoAtualPedido() {
        const itens = [];
        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
            const $row = $(this);
            if (!['PRODUTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '')) {
                return;
            }

            const produtoId = parseInt($row.find('.produto_id').val(), 10) || 0;
            const quantidade = parseInt($row.find('.item-qtd').val(), 10) || 0;

            if (produtoId > 0 && quantidade > 0) {
                itens.push({
                    produto_id: produtoId,
                    quantidade: quantidade
                });
            }
        });
        return itens;
    }

    function consultarDisponibilidadeAjax(produtoId, quantidade, callbackSucesso, callbackErro) {
        const periodo = obterPeriodoConsultaAtual();
        const itensContexto = coletarItensContextoAtualPedido();

        $.ajax({
            url: `edit.php?id=${PEDIDO_ID}`,
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
                turno_fim: periodo.turno_fim,
                ignorar_pedido_id: PEDIDO_ID,
                itens_contexto: JSON.stringify(itensContexto)
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

        // Mantém a soma de linhas repetidas do mesmo produto.
        // A revalidação geral, chamada nos eventos, atualiza também os produtos diferentes que compartilham componentes.
        $('#tabela_itens_pedido .produto_id').each(function() {
            if ($(this).val() == produtoId) {
                quantidade += parseInt($(this).closest('tr').find('.item-qtd').val(), 10) || 0;
            }
        });

        if (quantidade < 0) { quantidade = 0; }

        const nomeProduto = $row.find('.nome_produto_display').val() || '';
        const requestSeq = ++disponibilidadeRequestSeq;
        $row.data('disponibilidade-request-seq', requestSeq);

        consultarDisponibilidadeAjax(produtoId, quantidade, function(response) {
            // Evita resposta AJAX antiga sobrescrever consulta nova.
            if ($row.data('disponibilidade-request-seq') !== requestSeq) {
                return;
            }

            aplicarContextoDisponibilidadeNaLinha($row, response);

            if (exibirAlertaSeIndisponivel && response && response.success !== false && response.disponivel === false) {
                const chaveAlerta = [
                    produtoId,
                    quantidade,
                    $('#data_entrega').val(),
                    $('#hora_entrega').val(),
                    $('#turno_entrega').val(),
                    $('#data_devolucao_prevista').val(),
                    $('#hora_devolucao').val(),
                    $('#turno_devolucao').val()
                ].join('|');

                if ($row.data('alerta-indisponivel-chave') !== chaveAlerta) {
                    $row.data('alerta-indisponivel-chave', chaveAlerta);
                    Swal.fire({
                        title: 'Atenção no período consultado',
                        html: montarResumoDisponibilidadeHtml(response),
                        icon: 'warning',
                        confirmButtonText: 'Entendi'
                    });
                }
            } else if (response && response.disponivel !== false) {
                $row.removeData('alerta-indisponivel-chave');
            }

            atualizarPainelConsultaDisponibilidade(nomeProduto, response, false);
        }, function() {
            const response = {
                success: false,
                disponivel: false,
                alertas: ['Erro ao consultar disponibilidade temporal.']
            };
            aplicarContextoDisponibilidadeNaLinha($row, response);
        });
    }

    function revalidarTodasAsLinhasDisponibilidade($rowAlerta = null) {
        const rowAlertaEl = $rowAlerta && $rowAlerta.length ? $rowAlerta[0] : null;

        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
            const $row = $(this);
            if (['PRODUTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '') && $row.find('.produto_id').val()) {
                const deveAlertar = rowAlertaEl && this === rowAlertaEl;
                atualizarContextoDisponibilidadeLinha($row, deveAlertar);
            }
        });
    }

    function rolarTabelaItensParaLinha($row) {
        var $container = $('.itens-scroll-container');
        if (!$container.length || !$row || !$row.length) { return; }

        // Antes jogava sempre para o fim da tabela.
        // Em montagem de conjunto isso atrapalha, porque o item interno deve ficar visível
        // dentro do bloco do conjunto, não forçar o usuário a voltar manualmente.
        setTimeout(function() {
            var containerTop = $container.offset().top;
            var rowTop = $row.offset().top;
            var targetScroll = $container.scrollTop() + (rowTop - containerTop) - 90;

            if (targetScroll < 0) { targetScroll = 0; }

            $container.stop(true).animate({
                scrollTop: targetScroll
            }, 180);
        }, 60);
    }

    function obterLinhaConjuntoPorIndex(conjuntoIndex) {
        return $('#tabela_itens_pedido tbody tr.item-conjunto-row').filter(function() {
            return parseInt($(this).data('index') || 0, 10) === parseInt(conjuntoIndex || 0, 10);
        }).first();
    }

    function inserirLinhaNoBlocoDoConjunto($rowConjunto, $novaLinha) {
        const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
        let $referencia = $rowConjunto;

        $('#tabela_itens_pedido tbody tr').each(function() {
            const $linha = $(this);
            const ehGuia = $linha.hasClass('conjunto-grupo-guia') && parseInt($linha.data('conjunto-pai-index') || 0, 10) === conjuntoIndex;
            const ehFilho = $linha.hasClass('item-conjunto-filho-row') && parseInt($linha.data('conjunto-pai-index') || 0, 10) === conjuntoIndex;

            if (ehGuia || ehFilho) {
                $referencia = $linha;
            }
        });

        $novaLinha.insertAfter($referencia);
    }

    function adicionarLinhaItemTabela(dadosItem = null, tipoLinhaParam, $rowConjuntoDestino = null) {
        itemIndex++;
        var tipoLinha = tipoLinhaParam;
        var htmlLinha = '';
        var nomeDisplay = dadosItem ? (dadosItem.nome_produto || '') : '';
        var produtoIdInput = dadosItem ? (dadosItem.id || '') : '';
        var precoUnitarioDefault = 0;
        if (dadosItem) {
            if (dadosItem.preco_unitario !== undefined && dadosItem.preco_unitario !== null && dadosItem.preco_unitario !== '') {
                precoUnitarioDefault = parseFloat(dadosItem.preco_unitario) || 0;
            } else if (dadosItem.preco_locacao !== undefined && dadosItem.preco_locacao !== null && dadosItem.preco_locacao !== '') {
                precoUnitarioDefault = parseFloat(dadosItem.preco_locacao) || 0;
            }
        }
        var tipoItemLocVend = dadosItem ? (dadosItem.tipo_item_loc_vend || 'locacao') : 'locacao';
        var nomeInputName = "nome_produto_display[]";
        var ehLinhaConjunto = tipoLinha === 'CONJUNTO';
        var ehItemConjunto = tipoLinha === 'ITEM_CONJUNTO';
        var quantidadeDefault = dadosItem && dadosItem.quantidade !== undefined && dadosItem.quantidade !== null && dadosItem.quantidade !== '' ? parseInt(dadosItem.quantidade, 10) : (ehLinhaConjunto ? 1 : 0);
        var descontoDefault = dadosItem && dadosItem.desconto ? parseFloat(dadosItem.desconto) : 0;
        var subtotalDefault = ehItemConjunto ? 0 : quantidadeDefault * (precoUnitarioDefault - descontoDefault);
        var categoriaId = dadosItem && dadosItem.categoria_id ? dadosItem.categoria_id : '';
        var subcategoriaId = dadosItem && dadosItem.subcategoria_id ? dadosItem.subcategoria_id : '';
        var ehConjuntoProduto = dadosItem && parseInt(dadosItem.eh_conjunto || 0, 10) === 1 ? 1 : 0;
        var imagemHtml = dadosItem && dadosItem.foto_path_completo
            ? `<img src="${escapeHtml(dadosItem.foto_path_completo)}" alt="Miniatura" class="foto-produto-linha" data-foto-completa="${escapeHtml(dadosItem.foto_path_completo)}" data-nome-produto="${escapeHtml(nomeDisplay)}" title="Clique para ampliar a foto" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle; cursor: zoom-in;">`
            : '';

        if (tipoLinha === 'PRODUTO' || tipoLinha === 'CONJUNTO' || tipoLinha === 'ITEM_CONJUNTO') {
            let classeLinha = ehLinhaConjunto ? ' item-conjunto-row' : (ehItemConjunto ? ' item-conjunto-filho-row' : '');
            let estiloLinha = ehLinhaConjunto ? 'background-color: #eef6ff !important; border-left: 4px solid #0d6efd;' : (ehItemConjunto ? 'background-color: #f8fbff !important;' : 'background-color: #ffffff !important;');
            let larguraInput = ehItemConjunto ? 'calc(100% - 86px)' : 'calc(100% - 65px)';
            let paddingTd = ehItemConjunto ? 'padding-left: 28px;' : '';
            let precoValue = ehItemConjunto ? '0,00' : precoUnitarioDefault.toFixed(2).replace('.', ',');
            let descontoValue = ehItemConjunto ? '0,00' : descontoDefault.toFixed(2).replace('.', ',');
            let readonlyPreco = ehItemConjunto ? 'readonly' : '';
            let observacao = dadosItem && dadosItem.observacoes ? dadosItem.observacoes : '';
            let obsStyle = observacao ? '' : 'display:none;';

            htmlLinha = `<tr class="item-pedido-row${classeLinha}" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" data-categoria-id="${categoriaId}" data-subcategoria-id="${subcategoriaId}" data-eh-conjunto="${ehConjuntoProduto}" style="${estiloLinha}">
                <td style="${paddingTd}">
                    ${ehItemConjunto ? '<span class="text-primary mr-1" title="Item interno do conjunto"><i class="fas fa-level-up-alt fa-rotate-90"></i></span>' : ''}
                    ${imagemHtml}
                    <input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display ${ehLinhaConjunto ? 'font-weight-bold text-primary' : ''}" value="${escapeHtml(nomeDisplay)}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: ${larguraInput}; vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}>
                    <input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}">
                    <input type="hidden" name="tipo_linha[]" value="${tipoLinha}">
                    <input type="hidden" name="ordem[]" value="${itemIndex}">
                    <input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}">
                    <input type="hidden" name="grupo_conjunto[]" value="${dadosItem && dadosItem.grupo_conjunto ? escapeHtml(dadosItem.grupo_conjunto) : ''}">
                    ${ehLinhaConjunto ? '<small class="text-primary font-weight-bold ml-1"><i class="fas fa-layer-group"></i> Conjunto comercial</small>' : ''}
                    ${ehItemConjunto ? '<small class="form-text text-muted">Item interno do conjunto · sem preço</small>' : ''}
                    <small class="form-text text-muted observacoes_item_label" style="${obsStyle}">Obs. Item:</small>
                    <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="${obsStyle}" placeholder="Observação do item" value="${escapeHtml(observacao)}">
                </td>
                <td class="status-disponibilidade-cell text-center">
                    <div class="disponibilidade-contexto status-neutro" style="display:none;"></div>
                </td>
                <td>
                    <input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeDefault}" min="0" data-valor-original="${quantidadeDefault}" style="width: 70px;">
                </td>
                <td>
                    <input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoValue}" ${readonlyPreco}>
                </td>
                <td>
                    <input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoValue}" ${readonlyPreco}>
                </td>
                <td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault).replace('R$ ', '')}</td>
                <td>
                    <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                    ${ehLinhaConjunto ? '<button type="button" class="btn btn-xs btn-primary btn_montar_conjunto" title="Montar/continuar conjunto"><i class="fas fa-plus-circle"></i></button>' : ''}
                    <button type="button" class="btn btn-xs btn-outline-primary btn_trocar_produto" title="Trocar produto desta linha"><i class="fas fa-exchange-alt"></i></button>
                    ${ehLinhaConjunto ? '<button type="button" class="btn btn-xs btn-secondary btn_encerrar_conjunto" title="Encerrar montagem"><i class="fas fa-check"></i></button>' : ''}
                    <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button>
                    <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        } else if (tipoLinha === 'CABECALHO_SECAO') {
            htmlLinha = `<tr class="item-pedido-row item-titulo-secao" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #e7f1ff !important;">
                <td colspan="6">
                    <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                    <input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_titulo_secao" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);">
                    <input type="hidden" name="produto_id[]" value="">
                    <input type="hidden" name="tipo_linha[]" value="${tipoLinha}">
                    <input type="hidden" name="ordem[]" value="${itemIndex}">
                    <input type="hidden" name="quantidade[]" value="0">
                    <input type="hidden" name="tipo_item[]" value="">
                    <input type="hidden" name="grupo_conjunto[]" value="">
                    <input type="hidden" name="valor_unitario[]" value="0.00">
                    <input type="hidden" name="desconto_item[]" value="0.00">
                    <input type="hidden" name="observacoes_item[]" value="">
                </td>
                <td>
                    <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }

        if (htmlLinha) {
            var $novaLinha = $(htmlLinha);

            if (ehItemConjunto && $rowConjuntoDestino && $rowConjuntoDestino.length) {
                const conjuntoIndex = parseInt($rowConjuntoDestino.data('index') || 0, 10) || 0;
                $novaLinha.attr('data-conjunto-pai-index', conjuntoIndex).data('conjunto-pai-index', conjuntoIndex);
                inserirLinhaNoBlocoDoConjunto($rowConjuntoDestino, $novaLinha);
            } else {
                $('#tabela_itens_pedido tbody').append($novaLinha);
            }

            atualizarOrdemDosItens();

            if (tipoLinha === 'CABECALHO_SECAO') {
                $novaLinha.find('.nome_titulo_secao').focus();
                rolarTabelaItensParaLinha($novaLinha);
            } else {
                if (produtoIdInput && !ehLinhaConjunto) {
                    revalidarTodasLinhasPedido($novaLinha);
                }
                rolarTabelaItensParaLinha($novaLinha);
                setTimeout(function() {
                    $novaLinha.find('.item-qtd').focus().select();
                }, 120);
            }

            calcularTotaisPedido();
            return $novaLinha;
        }

        return $();
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


    function verificarEstoqueAntes(produto) {
        adicionarLinhaItemTabela(produto, 'PRODUTO');
        $('#busca_produto').val('').focus();
        $('#sugestoes_produtos').empty().hide();
    }

    function validarEstoqueQuantidade($row) {
        var produtoId = $row.find('.produto_id').val();
        var quantidadeAtual = parseInt($row.find('.item-qtd').val(), 10) || 0;

        if (!produtoId) return;

        if (quantidadeAtual < 0) {
            $row.find('.item-qtd').val(0);
            quantidadeAtual = 0;
        }

        // Revalida todas as linhas, porque produtos diferentes podem compartilhar componentes.
        // Ex.: Sofá Rosa Antigo e Sofá Cor A Definir usam a mesma estrutura/colchões.
        revalidarTodasAsLinhasDisponibilidade($row);
        $row.find('.item-qtd').data('valor-original', quantidadeAtual);
    }

    function atualizarGuiasDoConjuntoDaLinha($row) {
        if (!$row || !$row.length) { return; }

        const tipoLinha = $row.data('tipo-linha') || '';

        if (tipoLinha === 'CONJUNTO') {
            atualizarGuiasConjunto($row);
            return;
        }

        if (tipoLinha === 'ITEM_CONJUNTO') {
            const conjuntoIndex = parseInt($row.data('conjunto-pai-index') || 0, 10) || 0;
            const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoIndex);
            if ($rowConjunto.length) {
                atualizarGuiasConjunto($rowConjunto);
            }
        }
    }

    function revalidarTodasLinhasPedido($rowAlerta = null) {
        return revalidarTodasAsLinhasDisponibilidade($rowAlerta);
    }

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
                foto_path_completo: $(this).data('foto-completa'),
                eh_conjunto: parseInt($(this).data('eh-conjunto') || 0, 10),
                categoria_id: parseInt($(this).data('categoria-id') || 0, 10) || null,
                subcategoria_id: parseInt($(this).data('subcategoria-id') || 0, 10) || null
            };

            if (linhaSelecionadaParaTrocaProduto && linhaSelecionadaParaTrocaProduto.length) {
                const $linhaTroca = linhaSelecionadaParaTrocaProduto;
                const tipoLinhaTroca = $linhaTroca.data('tipo-linha') || 'PRODUTO';
                let trocaOk = false;

                if (tipoLinhaTroca === 'CONJUNTO') {
                    trocaOk = trocarConjuntoInteiro($linhaTroca, produto);
                } else if (tipoLinhaTroca === 'ITEM_CONJUNTO') {
                    trocaOk = trocarItemInternoConjunto($linhaTroca, produto);
                } else if (produto.eh_conjunto === 1) {
                    trocaOk = trocarConjuntoInteiro($linhaTroca, produto);
                } else {
                    substituirProdutoNaLinha($linhaTroca, produto, { tipoLinha: 'PRODUTO' });
                    trocaOk = true;
                }

                if (trocaOk) {
                    limparSelecaoTrocaProduto();
                    $('#busca_produto').val('').focus();
                    $('#sugestoes_produtos').empty().hide();
                }
                return;
            }

            if (produto.eh_conjunto === 1) {
                adicionarConjuntoAoPedido(produto);
                return;
            }

            if (conjuntoAtivoIndex !== null) {
                adicionarItemAoConjuntoAtivo(produto);
                return;
            }

            verificarEstoqueAntes(produto);
        };

        if (produtoJaExiste && conjuntoAtivoIndex === null && !(linhaSelecionadaParaTrocaProduto && linhaSelecionadaParaTrocaProduto.length)) {
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

    $('#busca_categoria_produto').on('click focus', function() {
        if ($(this).val()) {
            setTimeout(carregarSugestoesProdutos, 60);
        }
    });

    $('#tabela_itens_pedido').on('click', '.foto-produto-linha', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var fotoUrl = $(this).data('foto-completa');
        var nomeProduto = $(this).data('nome-produto') || 'Produto';

        if (fotoUrl) {
            Swal.fire({
                title: nomeProduto,
                imageUrl: fotoUrl,
                imageAlt: 'Foto ampliada de ' + nomeProduto,
                imageWidth: '90%',
                confirmButtonText: 'Fechar'
            });
        }
    });

    $('#tabela_itens_pedido').on('click', '.conjunto-grupo-guia', function() {
        selecionarGrupoConjunto($(this), true);
    });

    $('#tabela_itens_pedido').on('click', '.btn_montar_conjunto', function() {
        const $rowConjunto = $(this).closest('tr');
        const produtoId = parseInt($rowConjunto.find('.produto_id').val(), 10) || 0;

        if (!$rowConjunto.data('regras-conjunto') || !($rowConjunto.data('regras-conjunto') || []).length) {
            carregarRegrasConjunto(produtoId, $rowConjunto, function() {
                ativarMontagemConjunto($rowConjunto, true);
            });
            return;
        }

        ativarMontagemConjunto($rowConjunto, true);
    });

    $('#tabela_itens_pedido').on('click', '.btn_encerrar_conjunto', function() {
        if (!encerrarMontagemConjunto()) {
            return;
        }
        Swal.fire({
            title: 'Montagem encerrada',
            text: 'Os próximos produtos selecionados entrarão como itens normais do pedido.',
            icon: 'success',
            timer: 1400,
            showConfirmButton: false
        });
    });

    $('#btn_adicionar_titulo_secao').click(function() {
        adicionarLinhaItemTabela(null, 'CABECALHO_SECAO');
    });

    $('#btn_adicionar_item_manual').click(function() {
        adicionarLinhaItemTabela(null, 'PRODUTO');
    });

    $('#tabela_itens_pedido').on('click', '.btn_remover_item', function() {
        const $row = $(this).closest('tr');
        const conjuntoIndex = parseInt($row.data('index') || 0, 10) || 0;

        if (linhaSelecionadaParaTrocaProduto && linhaSelecionadaParaTrocaProduto[0] === $row[0]) {
            limparSelecaoTrocaProduto();
        }

        if ($row.hasClass('item-conjunto-row')) {
            $('#tabela_itens_pedido tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').remove();
            $('#tabela_itens_pedido tbody tr.item-conjunto-filho-row[data-conjunto-pai-index="' + conjuntoIndex + '"]').remove();
            if (conjuntoAtivoIndex === conjuntoIndex) {
                conjuntoAtivoIndex = null;
                conjuntoAtivoNome = '';
            }
        } else if ($row.hasClass('item-conjunto-filho-row')) {
            const conjuntoPaiIndex = parseInt($row.data('conjunto-pai-index') || 0, 10) || 0;
            $row.remove();
            atualizarGuiasConjunto(obterLinhaConjuntoPorIndex(conjuntoPaiIndex));
            atualizarOrdemDosItens();
            calcularTotaisPedido();
            revalidarTodasAsLinhasDisponibilidade();
            return;
        }

        const conjuntoPaiIndexRemovido = parseInt($row.data('conjunto-pai-index') || 0, 10) || 0;
        const $rowConjuntoPaiRemovido = conjuntoPaiIndexRemovido > 0 ? obterLinhaConjuntoPorIndex(conjuntoPaiIndexRemovido) : $();

        $row.remove();
        if ($rowConjuntoPaiRemovido.length) {
            atualizarGuiasConjunto($rowConjuntoPaiRemovido);
        }
        atualizarOrdemDosItens();
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
        revalidarTodasAsLinhasDisponibilidade();
        if ($('#tabela_itens_pedido tbody tr.item-pedido-row').length === 0) {
            $('#tabela_itens_pedido tbody').append('<tr class="no-items-row"><td colspan="7" class="text-center text-muted">Nenhum item adicionado a este pedido ainda.</td></tr>');
        }
    });

    $('#tabela_itens_pedido').on('click', '.btn_trocar_produto', function() {
        const $row = $(this).closest('tr');
        if (!['PRODUTO', 'CONJUNTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '')) {
            return;
        }
        selecionarLinhaParaTrocaProduto($row);
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
    $(document).on('focus', '.item-qtd', function() {
        $(this).data('valor-original', parseInt($(this).val()) || 0);
    });

    $(document).on('change', '.item-qtd, .item-valor-unitario, .desconto_item', function() {
        var $row = $(this).closest('tr');

        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }

        if ($(this).hasClass('item-qtd')) {
            atualizarGuiasDoConjuntoDaLinha($row);
        }

        if ($(this).hasClass('item-qtd') && $row.find('.produto_id').val()) {
            validarEstoqueQuantidade($row);
        }
    });
    $(document).on('keyup input', '.item-qtd, .item-valor-unitario, .desconto_item', function() {
        var $input = $(this);
        var $row = $input.closest('tr');
        calculateDebounced();

        if ($input.hasClass('item-qtd')) {
            atualizarGuiasDoConjuntoDaLinha($row);
        }

        if ($input.hasClass('item-qtd') && $row.find('.produto_id').val()) {
            clearTimeout($input.data('validacao-timeout'));
            $input.data('validacao-timeout', setTimeout(function() {
                validarEstoqueQuantidade($row);
            }, 700));
        }
    });


    $('#tabela_itens_pedido').on('click', '.disponibilidade-contexto', function() {
        var $row = $(this).closest('tr');
        var response = $row.data('disponibilidade-response') || null;
        var nomeProduto = $row.find('.nome_produto_display').val() || '';
        if (response) {
            atualizarPainelConsultaDisponibilidade(nomeProduto, response);
        }
    });

    $('.btn-fechar-painel-disponibilidade').on('click', function() {
        $('#painel_consulta_disponibilidade').hide();
    });

    // Revalida as linhas quando o período logístico muda.
    // Se as datas estiverem incoerentes, não consulta estoque para não pintar tudo de vermelho/amarelo indevidamente.
    $('#data_evento, #data_entrega, #data_devolucao_prevista, #hora_entrega, #turno_entrega, #hora_devolucao, #turno_devolucao').on('change keyup blur', function() {
        $('#tabela_itens_pedido tbody tr.item-pedido-row').removeData('alerta-indisponivel-chave');

        const ehCampoDataPrincipal = ['data_evento', 'data_entrega', 'data_devolucao_prevista'].includes(this.id);

        if (!validarSequenciaDatasLogisticaPedido(ehCampoDataPrincipal)) {
            marcarLinhasPedidoComoPeriodoInvalido();
            return;
        }

        revalidarTodasAsLinhasDisponibilidade();
    });

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
        displayEl.text('').removeData('dia-semana-base').removeClass('text-danger font-weight-bold text-success text-warning');
        if (dataStr) {
            var partes = dataStr.split('/');
            if (partes.length === 3) {
                var dataObj = new Date(partes[2], partes[1] - 1, partes[0]);
                if (!isNaN(dataObj.valueOf())) {
                    var diaSemana = diasDaSemana[dataObj.getDay()];
                    displayEl.data('dia-semana-base', diaSemana);
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
    validarSequenciaDatasLogisticaPedido(false);
}).trigger('change');

$('#data_entrega').on('change dp.change', function() { 
    exibirDiaSemana('#data_entrega', '#dia_semana_entrega');
    validarSequenciaDatasLogisticaPedido(false);
}).trigger('change');

$('#data_devolucao_prevista').on('change dp.change', function() { 
    exibirDiaSemana('#data_devolucao_prevista', '#dia_semana_devolucao');
    validarSequenciaDatasLogisticaPedido(false);
}).trigger('change');

    function converterDataBRParaDate(valor) {
        valor = String(valor || '').trim();

        if (!valor) {
            return null;
        }

        const partes = valor.split('/');
        if (partes.length !== 3) {
            return null;
        }

        const dia = parseInt(partes[0], 10);
        const mes = parseInt(partes[1], 10);
        const ano = parseInt(partes[2], 10);

        if (!dia || !mes || !ano) {
            return null;
        }

        const data = new Date(ano, mes - 1, dia);

        if (
            data.getFullYear() !== ano ||
            data.getMonth() !== mes - 1 ||
            data.getDate() !== dia
        ) {
            return null;
        }

        data.setHours(0, 0, 0, 0);
        return data;
    }

    function aplicarFeedbackData(displayId, tipo, mensagem) {
        const $display = $(displayId);
        const base = $display.data('dia-semana-base') || '';
        const classeBase = $display.hasClass('text-danger') ? 'text-danger' : 'text-success';

        if (!base && !mensagem) {
            $display.text('');
            return;
        }

        let statusHtml = '';
        if (mensagem) {
            if (tipo === 'erro') {
                statusHtml = ' <span class="text-warning">· ⚠ ' + escapeHtml(mensagem) + '</span>';
            } else if (tipo === 'ok') {
                statusHtml = ' <span class="text-success">· ✓ OK</span>';
            } else if (tipo === 'info') {
                statusHtml = ' <span class="text-muted">· ' + escapeHtml(mensagem) + '</span>';
            }
        }

        $display.removeClass('text-danger text-success text-warning').addClass('font-weight-bold ' + classeBase);
        $display.html(escapeHtml(base) + statusHtml);
    }

    function limparFeedbackDatasLogistica() {
        aplicarFeedbackData('#dia_semana_evento', '', '');
        aplicarFeedbackData('#dia_semana_entrega', '', '');
        aplicarFeedbackData('#dia_semana_devolucao', '', '');
    }

    function marcarDatasLogisticasComoOk(dataEvento, dataEntrega, dataDevolucao) {
        if (dataEvento) {
            aplicarFeedbackData('#dia_semana_evento', 'ok', 'OK');
        }
        if (dataEntrega) {
            aplicarFeedbackData('#dia_semana_entrega', 'ok', 'OK');
        }
        if (dataDevolucao) {
            aplicarFeedbackData('#dia_semana_devolucao', 'ok', 'OK');
        }
    }

    function validarSequenciaDatasLogisticaPedido(mostrarAlerta = true) {
        const dataEventoStr = $('#data_evento').val();
        const dataEntregaStr = $('#data_entrega').val();
        const dataDevolucaoStr = $('#data_devolucao_prevista').val();

        const dataEvento = converterDataBRParaDate(dataEventoStr);
        const dataEntrega = converterDataBRParaDate(dataEntregaStr);
        const dataDevolucao = converterDataBRParaDate(dataDevolucaoStr);

        limparFeedbackDatasLogistica();

        // Pedido pode ser editado com data antiga/histórica.
        // A validação só bloqueia contradições reais entre as datas preenchidas.
        if (dataEntrega && dataEvento && dataEntrega > dataEvento) {
            aplicarFeedbackData('#dia_semana_entrega', 'erro', 'entrega depois do evento');
            aplicarFeedbackData('#dia_semana_evento', 'info', 'referência');

            if (mostrarAlerta) {
                Swal.fire({
                    title: 'Data de entrega inválida',
                    html: 'A <strong>data da entrega</strong> não pode ser depois da <strong>data do evento</strong>.<br><br>' +
                          '<strong>Entrega:</strong> ' + escapeHtml(dataEntregaStr) + '<br>' +
                          '<strong>Evento:</strong> ' + escapeHtml(dataEventoStr),
                    icon: 'warning',
                    confirmButtonText: 'Entendi'
                });
            }

            return false;
        }

        if (dataEvento && dataDevolucao && dataDevolucao < dataEvento) {
            aplicarFeedbackData('#dia_semana_devolucao', 'erro', 'antes do evento');
            aplicarFeedbackData('#dia_semana_evento', 'info', 'referência');

            if (mostrarAlerta) {
                Swal.fire({
                    title: 'Data de devolução inválida',
                    html: 'A <strong>data da devolução/coleta</strong> não pode ser antes da <strong>data do evento</strong>.<br><br>' +
                          '<strong>Evento:</strong> ' + escapeHtml(dataEventoStr) + '<br>' +
                          '<strong>Devolução:</strong> ' + escapeHtml(dataDevolucaoStr),
                    icon: 'warning',
                    confirmButtonText: 'Entendi'
                });
            }

            return false;
        }

        if (dataEntrega && dataDevolucao && dataDevolucao < dataEntrega) {
            aplicarFeedbackData('#dia_semana_devolucao', 'erro', 'antes da entrega');
            aplicarFeedbackData('#dia_semana_entrega', 'info', 'referência');

            if (mostrarAlerta) {
                Swal.fire({
                    title: 'Período logístico inválido',
                    html: 'A <strong>data da devolução/coleta</strong> não pode ser antes da <strong>data da entrega</strong>.<br><br>' +
                          '<strong>Entrega:</strong> ' + escapeHtml(dataEntregaStr) + '<br>' +
                          '<strong>Devolução:</strong> ' + escapeHtml(dataDevolucaoStr),
                    icon: 'warning',
                    confirmButtonText: 'Entendi'
                });
            }

            return false;
        }

        marcarDatasLogisticasComoOk(dataEvento, dataEntrega, dataDevolucao);
        return true;
    }

    function marcarLinhasPedidoComoPeriodoInvalido() {
        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
            const $row = $(this);

            if (!['PRODUTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '')) {
                return;
            }

            limparStatusLinhaDisponibilidade($row);

            $row.find('.disponibilidade-contexto')
                .removeClass('status-ok status-atencao status-indisponivel')
                .addClass('status-neutro')
                .html('<span class="status-principal">AJUSTAR DATAS</span><span class="status-detalhe">· período inválido</span>')
                .show();
        });
    }
    
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


    $('#formEditarPedido').on('submit', function(e) {
        atualizarOrdemDosItens();

        if (!validarSequenciaDatasLogisticaPedido(true)) {
            e.preventDefault();
            return false;
        }

        if (!validarTodosConjuntosAntesSalvar(true)) {
            e.preventDefault();
            return false;
        }
        let erroQuantidade = null;
        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
            const $row = $(this);
            if (['PRODUTO', 'CONJUNTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '')) {
                const quantidade = parseInt($row.find('.item-qtd').val(), 10) || 0;
                const nomeProduto = $row.find('.nome_produto_display').val() || 'Produto sem nome';
                if (quantidade <= 0) {
                    erroQuantidade = nomeProduto;
                    return false;
                }
            }
        });

        if (erroQuantidade) {
            e.preventDefault();
            Swal.fire({
                title: 'Quantidade obrigatória',
                text: 'Há produto com quantidade zero no pedido: ' + erroQuantidade + '. Ajuste a quantidade ou remova a linha antes de salvar.',
                icon: 'warning',
                confirmButtonText: 'Entendi'
            });
            return false;
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
            // Não regrava data-index: ele é o identificador visual usado para ligar conjunto, guias e filhos.
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

    function inicializarConjuntosExistentes() {
        let ultimoConjuntoIndex = null;

        $('#tabela_itens_pedido tbody tr.item-pedido-row').each(function() {
            const $row = $(this);
            const tipoLinha = $row.data('tipo-linha') || '';

            if (tipoLinha === 'CONJUNTO') {
                ultimoConjuntoIndex = parseInt($row.data('index') || 0, 10) || 0;
                $row.addClass('item-conjunto-row');

                if (!$row.find('.btn_montar_conjunto').length) {
                    $row.find('td:last .drag-handle').after('<button type="button" class="btn btn-xs btn-primary btn_montar_conjunto" title="Montar/continuar conjunto"><i class="fas fa-plus-circle"></i></button><button type="button" class="btn btn-xs btn-secondary btn_encerrar_conjunto" title="Encerrar montagem"><i class="fas fa-check"></i></button>');
                }

                const produtoId = parseInt($row.find('.produto_id').val(), 10) || 0;
                if (produtoId > 0) {
                    carregarRegrasConjunto(produtoId, $row, function() {
                        atualizarGuiasConjunto($row);
                    });
                }
            } else if (tipoLinha === 'ITEM_CONJUNTO') {
                $row.addClass('item-conjunto-filho-row');
                if (ultimoConjuntoIndex !== null) {
                    $row.attr('data-conjunto-pai-index', ultimoConjuntoIndex).data('conjunto-pai-index', ultimoConjuntoIndex);
                }
                $row.find('.item-valor-unitario, .desconto_item').prop('readonly', true).val(formatCurrency(0));
                $row.find('.subtotal_item_display').text(formatCurrency(0));
            }
        });
    }

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
        inicializarConjuntosExistentes();
        if (!window.calculandoTotais) {
            calcularTotaisPedido();
        }
        revalidarTodasAsLinhasDisponibilidade();
    }, 500);

    // Salvar Cliente Modal (AJAX)
    $('#btnSalvarClienteModal').on('click', function() {
        var formArray = $('#formNovoClienteModal').serializeArray();
        formArray.push({ name: 'ajax', value: 'salvar_cliente_modal' });
        var formData = $.param(formArray);
        $.ajax({
            url: window.location.pathname + window.location.search,
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
                    $(newOption).attr('data-cliente-full-data', JSON.stringify(response.cliente));
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