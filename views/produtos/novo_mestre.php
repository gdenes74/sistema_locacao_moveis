<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';

if (!isLoggedIn()) redirect('views/usuarios/login.php');
if (!hasAccess('admin')) { http_response_code(403); exit('Acesso restrito.'); }
if (empty($_SESSION['csrf_motor_cores'])) $_SESSION['csrf_motor_cores'] = bin2hex(random_bytes(32));
$db = (new Database())->getConnection();
$motor = new MotorCores($db);
$erro = '';
$modeloEdicao = null;
$idEdicaoSolicitada = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT);
if ($idEdicaoSolicitada) {
    $consultaEdicao = $db->prepare("SELECT m.*, p.nome_produto FROM modelos_imagem m LEFT JOIN produtos p ON p.id=m.produto_origem_id WHERE m.id=? AND m.ativo=1");
    $consultaEdicao->execute([$idEdicaoSolicitada]);
    $modeloEdicao = $consultaEdicao->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$modeloEdicao || $modeloEdicao['status_geometria'] !== 'PENDENTE' || $modeloEdicao['status_recorte'] !== 'PENDENTE' || !$modeloEdicao['arquivo_base']) {
        http_response_code(404); exit('Mestre pendente não encontrado para edição.');
    }
}

function pngDoMestre(string $valor): string {
    $prefixo = 'data:image/png;base64,';
    if (!str_starts_with($valor, $prefixo)) throw new InvalidArgumentException('Imagem PNG inválida.');
    $binario = base64_decode(substr($valor, strlen($prefixo)), true);
    if ($binario === false || strlen($binario) < 1024 || strlen($binario) > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('Imagem vazia ou grande demais.');
    }
    $info = getimagesizefromstring($binario);
    if (!$info || $info['mime'] !== 'image/png' || $info[0] !== 800 || $info[1] !== 800) {
        throw new InvalidArgumentException('O mestre e o recorte precisam ser PNG de 800 × 800 pixels.');
    }
    return $binario;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baseFisica = null;
    $mascaraFisica = null;
    try {
        if (!hash_equals($_SESSION['csrf_motor_cores'], (string)($_POST['csrf'] ?? ''))) throw new DomainException('Sessão expirada.');
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 23 * 1024 * 1024) throw new DomainException('Arquivo grande demais.');
        $nome = trim((string)($_POST['nome'] ?? ''));
        $familiaId = filter_var($_POST['familia_variante_id'] ?? null, FILTER_VALIDATE_INT);
        $produtoOrigemId = filter_var($_POST['produto_origem_id'] ?? null, FILTER_VALIDATE_INT);
        $idEdicao = filter_var($_POST['modelo_edicao_id'] ?? null, FILTER_VALIDATE_INT);
        $registroEdicao = null;
        if ($idEdicao) {
            $qEdicao = $db->prepare("SELECT * FROM modelos_imagem WHERE id=? AND ativo=1 AND status_geometria='PENDENTE' AND status_recorte='PENDENTE' FOR UPDATE");
            $qEdicao->execute([$idEdicao]);
            $registroEdicao = $qEdicao->fetch(PDO::FETCH_ASSOC);
            if (!$registroEdicao) throw new DomainException('Este mestre não está mais disponível para edição.');
            $nome = (string)$registroEdicao['nome'];
            $familiaId = (int)$registroEdicao['familia_variante_id'];
            $produtoOrigemId = (int)$registroEdicao['produto_origem_id'];
        }
        $pontos = json_decode((string)($_POST['pontos'] ?? ''), true);
        $regioes = isset($_POST['regioes']) ? json_decode((string)$_POST['regioes'], true) : ($pontos ? [$pontos] : []);
        if ($nome === '' || mb_strlen($nome) > 150 || !$familiaId || !$produtoOrigemId || !is_array($pontos) || (count($pontos) > 0 && count($pontos) < 3) || count($pontos) > 100) {
            throw new InvalidArgumentException('Informe nome, produto e tipo visual; para marcar o tecido, use pelo menos três pontos ou deixe sem marcação.');
        }
        foreach ($pontos as $ponto) {
            if (!is_array($ponto) || count($ponto) !== 2 || !is_numeric($ponto[0]) || !is_numeric($ponto[1])
                || $ponto[0] < 0 || $ponto[0] > 800 || $ponto[1] < 0 || $ponto[1] > 800) {
                throw new InvalidArgumentException('Marcação do tecido inválida.');
            }
        }
        if (!is_array($regioes) || count($regioes) > 20) throw new InvalidArgumentException('Áreas de tecido inválidas.');
        foreach ($regioes as $regiao) {
            if (!is_array($regiao) || count($regiao) < 3 || count($regiao) > 100) throw new InvalidArgumentException('Feche cada área com pelo menos três pontos.');
            foreach ($regiao as $ponto) {
                if (!is_array($ponto) || count($ponto) !== 2 || !is_numeric($ponto[0]) || !is_numeric($ponto[1]) || $ponto[0] < 0 || $ponto[0] > 800 || $ponto[1] < 0 || $ponto[1] > 800) throw new InvalidArgumentException('Ponto de tecido inválido.');
            }
        }
        $verificarFamilia = $db->prepare('SELECT id, modo_impacto_estoque, produto_generico_id FROM familias_variantes WHERE id = ? AND ativo=1');
        $verificarFamilia->execute([$familiaId]);
        $familiaEscolhida = $verificarFamilia->fetch(PDO::FETCH_ASSOC);
        if (!$familiaEscolhida) throw new DomainException('Tipo de produto não encontrado.');
        if ($familiaEscolhida['modo_impacto_estoque'] === 'FORRACAO_PRODUCAO'
            && (int)$familiaEscolhida['produto_generico_id'] !== $produtoOrigemId) {
            throw new DomainException('Para forração sob demanda, escolha o produto forrado correspondente a este tipo de mestre.');
        }
        $verificarProduto = $db->prepare("SELECT id FROM produtos WHERE id=? AND tipo_produto='COMPOSTO'");
        $verificarProduto->execute([$produtoOrigemId]);
        if (!$verificarProduto->fetchColumn()) throw new DomainException('Produto montado não encontrado.');
        $base = pngDoMestre((string)($_POST['base_png'] ?? ''));
        $mascara = $regioes ? pngDoMestre((string)($_POST['mascara_png'] ?? '')) : null;
        $pasta = realpath(__DIR__ . '/../../assets/uploads/motor_cores/modelos');
        if (!$pasta || !is_writable($pasta)) throw new RuntimeException('Pasta de mestres indisponível.');
        $identificador = date('YmdHis') . '_' . bin2hex(random_bytes(8));
        $baseNome = 'mestre_' . $identificador . '.png';
        $mascaraNome = 'recorte_' . $identificador . '.png';
        $baseFisica = $pasta . DIRECTORY_SEPARATOR . $baseNome;
        $mascaraFisica = $mascara !== null ? $pasta . DIRECTORY_SEPARATOR . $mascaraNome : null;
        if (file_put_contents($baseFisica, $base, LOCK_EX) !== strlen($base)
            || ($mascara !== null && file_put_contents($mascaraFisica, $mascara, LOCK_EX) !== strlen($mascara))) {
            throw new RuntimeException('Não foi possível salvar o mestre.');
        }
        $db->beginTransaction();
        $observacoes = $mascara !== null ? 'Áreas de tecido marcadas na tela: ' . json_encode($regioes) : 'Foto pronta importada; recorte ainda não marcado.';
        if ($registroEdicao) {
            $id = (int)$registroEdicao['id'];
            $stmt = $db->prepare('UPDATE modelos_imagem SET arquivo_base=?, arquivo_mascara=?, observacoes=?, atualizado_em=NOW() WHERE id=?');
            $stmt->execute(['assets/uploads/motor_cores/modelos/' . $baseNome, $mascara !== null ? 'assets/uploads/motor_cores/modelos/' . $mascaraNome : null, $observacoes, $id]);
        } else {
            $sql = "INSERT INTO modelos_imagem
                (familia_variante_id, produto_origem_id, nome, slug, arquivo_base, arquivo_mascara, origem, versao,
                 status_geometria, status_recorte, observacoes, ativo, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, 'MARCAÇÃO_NA_TELA', 1, 'PENDENTE', 'PENDENTE', ?, 1, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $familiaId, $produtoOrigemId, $nome, MotorCores::normalizar($nome) . '-' . $identificador,
                'assets/uploads/motor_cores/modelos/' . $baseNome,
                $mascara !== null ? 'assets/uploads/motor_cores/modelos/' . $mascaraNome : null,
                $observacoes, (int)$_SESSION['user_id'],
            ]);
            $id = (int)$db->lastInsertId();
        }
        $historico = $db->prepare('INSERT INTO historico_motor_cores (entidade, entidade_id, acao, estado_novo, usuario_id) VALUES (?, ?, ?, ?, ?)');
        $historico->execute(['modelos_imagem', $id, $registroEdicao ? 'MESTRE_REMARCADO' : 'MESTRE_CRIADO', json_encode(['nome' => $nome, 'familia_id' => $familiaId, 'arquivo_base' => $baseNome, 'arquivo_mascara' => $mascaraNome]), (int)$_SESSION['user_id']]);
        $db->commit();
        $_SESSION['success_message'] = $registroEdicao ? 'Marcação do mestre atualizada para revisão. Nenhuma foto de produto foi alterada.' : 'Modelo mestre salvo para revisão. Nenhuma foto de produto foi alterada.';
        redirect('views/produtos/cores_imagens.php');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($baseFisica && is_file($baseFisica)) unlink($baseFisica);
        if ($mascaraFisica && is_file($mascaraFisica)) unlink($mascaraFisica);
        $erro = $e->getMessage();
    }
}

$familias = array_values(array_filter($motor->listarFamilias(),static fn($f)=>(int)$f['ativo']===1));
$vinculosMestre=$motor->listarVinculosProdutos();
$amostrasAprovadas = array_values(array_filter($motor->listarAmostras(), static fn($a) => (int)$a['aprovada'] === 1));
$referenciasPorCor = [];
foreach ($amostrasAprovadas as $amostra) {
    $corId = (int)$amostra['cor_padrao_id'];
    $referenciasPorCor[$corId]['fallback'] ??= (int)$amostra['id'];
    if ($amostra['familia_variante_id'] === null) {
        $referenciasPorCor[$corId]['global'] ??= (int)$amostra['id'];
    } else {
        $referenciasPorCor[$corId]['familias'][(int)$amostra['familia_variante_id']] ??= (int)$amostra['id'];
    }
}
$coresComReferencia = array_values(array_filter($motor->listarCores(),
    static fn(array $cor): bool => (int)$cor['ativo'] === 1 && isset($referenciasPorCor[(int)$cor['id']])));
$catalogoCompleto = $motor->listarCatalogoProdutos();
$taxonomiaCompleta = $db->query("SELECT se.id AS secao_id, se.nome AS secao_nome,
        cat.id AS categoria_id, cat.nome AS categoria_nome,
        sub.id AS subcategoria_id, sub.nome AS subcategoria_nome
    FROM secoes se
    LEFT JOIN categorias cat ON cat.secao_id = se.id
    LEFT JOIN subcategorias sub ON sub.categoria_id = cat.id
    ORDER BY se.nome, cat.nome, sub.nome")->fetchAll(PDO::FETCH_ASSOC);
$catalogoProdutos = array_values(array_filter(
    $catalogoCompleto,
    static fn(array $produto): bool => ($produto['tipo_produto'] ?? '') === 'COMPOSTO'
));
$secoesCatalogo = [];
foreach ($catalogoProdutos as $p) { if (($p['secao_id'] ?? null) !== null) $secoesCatalogo[(int)$p['secao_id']] = $p['secao_nome']; }
$page_title = 'Criar modelo mestre';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3">
  <a href="cores_imagens.php" class="btn btn-link pl-0">← Voltar para Montar fotos por cor</a>
  <a href="configurar_motor.php" id="configurar-mestre-produto" class="btn btn-outline-primary">Configurar novo produto / família</a>
  <h1 class="h3"><?= $modeloEdicao ? 'Editar marcação do modelo mestre' : 'Criar modelo mestre' ?></h1>
  <p><?= $modeloEdicao ? 'Refaça somente as áreas de tecido deste rascunho. A foto-base e o produto de origem permanecem os mesmos; nenhuma foto do catálogo será alterada.' : 'Escolha o produto, envie uma foto preparada do computador e marque apenas o tecido. Teste as cores aprovadas antes de guardar o modelo. Faça essa marcação uma vez por foto-base: ela será reutilizada nas outras cores. Madeira, metal e fundo ficam fora da marcação.' ?></p>
  <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  <form method="post" id="form-mestre" class="card card-body" enctype="application/x-www-form-urlencoded">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>">
    <?php if ($modeloEdicao): ?><input type="hidden" name="modelo_edicao_id" value="<?= (int)$modeloEdicao['id'] ?>"><?php endif; ?>
    <input type="hidden" name="base_png" id="base-png"><input type="hidden" name="mascara_png" id="mascara-png"><input type="hidden" name="pontos" id="pontos">
    <input type="hidden" name="regioes" id="regioes">
    <div class="row"><div class="col-md-3 form-group"><label for="mestre-secao">1. Seção do cadastro</label><select class="form-control" id="mestre-secao"><option value="">Todas as seções</option></select><small class="form-text text-muted">Mostra todas as seções cadastradas, como na lista de produtos.</small></div><div class="col-md-3 form-group"><label for="mestre-categoria">Categoria do cadastro</label><select class="form-control" id="mestre-categoria"><option value="">Todas as categorias</option></select><small id="mestre-categoria-info" class="form-text text-muted">Selecione a categoria do produto que receberá o mestre.</small></div><div class="col-md-3 form-group"><label for="mestre-subcategoria">Subcategoria do cadastro</label><select class="form-control" id="mestre-subcategoria"><option value="">Todas as subcategorias</option></select></div><div class="col-md-3 form-group"><label for="mestre-produto">Produto montado</label><select class="form-control" id="mestre-produto" name="produto_origem_id" required><option value="">Escolha o produto</option></select><small id="mestre-produto-info" class="form-text text-muted">Depois dos filtros, aparecem somente produtos montados que podem receber uma foto mestre.</small></div></div>
    <div class="row"><div class="col-md-4 form-group"><label for="nome">Nome do modelo (automático)</label><input class="form-control" id="nome" name="nome" maxlength="150" readonly required><small class="form-text text-muted">Identifica o formato do produto; a cor não faz parte do modelo.</small></div>
    <div class="col-md-4 form-group"><label for="familia">Tipo de produto</label><select class="form-control" id="familia" name="familia_variante_id" required><option value="">Escolha</option><?php foreach ($familias as $familia): ?><option value="<?= (int)$familia['id'] ?>" data-produto-generico="<?= (int)$familia['produto_generico_id'] ?>" data-produtos="<?= htmlspecialchars(json_encode(array_values(array_map(static fn($v)=>(int)$v['produto_id'],array_filter($vinculosMestre,static fn($v)=>(int)$v['ativo']===1 && (int)$v['familia_variante_id']===(int)$familia['id'])))),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($familia['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4 form-group border rounded bg-light p-3"><label for="foto-mestre" class="font-weight-bold">2. <?= $modeloEdicao ? 'Foto-base já carregada' : 'Envie a foto preparada' ?></label><input type="file" class="form-control-file" id="foto-mestre" accept="image/png,image/jpeg" <?= $modeloEdicao ? '' : 'required' ?>><small class="form-text text-muted"><?= $modeloEdicao ? 'Não precisa enviar outra foto. Se escolher um arquivo, ele substituirá a base deste rascunho.' : 'PNG ou JPG; a foto só é guardada quando tu confirmar no final.' ?></small></div></div>
    <div class="d-flex flex-wrap align-items-center mb-2"><button type="button" id="desfazer-ponto" class="btn btn-outline-secondary btn-sm mr-2" disabled>Desfazer último ponto</button><button type="button" id="fechar-area" class="btn btn-outline-primary btn-sm mr-2" disabled>Fechar área e marcar outra parte</button><button type="button" id="limpar-pontos" class="btn btn-outline-secondary btn-sm mr-2" disabled>Recomeçar marcação</button><span id="estado-mestre" role="status">Envie uma foto para liberar a marcação.</span></div>
    <p class="small text-muted">Clique ao redor do assento. Para marcar também o encosto, use “Fechar área e marcar outra parte” e contorne o encosto separadamente. Use PNG ou JPG com pelo menos 800 pixels no lado maior; a foto pode ser retangular e será centralizada sem esticar. O sistema não remove o fundo.</p>
    <div class="row">
      <div class="col-lg-6"><h2 class="h6">Foto-base e áreas de tecido</h2><canvas id="canvas-mestre" width="800" height="800" style="width:100%;height:auto;border:1px solid #bbb;cursor:crosshair;background:#eee" aria-label="Foto para marcar o tecido"></canvas></div>
      <div class="col-lg-6"><h2 class="h6">Prévia com a cor aprovada</h2>
        <label for="mestre-amostra">Referência de cor aprovada (somente para testar)</label><select id="mestre-amostra" class="form-control mb-2"><option value="">Escolha uma cor aprovada</option><?php foreach ($coresComReferencia as $cor): ?><?php $ref = $referenciasPorCor[(int)$cor['id']]; ?><option value="<?= (int)($ref['global'] ?? $ref['fallback']) ?>" data-global="<?= (int)($ref['global'] ?? 0) ?>" data-fallback="<?= (int)$ref['fallback'] ?>" data-familias="<?= htmlspecialchars(json_encode($ref['familias'] ?? []), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cor['nome']) ?></option><?php endforeach; ?></select><small class="form-text text-muted mb-2">Uma opção por cor aprovada, em ordem alfabética. A referência global é preferida; quando houver amostra própria deste tipo de produto, ela será usada. A cor de teste não vira o nome do mestre nem é gravada ao guardá-lo.</small>
        <button type="button" id="mestre-ver-previa" class="btn btn-outline-primary mb-2" disabled>Ver prévia com esta cor</button>
        <p id="mestre-previa-estado" role="status" class="small">Envie a foto, marque o tecido e escolha uma referência aprovada.</p>
        <img id="mestre-referencia" alt="Referência da cor selecionada" style="max-height:100px;max-width:100%" hidden>
        <canvas id="canvas-mestre-previa" width="800" height="800" style="width:100%;height:auto;border:1px solid #bbb" aria-label="Prévia da cor aplicada ao tecido" hidden></canvas>
        <a id="mestre-baixar-previa" class="btn btn-outline-secondary btn-sm mt-2" download="previa-modelo.png" hidden>Baixar prévia</a>
      </div>
    </div>
    <p class="small text-muted mt-2">A prévia muda somente o tecido marcado e mantém o relevo e as sombras da foto original. Confira o resultado: áreas muito escuras ou reflexos fortes podem limitar a fidelidade do tom. Guardar o modelo mantém a foto-base original e a marcação; a prévia não publica fotos no catálogo.</p>
    <button class="btn btn-primary" id="salvar-mestre" disabled><?= $modeloEdicao ? 'Salvar nova marcação para revisão' : 'Guardar modelo e marcação para revisão' ?></button>
  </form>
</div>
<?php
$regioesEdicao = [];
$preselecaoMestre=['produto'=>(int)($_GET['produto']??0),'familia'=>(int)($_GET['familia']??0)];
if ($modeloEdicao && preg_match('/\[\[.*\]\]/s', (string)$modeloEdicao['observacoes'], $achou)) {
    $decodado = json_decode($achou[0], true) ?: [];
    // Mestres antigos guardavam um único contorno como lista de pontos; o editor atual trabalha com lista de áreas.
    $regioesEdicao = isset($decodado[0][0]) && is_numeric($decodado[0][0]) ? [$decodado] : $decodado;
}
$inline_js_setup = 'window.MOBEL_MESTRE_PRESELECAO = '.json_encode($preselecaoMestre).';' . 'window.MOBEL_MESTRE_CATALOGO = ' . json_encode($catalogoProdutos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';window.MOBEL_MESTRE_TAXONOMIA = ' . json_encode($taxonomiaCompleta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';window.MOBEL_MESTRE_EXISTENTE = ' . json_encode($modeloEdicao ? ['id'=>(int)$modeloEdicao['id'], 'produtoId'=>(int)$modeloEdicao['produto_origem_id'], 'familiaId'=>(int)$modeloEdicao['familia_variante_id'], 'nome'=>$modeloEdicao['nome'], 'baseUrl'=>'modelo_base.php?id=' . (int)$modeloEdicao['id'] . '&revisao=1', 'regioes'=>$regioesEdicao] : null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
$extra_js = [BASE_URL . '/assets/js/motor_recoloracao.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/motor_recoloracao.js'), BASE_URL . '/assets/js/motor_mestre.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/motor_mestre.js')]; require_once __DIR__ . '/../includes/footer.php'; ?>
