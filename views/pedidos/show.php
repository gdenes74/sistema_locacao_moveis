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
    function formatarDataDiaSemana(mixed $dataModel): string
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
    function formatarTurnoHora(mixed $turno, mixed $hora): string
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
    function formatarValor(mixed $valor, bool $mostrarZeroComoString = false): string
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
    function formatarTelefone(mixed $telefone): string
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
    function formatarStatusPedido(mixed $situacao_pedido): array
    {
        $statusMap = [
            'confirmado' => ['class' => 'info', 'icon' => 'check-circle', 'text' => 'CONFIRMADO'],
            'em_separacao' => ['class' => 'primary', 'icon' => 'cogs', 'text' => 'EM SEPARAÇÃO'],
            'entregue' => ['class' => 'success', 'icon' => 'truck', 'text' => 'ENTREGUE'],
            'devolvido_parcial' => ['class' => 'warning', 'icon' => 'undo', 'text' => 'DEVOLVIDO PARCIAL'],
            'finalizado' => ['class' => 'success', 'icon' => 'check-double', 'text' => 'FINALIZADO'],
            'cancelado' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'CANCELADO']
        ];

        return $statusMap[$situacao_pedido] ?? ['class' => 'secondary', 'icon' => 'question', 'text' => strtoupper((string)$situacao_pedido)];
    }
}

if (!function_exists('formatarEtapaOperacionalPedidoShow')) {
    function formatarEtapaOperacionalPedidoShow(
        mixed $situacaoPedido,
        mixed $dataEntrega,
        mixed $dataEvento,
        mixed $dataDevolucaoPrevista
    ): array {
        $situacao = strtolower(trim((string)$situacaoPedido));

        // Situações manuais de exceção prevalecem sobre a leitura automática por datas.
        if ($situacao === 'cancelado') {
            return ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'CANCELADO'];
        }

        if ($situacao === 'finalizado') {
            return ['class' => 'success', 'icon' => 'check-double', 'text' => 'FINALIZADO'];
        }

        if ($situacao === 'devolvido_parcial') {
            return ['class' => 'warning', 'icon' => 'undo', 'text' => 'DEVOLVIDO PARCIAL'];
        }

        $hoje = new DateTimeImmutable('today');

        $parseData = function (mixed $valor): ?DateTimeImmutable {
            if (empty($valor) || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
                return null;
            }

            try {
                return new DateTimeImmutable(date('Y-m-d', strtotime((string)$valor)));
            } catch (Exception $e) {
                return null;
            }
        };

        $inicioOperacional = $parseData($dataEntrega) ?: $parseData($dataEvento);
        $fimOperacional = $parseData($dataDevolucaoPrevista);

        if ($fimOperacional && $hoje > $fimOperacional) {
            return ['class' => 'dark', 'icon' => 'calendar-check', 'text' => 'FINALIZADO PREVISTO'];
        }

        if ($inicioOperacional && $hoje >= $inicioOperacional) {
            return ['class' => 'success', 'icon' => 'truck-loading', 'text' => 'EM LOCAÇÃO'];
        }

        return ['class' => 'info', 'icon' => 'check-circle', 'text' => 'CONFIRMADO'];
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

if (!function_exists('montarNomeItemPedidoImpressao')) {
    function montarNomeItemPedidoImpressao(mixed $nomeItem, mixed $observacaoItem = ''): string
    {
        $nomeItem = trim((string)$nomeItem);
        $observacaoItem = trim((string)$observacaoItem);

        $nomeNormalizado = normalizarTextoComparacaoMobel($nomeItem);
        $obsNormalizada = normalizarTextoComparacaoMobel($observacaoItem);

        $obsEhUtil = $observacaoItem !== ''
            && !in_array($obsNormalizada, ['A DEFINIR', 'COR A DEFINIR', '-'], true);

        // Regra genérica para produtos compostos com cor informada na observação.
        // Exemplos:
        // Pufe Forrado Cor A Definir + Marsala => Pufe Forrado Cor Marsala
        // Almofada Capa Cor A Definir + Neon => Almofada Capa Cor Neon
        if ($obsEhUtil && strpos($nomeNormalizado, 'COR A DEFINIR') !== false) {
            $nomeComCor = preg_replace('/\bCOR\s+A\s+DEFINIR\b/iu', 'Cor ' . $observacaoItem, $nomeItem);
            return trim((string)($nomeComCor ?: $nomeItem));
        }

        return $nomeItem;
    }
}

if (!function_exists('observacaoItemUsadaComoCorPedidoShow')) {
    function observacaoItemUsadaComoCorPedidoShow(mixed $nomeItem, mixed $observacaoItem = ''): bool
    {
        $observacaoItem = trim((string)$observacaoItem);
        if ($observacaoItem === '') {
            return false;
        }

        $nomeNormalizado = normalizarTextoComparacaoMobel($nomeItem);
        $obsNormalizada = normalizarTextoComparacaoMobel($observacaoItem);

        return strpos($nomeNormalizado, 'COR A DEFINIR') !== false
            && !in_array($obsNormalizada, ['A DEFINIR', 'COR A DEFINIR', '-'], true);
    }
}

if (!function_exists('carregarComponentesProdutosCompostosPedidoShow')) {
    function carregarComponentesProdutosCompostosPedidoShow(PDO $conn, array $itens): array
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
            error_log('[pedidos/show.php] Erro ao carregar componentes de produtos compostos: ' . $e->getMessage());
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

if (!function_exists('montarNomeComponenteProducaoPedidoShow')) {
    function montarNomeComponenteProducaoPedidoShow(array $componente, mixed $observacaoItem = '', mixed $nomeItem = ''): string
    {
        $nomeComponente = trim((string)($componente['nome_produto'] ?? 'Componente'));
        $observacaoItem = trim((string)$observacaoItem);

        if ($nomeComponente === '') {
            $nomeComponente = 'Componente';
        }

        $tipoProduto = normalizarTextoComparacaoMobel($componente['tipo_produto'] ?? '');
        $nomeComponenteNormalizado = normalizarTextoComparacaoMobel($nomeComponente);

        // Serviço é sempre serviço, mesmo quando o nome contém "Capa".
        // Exemplo: Serviço Capa Almofada deve ir como serviço e receber a cor digitada.
        $ehServico = $tipoProduto === 'SERVICO'
            || strpos($nomeComponenteNormalizado, 'SERVICO') !== false;

        if ($ehServico && observacaoItemUsadaComoCorPedidoShow($nomeItem, $observacaoItem)) {
            $nomeComponenteSemDefinir = preg_replace('/\bCOR\s+A\s+DEFINIR\b/iu', '', $nomeComponente);
            return trim((string)($nomeComponenteSemDefinir ?: $nomeComponente) . ' Cor ' . $observacaoItem);
        }

        return $nomeComponente;
    }
}

if (!function_exists('adicionarComponenteResumoProducaoPedidoShow')) {
    function adicionarComponenteResumoProducaoPedidoShow(array &$resumo, array $componente, float $quantidadeItem, mixed $observacaoItem = '', mixed $nomeItem = ''): void
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
        $nomeComponenteProducao = montarNomeComponenteProducaoPedidoShow($componente, $observacaoItem, $nomeItem);
        $chaveResumo = $filhoId . '|' . normalizarTextoComparacaoMobel($nomeComponenteProducao);

        if (!isset($resumo[$chaveResumo])) {
            $resumo[$chaveResumo] = [
                'produto_filho_id' => $filhoId,
                'nome_produto' => $nomeComponenteProducao,
                'tipo_produto' => $componente['tipo_produto'] ?? '',
                'quantidade' => 0.0,
            ];
        }

        $resumo[$chaveResumo]['quantidade'] += $quantidadeTotal;
    }
}

if (!function_exists('formatarQuantidadeProducaoPedidoShow')) {
    function formatarQuantidadeProducaoPedidoShow(float $quantidade): string
    {
        if (abs($quantidade - round($quantidade)) < 0.00001) {
            return number_format($quantidade, 0, ',', '.');
        }
        return number_format($quantidade, 2, ',', '.');
    }
}

if (!function_exists('grupoProducaoPedidoShow')) {
    function grupoProducaoPedidoShow(mixed $nomeProduto, mixed $tipoProduto = ''): array
    {
        $nomeNormalizado = normalizarTextoComparacaoMobel($nomeProduto);
        $tipoNormalizado = normalizarTextoComparacaoMobel($tipoProduto);

        // Serviço vem antes de capa para não classificar "Serviço Capa Almofada" como CAPAS.
        if ($tipoNormalizado === 'SERVICO'
            || strpos($nomeNormalizado, 'SERVICO') !== false
            || strpos($nomeNormalizado, 'FORRACAO') !== false) {
            return ['chave' => 'servicos', 'titulo' => 'FORRAÇÕES / SERVIÇOS', 'ordem' => 20];
        }

        if (strpos($nomeNormalizado, 'CAPA') !== false) {
            return ['chave' => 'capas', 'titulo' => 'CAPAS', 'ordem' => 10];
        }

        if (strpos($nomeNormalizado, 'ESTRUTURA') !== false
            || strpos($nomeNormalizado, 'RECHEIO') !== false) {
            return ['chave' => 'estruturas', 'titulo' => 'ESTRUTURAS / RECHEIOS', 'ordem' => 30];
        }

        return ['chave' => 'outros', 'titulo' => 'OUTROS COMPONENTES', 'ordem' => 40];
    }
}

if (!function_exists('ordenarComponentesProducaoPedidoShow')) {
    function ordenarComponentesProducaoPedidoShow(array $componentes, mixed $observacaoItem = '', mixed $nomeItem = ''): array
    {
        usort($componentes, function (array $a, array $b) use ($observacaoItem, $nomeItem): int {
            $nomeA = montarNomeComponenteProducaoPedidoShow($a, $observacaoItem, $nomeItem);
            $nomeB = montarNomeComponenteProducaoPedidoShow($b, $observacaoItem, $nomeItem);
            $grupoA = grupoProducaoPedidoShow($nomeA, $a['tipo_produto'] ?? '');
            $grupoB = grupoProducaoPedidoShow($nomeB, $b['tipo_produto'] ?? '');

            if ($grupoA['ordem'] !== $grupoB['ordem']) {
                return $grupoA['ordem'] <=> $grupoB['ordem'];
            }

            return strnatcasecmp($nomeA, $nomeB);
        });

        return $componentes;
    }
}

if (!function_exists('agruparResumoProducaoPedidoShow')) {
    function agruparResumoProducaoPedidoShow(array $resumo): array
    {
        $grupos = [];

        foreach ($resumo as $itemResumo) {
            $grupo = grupoProducaoPedidoShow($itemResumo['nome_produto'] ?? '', $itemResumo['tipo_produto'] ?? '');
            $chave = $grupo['chave'];

            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [
                    'titulo' => $grupo['titulo'],
                    'ordem' => $grupo['ordem'],
                    'itens' => []
                ];
            }

            $grupos[$chave]['itens'][] = $itemResumo;
        }

        uasort($grupos, function (array $a, array $b): int {
            return $a['ordem'] <=> $b['ordem'];
        });

        foreach ($grupos as &$grupo) {
            usort($grupo['itens'], function (array $a, array $b): int {
                return strnatcasecmp((string)($a['nome_produto'] ?? ''), (string)($b['nome_produto'] ?? ''));
            });
        }
        unset($grupo);

        return $grupos;
    }
}

if (!function_exists('limparNomeArquivoPedidoShow')) {
    function limparNomeArquivoPedidoShow(mixed $texto): string
    {
        $texto = trim((string)$texto);
        $texto = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;
        return trim($texto) ?: 'SEM NOME';
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
$componentesPorProduto = is_array($itens) ? carregarComponentesProdutosCompostosPedidoShow($conn, $itens) : [];
$resumoComponentesProducao = [];

// ✅ CÁLCULO CORRETO DO SALDO
$valorFinal = floatval($pedidoModel->valor_final ?? 0);
$valorSinal = floatval($pedidoModel->valor_sinal ?? 0);
$valorComplemento = floatval($pedidoModel->valor_pago ?? 0);
$valorMultas = floatval($pedidoModel->valor_multas ?? 0);


$totalJaPago = $valorSinal + $valorComplemento;
$saldoBanco = $pedidoModel->saldo_calculado ?? '0';
$saldoBanco = str_replace(',', '.', $saldoBanco);
$saldoAPagar = floatval($saldoBanco);

// Etapa operacional do pedido: leitura automática por datas, preservando exceções manuais.
$statusInfo = formatarEtapaOperacionalPedidoShow(
    $pedidoModel->situacao_pedido ?? 'confirmado',
    $pedidoModel->data_entrega ?? null,
    $pedidoModel->data_evento ?? null,
    $pedidoModel->data_devolucao_prevista ?? null
);

// Nome sugerido para salvar/imprimir PDF
$dataEventoArquivo = 'sem-data';
if (!empty($pedidoModel->data_evento)) {
    $timestampEventoArquivo = strtotime($pedidoModel->data_evento);
    if ($timestampEventoArquivo !== false) {
        $dataEventoArquivo = date('d.m.y', $timestampEventoArquivo);
    }
}

$nomeClienteArquivo = limparNomeArquivoPedidoShow($clienteModel->nome ?? 'Cliente');
$numeroPedidoArquivo = limparNomeArquivoPedidoShow($pedidoModel->numero ?? $pedidoModel->id ?? $id);
$dataGeracaoDocumento = date('d/m/Y H:i');
$dataGeracaoArquivo = date('d.m.y H\\hi');
$nomeArquivoDocumento = limparNomeArquivoPedidoShow($dataEventoArquivo . ' - ' . $nomeClienteArquivo . ' - PEDIDO ' . $numeroPedidoArquivo);
$page_title = $nomeArquivoDocumento;

$nomeArquivoCliente = limparNomeArquivoPedidoShow($nomeArquivoDocumento . ' - CLIENTE - GERADO ' . $dataGeracaoArquivo);
$nomeArquivoProducao = limparNomeArquivoPedidoShow($nomeArquivoDocumento . ' - PRODUCAO - GERADO ' . $dataGeracaoArquivo);

// Define variáveis JavaScript para uso no footer
$inline_js_setup = "window.PEDIDO_ID = " . $id
    . "; window.NOME_ARQUIVO_DOCUMENTO = " . json_encode($nomeArquivoDocumento, JSON_UNESCAPED_UNICODE)
    . "; window.NOME_ARQUIVO_CLIENTE = " . json_encode($nomeArquivoCliente, JSON_UNESCAPED_UNICODE)
    . "; window.NOME_ARQUIVO_PRODUCAO = " . json_encode($nomeArquivoProducao, JSON_UNESCAPED_UNICODE) . ";";
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
                            
                            <!-- Etapa operacional do pedido -->
                            <br><small><strong>Etapa:</strong></small> <span class="badge badge-<?= $statusInfo['class'] ?> mt-1">
                                <i class="fas fa-<?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['text'] ?>
                            </span>
                            <br><small>Gerado em: <?= htmlspecialchars($dataGeracaoDocumento, ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                    <hr>

                    <!-- INFORMAÇÕES DO CLIENTE E EVENTO -->
                    <div class="row mb-2 bloco-cliente-logistica">
                        <div class="col-12">
                            <div><strong>Cliente:</strong> <?= htmlspecialchars($clienteModel->nome ?? 'Não informado') ?></div>
                            <?php if (!empty($clienteModel->telefone)): ?>
                                <div><strong>Telefone:</strong> <?= formatarTelefone($clienteModel->telefone) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($clienteModel->cpf_cnpj)): ?>
                                <div><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($clienteModel->cpf_cnpj) ?></div>
                            <?php endif; ?>

                            <?php
                            $dataEventoCompleta = '';
                            if (!empty($pedidoModel->data_evento)) {
                                $dataEventoCompleta = formatarDataDiaSemana($pedidoModel->data_evento);
                                if (!empty($pedidoModel->hora_evento) && $pedidoModel->hora_evento !== '00:00:00') {
                                    try {
                                        $horaEventoFormatada = date('H\H', strtotime($pedidoModel->hora_evento));
                                        $dataEventoCompleta .= ' às ' . $horaEventoFormatada;
                                    } catch (Exception $e) {}
                                }
                            } else {
                                $dataEventoCompleta = '-';
                            }

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
                            ?>

                            <div class="bloco-datas-operacionais">
                                <div class="linha-data-operacional data-evento-destaque"><span>DATA DO EVENTO</span><strong><?= htmlspecialchars($dataEventoCompleta) ?></strong></div>
                                <div class="linha-data-operacional data-entrega-destaque"><span>DATA DA ENTREGA</span><strong><?= htmlspecialchars($dataEntregaCompleta) ?></strong></div>
                                <div class="linha-data-operacional data-coleta-destaque"><span>DATA DA COLETA</span><strong><?= htmlspecialchars($dataColetaCompleta) ?></strong></div>
                            </div>

                            <div class="linha-local-entrega"><strong>Local de Entrega:</strong> <?= htmlspecialchars($pedidoModel->local_evento ?: '-') ?></div>
                        </div>
                    </div>

                    <!-- OBSERVAÇÕES DE TAXAS -->
                    <div class="obs-taxas-regras taxas-alerta-compactas">
                        <div># DOMINGO/FERIADO após as 8h e antes das 12h Taxa R$ 250,00</div>
                        <div># MADRUGADA após as 4:30h e antes das 8:30h Taxa R$ 800,00</div>
                        <div># HORÁRIO ESPECIAL após as 12h de sábado até as 23:30h de segunda a sábado Taxa R$ 500,00</div>
                        <div># HORA MARCADA SEGUNDA A SEXTA das 8:30h até as 17h e SÁBADO das 8:30h as 12h Taxa R$ 200,00</div>
                        <div># Infelizmente não dispomos de entregas ou coletas no período das 24h as 5h</div>
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
                                    <th class="col-financeira" style="width: 12%;">UNITÁRIO</th>
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
                                        <th class="col-financeira" style="width: 12%;">DESCONTO</th>
                                    <?php endif; ?>
                                    <th class="col-financeira" style="width: 15%;">TOTAL</th>
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
                                            // Produto, conjunto comercial ou item interno de conjunto
                                            $quantidadeItem = isset($item['quantidade']) ? (float)$item['quantidade'] : 0;
                                            $precoUnitarioItem = isset($item['preco_unitario']) ? (float)$item['preco_unitario'] : 0;
                                            $descontoItem = isset($item['desconto']) ? (float)$item['desconto'] : 0;
                                            $itemSubtotal = isset($item['preco_final']) ? (float)$item['preco_final'] : 0;

                                            $tipoLinhaItem = strtoupper(trim((string)($item['tipo_linha'] ?? 'PRODUTO')));
                                            $ehConjuntoPai = $tipoLinhaItem === 'CONJUNTO';
                                            $ehItemConjunto = $tipoLinhaItem === 'ITEM_CONJUNTO';
                                            $usaPrecoNoTotal = isset($item['usa_preco_no_total'])
                                                ? ((int)$item['usa_preco_no_total'] === 1)
                                                : !$ehItemConjunto;

                                            if ($usaPrecoNoTotal) {
                                                $subtotalItensPIX += $itemSubtotal;
                                            }

                                            $observacaoItem = trim((string)($item['observacoes'] ?? ''));
                                            $nomeItemExibicao = montarNomeItemPedidoImpressao($nomeItem, $observacaoItem);
                                            $obsFoiConcatenada = observacaoItemUsadaComoCorPedidoShow($nomeItem, $observacaoItem);
                                            $produtoIdItem = isset($item['produto_id']) ? (int)$item['produto_id'] : 0;
                                            $componentesItem = $produtoIdItem > 0 && isset($componentesPorProduto[$produtoIdItem]) ? $componentesPorProduto[$produtoIdItem] : [];
                                            if (!empty($componentesItem)) {
                                                foreach ($componentesItem as $componenteItem) {
                                                    adicionarComponenteResumoProducaoPedidoShow($resumoComponentesProducao, $componenteItem, $quantidadeItem, $observacaoItem, $nomeItem);
                                                }
                                            }
                                            $componentesItemOrdenados = !empty($componentesItem) ? ordenarComponentesProducaoPedidoShow($componentesItem, $observacaoItem, $nomeItem) : [];
                                            $classeLinhaItem = $ehConjuntoPai ? 'linha-conjunto-pai' : ($ehItemConjunto ? 'linha-conjunto-filho' : '');
                                            ?>
                                            <tr class="<?= $classeLinhaItem ?>">
                                                <td class="text-center qtd-item <?= $ehItemConjunto ? 'qtd-item-conjunto-filho' : '' ?>"><strong><?= htmlspecialchars(number_format($quantidadeItem, 0)) ?></strong></td>
                                                <td class="descricao-item <?= $ehItemConjunto ? 'descricao-item-conjunto-filho' : '' ?>">
                                                    <?php if ($ehItemConjunto): ?>
                                                        <span class="marcador-item-conjunto">↳</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['foto_path'])): ?>
                                                        <img src="<?= BASE_URL ?>/<?= ltrim($item['foto_path'], '/') ?>"
                                                            alt="<?= htmlspecialchars($nomeItemExibicao) ?>" class="produto-foto-impressao"
                                                            onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <div class="texto-produto-impressao">
                                                        <strong class="<?= $ehItemConjunto ? 'texto-filho-conjunto' : '' ?>"><?= htmlspecialchars(strtoupper($nomeItemExibicao)) ?></strong>
                                                        <?php if ($ehConjuntoPai): ?>
                                                            <br><small class="indicador-conjunto-pai">↓ itens internos do conjunto</small>
                                                        <?php endif; ?>
                                                        <?php if ($observacaoItem !== '' && !$obsFoiConcatenada): ?>
                                                            <br><small class="observacao-item text-muted" style="font-style: italic;"><?= htmlspecialchars($observacaoItem) ?></small>
                                                        <?php endif; ?>

                                                        <?php if (!empty($componentesItemOrdenados)): ?>
                                                            <div class="componentes-producao somente-producao">
                                                                <strong>Componentes para produção:</strong>
                                                                <?php foreach ($componentesItemOrdenados as $comp): ?>
                                                                    <?php
                                                                    $qtdComp = isset($comp['quantidade_componente']) ? (float)$comp['quantidade_componente'] : 1.0;
                                                                    $qtdTotalComp = $quantidadeItem * ($qtdComp > 0 ? $qtdComp : 1.0);
                                                                    $nomeComponenteProducao = montarNomeComponenteProducaoPedidoShow($comp, $observacaoItem, $nomeItem);
                                                                    ?>
                                                                    <div>
                                                                        - <?= htmlspecialchars(formatarQuantidadeProducaoPedidoShow($qtdTotalComp)) ?>
                                                                        <?= htmlspecialchars($nomeComponenteProducao) ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <?php if ($ehItemConjunto || !$usaPrecoNoTotal): ?>
                                                    <td class="text-right col-financeira valor-filho-conjunto">&nbsp;</td>
                                                    <?php if ($temDesconto): ?>
                                                        <td class="text-right col-financeira valor-filho-conjunto">&nbsp;</td>
                                                    <?php endif; ?>
                                                    <td class="text-right col-financeira valor-filho-conjunto">&nbsp;</td>
                                                <?php else: ?>
                                                    <td class="text-right col-financeira">R$ <?= formatarValor($precoUnitarioItem) ?></td>
                                                    <?php if ($temDesconto): ?>
                                                        <td class="text-right col-financeira">
                                                            <?= $descontoItem > 0 ? 'R$ ' . formatarValor($descontoItem) : '-' ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td class="text-right col-financeira"><strong>R$ <?= formatarValor($itemSubtotal) ?></strong></td>
                                                <?php endif; ?>
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
                                    <tr class="linha-branco-visual">
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
                            <table class="table table-sm table-bordered mb-0 tabela-resumo-producao">
                                <thead>
                                    <tr>
                                        <th style="width: 18%;">QTD</th>
                                        <th>COMPONENTE / SERVIÇO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (agruparResumoProducaoPedidoShow($resumoComponentesProducao) as $grupoResumo): ?>
                                        <tr class="grupo-resumo-producao">
                                            <td colspan="2"><?= htmlspecialchars($grupoResumo['titulo']) ?></td>
                                        </tr>
                                        <?php foreach ($grupoResumo['itens'] as $resumoComp): ?>
                                            <tr>
                                                <td class="text-center font-weight-bold">
                                                    <?= htmlspecialchars(formatarQuantidadeProducaoPedidoShow((float)$resumoComp['quantidade'])) ?>
                                                </td>
                                                <td><?= htmlspecialchars($resumoComp['nome_produto'] ?? 'Componente') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

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
                            function exibirTaxa(mixed $valor, mixed $valorPadrao = null): string
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
                                <span>R$ <?= formatarValor($valorFinal, true) ?></span>
                            </strong></h4>
                        </div>
                    </div>
                    <hr>

                    <!-- CONTROLE FINANCEIRO DO PEDIDO EM LINHA ÚNICA -->
                    <div class="financeiro-linha-unica mb-2">
                        <div class="financeiro-linha-titulo">
                            <i class="fas fa-money-bill-wave"></i> Controle Financeiro do Pedido
                        </div>
                        <div class="financeiro-linha-conteudo">
                            <span><strong>Valor Total:</strong> <b class="text-primary">R$ <?= formatarValor($valorFinal, true) ?></b></span>
                            <?php if (!empty($valorSinal) && $valorSinal > 0): ?>
                                <span><strong>Sinal:</strong> <b class="text-info">R$ <?= formatarValor($valorSinal, true) ?></b><?php if (!empty($pedidoModel->data_pagamento_sinal)): ?> <small>(<?= date('d/m/Y', strtotime($pedidoModel->data_pagamento_sinal)) ?>)</small><?php endif; ?></span>
                            <?php endif; ?>
                            <span><strong>Complemento:</strong> <b class="text-success">R$ <?= formatarValor($valorComplemento, true) ?></b><?php if (!empty($pedidoModel->data_pagamento_final)): ?> <small>(<?= date('d/m/Y', strtotime($pedidoModel->data_pagamento_final)) ?>)</small><?php endif; ?></span>
                            <?php if (!empty($valorMultas) && $valorMultas > 0): ?>
                                <span><strong>Multas/Extras:</strong> <b class="text-warning">R$ <?= formatarValor($valorMultas, true) ?></b></span>
                            <?php endif; ?>
                            <span><strong>Saldo:</strong> <b class="<?= $saldoAPagar > 0 ? 'text-danger' : 'text-success' ?>">R$ <?= formatarValor($saldoAPagar, true) ?></b></span>
                            <span><strong>Status:</strong>
                                <?php if ($saldoAPagar <= 0): ?>
                                    <b class="text-success">PAGO</b>
                                <?php elseif ($totalJaPago > 0): ?>
                                    <b class="text-warning">PARCIAL</b>
                                <?php else: ?>
                                    <b class="text-danger">PENDENTE</b>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($pedidoModel->condicoes_pagamento)): ?>
                            <div class="financeiro-linha-condicoes somente-tela">
                                <strong>Condições:</strong> <?= nl2br(htmlspecialchars($pedidoModel->condicoes_pagamento)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- INFORMAÇÕES DO PIX -->
                    <div class="row mt-3">
                        <div class="col-12 text-center info-pix">
                            <strong>PIX SICREDI CNPJ 19.318.614 / 0001-44</strong><br>
                            <small>* Pedimos a gentileza de enviar por Whatsapp seu comprovante para baixar no estoque e garantir sua reserva</small>
                        </div>
                    </div>

                    <!-- Observações adicionais -->
                    <?php if (!empty($pedidoModel->observacoes)): ?>
                        <div class="row mt-4 observacoes-adicionais">
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
        width: 46px !important;
        height: 46px !important;
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

    .card-financeiro-compacto {
        border: 1px solid #777;
        box-shadow: none;
        background: #fff;
    }

    .financeiro-compacto-titulo {
        font-weight: 800;
        font-size: 11pt;
        color: #000;
        border-bottom: 1px solid #999;
        padding-bottom: 3px;
        margin-bottom: 4px;
    }

    .financeiro-compacto-linha {
        margin-left: -4px;
        margin-right: -4px;
    }

    .financeiro-compacto-item {
        padding: 3px 4px;
        border-right: 1px solid #ddd;
        min-height: 38px;
    }

    .financeiro-compacto-item:last-child {
        border-right: none;
    }

    .financeiro-compacto-item small {
        display: block;
        font-size: 7.8pt;
        line-height: 1.05;
        color: #555;
        font-weight: 700;
    }

    .financeiro-compacto-item strong {
        display: block;
        font-size: 10.4pt;
        line-height: 1.15;
        font-weight: 900;
    }

    .financeiro-compacto-item span {
        display: block;
        font-size: 7.8pt;
        color: #555;
        line-height: 1.05;
    }

    .financeiro-compacto-condicoes {
        border-top: 1px solid #ddd;
        padding-top: 4px;
        font-size: 8.8pt;
        line-height: 1.15;
        color: #000;
    }


    .financeiro-linha-unica {
        border: 1px solid #777;
        background: #fff;
        color: #000;
        padding: 4px 6px;
        page-break-inside: avoid;
    }

    .financeiro-linha-titulo {
        display: inline-block;
        font-weight: 900;
        font-size: 9.4pt;
        margin-right: 8px;
        color: #000;
    }

    .financeiro-linha-conteudo {
        display: inline;
        font-size: 9.2pt;
        line-height: 1.25;
    }

    .financeiro-linha-conteudo span {
        display: inline-block;
        margin-right: 9px;
        white-space: nowrap;
    }

    .financeiro-linha-conteudo b {
        font-weight: 900;
    }

    .financeiro-linha-conteudo small {
        font-size: 7.6pt;
        color: #555;
    }

    .financeiro-linha-condicoes {
        margin-top: 3px;
        border-top: 1px solid #ddd;
        padding-top: 3px;
        font-size: 8.2pt;
        line-height: 1.15;
    }

    .somente-tela {
        display: block;
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

        .card-financeiro-compacto {
            border: 1px solid #777 !important;
            page-break-inside: avoid !important;
            margin-top: 3px !important;
            margin-bottom: 5px !important;
        }

        .card-financeiro-compacto .card-body {
            padding: 4px 6px !important;
        }

        .financeiro-compacto-titulo {
            font-size: 8.8pt !important;
            padding-bottom: 2px !important;
            margin-bottom: 3px !important;
        }

        .financeiro-compacto-item {
            padding: 2px 3px !important;
            min-height: 28px !important;
        }

        .financeiro-compacto-item small {
            font-size: 6.8pt !important;
        }

        .financeiro-compacto-item strong {
            font-size: 8.4pt !important;
        }

        .financeiro-compacto-item span,
        .financeiro-compacto-condicoes {
            font-size: 6.8pt !important;
        }


        .financeiro-linha-unica {
            padding: 3px 5px !important;
            margin-top: 3px !important;
            margin-bottom: 3px !important;
            border: 1px solid #777 !important;
        }

        .financeiro-linha-titulo {
            font-size: 9pt !important;
            margin-right: 5px !important;
        }

        .financeiro-linha-conteudo {
            font-size: 8.7pt !important;
            line-height: 1.1 !important;
        }

        .financeiro-linha-conteudo span {
            margin-right: 5px !important;
        }

        .financeiro-linha-conteudo small {
            font-size: 7.2pt !important;
        }

        .impressao-cliente .financeiro-linha-condicoes,
        .impressao-producao .financeiro-linha-unica {
            display: none !important;
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


    .bloco-cliente-logistica {
        font-size: 12.2pt;
        line-height: 1.28;
        color: #000;
    }

    .bloco-datas-operacionais {
        margin-top: 5px;
        margin-bottom: 4px;
        border: 2px solid #000;
        page-break-inside: avoid;
    }

    .linha-data-operacional {
        display: flex;
        align-items: center;
        border-bottom: 1px solid #777;
        min-height: 28px;
        color: #000;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .linha-data-operacional:last-child {
        border-bottom: none;
    }

    .linha-data-operacional span {
        width: 165px;
        min-width: 165px;
        padding: 4px 8px;
        font-weight: 900;
        letter-spacing: 0.02em;
        background: #e9ecef;
        border-right: 1px solid #777;
    }

    .linha-data-operacional strong {
        flex: 1;
        padding: 4px 8px;
        font-size: 12.8pt;
        font-weight: 900;
    }

    .data-evento-destaque strong { background: #fff7d6; }
    .data-entrega-destaque strong { background: #e8f4ff; }
    .data-coleta-destaque strong { background: #ffdede; }

    .linha-local-entrega {
        font-size: 12pt;
        line-height: 1.2;
        margin-top: 2px;
    }

    .taxas-alerta-compactas {
        margin: 4px 0 5px 0;
        padding: 4px 6px;
        border: 1px solid #999;
        background: #fff;
        color: #000;
        font-size: 9.2pt;
        line-height: 1.14;
    }

    .taxas-alerta-compactas div {
        margin: 0;
        padding: 1px 0;
    }

    .card-pedido-visual {
        font-family: Calibri, Arial, sans-serif;
        font-size: 12.6pt;
        border: 1px solid #ccc;
    }

    .card-pedido-visual .card-body {
        padding: 14px;
    }

    .cabecalho-empresa {
        border-bottom: 2px solid #000;
        padding-bottom: 8px;
        margin-bottom: 8px;
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
        font-size: 10.6pt;
        color: #000;
        display: block;
        line-height: 1.22;
    }

    .table-itens-pedido th, .table-itens-pedido td {
        padding: 0.24rem 0.45rem;
        vertical-align: middle;
        border: 1px solid #dee2e6;
        font-size: 12.1pt;
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
        font-size: 11.5pt;
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

    .linha-conjunto-pai td {
        background: #f8fbff !important;
        border-top: 2px solid #000 !important;
    }

    .linha-conjunto-pai .descricao-item strong {
        font-weight: 900;
    }

    .indicador-conjunto-pai {
        display: inline-block;
        margin-top: 2px;
        color: #0b5ed7;
        font-size: 8.8pt;
        font-weight: 800;
        letter-spacing: 0.01em;
    }

    .linha-conjunto-filho td {
        background: #ffffff !important;
        border-top-color: #aaa !important;
    }

    .linha-conjunto-filho .qtd-item {
        font-size: 9.8pt !important;
        color: #0b5ed7 !important;
    }

    .linha-conjunto-filho .valor-col,
    .valor-filho-conjunto {
        color: transparent !important;
    }

    .descricao-item-conjunto-filho {
        padding-left: 14px !important;
    }

    .marcador-item-conjunto {
        float: left;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 39px;
        margin-right: 3px;
        color: #0b5ed7;
        font-weight: 900;
        font-size: 14pt;
        line-height: 1;
    }

    .texto-filho-conjunto {
        font-weight: 700 !important;
        font-size: 10.7pt;
        color: #0b5ed7 !important;
    }

    .texto-produto-impressao {
        overflow: hidden;
        min-height: 46px;
        display: flex;
        flex-direction: column;
        justify-content: center;
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

    .tabela-resumo-producao th,
    .tabela-resumo-producao td {
        border: 1px solid #777 !important;
        color: #000 !important;
        font-size: 10.5pt;
        padding: 3px 6px !important;
    }

    .grupo-resumo-producao td {
        background: #e9ecef !important;
        font-weight: 900;
        text-transform: uppercase;
        text-align: left;
    }

    @page {
        size: A4;
        margin: 10mm;
    }

    @media print {

        @page {
            size: A4;
            margin: 7mm 7mm 7mm 7mm;
        }

        .bloco-cliente-logistica {
            font-size: 11.6pt !important;
            line-height: 1.18 !important;
            margin-bottom: 3px !important;
        }

        .bloco-datas-operacionais {
            margin-top: 3px !important;
            margin-bottom: 3px !important;
            border: 2px solid #000 !important;
        }

        .linha-data-operacional {
            min-height: 25px !important;
            border-bottom: 1px solid #777 !important;
        }

        .linha-data-operacional span {
            width: 145px !important;
            min-width: 145px !important;
            padding: 3px 6px !important;
            font-size: 10.4pt !important;
        }

        .linha-data-operacional strong {
            padding: 3px 6px !important;
            font-size: 12.4pt !important;
        }

        .linha-local-entrega {
            font-size: 11.4pt !important;
            line-height: 1.15 !important;
        }

        .taxas-alerta-compactas {
            font-size: 8.1pt !important;
            line-height: 1.04 !important;
            padding: 2px 4px !important;
            margin: 2px 0 3px 0 !important;
            border: 1px solid #999 !important;
        }

        .taxas-alerta-compactas div {
            padding: 0 !important;
        }

        .linha-branco-visual {
            display: none !important;
        }

        body {
            font-size: 11.5pt;
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

            
    .bloco-cliente-logistica {
        font-size: 12.2pt;
        line-height: 1.28;
        color: #000;
    }

    .bloco-datas-operacionais {
        margin-top: 5px;
        margin-bottom: 4px;
        border: 2px solid #000;
        page-break-inside: avoid;
    }

    .linha-data-operacional {
        display: flex;
        align-items: center;
        border-bottom: 1px solid #777;
        min-height: 28px;
        color: #000;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .linha-data-operacional:last-child {
        border-bottom: none;
    }

    .linha-data-operacional span {
        width: 165px;
        min-width: 165px;
        padding: 4px 8px;
        font-weight: 900;
        letter-spacing: 0.02em;
        background: #e9ecef;
        border-right: 1px solid #777;
    }

    .linha-data-operacional strong {
        flex: 1;
        padding: 4px 8px;
        font-size: 12.8pt;
        font-weight: 900;
    }

    .data-evento-destaque strong { background: #fff7d6; }
    .data-entrega-destaque strong { background: #e8f4ff; }
    .data-coleta-destaque strong { background: #ffdede; }

    .linha-local-entrega {
        font-size: 12pt;
        line-height: 1.2;
        margin-top: 2px;
    }

    .taxas-alerta-compactas {
        margin: 4px 0 5px 0;
        padding: 4px 6px;
        border: 1px solid #999;
        background: #fff;
        color: #000;
        font-size: 9.2pt;
        line-height: 1.14;
    }

    .taxas-alerta-compactas div {
        margin: 0;
        padding: 1px 0;
    }

    .card-pedido-visual {
            box-shadow: none !important;
            border: none !important;
            margin-bottom: 0 !important;
        }

        .card-pedido-visual .card-body {
            padding: 5px !important;
        }

        .table-itens-pedido {
            width: 100% !important;
        }

        .table-itens-pedido th, .table-itens-pedido td {
            border: 1px solid #777 !important;
            font-size: 12pt !important;
            padding: 3px 5px !important;
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
            font-size: 12.5pt !important;
            color: #000 !important;
        }

        .cabecalho-empresa {
            padding-bottom: 5px !important;
            margin-bottom: 5px !important;
        }

        .card-pedido-visual hr {
            margin-top: 0.25rem !important;
            margin-bottom: 0.25rem !important;
        }

        /* Oculta observações na impressão para cliente */
        .impressao-cliente .observacao-item {
            display: none !important;
        }

        .linha-conjunto-pai td {
            background: #f8fbff !important;
            border-top: 2px solid #000 !important;
        }

        .indicador-conjunto-pai {
            font-size: 7.8pt !important;
            color: #0b5ed7 !important;
            font-weight: 700 !important;
        }

        .linha-conjunto-filho td {
            background: #fff !important;
        }

        .descricao-item-conjunto-filho {
            padding-left: 12px !important;
        }

        .marcador-item-conjunto {
            height: 34px !important;
            font-size: 12pt !important;
            color: #0b5ed7 !important;
        }

        .texto-filho-conjunto {
            font-size: 10.2pt !important;
            font-weight: 700 !important;
            color: #0b5ed7 !important;
        }

        .linha-conjunto-filho .qtd-item {
            font-size: 9.8pt !important;
            color: #0b5ed7 !important;
        }

        .linha-conjunto-filho .produto-foto-impressao {
            width: 34px !important;
            height: 34px !important;
            margin-right: 6px !important;
        }

        .valor-filho-conjunto {
            color: transparent !important;
        }

        .impressao-producao .somente-producao {
            display: block !important;
        }

        .impressao-cliente .somente-producao {
            display: none !important;
        }

        .impressao-producao .card-financeiro-barra,
        .impressao-producao .obs-taxas-regras,
        .impressao-producao .bloco-pagamento-taxas,
        .impressao-producao .info-pix,
        .impressao-producao .forma-pagamento,
        .impressao-producao .taxas-fretes,
        .impressao-producao .obs-gerais,
        .impressao-producao .observacoes-adicionais,
        .impressao-producao .row.mb-3:has(.obs-gerais) {
            display: none !important;
        }

        .impressao-producao .col-financeira {
            display: none !important;
        }

        .impressao-producao .componentes-producao {
            display: block !important;
            font-size: 10pt !important;
            margin-top: 3px !important;
            padding: 3px 5px !important;
        }

        .impressao-producao .resumo-componentes-producao {
            display: block !important;
        }
    }

/* === AJUSTE MOBEL 04/06/2026 - impressão mais legível e compacta === */
.bloco-datas-operacionais {
    display: grid !important;
    grid-template-columns: 190px 1fr !important;
    border: 2px solid #000 !important;
    margin-top: 6px !important;
    margin-bottom: 5px !important;
}

.linha-data-operacional {
    display: contents !important;
}

.linha-data-operacional span,
.linha-data-operacional strong {
    display: flex !important;
    align-items: center !important;
    min-height: 32px !important;
    border-bottom: 1px solid #555 !important;
    color: #000 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

.linha-data-operacional span {
    width: auto !important;
    min-width: 0 !important;
    padding: 5px 9px !important;
    border-right: 1px solid #555 !important;
    font-size: 12.6pt !important;
    font-weight: 900 !important;
    letter-spacing: 0.02em !important;
    background: #e9ecef !important;
}

.linha-data-operacional strong {
    padding: 5px 10px !important;
    font-size: 14.2pt !important;
    font-weight: 900 !important;
    line-height: 1.12 !important;
}

.linha-data-operacional:nth-of-type(3) span,
.linha-data-operacional:nth-of-type(3) strong {
    border-bottom: none !important;
}

.data-evento-destaque span,
.data-evento-destaque strong {
    background: #fff3b0 !important;
}

.data-entrega-destaque span,
.data-entrega-destaque strong {
    background: #dceeff !important;
}

.data-coleta-destaque span,
.data-coleta-destaque strong {
    background: #ffdede !important;
    color: #7a0000 !important;
    border-bottom-color: #9b1c1c !important;
}

.linha-local-entrega {
    font-size: 12.2pt !important;
    font-weight: 700 !important;
    margin: 3px 0 4px 0 !important;
}

/* Regras/taxas em duas colunas, para não roubar uma faixa larga em branco */
.taxas-alerta-compactas {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 1px 14px !important;
    padding: 4px 7px !important;
    margin: 4px 0 5px 0 !important;
    font-size: 10.4pt !important;
    line-height: 1.12 !important;
}

.taxas-alerta-compactas div {
    padding: 0 !important;
    margin: 0 !important;
    font-weight: 700 !important;
}

.taxas-alerta-compactas div:last-child {
    grid-column: 1 / -1 !important;
}

/* Controle financeiro com itens mais distribuídos e legíveis */
.financeiro-linha-unica {
    padding: 6px 8px !important;
    border: 1.5px solid #555 !important;
}

.financeiro-linha-titulo {
    display: block !important;
    font-size: 10.8pt !important;
    margin: 0 0 3px 0 !important;
}

.financeiro-linha-conteudo {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
    width: 100% !important;
    font-size: 11.2pt !important;
    line-height: 1.15 !important;
}

.financeiro-linha-conteudo span {
    margin-right: 0 !important;
    white-space: nowrap !important;
}

.financeiro-linha-condicoes {
    font-size: 10pt !important;
    line-height: 1.18 !important;
}

/* Leitura geral */
.card-pedido-visual {
    font-size: 13pt !important;
}

.table-itens-pedido th,
.table-itens-pedido td {
    font-size: 12.8pt !important;
    padding: 0.30rem 0.50rem !important;
}

.texto-filho-conjunto {
    font-size: 11.8pt !important;
}

.linha-conjunto-filho .qtd-item {
    font-size: 11.2pt !important;
}

.obs-taxas-regras small,
.obs-gerais small,
.forma-pagamento small,
.info-pix small {
    font-size: 11.2pt !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 6mm 7mm 6mm 7mm;
    }

    body {
        font-size: 11.6pt !important;
    }

    .main-footer,
    footer {
        display: none !important;
    }

    .content-wrapper,
    .container-fluid,
    .content,
    .card-pedido-visual,
    .card-pedido-visual .card-body {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .cabecalho-empresa {
        padding-bottom: 5px !important;
        margin-bottom: 5px !important;
    }

    .info-empresa h4 {
        font-size: 13.2pt !important;
    }

    .info-empresa small,
    .info-pedido,
    .info-pedido small {
        font-size: 9.6pt !important;
        line-height: 1.15 !important;
    }

    .bloco-cliente-logistica {
        font-size: 11.7pt !important;
        line-height: 1.14 !important;
    }

    .bloco-datas-operacionais {
        grid-template-columns: 190px 1fr !important;
        margin-top: 3px !important;
        margin-bottom: 3px !important;
        border: 2px solid #000 !important;
    }

    .linha-data-operacional span,
    .linha-data-operacional strong {
        min-height: 27px !important;
    }

    .linha-data-operacional span {
        font-size: 11.8pt !important;
        padding: 3px 7px !important;
    }

    .linha-data-operacional strong {
        font-size: 13.4pt !important;
        padding: 3px 8px !important;
    }

    .linha-local-entrega {
        font-size: 11.4pt !important;
        margin: 2px 0 3px 0 !important;
    }

    .taxas-alerta-compactas {
        grid-template-columns: 1fr 1fr !important;
        gap: 0 12px !important;
        padding: 3px 6px !important;
        font-size: 9.2pt !important;
        line-height: 1.05 !important;
        margin: 3px 0 4px 0 !important;
    }

    .taxas-alerta-compactas div {
        font-weight: 700 !important;
    }

    .row.mb-3 {
        margin-bottom: 0.35rem !important;
    }

    .table-itens-pedido th,
    .table-itens-pedido td {
        font-size: 11.7pt !important;
        padding: 0.24rem 0.40rem !important;
        line-height: 1.12 !important;
    }

    .produto-foto-impressao {
        width: 38px !important;
        height: 38px !important;
        margin-right: 7px !important;
    }

    .texto-produto-impressao {
        min-height: 40px !important;
    }

    .texto-filho-conjunto {
        font-size: 10.8pt !important;
    }

    .linha-conjunto-filho .qtd-item {
        font-size: 10.6pt !important;
    }

    .obs-taxas-regras small,
    .obs-gerais small,
    .forma-pagamento small,
    .info-pix small {
        font-size: 10.2pt !important;
        line-height: 1.12 !important;
    }

    .taxas-fretes div {
        font-size: 10.4pt !important;
        line-height: 1.12 !important;
        margin-bottom: 1px !important;
    }

    .taxas-fretes h4 {
        font-size: 12.2pt !important;
    }

    .financeiro-linha-unica {
        padding: 4px 6px !important;
        margin-top: 3px !important;
        margin-bottom: 3px !important;
    }

    .financeiro-linha-titulo {
        font-size: 9.8pt !important;
        margin-bottom: 2px !important;
    }

    .financeiro-linha-conteudo {
        display: flex !important;
        justify-content: space-between !important;
        gap: 5px !important;
        font-size: 9.8pt !important;
        line-height: 1.05 !important;
    }

    .financeiro-linha-condicoes {
        font-size: 8.8pt !important;
        line-height: 1.05 !important;
        margin-top: 2px !important;
        padding-top: 2px !important;
    }

    .info-pix strong {
        font-size: 11pt !important;
    }

    .observacoes-adicionais {
        font-size: 10.5pt !important;
    }
}


/* ==========================================================
   AJUSTE V4 - LEITURA MAIOR / IDOSOS / IMPRESSÃO REAL
   Mantém a lógica existente e só reforça legibilidade.
   ========================================================== */

/* Linhas de datas: mais força visual e sem parecer igual */
.linha-data-operacional span {
    font-size: 12.8pt !important;
    font-weight: 900 !important;
}

.linha-data-operacional strong {
    font-size: 14.4pt !important;
    font-weight: 900 !important;
}

.data-evento-destaque span,
.data-evento-destaque strong {
    background-color: #fff3b0 !important;
}

.data-entrega-destaque span,
.data-entrega-destaque strong {
    background-color: #d7ecff !important;
}

.data-coleta-destaque span,
.data-coleta-destaque strong {
    background-color: #ffd6d6 !important;
    color: #7a0000 !important;
}

/* Tabela: fonte mais próxima da área das datas */
.table-itens-pedido th,
.table-itens-pedido td {
    font-size: 13.6pt !important;
    line-height: 1.22 !important;
}

.table-itens-pedido thead th {
    font-size: 13.2pt !important;
    font-weight: 900 !important;
}

.descricao-item strong {
    font-size: 13.0pt !important;
    font-weight: 900 !important;
}

/* Item interno de conjunto: quantidade menor e azul, igual à setinha */
.qtd-item-conjunto-filho,
.qtd-item-conjunto-filho strong {
    color: #0b5ed7 !important;
    font-size: 11.2pt !important;
    font-weight: 900 !important;
}

.marcador-item-conjunto {
    color: #0b5ed7 !important;
}

.texto-filho-conjunto {
    color: #0b5ed7 !important;
    font-size: 12.2pt !important;
    font-weight: 900 !important;
}

.indicador-conjunto-pai {
    font-size: 9.6pt !important;
}

/* Blocos de observações/pagamento: maior, sem desperdiçar tanto espaço */
.obs-gerais small,
.forma-pagamento small,
.info-pix small {
    font-size: 12.0pt !important;
    line-height: 1.22 !important;
}

.forma-pagamento strong,
.obs-gerais strong {
    font-size: 12.6pt !important;
}

.taxas-fretes div {
    font-size: 12.0pt !important;
    line-height: 1.18 !important;
}

.taxas-fretes h4 {
    font-size: 13.6pt !important;
}

.financeiro-linha-conteudo {
    font-size: 12.0pt !important;
}

.financeiro-linha-titulo {
    font-size: 11.8pt !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 5mm 6mm 5mm 6mm;
    }

    body {
        font-size: 12.2pt !important;
    }

    .info-empresa h4 {
        font-size: 13.8pt !important;
    }

    .info-empresa small,
    .info-pedido,
    .info-pedido small {
        font-size: 9.8pt !important;
        line-height: 1.12 !important;
    }

    .bloco-cliente-logistica {
        font-size: 12.2pt !important;
    }

    .bloco-datas-operacionais {
        grid-template-columns: 178px 1fr !important;
        border: 2px solid #000 !important;
        margin-top: 3px !important;
        margin-bottom: 3px !important;
    }

    .linha-data-operacional span,
    .linha-data-operacional strong {
        min-height: 25px !important;
    }

    .linha-data-operacional span {
        font-size: 11.8pt !important;
        padding: 3px 6px !important;
    }

    .linha-data-operacional strong {
        font-size: 13.9pt !important;
        padding: 3px 7px !important;
        white-space: nowrap !important;
    }

    .linha-local-entrega {
        font-size: 11.8pt !important;
        font-weight: 800 !important;
    }

    .taxas-alerta-compactas {
        font-size: 9.4pt !important;
        line-height: 1.03 !important;
        gap: 0 10px !important;
        padding: 3px 6px !important;
        margin: 3px 0 3px 0 !important;
    }

    .table-itens-pedido th,
    .table-itens-pedido td {
        font-size: 12.7pt !important;
        line-height: 1.12 !important;
        padding: 0.24rem 0.38rem !important;
    }

    .table-itens-pedido thead th {
        font-size: 12.2pt !important;
        font-weight: 900 !important;
    }

    .descricao-item strong {
        font-size: 12.4pt !important;
    }

    .produto-foto-impressao {
        width: 36px !important;
        height: 36px !important;
        margin-right: 7px !important;
    }

    .texto-produto-impressao {
        min-height: 38px !important;
    }

    .qtd-item-conjunto-filho,
    .qtd-item-conjunto-filho strong,
    .linha-conjunto-filho .qtd-item,
    .linha-conjunto-filho .qtd-item strong {
        color: #0b5ed7 !important;
        font-size: 10.8pt !important;
        font-weight: 900 !important;
    }

    .texto-filho-conjunto {
        color: #0b5ed7 !important;
        font-size: 11.5pt !important;
        font-weight: 900 !important;
    }

    .marcador-item-conjunto {
        color: #0b5ed7 !important;
        height: 33px !important;
        font-size: 12pt !important;
    }

    .obs-gerais small,
    .forma-pagamento small,
    .info-pix small {
        font-size: 10.8pt !important;
        line-height: 1.10 !important;
    }

    .taxas-fretes div {
        font-size: 10.8pt !important;
        line-height: 1.10 !important;
        margin-bottom: 0 !important;
    }

    .taxas-fretes h4 {
        font-size: 12.6pt !important;
    }

    .financeiro-linha-unica {
        padding: 5px 6px !important;
    }

    .financeiro-linha-titulo {
        font-size: 10.4pt !important;
    }

    .financeiro-linha-conteudo {
        font-size: 10.8pt !important;
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 4px 10px !important;
        align-items: center !important;
    }

    .financeiro-linha-conteudo span {
        white-space: nowrap !important;
        margin-right: 0 !important;
    }

    .observacoes-adicionais {
        font-size: 10.8pt !important;
    }
}

</style>

<script>
if (window.NOME_ARQUIVO_DOCUMENTO) {
    document.title = window.NOME_ARQUIVO_DOCUMENTO;
}

function definirTituloDocumentoPedido(tipo) {
    if (tipo === 'producao' && window.NOME_ARQUIVO_PRODUCAO) {
        document.title = window.NOME_ARQUIVO_PRODUCAO;
        return;
    }

    if (tipo === 'cliente' && window.NOME_ARQUIVO_CLIENTE) {
        document.title = window.NOME_ARQUIVO_CLIENTE;
        return;
    }

    if (window.NOME_ARQUIVO_DOCUMENTO) {
        document.title = window.NOME_ARQUIVO_DOCUMENTO;
    }
}

function limparModoImpressaoPedido() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = ''; });
    document.body.classList.remove('impressao-cliente');
    document.body.classList.remove('impressao-producao');
    definirTituloDocumentoPedido('visualizacao');
    window.onafterprint = null;
}

function imprimirCliente() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = 'none'; });
    document.body.classList.remove('impressao-producao');
    document.body.classList.add('impressao-cliente');
    definirTituloDocumentoPedido('cliente');
    window.onafterprint = limparModoImpressaoPedido;
    window.print();
}

function imprimirProducao() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = 'inline'; });
    document.body.classList.remove('impressao-cliente');
    document.body.classList.add('impressao-producao');
    definirTituloDocumentoPedido('producao');
    window.onafterprint = limparModoImpressaoPedido;
    window.print();
}

document.addEventListener('DOMContentLoaded', function () {
    definirTituloDocumentoPedido('visualizacao');
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