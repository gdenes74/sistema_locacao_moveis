<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';
if (!isLoggedIn()) redirect('views/usuarios/login.php');
if (!hasAccess('admin')) { http_response_code(403); exit('Acesso restrito.'); }
$motor = new MotorCores((new Database())->getConnection());
if (empty($_SESSION['csrf_motor_cores'])) $_SESSION['csrf_motor_cores'] = bin2hex(random_bytes(32));
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_motor_cores'], (string)($_POST['csrf'] ?? ''))) throw new DomainException('Sessão expirada.');
        if (($_POST['acao'] ?? '') !== 'aprovar_modelo') throw new DomainException('Ação inválida.');
        $motor->aprovarModelo((int)($_POST['modelo_id'] ?? 0), (int)$_SESSION['user_id']);
        $_SESSION['success_message'] = 'Modelo mestre aprovado. Ainda é preciso vincular as cores e produtos correspondentes.';
        redirect('views/produtos/preparar_fotos.php');
    } catch (Throwable $e) { $erro = $e->getMessage(); }
}
$modelos = $motor->listarModelos();
$fila = $motor->listarFilaMontagem();
$opcoesDescobertas = $motor->listarOpcoesPrevia();
$page_title = 'Preparar cores e produtos';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3">
  <a href="cores_imagens.php" class="btn btn-link pl-0">← Voltar para Montar fotos por cor</a>
  <div class="d-flex justify-content-between align-items-start"><div><h1 class="h3">Preparar cores e produtos</h1><p class="text-muted">Aqui ficam as combinações cadastradas, inclusive as que ainda não podem gerar foto. A cor física já confirmada não precisa ser aprovada novamente.</p></div><a href="novo_mestre.php" class="btn btn-outline-primary">Criar modelo mestre</a></div>
  <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  <div class="alert alert-info">Esta lista é um diagnóstico do cadastro atual. “Adicionar referência de cor” significa vincular uma foto/amostra digital; não significa decidir outra vez o nome ou o tom. Nenhum item desta tela altera estoque.</div>
  <?php foreach ($modelos as $modelo): ?><?php if ((int)$modelo['ativo'] === 1 && $modelo['status_geometria'] === 'PENDENTE' && $modelo['status_recorte'] === 'PENDENTE'): ?>
    <div class="card card-body mb-3"><h2 class="h5">Revisar mestre: <?= htmlspecialchars($modelo['nome']) ?></h2>
      <p class="small"><strong>Produto usado como base:</strong> <?= htmlspecialchars($modelo['produto_origem_nome'] ?? 'não informado') ?></p>
      <?php if (empty($modelo['arquivo_mascara'])): ?>
        <div class="alert alert-warning"><strong>Marcação ainda não concluída.</strong> A foto foi guardada, mas nenhuma área de tecido foi fechada.</div>
        <div class="row"><div class="col-md-3"><img class="img-fluid border" src="modelo_base.php?id=<?= (int)$modelo['id'] ?>&amp;revisao=1" alt="Foto mestre aguardando marcação"></div></div>
        <a class="btn btn-primary btn-sm mt-2" href="novo_mestre.php?editar=<?= (int)$modelo['id'] ?>">Continuar marcação</a>
      <?php else: ?>
        <div class="row"><div class="col-md-3"><img class="img-fluid border" src="modelo_base.php?id=<?= (int)$modelo['id'] ?>&amp;revisao=1" alt="Foto mestre"></div><div class="col-md-3"><img class="img-fluid border" src="modelo_base.php?id=<?= (int)$modelo['id'] ?>&amp;parte=mascara&amp;revisao=1" alt="Região do tecido em branco"></div></div>
        <p class="small mt-2">A área branca deve cobrir apenas o tecido a colorir.</p>
        <div class="d-flex flex-wrap"><a class="btn btn-outline-primary btn-sm mr-2" href="novo_mestre.php?editar=<?= (int)$modelo['id'] ?>">Editar marcação</a><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="acao" value="aprovar_modelo"><input type="hidden" name="modelo_id" value="<?= (int)$modelo['id'] ?>"><button class="btn btn-success btn-sm">Aprovar mestre</button></form></div>
      <?php endif; ?>
    </div>
  <?php endif; ?><?php endforeach; ?>
  <h2 class="h5 mt-4">Combinações encontradas automaticamente</h2>
  <p class="text-muted small">Esta lista automática é para produtos com capas físicas. Banco, coletivos e aparadores forrados sem capa usam o mestre e a referência aprovada diretamente em orçamento/pedido; não precisam criar uma variante física por cor. Madeira e demais partes fora da máscara permanecem intactas.</p>
  <div class="table-responsive mb-4"><table class="table table-sm table-striped"><thead><tr><th>Modelo</th><th>Produto</th><th>Cor</th><th>Situação</th></tr></thead><tbody>
    <?php if (!$opcoesDescobertas): ?><tr><td colspan="4" class="text-muted">Nenhuma combinação pronta. Conclua e aprove um mestre para liberar seus produtos e cores.</td></tr><?php endif; ?>
    <?php foreach ($opcoesDescobertas as $opcao): ?><tr><td><?= htmlspecialchars($opcao['modelo_nome']) ?></td><td><a href="show.php?id=<?= (int)$opcao['produto_id'] ?>"><?= htmlspecialchars($opcao['nome_produto']) ?></a></td><td><?= htmlspecialchars($opcao['cor_nome']) ?></td><td><?php if (!empty($opcao['variante_imagem_id']) && ($opcao['status_publicacao'] ?? '') === 'PUBLICADA'): ?><span class="badge badge-primary">Publicado no produto</span><?php elseif (!empty($opcao['foto_atual_publicada'])): ?><span class="badge badge-primary">Foto atual publicada</span> <a class="btn btn-outline-primary btn-sm" href="cores_imagens.php?produto=<?= (int)$opcao['produto_id'] ?>&modelo=<?= (int)$opcao['modelo_id'] ?>&cor=<?= (int)$opcao['cor_id'] ?>#montar">Conferir nova prévia</a><?php elseif (!empty($opcao['somente_visual'])): ?><a class="btn btn-outline-primary btn-sm" href="cores_imagens.php?produto=<?= (int)$opcao['produto_id'] ?>&modelo=<?= (int)$opcao['modelo_id'] ?>&cor=<?= (int)$opcao['cor_id'] ?>#montar">Abrir prévia</a> <span class="text-muted small">capa sem estoque</span><?php else: ?><a class="btn btn-success btn-sm" href="cores_imagens.php?produto=<?= (int)$opcao['produto_id'] ?>&modelo=<?= (int)$opcao['modelo_id'] ?>&cor=<?= (int)$opcao['cor_id'] ?>#montar">Montar prévia</a><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Modelo</th><th>Cor</th><th>Produto</th><th>Situação para montar foto</th></tr></thead><tbody>
    <?php foreach ($fila as $item): ?><tr><td><?= htmlspecialchars($item['modelo_nome']) ?></td><td><?= htmlspecialchars($item['cor_nome']) ?></td><td><?php if ($item['produto_id']): ?><a href="show.php?id=<?= (int)$item['produto_id'] ?>"><?= htmlspecialchars($item['nome_produto']) ?></a><?php else: ?>Não vinculado<?php endif; ?></td><td><?php if ($item['publicada']): ?><span class="badge badge-primary">Publicado no produto</span><?php elseif (!empty($item['foto_atual_publicada'])): ?><span class="badge badge-primary">Foto atual publicada</span> <span class="text-muted small">Nova prévia aguardando substituição</span><?php elseif ($item['pendencias']): ?><?= htmlspecialchars(implode(' · ', $item['pendencias'])) ?><?php else: ?><span class="badge badge-success">Pronto para prévia</span><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="small text-muted">A lista superior mostra o que o sistema já descobriu pelas capas e referências aprovadas. A lista inferior mantém o histórico das combinações já registradas.</p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
