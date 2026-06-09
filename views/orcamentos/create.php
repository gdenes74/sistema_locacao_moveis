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
require_once __DIR__ . '/../../models/ConfiguracaoTexto.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('obterTextoPadraoSistema')) {
    function obterTextoPadraoSistema(PDO $db, string $chave, string $fallback = ''): string
    {
        try {
            // Prioriza a leitura direta da matriz no banco para evitar textos antigos
            // ficarem aparecendo quando já foram atualizados em Configurações > Textos Padrão.
            $stmt = $db->prepare("SELECT conteudo FROM configuracoes_textos WHERE chave = :chave AND ativo = 1 LIMIT 1");
            $stmt->bindParam(':chave', $chave, PDO::PARAM_STR);
            $stmt->execute();
            $conteudo = $stmt->fetchColumn();

            if ($conteudo !== false && trim((string) $conteudo) !== '') {
                return (string) $conteudo;
            }

            return $fallback;
        } catch (Throwable $e) {
            error_log('[orcamentos/create.php] Falha ao obter texto padrão ' . $chave . ': ' . $e->getMessage());
            return $fallback;
        }
    }
}

$database = new Database();
$db = $database->getConnection(); // Conexão PDO

$clienteModel = new Cliente($db);
$numeracaoModel = new NumeracaoSequencial($db);
$orcamentoModel = new Orcamento($db); // Instância do nosso model ajustado
$estoqueModel = new EstoqueMovimentacao($db);
$numeroFormatado = 'Gerado ao Salvar';

// Textos padrão vindos de Configurações > Textos Padrão.
// No create, eles apenas pré-preenchem os campos; ao salvar, o texto fica gravado neste orçamento.
$fallbackObservacoesOrcamento = "# Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.
# Não inclui posicionamento dos móveis no local.
# DOMINGO/FERIADO após as 8h e antes das 12h: Taxa R$ 250,00
# MADRUGADA após as 4:30h e antes das 8:30h: Taxa R$ 800,00
# HORÁRIO ESPECIAL após as 12h de sábado até as 23:30h de segunda a sábado: Taxa R$ 500,00
# HORA MARCADA segunda a sexta das 8:30h até as 17h e sábado das 8:30h às 12h: Taxa R$ 200,00
# Infelizmente não dispomos de entregas ou coletas no período das 23:30h às 5h.";
$fallbackCondicoesOrcamento = "Entrada de 30% para reserva, via PIX ou depósito.
O saldo deverá ser pago via PIX ou depósito até 7 dias antes da data do evento.
Consulte previamente a disponibilidade e as condições para locações com período estendido, mais de uma diária ou necessidades especiais de entrega/coleta.";

$textoPadraoObservacoes = obterTextoPadraoSistema($db, 'observacoes_gerais_padrao', $fallbackObservacoesOrcamento);
$textoPadraoCondicoes = obterTextoPadraoSistema($db, 'condicoes_pagamento_padrao', $fallbackCondicoesOrcamento);

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

       $sql = "SELECT 
            p.id, p.codigo, p.nome_produto, p.descricao_detalhada, p.preco_locacao, 
            p.quantidade_total, p.foto_path, p.tipo_produto, COALESCE(p.eh_conjunto, 0) AS eh_conjunto,
            p.subcategoria_id,
            s.categoria_id
        FROM produtos p
        LEFT JOIN subcategorias s ON s.id = p.subcategoria_id";

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

// --- Bloco AJAX para buscar grupos/regras de um conjunto ---
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
        error_log("Erro AJAX buscar_grupos_conjunto: " . $e->getMessage());
        echo json_encode(['error' => 'Erro no banco de dados ao buscar regras do conjunto.']);
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
                } else if (in_array($tipo_linha_atual, ['PRODUTO', 'CONJUNTO', 'ITEM_CONJUNTO'], true)) {
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

                    // ITEM_CONJUNTO é produto operacional: consulta estoque normalmente,
                    // mas não soma preço no orçamento. O preço fica apenas na linha CONJUNTO.
                    if ($tipo_linha_atual === 'ITEM_CONJUNTO') {
                        $item_data['preco_unitario'] = 0.00;
                        $item_data['desconto'] = 0.00;
                        $item_data['preco_final'] = 0.00;
                    } else {
                        $item_data['preco_unitario'] = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                        $item_data['desconto'] = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');
                        $item_data['preco_final'] = $item_data['quantidade'] * ($item_data['preco_unitario'] - $item_data['desconto']);
                    }

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

            <form id="formNovoOrcamento" action="create.php" method="POST" novalidate autocomplete="off">
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
                                        name="data_orcamento" value="<?= date('d/m/Y') ?>" autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false" required>
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
                                        name="data_evento" placeholder="DD/MM/AAAA" autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false">
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
                                        name="data_entrega" placeholder="DD/MM/AAAA" autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false">
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
                                        name="data_devolucao_prevista" placeholder="DD/MM/AAAA" autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false">
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
                        <div class="row mb-3 orcamento-busca-barra">
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

                        <div class="itens-scroll-container mt-3">
                            <div class="table-responsive mb-0">
                            <table class="table table-bordered table-hover mb-0" id="tabela_itens_orcamento">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 34%;">Produto/Serviço/Seção <span class="text-danger">*</span>
                                        </th>
                                        <th style="width: 20%;">Status</th>
                                        <th style="width: 6%;">Qtd. <span class="text-danger">*</span></th>
                                        <th style="width: 11%;">Vlr. Unit. (R$)</th>
                                        <th style="width: 8%;">Desc. Item (R$)</th>
                                        <th style="width: 9%;">Subtotal (R$)</th>
                                        <th style="width: 12%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Linhas de itens e títulos serão adicionadas aqui via JavaScript -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-right"><strong>Subtotal dos Itens:</strong></td>
                                        <td id="subtotal_geral_itens" class="text-right font-weight-bold">A confirmar
                                        </td>
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
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="observacoes_gerais" class="mb-0">Observações Gerais</label>
                                        <button type="button" class="btn btn-xs btn-outline-primary btn-usar-texto-padrao" data-target-textarea="observacoes_gerais" data-chave-padrao="observacoes">
                                            <i class="fas fa-magic"></i> Usar texto padrão
                                        </button>
                                    </div>
                                    <textarea class="form-control" id="observacoes_gerais" name="observacoes_gerais" rows="6" placeholder="Ex: Cliente solicitou montagem especial..."><?= htmlspecialchars($textoPadraoObservacoes ?? '') ?></textarea>
                                    <small class="form-text text-muted">Este texto será salvo somente neste orçamento. Para alterar o padrão definitivo, use Configurações &gt; Textos Padrão.</small>
                                </div>
                                <div class="form-group">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="condicoes_pagamento" class="mb-0">Condições de Pagamento</label>
                                        <button type="button" class="btn btn-xs btn-outline-primary btn-usar-texto-padrao" data-target-textarea="condicoes_pagamento" data-chave-padrao="condicoes">
                                            <i class="fas fa-magic"></i> Usar texto padrão
                                        </button>
                                    </div>
                                    <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento" rows="5" placeholder="Ex: Entrada, saldo, PIX, locação estendida..."><?= htmlspecialchars($textoPadraoCondicoes ?? '') ?></textarea>
                                    <small class="form-text text-muted">Este texto será salvo somente neste orçamento. Para alterar o padrão definitivo, use Configurações &gt; Textos Padrão.</small>
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

                    /* Área de inclusão de itens com busca normal e rolagem própria na tabela.
                       A busca fica acima da grade; somente a lista de itens rola, como uma planilha. */
                    .orcamento-busca-barra {
                        position: relative;
                        z-index: 1020;
                        background: #ffffff;
                    }
                    .orcamento-busca-barra .col-md-7 {
                        position: relative;
                    }
                    .orcamento-busca-barra #sugestoes_produtos {
                        z-index: 1060 !important;
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

                    /* Status compacto de disponibilidade na coluna própria */
                    #tabela_itens_orcamento th,
                    #tabela_itens_orcamento td {
                        vertical-align: middle !important;
                    }
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
                    .disponibilidade-contexto .status-abrir {
                        display: none;
                    }
                    #tabela_itens_orcamento td:last-child {
                        white-space: nowrap;
                        min-width: 105px;
                    }
                    #tabela_itens_orcamento td:last-child .btn,
                    #tabela_itens_orcamento td:last-child .drag-handle {
                        display: inline-block;
                        margin-right: 5px !important;
                        vertical-align: middle;
                    }
                    #tabela_itens_orcamento .desconto_item {
                        min-width: 76px;
                    }
                    #tabela_itens_orcamento .subtotal_item_display {
                        min-width: 82px;
                    }
                    .disponibilidade-contexto.status-neutro {
                        background: #eef2f7;
                        border: 1px solid #cbd5e1;
                        color: #475569;
                    }
                    .item-conjunto-row td {
                        border-top: 2px solid #0d6efd !important;
                        border-bottom: 1px solid #b8daff !important;
                    }
                    .item-conjunto-filho-row td {
                        border-left: 0 !important;
                    }
                    .item-conjunto-filho-row td:first-child {
                        border-left: 4px solid #8bbafe !important;
                    }
                    .regras-conjunto-resumo {
                        line-height: 1.35;
                    }
                    .conjunto-grupo-guia td {
                        background: #f8fbff !important;
                        border-left: 4px solid #6ea8fe !important;
                        font-size: 0.82rem;
                        padding-top: 6px !important;
                        padding-bottom: 6px !important;
                        cursor: pointer;
                    }
                    .conjunto-grupo-guia:hover td {
                        background: #eef6ff !important;
                    }
                    .conjunto-grupo-guia .grupo-status-badge {
                        display: inline-block;
                        border-radius: 999px;
                        padding: 2px 7px;
                        font-size: 0.70rem;
                        font-weight: 800;
                    }
                    .conjunto-grupo-guia .grupo-ok {
                        background: #d1e7dd;
                        color: #0f5132;
                    }
                    .conjunto-grupo-guia .grupo-pendente {
                        background: #fff3cd;
                        color: #664d03;
                    }
                    .conjunto-grupo-guia .grupo-excesso {
                        background: #f8d7da;
                        color: #842029;
                    }
                    .foto-produto-linha {
                        cursor: zoom-in;
                    }
                    /* Datepicker: mantém o visual original, mas força o calendário a ficar por cima dos inputs. */
                    .datepicker-dropdown,
                    .datepicker-dropdown.dropdown-menu,
                    body > .datepicker-dropdown,
                    body > .datepicker-dropdown.dropdown-menu {
                        z-index: 999999 !important;
                        background: #ffffff !important;
                        border: 1px solid #ced4da !important;
                        box-shadow: 0 8px 18px rgba(0,0,0,0.18) !important;
                    }
                    .datepicker-dropdown table,
                    .datepicker-dropdown .datepicker-days,
                    .datepicker-dropdown .datepicker-months,
                    .datepicker-dropdown .datepicker-years {
                        background: #ffffff !important;
                    }
                    .datepicker-dropdown:before,
                    .datepicker-dropdown:after {
                        z-index: 1000000 !important;
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
    // Evita sugestões antigas do navegador cobrindo o calendário.
    // Truque seguro: campo começa readonly para o Chrome não abrir histórico/autocomplete;
    // ao focar/clicar, remove readonly e mantém digitação normal.
    $('.datepicker').attr({
        'autocomplete': 'off',
        'aria-autocomplete': 'none',
        'inputmode': 'numeric',
        'autocorrect': 'off',
        'autocapitalize': 'off',
        'spellcheck': 'false',
        'readonly': 'readonly'
    });
    $('#btnUsarEnderecoCliente').hide(); // <-- ESCONDE O BOTAO ADIC ENDERECO CLIENTE
    var itemIndex = 0;
    var disponibilidadeRequestSeq = 0;
    var conjuntoAtivoIndex = null;
    var conjuntoAtivoNome = '';
    var textosPadraoOrcamento = window.TEXTOS_PADRAO_ORCAMENTO || {};
    $('#observacoes_gerais, #condicoes_pagamento').scrollTop(0);

    $(document).on('click', '.btn-usar-texto-padrao', function() {
        var targetId = $(this).data('target-textarea');
        var chave = $(this).data('chave-padrao');
        var textoPadrao = textosPadraoOrcamento[chave] || '';
        var $campo = $('#' + targetId);

        if (!$campo.length) {
            return;
        }

        if (!textoPadrao) {
            if (typeof toastr !== 'undefined') {
                toastr.warning('Texto padrão não encontrado em Configurações > Textos Padrão.');
            } else {
                alert('Texto padrão não encontrado em Configurações > Textos Padrão.');
            }
            return;
        }

        var aplicarTexto = function() {
            $campo.val(textoPadrao).trigger('change').scrollTop(0).focus();
        };

        if ($campo.val().trim() !== '' && $campo.val().trim() !== textoPadrao.trim() && typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Usar texto padrão atual?',
                text: 'O texto deste orçamento será substituído pelo padrão atual do sistema. Isso não altera a matriz.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, substituir',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (result.isConfirmed) {
                    aplicarTexto();
                }
            });
        } else if ($campo.val().trim() !== '' && $campo.val().trim() !== textoPadrao.trim()) {
            if (confirm('Substituir o texto deste orçamento pelo padrão atual do sistema?')) {
                aplicarTexto();
            }
        } else {
            aplicarTexto();
        }
    });

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
        const produtoConsultadoId = parseInt(response.produto_id || 0, 10);
        const produtoConsultadoNome = response.produto_nome || '';

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
                const quantidade = parseInt(item.quantidade || 0, 10);
                const produtoOrigemId = parseInt(item.produto_origem_id || item.produto_id || 0, 10);
                const produtoOrigemNome = item.produto_origem_nome || item.produto_nome || item.componente_nome || produtoConsultadoNome || 'Produto';
                const destaque = produtoOrigemId > 0 && produtoOrigemId === produtoConsultadoId;
                const classeDestaque = destaque ? ' style="background:rgba(255,255,255,.18); border-radius:8px; padding:4px 6px; margin:3px 0;"' : '';
                const badge = destaque ? ' <span class="badge badge-light text-dark ml-1">produto consultado</span>' : '';
                const pedidoId = parseInt(item.pedido_id || 0, 10);
                const linkPedido = pedidoId > 0
                    ? ` <a href="../pedidos/show.php?id=${pedidoId}" target="_blank" class="btn btn-xs btn-light ml-1" title="Abrir pedido"><i class="fas fa-eye"></i></a>`
                    : '';

                return `<li${classeDestaque}>
                    <strong>${escapeHtml(item.cliente || 'Cliente')}</strong>
                    — ${quantidade} un. de <strong>${escapeHtml(produtoOrigemNome)}</strong>${badge}
                    <span style="opacity:.85;">(${escapeHtml(item.inicio_formatado || '')} → ${escapeHtml(item.fim_formatado || '')})</span>${linkPedido}
                </li>`;
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
        const pedidosPeriodo = parseInt(comp.comprometido_periodo || 0, 10);
        const reservadoComponente = parseInt((comp.reservado_orcamento_atual ?? comp.quantidade_necessaria ?? 0), 10);
        const livrePeriodo = parseInt(
            comp.livre_periodo !== undefined
                ? comp.livre_periodo
                : (comp.estoque_disponivel !== undefined ? comp.estoque_disponivel : estoqueTotal),
            10
        );
        const livreApos = parseInt(
            comp.livre_apos_orcamento !== undefined
                ? comp.livre_apos_orcamento
                : livrePeriodo,
            10
        );
        const faltanteComponente = parseInt(comp.faltante_orcamento || 0, 10);
        const qtdPorUnidade = parseFloat(comp.quantidade_por_unidade || comp.quantidade || 1);

        return `<li>
            <strong>${escapeHtml(nome)}</strong>
            — usa ${qtdPorUnidade} por unidade
            · estoque total: ${estoqueTotal}
            · pedidos no período: ${pedidosPeriodo}
            · neste orçamento: ${reservadoComponente}
            · livre após orçamento: ${livreApos}${faltanteComponente > 0 ? ` · faltando ${faltanteComponente}` : ''}
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

        let detalhe = `· Ped. ${comprometido} · Orç. ${reservadoAtual} · Livre ${livreApos}`;
        if (faltante > 0) {
            detalhe = `· Faltando ${faltante}`;
        }

        return `<span class="status-principal">${statusTexto}</span><span class="status-detalhe">${detalhe}</span>`;
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

function coletarItensContextoAtualOrcamento() {
        const itens = [];

        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            const $row = $(this);

            if (!['PRODUTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '')) {
                return;
            }

            const produtoId = parseInt($row.find('.produto_id').val(), 10) || 0;
            const quantidade = parseInt($row.find('.quantidade_item').val(), 10) || 0;

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
        const itensContexto = coletarItensContextoAtualOrcamento();

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
                turno_fim: periodo.turno_fim,
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
        // O contexto completo enviado ao backend continua considerando todos os itens do orçamento,
        // inclusive produtos diferentes que compartilham componentes.
        $('#tabela_itens_orcamento .produto_id').each(function() {
            if ($(this).val() == produtoId) {
                quantidade += parseInt($(this).closest('tr').find('.quantidade_item').val(), 10) || 0;
            }
        });

        if (quantidade < 0) { quantidade = 0; }

        const requestSeq = ++disponibilidadeRequestSeq;
        $row.data('disponibilidade-request-seq', requestSeq);

        consultarDisponibilidadeAjax(produtoId, quantidade, function(response) {
            // Evita resposta AJAX antiga sobrescrever consulta mais recente.
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

                const jaAlertado = $row.data('alerta-indisponivel-chave');

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
            }

            if (response && response.disponivel !== false) {
                $row.removeData('alerta-indisponivel-chave');
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

    function revalidarTodasAsLinhasDisponibilidade($rowAlerta = null) {
        // Segurança operacional: se as datas logísticas estiverem contraditórias,
        // não chama o AJAX de estoque para não pintar produtos como indisponíveis por erro de data.
        if (typeof validarSequenciaDatasLogisticaCreate === 'function' && !validarSequenciaDatasLogisticaCreate(false)) {
            if (typeof marcarLinhasComoPeriodoInvalido === 'function') {
                marcarLinhasComoPeriodoInvalido();
            }
            return;
        }

        const rowAlertaEl = $rowAlerta && $rowAlerta.length ? $rowAlerta[0] : null;

        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            const $row = $(this);
            if (['PRODUTO', 'ITEM_CONJUNTO'].includes($row.data('tipo-linha') || '') && $row.find('.produto_id').val()) {
                const deveAlertar = rowAlertaEl && this === rowAlertaEl;
                atualizarContextoDisponibilidadeLinha($row, deveAlertar);
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
                        let ehConjunto = parseInt(produto.eh_conjunto || 0, 10) === 1;
                        let etiquetaProduto = ehConjunto
                            ? '<small class="d-block text-primary font-weight-bold"><i class="fas fa-layer-group"></i> Conjunto comercial</small><small class="d-block text-muted">Selecione para montar os itens internos</small>'
                            : (produto.tipo_produto === 'COMPOSTO'
                                ? '<small class="d-block text-info">Estoque por componentes</small><small class="d-block text-muted">Selecione para consultar capa e estrutura</small>'
                                : (produto.quantidade_total !== null ? '<small class="d-block text-info">Estoque: ' + produto.quantidade_total + '</small>' : '')
                            );

                        $('#sugestoes_produtos').append(`<a href="#" class="list-group-item list-group-item-action d-flex align-items-center item-sugestao-produto py-2" data-id="${produto.id}" data-nome="${produto.nome_produto || 'Sem nome'}" data-codigo="${produto.codigo || ''}" data-preco="${preco}" data-foto-completa="${fotoPathParaDataAttribute}" data-eh-conjunto="${ehConjunto ? 1 : 0}" data-categoria-id="${produto.categoria_id || ''}" data-subcategoria-id="${produto.subcategoria_id || ''}">${fotoHtml}<div class="flex-grow-1"><strong>${produto.nome_produto || 'Sem nome'}</strong>${produto.codigo ? '<small class="d-block text-muted">Cód: ' + produto.codigo + '</small>' : ''}${etiquetaProduto}</div><span class="ml-auto text-primary font-weight-bold">R$ ${preco.toFixed(2).replace('.', ',')}</span></a>`);
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



    function atualizarListaProdutosAposSelecao() {
        $('#busca_produto').val('').focus();
        if ($('#busca_categoria_produto').val()) {
            setTimeout(function() {
                carregarSugestoesProdutos();
            }, 80);
        } else {
            $('#sugestoes_produtos').empty().hide();
        }
    }

    function abrirFotoProduto(fotoUrl, nomeProduto) {
        if (!fotoUrl) { return; }
        Swal.fire({
            title: nomeProduto || 'Produto',
            imageUrl: fotoUrl,
            imageAlt: 'Foto ampliada de ' + (nomeProduto || 'Produto'),
            imageWidth: '90%',
            confirmButtonText: 'Fechar'
        });
    }

    function inserirLinhasGuiaConjunto($rowConjunto, grupos) {
        if (!$rowConjunto || !$rowConjunto.length || !grupos || !grupos.length) { return; }

        const conjuntoIndex = parseInt($rowConjunto.data('index'), 10) || 0;
        $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').remove();

        let htmlGuias = '';
        grupos.forEach(function(grupo, idx) {
            const qtd = parseFloat(grupo.quantidade_por_conjunto || 0) || 0;
            const origem = grupo.subcategoria_nome || grupo.categoria_nome || 'produtos configurados';
            htmlGuias += `<tr class="conjunto-grupo-guia" title="Clique para listar opções deste grupo" data-conjunto-pai-index="${conjuntoIndex}" data-regra-index="${idx}" data-categoria-id="${grupo.categoria_id || ''}" data-subcategoria-id="${grupo.subcategoria_id || ''}">
                <td colspan="7" style="padding-left: 32px;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <div>
                            <strong class="text-primary"><i class="fas fa-layer-group mr-1"></i>${escapeHtml(grupo.nome_grupo || 'Grupo')}</strong>
                            <span class="text-muted ml-2">${qtd} por conjunto · ${escapeHtml(origem)}</span>
                        </div>
                        <span class="grupo-status-badge grupo-pendente ml-2">Falta calcular</span>
                    </div>
                </td>
            </tr>`;
        });

        $rowConjunto.after(htmlGuias);
        atualizarGuiasConjunto($rowConjunto);

        // Não abre a lista automaticamente: evita que a busca fique sobreposta na tela.
        // O atendente clica no grupo (Bistrô/Pufes/etc.) quando quiser listar as opções.
    }

    function atualizarGuiasConjunto($rowConjunto) {
        if (!$rowConjunto || !$rowConjunto.length) { return; }
        const conjuntoIndex = parseInt($rowConjunto.data('index'), 10) || 0;
        const regras = $rowConjunto.data('regras-conjunto') || [];
        const qtdConjunto = parseInt($rowConjunto.find('.quantidade_item').val(), 10) || 0;

        $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').each(function() {
            const $guia = $(this);
            const regraIndex = parseInt($guia.data('regra-index'), 10) || 0;
            const regra = regras[regraIndex];
            if (!regra) { return; }

            const esperado = qtdConjunto * (parseFloat(regra.quantidade_por_conjunto || 0) || 0);
            const atual = somarItensDoGrupoConjunto($rowConjunto, regra, null);
            const diferenca = esperado - atual;
            const $badge = $guia.find('.grupo-status-badge');

            $badge.removeClass('grupo-ok grupo-pendente grupo-excesso');

            if (diferenca === 0) {
                $badge.addClass('grupo-ok').text('OK: ' + atual + '/' + esperado);
            } else if (diferenca > 0) {
                $badge.addClass('grupo-pendente').text('Faltam ' + diferenca + ' · ' + atual + '/' + esperado);
            } else {
                $badge.addClass('grupo-excesso').text('Excesso ' + Math.abs(diferenca) + ' · ' + atual + '/' + esperado);
            }
        });
    }

    function rolarTabelaItensParaLinha($row) {
        var $container = $('.itens-scroll-container');
        if (!$container.length || !$row || !$row.length) { return; }

        setTimeout(function() {
            $container.stop(true).animate({
                scrollTop: $container[0].scrollHeight
            }, 180);
        }, 60);
    }

    function adicionarLinhaItemTabela(dadosItem = null, tipoLinhaParam) {
        itemIndex++;
        var tipoLinha = tipoLinhaParam;
        var htmlLinha = '';
        var nomeDisplay = dadosItem ? dadosItem.nome_produto : '';
        var produtoIdInput = dadosItem ? dadosItem.id : '';
        var precoUnitarioDefault = dadosItem ? (parseFloat(dadosItem.preco_locacao) || 0) : 0;
        var tipoItemLocVend = dadosItem ? (dadosItem.tipo_item_loc_vend || 'locacao') : 'locacao';
        var categoriaIdItem = dadosItem ? (parseInt(dadosItem.categoria_id || 0, 10) || '') : '';
        var subcategoriaIdItem = dadosItem ? (parseInt(dadosItem.subcategoria_id || 0, 10) || '') : '';
        var nomeInputName = "nome_produto_display[]";

        if (tipoLinha === 'CONJUNTO') {
            var quantidadeConjuntoDefault = 1;
            var subtotalConjuntoDefault = quantidadeConjuntoDefault * precoUnitarioDefault;
            var imagemConjuntoHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" class="foto-produto-linha" data-foto-completa="${dadosItem.foto_path_completo}" data-nome-produto="${nomeDisplay}" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #0d6efd; border-radius: 4px; vertical-align: middle;">` : '';
            htmlLinha = `<tr class="item-orcamento-row item-conjunto-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #eef6ff !important; border-left: 4px solid #0d6efd;">
                <td>${imagemConjuntoHtml}
                    <input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display font-weight-bold text-primary" value="${nomeDisplay}" placeholder="Nome do Conjunto" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" readonly>
                    <input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}">
                    <input type="hidden" name="tipo_linha[]" value="${tipoLinha}">
                    <input type="hidden" name="ordem[]" value="${itemIndex}">
                    <input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}">
                    <small class="text-primary font-weight-bold ml-1"><i class="fas fa-layer-group"></i> Conjunto comercial</small>
                    <small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Conjunto:</small>
                    <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do conjunto">
                </td>
                <td class="status-disponibilidade-cell text-center"><div class="disponibilidade-contexto status-neutro" style="display:none;"></div></td>
                <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeConjuntoDefault}" min="1" style="width: 70px;"></td>
                <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoUnitarioDefault.toFixed(2).replace('.', ',')}"></td>
                <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="0,00"></td>
                <td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalConjuntoDefault).replace('R$ ', '')}</td>
                <td>
                    <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                    <button type="button" class="btn btn-xs btn-primary btn_montar_conjunto" title="Montar/continuar conjunto"><i class="fas fa-plus-circle"></i></button>
                    <button type="button" class="btn btn-xs btn-secondary btn_encerrar_conjunto" title="Encerrar montagem"><i class="fas fa-check"></i></button>
                    <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button>
                    <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        } else if (tipoLinha === 'ITEM_CONJUNTO') {
            var quantidadeFilhoDefault = (dadosItem && dadosItem.quantidade !== undefined && dadosItem.quantidade !== null && dadosItem.quantidade !== '') ? parseInt(dadosItem.quantidade, 10) : 0; 
            var imagemFilhoHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" class="foto-produto-linha" data-foto-completa="${dadosItem.foto_path_completo}" data-nome-produto="${nomeDisplay}" style="width: 42px; height: 42px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">` : '';
            var conjuntoNomeEscapado = conjuntoAtivoNome ? escapeHtml(conjuntoAtivoNome) : 'conjunto';
            htmlLinha = `<tr class="item-orcamento-row item-conjunto-filho-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" data-conjunto-pai-index="${conjuntoAtivoIndex || ''}" data-categoria-id="${categoriaIdItem}" data-subcategoria-id="${subcategoriaIdItem}" style="background-color: #f8fbff !important;">
                <td style="padding-left: 28px;">
                    <span class="text-primary mr-1"><i class="fas fa-level-up-alt fa-rotate-90"></i></span>${imagemFilhoHtml}
                    <input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Item do conjunto" style="display: inline-block; width: calc(100% - 86px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}>
                    <input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}">
                    <input type="hidden" name="tipo_linha[]" value="${tipoLinha}">
                    <input type="hidden" name="ordem[]" value="${itemIndex}">
                    <input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}">
                    <small class="form-text text-muted">Item interno de: ${conjuntoNomeEscapado}. Consulta estoque normalmente, mas não soma preço.</small>
                    <small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small>
                    <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do item do conjunto">
                </td>
                <td class="status-disponibilidade-cell text-center"><div class="disponibilidade-contexto status-neutro" style="display:none;"></div></td>
                <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeFilhoDefault}" min="0" style="width: 70px;"></td>
                <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="0,00" readonly></td>
                <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="0,00" readonly></td>
                <td class="subtotal_item_display text-right font-weight-bold">0,00</td>
                <td>
                    <span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span>
                    <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button>
                    <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        } else if (tipoLinha === 'PRODUTO') {
            var quantidadeDefault = 0; var descontoDefault = 0;
            var subtotalDefault = quantidadeDefault * (precoUnitarioDefault - descontoDefault);
            var imagemHtml = dadosItem && dadosItem.foto_path_completo ? `<img src="${dadosItem.foto_path_completo}" alt="Miniatura" class="foto-produto-linha" data-foto-completa="${dadosItem.foto_path_completo}" data-nome-produto="${nomeDisplay}" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle;">` : '';
            htmlLinha = `<tr class="item-orcamento-row" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" data-categoria-id="${categoriaIdItem}" data-subcategoria-id="${subcategoriaIdItem}" style="background-color: #ffffff !important;"><td>${imagemHtml}<input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_produto_display" value="${nomeDisplay}" placeholder="Nome do Produto/Serviço" style="display: inline-block; width: calc(100% - 65px); vertical-align: middle;" ${dadosItem && dadosItem.id ? 'readonly' : ''}><input type="hidden" name="produto_id[]" class="produto_id" value="${produtoIdInput}"><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="tipo_item[]" value="${tipoItemLocVend}"><small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small><input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação do item"></td><td class="status-disponibilidade-cell text-center"><div class="disponibilidade-contexto status-neutro" style="display:none;"></div></td><td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item text-center item-qtd" value="${quantidadeDefault}" min="0" style="width: 70px;"></td><td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-input item-valor-unitario" value="${precoUnitarioDefault.toFixed(2).replace('.', ',')}"></td><td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-input" value="${descontoDefault.toFixed(2).replace('.', ',')}"></td><td class="subtotal_item_display text-right font-weight-bold">${formatCurrency(subtotalDefault).replace('R$ ', '')}</td><td><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><button type="button" class="btn btn-xs btn-info btn_obs_item" title="Observação"><i class="fas fa-comment-dots"></i></button> <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover"><i class="fas fa-trash"></i></button></td></tr>`;
        } else if (tipoLinha === 'CABECALHO_SECAO') {
            htmlLinha = `<tr class="item-orcamento-row item-titulo-secao" data-index="${itemIndex}" data-tipo-linha="${tipoLinha}" style="background-color: #e7f1ff !important;"><td colspan="6"><span class="drag-handle" style="cursor: move; margin-right: 10px; color: #555;"><i class="fas fa-arrows-alt"></i></span><input type="text" name="${nomeInputName}" class="form-control form-control-sm nome_titulo_secao" placeholder="Digite o Título da Seção aqui..." required style="font-weight: bold; border: none; background-color: transparent; display: inline-block; width: calc(100% - 30px);"><input type="hidden" name="produto_id[]" value=""><input type="hidden" name="tipo_linha[]" value="${tipoLinha}"><input type="hidden" name="ordem[]" value="${itemIndex}"><input type="hidden" name="quantidade[]" value="0"><input type="hidden" name="tipo_item[]" value=""><input type="hidden" name="valor_unitario[]" value="0.00"><input type="hidden" name="desconto_item[]" value="0.00"><input type="hidden" name="observacoes_item[]" value=""></td><td><button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Título"><i class="fas fa-trash"></i></button></td></tr>`;
        }
        if (htmlLinha) {
            $('#tabela_itens_orcamento tbody').append(htmlLinha);
            var $novaLinha = $('#tabela_itens_orcamento tbody tr:last-child');
            if (tipoLinha === 'CABECALHO_SECAO') {
                $novaLinha.find('.nome_titulo_secao').focus();
                rolarTabelaItensParaLinha($novaLinha);
            } else if (['PRODUTO', 'ITEM_CONJUNTO', 'CONJUNTO'].includes(tipoLinha)) {
                if (dadosItem && dadosItem.id && tipoLinha !== 'CONJUNTO') {
                    revalidarTodasAsLinhasDisponibilidade($novaLinha);
                }
                rolarTabelaItensParaLinha($novaLinha);
                setTimeout(function() {
                    $novaLinha.find('.quantidade_item').focus().select();
                }, 220);
            }
            calcularTotaisOrcamento();
            return $novaLinha;
        }

        return $();
    }

    function calcularSubtotalItem($row) {
        if ($row.data('tipo-linha') === 'CABECALHO_SECAO') { return 0; }
        if ($row.data('tipo-linha') === 'ITEM_CONJUNTO') {
            $row.find('.subtotal_item_display').text('0,00');
            return 0;
        }
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
        foto_path_completo: $(this).data('foto-completa'),
        eh_conjunto: parseInt($(this).data('eh-conjunto') || 0, 10),
        categoria_id: parseInt($(this).data('categoria-id') || 0, 10) || null,
        subcategoria_id: parseInt($(this).data('subcategoria-id') || 0, 10) || null
    };
    
    // Se for conjunto, cria linha pai e ativa modo de montagem.
    if (produto.eh_conjunto === 1) {
        adicionarConjuntoAoOrcamento(produto);
        return;
    }

    // Se existe conjunto ativo, o produto selecionado entra como item interno do conjunto.
    if (conjuntoAtivoIndex !== null) {
        adicionarItemAoConjuntoAtivo(produto);
        return;
    }

    // Fluxo normal de produto avulso.
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



    $('#busca_categoria_produto').on('click focus', function() {
        if ($(this).val()) {
            setTimeout(carregarSugestoesProdutos, 60);
        }
    });

    $('#tabela_itens_orcamento').on('click', '.foto-produto-linha', function(e) {
        e.preventDefault();
        e.stopPropagation();
        abrirFotoProduto($(this).data('foto-completa'), $(this).data('nome-produto'));
    });

    function selecionarGrupoConjunto($guia, abrirSugestoes = true) {
        const conjuntoIndex = parseInt($guia.data('conjunto-pai-index') || 0, 10) || 0;
        const categoriaId = $guia.data('categoria-id') || '';
        const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoIndex);

        if ($rowConjunto.length) {
            ativarMontagemConjunto($rowConjunto, false);
        }

        $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia').removeClass('table-active');
        $guia.addClass('table-active');

        if (categoriaId) {
            $('#busca_categoria_produto').val(String(categoriaId));
        }

        $('#busca_produto').val('').focus();

        if (abrirSugestoes) {
            carregarSugestoesProdutos();
        }
    }

    $('#tabela_itens_orcamento').on('click', 'tr.conjunto-grupo-guia', function(e) {
        e.preventDefault();
        selecionarGrupoConjunto($(this), true);
    });

    $(document).on('click', function(e) {
        if ($(e.target).closest('#sugestoes_produtos, #busca_produto, #busca_categoria_produto, .conjunto-grupo-guia').length === 0) {
            $('#sugestoes_produtos').empty().hide();
        }
    });

    $('#btn_adicionar_titulo_secao').click(function() {
        adicionarLinhaItemTabela(null, 'CABECALHO_SECAO');
    });

    $('#btn_adicionar_item_manual').click(function() {
        adicionarLinhaItemTabela(null, 'PRODUTO');
    });

    $('#tabela_itens_orcamento').on('click', '.btn_remover_item', function() {
        var $rowRemovida = $(this).closest('tr');
        const tipoLinhaRemovida = $rowRemovida.data('tipo-linha') || '';
        const conjuntoIndexRemovido = parseInt($rowRemovida.data('index') || 0, 10) || 0;
        const conjuntoPaiIndexRemovido = parseInt($rowRemovida.data('conjunto-pai-index') || 0, 10) || 0;

        if (tipoLinhaRemovida === 'CONJUNTO') {
            $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndexRemovido + '"]').remove();
            $('#tabela_itens_orcamento tbody tr.item-conjunto-filho-row[data-conjunto-pai-index="' + conjuntoIndexRemovido + '"]').remove();

            if (conjuntoAtivoIndex === conjuntoIndexRemovido) {
                conjuntoAtivoIndex = null;
                conjuntoAtivoNome = '';
            }
        }

        $rowRemovida.remove();

        if (tipoLinhaRemovida === 'ITEM_CONJUNTO' && conjuntoPaiIndexRemovido > 0) {
            atualizarGuiasConjunto(obterLinhaConjuntoPorIndex(conjuntoPaiIndexRemovido));
        }

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

    $('#tabela_itens_orcamento').on('click', '.btn_montar_conjunto', function() {
        ativarMontagemConjunto($(this).closest('tr'));
    });

    $('#tabela_itens_orcamento').on('click', '.btn_encerrar_conjunto', function() {
        if (!encerrarMontagemConjunto()) {
            return;
        }
        Swal.fire({
            title: 'Montagem encerrada',
            text: 'Os próximos produtos selecionados entrarão como itens normais do orçamento.',
            icon: 'success',
            timer: 1400,
            showConfirmButton: false
        });
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

function carregarRegrasConjunto(produtoId, $rowConjunto) {
    $.ajax({
        url: 'create.php',
        type: 'GET',
        dataType: 'json',
        data: {
            ajax: 'buscar_grupos_conjunto',
            produto_id: produtoId
        },
        success: function(grupos) {
            if (!grupos || !grupos.length) {
                return;
            }

            grupos = grupos.map(function(grupo) {
                grupo.categoria_id = parseInt(grupo.categoria_id || 0, 10) || 0;
                grupo.subcategoria_id = parseInt(grupo.subcategoria_id || 0, 10) || 0;
                grupo.quantidade_por_conjunto = parseFloat(grupo.quantidade_por_conjunto || 0) || 0;
                return grupo;
            });

            $rowConjunto.data('regras-conjunto', grupos);

            let resumo = grupos.map(function(grupo) {
                let qtd = parseFloat(grupo.quantidade_por_conjunto || 0);
                let origem = grupo.subcategoria_nome || grupo.categoria_nome || 'produtos configurados';
                return `${escapeHtml(grupo.nome_grupo || 'Grupo')}: ${qtd} por conjunto (${escapeHtml(origem)})`;
            }).join('<br>');

            $rowConjunto.find('.regras-conjunto-resumo').remove();
            $rowConjunto.find('td:first small.text-primary').after(`<small class="form-text text-muted regras-conjunto-resumo">${resumo}</small>`);
            inserirLinhasGuiaConjunto($rowConjunto, grupos);
        }
    });
}


function obterLinhaConjuntoPorIndex(conjuntoIndex) {
    return $('#tabela_itens_orcamento tbody tr.item-conjunto-row').filter(function() {
        return parseInt($(this).data('index'), 10) === parseInt(conjuntoIndex, 10);
    }).first();
}

function regraConjuntoCasaComItem(regra, $rowItem) {
    const categoriaItem = parseInt($rowItem.data('categoria-id') || 0, 10) || 0;
    const subcategoriaItem = parseInt($rowItem.data('subcategoria-id') || 0, 10) || 0;
    const regraCategoria = parseInt(regra.categoria_id || 0, 10) || 0;
    const regraSubcategoria = parseInt(regra.subcategoria_id || 0, 10) || 0;

    if (regraSubcategoria > 0) {
        return subcategoriaItem === regraSubcategoria;
    }
    if (regraCategoria > 0) {
        return categoriaItem === regraCategoria;
    }
    return false;
}

function regraConjuntoCasaComProduto(regra, produto) {
    if (!regra || !produto) { return false; }

    const categoriaProduto = parseInt(produto.categoria_id || 0, 10) || 0;
    const subcategoriaProduto = parseInt(produto.subcategoria_id || 0, 10) || 0;
    const regraCategoria = parseInt(regra.categoria_id || 0, 10) || 0;
    const regraSubcategoria = parseInt(regra.subcategoria_id || 0, 10) || 0;

    if (regraSubcategoria > 0) {
        return subcategoriaProduto === regraSubcategoria;
    }
    if (regraCategoria > 0) {
        return categoriaProduto === regraCategoria;
    }
    return false;
}

function obterRegraSelecionadaDoConjunto($rowConjunto) {
    const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
    const $guiaAtiva = $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia.table-active[data-conjunto-pai-index="' + conjuntoIndex + '"]').first();
    const regras = $rowConjunto.data('regras-conjunto') || [];

    if (!$guiaAtiva.length) {
        return null;
    }

    const regraIndex = parseInt($guiaAtiva.data('regra-index'), 10);
    if (isNaN(regraIndex) || !regras[regraIndex]) {
        return null;
    }

    return regras[regraIndex];
}

function selecionarGuiaDoConjuntoPorRegra($rowConjunto, regra) {
    if (!$rowConjunto || !$rowConjunto.length || !regra) { return; }

    const conjuntoIndex = parseInt($rowConjunto.data('index') || 0, 10) || 0;
    const regras = $rowConjunto.data('regras-conjunto') || [];
    let indiceRegra = -1;

    regras.forEach(function(r, idx) {
        if (indiceRegra >= 0) { return; }

        const mesmoId = parseInt(r.id || 0, 10) > 0 && parseInt(r.id || 0, 10) === parseInt(regra.id || 0, 10);
        const mesmaCategoria = parseInt(r.categoria_id || 0, 10) === parseInt(regra.categoria_id || 0, 10);
        const mesmaSubcategoria = parseInt(r.subcategoria_id || 0, 10) === parseInt(regra.subcategoria_id || 0, 10);

        if (mesmoId || (mesmaCategoria && mesmaSubcategoria)) {
            indiceRegra = idx;
        }
    });

    if (indiceRegra >= 0) {
        $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"]').removeClass('table-active');
        $('#tabela_itens_orcamento tbody tr.conjunto-grupo-guia[data-conjunto-pai-index="' + conjuntoIndex + '"][data-regra-index="' + indiceRegra + '"]').addClass('table-active');
    }
}


function obterRegraDoItemConjunto($rowItem) {
    const conjuntoIndex = parseInt($rowItem.data('conjunto-pai-index') || 0, 10) || 0;
    if (conjuntoIndex <= 0) { return null; }

    const $rowConjunto = obterLinhaConjuntoPorIndex(conjuntoIndex);
    if (!$rowConjunto.length) { return null; }

    const regras = $rowConjunto.data('regras-conjunto') || [];
    for (let i = 0; i < regras.length; i++) {
        if (regraConjuntoCasaComItem(regras[i], $rowItem)) {
            return { regra: regras[i], $rowConjunto: $rowConjunto };
        }
    }

    return null;
}

function somarItensDoGrupoConjunto($rowConjunto, regra, $rowIgnorar = null) {
    const conjuntoIndex = parseInt($rowConjunto.data('index'), 10) || 0;
    let total = 0;

    $('#tabela_itens_orcamento tbody tr.item-conjunto-filho-row').each(function() {
        const $row = $(this);
        if (parseInt($row.data('conjunto-pai-index') || 0, 10) !== conjuntoIndex) {
            return;
        }
        if ($rowIgnorar && $rowIgnorar.length && this === $rowIgnorar[0]) {
            return;
        }
        if (!regraConjuntoCasaComItem(regra, $row)) {
            return;
        }
        total += parseInt($row.find('.quantidade_item').val(), 10) || 0;
    });

    return total;
}

function validarLimiteItemConjunto($rowItem, mostrarAlerta = true) {
    if (($rowItem.data('tipo-linha') || '') !== 'ITEM_CONJUNTO') {
        return true;
    }

    const info = obterRegraDoItemConjunto($rowItem);
    if (!info) {
        return true;
    }

    const qtdConjunto = parseInt(info.$rowConjunto.find('.quantidade_item').val(), 10) || 0;
    const limiteGrupo = qtdConjunto * (parseFloat(info.regra.quantidade_por_conjunto || 0) || 0);
    const jaUsado = somarItensDoGrupoConjunto(info.$rowConjunto, info.regra, $rowItem);
    const maxLinha = Math.max(0, Math.floor(limiteGrupo - jaUsado));
    let qtdLinha = parseInt($rowItem.find('.quantidade_item').val(), 10) || 0;

    if (qtdLinha > maxLinha) {
        $rowItem.find('.quantidade_item').val(maxLinha);
        qtdLinha = maxLinha;
        calcularTotaisOrcamento();

        if (mostrarAlerta) {
            Swal.fire({
                title: 'Limite do conjunto',
                html: 'Este conjunto permite no máximo <strong>' + limiteGrupo + '</strong> item(ns) no grupo <strong>' + escapeHtml(info.regra.nome_grupo || 'Grupo') + '</strong>.<br>Esta linha foi ajustada para <strong>' + maxLinha + '</strong>.',
                icon: 'warning',
                confirmButtonText: 'Entendi'
            });
        }
        return false;
    }

    return true;
}

function validarFechamentoConjunto($rowConjunto, mostrarAlerta = true) {
    if (!$rowConjunto || !$rowConjunto.length) { return true; }

    const regras = $rowConjunto.data('regras-conjunto') || [];
    if (!regras.length) { return true; }

    const conjuntoIndex = parseInt($rowConjunto.data('index'), 10) || 0;
    const qtdConjunto = parseInt($rowConjunto.find('.quantidade_item').val(), 10) || 0;
    const problemas = [];

    // 1) Nenhum item interno pode ficar com quantidade zero.
    $('#tabela_itens_orcamento tbody tr.item-conjunto-filho-row').each(function() {
        const $item = $(this);
        if (parseInt($item.data('conjunto-pai-index') || 0, 10) !== conjuntoIndex) {
            return;
        }

        const nomeItem = $item.find('.nome_produto_display').val() || 'Item do conjunto';
        const qtdItem = parseInt($item.find('.quantidade_item').val(), 10) || 0;

        if (qtdItem <= 0) {
            problemas.push({
                grupo: 'Quantidade obrigatória',
                esperado: 'maior que zero',
                atual: '0 em ' + nomeItem
            });
            return;
        }

        // 2) Item interno precisa pertencer a algum grupo configurado do conjunto.
        let casaComAlgumaRegra = false;
        for (let i = 0; i < regras.length; i++) {
            if (regraConjuntoCasaComItem(regras[i], $item)) {
                casaComAlgumaRegra = true;
                break;
            }
        }

        if (!casaComAlgumaRegra) {
            problemas.push({
                grupo: 'Produto fora das regras do conjunto',
                esperado: 'categoria/subcategoria permitida',
                atual: nomeItem
            });
        }
    });

    // 3) Cada grupo precisa fechar exatamente a quantidade esperada.
    regras.forEach(function(regra) {
        const esperado = qtdConjunto * (parseFloat(regra.quantidade_por_conjunto || 0) || 0);
        const atual = somarItensDoGrupoConjunto($rowConjunto, regra, null);
        if (atual !== esperado) {
            problemas.push({
                grupo: regra.nome_grupo || 'Grupo',
                esperado: esperado,
                atual: atual
            });
        }
    });

    if (problemas.length) {
        if (mostrarAlerta) {
            const linhas = problemas.map(function(p) {
                return '<li><strong>' + escapeHtml(p.grupo) + '</strong>: precisa ' + escapeHtml(String(p.esperado)) + ', lançado ' + escapeHtml(String(p.atual)) + '</li>';
            }).join('');

            Swal.fire({
                title: 'Conjunto incompleto ou inválido',
                html: '<p>Antes de encerrar a montagem, ajuste os itens internos do conjunto.</p><ul class="text-left">' + linhas + '</ul>',
                icon: 'warning',
                confirmButtonText: 'Entendi'
            });
        }
        return false;
    }

    return true;
}

function validarTodosConjuntosAntesSalvar(mostrarAlerta = true) {
    let ok = true;
    $('#tabela_itens_orcamento tbody tr.item-conjunto-row').each(function() {
        if (!validarFechamentoConjunto($(this), mostrarAlerta)) {
            ok = false;
            return false;
        }
    });
    return ok;
}

function ativarMontagemConjunto($rowConjunto, mostrarMensagem = true) {
    conjuntoAtivoIndex = parseInt($rowConjunto.data('index'), 10) || null;
    conjuntoAtivoNome = $rowConjunto.find('.nome_produto_display').val() || 'Conjunto';

    $('#tabela_itens_orcamento tbody tr.item-conjunto-row').removeClass('table-primary');
    $rowConjunto.addClass('table-primary');

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
    $('#tabela_itens_orcamento tbody tr.item-conjunto-row').removeClass('table-primary');
    return true;
}

function adicionarConjuntoAoOrcamento(produto) {
    var $rowConjunto = adicionarLinhaItemTabela(produto, 'CONJUNTO');
    $('#busca_produto').val('').blur();
    $('#sugestoes_produtos').empty().hide();

    if ($rowConjunto && $rowConjunto.length) {
        carregarRegrasConjunto(produto.id, $rowConjunto);
        ativarMontagemConjunto($rowConjunto);
    }
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

    // Se o atendente não clicou na guia Bistrô/Pufes/etc.,
    // tenta encaixar automaticamente quando só existir um grupo compatível.
    if (!regraSelecionada) {
        const regrasCompativeis = regras.filter(function(regra) {
            return regraConjuntoCasaComProduto(regra, produto);
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

    if (!regraConjuntoCasaComProduto(regraSelecionada, produto)) {
        Swal.fire('Produto fora da montagem', 'Este produto não pertence ao grupo selecionado para este conjunto. Nada foi alterado.', 'warning');
        return false;
    }

    const qtdConjunto = parseInt($rowConjunto.find('.quantidade_item').val(), 10) || 0;
    const qtdPorConjunto = parseFloat(regraSelecionada.quantidade_por_conjunto || 1) || 1;
    const necessario = Math.round(qtdConjunto * qtdPorConjunto);
    const lancado = somarItensDoGrupoConjunto($rowConjunto, regraSelecionada, null);
    const faltante = Math.max(0, necessario - lancado);

    if (faltante <= 0) {
        atualizarGuiasConjunto($rowConjunto);
        Swal.fire({
            title: 'Grupo completo',
            html: 'O grupo <strong>' + escapeHtml(regraSelecionada.nome_grupo || 'Grupo') + '</strong> já está completo.<br>Remova ou diminua algum item interno antes de adicionar outro.',
            icon: 'warning',
            confirmButtonText: 'Entendi'
        });
        return false;
    }

    produto.quantidade = faltante;
    produto.tipo_item_loc_vend = 'locacao';

    var $rowItem = adicionarLinhaItemTabela(produto, 'ITEM_CONJUNTO');

    if ($rowItem && $rowItem.length) {
        validarLimiteItemConjunto($rowItem, false);
        atualizarGuiasConjunto($rowConjunto);
        revalidarTodasAsLinhasDisponibilidade($rowItem);
    }

    $('#busca_produto').val('').focus();
    $('#sugestoes_produtos').empty().hide();
    return true;
}

function verificarEstoqueAntes(produto) {
    adicionarLinhaItemTabela(produto, 'PRODUTO');
    atualizarListaProdutosAposSelecao();
}

// ✅ FUNÇÃO MELHORADA: revalida todas as linhas para produtos que compartilham componentes.
function validarEstoqueQuantidade($row) {
    var quantidadeAtual = parseInt($row.find('.quantidade_item').val(), 10) || 0;

    if (quantidadeAtual < 0) {
        $row.find('.quantidade_item').val(0);
    }

    if (($row.data('tipo-linha') || '') === 'ITEM_CONJUNTO') {
        validarLimiteItemConjunto($row, true);
    }

    if (($row.data('tipo-linha') || '') === 'CONJUNTO') {
        const conjuntoIndex = parseInt($row.data('index'), 10) || 0;
        $('#tabela_itens_orcamento tbody tr.item-conjunto-filho-row').each(function() {
            if (parseInt($(this).data('conjunto-pai-index') || 0, 10) === conjuntoIndex) {
                validarLimiteItemConjunto($(this), false);
            }
        });
    }

    if (($row.data('tipo-linha') || '') === 'ITEM_CONJUNTO') {
        const info = obterRegraDoItemConjunto($row);
        if (info && info.$rowConjunto) {
            atualizarGuiasConjunto(info.$rowConjunto);
        }
    }

    if (($row.data('tipo-linha') || '') === 'CONJUNTO') {
        atualizarGuiasConjunto($row);
    }

    // Atualiza os indicadores compactos dos grupos do conjunto, quando aplicável.
    if (($row.data('tipo-linha') || '') === 'ITEM_CONJUNTO') {
        const $rowConjunto = obterLinhaConjuntoPorIndex($row.data('conjunto-pai-index'));
        atualizarGuiasConjunto($rowConjunto);
    }
    if (($row.data('tipo-linha') || '') === 'CONJUNTO') {
        atualizarGuiasConjunto($row);
    }

    // Revalida todas as linhas, porque produtos diferentes podem compartilhar componentes.
    // Ex.: Sofá Rosa Antigo e Sofá Cor A Definir usam a mesma estrutura e os mesmos colchões.
    revalidarTodasAsLinhasDisponibilidade($row);
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

// Revalida as linhas quando o período logístico muda.
// Se as datas estiverem incoerentes, não consulta estoque para não pintar tudo de vermelho/amarelo indevidamente.
$('#data_evento, #data_entrega, #data_devolucao_prevista, #hora_entrega, #turno_entrega, #hora_devolucao, #turno_devolucao').on('change keyup blur', function() {
    $('#tabela_itens_orcamento tbody tr.item-orcamento-row').removeData('alerta-indisponivel-chave');

    const ehCampoDataPrincipal = ['data_evento', 'data_entrega', 'data_devolucao_prevista'].includes(this.id);

    if (!validarSequenciaDatasLogisticaCreate(ehCampoDataPrincipal)) {
        marcarLinhasComoPeriodoInvalido();
        return;
    }

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

    function normalizarDataDigitada(valor) {
        valor = String(valor || '').trim();
        if (!valor) { return ''; }

        // Já está no formato brasileiro completo.
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(valor)) {
            return valor;
        }

        // Aceita 17/05/26.
        let m = valor.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/);
        if (m) {
            const anoAtual = new Date().getFullYear();
            const seculo = Math.floor(anoAtual / 100) * 100;
            return String(m[1]).padStart(2, '0') + '/' + String(m[2]).padStart(2, '0') + '/' + (seculo + parseInt(m[3], 10));
        }

        const digitos = valor.replace(/\D/g, '');
        if (digitos.length === 4) {
            // 1705 => 17/05/ano atual
            return digitos.substring(0, 2) + '/' + digitos.substring(2, 4) + '/' + new Date().getFullYear();
        }
        if (digitos.length === 6) {
            // 170526 => 17/05/2026
            const anoAtual = new Date().getFullYear();
            const seculo = Math.floor(anoAtual / 100) * 100;
            return digitos.substring(0, 2) + '/' + digitos.substring(2, 4) + '/' + (seculo + parseInt(digitos.substring(4, 6), 10));
        }
        if (digitos.length === 8) {
            // 17052026 => 17/05/2026
            return digitos.substring(0, 2) + '/' + digitos.substring(2, 4) + '/' + digitos.substring(4, 8);
        }

        return valor;
    }

    function aplicarNormalizacaoData($input) {
        const antes = $input.val();
        const depois = normalizarDataDigitada(antes);
        if (depois && depois !== antes) {
            $input.val(depois);
            if (typeof $.fn.datepicker === 'function') {
                $input.datepicker('update', depois);
            }
            $input.trigger('change');
        }
    }

    $('.datepicker').on('focus click', function() {
        $(this).removeAttr('readonly');
    });

    if (typeof $.fn.datepicker === 'function') {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            language: 'pt-BR',
            autoclose: true,
            todayHighlight: true,
            orientation: "bottom auto",
            container: 'body',
            zIndexOffset: 999990
        });
    }

    $('.datepicker').on('blur', function() {
        aplicarNormalizacaoData($(this));
        $(this).attr('readonly', 'readonly');
    });

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
        displayEl.text('').removeData('dia-semana-base').removeClass('text-danger font-weight-bold text-success text-warning');
        if (dataStr) {
            var partes = dataStr.split('/');
            if (partes.length === 3) {
                var dataObj = new Date(partes[2], partes[1] - 1, partes[0]);
                if (!isNaN(dataObj.valueOf())) {
                    var diaSemana = diasDaSemana[dataObj.getDay()];
                    displayEl.data('dia-semana-base', diaSemana);
                    displayEl.text(diaSemana).addClass('font-weight-bold');
                    if (dataObj.getDay() === 0 || dataObj.getDay() === 6) { displayEl.addClass('text-danger'); } 
                    else { displayEl.addClass('text-success'); }
                    return;
                }
            }
        }
    }
    $('#data_evento').on('change dp.change', function() {
    exibirDiaSemana(this, '#dia_semana_evento');
    validarSequenciaDatasLogisticaCreate(false);
}).trigger('change');

$('#data_entrega').on('change dp.change', function() {
    exibirDiaSemana(this, '#dia_semana_entrega');
    validarSequenciaDatasLogisticaCreate(false);
}).trigger('change');

$('#data_devolucao_prevista').on('change dp.change', function() {
    exibirDiaSemana(this, '#dia_semana_devolucao');
    validarSequenciaDatasLogisticaCreate(false);
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

    function validarSequenciaDatasLogisticaCreate(mostrarAlerta = true) {
        const dataEventoStr = $('#data_evento').val();
        const dataEntregaStr = $('#data_entrega').val();
        const dataDevolucaoStr = $('#data_devolucao_prevista').val();

        const dataEvento = converterDataBRParaDate(dataEventoStr);
        const dataEntrega = converterDataBRParaDate(dataEntregaStr);
        const dataDevolucao = converterDataBRParaDate(dataDevolucaoStr);

        limparFeedbackDatasLogistica();

        // Orçamento pode ficar sem datas ou com datas parciais.
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

    function marcarLinhasComoPeriodoInvalido() {
        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
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
        let produtoErro = null;

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
                produtoErro = nomeProduto || 'Produto sem nome';
                return false;
            }
        });

        if (produtoErro) {
            Swal.fire({
                title: 'Quantidade obrigatória',
                text: 'Há produto com quantidade zero no orçamento: ' + produtoErro + '. Ajuste a quantidade ou remova a linha antes de salvar.',
                icon: 'warning',
                confirmButtonText: 'Entendi'
            });
            return false;
        }

        return true;
    }

    $('#formNovoOrcamento').on('submit', function(e) {
        if (!validarSequenciaDatasLogisticaCreate(true)) {
            e.preventDefault();
            return false;
        }

        if (!validarItensQuantidadeZeroCreate()) {
            e.preventDefault();
            return false;
        }

        if (!validarTodosConjuntosAntesSalvar(true)) {
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
    validarSequenciaDatasLogisticaCreate(false);
    calcularTotaisOrcamento();
    
    function atualizarOrdemDosItens() {
        let ordemReal = 1;
        $('#tabela_itens_orcamento tbody tr.item-orcamento-row').each(function() {
            // Não alterar data-index: ele é o vínculo temporário entre conjunto pai, guias e filhos.
            // Aqui atualizamos apenas a ordem persistida no banco.
            $(this).find('input[name="ordem[]"]').val(ordemReal);
            ordemReal++;
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

$inline_js_setup = ($inline_js_setup ?? '') . "
window.TEXTOS_PADRAO_ORCAMENTO = " . json_encode([
    'observacoes' => $textoPadraoObservacoes,
    'condicoes' => $textoPadraoCondicoes,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ";";

include_once __DIR__ . '/../includes/footer.php';
?>