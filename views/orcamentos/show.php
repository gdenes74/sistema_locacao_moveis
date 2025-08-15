<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Funções Auxiliares ---
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
                // não faz nada se a hora for inválida
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
// --- Fim Funções Auxiliares ---

$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);
$clienteModel = new Cliente($conn);

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de orçamento inválido.";
    header('Location: index.php');
    exit;
}

$id = (int) $_GET['id'];
if (!$orcamentoModel->getById($id)) {
    $_SESSION['error_message'] = "Orçamento não encontrado (ID: {$id}).";
    header('Location: index.php');
    exit;
}

// Preencher dados do cliente
if (!empty($orcamentoModel->cliente_id)) {
    $clienteModel->getById($orcamentoModel->cliente_id);
}

$itens = $orcamentoModel->getItens($id);
?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <section class="content-header no-print">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Orçamento #<?= htmlspecialchars($orcamentoModel->numero ?? 'N/A') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <a href="edit.php?id=<?= htmlspecialchars($orcamentoModel->id ?? '') ?>"
                        class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <button type="button" class="btn btn-success btn-sm" id="btnGerarPedidoShow">
    <i class="fas fa-check-circle"></i> Converter p/ Pedido
</button>
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
            <div class="card card-orcamento-visual">
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
                        <div class="col-3 text-right info-orcamento">
                            <h5 class="mb-0"><strong>ORÇAMENTO</strong></h5>
                            <strong>Nº: <?= htmlspecialchars($orcamentoModel->numero ?? 'N/A') ?></strong><br>
                            <small>Data:
                                <?= isset($orcamentoModel->data_orcamento) ? date('d/m/Y', strtotime($orcamentoModel->data_orcamento)) : date('d/m/Y') ?></small>
                            <?php if (!empty($orcamentoModel->data_validade)): ?>
                                <br><small><strong>Válido até:</strong>
                                    <?= date('d/m/Y', strtotime($orcamentoModel->data_validade)) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>

                    <!-- INFORMAÇÕES DO CLIENTE E EVENTO -->
                    <div class="row mb-3">
                        <div class="col-8">
                            <strong>Cliente:</strong>
                            <?= htmlspecialchars($clienteModel->nome ?? 'Não informado') ?><br>
                            <?php if (!empty($clienteModel->telefone)): ?>
                                <strong>Telefone:</strong> <?= formatarTelefone($clienteModel->telefone) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($clienteModel->cpf_cnpj)): ?>
                                <strong>CPF/CNPJ:</strong> <?= htmlspecialchars($clienteModel->cpf_cnpj) ?><br>
                            <?php endif; ?>
                            <strong>Data do evento:</strong>
                            <?= formatarDataDiaSemana($orcamentoModel->data_evento ?? null) ?><br>
                            <strong>Local de Entrega:</strong>
                            <?= htmlspecialchars($orcamentoModel->local_evento ?: '-') ?>
                        </div>
                        <div class="col-4 text-right">
                            <!-- Espaço reservado para informações adicionais se necessário -->
                        </div>
                    </div>

                    <!-- DATAS DE LOGÍSTICA -->
                    <div class="row mb-2">
                        <div class="col-12">
                            <strong>Data da Entrega:</strong>
                            <?= formatarTurnoHora($orcamentoModel->turno_entrega ?? null, $orcamentoModel->hora_evento ?? null) ?><br>
                            <strong>Data da Coleta:</strong>
                            <?= formatarTurnoHora($orcamentoModel->turno_devolucao ?? null, $orcamentoModel->hora_devolucao ?? null) ?>
                        </div>
                    </div>

                    <!-- OBSERVAÇÕES DE TAXAS -->
                    <div class="row mb-3 obs-taxas-regras">
                        <div class="col-12">
                            <small># DOMINGO/FERIADO após as 8h e antes das 12h Taxa R$ 250,00</small><br>
                            <small># MADRUGADA após as 4:30h e antes das 8:30h Taxa R$ 800,00</small><br>
                            <small># HORÁRIO ESPECIAL após as 12h de sábado até as 23:30h de segunda a sábado Taxa R$
                                500,00</small><br>
                            <small># HORA MARCADA SEGUNDA A SEXTA das 8:30h até as 17h e SÁBADO das 8:30h as 12h Taxa R$
                                200,00</small><br>
                            <small># Infelizmente não dispomos de entregas ou coletas no período das 24h as 5h</small>
                        </div>
                    </div>
                    <hr>

                    <!-- ATENDIMENTO E TIPO -->
                    <div class="row mb-3">
                        <div class="col-6">
                            Atend.: <?php
                            $nomeAtendente = 'LARA';
                            if (!empty($orcamentoModel->usuario_id)) {
                                try {
                                    $userStmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = :id");
                                    $userStmt->bindParam(':id', $orcamentoModel->usuario_id, PDO::PARAM_INT);
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
                            ORÇAMENTO <?= htmlspecialchars(strtoupper($orcamentoModel->tipo ?? 'Locação')) ?>
                        </div>
                    </div>

                    <!-- TABELA DE ITENS -->
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered table-itens-orcamento">
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
                                            // Item digitado manualmente
                                            $nomeItem = $item['nome_produto_manual'];
                                        } elseif (!empty($item['nome_produto_catalogo'])) {
                                            // Item do catálogo
                                            $nomeItem = $item['nome_produto_catalogo'];
                                        } else {
                                            $nomeItem = 'Item não identificado';
                                        }

                                        // Verifica se é um título de seção
                                        if (($item['tipo_linha'] ?? '') === 'CABECALHO_SECAO'):
                                            ?>
                                            <tr class="titulo-secao">
                                                <td colspan="<?= $temDesconto ? '5' : '4' ?>" class="font-weight-bold bg-light">
                                                    <span
                                                        class="titulo-secao-texto"><?= htmlspecialchars(strtoupper($nomeItem)) ?></span>
                                                </td>
                                            </tr>
                                        <?php else:
                                            // É um produto normal
                                            $quantidadeItem = isset($item['quantidade']) ? floatval($item['quantidade']) : 0;
                                            $precoUnitarioItem = isset($item['preco_unitario']) ? floatval($item['preco_unitario']) : 0;
                                            $descontoItem = isset($item['desconto']) ? floatval($item['desconto']) : 0;

                                            // Usa o preco_final que já vem calculado do banco
                                            $itemSubtotal = isset($item['preco_final']) ? floatval($item['preco_final']) : 0;
                                            $subtotalItensPIX += $itemSubtotal;
                                            ?>
                                            <tr>
                                                <td class="text-center"><?= htmlspecialchars(number_format($quantidadeItem, 0)) ?>
                                                </td>
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
                                        <td colspan="<?= $temDesconto ? '5' : '4' ?>" class="text-center text-muted">Nenhum
                                            item adicionado.</td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Linhas em branco para visual -->
                                <?php
                                $totalItensExibidos = is_array($itens) ? count($itens) : 0;
                                $totalLinhasVisuais = 8;
                                $linhasAdicionais = $totalLinhasVisuais - $totalItensExibidos;
                                if ($linhasAdicionais < 0)
                                    $linhasAdicionais = 0;

                                for ($i = 0; $i < $linhasAdicionais; $i++):
                                    ?>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <?php if ($temDesconto): ?>
                                            <td>&nbsp;</td><?php endif; ?>
                                        <td>&nbsp;</td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- OBSERVAÇÕES GERAIS E SUBTOTAL -->
                    <div class="row mb-3">
                        <div class="col-7 obs-gerais">
                            <small># Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da
                                festa</small><br>
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
                                <span><?= exibirTaxa($orcamentoModel->taxa_domingo_feriado ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA MADRUGADA R$ 800,00</span>
                                <span><?= exibirTaxa($orcamentoModel->taxa_madrugada ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA HORÁRIO ESPECIAL R$ 500,00</span>
                                <span><?= exibirTaxa($orcamentoModel->taxa_horario_especial ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">TAXA HORA MARCADA R$ 200,00</span>
                                <span><?= exibirTaxa($orcamentoModel->taxa_hora_marcada ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">FRETE ELEVADOR</span>
                                <span><?= exibirTaxa($orcamentoModel->frete_elevador ?? 0) ?></span>
                            </div>
                            <div class="mb-1">
                                <span class="text-left-label">FRETE ESCADAS</span>
                                <span><?= exibirTaxa($orcamentoModel->frete_escadas ?? 0) ?></span>
                            </div>
                            <div class="mb-2">
                                <span class="text-left-label"><strong>FRETE TÉRREO SEM ESCADAS</strong></span>
                                <span><strong>R$
                                        <?= formatarValor($orcamentoModel->frete_terreo ?? 0, true) ?></strong></span>
                            </div>

                            <?php if (!empty($orcamentoModel->desconto) && $orcamentoModel->desconto > 0): ?>
                                <div class="mb-1 text-danger">
                                    <span class="text-left-label">DESCONTO GERAL</span>
                                    <span>- R$ <?= formatarValor($orcamentoModel->desconto) ?></span>
                                </div>
                            <?php endif; ?>

                            <hr style="margin: 0.5rem 0;">
                            <h4><strong>
                                    <span class="text-left-label">Total p/ PIX ou Depósito</span>
                                    <span>R$ <?= formatarValor($orcamentoModel->valor_final ?? 0, true) ?></span>
                                </strong></h4>
                        </div>
                    </div>
                    <hr>

                    <!-- INFORMAÇÕES DO PIX -->
                    <div class="row mt-3">
                        <div class="col-12 text-center info-pix">
                            <strong>PIX SICREDI CNPJ 19.318.614 / 0001-44</strong><br>
                            <small>* Pedimos a gentileza de enviar por Whatsapp seu comprovante para baixar no estoque e
                                garantir sua reserva</small>
                        </div>
                    </div>

                    <!-- Observações adicionais -->
                    <?php if (!empty($orcamentoModel->observacoes)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <h5>Observações Adicionais:</h5>
                                <p><?= nl2br(htmlspecialchars($orcamentoModel->observacoes)) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($orcamentoModel->motivo_ajuste) && !empty($orcamentoModel->desconto) && $orcamentoModel->desconto > 0): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <small><strong>Motivo do ajuste:</strong>
                                    <?= htmlspecialchars($orcamentoModel->motivo_ajuste) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
</div>

<style>
    /* Estilos para fotos dos produtos */
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

    @media print {
        .produto-foto-impressao {
            width: 40px !important;
            height: 40px !important;
            margin-right: 8px !important;
            border: 1px solid #777 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }

    .card-orcamento-visual {
        font-family: Calibri, Arial, sans-serif;
        font-size: 11pt;
        border: 1px solid #ccc;
    }

    .card-orcamento-visual .card-body {
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

    .info-orcamento {
        font-size: 10pt;
        color: #000;
    }

    .card-orcamento-visual strong {
        font-weight: bold;
        color: #000;
    }

    .card-orcamento-visual hr {
        border-top: 1px solid #000;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .obs-taxas-regras small,
    .obs-gerais small,
    .forma-pagamento small,
    .info-pix small {
        font-size: 10pt;
        color: #000;
        display: block;
        line-height: 1.3;
    }

    .table-itens-orcamento th,
    .table-itens-orcamento td {
        padding: 0.25rem 0.5rem;
        vertical-align: middle;
        border: 1px solid #dee2e6;
        font-size: 11pt;
        color: #000;
    }

    .table-itens-orcamento thead th {
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

    @media print {
        body {
            font-size: 10pt;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #000 !important;
        }

        .no-print,
        .main-sidebar,
        .content-header .btn,
     orcamento   .alert {
            display: none !important;
        }

        .content-wrapper {
            margin-left: 0 !important;
            padding-top: 0 !important;
        }

        .content-header {
            display: none;
        }

        .card-orcamento-visual {
            box-shadow: none !important;
            border: none !important;
            margin-bottom: 0 !important;
        }

        .card-orcamento-visual .card-body {
            padding: 10px !important;
        }

        .table-itens-orcamento {
            width: 100% !important;
        }

        .table-itens-orcamento th,
        .table-itens-orcamento td {
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
    function imprimirCliente() {
        console.log('Função imprimirCliente chamada');

        // Esconde as observações dos itens
        var observacoes = document.querySelectorAll('.observacao-item');
        console.log('Observações encontradas:', observacoes.length);

        observacoes.forEach(function (el) {
            el.style.display = 'none';
        });

        // Adiciona classe para identificar impressão cliente
        document.body.classList.add('impressao-cliente');

        // Imprime
        window.print();

        // Restaura as observações após a impressão
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

        // Mostra todas as observações
        var observacoes = document.querySelectorAll('.observacao-item');
        console.log('Observações encontradas:', observacoes.length);

        observacoes.forEach(function (el) {
            el.style.display = 'block';
        });

        // Adiciona classe para identificar impressão produção
        document.body.classList.add('impressao-producao');

        // Imprime
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
    $('#btnGerarPedidoShow').on('click', function() {
    const orcamentoId = <?= $orcamentoModel->id ?>;
    const orcamentoNumero = '<?= htmlspecialchars($orcamentoModel->numero) ?>';
    
    Swal.fire({
        title: 'Gerar Pedido',
        text: `Deseja converter o orçamento #${orcamentoNumero} em um pedido confirmado?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Gerar Pedido',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'converter_pedido.php',
                type: 'POST',
                data: { orcamento_id: orcamentoId },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        Swal.fire('Sucesso!', data.message, 'success').then(() => {
                            window.location.href = `../pedidos/show.php?id=${data.pedido_id}`;
                        });
                    } else {
                        Swal.fire('Erro', data.message, 'error');
                    }
                }
            });
        }
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>