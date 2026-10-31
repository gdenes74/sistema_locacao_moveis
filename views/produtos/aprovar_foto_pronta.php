<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';
require_once __DIR__ . '/../../models/FotoPronta.php';
if (!isLoggedIn()) redirect('views/usuarios/login.php');
if (!hasAccess('admin')) { http_response_code(403); exit('Acesso restrito.'); }
if (empty($_SESSION['csrf_motor_cores'])) $_SESSION['csrf_motor_cores'] = bin2hex(random_bytes(32));
$db = (new Database())->getConnection();
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_motor_cores'], (string)($_POST['csrf'] ?? ''))) throw new DomainException('Sessão expirada.');
        $produtoId = filter_var($_POST['produto_id'] ?? null, FILTER_VALIDATE_INT);
        $modeloId = filter_var($_POST['modelo_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$produtoId || !$modeloId) throw new DomainException('Escolha produto e foto pronta.');
        $resultado = (new FotoPronta($db))->aprovar((int)$produtoId, (int)$modeloId, (string)($_POST['foto_anterior'] ?? ''), (int)$_SESSION['user_id']);
        $_SESSION['success_message'] = 'Foto pronta aprovada para o produto e seus componentes neste contexto. A foto física global dos componentes compartilhados foi preservada; o recorte para outras cores continua pendente.';
        redirect('views/produtos/show.php?id=' . (int)$produtoId);
    } catch (Throwable $e) { $erro = $e->getMessage(); }
}
$catalogo = array_values(array_filter((new MotorCores($db))->listarCatalogoProdutos(), static fn($p) => $p['tipo_produto'] === 'COMPOSTO'));
$mestres = $db->query("SELECT id,nome,produto_origem_id FROM modelos_imagem WHERE ativo=1 AND status_geometria='PENDENTE' AND produto_origem_id IS NOT NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Aprovar foto pronta';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-3">
  <a href="cores_imagens.php" class="btn btn-link pl-0">← Montagem por cor</a>
  <h1 class="h3">Aprovar foto pronta de um produto</h1>
  <p>Selecione o produto e compare a foto atual com a imagem já preparada fora do sistema. Esta aprovação troca a foto do produto e registra a apresentação dos componentes <em>nesse produto</em>. Não aprova o recorte para gerar outras cores.</p>
  <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  <form method="post" id="form-foto-pronta" class="card card-body">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>">
    <input type="hidden" name="foto_anterior" id="foto-anterior">
    <div class="row">
      <div class="col-md-3 form-group"><label for="foto-secao">Seção</label><select id="foto-secao" class="form-control"><option value="">Todas</option></select></div>
      <div class="col-md-3 form-group"><label for="foto-categoria">Categoria</label><select id="foto-categoria" class="form-control"><option value="">Todas</option></select></div>
      <div class="col-md-3 form-group"><label for="foto-subcategoria">Subcategoria</label><select id="foto-subcategoria" class="form-control"><option value="">Todas</option></select></div>
      <div class="col-md-3 form-group"><label for="foto-produto">Produto montado</label><select id="foto-produto" name="produto_id" class="form-control" required><option value="">Escolha</option></select></div>
    </div>
    <div class="form-group"><label for="foto-modelo">Foto candidata para este produto</label><select id="foto-modelo" name="modelo_id" class="form-control" required><option value="">Escolha primeiro o produto</option></select></div>
    <div class="d-flex flex-wrap align-items-center justify-content-between border rounded bg-light p-3 mb-3">
      <div id="foto-aprovar-estado" class="mr-3 mb-2 mb-md-0 text-muted" role="status">Escolha o produto e a foto candidata.</div>
      <button id="foto-aprovar" class="btn btn-primary" disabled>Aprovar e substituir foto deste produto</button>
    </div>
    <div class="row">
      <div class="col-md-6"><h2 class="h6">Foto atual</h2><div class="border bg-white text-center p-2" style="height:280px"><img id="foto-atual" class="img-fluid h-100" style="object-fit:contain" alt="Foto atual do produto"></div><p id="foto-atual-caminho" class="small text-muted"></p></div>
      <div class="col-md-6"><h2 class="h6">Foto candidata</h2><div class="border bg-white text-center p-2" style="height:280px"><img id="foto-candidata" class="img-fluid h-100" style="object-fit:contain" alt="Foto candidata"></div><p id="foto-modelo-nome" class="small text-muted"></p></div>
    </div>
    <p class="small text-muted mt-3">Estoque, quantidade, composição e preços não são alterados. O mesmo colchão físico pode mostrar a foto do sofá no contexto do sofá e a foto da cama no contexto da Cama Thai.</p>
  </form>
</div>
<?php $inline_js_setup = 'window.MOBEL_FOTO_PRONTA = ' . json_encode(['produtos'=>$catalogo,'mestres'=>$mestres], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'; $extra_js = [BASE_URL . '/assets/js/aprovar_foto_pronta.js?v=' . (string)filemtime(__DIR__ . '/../../assets/js/aprovar_foto_pronta.js')]; require_once __DIR__ . '/../includes/footer.php'; ?>
