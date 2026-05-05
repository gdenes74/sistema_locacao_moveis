<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../../models/Produto.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('formatarDataDiaSemana')) {
    function formatarDataDiaSemana(mixed $dataModel): string
    {
        if (empty($dataModel) || $dataModel === '0000-00-00' || $dataModel === '0000-00-00 00:00:00') {
            return '-';
        }
        try {
            $timestamp = strtotime($dataModel);
            if ($timestamp === false) {
                return '-';
            }
            $dias = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
            return date('d.m.y', $timestamp) . ' ' . $dias[(int)date('w', $timestamp)];
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('formatarTurnoHora')) {
    function formatarTurnoHora(mixed $turno, mixed $hora): string
    {
        $retorno = htmlspecialchars(trim($turno ?? ''), ENT_QUOTES, 'UTF-8');
        if (!empty($hora) && $hora !== '00:00:00') {
            try {
                $horaFormatada = date('H\H', strtotime($hora));
                $retorno .= ($retorno ? ' APROX. ' : 'APROX. ') . htmlspecialchars($horaFormatada, ENT_QUOTES, 'UTF-8');
            } catch (Exception $e) {
                // ignora hora inválida
            }
        }
        return trim($retorno) ?: '-';
    }
}

if (!function_exists('formatarValor')) {
    function formatarValor(mixed $valor, bool $mostrarZeroComoString = false): string
    {
        if (is_numeric($valor)) {
            if ((float)$valor == 0.0 && !$mostrarZeroComoString) {
                return '0,00';
            }
            return number_format((float)$valor, 2, ',', '.');
        }
        if (is_string($valor) && trim($valor) !== '') {
            return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
        }
        return $mostrarZeroComoString ? '0,00' : '-';
    }
}

if (!function_exists('formatarTelefone')) {
    function formatarTelefone(mixed $telefone): string
    {
        if (empty($telefone)) {
            return '-';
        }
        $telefone = preg_replace('/\D/', '', $telefone);
        if (strlen($telefone) == 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
        }
        if (strlen($telefone) == 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
        }
        return $telefone;
    }
}

if (!function_exists('normalizarTextoComparacaoMobel')) {
    function normalizarTextoComparacaoMobel(mixed $texto): string
    {
        $texto = trim((string)$texto);
        $texto = mb_strtoupper($texto, 'UTF-8');
        $map = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C'
        ];
        return strtr($texto, $map);
    }
}

if (!function_exists('montarNomeItemOrcamentoImpressao')) {
    function montarNomeItemOrcamentoImpressao(mixed $nomeItem, mixed $observacaoItem = ''): string
    {
        $nomeItem = trim((string)$nomeItem);
        $observacaoItem = trim((string)$observacaoItem);

        $nomeNormalizado = normalizarTextoComparacaoMobel($nomeItem);
        $obsNormalizada = normalizarTextoComparacaoMobel($observacaoItem);

        $obsEhUtil = $observacaoItem !== ''
            && $obsNormalizada !== 'A DEFINIR'
            && $obsNormalizada !== 'COR A DEFINIR'
            && $obsNormalizada !== '-';

        // Produto genérico usado quando a cor ainda não foi fechada.
        // Se a atendente preencher a observação da linha com "Marsala", a impressão fica:
        // PUFE FORRADO COR MARSALA
        if ($obsEhUtil && strpos($nomeNormalizado, 'PUFE FORRADO COR A DEFINIR') !== false) {
            return 'Pufe Forrado Cor ' . $observacaoItem;
        }

        return $nomeItem;
    }
}

if (!function_exists('exibirTaxaOrcamentoShow')) {
    function exibirTaxaOrcamentoShow(mixed $valor): string
    {
        if (is_numeric($valor) && (float)$valor > 0) {
            return 'R$ ' . formatarValor($valor);
        }
        return 'a confirmar';
    }
}


if (!function_exists('carregarComponentesProdutosCompostosOrcamentoShow')) {
    function carregarComponentesProdutosCompostosOrcamentoShow(PDO $conn, array $itens): array
    {
        $produtoIds = [];

        foreach ($itens as $item) {
            $produtoId = isset($item['produto_id']) ? (int)$item['produto_id'] : 0;
            if ($produtoId > 0) {
                $produtoIds[$produtoId] = true;
            }
        }

        if (empty($produtoIds)) {
            return [];
        }

        $ids = array_keys($produtoIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $query = "SELECT
                    pc.produto_pai_id,
                    pc.produto_filho_id,
                    pc.quantidade AS quantidade_componente,
                    filho.nome_produto,
                    filho.tipo_produto,
                    filho.controla_estoque,
                    filho.quantidade_total
                  FROM produto_composicao pc
                  INNER JOIN produtos filho ON filho.id = pc.produto_filho_id
                  WHERE pc.produto_pai_id IN ($placeholders)
                  ORDER BY pc.produto_pai_id ASC, filho.nome_produto ASC";

        try {
            $stmt = $conn->prepare($query);
            foreach ($ids as $idx => $produtoId) {
                $stmt->bindValue($idx + 1, (int)$produtoId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[orcamentos/show.php] Erro ao carregar componentes de produtos compostos: ' . $e->getMessage());
            return [];
        }

        $componentesPorProduto = [];
        foreach ($linhas as $linha) {
            $paiId = (int)$linha['produto_pai_id'];
            if (!isset($componentesPorProduto[$paiId])) {
                $componentesPorProduto[$paiId] = [];
            }
            $componentesPorProduto[$paiId][] = $linha;
        }

        return $componentesPorProduto;
    }
}

if (!function_exists('adicionarComponenteResumoProducaoOrcamentoShow')) {
    function adicionarComponenteResumoProducaoOrcamentoShow(array &$resumo, array $componente, float $quantidadeItem): void
    {
        $filhoId = isset($componente['produto_filho_id']) ? (int)$componente['produto_filho_id'] : 0;
        if ($filhoId <= 0) {
            return;
        }

        $quantidadeComponente = isset($componente['quantidade_componente']) ? (float)$componente['quantidade_componente'] : 1.0;
        if ($quantidadeComponente <= 0) {
            $quantidadeComponente = 1.0;
        }

        $quantidadeTotal = $quantidadeItem * $quantidadeComponente;

        if (!isset($resumo[$filhoId])) {
            $resumo[$filhoId] = [
                'nome_produto' => $componente['nome_produto'] ?? 'Componente',
                'tipo_produto' => $componente['tipo_produto'] ?? '',
                'quantidade' => 0.0,
            ];
        }

        $resumo[$filhoId]['quantidade'] += $quantidadeTotal;
    }
}

if (!function_exists('formatarQuantidadeProducaoOrcamentoShow')) {
    function formatarQuantidadeProducaoOrcamentoShow(float $quantidade): string
    {
        if (abs($quantidade - round($quantidade)) < 0.00001) {
            return number_format($quantidade, 0, ',', '.');
        }
        return number_format($quantidade, 2, ',', '.');
    }
}

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

$id = (int)$_GET['id'];
if (!$orcamentoModel->getById($id)) {
    $_SESSION['error_message'] = "Orçamento não encontrado (ID: {$id}).";
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos WHERE orcamento_id = ?");
$stmt->execute([$id]);
$ja_convertido = $stmt->fetchColumn() > 0;

$pedidoId = null;
$pedidoNumero = null;
if ($ja_convertido) {
    $stmt_pedido = $conn->prepare("SELECT id, numero FROM pedidos WHERE orcamento_id = ? LIMIT 1");
    $stmt_pedido->execute([$id]);
    $pedido_dados = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
    if ($pedido_dados) {
        $pedidoId = $pedido_dados['id'];
        $pedidoNumero = $pedido_dados['numero'];
    }

    if ($orcamentoModel->status !== 'convertido') {
        try {
            $stmt_update_status = $conn->prepare("UPDATE orcamentos SET status = 'convertido', updated_at = NOW() WHERE id = ?");
            $stmt_update_status->execute([$id]);
            if ($stmt_update_status->rowCount() > 0) {
                $orcamentoModel->status = 'convertido';
            }
        } catch (PDOException $e) {
            error_log("Erro ao sincronizar status do orçamento convertido no show.php: " . $e->getMessage());
        }
    }
}

$statusPermiteConversao = !in_array($orcamentoModel->status, ['convertido', 'recusado', 'expirado', 'cancelado'], true);
$orcamento_finalizado_ou_irreversivel = in_array($orcamentoModel->status, ['convertido', 'finalizado', 'recusado', 'expirado', 'cancelado'], true);

if (!empty($orcamentoModel->cliente_id)) {
    $clienteModel->getById($orcamentoModel->cliente_id);
}

$itens = $orcamentoModel->getItens($id);
$componentesPorProduto = is_array($itens) ? carregarComponentesProdutosCompostosOrcamentoShow($conn, $itens) : [];
$resumoComponentesProducao = [];

$inline_js_setup = "const ORCAMENTO_ID = " . $id . "; const BASE_URL = '" . BASE_URL . "';";
?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <section class="content-header no-print">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Orçamento #<?= htmlspecialchars($orcamentoModel->numero ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>

                    <?php if ($ja_convertido): ?>
                        <a href="../pedidos/show.php?id=<?= (int)$pedidoId ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-eye"></i> Ver Pedido #<?= htmlspecialchars($pedidoNumero ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <span class="badge badge-success ml-1"><i class="fas fa-check-circle"></i> CONVERTIDO</span>
                    <?php elseif ($orcamento_finalizado_ou_irreversivel): ?>
                        <span class="badge badge-<?=
                            $orcamentoModel->status === 'recusado' ? 'danger' :
                            ($orcamentoModel->status === 'expirado' ? 'warning' : 'secondary')
                        ?> ml-1">
                            <?= htmlspecialchars(strtoupper($orcamentoModel->status), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php else: ?>
                        <a href="edit.php?id=<?= htmlspecialchars($orcamentoModel->id ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <button type="button" class="btn btn-success btn-sm" id="btnGerarPedidoShow"
                                data-orcamento-id="<?= (int)$id ?>"
                                data-orcamento-numero="<?= htmlspecialchars($orcamentoModel->numero, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-check-circle"></i> Converter p/ Pedido
                        </button>
                    <?php endif; ?>

                    <button onclick="imprimirCliente();" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Imprimir p/ Cliente</button>
                    <button onclick="imprimirProducao();" class="btn btn-warning btn-sm"><i class="fas fa-tools"></i> Imprimir p/ Produção</button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="card card-orcamento-visual">
                <div class="card-body">
                    <div class="row cabecalho-empresa align-items-center">
                        <div class="col-2 logo-empresa">
                            <div class="logo-placeholder">
                                <img src="<?= BASE_URL ?>/assets/img/logo-mobel-festas.png" alt="Mobel Festas" class="img-fluid logo-mobel"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="logo-texto" style="display:none; text-align:center; padding:10px; border:2px solid #000; font-size:12px; background-color:#f8f9fa;">
                                    <div style="font-weight:bold; font-size:14px; color:#000;">MOBEL</div>
                                    <div style="font-size:12px; color:#000; margin-top:2px;">FESTAS</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-7 info-empresa text-center">
                            <h4 class="mb-1"><strong>MOBEL FESTAS</strong></h4>
                            <small>R. Marques do Alegrete, 179 - São João - Porto Alegre/RS - CEP: 91020-030</small><br>
                            <small>WhatsApp: (51) 99502-5886 • CNPJ: 19.318.614/0001-44</small>
                        </div>
                        <div class="col-3 text-right info-orcamento">
                            <h5 class="mb-0 titulo-documento"><strong>ORÇAMENTO</strong></h5>
                            <strong>Nº: <?= htmlspecialchars($orcamentoModel->numero ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong><br>
                            <small>Data: <?= isset($orcamentoModel->data_orcamento) ? date('d/m/Y', strtotime($orcamentoModel->data_orcamento)) : date('d/m/Y') ?></small>
                            <?php if (!empty($orcamentoModel->data_validade)): ?>
                                <br><small><strong>Válido até:</strong> <?= date('d/m/Y', strtotime($orcamentoModel->data_validade)) ?></small>
                            <?php endif; ?>
                            <?php if ($ja_convertido): ?>
                                <br><span class="badge badge-success mt-1"><i class="fas fa-check-circle"></i> CONVERTIDO EM PEDIDO</span>
                            <?php elseif ($orcamentoModel->status === 'recusado'): ?>
                                <br><span class="badge badge-danger mt-1"><i class="fas fa-times-circle"></i> RECUSADO</span>
                            <?php elseif ($orcamentoModel->status === 'expirado'): ?>
                                <br><span class="badge badge-warning mt-1"><i class="fas fa-clock"></i> EXPIRADO</span>
                            <?php elseif ($orcamentoModel->status === 'aprovado'): ?>
                                <br><span class="badge badge-info mt-1"><i class="fas fa-thumbs-up"></i> APROVADO</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="box-dados-principais mt-2">
                        <div class="row">
                            <div class="col-7">
                                <strong>Cliente:</strong> <?= htmlspecialchars($clienteModel->nome ?? 'Não informado', ENT_QUOTES, 'UTF-8') ?><br>
                                <?php if (!empty($clienteModel->telefone)): ?>
                                    <strong>Telefone:</strong> <?= formatarTelefone($clienteModel->telefone) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($clienteModel->cpf_cnpj)): ?>
                                    <strong>CPF/CNPJ:</strong> <?= htmlspecialchars($clienteModel->cpf_cnpj, ENT_QUOTES, 'UTF-8') ?><br>
                                <?php endif; ?>
                                <?php
                                $dataEventoCompleta = '-';
                                if (!empty($orcamentoModel->data_evento)) {
                                    $dataEventoCompleta = formatarDataDiaSemana($orcamentoModel->data_evento);
                                    if (!empty($orcamentoModel->hora_evento) && $orcamentoModel->hora_evento !== '00:00:00') {
                                        try {
                                            $dataEventoCompleta .= ' às ' . date('H\H', strtotime($orcamentoModel->hora_evento));
                                        } catch (Exception $e) {}
                                    }
                                }
                                ?>
                                <div class="box-data-evento">
                                    <span>DATA DO EVENTO</span>
                                    <strong><?= htmlspecialchars($dataEventoCompleta, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <strong>Local de Entrega:</strong> <?= htmlspecialchars($orcamentoModel->local_evento ?: '-', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="col-5 box-logistica">
                                <div class="linha-logistica destaque-amarelo">
                                    <strong>Data da Entrega:</strong>
                                    <?php
                                    $dataEntregaCompleta = '-';
                                    if (!empty($orcamentoModel->data_entrega)) {
                                        $dataEntregaCompleta = formatarDataDiaSemana($orcamentoModel->data_entrega);
                                        $turnoHoraEntrega = formatarTurnoHora($orcamentoModel->turno_entrega ?? null, $orcamentoModel->hora_entrega ?? null);
                                        if ($turnoHoraEntrega !== '-') {
                                            $dataEntregaCompleta .= ' - ' . $turnoHoraEntrega;
                                        }
                                    }
                                    echo $dataEntregaCompleta;
                                    ?>
                                </div>
                                <div class="linha-logistica destaque-amarelo mt-1">
                                    <strong>Data da Coleta:</strong>
                                    <?php
                                    $dataColetaCompleta = '-';
                                    if (!empty($orcamentoModel->data_devolucao_prevista)) {
                                        $dataColetaCompleta = formatarDataDiaSemana($orcamentoModel->data_devolucao_prevista);
                                        $turnoHoraColeta = formatarTurnoHora($orcamentoModel->turno_devolucao ?? null, $orcamentoModel->hora_devolucao ?? null);
                                        if ($turnoHoraColeta !== '-') {
                                            $dataColetaCompleta .= ' - ' . $turnoHoraColeta;
                                        }
                                    }
                                    echo $dataColetaCompleta;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row obs-taxas-regras mt-2 mb-2">
                        <div class="col-12">
                            <small># DOMINGO/FERIADO após as 8h e antes das 12h Taxa R$ 250,00</small>
                            <small># MADRUGADA após as 4:30h e antes das 8:30h Taxa R$ 800,00</small>
                            <small># HORÁRIO ESPECIAL após as 12h de sábado até as 23:30h de segunda a sábado Taxa R$ 500,00</small>
                            <small># HORA MARCADA SEGUNDA A SEXTA das 8:30h até as 17h e SÁBADO das 8:30h as 12h Taxa R$ 200,00</small>
                            <small># Infelizmente não dispomos de entregas ou coletas no período das 24h as 5h</small>
                        </div>
                    </div>

                    <div class="row linha-atendimento mb-2">
                        <div class="col-6">
                            Atend.: <?php
                            $nomeAtendente = 'LARA';
                            if (!empty($orcamentoModel->usuario_id)) {
                                try {
                                    $userStmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = :id");
                                    $userStmt->bindParam(':id', $orcamentoModel->usuario_id, PDO::PARAM_INT);
                                    $userStmt->execute();
                                    $nomeAtendenteDB = $userStmt->fetchColumn();
                                    if ($nomeAtendenteDB) {
                                        $nomeAtendente = $nomeAtendenteDB;
                                    }
                                } catch (PDOException $e) {
                                    error_log("Erro ao buscar nome do atendente: " . $e->getMessage());
                                }
                            }
                            echo htmlspecialchars($nomeAtendente, ENT_QUOTES, 'UTF-8');
                            ?>
                        </div>
                        <div class="col-6 text-right font-weight-bold">
                            ORÇAMENTO <?= htmlspecialchars(strtoupper($orcamentoModel->tipo ?? 'Locação'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered table-itens-orcamento">
                            <thead>
                                <tr class="text-center">
                                    <th style="width: 7%;">QTD</th>
                                    <th>DESCRIÇÃO DO PRODUTO/SERVIÇO</th>
                                    <th class="col-financeira" style="width: 12%;">UNITÁRIO</th>
                                    <?php
                                    $temDesconto = false;
                                    if (!empty($itens) && is_array($itens)) {
                                        foreach ($itens as $item) {
                                            if (isset($item['desconto']) && (float)$item['desconto'] > 0) {
                                                $temDesconto = true;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if ($temDesconto): ?>
                                        <th class="col-financeira" style="width: 12%;">DESCONTO</th>
                                    <?php endif; ?>
                                    <th class="col-financeira" style="width: 14%;">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $subtotalItensPIX = 0;
                                $totalItensExibidos = 0;
                                if (!empty($itens) && is_array($itens)):
                                    foreach ($itens as $item):
                                        $nomeItem = '';
                                        if (!empty($item['nome_produto_manual'])) {
                                            $nomeItem = $item['nome_produto_manual'];
                                        } elseif (!empty($item['nome_produto_catalogo'])) {
                                            $nomeItem = $item['nome_produto_catalogo'];
                                        } else {
                                            $nomeItem = 'Item não identificado';
                                        }

                                        if (($item['tipo_linha'] ?? '') === 'CABECALHO_SECAO'):
                                            $totalItensExibidos++;
                                            ?>
                                            <tr class="titulo-secao">
                                                <td colspan="<?= $temDesconto ? '5' : '4' ?>" class="font-weight-bold bg-light">
                                                    <span class="titulo-secao-texto"><?= htmlspecialchars(strtoupper($nomeItem), ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                            </tr>
                                        <?php else:
                                            $totalItensExibidos++;
                                            $quantidadeItem = isset($item['quantidade']) ? (float)$item['quantidade'] : 0;
                                            $precoUnitarioItem = isset($item['preco_unitario']) ? (float)$item['preco_unitario'] : 0;
                                            $descontoItem = isset($item['desconto']) ? (float)$item['desconto'] : 0;
                                            $itemSubtotal = isset($item['preco_final']) ? (float)$item['preco_final'] : 0;
                                            $subtotalItensPIX += $itemSubtotal;
                                            $observacaoItem = trim((string)($item['observacoes'] ?? ''));
                                            $nomeItemExibicao = montarNomeItemOrcamentoImpressao($nomeItem, $observacaoItem);
                                            $nomeNormalizado = normalizarTextoComparacaoMobel($nomeItem);
                                            $obsFoiConcatenada = $observacaoItem !== '' && strpos($nomeNormalizado, 'PUFE FORRADO COR A DEFINIR') !== false && normalizarTextoComparacaoMobel($observacaoItem) !== 'A DEFINIR';
                                            $produtoIdItem = isset($item['produto_id']) ? (int)$item['produto_id'] : 0;
                                            $componentesItem = $produtoIdItem > 0 && isset($componentesPorProduto[$produtoIdItem]) ? $componentesPorProduto[$produtoIdItem] : [];
                                            if (!empty($componentesItem)) {
                                                foreach ($componentesItem as $componenteItem) {
                                                    adicionarComponenteResumoProducaoOrcamentoShow($resumoComponentesProducao, $componenteItem, $quantidadeItem);
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td class="text-center qtd-item"><strong><?= htmlspecialchars(number_format($quantidadeItem, 0), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                <td class="descricao-item">
                                                    <?php if (!empty($item['foto_path'])): ?>
                                                        <img src="<?= BASE_URL ?>/<?= ltrim($item['foto_path'], '/') ?>"
                                                             alt="<?= htmlspecialchars($nomeItemExibicao, ENT_QUOTES, 'UTF-8') ?>"
                                                             class="produto-foto-impressao"
                                                             onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <div class="texto-produto-impressao">
                                                        <strong><?= htmlspecialchars(strtoupper($nomeItemExibicao), ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <?php if ($observacaoItem !== '' && !$obsFoiConcatenada): ?>
                                                            <br><small class="observacao-item"><?= htmlspecialchars($observacaoItem, ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php elseif ($observacaoItem !== '' && $obsFoiConcatenada): ?>
                                                            <br><small class="observacao-item observacao-producao">Obs.: <?= htmlspecialchars($observacaoItem, ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php endif; ?>

                                                        <?php if (!empty($componentesItem)): ?>
                                                            <div class="componentes-producao somente-producao">
                                                                <strong>Componentes para produção:</strong>
                                                                <?php foreach ($componentesItem as $comp): ?>
                                                                    <?php
                                                                    $qtdComp = isset($comp['quantidade_componente']) ? (float)$comp['quantidade_componente'] : 1.0;
                                                                    $qtdTotalComp = $quantidadeItem * ($qtdComp > 0 ? $qtdComp : 1.0);
                                                                    ?>
                                                                    <div>
                                                                        - <?= htmlspecialchars(formatarQuantidadeProducaoOrcamentoShow($qtdTotalComp), ENT_QUOTES, 'UTF-8') ?>
                                                                        <?= htmlspecialchars($comp['nome_produto'] ?? 'Componente', ENT_QUOTES, 'UTF-8') ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-right valor-col col-financeira">R$ <?= formatarValor($precoUnitarioItem) ?></td>
                                                <?php if ($temDesconto): ?>
                                                    <td class="text-right valor-col col-financeira"><?= $descontoItem > 0 ? 'R$ ' . formatarValor($descontoItem) : '-' ?></td>
                                                <?php endif; ?>
                                                <td class="text-right valor-col total-item col-financeira"><strong>R$ <?= formatarValor($itemSubtotal) ?></strong></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $temDesconto ? '5' : '4' ?>" class="text-center text-muted">Nenhum item adicionado.</td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $totalLinhasVisuais = 7;
                                $linhasAdicionais = $totalLinhasVisuais - $totalItensExibidos;
                                if ($linhasAdicionais < 0) {
                                    $linhasAdicionais = 0;
                                }
                                for ($i = 0; $i < $linhasAdicionais; $i++):
                                    ?>
                                    <tr class="linha-vazia-impressao">
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td class="col-financeira">&nbsp;</td>
                                        <?php if ($temDesconto): ?><td class="col-financeira">&nbsp;</td><?php endif; ?>
                                        <td class="col-financeira">&nbsp;</td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($resumoComponentesProducao)): ?>
                        <div class="resumo-componentes-producao somente-producao">
                            <div class="titulo-resumo-producao">RESUMO PARA PRODUÇÃO / SEPARAÇÃO</div>
                            <div class="row">
                                <?php foreach ($resumoComponentesProducao as $resumoComp): ?>
                                    <div class="col-6 item-resumo-producao">
                                        <strong><?= htmlspecialchars(formatarQuantidadeProducaoOrcamentoShow((float)$resumoComp['quantidade']), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?= htmlspecialchars($resumoComp['nome_produto'] ?? 'Componente', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row bloco-pos-itens mb-2">
                        <div class="col-7 obs-gerais">
                            <small># Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa</small><br>
                            <small>&nbsp;&nbsp;desde que não ultrapasse 10% do valor total contratado #</small><br>
                            <small>* Não Inclui Posicionamento dos Móveis no Local *</small>
                        </div>
                        <div class="col-5 text-right box-subtotal">
                            <strong>Sub total p/ PIX ou Depósito</strong>
                            <strong class="ml-3">R$ <?= formatarValor($subtotalItensPIX) ?></strong>
                        </div>
                    </div>

                    <div class="row bloco-pagamento-taxas">
                        <div class="col-7 forma-pagamento">
                            <strong>Forma de Pagamento:</strong><br>
                            <small>ENTRADA 30% PARA RESERVA EM PIX OU DEPÓSITO</small><br>
                            <small>SALDO EM PIX OU DEPÓSITO 7 DIAS ANTES EVENTO</small><br><br>
                            <small>* Consulte se há disponibilidade e</small><br>
                            <small>&nbsp;&nbsp;quais os preços de locação para pagamento</small><br>
                            <small>&nbsp;&nbsp;no cartão de crédito</small>
                        </div>
                        <div class="col-5 taxas-fretes text-right">
                            <div><span class="text-left-label">TAXA DOMINGO E FERIADO R$ 250,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_domingo_feriado ?? 0) ?></span></div>
                            <div><span class="text-left-label">TAXA MADRUGADA R$ 800,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_madrugada ?? 0) ?></span></div>
                            <div><span class="text-left-label">TAXA HORÁRIO ESPECIAL R$ 500,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_horario_especial ?? 0) ?></span></div>
                            <div><span class="text-left-label">TAXA HORA MARCADA R$ 200,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_hora_marcada ?? 0) ?></span></div>
                            <div><span class="text-left-label">FRETE ELEVADOR</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->frete_elevador ?? 0) ?></span></div>
                            <div><span class="text-left-label">FRETE ESCADAS</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->frete_escadas ?? 0) ?></span></div>
                            <div><span class="text-left-label"><strong>FRETE TÉRREO SEM ESCADAS</strong></span><span><strong>R$ <?= formatarValor($orcamentoModel->frete_terreo ?? 0, true) ?></strong></span></div>

                            <?php if (!empty($orcamentoModel->desconto) && $orcamentoModel->desconto > 0): ?>
                                <div class="text-danger"><span class="text-left-label">DESCONTO GERAL</span><span>- R$ <?= formatarValor($orcamentoModel->desconto) ?></span></div>
                            <?php endif; ?>

                            <div class="total-final-box mt-1">
                                <span class="text-left-label"><strong>Total p/ PIX ou Depósito</strong></span>
                                <span><strong>R$ <?= formatarValor($orcamentoModel->valor_final ?? 0, true) ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2 info-pix">
                        <div class="col-12 text-center">
                            <strong>PIX SICREDI CNPJ 19.318.614 / 0001-44</strong><br>
                            <small>* Pedimos a gentileza de enviar por Whatsapp seu comprovante para baixar no estoque e garantir sua reserva</small>
                        </div>
                    </div>

                    <?php if (!empty($orcamentoModel->observacoes)): ?>
                        <div class="row mt-2 observacoes-adicionais">
                            <div class="col-12">
                                <strong>Observações Adicionais:</strong>
                                <div><?= nl2br(htmlspecialchars($orcamentoModel->observacoes, ENT_QUOTES, 'UTF-8')) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($orcamentoModel->motivo_ajuste) && !empty($orcamentoModel->desconto) && $orcamentoModel->desconto > 0): ?>
                        <div class="row mt-1">
                            <div class="col-12">
                                <small><strong>Motivo do ajuste:</strong> <?= htmlspecialchars($orcamentoModel->motivo_ajuste, ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
    .card-orcamento-visual {
        font-family: Calibri, Arial, sans-serif;
        font-size: 11.5pt;
        border: 1px solid #bbb;
        color: #000;
    }

    .card-orcamento-visual .card-body {
        padding: 14px 18px;
    }

    .cabecalho-empresa {
        border-bottom: 2px solid #000;
        padding-bottom: 8px;
        margin-bottom: 8px;
    }

    .logo-placeholder {
        min-height: 54px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logo-mobel {
        max-height: 54px;
        max-width: 100%;
    }

    .info-empresa h4 {
        color: #000;
        font-size: 15pt;
        margin-bottom: 2px;
    }

    .info-empresa small,
    .info-orcamento small {
        color: #000;
        font-size: 9.5pt;
    }

    .titulo-documento {
        color: #000;
        font-size: 15pt;
    }

    .box-dados-principais {
        font-size: 11.5pt;
        line-height: 1.25;
    }

    .destaque-amarelo {
        background: #fff2cc;
        border: 1px solid #d6b656;
        padding: 4px 6px;
        font-weight: 700;
    }


    .box-data-evento {
        display: inline-block;
        margin: 4px 0 5px 0;
        padding: 5px 8px;
        background: #d9ead3;
        border: 1px solid #6aa84f;
        font-weight: 800;
        color: #000;
    }

    .box-data-evento span {
        display: block;
        font-size: 9.5pt;
        line-height: 1;
    }

    .box-data-evento strong {
        display: block;
        font-size: 13pt;
        line-height: 1.1;
    }

    .somente-producao {
        display: none;
    }

    .componentes-producao {
        clear: both;
        margin-top: 4px;
        padding: 4px 6px;
        border-left: 3px solid #666;
        background: #f7f7f7;
        font-size: 9.4pt;
        line-height: 1.18;
        color: #000;
    }

    .resumo-componentes-producao {
        margin: 6px 0 8px 0;
        padding: 6px 8px;
        border: 2px solid #000;
        background: #f2f2f2;
        color: #000;
        page-break-inside: avoid;
    }

    .titulo-resumo-producao {
        font-weight: 900;
        font-size: 12pt;
        margin-bottom: 4px;
        text-align: center;
        border-bottom: 1px solid #000;
        padding-bottom: 3px;
    }

    .item-resumo-producao {
        font-size: 10.5pt;
        line-height: 1.35;
    }

    .obs-taxas-regras {
        line-height: 1.15;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 5px 0;
    }

    .obs-taxas-regras small,
    .obs-gerais small,
    .forma-pagamento small,
    .info-pix small {
        font-size: 9.5pt;
        color: #000;
        display: block;
        line-height: 1.2;
    }

    .linha-atendimento {
        border-bottom: 1px solid #000;
        padding-bottom: 4px;
        font-size: 11pt;
    }

    .produto-foto-impressao {
        width: 46px !important;
        height: 46px !important;
        object-fit: cover !important;
        margin-right: 8px !important;
        border: 1px solid #777 !important;
        border-radius: 3px !important;
        vertical-align: middle !important;
        float: left !important;
    }

    .texto-produto-impressao {
        overflow: hidden;
        min-height: 46px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .table-itens-orcamento {
        width: 100%;
        margin-bottom: 0;
    }

    .table-itens-orcamento th,
    .table-itens-orcamento td {
        padding: 4px 6px;
        vertical-align: middle;
        border: 1px solid #777;
        font-size: 11.2pt;
        color: #000;
    }

    .table-itens-orcamento thead th {
        background-color: #f2f2f2;
        font-weight: 800;
        text-align: center;
        color: #000;
    }

    .descricao-item strong {
        font-weight: 800;
        letter-spacing: 0.01em;
    }

    .qtd-item {
        font-size: 12pt !important;
    }

    .valor-col {
        white-space: nowrap;
        font-size: 10.8pt !important;
    }

    .total-item {
        font-weight: 800;
    }

    .observacao-item {
        font-size: 10.2pt;
        color: #000 !important;
        font-weight: 700;
        font-style: normal;
        margin-top: 2px;
    }

    .titulo-secao td {
        background-color: #e7f1ff !important;
        font-weight: bold;
        font-size: 12pt;
        padding: 5px 6px !important;
        color: #000 !important;
    }

    .titulo-secao-texto {
        text-align: center !important;
        display: block;
        width: 100%;
    }

    .linha-vazia-impressao td {
        height: 22px;
        padding: 2px 6px;
    }

    .bloco-pos-itens {
        border-top: 1px solid #000;
        padding-top: 6px;
    }

    .box-subtotal {
        font-size: 11.5pt;
    }

    .bloco-pagamento-taxas {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 6px 0;
    }

    .taxas-fretes div {
        font-size: 10.5pt;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1px;
        color: #000;
    }

    .taxas-fretes div span:last-child {
        text-align: right !important;
        min-width: 80px;
        margin-left: 8px;
    }

    .taxas-fretes div span.text-left-label {
        text-align: left;
        margin-right: auto;
        color: #000;
    }

    .total-final-box {
        background: #fff2cc;
        border: 1px solid #d6b656;
        padding: 3px 5px;
        font-size: 12pt !important;
    }

    .info-pix {
        font-size: 10.5pt;
    }

    .observacoes-adicionais {
        border-top: 1px solid #999;
        padding-top: 5px;
        font-size: 10pt;
    }

    .badge {
        font-size: 0.8em;
        margin-left: 5px;
    }

    @media print {
        @page {
            size: A4;
            margin: 7mm;
        }

        html,
        body {
            font-size: 10.5pt;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #000 !important;
            background: #fff !important;
        }

        .no-print,
        .main-sidebar,
        .content-header .btn,
        .alert,
        footer,
        .main-footer {
            display: none !important;
        }

        .content-wrapper {
            margin-left: 0 !important;
            padding-top: 0 !important;
            background: #fff !important;
        }

        .content-header {
            display: none !important;
        }

        .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .card-orcamento-visual {
            box-shadow: none !important;
            border: none !important;
            margin-bottom: 0 !important;
            font-size: 10.7pt !important;
        }

        .card-orcamento-visual .card-body {
            padding: 0 !important;
        }

        .cabecalho-empresa {
            padding-bottom: 6px !important;
            margin-bottom: 6px !important;
        }

        .logo-mobel {
            max-height: 45px !important;
        }

        .info-empresa h4 {
            font-size: 13pt !important;
        }

        .info-empresa small,
        .info-orcamento small {
            font-size: 8.5pt !important;
        }

        .titulo-documento {
            font-size: 13pt !important;
        }

        .box-dados-principais {
            font-size: 10.3pt !important;
        }

        .obs-taxas-regras small,
        .obs-gerais small,
        .forma-pagamento small,
        .info-pix small {
            font-size: 8.5pt !important;
            line-height: 1.1 !important;
        }

        .table-itens-orcamento th,
        .table-itens-orcamento td {
            border: 1px solid #777 !important;
            font-size: 10.5pt !important;
            color: #000 !important;
            padding: 3px 5px !important;
        }

        .produto-foto-impressao {
            width: 39px !important;
            height: 39px !important;
            margin-right: 7px !important;
        }

        .texto-produto-impressao {
            min-height: 39px !important;
        }

        .observacao-item {
            font-size: 9.5pt !important;
            color: #000 !important;
            font-weight: 700 !important;
        }

        .impressao-cliente .observacao-item {
            display: none !important;
        }

        .impressao-producao .observacao-item {
            display: inline !important;
        }

        .linha-vazia-impressao td {
            height: 18px !important;
        }

        .taxas-fretes div {
            font-size: 9.3pt !important;
        }

        .total-final-box {
            font-size: 11pt !important;
        }

        .observacoes-adicionais {
            font-size: 8.8pt !important;
        }


        .impressao-producao .somente-producao {
            display: block !important;
        }

        .impressao-cliente .somente-producao {
            display: none !important;
        }

        .impressao-producao .obs-taxas-regras,
        .impressao-producao .bloco-pagamento-taxas,
        .impressao-producao .info-pix,
        .impressao-producao .observacoes-adicionais,
        .impressao-producao .bloco-pos-itens .obs-gerais {
            display: none !important;
        }

        .impressao-producao .bloco-pos-itens {
            border-top: 2px solid #000 !important;
            padding-top: 4px !important;
        }

        .impressao-producao .box-subtotal {
            display: none !important;
        }

        .impressao-producao .col-financeira {
            display: none !important;
        }

        .impressao-producao .componentes-producao {
            display: block !important;
            font-size: 9.2pt !important;
            margin-top: 3px !important;
            padding: 3px 5px !important;
        }

        .impressao-producao .resumo-componentes-producao {
            display: block !important;
        }

        .impressao-producao .box-data-evento,
        .impressao-producao .destaque-amarelo {
            font-size: 10.8pt !important;
        }
    }
</style>

<script>
function limparModoImpressaoOrcamento() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = ''; });
    document.body.classList.remove('impressao-cliente');
    document.body.classList.remove('impressao-producao');
    window.onafterprint = null;
}

function imprimirCliente() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = 'none'; });
    document.body.classList.remove('impressao-producao');
    document.body.classList.add('impressao-cliente');
    window.onafterprint = limparModoImpressaoOrcamento;
    window.print();
}

function imprimirProducao() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = 'inline'; });
    document.body.classList.remove('impressao-cliente');
    document.body.classList.add('impressao-producao');
    window.onafterprint = limparModoImpressaoOrcamento;
    window.print();
}

$(document).ready(function() {
    const $btnConverter = $('#btnGerarPedidoShow');
    if ($btnConverter.length === 0) {
        return;
    }

    $btnConverter.on('click', function() {
        const orcamentoId = $(this).data('orcamento-id');
        const orcamentoNumero = $(this).data('orcamento-numero');

        if ($(this).prop('disabled')) {
            return;
        }

        Swal.fire({
            title: 'Confirmar Conversão?',
            html: `
                <div class="text-left">
                    <p>Deseja realmente converter o orçamento <strong>#${orcamentoNumero}</strong> em um pedido confirmado?</p>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> <strong>O que acontecerá:</strong><br>
                        • Um novo pedido será criado<br>
                        • Todos os itens serão copiados<br>
                        • O orçamento será marcado como "convertido"<br>
                        • Esta ação não pode ser desfeita
                    </small>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> Sim, Converter',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            width: '500px'
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnGerarPedidoShow');
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Convertendo...');

                $.ajax({
                    url: `${BASE_URL}/views/orcamentos/converter_pedido.php`,
                    type: 'POST',
                    data: { orcamento_id: orcamentoId },
                    dataType: 'json',
                    timeout: 30000,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Sucesso!',
                                html: `
                                    <div class="text-center">
                                        <i class="fas fa-check-circle text-success" style="font-size: 3em;"></i>
                                        <p class="mt-3">${response.message}</p>
                                        <p><strong>Pedido #${response.pedido_numero || response.pedido_id}</strong></p>
                                    </div>
                                `,
                                icon: 'success',
                                confirmButtonText: '<i class="fas fa-edit"></i> Editar Pedido',
                                confirmButtonColor: '#007bff'
                            }).then(() => {
                                window.location.href = `${BASE_URL}/views/pedidos/edit.php?id=${response.pedido_id}&converted=1`;
                            });
                        } else {
                            Swal.fire({
                                title: 'Erro na Conversão',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: 'Entendi'
                            });
                            $btn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Converter p/ Pedido');
                        }
                    },
                    error: function(xhr, status) {
                        let errorMessage = 'Erro de comunicação com o servidor.';
                        if (status === 'timeout') {
                            errorMessage = 'Tempo limite excedido. Tente novamente.';
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            try {
                                const errorData = JSON.parse(xhr.responseText);
                                errorMessage = errorData.message || errorMessage;
                            } catch (e) {
                                errorMessage = 'Erro interno do servidor.';
                            }
                        }
                        Swal.fire({
                            title: 'Erro de Comunicação',
                            text: errorMessage,
                            icon: 'error',
                            confirmButtonText: 'Tentar Novamente'
                        });
                        $btn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Converter p/ Pedido');
                    }
                });
            }
        });
    });
});
</script>

<?php
$custom_js = <<<'JS'
console.log('Show.php carregado com visual compacto, componentes de produção e concatenação de cor.');
console.log('ORCAMENTO_ID:', typeof ORCAMENTO_ID !== 'undefined' ? ORCAMENTO_ID : 'não definido');
console.log('BASE_URL:', typeof BASE_URL !== 'undefined' ? BASE_URL : 'não definido');
JS;

include_once __DIR__ . '/../includes/footer.php';
?>
