<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php'; // Para buscar nomes de produtos

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Funções Auxiliares (coloque no início ou em um arquivo de 'helpers') ---
if (!function_exists('formatarDataDiaSemana')) {
    function formatarDataDiaSemana($dataModel) {
        if (empty($dataModel) || $dataModel === '0000-00-00' || $dataModel === '0000-00-00 00:00:00') return '-';
        try {
            $timestamp = strtotime($dataModel);
            if ($timestamp === false) return '-'; // Data inválida
            $dias = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
            return date('d.m.y', $timestamp) . ' ' . $dias[date('w', $timestamp)];
        } catch (Exception $e) {
            return '-'; // Em caso de erro na conversão
        }
    }
}

if (!function_exists('formatarTurnoHora')) {
    function formatarTurnoHora($turno, $hora) {
        $retorno = htmlspecialchars(trim($turno ?? ''));
        if (!empty($hora) && $hora !== '00:00:00') {
            try {
                $horaFormatada = date('H\\H', strtotime($hora)); // Escapar o H para literal 'H'
                $retorno .= ($retorno ? ' APROX. ' : 'APROX. ') . htmlspecialchars($horaFormatada);
            } catch (Exception $e) {
                // não faz nada se a hora for inválida
            }
        }
        return trim($retorno) ?: '-';
    }
}

if (!function_exists('formatarValor')) {
    function formatarValor($valor, $mostrarZeroComoString = false) {
        if (is_numeric($valor)) {
            if ($valor == 0 && !$mostrarZeroComoString) {
                return '0,00'; // Ou '-', se preferir não mostrar zero
            }
            return number_format(floatval($valor), 2, ',', '.');
        }
        // Se não for numérico mas for uma string não vazia (ex: "confirmar"), retorna ela
        if (is_string($valor) && !empty(trim($valor))) {
            return htmlspecialchars(trim($valor));
        }
        return $mostrarZeroComoString ? '0,00' : '-'; // Default para outros casos
    }
}
// --- Fim Funções Auxiliares ---


$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);
$clienteModel = new Cliente($conn);
// $produtoModel global não será usado para buscar itens individuais no loop,
// mas pode ser útil para outras coisas se necessário.

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID de orçamento inválido.";
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id']; // Cast para int
if (!$orcamentoModel->getById($id)) {
    $_SESSION['error_message'] = "Orçamento não encontrado (ID: {$id}). Verifique se o método getById na classe Orcamento está funcionando e retornando dados.";
    header('Location: index.php');
    exit;
}

// Preencher dados do cliente se cliente_id existir no orçamento
if (!empty($orcamentoModel->cliente_id)) {
    $clienteModel->getById($orcamentoModel->cliente_id); // Assume que getById preenche o objeto $clienteModel
}

$itens = $orcamentoModel->getItens($id); // Assume que getItens retorna um array de itens
?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

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
                    <a href="edit.php?id=<?= htmlspecialchars($orcamentoModel->id ?? '') ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="../pedidos/create.php?orcamento_id=<?= htmlspecialchars($orcamentoModel->id ?? '') ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-check-circle"></i> Converter p/ Pedido
                    </a>
                    <button onclick="window.print();" class="btn btn-info btn-sm">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Mensagens de Alerta (não serão impressas) -->
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

            <!-- Card Principal para o conteúdo do orçamento -->
            <div class="card card-orcamento-visual">
                <div class="card-body">
                    <!-- CABEÇALHO DO ORÇAMENTO -->
                    <div class="row mb-3">
                        <div class="col-8">
                            <strong>Cliente:</strong> <?= htmlspecialchars($clienteModel->nome ?? 'Não informado') ?><br>
                            <strong>Data do evento:</strong> <?= formatarDataDiaSemana($orcamentoModel->data_evento ?? null) ?><br>
                            <strong>Local de Entrega:</strong> <?= htmlspecialchars($orcamentoModel->local_evento ?: '-') ?>
                        </div>
                        <div class="col-4 text-right">
                            <h5 class="mb-0">ORÇAMENTO</h5>
                            <strong>Nº: <?= htmlspecialchars($orcamentoModel->numero ?? 'N/A') ?></strong><br>
                            Data: <?= isset($orcamentoModel->data_orcamento) ? date('d/m/Y', strtotime($orcamentoModel->data_orcamento)) : date('d/m/Y') ?>
                        </div>
                    </div>
                    <hr>

                    <!-- DATAS DE LOGÍSTICA E OBSERVAÇÕES DE TAXAS -->
                    <div class="row mb-2">
                        <div class="col-12">
                            <strong>Data da Entrega:</strong> <?= formatarTurnoHora($orcamentoModel->turno_entrega ?? null, $orcamentoModel->hora_evento ?? null) ?><br>
                            <strong>Data da Coleta:</strong> <?= formatarTurnoHora($orcamentoModel->turno_devolucao ?? null, $orcamentoModel->hora_devolucao ?? null) ?>
                        </div>
                    </div>
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
                    <!-- ATENDIMENTO E TIPO PEDIDO/ORÇAMENTO -->
                    <div class="row mb-3">
                        <div class="col-6">
                            Atend.: <?php
                                $nomeAtendente = 'LARA'; // Valor Padrão
                                if (!empty($orcamentoModel->usuario_id)) {
                                    try {
                                        $userStmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = :id");
                                        $userStmt->bindParam(':id', $orcamentoModel->usuario_id, PDO::PARAM_INT);
                                        $userStmt->execute();
                                        $nomeAtendenteDB = $userStmt->fetchColumn();
                                        if ($nomeAtendenteDB) $nomeAtendente = $nomeAtendenteDB;
                                    } catch (PDOException $e) {
                                        error_log("Erro ao buscar nome do atendente: " . $e->getMessage());
                                        // Mantém $nomeAtendente como 'LARA'
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
                                    <th style="width: 15%;">UNITÁRIO</th>
                                    <th style="width: 15%;">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $subtotalItensPIX = 0;
                                if (!empty($itens) && is_array($itens)): // Verifica se $itens é um array e não está vazio
                                    foreach ($itens as $item):
                                        $produtoNome = 'Produto não encontrado'; // Valor padrão
                                        $produto_id_item = $item['produto_id'] ?? null;

                                        if ($produto_id_item) {
                                            $produtoParaItem = new Produto($conn); // Nova instância para cada item
                                            $dadosProdutoArray = $produtoParaItem->lerPorId((int)$produto_id_item); // Chama o método correto

                                            if ($dadosProdutoArray !== null && isset($dadosProdutoArray['nome_produto'])) {
                                                $produtoNome = $dadosProdutoArray['nome_produto'];
                                            }
                                        }
                                        
                                        $quantidadeItem = isset($item['quantidade']) ? floatval($item['quantidade']) : 0;
                                        $precoUnitarioItem = isset($item['preco_unitario']) ? floatval($item['preco_unitario']) : 0;
                                        $descontoItem = isset($item['desconto']) ? floatval($item['desconto']) : 0;
                                        
                                        $itemSubtotal = ($quantidadeItem * $precoUnitarioItem) - $descontoItem;
                                        $subtotalItensPIX += $itemSubtotal;
                                ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars(number_format($quantidadeItem, 0)) ?></td>
                                    <td><?= htmlspecialchars(strtoupper($produtoNome)) ?></td>
                                    <td class="text-right">R$ <?= formatarValor($precoUnitarioItem) ?></td>
                                    <td class="text-right">R$ <?= formatarValor($itemSubtotal) ?></td>
                                </tr>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhum item adicionado.</td>
                                </tr>
                                <?php endif; ?>
                                <!-- Linhas em branco para preenchimento visual como no Excel -->
                                <?php
                                $totalItensExibidos = is_array($itens) ? count($itens) : 0;
                                $totalLinhasVisuais = 8; // Número total de linhas que você quer na tabela visualmente
                                $linhasAdicionais = $totalLinhasVisuais - $totalItensExibidos;
                                if ($linhasAdicionais < 0) $linhasAdicionais = 0;

                                for ($i = 0; $i < $linhasAdicionais; $i++):
                                ?>
                                <tr>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
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
                            <div class="mb-1"><span class="mr-2 text-left-label">TAXA DOMINGO E FERIADO R$ 250,</span> <?= (isset($orcamentoModel->taxa_domingo_feriado) && is_numeric($orcamentoModel->taxa_domingo_feriado) && $orcamentoModel->taxa_domingo_feriado > 0) ? 'R$ '.formatarValor($orcamentoModel->taxa_domingo_feriado) : 'confirmar' ?></div>
                            <div class="mb-1"><span class="mr-2 text-left-label">TAXA MADRUGADA R$ 800,</span> <?= (isset($orcamentoModel->taxa_madrugada) && is_numeric($orcamentoModel->taxa_madrugada) && $orcamentoModel->taxa_madrugada > 0) ? 'R$ '.formatarValor($orcamentoModel->taxa_madrugada) : 'confirmar' ?></div>
                            <div class="mb-1"><span class="mr-2 text-left-label">TAXA HORÁRIO ESPECIAL R$ 500,</span> <?= (isset($orcamentoModel->taxa_horario_especial) && is_numeric($orcamentoModel->taxa_horario_especial) && $orcamentoModel->taxa_horario_especial > 0) ? 'R$ '.formatarValor($orcamentoModel->taxa_horario_especial) : 'confirmar' ?></div>
                            <div class="mb-1"><span class="mr-2 text-left-label">TAXA HORA MARCADA R$ 200,</span> <?= (isset($orcamentoModel->taxa_hora_marcada) && is_numeric($orcamentoModel->taxa_hora_marcada) && $orcamentoModel->taxa_hora_marcada > 0) ? 'R$ '.formatarValor($orcamentoModel->taxa_hora_marcada) : 'confirmar' ?></div>
                            <div class="mb-1"><span class="mr-2 text-left-label">FRETE ELEVADOR</span> <?= formatarValor($orcamentoModel->frete_elevador ?? 'confirmar') ?></div>
                            <div class="mb-1"><span class="mr-2 text-left-label">FRETE ESCADAS</span> <?= formatarValor($orcamentoModel->frete_escadas ?? 'confirmar') ?></div>
                            <div class="mb-2"><strong><span class="mr-2 text-left-label">FRETE TÉRREO SEM ESCADAS</span> R$ <?= formatarValor($orcamentoModel->frete_terreo ?? 0, true) ?></strong></div>

                            <h4><strong>Total p/ PIX ou Depósito <span class="ml-2">R$ <?= formatarValor($orcamentoModel->valor_final ?? 0, true) ?></span></strong></h4>
                        </div>
                    </div>
                    <hr>
                    <!-- INFORMAÇÕES DO PIX E OBSERVAÇÃO FINAL -->
                    <div class="row mt-3">
                        <div class="col-12 text-center info-pix">
                            <strong>PIX SICREDI CNPJ 19.318.614 / 0001-44</strong><br>
                            <small>* Pedimos a gentileza de enviar por Whatsapp seu comprovante para baixar no estoque e garantir sua reserva</small>
                        </div>
                    </div>

                    <!-- Observações gerais do orçamento (se houver algo além dos textos fixos) -->
                    <?php if (!empty($orcamentoModel->observacoes)): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <hr>
                            <h5>Observações Adicionais do Orçamento:</h5>
                            <p><?= nl2br(htmlspecialchars($orcamentoModel->observacoes)) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div><!-- /.container-fluid -->
    </section>
</div>

<style>
    .card-orcamento-visual {
        font-family: Calibri, Arial, sans-serif; /* Fonte similar ao Excel */
        font-size: 11pt; /* Tamanho de fonte base */
        border: 1px solid #ccc;
    }
    .card-orcamento-visual .card-body {
        padding: 20px;
    }
    .card-orcamento-visual strong {
        font-weight: bold;
    }
    .card-orcamento-visual hr {
        border-top: 1px solid #000;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .obs-taxas-regras small, .obs-gerais small, .forma-pagamento small, .info-pix small {
        font-size: 9pt;
        color: #555;
        display: block; /* Para que o <br> funcione entre elas */
        line-height: 1.3;
    }
    .table-itens-orcamento th, .table-itens-orcamento td {
        padding: 0.25rem 0.5rem; /* Padding menor para condensar */
        vertical-align: middle;
        border: 1px solid #dee2e6; /* Bordas visíveis como no Excel */
    }
    .table-itens-orcamento thead th {
        background-color: #f8f9fa; /* Cor de fundo leve para cabeçalho */
        font-weight: bold;
    }
    .taxas-fretes div {
        font-size: 10pt;
        display: flex; /* Usar flexbox para alinhar texto à esquerda e valor à direita */
        justify-content: space-between; /* Distribui espaço entre os itens */
        align-items: center; /* Alinha verticalmente ao centro */
    }
    .taxas-fretes div span.text-left-label { /* Classe para o label da taxa/frete */
        text-align: left;
        margin-right: auto; /* Empurra o valor para a direita */
    }
     .taxas-fretes h4 strong span.ml-2 {
        display: inline-block;
    }

    @media print {
        body {
            font-size: 10pt; /* Ajuste para impressão */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .no-print, .main-sidebar, .content-header .btn, .alert {
            display: none !important;
        }
        .content-wrapper {
            margin-left: 0 !important;
            padding-top: 0 !important;
        }
        .content-header {
            display: none; /* Esconder o cabeçalho da página em si */
        }
        .card-orcamento-visual {
            box-shadow: none !important;
            border: none !important; /* Sem borda do card na impressão */
            margin-bottom: 0 !important;
        }
        .card-orcamento-visual .card-body {
            padding: 5px !important; /* Menor padding na impressão */
        }
        .table-itens-orcamento {
            width: 100% !important;
        }
         .table-itens-orcamento th, .table-itens-orcamento td {
            border: 1px solid #777 !important; /* Bordas mais escuras para impressão */
        }
        hr {
            border-top: 1px solid #777 !important;
        }
        .taxas-fretes div { /* Manter o layout flex na impressão */
            display: flex;
            justify-content: space-between;
        }
    }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>