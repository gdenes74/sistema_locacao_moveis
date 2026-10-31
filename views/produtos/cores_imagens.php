<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';

if (!isLoggedIn()) redirect('views/usuarios/login.php');
if (!hasAccess('admin')) {
    http_response_code(403);
    die('Acesso restrito à administração.');
}

$conn = (new Database())->getConnection();
$motor = new MotorCores($conn);
$usuarioId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (empty($_SESSION['csrf_motor_cores'])) $_SESSION['csrf_motor_cores'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_motor_cores'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página.');
        }
        $acao = (string)($_POST['acao'] ?? '');
        if ($acao === 'criar_cor') {
            $motor->criarCor($_POST, $usuarioId);
            $_SESSION['success_message'] = 'Cor cadastrada. A geração automática continua desligada até aprovação.';
        } elseif ($acao === 'criar_alias') {
            $motor->criarAlias((int)($_POST['cor_padrao_id'] ?? 0), (string)($_POST['alias'] ?? ''), !empty($_POST['familia_variante_id']) ? (int)$_POST['familia_variante_id'] : null, $usuarioId);
            $_SESSION['success_message'] = 'Alias cadastrado sem alterar produtos existentes.';
        } elseif ($acao === 'criar_familia') {
            $motor->criarFamilia($_POST, $usuarioId);
            $_SESSION['success_message'] = 'Família cadastrada. Ainda não está ligada a pedidos/orçamentos.';
        } elseif ($acao === 'salvar_familia') {
            $motor->salvarFamilia($_POST, $usuarioId);
            $_SESSION['success_message'] = 'Família atualizada. Os vínculos ativos passam a usar esta configuração.';
        } elseif ($acao === 'desativar_familia') {
            $motor->desativarFamilia((int)($_POST['familia_id'] ?? 0), $usuarioId);
            $_SESSION['success_message'] = 'Família desativada. Nenhum histórico foi apagado.';
        } elseif ($acao === 'salvar_vinculo_produto') {
            $motor->salvarVinculoProduto($_POST, $usuarioId);
            $_SESSION['success_message'] = 'Vínculo de produto salvo.';
        } elseif ($acao === 'desativar_vinculo_produto') {
            $motor->desativarVinculoProduto((int)($_POST['vinculo_id'] ?? 0), $usuarioId);
            $_SESSION['success_message'] = 'Vínculo desativado. Nenhum pedido, orçamento ou foto foi apagado.';
        } elseif ($acao === 'aprovar_modelo') {
            $motor->aprovarModelo((int)($_POST['modelo_id'] ?? 0), $usuarioId);
            $_SESSION['success_message'] = 'Modelo mestre aprovado. Agora é possível ligar produtos e cores a ele.';
        } elseif ($acao === 'descartar_modelo') {
            $motor->descartarModelo((int)($_POST['modelo_id'] ?? 0), $usuarioId);
            $_SESSION['success_message'] = 'Modelo mestre descartado. Ele saiu da fila, mas a operação permaneceu registrada no histórico.';
        } elseif ($acao === 'aprovar_amostra_global') {
            $n = $motor->aprovarAmostraGlobal((int)($_POST['amostra_id'] ?? 0), $usuarioId);
            $_SESSION['success_message'] = "Tom global aprovado. {$n} variante(s) física(s) liberada(s) para prévia; nenhuma foto foi publicada.";
        } elseif ($acao === 'criar_amostra') {
            $motor->criarAmostra($_POST, $_FILES['arquivo'] ?? [], $usuarioId);
            $_SESSION['success_message'] = 'Referência cadastrada como pendente. Revise a origem e aprove o tom global quando estiver confirmado.';
        } elseif ($acao === 'criar_amostra_produto') {
            $motor->criarAmostraDeProduto($_POST, $usuarioId);
            $_SESSION['success_message'] = 'Foto do catálogo copiada como referência pendente. O produto original não foi alterado.';
        } elseif ($acao === 'editar_amostra') {
            $motor->editarAmostra($_POST, $_FILES['arquivo'] ?? [], $usuarioId);
            $_SESSION['success_message'] = 'Referência atualizada e registrada no histórico. A aprovação/publicação continua separada.';
        } else {
            throw new InvalidArgumentException('Ação desconhecida.');
        }
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    $destino = 'views/produtos/cores_imagens.php';
    if (in_array($acao, ['criar_amostra', 'criar_amostra_produto', 'editar_amostra', 'aprovar_amostra_global'], true)) $destino .= '#amostras';
    elseif (in_array($acao, ['aprovar_modelo', 'descartar_modelo'], true)) $destino .= '#modelos';
    redirect($destino);
}

$resumo = $motor->resumo();
$cores = $motor->listarCores();
$familias = $motor->listarFamilias();
$vinculosProdutos = $motor->listarVinculosProdutos();
$amostras = $motor->listarAmostras();
$amostrasAprovadas = array_values(array_filter($amostras, static fn(array $a): bool => (int)$a['aprovada'] === 1 && (int)$a['ativo'] === 1));
$produtosComFoto = $motor->listarProdutosComFoto();
$modelos = $motor->listarModelos();
$variantesImagem = $motor->listarVariantesImagem();
$opcoesPrevia = $motor->listarOpcoesPrevia();
$catalogoProdutos = $motor->listarCatalogoProdutos();
$secoesCatalogo = [];
foreach ($catalogoProdutos as $p) { if (($p['secao_id'] ?? null) !== null) $secoesCatalogo[(int)$p['secao_id']] = $p['secao_nome']; }
$coresPrevia = [];
foreach ($opcoesPrevia as $opcao) {
    $coresPrevia[(int)$opcao['cor_id']] = $opcao['cor_nome'];
}
asort($coresPrevia, SORT_NATURAL | SORT_FLAG_CASE);
$page_title = 'Montagem por cor — móveis estofados e forrados';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div><h1 class="h3 mb-1">Montagem por cor — móveis estofados e forrados</h1><p class="text-muted mb-0">Escolha um produto real, confira sua composição e monte a foto apenas para cores compatíveis.</p></div>
    <div><a class="btn btn-primary mr-2" href="aprovar_foto_pronta.php">Aprovar foto pronta</a><a class="btn btn-outline-primary mr-2" href="#amostras" id="abrir-cores-referencias">Cores e referências</a><a class="btn btn-outline-primary mr-2" href="novo_mestre.php">Criar modelo mestre</a><a class="btn btn-outline-secondary" href="preparar_fotos.php">Preparar cores e produtos</a></div>
  </div>
  <div class="card card-outline card-primary mb-3" id="montar">
    <div class="card-header"><a class="btn btn-outline-success" href="#modelos">Revisar e aprovar mestres</a> <a class="btn btn-outline-primary" href="#amostras">Revisar amostras de tecido</a></div>
    <div class="card-header"><a href="configurar_motor.php" id="abrir-configuracao-motor" class="btn btn-primary">Configurar novo produto no motor / editar famílias</a><small class="d-block mt-2">Cadastre a família, vincule o produto e crie o mestre pela tela. Forrações usam amostras aprovadas; capas físicas mantêm as cores da própria família.</small></div>
    <div class="card-body">
      <div class="alert alert-info">Banco, coletivos e aparadores forrados <strong>sem capa removível</strong> usam o mestre aprovado e as cores aprovadas diretamente na linha do orçamento/pedido. A foto é ilustrativa e contextual; a estrutura continua sendo o único estoque físico. Esta montagem em lote abaixo permanece para produtos com capas cadastradas.</div>
      <h2 class="h5">1. Escolha o produto real</h2>
      <div class="row align-items-end">
        <div class="col-md-2 form-group"><label for="motor-secao">Seção</label><select id="motor-secao" class="form-control"><option value="">Todas as seções</option><?php foreach($secoesCatalogo as $id=>$nome): ?><option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2 form-group"><label for="motor-categoria">Categoria</label><select id="motor-categoria" class="form-control"><option value="">Todas as categorias</option></select></div>
        <div class="col-md-2 form-group"><label for="motor-subcategoria">Subcategoria</label><select id="motor-subcategoria" class="form-control"><option value="">Todas as subcategorias</option></select></div>
        <div class="col-md-3 form-group"><label for="motor-busca">Buscar nome/ID</label><input id="motor-busca" class="form-control" placeholder="ex.: sofá sem braço"></div><div class="col-md-3 form-group"><label for="motor-produto">Produto cadastrado</label>
          <select id="motor-produto" class="form-control"><option value="">Escolha um produto</option>
            <?php foreach ($catalogoProdutos as $produto): ?><?php $repeteCor = $produto['cor'] && stripos((string)$produto['nome_produto'], (string)$produto['cor']) !== false; ?><option value="<?= (int)$produto['produto_id'] ?>" data-secao-id="<?= (int)($produto['secao_id'] ?? 0) ?>" data-categoria-id="<?= (int)($produto['categoria_id'] ?? 0) ?>" data-subcategoria-id="<?= (int)($produto['subcategoria_id'] ?? 0) ?>" data-secao="<?= htmlspecialchars($produto['secao_nome'] ?? '') ?>" data-categoria="<?= htmlspecialchars($produto['categoria_nome'] ?? '') ?>" data-subcategoria="<?= htmlspecialchars($produto['subcategoria_nome'] ?? '') ?>"><?= htmlspecialchars($produto['nome_produto']) ?><?= $produto['cor'] && !$repeteCor ? ' — ' . htmlspecialchars($produto['cor']) : '' ?></option><?php endforeach; ?>
          </select><small class="form-text text-muted">O catálogo inclui produtos ainda pendentes; só a geração/publicação exige mestre, referência e componente compatível.</small>
          <div id="motor-produto-info" class="small text-muted mt-2">Selecione qualquer produto do catálogo; produtos sem mestre aparecem como pendentes.</div>
        </div>
        <div class="col-md-4 form-group mb-md-0"><label for="motor-modelo">Foto-base (mestre)</label>
          <select id="motor-modelo" class="form-control"><option value="">Escolha o modelo</option>
            <?php foreach ($modelos as $modelo): ?><?php if ($modelo['ativo'] && $modelo['status_geometria'] === 'APROVADA' && $modelo['status_recorte'] === 'APROVADO'): ?><option value="<?= (int)$modelo['id'] ?>"><?= htmlspecialchars($modelo['nome']) ?></option><?php endif; ?><?php endforeach; ?>
          </select><a id="criar-mestre-produto" class="btn btn-sm btn-outline-primary mt-2" href="novo_mestre.php">Criar ou revisar o mestre deste produto</a>
        </div>
        <div class="col-md-4 form-group mb-md-0"><label for="motor-cor">Cor pronta para montagem</label>
          <select id="motor-cor" class="form-control" disabled><option value="">Escolha primeiro um modelo</option></select>
        </div>
        <div class="col-md-4"><button type="button" id="motor-gerar-lote" class="btn btn-primary btn-block">Ver fotos para montar</button></div>
      </div>
      <p class="small text-muted mt-3 mb-2">Nesta montagem em lote só aparecem cores com capa física cadastrada e referência de imagem pronta. A quantidade exibida é a das capas, não a do produto composto. Esta tela não altera estoque.</p>
      <h2 class="h5 mt-3">2. Confira e aprove a prévia</h2>
      <p id="motor-estado" role="status" class="mb-2">Selecione uma cor para começar.</p>
      <button type="button" id="motor-salvar-lote" class="btn btn-success mb-3" hidden disabled>3. Publicar prévias aprovadas dos produtos sem foto</button>
      <div id="motor-resultados" class="row"></div>
      <div class="alert alert-light border mt-3 mb-0">Aprovar a prévia registra a imagem derivada, mas não troca a foto do produto. A publicação é uma ação separada, registrada no histórico. Para vincular cores ou produtos ainda pendentes, use “Preparar cores e produtos”.</div>
    </div>
  </div>

  <details class="mb-3"><summary class="btn btn-outline-secondary mb-3">Ver configurações avançadas e pendências técnicas</summary>
  <div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i>Os números abaixo descrevem apenas o estágio atual do motor, começando por Pufe Forrado e Pufe Thai. Não representam todas as cores nem todos os produtos da empresa.</div>
  <div class="row">
    <?php foreach (['Nomes de cor no motor'=>'cores_padrao','Sinônimos de busca'=>'cor_aliases','Tipos de produto'=>'familias_variantes','Referências de cor (inclusive históricas)'=>'amostras_cor','Modelos mestres'=>'modelos_imagem','Produtos ligados a modelos'=>'variantes_imagem'] as $rotulo=>$chave): ?>
      <div class="col-6 col-md-2"><div class="small-box bg-light"><div class="inner"><h3><?= (int)$resumo[$chave] ?></h3><p><?= htmlspecialchars($rotulo) ?></p></div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="card card-outline card-primary">
    <div class="card-header p-0"><ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#cores">Cores e aliases</a></li>
      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#familias">Famílias e variantes</a></li>
      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#amostras">Amostras</a></li>
      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#modelos">Modelos e aprovação</a></li>
    </ul></div>
    <div class="card-body tab-content">
      <div id="cores" class="tab-pane fade show active">
        <div class="row"><div class="col-lg-8"><div class="table-responsive"><table class="table table-sm table-striped">
<thead><tr><th>Cor padrão</th><th>Nome técnico</th><th>Família operacional</th><th>Aliases</th><th>Referência</th><th>Automático</th></tr></thead><tbody>
          <?php foreach ($cores as $cor): ?><tr><td><?= htmlspecialchars($cor['nome']) ?></td><td><code><?= htmlspecialchars($cor['slug_normalizado'] ?? '') ?></code></td><td><?= htmlspecialchars($cor['familia_operacional'] ?? '') ?></td><td><?= htmlspecialchars($cor['aliases'] ?? '') ?></td><td><?php if ((int)$cor['amostras_aprovadas'] > 0): ?><span class="badge badge-success">aprovada</span><?php elseif ((int)$cor['total_amostras'] > 0): ?><span class="badge badge-warning">pendente</span><?php else: ?><span class="badge badge-secondary">sem referência</span><?php endif; ?> <small>(<?= (int)$cor['total_amostras'] ?>)</small></td><td><?= !empty($cor['automatico_permitido']) ? '<span class="badge badge-success">permitido</span>' : '<span class="badge badge-secondary">revisão</span>' ?></td></tr><?php endforeach; ?>
          </tbody></table></div></div>
          <div class="col-lg-4">
            <form method="post" class="card card-body bg-light"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="criar_cor"><h5>Nova cor canônica</h5><div class="form-group"><label>Nome</label><input name="nome" class="form-control" required></div><div class="form-group"><label>Família operacional</label><input name="familia_operacional" class="form-control"></div><div class="form-group"><label>Hex de referência (opcional)</label><input name="hex_referencia" class="form-control" placeholder="#RRGGBB"></div><div class="form-group"><label>Observações</label><textarea name="observacoes" class="form-control"></textarea></div><button class="btn btn-primary">Cadastrar cor</button></form>
            <form method="post" class="card card-body bg-light"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="criar_alias"><h5>Novo alias</h5><div class="form-group"><label>Cor padrão</label><select name="cor_padrao_id" class="form-control" required><option value="">Selecione</option><?php foreach($cores as $cor): ?><option value="<?= (int)$cor['id'] ?>"><?= htmlspecialchars($cor['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Nome alternativo</label><input name="alias" class="form-control" required></div><div class="form-group"><label>Família específica (opcional)</label><select name="familia_variante_id" class="form-control"><option value="">Global</option><?php foreach($familias as $familia): ?><option value="<?= (int)$familia['id'] ?>"><?= htmlspecialchars($familia['nome']) ?></option><?php endforeach; ?></select></div><button class="btn btn-outline-primary">Cadastrar alias</button></form>
          </div></div>
      </div>
      <div id="familias" class="tab-pane fade">
        <div class="alert alert-primary"><strong>Produtos e vínculos do motor</strong><br>Cadastre a regra uma vez para cada família. Depois vincule o produto genérico “cor a definir” e as variantes físicas. Desativar preserva orçamento, pedido, foto e histórico.</div>
        <div class="row"><div class="col-lg-7"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Família</th><th>Genérico</th><th>Modo</th><th>Vínculos</th><th></th></tr></thead><tbody><?php foreach($familias as $familia): ?><tr class="<?= empty($familia['ativo'])?'text-muted':'' ?>"><td><?= htmlspecialchars($familia['nome']) ?> <?= empty($familia['ativo'])?'<span class="badge badge-secondary">inativa</span>':'' ?></td><td><?= htmlspecialchars($familia['produto_generico'] ?? '—') ?></td><td><code><?= htmlspecialchars($familia['modo_impacto_estoque']) ?></code></td><td><?= (int)$familia['total_variantes'] ?></td><td><button type="button" class="btn btn-sm btn-outline-primary editar-familia" data-familia='<?= htmlspecialchars(json_encode($familia, JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT), ENT_QUOTES) ?>'>Editar</button><?php if(!empty($familia['ativo'])): ?><form method="post" class="d-inline" onsubmit="return confirm('Desativar esta família? Os registros e o histórico serão preservados.');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="desativar_familia"><input type="hidden" name="familia_id" value="<?= (int)$familia['id'] ?>"><button class="btn btn-sm btn-outline-danger">Desativar</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <div class="col-lg-5"><form method="post" id="form-familia" class="card card-body bg-light"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="salvar_familia"><input type="hidden" name="familia_id"><h5 id="titulo-form-familia">Nova família</h5><div class="form-row"><div class="form-group col-md-8"><label>Nome</label><input name="nome" class="form-control" required></div><div class="form-group col-md-4"><label>Slug</label><input name="slug" class="form-control" placeholder="automático"></div></div><div class="form-group"><label>Produto genérico — “cor a definir”</label><select name="produto_generico_id" class="form-control"><option value="">Nenhum por enquanto</option><?php foreach($catalogoProdutos as $p): ?><option value="<?= (int)$p['produto_id'] ?>">#<?= (int)$p['produto_id'] ?> — <?= htmlspecialchars($p['nome_produto']) ?></option><?php endforeach; ?></select></div><div class="form-row"><div class="form-group col"><label>Modo</label><select name="modo_impacto_estoque" class="form-control"><option>COMPONENTE_COR</option><option>ESTRUTURA_COMPARTILHADA</option><option>FORRACAO_PRODUCAO</option><option>SEM_IMPACTO_FISICO</option></select></div><div class="form-group col"><label>Preço</label><select name="politica_preco" class="form-control"><option>MESMO_PRECO</option><option>PRECO_DA_VARIANTE</option></select></div></div><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="familia-livre" name="permite_cor_livre" value="1"><label class="custom-control-label" for="familia-livre">Permite cor livre</label></div><div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="familia-revisao" name="exige_revisao" value="1" checked><label class="custom-control-label" for="familia-revisao">Exige revisão humana</label></div><div class="form-group"><label>Observações</label><textarea name="observacoes" class="form-control"></textarea></div><button class="btn btn-primary">Salvar família</button> <button type="button" id="nova-familia" class="btn btn-link">Limpar</button></form></div></div>
        <hr><h5>Vínculos de produtos</h5><p class="text-muted small">Para “capa com estoque”, cada produto colorido recebe uma cor e acabamento CAPA. Para forração, ligue o próprio produto “cor a definir” à família e selecione FORRACAO.</p>
        <div class="row"><div class="col-lg-7"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Família</th><th>Produto</th><th>Cor</th><th>Acabamento</th><th></th></tr></thead><tbody><?php foreach($vinculosProdutos as $v): ?><tr class="<?= empty($v['ativo'])?'text-muted':'' ?>"><td><?= htmlspecialchars($v['familia_nome']) ?></td><td><?= htmlspecialchars($v['nome_produto']) ?><?= !empty($v['eh_generica'])?' <span class="badge badge-info">genérico</span>':'' ?></td><td><?= htmlspecialchars($v['cor_nome'] ?? '—') ?></td><td><?= htmlspecialchars($v['tipo_acabamento']) ?></td><td><button type="button" class="btn btn-sm btn-outline-primary editar-vinculo" data-vinculo='<?= htmlspecialchars(json_encode($v, JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT), ENT_QUOTES) ?>'>Editar</button><?php if(!empty($v['ativo'])): ?><form method="post" class="d-inline" onsubmit="return confirm('Desativar este vínculo? O histórico será preservado.');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="desativar_vinculo_produto"><input type="hidden" name="vinculo_id" value="<?= (int)$v['id'] ?>"><button class="btn btn-sm btn-outline-danger">Desativar</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <div class="col-lg-5"><form method="post" id="form-vinculo" class="card card-body bg-light"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="salvar_vinculo_produto"><input type="hidden" name="vinculo_id"><h5 id="titulo-form-vinculo">Novo vínculo</h5><div class="form-group"><label>Família</label><select name="familia_variante_id" class="form-control" required><option value="">Selecione</option><?php foreach($familias as $f): if(!empty($f['ativo'])): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option><?php endif; endforeach; ?></select></div><div class="form-group"><label>Produto existente</label><select name="produto_id" class="form-control" required><option value="">Selecione</option><?php foreach($catalogoProdutos as $p): ?><option value="<?= (int)$p['produto_id'] ?>">#<?= (int)$p['produto_id'] ?> — <?= htmlspecialchars($p['nome_produto']) ?></option><?php endforeach; ?></select></div><div class="form-row"><div class="form-group col"><label>Cor (opcional no genérico)</label><select name="cor_padrao_id" class="form-control"><option value="">Sem cor / a definir</option><?php foreach($cores as $c): if(!empty($c['ativo'])): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option><?php endif; endforeach; ?></select></div><div class="form-group col"><label>Acabamento</label><select name="tipo_acabamento" class="form-control"><option value="CAPA">CAPA</option><option value="FORRACAO">FORRACAO</option><option value="PINTURA">PINTURA</option><option value="MATERIAL">MATERIAL</option><option value="OUTRO">OUTRO</option></select></div></div><div class="form-group"><label>Chave de estrutura (opcional)</label><input name="estrutura_chave" class="form-control" placeholder="ex.: sofa-3-lugares"></div><div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="vinculo-generico" name="eh_generica" value="1"><label class="custom-control-label" for="vinculo-generico">É o produto genérico “cor a definir”</label></div><button class="btn btn-primary">Salvar vínculo</button> <button type="button" id="novo-vinculo" class="btn btn-link">Limpar</button></form></div></div>
      </div>
      <div id="amostras" class="tab-pane fade">
        <div class="alert alert-primary"><strong>Cores e referências</strong><br>Prepare, edite e aprove referências globais sem alterar estoque, preço ou composição.</div>
        <p class="text-muted">Escolha uma cor padrão pelo nome (por exemplo, Amarelo) e associe uma imagem que mostre o tom correto. Você pode enviar um arquivo do computador ou usar uma foto do catálogo. “Origem” informa de onde veio a referência, como almofada, tecido ou pufe.</p>
        <p><a href="#cores" id="abrir-cadastro-cor" class="btn btn-outline-primary btn-sm">Cadastrar uma nova cor</a> <span class="text-muted small">Use quando o nome ainda não existir na lista.</span></p>
        <div class="alert alert-info">A referência vale no escopo escolhido: global ou uma família específica. Editar ou aprovar aqui não muda estoque, preço, composição nem publica foto de produto.</div>
        <form method="post" enctype="multipart/form-data" class="card card-body bg-light mb-3"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="criar_amostra"><h5>Cadastrar referência de cor por arquivo</h5><p class="small text-muted">Use uma foto do tecido/tom, não uma foto comercial do produto. “Global” atende todas as famílias; uma família específica tem prioridade só nela e preserva as exceções, como almofadas.</p><div class="form-row"><div class="form-group col-md-3"><label>Cor padrão</label><select name="cor_padrao_id" class="form-control" required><option value="">Selecione</option><?php foreach($cores as $cor): ?><option value="<?= (int)$cor['id'] ?>"><?= htmlspecialchars($cor['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-3"><label>Aplicar em</label><select name="familia_variante_id" class="form-control"><option value="">Global (todas as famílias)</option><?php foreach($familias as $familia): ?><option value="<?= (int)$familia['id'] ?>"><?= htmlspecialchars($familia['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-3"><label>Imagem da referência</label><input type="file" name="arquivo" class="form-control-file" accept="image/png,image/jpeg,image/webp" required></div><div class="form-group col-md-3"><label>Origem</label><input name="familia_origem" class="form-control" placeholder="almofada, tecido, pufe..."></div></div><div class="form-group"><label>Observações</label><input name="observacoes" class="form-control"></div><button class="btn btn-outline-primary">Cadastrar referência pendente</button></form>
        <form method="post" class="card card-body bg-light mb-3"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="criar_amostra_produto"><h5>Usar foto já cadastrada no catálogo</h5><div class="form-row"><div class="form-group col-md-3"><label>Cor padrão</label><select name="cor_padrao_id" class="form-control" required><option value="">Selecione</option><?php foreach($cores as $cor): ?><option value="<?= (int)$cor['id'] ?>"><?= htmlspecialchars($cor['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-3"><label>Aplicar em</label><select name="familia_variante_id" class="form-control"><option value="">Global (todas as famílias)</option><?php foreach($familias as $familia): ?><option value="<?= (int)$familia['id'] ?>"><?= htmlspecialchars($familia['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-6"><label>Produto com foto de referência</label><select name="produto_origem_id" class="form-control" required><option value="">Selecione um produto</option><?php foreach($produtosComFoto as $produto): ?><?php $repeteCor = $produto['cor'] && stripos((string)$produto['nome_produto'], (string)$produto['cor']) !== false; ?><option value="<?= (int)$produto['id'] ?>">#<?= (int)$produto['id'] ?> — <?= htmlspecialchars($produto['nome_produto']) ?><?= $produto['cor'] && !$repeteCor ? ' — ' . htmlspecialchars($produto['cor']) : '' ?></option><?php endforeach; ?></select></div></div><div class="form-group"><label>Observação</label><input name="observacoes" class="form-control" placeholder="ex.: foto de almofada usada como referência para pufe"></div><button class="btn btn-outline-secondary">Criar referência pendente a partir da foto</button></form>
        <div class="row">
        <?php foreach ($amostras as $a): ?>
          <div class="col-md-6 col-xl-4"><div class="card h-100">
            <a href="amostra_imagem.php?id=<?= (int)$a['id'] ?>" target="_blank" rel="noopener">
              <img src="amostra_imagem.php?id=<?= (int)$a['id'] ?>" alt="Referência visual <?= htmlspecialchars($a['cor_nome'], ENT_QUOTES, 'UTF-8') ?>" class="card-img-top" style="height:240px;object-fit:contain;background:white">
            </a>
            <div class="card-body"><h5><?= htmlspecialchars($a['cor_nome']) ?></h5>
              <span class="badge <?= $a['aprovada'] ? 'badge-success' : 'badge-warning' ?>"><?= $a['aprovada'] ? 'Tom aprovado' : 'Tom pendente' ?></span>
              <?php if (!$a['aprovada']): ?><form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="aprovar_amostra_global"><input type="hidden" name="amostra_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-success" type="submit">Aprovar referência<?= !empty($a['familia_variante_id']) ? ' desta família' : ' global' ?></button></form><?php endif; ?>
              <p class="small mt-2"><?= htmlspecialchars($a['familia_origem'] ?? $a['familia_nome'] ?? '') ?><?= $a['produto_origem'] ? ' · produto: ' . htmlspecialchars($a['produto_origem']) : '' ?></p>
              <p class="small text-muted"><?= htmlspecialchars($a['observacoes'] ?? '') ?></p>
              <a class="btn btn-sm btn-outline-primary mb-2" href="definir_area_referencia.php?id=<?= (int)$a['id'] ?>">Definir área do tom</a>
              <span class="small <?= !empty($a['area_referencia_json']) ? 'text-success' : 'text-muted' ?> d-inline-block mb-2"><?= !empty($a['area_referencia_json']) ? 'Área do tecido definida' : 'Área do tecido ainda não definida' ?></span>
              <details class="mt-2"><summary>Editar referência</summary><form method="post" enctype="multipart/form-data" class="mt-2"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="editar_amostra"><input type="hidden" name="amostra_id" value="<?= (int)$a['id'] ?>"><div class="form-group"><label>Cor padrão</label><select name="cor_padrao_id" class="form-control form-control-sm"><?php foreach($cores as $cor): ?><option value="<?= (int)$cor['id'] ?>" <?= (int)$cor['id'] === (int)$a['cor_padrao_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cor['nome']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Substituir imagem (opcional)</label><input type="file" name="arquivo" class="form-control-file" accept="image/png,image/jpeg,image/webp"></div><div class="form-group"><label>Origem</label><input name="familia_origem" class="form-control form-control-sm" value="<?= htmlspecialchars($a['familia_origem'] ?? '') ?>"></div><div class="form-group"><label>Observações</label><textarea name="observacoes" class="form-control form-control-sm"><?= htmlspecialchars($a['observacoes'] ?? '') ?></textarea></div><button class="btn btn-sm btn-outline-primary">Salvar edição</button></form></details>
            </div>
          </div></div>
        <?php endforeach; ?>
        </div>
        <?php if (!$amostras): ?><div class="alert alert-light">Nenhuma amostra vinculada.</div><?php endif; ?>
      </div>
      <div id="modelos" class="tab-pane fade">
        <p class="text-muted">Esta tabela controla a capacidade de montar novas fotos. Uma foto que já está no produto pode existir mesmo que a amostra para novas montagens ainda não tenha sido cadastrada aqui.</p>
        <?php foreach ($modelos as $m): ?><?php if ((int)$m['ativo'] === 1 && $m['status_geometria'] === 'PENDENTE' && $m['status_recorte'] === 'PENDENTE'): ?>
          <div class="card card-body mb-2" id="modelo-<?= (int)$m['id'] ?>"><h5>Modelo #<?= (int)$m['id'] ?> — <?= htmlspecialchars($m['nome']) ?></h5>
            <p class="mb-2"><strong>Produto usado como base:</strong> <?= htmlspecialchars($m['produto_origem_nome'] ?? 'modelo anterior sem produto vinculado') ?>. <span class="badge badge-warning">Pendente de aprovação</span></p>
            <?php if (empty($m['arquivo_mascara'])): ?>
              <div class="alert alert-warning mb-3"><strong>Marcação ainda não concluída.</strong> A foto foi guardada, mas nenhuma área de tecido foi fechada. Continue a marcação antes de aprovar este mestre.</div>
              <div class="row mb-3"><div class="col-md-4"><strong class="d-block mb-1">Foto-base guardada</strong><img class="img-fluid border" src="modelo_base.php?id=<?= (int)$m['id'] ?>&amp;revisao=1" alt="Foto mestre aguardando marcação"></div></div>
              <div class="d-flex flex-wrap"><a class="btn btn-primary btn-sm mr-2 mb-2" href="novo_mestre.php?editar=<?= (int)$m['id'] ?>">Continuar marcação</a><form method="post" class="mb-2" onsubmit="return confirm('Descartar este mestre pendente? Ele sairá da fila, mas o histórico será mantido.');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="descartar_modelo"><input type="hidden" name="modelo_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-outline-danger btn-sm">Descartar este mestre</button></form></div>
            <?php else: ?>
            <div class="row modelo-revisao" data-modelo-id="<?= (int)$m['id'] ?>">
              <div class="col-md-3"><strong class="d-block mb-1">1. Foto-base</strong><img class="img-fluid border modelo-revisao-base" src="modelo_base.php?id=<?= (int)$m['id'] ?>&amp;revisao=1" alt="Foto mestre"></div>
              <div class="col-md-3"><strong class="d-block mb-1">2. Máscara técnica</strong><img class="img-fluid border modelo-revisao-mascara" src="modelo_base.php?id=<?= (int)$m['id'] ?>&amp;parte=mascara&amp;revisao=1" alt="Área de tecido marcada em branco"><small class="d-block text-muted">Branco recebe a nova cor; preto permanece intacto.</small></div>
              <div class="col-md-4"><strong class="d-block mb-1">3. Resultado para conferir</strong><select class="form-control form-control-sm modelo-revisao-amostra mb-2"><option value="">Escolha uma cor aprovada</option><?php foreach($amostrasAprovadas as $amostra): ?><option value="<?= (int)$amostra['id'] ?>"><?= htmlspecialchars($amostra['cor_nome']) ?></option><?php endforeach; ?></select><button type="button" class="btn btn-outline-primary btn-sm modelo-revisao-gerar mb-2">Aplicar cor e conferir</button><canvas class="modelo-revisao-canvas border" width="800" height="800" style="display:none;width:100%;height:auto" aria-label="Resultado da cor aplicada ao modelo"></canvas><small class="d-block modelo-revisao-estado text-muted">Escolha uma cor para visualizar o resultado antes de aprovar.</small></div>
            </div>
            <p class="small mt-2">Confirme se o branco cobre somente assento e encosto. A cor usada na prévia durante a criação não ficou vinculada ao mestre. Se a marcação estiver ruim, descarte este rascunho e crie outro antes de aprovar.</p>
            <div class="d-flex flex-wrap"><a class="btn btn-outline-primary btn-sm mr-2 mb-2" href="novo_mestre.php?editar=<?= (int)$m['id'] ?>">Editar marcação</a><form method="post" class="mr-2 mb-2"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="aprovar_modelo"><input type="hidden" name="modelo_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-success btn-sm">Aprovar este mestre</button></form><form method="post" class="mb-2" onsubmit="return confirm('Descartar este mestre pendente? Ele sairá da fila, mas o histórico será mantido.');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="descartar_modelo"><input type="hidden" name="modelo_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-outline-danger btn-sm">Descartar este mestre</button></form></div>
            <?php endif; ?>
          </div>
        <?php endif; ?><?php endforeach; ?>
        <table class="table table-sm"><thead><tr><th>Modelo</th><th>Família</th><th>Geometria</th><th>Recorte</th><th>Cores vinculadas</th><th>Com amostra</th><th>Visualização</th></tr></thead><tbody><?php foreach($modelos as $m): ?><tr><td><?= htmlspecialchars($m['nome']) ?> v<?= (int)$m['versao'] ?></td><td><?= htmlspecialchars($m['familia_nome'] ?? '') ?></td><td><?= htmlspecialchars($m['status_geometria']) ?></td><td><?= htmlspecialchars($m['status_recorte']) ?></td><td><?= (int)$m['total_variantes'] ?></td><td><?= (int)$m['variantes_com_amostra'] ?></td><td><?php if ((int)$m['ativo'] === 1 && $m['status_geometria'] === 'APROVADA' && $m['status_recorte'] === 'APROVADO'): ?><a class="btn btn-sm btn-outline-primary mb-1" target="_blank" rel="noopener" href="modelo_base.php?id=<?= (int)$m['id'] ?>">Ver foto mestre</a><?php if (!empty($m['arquivo_mascara'])): ?> <a class="btn btn-sm btn-outline-secondary mb-1" target="_blank" rel="noopener" href="modelo_base.php?id=<?= (int)$m['id'] ?>&amp;parte=mascara">Ver marcação</a><?php endif; ?><?php else: ?><span class="text-muted small">Ainda não aprovado</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
        <div class="table-responsive" style="max-height:420px;overflow:auto"><table class="table table-sm table-striped"><thead><tr><th>Modelo</th><th>Cor</th><th>Produto</th><th>Amostra</th><th>Publicação</th></tr></thead><tbody>
          <?php foreach($variantesImagem as $v): ?><tr><td><?= htmlspecialchars($v['modelo_nome']) ?></td><td><?= htmlspecialchars($v['cor_nome']) ?></td><td><?= htmlspecialchars($v['nome_produto'] ?? '') ?></td><td><?= $v['amostra_cor_id'] ? 'Aprovada; prévia disponível' : 'Pendente; não gerar' ?></td><td><?= htmlspecialchars($v['status_publicacao']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>
  </div>
  </details>
</div>
<?php $inline_js_setup = 'window.MOBEL_FORRACOES_CONFIG = '.json_encode(array_values(array_filter($familias,static fn($f)=>(int)$f['ativo']===1 && $f['modo_impacto_estoque']==='FORRACAO_PRODUCAO'))).';' . 'window.MOBEL_PREVIAS = ' . json_encode($opcoesPrevia, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';window.MOBEL_CATALOGO = ' . json_encode($catalogoProdutos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';window.MOBEL_MOTOR_CSRF = ' . json_encode($_SESSION['csrf_motor_cores']) . ';window.MOBEL_PRESELECAO = ' . json_encode(['produto'=>(int)($_GET['produto'] ?? 0), 'modelo'=>(int)($_GET['modelo'] ?? 0), 'cor'=>(int)($_GET['cor'] ?? 0)]) . ';';
$extra_js = [BASE_URL . '/assets/js/motor_referencias_navegacao.js', BASE_URL . '/assets/js/motor_recoloracao.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/motor_recoloracao.js'), BASE_URL . '/assets/js/motor_previa.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/motor_previa.js'), BASE_URL . '/assets/js/motor_modelo_revisao.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/motor_modelo_revisao.js'), BASE_URL . '/assets/js/motor_crud_variantes.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/motor_crud_variantes.js')];
require_once __DIR__ . '/../includes/footer.php'; ?>
