<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Funções Auxiliares (mantidas iguais ao orçamento) ---
if (!function_exists('formatarDataDiaSemana')) {
    function formatarDataDiaSemana($dataModel)
    {
        if (empty($dataModel) || $dataModel === '0000-00-00' || $dataModel === '0000-00-00 00:00:00')
            return '-';
        try {
            $timestamp = strtotime($dataModel);
            if ($timestamp === false)
                return '-';
            $dias = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
            return date('d.m.y', $timestamp) . ' ' . $dias[date('w', $timestamp)];
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('formatarTurnoHora')) {
    function formatarTurnoHora($turno, $hora)
    {
        $retorno = htmlspecialchars(trim($turno ?? ''));

        if (!empty($hora) && $hora !== '00:00:00') {
            try {
                $horaFormatada = date('H\H', strtotime($hora));
                $retorno .= ($retorno ? ' APROX. ' : 'APROX. ') . htmlspecialchars($horaFormatada);
            } catch (Exception $e) {
                // Se a hora for inválida, apenas ignora
            }
        }
        
        return trim($retorno) ?: '-';
    }
}

if (!function_exists('formatarValor')) {
    function formatarValor($valor, $mostrarZeroComoString = false)
    {
        if (is_numeric($valor)) {
            if ($valor == 0 && !$mostrarZeroComoString) {
                return '0,00';
            }
            return number_format(floatval($valor), 2, ',', '.');
        }
        if (is_string($valor) && !empty(trim($valor))) {
            return htmlspecialchars(trim($valor));
        }
        return $mostrarZeroComoString ? '0,00' : '-';
    }
}

if (!function_exists('formatarTelefone')) {
    function formatarTelefone($telefone)
    {
        if (empty($telefone))
            return '-';
        $telefone = preg_replace('/\D/', '', $telefone);
        if (strlen($telefone) == 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
        } elseif (strlen($telefone) == 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
        }
        return $telefone;
    }
}

if (!function_exists('formatarStatusPedido')) {
    function formatarStatusPedido($situacao_pedido)
    {
        $statusMap = [
            'confirmado' => ['class' => 'info', 'icon' => 'check-circle', 'text' => 'CONFIRMADO'],
            'em_separacao' => ['class' => 'primary', 'icon' => 'cogs', 'text' => 'EM SEPARAÇÃO'],
            'entregue' => ['class' => 'success', 'icon' => 'truck', 'text' => 'ENTREGUE'],
            'devolvido_parcial' => ['class' => 'warning', 'icon' => 'undo', 'text' => 'DEVOLVIDO PARCIAL'],
            'finalizado' => ['class' => 'success', 'icon' => 'check-double', 'text' => 'FINALIZADO'],
            'cancelado' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'CANCELADO']
        ];

        return $statusMap[$situacao_pedido] ?? ['class' => 'secondary', 'icon' => 'question', 'text' => strtoupper($situacao_pedido)];
    }
}
// --- Fim Funções Auxiliares ---

$database = new Database();
$conn = $database->getConnection();
$pedidoModel = new Pedido($conn);
$clienteModel = new Cliente($conn);

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de pedido inválido.";
    header('Location: index.php');
    exit;
}

$id = (int) $_GET['id'];
$pedidoData = $pedidoModel->getById($id);

if (!$pedidoData) {
    $_SESSION['error_message'] = "Pedido não encontrado (ID: {$id}).";
    header('Location: index.php');
    exit;
}

// Carregar dados do pedido no modelo
foreach ($pedidoData as $key => $value) {
    if (property_exists($pedidoModel, $key)) {
        $pedidoModel->$key = $value;
    }
}

// Verificar se veio de orçamento
$orcamentoOrigem = null;
if (!empty($pedidoModel->orcamento_id)) {
    $stmt = $conn->prepare("SELECT numero FROM orcamentos WHERE id = ?");
    $stmt->execute([$pedidoModel->orcamento_id]);
    $orcamentoOrigem = $stmt->fetchColumn();
}

// Preencher dados do cliente
if (!empty($pedidoModel->cliente_id)) {
    $clienteModel->getById($pedidoModel->cliente_id);
}

$itens = $pedidoModel->getItens($id);

// ✅ CALCULAR SALDO CORRETO (considerando sinal, valor pago e multas)
$valorFinal = floatval($pedidoModel->valor_final ?? 0);
$valorSinal = floatval($pedidoModel->valor_sinal ?? 0);
$valorPago = floatval($pedidoModel->valor_pago ?? 0);
$valorMultas = floatval($pedidoModel->valor_multas ?? 0);

$totalJaPago = $valorSinal + $valorPago;
$valorFinalComMultas = $valorFinal + $valorMultas;
$saldoDevedor = max(0, $valorFinalComMultas - $totalJaPago);

// Debug para verificar cálculo
error_log("SHOW.PHP - Valor Final: R$ " . number_format($valorFinal, 2, ',', '.') .
    " | Sinal: R$ " . number_format($valorSinal, 2, ',', '.') .
    " | Valor Pago: R$ " . number_format($valorPago, 2, ',', '.') .
    " | Multas: R$ " . number_format($valorMultas, 2, ',', '.') .
    " | Total Pago: R$ " . number_format($totalJaPago, 2, ',', '.') .
    " | Saldo: R$ " . number_format($saldoDevedor, 2, ',', '.'));

// Status do pedido
$statusInfo = formatarStatusPedido($pedidoModel->situacao_pedido ?? 'confirmado');

// Define a variável JavaScript para uso no footer
$inline_js_setup = "const PEDIDO_ID = " . $id . ";";
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <section class="content-header no-print">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Pedido #<?= htmlspecialchars($pedidoModel->numero ?? 'N/A') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <?php if ($orcamentoOrigem): ?>
                        <!-- Link para orçamento origem -->
                        <a href="../orcamentos/show.php?id=<?= $pedidoModel->orcamento_id ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-file-alt"></i> Ver Orçamento #<?= $orcamentoOrigem ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($pedidoModel->situacao_pedido !== 'cancelado' && $pedidoModel->situacao_pedido !== 'finalizado'): ?>
                        <!-- Botões de ação apenas se não estiver cancelado/finalizado -->
                        <a href="edit.php?id=<?= htmlspecialchars($pedidoModel->id ?? '') ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                    <?php endif; ?>
                    
                    <!-- Status badge -->
                    <span class="badge badge-<?= $statusInfo['class'] ?> ml-1">
                        <i class="fas fa-<?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['text'] ?>
                    </span>
                    
                    <!-- Botões de impressão sempre disponíveis -->
                    <button onclick="imprimirCliente();" class="btn btn-primary btn-sm">
                        <i class="fas fa-print"></i> Imprimir p/ Cliente
                    </button>
                    <button onclick="imprimirProducao();" class="btn btn-warning btn-sm">
                        <i class="fas fa-tools"></i> Imprimir p/ Produção
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Mensagens de Alerta -->
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Card Principal -->
            <div class="card card-pedido-visual">
                <div class="card-body">
                    <!-- CABEÇALHO COM LOGO E INFORMAÇÕES DA EMPRESA -->
                    <div class="row mb-3 cabecalho-empresa">
                        <div class="col-2 logo-empresa">
                            <div class="logo-placeholder">
                                <img src="<?= BASE_URL ?>/assets/img/logo-mobel-festas.png" alt="Mobel Festas"
                                    class="img-fluid" style="max-height: 60px; max-width: 100%;"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="logo-texto"
                                    style="display: none; text-align: center; padding: 15px; border: 2px solid #000; font-size: 12px; background-color: #f8f9fa;">
                                    <div style="font-weight: bold; font-size: 14px; color: #000;">MOBEL</div>
                                    <div style="font-weight: normal; font-size: 12px; color: #000; margin-top: 2px;">
                                        FESTAS</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-7 info-empresa text-center">
                            <h4 class="mb-1"><strong>MOBEL FESTAS</strong></h4>
                            <small>R. Marques do Alegrete, 179 - São João - Porto Alegre/RS - CEP: 91020-030</small><br>
                            <small>WhatsApp: (51) 99502-5886 • CNPJ: 19.318.614/0001-44</small>
                        </div>
                        <div class="col-3 text-right info-pedido">
                            <h5 class="mb-0"><strong>PEDIDO CONFIRMADO</strong></h5>
                            <strong>Nº: <?= htmlspecialchars($pedidoModel->numero ?? 'N/A') ?></strong><br>
                            <small>Data:
                                <?= isset($pedidoModel->data_pedido) ? date('d/m/Y', strtotime($pedidoModel->data_pedido)) : date('d/m/Y') ?></small>
                            
                            <?php if ($orcamentoOrigem): ?>
                                <br><small><strong>Origem:</strong> Orçamento #<?= $orcamentoOrigem ?></small>
                            <?php endif; ?>
                            
                            <!-- Status do pedido -->
                            <br><span class="badge badge-<?= $statusInfo['class'] ?> mt-1">
                                <i class="fas fa-<?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['text'] ?>
                            </span>
                        </div>
                    </div>
                    <hr>

                    <!-- INFORMAÇÕES DO CLIENTE E EVENTO -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>Cliente:</strong>
                            <?= htmlspecialchars($clienteModel->nome ?? 'Não informado') ?><br>
                            <?php if (!empty($clienteModel->telefone)): ?>
                                <strong>Telefone:</strong> <?= formatarTelefone($clienteModel->telefone) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($clienteModel->cpf_cnpj)): ?>
                                <strong>CPF/CNPJ:</strong> <?= htmlspecialchars($clienteModel->cpf_cnpj) ?><br>
                            <?php endif; ?>
                            
                            <!-- Data do Evento -->
                            <strong>Data do evento:</strong>
                            <?php 
                            $dataEventoCompleta = '';
                            if (!empty($pedidoModel->data_evento)) {
                                $dataEventoCompleta = formatarDataDiaSemana($pedidoModel->data_evento);
                                if (!empty($pedidoModel->hora_evento) && $pedidoModel->hora_evento !== '00:00:00') {
                                    try {
                                        $horaEventoFormatada = date('H\H', strtotime($pedidoModel->hora_evento));
                                        $dataEventoCompleta .= ' às ' . $horaEventoFormatada;
                                    } catch (Exception $e) {
                                        // Ignora erro de hora
                                    }
                                }
                            } else {
                                $dataEventoCompleta = '-';
                            }
                            echo $dataEventoCompleta;
                            ?><br>
                            
                            <strong>Local de Entrega:</strong>
                            <?= htmlspecialchars($pedidoModel->local_evento ?: '-') ?><br>
                            
                            <!-- Data da Entrega -->
                            <strong>Data da Entrega:</strong>
                            <?php 
                            $dataEntregaCompleta = '';
                            if (!empty($pedidoModel->data_entrega)) {
                                $dataEntregaCompleta = formatarDataDiaSemana($pedidoModel->data_entrega);
                                $turnoHoraEntrega = formatarTurnoHora($pedidoModel->turno_entrega ?? null, $pedidoModel->hora_entrega ?? null);
                                if ($turnoHoraEntrega !== '-') {
                                    $dataEntregaCompleta .= ' - ' . $turnoHoraEntrega;
                                }
                            } else {
                                $dataEntregaCompleta = '-';
                            }
                            echo $dataEntregaCompleta;
                            ?><br>
                            
                            <!-- Data da Coleta -->
                            <strong>Data da Coleta:</strong>
                            <?php 
                            $dataColetaCompleta = '';
                            if (!empty($pedidoModel->data_devolucao_prevista)) {
                                $dataColetaCompleta = formatarDataDiaSemana($pedidoModel->data_devolucao_prevista);
                                $turnoHoraColeta = formatarTurnoHora($pedidoModel->turno_devolucao ?? null, $pedidoModel->hora_devolucao ?? null);
                                if ($turnoHoraColeta !== '-') {
                                    $dataColetaCompleta .= ' - ' . $turnoHoraColeta;
                                }
                            } else {
                                $dataColetaCompleta = '-';
                            }
                            echo $dataColetaCompleta;
                            ?>
                        </div>
                    </div>

                    <!-- BARRA DE CONTROLE FINANCEIRO -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card card-financeiro-barra">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Controle Financeiro do Pedido</h5>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row align-items-center">
                                        <!-- Valor Total -->
                                        <div class="col-md-2 text-center border-right">
                                            <div class="financeiro-item">
                                                <small class="text-muted d-block">VALOR TOTAL</small>
                                                <h4 class="text-primary mb-0">R$ <?= formatarValor($pedidoModel->valor_final ?? 0, true) ?></h4>
                                            </div>
                                        </div>
                                        
                                        <!-- Sinal (se houver) -->
                                        <?php if (!empty($pedidoModel->valor_sinal) && $pedidoModel->valor_sinal > 0): ?>
                                            <div class="col-md-2 text-center border-right">
                                                <div class="financeiro-item">
                                                    <small class="text-muted d-block">SINAL PAGO</small>
                                                    <h5 class="text-info mb-0">R$ <?= formatarValor($pedidoModel->valor_sinal, true) ?></h5>
                                                    <?php if (!empty($pedidoModel->data_pagamento_sinal)): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($pedidoModel->data_pagamento_sinal)) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Total Pago -->
                                        <div class="col-md-2 text-center border-right">
                                            <div class="financeiro-item">
                                                <small class="text-muted d-block">TOTAL PAGO</small>
                                                                <h5 class="text-success mb-0">R$ <?= formatarValor($totalJaPago, true) ?></h5>
                                                <?php if (!empty($pedidoModel->data_pagamento_final)): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($pedidoModel->data_pagamento_final)) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Multas (se houver) -->
                                        <?php if (!empty($pedidoModel->valor_multas) && $pedidoModel->valor_multas > 0): ?>
                                            <div class="col-md-2 text-center border-right">
                                                <div class="financeiro-item">
                                                    <small class="text-muted d-block">MULTAS/EXTRAS</small>
                                                    <h5 class="text-warning mb-0">R$ <?= formatarValor($pedidoModel->valor_multas, true) ?></h5>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Saldo Devedor -->
                                        <div class="col-md-2 text-center border-right">
                                            <div class="financeiro-item">
                                                <small class="text-muted d-block">SALDO DEVEDOR</small>
                                                <h4 class="<?= $saldoDevedor > 0 ? 'text-danger' : 'text-success' ?> mb-0">
                                                    R$ <?= formatarValor($saldoDevedor, true) ?>
                                                </h4>
                                                <?php if ($saldoDevedor <= 0): ?>
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle"></i> Quitado
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Status do Pagamento -->
                                        <div class="col-md-2 text-center">
                                            <div class="financeiro-item">
                                                <small class="text-muted d-block">STATUS</small>
                                                <?php if ($saldoDevedor <= 0): ?>
                                                    <span class="badge badge-success badge-status">
                                                        <i class="fas fa-check-double"></i><br>PAGO
                                                    </span>
                                                <?php elseif ($totalJaPago > 0): ?>
                                                    <span class="badge badge-warning badge-status">
                                                        <i class="fas fa-clock"></i><br>PARCIAL
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger badge-status">
                                                        <i class="fas fa-exclamation-triangle"></i><br>PENDENTE
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Condições de Pagamento (se houver) -->
                                    <?php if (!empty($pedidoModel->condicoes_pagamento)): ?>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-info mb-0">
                                                    <strong><i class="fas fa-file-contract"></i> Condições de Pagamento:</strong><br>
                                                    <?= nl2br(htmlspecialchars($pedidoModel->condicoes_pagamento)) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- OBSERVAÇÕES DE TAXAS (igual ao orçamento) -->
                    <div class="row mb-3 obs-taxas-regras">
                        <div class="col-12">
                            <small># DOMINGO/FERIADO após as 8h e antes das 12h Taxa R$ 250,00</small><br>
                            <small># MADRUGADA após as 4:30h e antes das 8:30h Taxa R$ 800,00</small><br>
                            <small># HORÁRIO ESPECIAL após as 12h de sábado até as 23:30h de segunda a sábado Taxa R$ 500,00</small><br>
                            <small># HORA MARCADA SEGUNDA A SEXTA das 8:30h até as 17h e SÁBADO das 8:30h as 12h Taxa R$ 200,00</small><br>
                            <small># Infelizmente não dispomos de entregas ou coletas no período das 24h as 5h</small>
                        </div>
                    </div>
                    <hr>

                    <!-- ATENDIMENTO E TIPO -->
                    <div class="row mb-3">
                        <div class="col-6">
                            Atend.: <?php
                            $nomeAtendente = 'LARA';
                            if (!empty($pedidoModel->usuario_id)) {
                                try {
                                    $userStmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = :id");
                                    $userStmt->bindParam(':id', $pedidoModel->usuario_id, PDO::PARAM_INT);
                                    $userStmt->execute();
                                    $nomeAtendenteDB = $userStmt->fetchColumn();
                                    if ($nomeAtendenteDB)
                                        $nomeAtendente = $nomeAtendenteDB;
                                } catch (PDOException $e) {
                                    error_log("Erro ao buscar nome do atendente: " . $e->getMessage());
                                }
                            }
                            echo htmlspecialchars($nomeAtendente);
                            ?>
                        </div>
                        <div class="col-6 text-right font-weight-bold">
                            PEDIDO CONFIRMADO <?= htmlspecialchars(strtoupper($pedidoModel->tipo ?? 'Locação')) ?>
                        </div>
                    </div>

                    <!-- TABELA DE ITENS (igual ao orçamento) -->
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered table-itens-pedido">
                            <thead>
                                <tr class="text-center">
                                    <th style="width: 8%;">QTD</th>
                                    <th>DESCRIÇÃO DO PRODUTO/SERVIÇO</th>
                                    <th style="width: 12%;">UNITÁRIO</th>
                                    <?php
                                    // Verifica se algum item tem desconto para mostrar a coluna
                                    $temDesconto = false;
                                    if (!empty($itens) && is_array($itens)) {
                                        foreach ($itens as $item) {
                                            if (isset($item['desconto']) && floatval($item['desconto']) > 0) {
                                                $temDesconto = true;
                                                break;
                                            }
                                        }
                                    }
                                    if ($temDesconto): ?>
                                        <th style="width: 12%;">DESCONTO</th>
                                    <?php endif; ?>
                                    <th style="width: 15%;">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $subtotalItensPIX = 0;
                                if (!empty($itens) && is_array($itens)):
                                    foreach ($itens as $item):
                                        // Determina o nome do produto/serviço
                                        $nomeItem = '';
                                        if (!empty($item['nome_produto_manual'])) {
                                            $nomeItem = $item['nome_produto_manual'];
                                        } elseif (!empty($item['nome_produto_catalogo'])) {
                                            $nomeItem = $item['nome_produto_catalogo'];
                                        } else {
                                            $nomeItem = 'Item não identificado';
                                        }

                                        // Verifica se é um título de seção
                                        if (($item['tipo_linha'] ?? '') === 'CABECALHO_SECAO'):
                                            ?>
                                            <tr class="titulo-secao">
                                                <td colspan="<?= $temDesconto ? '5' : '4' ?>" class="font-weight-bold bg-light">
                                                    <span class="titulo-secao-texto"><?= htmlspecialchars(strtoupper($nomeItem)) ?></span>
                                                </td>
                                            </tr>
                                        <?php else:
                                            // É um produto normal
                                            $quantidadeItem = isset($item['quantidade']) ? floatval($item['quantidade']) : 0;
                                            $precoUnitarioItem = isset($item['preco_unitario']) ? floatval($item['preco_unitario']) : 0;
                                            $descontoItem = isset($item['desconto']) ? floatval($item['desconto']) : 0;
                                            $itemSubtotal = isset($item['preco_final']) ? floatval($item['preco_final']) : 0;
                                            $subtotalItensPIX += $itemSubtotal;
                                            ?>
                                            <tr>
                                                <td class="text-center"><?= htmlspecialchars(number_format($quantidadeItem, 0)) ?></td>
                                                <td>
                                                    <?php if (!empty($item['foto_path'])): ?>
                                                        <img src="<?= BASE_URL ?>/<?= ltrim($item['foto_path'], '/') ?>"
                                                            alt="<?= htmlspecialchars($nomeItem) ?>" class="produto-foto-impressao"
                                                            style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; vertical-align: middle; float: left;"
                                                            onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <div style="overflow: hidden;">
                                                        <?= htmlspecialchars(strtoupper($nomeItem)) ?>
                                                        <?php if (!empty($item['observacoes'])): ?>
                                                            <br><small class="observacao-item text-muted"
                                                                style="font-style: italic;"><?= htmlspecialchars($item['observacoes']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-right">R$ <?= formatarValor($precoUnitarioItem) ?></td>
                                                <?php if ($temDesconto): ?>
                                                    <td class="text-right">
                                                        <?= $descontoItem > 0 ? 'R$ ' . formatarValor($descontoItem) : '-' ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td class="text-right">R$ <?= formatarValor($itemSubtotal) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php
                                    endforeach;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="<?= $temDesconto ? '5' : '4' ?>" class="text-center text-muted">Nenhum item adicionado.</td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Linhas em branco para visual -->
                                <?php
                                $totalItensExibidos = is_array($itens) ? count($itens) : 0;
                                $totalLinhasVisuais = 8;
                                $linhasAdicionais = $totalLinhasVisuais - $totalItensExibidos;
                                if ($linhasAdicionais < 0) $linhasAdicionais = 0;

                                for ($i = 0; $i < $linhasAdicionais; $i++):
                                    ?>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <?php if ($temDesconto): ?><td>&nbsp;</td><?php endif; ?>
                                        <td>&nbsp;</td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- OBSERVAÇÕES GERAIS E SUBTOTAL -->
                    <div class="row mb-3">
                        <div class="col-7 obs-gerais">
                            <small># Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa</small><br>
                            <small>&nbsp;&nbsp;desde que não ultrapasse 10% do valor total contratado #</small><br>
                            <small>* Não Inclui Posicionamento dos Móveis no Local *</small>
                        </div>
                        <div class="col-5 text-right">
                            <strong>Sub total p/ PIX ou Depósito</strong>
                            <strong class="ml-3">R$ <?= formatarValor($subtotalItensPIX) ?></strong>
                        </div>
                    </div>
                    <hr>

                    <!-- FORMA DE PAGAMENTO E TAXAS/FRETES -->
                    <div class="row">
                        <div class="col-7 forma-pagamento">
                            <strong>Forma de Pagamento:</strong><br>
                            <small>ENTRADA 30% PARA RESERVA EM PIX OU DEPÓSITO</small><br>
                            <small>SALDO EM PIX OU DEPÓSITO 7 DIAS ANTES EVENTO</small><br><br>
                            <small>* Consulte se há disponibilidade e</small><br>
                            <small>&nbsp;&nbsp;quais os preços de locação para pagamento</small><br>
                            <small>&nbsp;&nbsp;no cartão de crédito</small>
                        </div>
                        <div class="col-5 taxas-fretes text-right">
                            <?php
                            // Função para verificar se uma taxa deve ser exibida
                            function exibirTaxa($valor, $valorPadrao = null)
                            {
                                if (is_numeric($valor) && $valor > 0) {
                                    return 'R$ ' . formatarValor($valor);
                                }
                                return 'a confirmar';
                            }
                            ?>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA DOMINGO E FERIADO R$ 250,00</span>
                                <span><?= exibirTaxa($pedidoModel->taxa_domingo_feriado ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA MADRUGADA R$ 800,00</span>
                                <span><?= exibirTaxa($pedidoModel->taxa_madrugada ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA HORÁRIO ESPECIAL R$ 500,00</span>
                                <span><?= exibirTaxa($pedidoModel->taxa_horario_especial ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA HORA MARCADA R$ 200,00</span>
                                <span><?= exibirTaxa($pedidoModel->taxa_hora_marcada ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">FRETE ELEVADOR</span>
                                <span><?= exibirTaxa($pedidoModel->frete_elevador ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">FRETE ESCADAS</span>
                                <span><?= exibirTaxa($pedidoModel->frete_escadas ?? 0) ?></span>
                            </div>
                            <div class="mb-2">
                                <span class="text-left-label"><strong>FRETE TÉRREO SEM ESCADAS</strong></span>
                                <span><strong>R$ <?= formatarValor($pedidoModel->frete_terreo ?? 0, true) ?></strong></span>
                            </div>

                            <?php if (!empty($pedidoModel->desconto) && $pedidoModel->desconto > 0): ?>
                                <div class="mb-1 text-danger">
                                    <span class="text-left-label">DESCONTO GERAL</span>
                                    <span>- R$ <?= formatarValor($pedidoModel->desconto) ?></span>
                                </div>
                            <?php endif; ?>

                            <hr style="margin: 0.5rem 0;">
                            <h4><strong>
                                <span class="text-left-label">Total p/ PIX ou Depósito</span>
                                <span>R$ <?= formatarValor($pedidoModel->valor_final ?? 0, true) ?></span>
                            </strong></h4>
                        </div>
                    </div>
                    <hr>

                    <!-- INFORMAÇÕES DO PIX -->
                    <div class="row mt-3">
                        <div class="col-12 text-center info-pix">
                            <strong>PIX SICREDI CNPJ 19.318.614 / 0001-44</strong><br>
                                                        <small>* Pedimos a gentileza de enviar por Whatsapp seu comprovante para baixar no estoque e garantir sua reserva</small>
                        </div>
                    </div>

                    <!-- Observações adicionais -->
                    <?php if (!empty($pedidoModel->observacoes)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <h5>Observações Adicionais:</h5>
                                <p><?= nl2br(htmlspecialchars($pedidoModel->observacoes)) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($pedidoModel->motivo_ajuste) && !empty($pedidoModel->desconto) && $pedidoModel->desconto > 0): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <small><strong>Motivo do ajuste:</strong>
                                    <?= htmlspecialchars($pedidoModel->motivo_ajuste) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
</div>

<style>
    /* Estilos herdados do orçamento com adaptações para pedido */
    .produto-foto-impressao {
        width: 50px !important;
        height: 50px !important;
        object-fit: cover !important;
        margin-right: 10px !important;
        border: 1px solid #ddd !important;
        border-radius: 4px !important;
        vertical-align: middle !important;
        float: left !important;
    }

    /* ESTILOS PARA BARRA FINANCEIRA */
    .card-financeiro-barra {
        border: 2px solid #17a2b8;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .financeiro-item {
        padding: 10px 5px;
    }

    .financeiro-item h4, .financeiro-item h5 {
        font-weight: bold;
        margin-bottom: 5px;
    }

    .badge-status {
        font-size: 0.8rem;
        padding: 8px 12px;
        text-align: center;
        line-height: 1.2;
    }

    .border-right {
        border-right: 1px solid #dee2e6 !important;
    }

    @media print {
        .produto-foto-impressao {
            width: 40px !important;
            height: 40px !important;
            margin-right: 8px !important;
            border: 1px solid #777 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .card-financeiro-barra {
            border: 2px solid #777 !important;
            box-shadow: none !important;
        }

        .card-financeiro-barra .card-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .border-right {
            border-right: 1px solid #777 !important;
        }
    }

    @media (max-width: 768px) {
        .border-right {
            border-right: none !important;
            border-bottom: 1px solid #dee2e6 !important;
            margin-bottom: 15px;
        }
    }

    .card-pedido-visual {
        font-family: Calibri, Arial, sans-serif;
        font-size: 11pt;
        border: 1px solid #ccc;
    }

    .card-pedido-visual .card-body {
        padding: 20px;
    }

    .cabecalho-empresa {
        border-bottom: 2px solid #000;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .logo-placeholder {
        text-align: center;
        padding: 5px;
        min-height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .info-empresa h4 {
        color: #000;
        margin-bottom: 5px;
        font-size: 14pt;
    }

    .info-pedido {
        font-size: 10pt;
        color: #000;
    }

    .card-pedido-visual strong {
        font-weight: bold;
        color: #000;
    }

    .card-pedido-visual hr {
        border-top: 1px solid #000;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .obs-taxas-regras small, .obs-gerais small, .forma-pagamento small, .info-pix small {
        font-size: 10pt;
        color: #000;
        display: block;
        line-height: 1.3;
    }

    .table-itens-pedido th, .table-itens-pedido td {
        padding: 0.25rem 0.5rem;
        vertical-align: middle;
        border: 1px solid #dee2e6;
        font-size: 11pt;
        color: #000;
    }

    .table-itens-pedido thead th {
        background-color: #f8f9fa;
        font-weight: bold;
        text-align: center;
        color: #000;
    }

    .titulo-secao td {
        background-color: #e7f1ff !important;
        font-weight: bold;
        font-size: 12pt;
        padding: 0.4rem 0.5rem !important;
        color: #000 !important;
    }

    .titulo-secao-texto {
        text-align: center !important;
        display: block;
        width: 100%;
    }

    .taxas-fretes div {
        font-size: 11pt;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2px;
        color: #000;
    }

    .taxas-fretes div span:last-child {
        text-align: left !important;
        min-width: 80px;
    }

    .taxas-fretes div span.valor-preenchido {
        text-align: right !important;
    }

    .taxas-fretes div span.text-left-label {
        text-align: left;
        margin-right: auto;
        color: #000;
    }

    .taxas-fretes h4 {
        font-size: 12pt;
        color: #000;
    }

    /* Estilos para badges */
    .badge {
        font-size: 0.8em;
        margin-left: 5px;
    }

    .btn-group-actions {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    @media print {
        body {
            font-size: 10pt;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #000 !important;
        }

        .no-print, .main-sidebar, .content-header .btn, .alert {
            display: none !important;
        }

        .content-wrapper {
            margin-left: 0 !important;
            padding-top: 0 !important;
        }

        .content-header {
            display: none;
        }

        .card-pedido-visual {
            box-shadow: none !important;
            border: none !important;
            margin-bottom: 0 !important;
        }

        .card-pedido-visual .card-body {
            padding: 10px !important;
        }

        .table-itens-pedido {
            width: 100% !important;
        }

        .table-itens-pedido th, .table-itens-pedido td {
            border: 1px solid #777 !important;
            font-size: 10pt !important;
            color: #000 !important;
        }

        .titulo-secao td {
            background-color: #e7f1ff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #000 !important;
        }

        hr {
            border-top: 1px solid #777 !important;
        }

        .taxas-fretes div {
            display: flex;
            justify-content: space-between;
            color: #000 !important;
        }

        .cabecalho-empresa {
            border-bottom: 2px solid #000 !important;
        }

        .info-empresa h4 {
            font-size: 12pt !important;
            color: #000 !important;
        }

        /* Oculta observações na impressão para cliente */
        .impressao-cliente .observacao-item {
            display: none !important;
        }
    }
</style>

<script>
    // Funções de impressão (iguais ao orçamento)
    function imprimirCliente() {
        console.log('Função imprimirCliente chamada');
        var observacoes = document.querySelectorAll('.observacao-item');
        console.log('Observações encontradas:', observacoes.length);
        observacoes.forEach(function (el) {
            el.style.display = 'none';
        });
        document.body.classList.add('impressao-cliente');
        window.print();
        setTimeout(function () {
            observacoes.forEach(function (el) {
                el.style.display = 'block';
            });
            document.body.classList.remove('impressao-cliente');
            console.log('Observações restauradas');
        }, 1000);
    }

    function imprimirProducao() {
        console.log('Função imprimirProducao chamada');
        var observacoes = document.querySelectorAll('.observacao-item');
        console.log('Observações encontradas:', observacoes.length);
        observacoes.forEach(function (el) {
            el.style.display = 'block';
        });
        document.body.classList.add('impressao-producao');
        window.print();
        setTimeout(function () {
            document.body.classList.remove('impressao-producao');
            console.log('Classe impressao-producao removida');
        }, 1000);
    }

    // Teste se as funções estão carregadas
    document.addEventListener('DOMContentLoaded', function () {
        console.log('JavaScript carregado com sucesso');
        console.log('Função imprimirCliente:', typeof imprimirCliente);
        console.log('Função imprimirProducao:', typeof imprimirProducao);
    });
</script>

<?php
// Define o JavaScript customizado para o footer
$custom_js = <<<'JS'
// JavaScript adicional se necessário
console.log('Show.php de pedidos carregado com sucesso');
console.log('PEDIDO_ID:', typeof PEDIDO_ID !== 'undefined' ? PEDIDO_ID : 'não definido');
JS;

include_once __DIR__ . '/../includes/footer.php';
?>