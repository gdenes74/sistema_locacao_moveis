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

if (!function_exists('exibirTaxaOrcamentoShow')) {
    function exibirTaxaOrcamentoShow(mixed $valor): string
    {
        if (is_numeric($valor) && (float)$valor > 0) {
            return 'R$ ' . formatarValor($valor);
        }
        return 'a confirmar';
    }
}



if (!function_exists('carregarConfiguracaoTextoOrcamentoShow')) {
    function carregarConfiguracaoTextoOrcamentoShow(PDO $conn, string $chave, string $fallback = ''): string
    {
        try {
            $stmt = $conn->prepare("SELECT conteudo FROM configuracoes_textos WHERE chave = :chave AND ativo = 1 LIMIT 1");
            $stmt->bindValue(':chave', $chave, PDO::PARAM_STR);
            $stmt->execute();
            $conteudo = $stmt->fetchColumn();

            if (is_string($conteudo) && trim($conteudo) !== '') {
                return trim($conteudo);
            }
        } catch (PDOException $e) {
            error_log('[orcamentos/show.php] Erro ao carregar configuração de texto "' . $chave . '": ' . $e->getMessage());
        }

        return trim($fallback);
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

if (!function_exists('observacaoItemUsadaComoCorOrcamentoShow')) {
    function observacaoItemUsadaComoCorOrcamentoShow(mixed $nomeItem, mixed $observacaoItem = ''): bool
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

if (!function_exists('montarNomeComponenteProducaoOrcamentoShow')) {
    function montarNomeComponenteProducaoOrcamentoShow(array $componente, mixed $observacaoItem = '', mixed $nomeItem = ''): string
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

        if ($ehServico && observacaoItemUsadaComoCorOrcamentoShow($nomeItem, $observacaoItem)) {
            $nomeComponenteSemDefinir = preg_replace('/\bCOR\s+A\s+DEFINIR\b/iu', '', $nomeComponente);
            return trim((string)($nomeComponenteSemDefinir ?: $nomeComponente) . ' Cor ' . $observacaoItem);
        }

        return $nomeComponente;
    }
}

if (!function_exists('adicionarComponenteResumoProducaoOrcamentoShow')) {
    function adicionarComponenteResumoProducaoOrcamentoShow(array &$resumo, array $componente, float $quantidadeItem, mixed $observacaoItem = '', mixed $nomeItem = ''): void
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

        $nomeComponenteProducao = montarNomeComponenteProducaoOrcamentoShow($componente, $observacaoItem, $nomeItem);
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

if (!function_exists('formatarQuantidadeProducaoOrcamentoShow')) {
    function formatarQuantidadeProducaoOrcamentoShow(float $quantidade): string
    {
        if (abs($quantidade - round($quantidade)) < 0.00001) {
            return number_format($quantidade, 0, ',', '.');
        }
        return number_format($quantidade, 2, ',', '.');
    }
}


if (!function_exists('grupoProducaoOrcamentoShow')) {
    function grupoProducaoOrcamentoShow(mixed $nomeProduto, mixed $tipoProduto = ''): array
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

if (!function_exists('ordenarComponentesProducaoOrcamentoShow')) {
    function ordenarComponentesProducaoOrcamentoShow(array $componentes, mixed $observacaoItem = '', mixed $nomeItem = ''): array
    {
        usort($componentes, function ($a, $b) use ($observacaoItem, $nomeItem) {
            $nomeA = montarNomeComponenteProducaoOrcamentoShow($a, $observacaoItem, $nomeItem);
            $nomeB = montarNomeComponenteProducaoOrcamentoShow($b, $observacaoItem, $nomeItem);
            $grupoA = grupoProducaoOrcamentoShow($nomeA, $a['tipo_produto'] ?? '');
            $grupoB = grupoProducaoOrcamentoShow($nomeB, $b['tipo_produto'] ?? '');

            if ($grupoA['ordem'] !== $grupoB['ordem']) {
                return $grupoA['ordem'] <=> $grupoB['ordem'];
            }

            return strnatcasecmp($nomeA, $nomeB);
        });

        return $componentes;
    }
}

if (!function_exists('agruparResumoProducaoOrcamentoShow')) {
    function agruparResumoProducaoOrcamentoShow(array $resumo): array
    {
        $grupos = [];

        foreach ($resumo as $itemResumo) {
            $grupo = grupoProducaoOrcamentoShow($itemResumo['nome_produto'] ?? '', $itemResumo['tipo_produto'] ?? '');
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

        uasort($grupos, function ($a, $b) {
            return $a['ordem'] <=> $b['ordem'];
        });

        foreach ($grupos as &$grupo) {
            usort($grupo['itens'], function ($a, $b) {
                return strnatcasecmp((string)($a['nome_produto'] ?? ''), (string)($b['nome_produto'] ?? ''));
            });
        }
        unset($grupo);

        return $grupos;
    }
}

if (!function_exists('limparNomeArquivoOrcamentoShow')) {
    function limparNomeArquivoOrcamentoShow(mixed $texto): string
    {
        $texto = trim((string)$texto);

        // Remove caracteres inválidos para nome de arquivo no Windows sem usar regex complexo.
        // Evita warnings como: preg_replace(): Unknown modifier '\'.
        $texto = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto) ?: 'SEM NOME';
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

$fallbackObservacoesOrcamento = "Confirmação de quantidades e diminuições são aceitos no máximo até 7 dias antes da festa, desde que não ultrapasse 10% do valor total contratado.\nNão inclui posicionamento dos móveis no local.\nDOMINGO/FERIADO após as 8h e antes das 12h: Taxa R$ 250,00\nMADRUGADA após as 4:30h e antes das 8:30h: Taxa R$ 800,00\nHORÁRIO ESPECIAL após as 12h de sábado até as 23:30h de segunda a sábado: Taxa R$ 500,00\nHORA MARCADA segunda a sexta das 8:30h até as 17h e sábado das 8:30h às 12h: Taxa R$ 200,00\nInfelizmente não dispomos de entregas ou coletas no período das 23:30h às 5h.";
$fallbackCondicoesOrcamento = "Entrada de 30% para reserva, via PIX ou depósito.\nO saldo deverá ser pago via PIX ou depósito até 7 dias antes da data do evento.\nConsulte previamente a disponibilidade e as condições para locações com período estendido, mais de uma diária ou necessidades especiais de entrega/coleta.";
$fallbackPixOrcamento = "PIX SICREDI\nCNPJ: 19.318.614/0001-44\nPedimos a gentileza de enviar o comprovante por WhatsApp para baixa no sistema, confirmação da reserva e organização do estoque.";

$textoObservacoesOrcamento = trim((string)($orcamentoModel->observacoes ?? ''));
if ($textoObservacoesOrcamento === '') {
    $textoObservacoesOrcamento = carregarConfiguracaoTextoOrcamentoShow($conn, 'observacoes_gerais_padrao', $fallbackObservacoesOrcamento);
}

$textoCondicoesOrcamento = trim((string)($orcamentoModel->condicoes_pagamento ?? ''));
if ($textoCondicoesOrcamento === '') {
    $textoCondicoesOrcamento = carregarConfiguracaoTextoOrcamentoShow($conn, 'condicoes_pagamento_padrao', $fallbackCondicoesOrcamento);
}

$textoPixOrcamento = carregarConfiguracaoTextoOrcamentoShow($conn, 'pix_padrao', $fallbackPixOrcamento);

$clienteNomeShow = trim((string)($clienteModel->nome ?? '')) ?: 'Não informado';
$clienteTelefoneShow = !empty($clienteModel->telefone) ? formatarTelefone($clienteModel->telefone) : 'N/A';
$clienteEmailShow = trim((string)($clienteModel->email ?? '')) ?: 'N/A';
$clienteCpfCnpjShow = trim((string)($clienteModel->cpf_cnpj ?? '')) ?: 'N/A';
$clienteEnderecoBaseShow = trim((string)($clienteModel->endereco ?? ''));
$clienteCidadeShow = trim((string)($clienteModel->cidade ?? ''));
$clienteEnderecoShow = $clienteEnderecoBaseShow;
if ($clienteCidadeShow !== '') {
    $clienteEnderecoShow .= ($clienteEnderecoShow !== '' ? ', ' : '') . $clienteCidadeShow;
}
$clienteEnderecoShow = $clienteEnderecoShow !== '' ? $clienteEnderecoShow : 'N/A';

$itens = $orcamentoModel->getItens($id);
$componentesPorProduto = is_array($itens) ? carregarComponentesProdutosCompostosOrcamentoShow($conn, $itens) : [];
$resumoComponentesProducao = [];

$dataEventoArquivo = 'sem-data';
if (!empty($orcamentoModel->data_evento)) {
    $timestampEventoArquivo = strtotime($orcamentoModel->data_evento);
    if ($timestampEventoArquivo !== false) {
        $dataEventoArquivo = date('d.m.y', $timestampEventoArquivo);
    }
}

$nomeClienteArquivo = limparNomeArquivoOrcamentoShow($clienteModel->nome ?? 'Cliente');
$numeroOrcamentoArquivo = limparNomeArquivoOrcamentoShow($orcamentoModel->numero ?? $orcamentoModel->id ?? $id);
$nomeArquivoDocumento = limparNomeArquivoOrcamentoShow($dataEventoArquivo . ' - ' . $nomeClienteArquivo . ' - ORCAMENTO ' . $numeroOrcamentoArquivo);
$page_title = $nomeArquivoDocumento;

// Data/hora da geração visual do documento.
// Como o PDF é salvo pelo navegador via window.print(), esta informação entra no nome sugerido
// e também fica impressa no cabeçalho para diferenciar versões geradas em momentos diferentes.
$dataHoraGeracaoArquivo = date('d.m.y H\hi');
$dataHoraGeracaoDisplay = date('d/m/Y H:i');

$nomeArquivoCliente = limparNomeArquivoOrcamentoShow($nomeArquivoDocumento . ' - CLIENTE - GERADO ' . $dataHoraGeracaoArquivo);
$nomeArquivoProducao = limparNomeArquivoOrcamentoShow($nomeArquivoDocumento . ' - PRODUCAO - GERADO ' . $dataHoraGeracaoArquivo);

$inline_js_setup = "window.ORCAMENTO_ID = " . $id
    . "; window.NOME_ARQUIVO_DOCUMENTO = " . json_encode($nomeArquivoDocumento, JSON_UNESCAPED_UNICODE)
    . "; window.NOME_ARQUIVO_CLIENTE = " . json_encode($nomeArquivoCliente, JSON_UNESCAPED_UNICODE)
    . "; window.NOME_ARQUIVO_PRODUCAO = " . json_encode($nomeArquivoProducao, JSON_UNESCAPED_UNICODE)
    . "; window.DATA_HORA_GERACAO_DOCUMENTO = " . json_encode($dataHoraGeracaoDisplay, JSON_UNESCAPED_UNICODE) . ";";
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

                    <?php elseif ($orcamentoModel->status === 'expirado'): ?>
                        <a href="edit.php?id=<?= htmlspecialchars($orcamentoModel->id ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Editar / Reabrir
                        </a>
                        <span class="badge badge-warning ml-1">
                            <i class="fas fa-clock"></i> EXPIRADO
                        </span>
                        <small class="text-muted ml-1">
                            Reabra como pendente no editar para converter depois.
                        </small>

                    <?php elseif ($orcamento_finalizado_ou_irreversivel): ?>
                        <span class="badge badge-<?=
                            $orcamentoModel->status === 'recusado' ? 'danger' :
                            ($orcamentoModel->status === 'cancelado' ? 'secondary' : 'secondary')
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
                            <br><small class="documento-gerado-em"><strong>Gerado em:</strong> <?= htmlspecialchars($dataHoraGeracaoDisplay, ENT_QUOTES, 'UTF-8') ?></small>
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
                                <div class="dados-cliente-show">
                                    <strong>Cliente:</strong> <?= htmlspecialchars($clienteNomeShow, ENT_QUOTES, 'UTF-8') ?><br>
                                    <strong>Telefone:</strong> <?= htmlspecialchars($clienteTelefoneShow, ENT_QUOTES, 'UTF-8') ?><br>
                                    <strong>E-mail:</strong> <?= htmlspecialchars($clienteEmailShow, ENT_QUOTES, 'UTF-8') ?><br>
                                    <strong>CPF/CNPJ:</strong> <?= htmlspecialchars($clienteCpfCnpjShow, ENT_QUOTES, 'UTF-8') ?><br>
                                    <strong>Endereço:</strong> <?= htmlspecialchars($clienteEnderecoShow, ENT_QUOTES, 'UTF-8') ?>
                                </div>
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
                                <div class="local-entrega-show">
                                    <strong>Local de Entrega:</strong> <?= htmlspecialchars($orcamentoModel->local_evento ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="col-5 box-logistica">
                                <div class="linha-logistica destaque-evento">
                                    <strong>Data do Evento:</strong>
                                    <?= htmlspecialchars($dataEventoCompleta, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="linha-logistica destaque-entrega mt-1">
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
                                <div class="linha-logistica destaque-coleta mt-1">
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
                                            $obsFoiConcatenada = observacaoItemUsadaComoCorOrcamentoShow($nomeItem, $observacaoItem);
                                            $produtoIdItem = isset($item['produto_id']) ? (int)$item['produto_id'] : 0;
                                            $componentesItem = $produtoIdItem > 0 && isset($componentesPorProduto[$produtoIdItem]) ? $componentesPorProduto[$produtoIdItem] : [];
                                            if (!empty($componentesItem)) {
                                                $componentesItem = ordenarComponentesProducaoOrcamentoShow($componentesItem, $observacaoItem, $nomeItem);
                                                foreach ($componentesItem as $componenteItem) {
                                                    adicionarComponenteResumoProducaoOrcamentoShow($resumoComponentesProducao, $componenteItem, $quantidadeItem, $observacaoItem, $nomeItem);
                                                }
                                            }
                                            $tipoLinhaItem = strtoupper(trim((string)($item['tipo_linha'] ?? 'PRODUTO')));
                                            $ehConjuntoPai = $tipoLinhaItem === 'CONJUNTO';
                                            $ehItemConjunto = $tipoLinhaItem === 'ITEM_CONJUNTO';
                                            $classeLinhaItem = $ehConjuntoPai ? 'linha-conjunto-pai' : ($ehItemConjunto ? 'linha-conjunto-filho' : '');
                                            ?>
                                            <tr class="<?= $classeLinhaItem ?>">
                                                <td class="text-center qtd-item <?= $ehItemConjunto ? 'qtd-item-conjunto-filho' : '' ?>"><strong><?= htmlspecialchars(number_format($quantidadeItem, 0), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                <td class="descricao-item <?= $ehItemConjunto ? 'descricao-item-conjunto-filho' : '' ?>">
                                                    <?php if ($ehItemConjunto): ?>
                                                        <span class="marcador-item-conjunto">↳</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['foto_path'])): ?>
                                                        <img src="<?= BASE_URL ?>/<?= ltrim($item['foto_path'], '/') ?>"
                                                             alt="<?= htmlspecialchars($nomeItemExibicao, ENT_QUOTES, 'UTF-8') ?>"
                                                             class="produto-foto-impressao"
                                                             onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <div class="texto-produto-impressao">
                                                        <strong class="<?= $ehItemConjunto ? 'texto-filho-conjunto' : '' ?>"><?= htmlspecialchars(strtoupper($nomeItemExibicao), ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <?php if ($ehConjuntoPai): ?>
                                                            <br><small class="indicador-conjunto-pai">↓ itens internos do conjunto</small>
                                                        <?php endif; ?>
                                                        <?php if ($observacaoItem !== '' && !$obsFoiConcatenada): ?>
                                                            <br><small class="observacao-item"><?= htmlspecialchars($observacaoItem, ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php endif; ?>

                                                        <?php if (!empty($componentesItem)): ?>
                                                            <div class="componentes-producao somente-producao">
                                                                <strong>Componentes para produção:</strong>
                                                                <?php foreach ($componentesItem as $comp): ?>
                                                                    <?php
                                                                    $qtdComp = isset($comp['quantidade_componente']) ? (float)$comp['quantidade_componente'] : 1.0;
                                                                    $qtdTotalComp = $quantidadeItem * ($qtdComp > 0 ? $qtdComp : 1.0);
                                                                    $nomeComponenteProducao = montarNomeComponenteProducaoOrcamentoShow($comp, $observacaoItem, $nomeItem);
                                                                    ?>
                                                                    <div>
                                                                        - <?= htmlspecialchars(formatarQuantidadeProducaoOrcamentoShow($qtdTotalComp), ENT_QUOTES, 'UTF-8') ?>
                                                                        <?= htmlspecialchars($nomeComponenteProducao, ENT_QUOTES, 'UTF-8') ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <?php if ($ehItemConjunto): ?>
                                                    <td class="text-right valor-col col-financeira valor-filho-conjunto">&nbsp;</td>
                                                    <?php if ($temDesconto): ?>
                                                        <td class="text-right valor-col col-financeira valor-filho-conjunto">&nbsp;</td>
                                                    <?php endif; ?>
                                                    <td class="text-right valor-col total-item col-financeira valor-filho-conjunto">&nbsp;</td>
                                                <?php else: ?>
                                                    <td class="text-right valor-col col-financeira">R$ <?= formatarValor($precoUnitarioItem) ?></td>
                                                    <?php if ($temDesconto): ?>
                                                        <td class="text-right valor-col col-financeira"><?= $descontoItem > 0 ? 'R$ ' . formatarValor($descontoItem) : '-' ?></td>
                                                    <?php endif; ?>
                                                    <td class="text-right valor-col total-item col-financeira"><strong>R$ <?= formatarValor($itemSubtotal) ?></strong></td>
                                                <?php endif; ?>
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
                            <?php $gruposResumoProducao = agruparResumoProducaoOrcamentoShow($resumoComponentesProducao); ?>
                            <table class="table table-sm table-bordered tabela-resumo-producao mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 12%;">QTD</th>
                                        <th>COMPONENTE / SERVIÇO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gruposResumoProducao as $grupoResumo): ?>
                                        <tr class="linha-grupo-resumo-producao">
                                            <td colspan="2"><?= htmlspecialchars($grupoResumo['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                        <?php foreach ($grupoResumo['itens'] as $resumoComp): ?>
                                            <tr>
                                                <td class="text-center font-weight-bold">
                                                    <?= htmlspecialchars(formatarQuantidadeProducaoOrcamentoShow((float)$resumoComp['quantidade']), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($resumoComp['nome_produto'] ?? 'Componente', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="row bloco-pos-itens mb-2">
                        <div class="col-7 obs-gerais bloco-texto-documento">
                            <strong>Observações Gerais:</strong>
                            <div><?= nl2br(htmlspecialchars($textoObservacoesOrcamento, ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                        <div class="col-5 text-right box-subtotal">
                            <strong>Sub total p/ PIX ou Depósito</strong>
                            <strong class="ml-3">R$ <?= formatarValor($subtotalItensPIX) ?></strong>
                        </div>
                    </div>

                    <div class="row bloco-pagamento-taxas">
                        <div class="col-7 forma-pagamento bloco-texto-documento">
                            <strong>Forma de Pagamento:</strong>
                            <div><?= nl2br(htmlspecialchars($textoCondicoesOrcamento, ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                        <div class="col-5 taxas-fretes text-right">
                            <div><span class="text-left-label">TAXA DOMINGO E FERIADO R$ 250,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_domingo_feriado ?? 0) ?></span></div>
                            <div><span class="text-left-label">TAXA MADRUGADA R$ 800,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_madrugada ?? 0) ?></span></div>
                            <div><span class="text-left-label">TAXA HORÁRIO ESPECIAL R$ 500,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_horario_especial ?? 0) ?></span></div>
                            <div><span class="text-left-label">TAXA HORA MARCADA R$ 200,00</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->taxa_hora_marcada ?? 0) ?></span></div>

                            <!-- Fretes na mesma ordem do create/edit: térreo, elevador, escadas. -->
                            <div><span class="text-left-label">FRETE TÉRREO</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->frete_terreo ?? 0) ?></span></div>
                            <div><span class="text-left-label">FRETE ELEVADOR</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->frete_elevador ?? 0) ?></span></div>
                            <div><span class="text-left-label">FRETE ESCADAS</span><span><?= exibirTaxaOrcamentoShow($orcamentoModel->frete_escadas ?? 0) ?></span></div>

                            <?php if (!empty($orcamentoModel->desconto) && (float)$orcamentoModel->desconto > 0): ?>
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
                            <div><?= nl2br(htmlspecialchars($textoPixOrcamento, ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                    </div>

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
        font-size: 12.2pt;
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

    .documento-gerado-em {
        color: #555 !important;
        font-size: 8.8pt !important;
    }

    .titulo-documento {
        color: #000;
        font-size: 15pt;
    }

    .box-dados-principais {
        font-size: 12.0pt;
        line-height: 1.24;
    }

    .dados-cliente-show {
        font-size: 11.9pt;
        line-height: 1.22;
        margin-bottom: 3px;
    }

    .bloco-texto-documento {
        font-size: 11.4pt;
        line-height: 1.18;
        color: #000;
    }

    .bloco-texto-documento strong {
        font-size: 11.8pt;
        font-weight: 900;
        display: block;
        margin-bottom: 2px;
    }

    .bloco-texto-documento div {
        white-space: normal;
    }

    .linha-logistica {
        border: 1px solid #777;
        padding: 5px 7px;
        font-weight: 800;
        color: #000;
        font-size: 11.7pt;
        line-height: 1.18;
    }

    .destaque-amarelo,
    .destaque-entrega {
        background: #fff2cc;
        border-color: #d6b656;
    }

    .destaque-evento {
        background: #d9ead3;
        border-color: #6aa84f;
    }

    .destaque-coleta {
        background: #f4cccc;
        border-color: #cc0000;
    }

    .local-entrega-show {
        margin-top: 5px;
        font-size: 11.7pt;
        line-height: 1.22;
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

    .tabela-resumo-producao {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }

    .tabela-resumo-producao th,
    .tabela-resumo-producao td {
        border: 1px solid #000 !important;
        padding: 3px 6px !important;
        font-size: 10.5pt;
        color: #000 !important;
        vertical-align: middle;
    }

    .tabela-resumo-producao th {
        background: #e6e6e6 !important;
        font-weight: 900;
        text-align: center;
    }


    .tabela-resumo-producao .linha-grupo-resumo-producao td {
        background: #e6e6e6 !important;
        font-weight: 900;
        text-transform: uppercase;
        text-align: left;
        border-top: 2px solid #000 !important;
    }

    .obs-gerais,
    .forma-pagamento,
    .info-pix {
        color: #000;
    }

    .info-pix {
        border-top: 1px solid #999;
        padding-top: 5px;
        font-size: 10.8pt;
        line-height: 1.2;
        font-weight: 700;
    }

    .linha-atendimento {
        border-bottom: 1px solid #000;
        padding-bottom: 4px;
        font-size: 11.8pt;
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
        font-size: 9.6pt;
        font-weight: 800;
        letter-spacing: 0.01em;
    }

    .linha-conjunto-filho td {
        background: #ffffff !important;
        border-top-color: #b6d4fe !important;
    }

    .linha-conjunto-filho .qtd-item {
        font-size: 9.8pt !important;
        color: #0b5ed7 !important;
    }

    .linha-conjunto-filho .valor-col {
        font-size: 9.4pt !important;
        color: transparent !important;
    }

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
        font-size: 11.4pt;
        color: #0b5ed7 !important;
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
        font-size: 12.0pt;
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
        font-size: 12.8pt !important;
    }

    .valor-col {
        white-space: nowrap;
        font-size: 11.4pt !important;
    }

    .total-item {
        font-weight: 800;
    }

    .observacao-item {
        font-size: 10.9pt;
        color: #000 !important;
        font-weight: 700;
        font-style: normal;
        margin-top: 2px;
    }

    .titulo-secao td {
        background-color: #e7f1ff !important;
        font-weight: bold;
        font-size: 12.8pt;
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
        font-size: 12.0pt;
    }

    .bloco-pagamento-taxas {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 6px 0;
    }

    .taxas-fretes div {
        font-size: 11.0pt;
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
        font-size: 12.5pt !important;
    }

    .info-pix {
        font-size: 11.2pt;
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
            font-size: 11.2pt;
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
            font-size: 11.6pt !important;
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

        .documento-gerado-em {
            font-size: 8.9pt !important;
            color: #444 !important;
        }

        .titulo-documento {
            font-size: 13pt !important;
        }

        .box-dados-principais {
            font-size: 11.6pt !important;
            line-height: 1.22 !important;
        }

        .dados-cliente-show {
            font-size: 11.4pt !important;
            line-height: 1.18 !important;
        }

        .bloco-texto-documento {
            font-size: 10.7pt !important;
            line-height: 1.14 !important;
        }

        .bloco-texto-documento strong {
            font-size: 11.3pt !important;
            margin-bottom: 1px !important;
        }

        .info-pix {
            font-size: 10.7pt !important;
            line-height: 1.13 !important;
            padding-top: 4px !important;
        }



        .box-logistica {
            padding-left: 6px !important;
        }

        .linha-logistica {
            font-size: 11.5pt !important;
            line-height: 1.12 !important;
            padding: 4px 6px !important;
            page-break-inside: avoid;
        }

        .local-entrega-show {
            font-size: 11.4pt !important;
            line-height: 1.18 !important;
        }

        .table-itens-orcamento th,
        .table-itens-orcamento td {
            border: 1px solid #777 !important;
            font-size: 11.4pt !important;
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
            font-size: 10.4pt !important;
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
            font-size: 10.6pt !important;
            line-height: 1.16 !important;
        }

        .total-final-box {
            font-size: 11.8pt !important;
        }



        .linha-conjunto-pai td {
            background: #f8fbff !important;
            border-top: 2px solid #000 !important;
        }

        .indicador-conjunto-pai {
            font-size: 8.9pt !important;
            color: #0b5ed7 !important;
            font-weight: 700 !important;
        }

        .linha-conjunto-filho td {
            background: #fff !important;
            border-top-color: #b6d4fe !important;
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
            font-size: 10.4pt !important;
            font-weight: 700 !important;
            color: #0b5ed7 !important;
        }

        .linha-conjunto-filho .qtd-item {
            font-size: 10.0pt !important;
            color: #0b5ed7 !important;
        }

        .linha-conjunto-filho .valor-col,
        .valor-filho-conjunto {
            color: transparent !important;
        }

        .linha-conjunto-filho .produto-foto-impressao {
            width: 34px !important;
            height: 34px !important;
            margin-right: 6px !important;
        }

        .linha-conjunto-filho .texto-produto-impressao {
            min-height: 34px !important;
        }

        .impressao-producao .somente-producao {
            display: block !important;
        }

        .impressao-cliente .somente-producao {
            display: none !important;
        }

        .impressao-producao .bloco-pagamento-taxas,
        .impressao-producao .info-pix,
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
            font-size: 10.0pt !important;
            margin-top: 3px !important;
            padding: 3px 5px !important;
        }

        .impressao-producao .resumo-componentes-producao {
            display: block !important;
        }

        .impressao-producao .tabela-resumo-producao th,
        .impressao-producao .tabela-resumo-producao td {
            font-size: 10.4pt !important;
            padding: 2px 5px !important;
        }

        .impressao-producao .box-data-evento,
        .impressao-producao .linha-logistica,
        .impressao-producao .destaque-amarelo {
            font-size: 11.4pt !important;
        }
    }

/* ==========================================================
   AJUSTE MOBEL - ORÇAMENTO IMPRESSÃO MAIOR + TABELA COM RESPIRO
   Mantém lógica do show; reforça legibilidade no papel.
   ========================================================== */
@media print {
    @page {
        size: A4 portrait;
        margin: 5mm 6mm 5mm 6mm;
    }

    html,
    body {
        font-size: 12pt !important;
    }

    .card-orcamento-visual {
        font-size: 12.1pt !important;
    }

    .info-empresa h4 {
        font-size: 13.8pt !important;
    }

    .info-empresa small,
    .info-orcamento small {
        font-size: 9.4pt !important;
        line-height: 1.12 !important;
    }

    .titulo-documento {
        font-size: 13.8pt !important;
    }

    .box-dados-principais,
    .dados-cliente-show,
    .local-entrega-show,
    .linha-atendimento {
        font-size: 12.1pt !important;
        line-height: 1.15 !important;
    }

    .linha-logistica {
        font-size: 12.0pt !important;
        line-height: 1.12 !important;
        padding: 4px 7px !important;
    }

    .table-itens-orcamento th,
    .table-itens-orcamento td {
        font-size: 12.5pt !important;
        line-height: 1.13 !important;
        padding: 3px 5px !important;
    }

    .descricao-item strong {
        font-size: 12.6pt !important;
        font-weight: 900 !important;
    }

    .qtd-item {
        font-size: 12.8pt !important;
    }

    .valor-col {
        font-size: 11.9pt !important;
    }

    .texto-filho-conjunto {
        font-size: 11.4pt !important;
        font-weight: 900 !important;
    }

    .linha-conjunto-filho .qtd-item,
    .linha-conjunto-filho .qtd-item strong {
        font-size: 10.9pt !important;
        font-weight: 900 !important;
        color: #0b5ed7 !important;
    }

    .indicador-conjunto-pai {
        font-size: 9.0pt !important;
    }

    .linha-vazia-impressao {
        display: table-row !important;
    }

    .linha-vazia-impressao td {
        height: 24px !important;
        min-height: 24px !important;
        padding: 2px 5px !important;
    }

    .bloco-texto-documento {
        font-size: 11.2pt !important;
        line-height: 1.13 !important;
    }

    .bloco-texto-documento strong {
        font-size: 11.8pt !important;
        font-weight: 900 !important;
    }

    .box-subtotal,
    .taxas-fretes div,
    .info-pix {
        font-size: 11.2pt !important;
        line-height: 1.12 !important;
    }

    .total-final-box {
        font-size: 12.4pt !important;
        padding: 4px 5px !important;
    }

    .impressao-producao .componentes-producao {
        font-size: 10.8pt !important;
        line-height: 1.12 !important;
    }

    .impressao-producao .tabela-resumo-producao th,
    .impressao-producao .tabela-resumo-producao td {
        font-size: 10.8pt !important;
        padding: 3px 5px !important;
    }
}



/* ==========================================================
   AJUSTE FINAL PRODUÇÃO EXTRA GRANDE - ORÇAMENTO - 08/06/2026
   Objetivo: deixar componentes e resumo de produção legíveis para equipe.
   Somente visual de impressão/produção; não altera lógica, fretes ou desconto.
   ========================================================== */
@media print {
    .impressao-producao .componentes-producao {
        display: block !important;
        font-size: 13.2pt !important;
        line-height: 1.16 !important;
        margin-top: 5px !important;
        padding: 6px 8px !important;
        border-left: 5px solid #000 !important;
        background: #f2f2f2 !important;
        color: #000 !important;
        font-weight: 900 !important;
    }

    .impressao-producao .componentes-producao strong {
        display: block !important;
        font-size: 13.6pt !important;
        line-height: 1.12 !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        margin-bottom: 3px !important;
        color: #000 !important;
    }

    .impressao-producao .componentes-producao div {
        font-size: 13.2pt !important;
        line-height: 1.15 !important;
        font-weight: 900 !important;
        color: #000 !important;
        margin: 2px 0 !important;
    }

    .impressao-producao .resumo-componentes-producao {
        display: block !important;
        margin-top: 8px !important;
        padding: 7px 8px !important;
        border: 2px solid #000 !important;
        background: #f2f2f2 !important;
        color: #000 !important;
        page-break-inside: avoid !important;
    }

    .impressao-producao .titulo-resumo-producao {
        font-size: 14.2pt !important;
        line-height: 1.12 !important;
        font-weight: 900 !important;
        text-align: center !important;
        text-transform: uppercase !important;
        border-bottom: 2px solid #000 !important;
        padding-bottom: 5px !important;
        margin-bottom: 5px !important;
        color: #000 !important;
    }

    .impressao-producao .tabela-resumo-producao th,
    .impressao-producao .tabela-resumo-producao td {
        font-size: 13.4pt !important;
        line-height: 1.14 !important;
        padding: 5px 7px !important;
        border: 1.5px solid #000 !important;
        color: #000 !important;
        font-weight: 900 !important;
    }

    .impressao-producao .tabela-resumo-producao th {
        background: #e6e6e6 !important;
        text-transform: uppercase !important;
        font-size: 13.2pt !important;
    }

    .impressao-producao .tabela-resumo-producao .linha-grupo-resumo-producao td {
        background: #d9d9d9 !important;
        font-size: 13.8pt !important;
        line-height: 1.12 !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        border-top: 2px solid #000 !important;
        color: #000 !important;
    }

    .impressao-producao .tabela-resumo-producao td:first-child {
        font-size: 13.8pt !important;
        font-weight: 900 !important;
        text-align: center !important;
    }

    .impressao-producao .tabela-resumo-producao td:last-child {
        font-size: 13.4pt !important;
        font-weight: 900 !important;
    }
}

</style>

<script>
if (window.NOME_ARQUIVO_DOCUMENTO) {
    document.title = window.NOME_ARQUIVO_DOCUMENTO;
}

function definirTituloDocumentoOrcamento(tipo) {
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

function limparModoImpressaoOrcamento() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = ''; });
    document.body.classList.remove('impressao-cliente');
    document.body.classList.remove('impressao-producao');
    definirTituloDocumentoOrcamento('visualizacao');
    window.onafterprint = null;
}

function imprimirCliente() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = 'none'; });
    document.body.classList.remove('impressao-producao');
    document.body.classList.add('impressao-cliente');
    definirTituloDocumentoOrcamento('cliente');
    window.onafterprint = limparModoImpressaoOrcamento;
    window.print();
}

function imprimirProducao() {
    var observacoes = document.querySelectorAll('.observacao-item');
    observacoes.forEach(function (el) { el.style.display = 'inline'; });
    document.body.classList.remove('impressao-cliente');
    document.body.classList.add('impressao-producao');
    definirTituloDocumentoOrcamento('producao');
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
                    url: `${window.BASE_URL || '<?= BASE_URL ?>'}/views/orcamentos/converter_pedido.php`,
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
                                window.location.href = `${window.BASE_URL || '<?= BASE_URL ?>'}/views/pedidos/edit.php?id=${response.pedido_id}&converted=1`;
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
console.log('ORCAMENTO_ID:', typeof window.ORCAMENTO_ID !== 'undefined' ? window.ORCAMENTO_ID : 'não definido');
console.log('BASE_URL:', typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : 'não definido');
JS;

include_once __DIR__ . '/../includes/footer.php';
?>
