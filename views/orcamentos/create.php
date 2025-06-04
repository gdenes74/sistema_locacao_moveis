<?php
$page_title = "Novo Orçamento";
// Define $extra_css e $custom_js aqui se necessário para este arquivo específico
// Ex: $extra_css = [BASE_URL . '/assets/css/orcamentos_create.css'];

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/NumeracaoSequencial.php';
require_once __DIR__ . '/../../models/Orcamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$clienteModel = new Cliente($db);
$produtoModel = new Produto($db);
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

// --- Bloco AJAX para buscar produtos ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'buscar_produtos') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        if (empty($termo)) {
            echo json_encode([]);
            exit;
        }
        // Assumindo que sua tabela produtos tem 'nome_produto' e 'preco_locacao'
        $sql = "SELECT id, codigo, nome_produto, descricao_detalhada, preco_locacao, quantidade_total 
                FROM produtos 
                WHERE nome_produto LIKE :termo_nome OR codigo LIKE :termo_codigo";
        $stmt = $db->prepare($sql);
        $likeTerm = "%" . $termo . "%";
        $stmt->bindParam(':termo_nome', $likeTerm, PDO::PARAM_STR);
        $stmt->bindParam(':termo_codigo', $likeTerm, PDO::PARAM_STR);
        $stmt->execute();
        $produtos_ajax = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($produtos_ajax);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Erro AJAX buscar_produtos: " . $e->getMessage());
        echo json_encode(['error' => 'Erro no banco de dados ao buscar produtos.']);
        exit;
    }
}


// --- Lógica de submissão do formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Descomente para depurar os dados recebidos:
    // error_log("[DEBUG CREATE ORCAMENTO POST DATA]: " . print_r($_POST, true));
    try {
        $proximoNumeroGerado = $numeracaoModel->gerarProximoNumero('orcamento');
        if ($proximoNumeroGerado === false || $proximoNumeroGerado === null) {
            throw new Exception("Falha crítica ao gerar o número sequencial do orçamento.");
        }
        $orcamentoModel->numero = $proximoNumeroGerado; // O modelo Orcamento.php criará o $codigo internamente

        if (empty($_POST['cliente_id'])) {
            throw new Exception("Cliente é obrigatório.");
        }
        $orcamentoModel->cliente_id = $_POST['cliente_id'];
        
        // Data Orçamento
        $data_orcamento_input = $_POST['data_orcamento'] ?? date('d/m/Y');
        $data_orcamento_dt = DateTime::createFromFormat('d/m/Y', $data_orcamento_input);
        if (!$data_orcamento_dt) { // Se falhar, tenta Y-m-d (caso o datepicker envie assim por algum motivo) ou usa hoje
            $data_orcamento_dt = DateTime::createFromFormat('Y-m-d', $data_orcamento_input) ?: new DateTime();
        }
        $orcamentoModel->data_orcamento = $data_orcamento_dt->format('Y-m-d');

        // Data Validade
        // O campo 'data_validade_calculada_hidden' é o que deve ser usado, pois o JS o preenche em YYYY-MM-DD
        if (isset($_POST['data_validade_calculada_hidden']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data_validade_calculada_hidden'])) {
            $orcamentoModel->data_validade = $_POST['data_validade_calculada_hidden'];
        } else { // Fallback se o campo hidden não vier ou for inválido
            $validade_dias = isset($_POST['validade_dias']) ? (int)$_POST['validade_dias'] : 7; // Default 7 dias
            $data_validade_dt_calc = clone $data_orcamento_dt;
            $data_validade_dt_calc->modify("+{$validade_dias} days");
            $orcamentoModel->data_validade = $data_validade_dt_calc->format('Y-m-d');
        }
        
        // Data do Evento
        $data_evento_dt = !empty($_POST['data_evento']) ? DateTime::createFromFormat('d/m/Y', $_POST['data_evento']) : null;
        $orcamentoModel->data_evento = $data_evento_dt ? $data_evento_dt->format('Y-m-d') : null;
        $orcamentoModel->hora_evento = !empty($_POST['hora_evento']) ? $_POST['hora_evento'] : null;

        // Data da Entrega
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
        $orcamentoModel->status = $_POST['status_orcamento'] ?? 'pendente'; // Usar 'status_orcamento' do form
        
        // Valores e Taxas (Função str_replace para remover 'R$', '.' e trocar ',' por '.')
        $fnConverterMoeda = function ($valorStr) {
            if (empty($valorStr)) return 0.0;
            $valor = str_replace('R$', '', $valorStr);
            $valor = str_replace('.', '', $valor); // Remove separador de milhar
            $valor = str_replace(',', '.', $valor); // Troca vírgula decimal por ponto
            return (float)$valor;
        };

        $orcamentoModel->desconto = $fnConverterMoeda($_POST['desconto_total'] ?? '0,00');
        $orcamentoModel->taxa_domingo_feriado = $fnConverterMoeda($_POST['taxa_domingo_feriado'] ?? '0,00');
        $orcamentoModel->taxa_madrugada = $fnConverterMoeda($_POST['taxa_madrugada'] ?? '0,00');
        $orcamentoModel->taxa_horario_especial = $fnConverterMoeda($_POST['taxa_horario_especial'] ?? '0,00');
        $orcamentoModel->taxa_hora_marcada = $fnConverterMoeda($_POST['taxa_hora_marcada'] ?? '0,00');
        $orcamentoModel->frete_terreo = $fnConverterMoeda($_POST['frete_terreo'] ?? '0,00');
        $orcamentoModel->frete_elevador = $_POST['frete_elevador'] ?? ''; // Campo textual
        $orcamentoModel->frete_escadas = $_POST['frete_escadas'] ?? '';   // Campo textual

        $orcamentoModel->ajuste_manual = isset($_POST['ajuste_manual']) ? 1 : 0;
        $orcamentoModel->motivo_ajuste = $_POST['motivo_ajuste'] ?? '';
        $orcamentoModel->observacoes = $_POST['observacoes'] ?? '';
        $orcamentoModel->condicoes_pagamento = $_POST['condicoes_pagamento'] ?? '';
        $orcamentoModel->usuario_id = $_SESSION['usuario_id'] ?? 1; // Fallback para usuário 1 se não houver sessão

        // O método create() no modelo Orcamento já foi atualizado para incluir data_entrega e hora_entrega
        $orcamentoIdSalvo = $orcamentoModel->create(); // Este método agora retorna o ID ou false

        if ($orcamentoIdSalvo !== false && $orcamentoIdSalvo > 0) {
            // Salvar Itens
            $itens = [];
            if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
                foreach ($_POST['produto_id'] as $index => $produto_id) {
                    if (!empty($produto_id) || !empty(trim($_POST['nome_produto_display'][$index]))) { // Permite item manual sem ID de produto
                        $quantidade = isset($_POST['quantidade'][$index]) ? (int)$_POST['quantidade'][$index] : 1;
                        if ($quantidade <= 0) $quantidade = 1; // Garante quantidade mínima

                        $preco_unitario = $fnConverterMoeda($_POST['valor_unitario'][$index] ?? '0,00');
                        $desconto_item = $fnConverterMoeda($_POST['desconto_item'][$index] ?? '0,00');
                        $preco_final = ($quantidade * $preco_unitario) - $desconto_item;

                        $itens[] = [
                            'produto_id' => !empty($produto_id) ? $produto_id : null, // Permite nulo para item manual
                            'nome_produto_manual' => empty($produto_id) ? trim($_POST['nome_produto_display'][$index]) : null, // Salva nome manual se não houver ID
                            'quantidade' => $quantidade,
                            'tipo' => $_POST['tipo_item'][$index] ?? 'locacao',
                            'preco_unitario' => $preco_unitario,
                            'desconto' => $desconto_item,
                            'preco_final' => $preco_final,
                            'ajuste_manual' => false, // Ajuste manual é do orçamento total, não por item aqui
                            'motivo_ajuste' => '',    // Idem
                            'observacoes' => $_POST['observacoes_item'][$index] ?? ''
                        ];
                    }
                }
            }

            if (!empty($itens)) {
                if (!$orcamentoModel->salvarItens($orcamentoIdSalvo, $itens)) { // salvarItens precisa lidar com nome_produto_manual
                    $_SESSION['warning_message'] = "Orçamento principal salvo (Nº {$orcamentoModel->numero}), mas houve um erro ao salvar os itens. Verifique e edite o orçamento se necessário.";
                    error_log("[ERRO CREATE ORCAMENTO] Falha ao salvar itens para o orçamento ID: " . $orcamentoIdSalvo);
                }
            }
            
            // Recalcular valores FINAIS após salvar itens (o método recalcularValores em Orcamento.php usará os valores de taxa/frete já atribuídos ao $orcamentoModel)
            $orcamentoModel->id = $orcamentoIdSalvo; // Garante que o ID está no objeto para recalcularValores
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
    // Se chegou aqui, houve erro, recarrega a página (os dados do POST não serão mantidos facilmente sem JS complexo ou framework)
    // header("Location: create.php"); // Evita reenvio de formulário em refresh, mas perde dados digitados.
    // Melhor deixar exibir a mensagem de erro na própria página.
}

include_once __DIR__ . '/../includes/header.php'; // Inclui o header.php
?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/views/dashboard/index.php">Início</a></li>
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
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['warning_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['warning_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['warning_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): // Adicionado para exibir msg de sucesso se o redirect falhar por algum motivo ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success_message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form id="formNovoOrcamento" action="create.php" method="POST" novalidate>
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Dados do Orçamento</h3>
                        <div class="card-tools">
                             <span class="badge badge-info">Nº Orçamento: <?= htmlspecialchars($numeroFormatado) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Dados Iniciais do Orçamento -->
                        <div class="row mb-3">
                            <div class="col-md-7">
                                <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control select2" id="cliente_id" name="cliente_id" required>
                                        <option value="">Selecione ou Busque um Cliente</option>
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
                                    <input type="text" class="form-control datepicker" id="data_orcamento" name="data_orcamento" value="<?= date('d/m/Y') ?>" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="validade_dias" class="form-label">Validade (dias) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="validade_dias" name="validade_dias" value="7" min="1" required>
                                <input type="hidden" id="data_validade_calculada_hidden" name="data_validade_calculada_hidden">
                                <small id="data_validade_display" class="form-text text-muted"></small>
                            </div>
                        </div>
                        <hr>

                        <!-- Dados do Evento e Entrega (NOVA ORDEM) -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h5><i class="fas fa-calendar-check mr-2"></i>Detalhes do Evento e Logística</h5>
                            </div>
                            <!-- Data do Evento (Primeiro) -->
                            <div class="col-md-3">
                                <label for="data_evento" class="form-label">Data do Evento</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_evento" name="data_evento" placeholder="DD/MM/AAAA">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    </div>
                                </div>
                                <small id="dia_semana_evento" class="form-text text-muted"></small>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_evento" class="form-label">Hora do Evento</label>
                                <input type="time" class="form-control" id="hora_evento" name="hora_evento">
                            </div>
                            <div class="col-md-7">
                                <label for="local_evento" class="form-label">Local do Evento/Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="local_evento" name="local_evento" placeholder="Ex: Salão de Festas Condomínio XYZ">
                                    <div class="input-group-append">
                                        <button type="button" id="btnUsarEnderecoCliente" class="btn btn-sm btn-outline-info" title="Usar endereço do cliente selecionado" style="display: none;"><i class="fas fa-map-marker-alt"></i> Usar End. Cliente</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data da Entrega (Depois da Data do Evento) -->
                            <div class="col-md-3 mt-md-3">
                                <label for="data_entrega" class="form-label">Data da Entrega</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_entrega" name="data_entrega" placeholder="DD/MM/AAAA">
                                     <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    </div>
                                </div>
                                <small id="dia_semana_entrega" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2 mt-md-3">
                                <label for="hora_entrega" class="form-label">Hora da Entrega</label>
                                <input type="time" class="form-control" id="hora_entrega" name="hora_entrega">
                            </div>
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

                        <!-- Dados da Devolução -->
                        <div class="row mb-3">
                             <div class="col-12">
                                <h5><i class="fas fa-undo-alt mr-2"></i>Detalhes da Devolução/Coleta</h5>
                            </div>
                            <div class="col-md-3">
                                <label for="data_devolucao_prevista" class="form-label">Data Devolução (Prev.)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control datepicker" id="data_devolucao_prevista" name="data_devolucao_prevista" placeholder="DD/MM/AAAA">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    </div>
                                </div>
                                <small id="dia_semana_devolucao" class="form-text text-muted font-weight-bold"></small>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_devolucao" class="form-label">Hora Devolução</label>
                                <input type="time" class="form-control" id="hora_devolucao" name="hora_devolucao">
                            </div>
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
                </div> <!-- Fim Card Dados do Orçamento -->

                <!-- Itens do Orçamento -->
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list-ul mr-2"></i>Itens do Orçamento</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-12">
                                <label for="busca_produto" class="form-label">Buscar Produto por Nome ou Código:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="busca_produto" placeholder="Digite para buscar...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="btnLimparBuscaProduto" title="Limpar busca"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <div id="sugestoes_produtos" class="list-group mt-1" style="position: absolute; z-index: 1000; width: calc(100% - 30px); max-height: 200px; overflow-y: auto;">
                                    <!-- Sugestões de produtos aparecerão aqui -->
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
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
                                    <!-- Exemplo de como seria uma linha (para referência, não deixar no HTML final)
                                    <tr class="item-orcamento-row">
                                        <td>
                                            <input type="text" name="nome_produto_display[]" class="form-control form-control-sm nome_produto_display" value="Produto Exemplo" placeholder="Nome do Produto/Serviço">
                                            <input type="hidden" name="produto_id[]" class="produto_id" value="1">
                                            <input type="hidden" name="tipo_item[]" class="tipo_item" value="locacao">
                                            <small class="form-text text-muted observacoes_item_label" style="display:none;">Obs. Item:</small>
                                            <input type="text" name="observacoes_item[]" class="form-control form-control-sm observacoes_item_input mt-1" style="display:none;" placeholder="Observação específica do item">
                                        </td>
                                        <td><input type="number" name="quantidade[]" class="form-control form-control-sm quantidade_item" value="1" min="1" style="width: 70px;"></td>
                                        <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right money-item" value="100,00" style="width: 100px;"></td>
                                        <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right money-item" value="0,00" style="width: 100px;"></td>
                                        <td class="subtotal_item_display text-right font-weight-bold">100,00</td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Adicionar Observação ao Item"><i class="fas fa-comment-dots"></i></button>
                                            <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Item"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                     -->
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
                    </div>
                </div> <!-- Fim Card Itens do Orçamento -->

                <!-- Valores, Taxas e Condições -->
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calculator mr-2"></i>Totais, Taxas e Condições</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Coluna da Esquerda: Observações e Condições -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="observacoes">Observações Gerais</label>
                                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4" placeholder="Ex: Cliente solicitou montagem especial..."><?= htmlspecialchars($textoPadraoObservacoes) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="condicoes_pagamento">Condições de Pagamento</label>
                                    <textarea class="form-control" id="condicoes_pagamento" name="condicoes_pagamento" rows="4" placeholder="Ex: 50% na aprovação, 50% na entrega. PIX CNPJ ..."><?= htmlspecialchars($textoPadraoCondicoes) ?></textarea>
                                </div>
                                 <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="ajuste_manual" name="ajuste_manual">
                                        <label class="custom-control-label" for="ajuste_manual">Ajustar Valor Final Manualmente?</label>
                                    </div>
                                </div>
                                <div class="form-group" id="campo_motivo_ajuste" style="display: none;">
                                    <label for="motivo_ajuste">Motivo do Ajuste Manual</label>
                                    <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste" placeholder="Ex: Desconto especial concedido">
                                </div>
                            </div>

                            <!-- Coluna da Direita: Taxas, Fretes e Totais -->
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label for="desconto_total" class="col-sm-6 col-form-label">Desconto Total (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control money text-right" id="desconto_total" name="desconto_total" value="0,00">
                                    </div>
                                </div>
                                <hr>
                                <h5 class="text-muted">Taxas Adicionais (Valores Informativos/Editáveis)</h5>
                                <div class="form-group row">
                                    <label for="taxa_domingo_feriado" class="col-sm-6 col-form-label">Taxa Domingo/Feriado (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control money text-right" id="taxa_domingo_feriado" name="taxa_domingo_feriado" value="<?= number_format($valorPadraoTaxaDomingo, 2, ',', '.') ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="taxa_madrugada" class="col-sm-6 col-form-label">Taxa Madrugada (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control money text-right" id="taxa_madrugada" name="taxa_madrugada" value="<?= number_format($valorPadraoTaxaMadrugada, 2, ',', '.') ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="taxa_horario_especial" class="col-sm-6 col-form-label">Taxa Horário Especial (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control money text-right" id="taxa_horario_especial" name="taxa_horario_especial" value="<?= number_format($valorPadraoTaxaHorarioEspecial, 2, ',', '.') ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="taxa_hora_marcada" class="col-sm-6 col-form-label">Taxa Hora Marcada (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control money text-right" id="taxa_hora_marcada" name="taxa_hora_marcada" value="<?= number_format($valorPadraoTaxaHoraMarcada, 2, ',', '.') ?>">
                                    </div>
                                </div>
                                <hr>
                                <h5 class="text-muted">Frete</h5>
                                <div class="form-group row">
                                    <label for="frete_terreo" class="col-sm-6 col-form-label">Frete Térreo (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control money text-right" id="frete_terreo" name="frete_terreo" value="<?= number_format($valorPadraoFreteTerreo, 2, ',', '.') ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="frete_elevador" class="col-sm-6 col-form-label">Frete Elevador:</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control" id="frete_elevador" name="frete_elevador" placeholder="Ex: R$ 50,00 ou A Confirmar">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="frete_escadas" class="col-sm-6 col-form-label">Frete Escadas:</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control" id="frete_escadas" name="frete_escadas" placeholder="Ex: R$ 100,00 por lance ou A Confirmar">
                                    </div>
                                </div>
                                <hr>
                                <div class="form-group row mt-3 bg-light p-2 rounded">
                                    <label class="col-sm-6 col-form-label text-lg text-primary">VALOR FINAL (R$):</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control form-control-lg text-right font-weight-bold text-primary money-display" id="valor_final_display" readonly value="R$ 0,00" style="background-color: #e9ecef; border: none;">
                                        <input type="hidden" id="valor_final_hidden" name="valor_final" value="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="index.php" class="btn btn-secondary mr-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save mr-1"></i> Salvar Orçamento
                        </button>
                    </div>
                </div> <!-- Fim Card Valores e Taxas -->
            </form>
        </div>
    </section>
</div> <!-- Fim Content Wrapper -->

<!-- Modal Novo Cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1" role="dialog" aria-labelledby="modalNovoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoClienteLabel">Novo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formNovoClienteModal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_cliente_nome">Nome Completo / Razão Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_cliente_nome" name="nome" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_cliente_cpf_cnpj">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="modal_cliente_cpf_cnpj" name="cpf_cnpj">
                            </div>
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_cliente_email">E-mail</label>
                                <input type="email" class="form-control" id="modal_cliente_email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                             <div class="form-group">
                                <label for="modal_cliente_telefone">Telefone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control telefone" id="modal_cliente_telefone" name="telefone" required>
                            </div>
                        </div>
                    </div>
                     <div class="form-group">
                        <label for="modal_cliente_endereco">Endereço (Rua, Nº, Bairro)</label>
                        <input type="text" class="form-control" id="modal_cliente_endereco" name="endereco">
                    </div>
                     <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="modal_cliente_cidade">Cidade</label>
                                <input type="text" class="form-control" id="modal_cliente_cidade" name="cidade" value="Porto Alegre">
                            </div>
                        </div>
                         <div class="col-md-4">
                            <div class="form-group">
                                <label for="modal_cliente_cep">CEP</label>
                                <input type="text" class="form-control cep" id="modal_cliente_cep" name="cep">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_cliente_observacoes">Observações do Cliente</label>
                        <textarea class="form-control" id="modal_cliente_observacoes" name="observacoes" rows="2"></textarea>
                    </div>
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
// Definindo o JavaScript customizado para esta página
$custom_js = <<<'JS'
$(document).ready(function() {
    // Inicializar Select2 para busca de clientes (já deve estar no footer, mas pode reconfigurar se precisar)
    if (typeof $.fn.select2 === 'function') {
        $('#cliente_id').select2({
            placeholder: 'Selecione ou Busque um Cliente',
            allowClear: true,
            width: '100%',
            theme: 'bootstrap4', // Garante tema do AdminLTE
            language: "pt-BR", // Para mensagens do Select2 em português
            ajax: {
                url: 'create.php?ajax=buscar_clientes',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { termo: params.term };
                },
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

    // Inicializar Datepickers (Bootstrap Datepicker)
    // Certifique-se de que o CSS e JS do Bootstrap Datepicker e seu locale pt-BR estão carregados (no header e footer)
    if (typeof $.fn.datepicker === 'function' && typeof $.fn.datepicker.dates !== 'undefined' && $.fn.datepicker.dates['pt-BR']) {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            language: 'pt-BR',
            autoclose: true,
            todayHighlight: true,
            showOnFocus: true, // Abre ao focar
            orientation: "bottom auto" // Tenta ajustar a posição
        }).on('show', function(e){
            // Correção para z-index em modais se o datepicker for usado dentro de um
             if ($('.modal.show').length > 0) {
                $(this).data('datepicker').picker.css('z-index', parseInt($('.modal.show').css('z-index')) + 10);
            }
        });
    } else {
        console.error("Bootstrap Datepicker ou locale pt-BR não carregado. Verifique as inclusões no footer.php.");
        // Fallback para input type="date" se o plugin falhar (menos amigável)
        // $('.datepicker').attr('type', 'date');
    }


    function calcularDataValidade() {
        var dataOrcamentoStr = $('#data_orcamento').val();
        var validadeDias = parseInt($('#validade_dias').val());

        if (dataOrcamentoStr && validadeDias > 0) {
            // Tenta criar a data a partir de DD/MM/YYYY
            var partesData = dataOrcamentoStr.split('/');
            if (partesData.length === 3) {
                // Ano, Mês (0-11), Dia
                var dataOrcamento = new Date(partesData[2], partesData[1] - 1, partesData[0]);
                
                if (!isNaN(dataOrcamento.valueOf())) { // Verifica se a data é válida
                    dataOrcamento.setDate(dataOrcamento.getDate() + validadeDias);
                    
                    var dia = String(dataOrcamento.getDate()).padStart(2, '0');
                    var mes = String(dataOrcamento.getMonth() + 1).padStart(2, '0');
                    var ano = dataOrcamento.getFullYear();
                    var dataValidadeFormatadaUser = dia + '/' + mes + '/' + ano;
                    var dataValidadeFormatadaHidden = ano + '-' + mes + '-' + dia;

                    $('#data_validade_display').text('Validade até: ' + dataValidadeFormatadaUser);
                    $('#data_validade_calculada_hidden').val(dataValidadeFormatadaHidden);
                    return; // Sucesso
                }
            }
        }
        $('#data_validade_display').text('Data de orçamento ou validade inválida.');
        $('#data_validade_calculada_hidden').val('');
    }

    $('#data_orcamento, #validade_dias').on('change keyup blur', calcularDataValidade);
    calcularDataValidade(); // Calcula na carga inicial


    // Função para exibir dia da semana
    const diasDaSemana = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
    function exibirDiaSemana(inputId, displayId) {
        var dataStr = $(inputId).val();
        var displayEl = $(displayId);
        displayEl.text('').removeClass('text-danger font-weight-bold text-success');

        if (dataStr) {
            var partes = dataStr.split('/');
            if (partes.length === 3) {
                var dataObj = new Date(partes[2], partes[1] - 1, partes[0]); // Ano, Mês (0-11), Dia
                if (!isNaN(dataObj.valueOf())) { // Verifica se a data é válida
                    var diaSemana = diasDaSemana[dataObj.getDay()];
                    displayEl.text(diaSemana).addClass('font-weight-bold');
                    if (dataObj.getDay() === 0 || dataObj.getDay() === 6) { // Domingo ou Sábado
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
    
    // Aplicar para os campos de data
    $('#data_evento').on('change dp.change keyup blur', function() { exibirDiaSemana(this, '#dia_semana_evento'); }).trigger('change');
    $('#data_entrega').on('change dp.change keyup blur', function() { exibirDiaSemana(this, '#dia_semana_entrega'); }).trigger('change');
    $('#data_devolucao_prevista').on('change dp.change keyup blur', function() { exibirDiaSemana(this, '#dia_semana_devolucao'); }).trigger('change');


    // Usar endereço do cliente no local do evento
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

// Máscaras de dinheiro (usando jQuery Mask Plugin)
function formatCurrency(value) {
    let val = parseFloat(value);
    if (isNaN(val)) return "R$ 0,00";
    return "R$ " + val.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
}

function unformatCurrency(valueStr) {
    if (typeof valueStr !== 'string') return 0.0;
    return parseFloat(valueStr.replace(/R\$\s?/, '').replace(/\./g, '').replace(',', '.')) || 0.0;
}

// Adicionar Itens Dinamicamente
var itemIndex = 0;
function adicionarLinhaItem(produto = null) {
    itemIndex++;
    var nomeProduto = produto ? produto.nome_produto : '';
    var idProduto = produto ? produto.id : '';
    var precoUnitario = produto ? (parseFloat(produto.preco_locacao) || 0) : 0;
    var quantidade = 1;
    var descontoItem = 0;
    var subtotalItem = (quantidade * precoUnitario) - descontoItem;

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
            <td><input type="text" name="valor_unitario[]" class="form-control form-control-sm valor_unitario_item text-right" value="${precoUnitario.toFixed(2).replace('.', ',')}" style="width: 100px;"></td>
            <td><input type="text" name="desconto_item[]" class="form-control form-control-sm desconto_item text-right" value="${descontoItem.toFixed(2).replace('.', ',')}" style="width: 100px;"></td>
            <td class="subtotal_item_display text-right font-weight-bold">${subtotalItem.toFixed(2).replace('.', ',')}</td>
            <td>
                <button type="button" class="btn btn-xs btn-info btn_obs_item" title="Adicionar Observação ao Item"><i class="fas fa-comment-dots"></i></button>
                <button type="button" class="btn btn-xs btn-danger btn_remover_item" title="Remover Item"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
    $('#tabela_itens_orcamento tbody').append(newRow);
    calcularTotaisOrcamento();
}

$('#btn_adicionar_item_manual').click(function() {
    adicionarLinhaItem();
});

// Remover Item
$('#tabela_itens_orcamento').on('click', '.btn_remover_item', function() {
    $(this).closest('tr').remove();
    calcularTotaisOrcamento();
});

// Toggle Observação do Item
$('#tabela_itens_orcamento').on('click', '.btn_obs_item', function() {
    var $row = $(this).closest('tr');
    $row.find('.observacoes_item_label, .observacoes_item_input').toggle();
});

// Busca de produtos com Autocomplete
$('#busca_produto').on('keyup', function() {
    var termo = $(this).val();
    if (termo.length >= 2) {
        $.ajax({
            url: 'create.php?ajax=buscar_produtos',
            type: 'GET',
            dataType: 'json',
            data: { termo: termo },
            success: function(data) {
                $('#sugestoes_produtos').empty().show();
                if (data.length > 0) {
                    $.each(data, function(i, produto) {
                        let preco = parseFloat(produto.preco_locacao) || 0;
                        $('#sugestoes_produtos').append(
                            `<a href="#" class="list-group-item list-group-item-action item-sugestao-produto" 
                               data-id="${produto.id}" 
                               data-nome="${produto.nome_produto}" 
                               data-codigo="${produto.codigo}" 
                               data-preco="${preco}">
                                <strong>${produto.nome_produto}</strong> (Cód: ${produto.codigo}) - R$ ${preco.toFixed(2).replace('.', ',')}
                            </a>`
                        );
                    });
                } else {
                    $('#sugestoes_produtos').append('<span class="list-group-item">Nenhum produto encontrado.</span>');
                }
            },
            error: function() {
                $('#sugestoes_produtos').empty().show().append('<span class="list-group-item text-danger">Erro ao buscar produtos.</span>');
            }
        });
    } else {
        $('#sugestoes_produtos').empty().hide();
    }
});

// Limpar busca de produto
$('#btnLimparBuscaProduto').on('click', function() {
    $('#busca_produto').val('');
    $('#sugestoes_produtos').empty().hide();
});

// Clicar em uma sugestão de produto
$('#sugestoes_produtos').on('click', '.item-sugestao-produto', function(e) {
    e.preventDefault();
    var produtoSelecionado = {
        id: $(this).data('id'),
        nome_produto: $(this).data('nome'),
        codigo: $(this).data('codigo'),
        preco_locacao: $(this).data('preco')
    };
    adicionarLinhaItem(produtoSelecionado);
    $('#busca_produto').val('');
    $('#sugestoes_produtos').empty().hide();
});

// Esconder sugestões se clicar fora
$(document).on('click', function(e) {
    if (!$(e.target).closest('#busca_produto, #sugestoes_produtos').length) {
        $('#sugestoes_produtos').empty().hide();
    }
});

function calcularSubtotalItem($row) {
    var quantidade = parseFloat($row.find('.quantidade_item').val()) || 0;
    var valorUnitario = unformatCurrency($row.find('.valor_unitario_item').val());
    var descontoItem = unformatCurrency($row.find('.desconto_item').val());
    var subtotal = (quantidade * valorUnitario) - descontoItem;
    $row.find('.subtotal_item_display').text(subtotal.toFixed(2).replace('.', ','));
    return subtotal;
}

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

    var valorFinal = subtotalGeralItens - descontoTotal + 
                     taxaDomingo + taxaMadrugada + taxaHorarioEspecial + taxaHoraMarcada +
                     freteTerreo;
    
    $('#valor_final_display').val(formatCurrency(valorFinal));
    $('#valor_final_hidden').val(valorFinal.toFixed(2));
}

$('#tabela_itens_orcamento').on('change keyup', '.quantidade_item, .valor_unitario_item, .desconto_item', function() {
    var $row = $(this).closest('tr');
    calcularSubtotalItem($row);
    calcularTotaisOrcamento();
});

$('#desconto_total, #taxa_domingo_feriado, #taxa_madrugada, #taxa_horario_especial, #taxa_hora_marcada, #frete_terreo').on('change keyup', function() {
    calcularTotaisOrcamento();
});

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
        url: '../clientes/store_ajax.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success && response.cliente) {
                var newOption = new Option(response.cliente.nome + (response.cliente.cpf_cnpj ? ' (' + response.cliente.cpf_cnpj + ')' : ''), response.cliente.id, true, true);
                $('#cliente_id').append(newOption).trigger('change');
                $('#cliente_id').trigger({
                    type: 'select2:select',
                    params: { data: { full_data: response.cliente } }
                });
                $('#modalNovoCliente').modal('hide');
                $('#formNovoClienteModal')[0].reset();
                mostrarMensagem('success', 'Cliente salvo e selecionado!');
            } else {
                mostrarMensagem('error', 'Erro ao salvar cliente: ' + (response.message || 'Erro desconhecido.'));
            }
        },
        error: function(xhr) {
            mostrarMensagem('error', 'Erro de comunicação ao salvar cliente.');
            console.error("Erro AJAX salvar cliente:", xhr.responseText);
        }
    });
});

$('#formNovoOrcamento').on('submit', function(event) {
    if (!this.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
    }
    $(this).addClass('was-validated');

    if (!$('#cliente_id').val()) {
        alert('Por favor, selecione um cliente.');
        $('#cliente_id').select2('open');
        event.preventDefault();
        event.stopPropagation();
        return false;
    }

    if ($('#tabela_itens_orcamento tbody tr.item-orcamento-row').length === 0) {
        alert('Adicione pelo menos um item ao orçamento.');
        event.preventDefault();
        event.stopPropagation();
        return false;
    }
});

});
JS;
include_once __DIR__ . '/../includes/footer.php';
?>