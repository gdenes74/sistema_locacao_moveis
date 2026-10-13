<?php

declare(strict_types=1);

const BASE_URL = 'http://localhost/sistema-toalhas/views/pedidos';
const ORCAMENTOS_URL = 'http://localhost/sistema-toalhas/views/orcamentos';

$falhas = [];
$aprovacoes = 0;

function obter(string $url): string
{
    $contexto = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $conteudo = @file_get_contents($url, false, $contexto);
    if ($conteudo === false) {
        throw new RuntimeException("Não foi possível acessar {$url}");
    }
    return $conteudo;
}

function produtos(string $termo): array
{
    $json = obter(BASE_URL . '/create.php?ajax=buscar_produtos&termo=' . rawurlencode($termo));
    $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($dados)) {
        throw new RuntimeException("Resposta inválida ao buscar {$termo}");
    }
    return $dados;
}

function gruposConjunto(int $produtoId): array
{
    $json = obter(BASE_URL . '/create.php?ajax=buscar_grupos_conjunto&produto_id=' . $produtoId);
    $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($dados)) {
        throw new RuntimeException("Resposta inválida ao buscar grupos do conjunto {$produtoId}");
    }
    return $dados;
}

function quantidadePadraoNovaCor(float $quantidadePorConjunto, int $quantidadeFaltante): int
{
    return $quantidadePorConjunto > 1
        ? min((int)round($quantidadePorConjunto), $quantidadeFaltante)
        : $quantidadeFaltante;
}

function produtoPorId(array $produtos, int $id): array
{
    foreach ($produtos as $produto) {
        if ((int)($produto['id'] ?? 0) === $id) {
            return $produto;
        }
    }
    throw new RuntimeException("Produto {$id} não retornado pela busca");
}

function regraCasaComProduto(array $regra, array $produto): bool
{
    $regraProduto = (int)($regra['produto_id'] ?? 0);
    $regraSubcategoria = (int)($regra['subcategoria_id'] ?? 0);
    $regraCategoria = (int)($regra['categoria_id'] ?? 0);

    if ($regraProduto > 0) {
        return (int)($produto['id'] ?? 0) === $regraProduto;
    }
    if ($regraSubcategoria > 0) {
        return (int)($produto['subcategoria_id'] ?? 0) === $regraSubcategoria;
    }
    if ($regraCategoria > 0) {
        return (int)($produto['categoria_id'] ?? 0) === $regraCategoria;
    }
    return false;
}

function verificar(bool $condicao, string $descricao): void
{
    global $falhas, $aprovacoes;
    if ($condicao) {
        $aprovacoes++;
        echo "[OK] {$descricao}\n";
        return;
    }
    $falhas[] = $descricao;
    echo "[FALHA] {$descricao}\n";
}

try {
    $bistros = produtos('bistro');
    $pufes = produtos('pufe');
    $sofas = produtos('sofa');

    $candidatos = [
        'madeira' => produtoPorId($bistros, 66),
        'preto' => produtoPorId($bistros, 64),
        'cor_a_definir' => produtoPorId($bistros, 65),
        'bistro_alto' => produtoPorId($bistros, 636),
        'pufe' => produtoPorId($pufes, 38),
        'sofa' => produtoPorId($sofas, 54),
    ];

    $matriz = [
        68 => ['bistro' => 'madeira', 'permitidos' => ['madeira', 'pufe']],
        69 => ['bistro' => 'preto', 'permitidos' => ['preto', 'pufe']],
        638 => ['bistro' => 'cor_a_definir', 'permitidos' => ['cor_a_definir', 'pufe']],
    ];

    foreach ($matriz as $conjuntoId => $esperado) {
        $conjunto = produtoPorId($bistros, $conjuntoId);
        $regras = $conjunto['regras_conjunto'] ?? [];
        verificar(count($regras) === 2, "conjunto {$conjuntoId} possui dois grupos");

        $regraBistro = null;
        $regraPufe = null;
        foreach ($regras as $regra) {
            if (($regra['nome_grupo'] ?? '') === 'Bistrô') {
                $regraBistro = $regra;
            } elseif (($regra['nome_grupo'] ?? '') === 'Pufes') {
                $regraPufe = $regra;
            }
        }

        verificar(is_array($regraBistro), "conjunto {$conjuntoId} expõe regra de Bistrô");
        verificar(is_array($regraPufe), "conjunto {$conjuntoId} expõe regra de Pufes");
        if (!is_array($regraBistro) || !is_array($regraPufe)) {
            continue;
        }

        foreach ($candidatos as $nome => $produto) {
            $permitido = regraCasaComProduto($regraBistro, $produto)
                || regraCasaComProduto($regraPufe, $produto);
            $deveriaPermitir = in_array($nome, $esperado['permitidos'], true);
            verificar(
                $permitido === $deveriaPermitir,
                "conjunto {$conjuntoId}: {$nome} " . ($deveriaPermitir ? 'permitido' : 'proibido')
            );
        }

        $gruposDetalhados = gruposConjunto($conjuntoId);
        $grupoFixo = null;
        foreach ($gruposDetalhados as $grupo) {
            if ((int)($grupo['produto_id'] ?? 0) > 0) {
                $grupoFixo = $grupo;
                break;
            }
        }
        $produtoFixoEsperado = (int)$candidatos[$esperado['bistro']]['id'];
        verificar(is_array($grupoFixo), "conjunto {$conjuntoId} possui grupo automático");
        verificar(
            is_array($grupoFixo) && (int)($grupoFixo['produto_id'] ?? 0) === $produtoFixoEsperado,
            "conjunto {$conjuntoId} aponta para o bistrô fixo correto"
        );
        verificar(
            is_array($grupoFixo) && trim((string)($grupoFixo['produto_fixo_nome'] ?? '')) !== '',
            "conjunto {$conjuntoId} entrega os dados do produto automático"
        );
    }

    verificar(quantidadePadraoNovaCor(4, 4) === 4, 'um conjunto sugere quatro pufes da primeira cor');
    verificar(quantidadePadraoNovaCor(4, 8) === 4, 'dois conjuntos sugerem quatro pufes da primeira cor');
    verificar(quantidadePadraoNovaCor(4, 4) === 4, 'segunda cor recebe os quatro pufes restantes');
    verificar(quantidadePadraoNovaCor(4, 2) === 2, 'última cor recebe somente o saldo restante');
    verificar(quantidadePadraoNovaCor(1, 2) === 2, 'grupo unitário acompanha duas unidades do conjunto');

    $index = obter(BASE_URL . '/index.php');
    preg_match('/edit\.php\?id=(\d+)/', $index, $pedidoEncontrado);
    verificar(isset($pedidoEncontrado[1]), 'há pedido existente para testar a renderização da edição sem salvar');

    $formularios = ['create' => obter(BASE_URL . '/create.php')];
    if (isset($pedidoEncontrado[1])) {
        $formularios['edit'] = obter(BASE_URL . '/edit.php?id=' . (int)$pedidoEncontrado[1]);
    }

    $interpolacoes = ['produtoIdInput', 'tipoLinha', 'itemIndex', 'tipoItemLocVend'];
    foreach ($formularios as $nomeFormulario => $html) {
        foreach ($interpolacoes as $variavel) {
            verificar(
                str_contains($html, 'value="${escapeHtml(String(' . $variavel . '))}"'),
                "{$nomeFormulario}: interpolação JS {$variavel} sobrevive à renderização PHP"
            );
        }
        verificar(
            str_contains($html, '$novaLinha.find(\'input[name="produto_id[]"]\').attr(\'value\', String(produtoIdInput)).val(produtoIdInput);'),
            "{$nomeFormulario}: ID é preenchido explicitamente na linha dinâmica"
        );
        verificar(
            str_contains($html, '$novaLinha.find(\'input[name="tipo_linha[]"]\').attr(\'value\', String(tipoLinha)).val(tipoLinha);'),
            "{$nomeFormulario}: tipo da linha é preenchido explicitamente"
        );
        verificar(
            str_contains($html, '$novaLinha.find(\'input[name="ordem[]"]\').attr(\'value\', String(itemIndex)).val(itemIndex);'),
            "{$nomeFormulario}: ordem é preenchida explicitamente"
        );
        verificar(
            str_contains($html, '$novaLinha.find(\'input[name="tipo_item[]"]\').attr(\'value\', String(tipoItemLocVend)).val(tipoItemLocVend);'),
            "{$nomeFormulario}: tipo do item é preenchido explicitamente"
        );
        verificar(
            str_contains($html, 'const regrasCompativeisProduto = regras.filter(function(regra)'),
            "{$nomeFormulario}: inclusão possui guarda prévia contra produto incompatível"
        );
        verificar(
            str_contains($html, "mensagens.push('Produto fora da montagem: '"),
            "{$nomeFormulario}: salvamento detecta linha órfã dentro do conjunto"
        );
        verificar(
            str_contains($html, 'function sincronizarItensFixosConjunto($rowConjunto, criarAusentes = false)'),
            "{$nomeFormulario}: sincroniza produtos automáticos do conjunto"
        );
        verificar(
            str_contains($html, "Definido pelo conjunto · para trocar, altere o conjunto completo"),
            "{$nomeFormulario}: identifica o produto automático como travado"
        );
        verificar(
            str_contains($html, "Math.min(Math.round(quantidadePorConjunto), quantidadeFaltante)"),
            "{$nomeFormulario}: distribui novas cores sem ultrapassar o saldo"
        );
    }

    $indexOrcamentos = obter(ORCAMENTOS_URL . '/index.php');
    preg_match('/edit\.php\?id=(\d+)/', $indexOrcamentos, $orcamentoEncontrado);
    verificar(isset($orcamentoEncontrado[1]), 'há orçamento existente para testar a edição sem salvar');

    $formulariosOrcamento = ['orcamento-create' => obter(ORCAMENTOS_URL . '/create.php')];
    if (isset($orcamentoEncontrado[1])) {
        $formulariosOrcamento['orcamento-edit'] = obter(ORCAMENTOS_URL . '/edit.php?id=' . (int)$orcamentoEncontrado[1]);
    }
    foreach ($formulariosOrcamento as $nomeFormulario => $html) {
        verificar(
            str_contains($html, 'function sincronizarItensFixosConjunto($rowConjunto, criarAusentes = false)'),
            "{$nomeFormulario}: sincroniza produtos automáticos do conjunto"
        );
        verificar(
            str_contains($html, 'Definido pelo conjunto · para trocar, altere o conjunto completo'),
            "{$nomeFormulario}: trava o produto automático"
        );
        verificar(
            str_contains($html, 'Math.min(Math.round(qtdPorConjunto), faltante)'),
            "{$nomeFormulario}: distribui pufes por cor sem ultrapassar o saldo"
        );
        verificar(
            str_contains($html, 'produto_fixo_nome'),
            "{$nomeFormulario}: recebe os dados do produto fixo"
        );
    }
} catch (Throwable $erro) {
    $falhas[] = $erro->getMessage();
    echo '[ERRO] ' . $erro->getMessage() . "\n";
}

echo "\nAprovações: {$aprovacoes}; falhas: " . count($falhas) . "\n";
exit($falhas === [] ? 0 : 1);

