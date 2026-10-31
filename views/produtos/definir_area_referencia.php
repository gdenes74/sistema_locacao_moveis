<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';
if (!isLoggedIn() || !hasAccess('admin')) { http_response_code(403); exit('Acesso restrito.'); }
$db = (new Database())->getConnection();
$id = (int)($_GET['id'] ?? $_POST['amostra_id'] ?? 0);
$q = $db->prepare('SELECT a.*,c.nome cor_nome FROM amostras_cor a JOIN cores_padrao c ON c.id=a.cor_padrao_id WHERE a.id=? AND a.ativo=1');
$q->execute([$id]); $amostra=$q->fetch(PDO::FETCH_ASSOC);
if (!$amostra) { http_response_code(404); exit('Referência não encontrada.'); }
if (empty($_SESSION['csrf_motor_cores'])) $_SESSION['csrf_motor_cores']=bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if (!hash_equals($_SESSION['csrf_motor_cores'], (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Sessão expirada.');
    $areaJson=trim((string)($_POST['area_json'] ?? ''));
    $area=$areaJson === '' ? null : json_decode($areaJson,true);
    if ($area !== null && (!is_array($area) || count($area)!==4)) throw new InvalidArgumentException('A área marcada é inválida.');
    if ($area !== null) {
      [$x,$y,$w,$h]=array_map('floatval',$area);
      if ($x<0||$y<0||$w<=.01||$h<=.01||$x+$w>1||$y+$h>1) throw new InvalidArgumentException('A área marcada é inválida.');
      $novo=json_encode([$x,$y,$w,$h]);
    } else $novo=null;
    $db->beginTransaction();
    $db->prepare('UPDATE amostras_cor SET area_referencia_json=? WHERE id=?')->execute([$novo,$id]);
    $acao=$novo === null ? 'AREA_REFERENCIA_REMOVIDA' : 'AREA_REFERENCIA_DEFINIDA';
    $db->prepare('INSERT INTO historico_motor_cores (entidade,entidade_id,acao,estado_anterior,estado_novo,usuario_id) VALUES (?,?,?,?,?,?)')->execute(['amostras_cor',$id,$acao,json_encode(['area_referencia_json'=>$amostra['area_referencia_json']]),json_encode(['area_referencia_json'=>$novo]),(int)$_SESSION['user_id']]);
    $db->commit(); $_SESSION['success_message']=$novo === null ? 'Seleção removida. As próximas montagens usarão a leitura automática da amostra.' : 'Área de tecido salva. As próximas montagens usarão somente esse trecho para medir o tom.';
    redirect('views/produtos/cores_imagens.php#amostras');
  } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); $erro=$e->getMessage(); }
}
$page_title='Definir área da referência'; require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-3"><a href="cores_imagens.php#amostras" class="btn btn-link pl-0">← Voltar para Cores e referências</a><h1 class="h3">Definir área da referência: <?= htmlspecialchars($amostra['cor_nome']) ?></h1><p class="text-muted">Arraste um retângulo sobre um pedaço uniforme do tecido. Madeira, fundo, zíper, sombra muito forte e etiqueta devem ficar fora. Se a foto inteira for uma boa referência, clique em “Limpar marcação” e salve: o motor fará leitura automática.</p><?php if(!empty($erro)): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_motor_cores']) ?>"><input type="hidden" name="amostra_id" value="<?= (int)$id ?>"><input type="hidden" id="area-json" name="area_json" value="<?= htmlspecialchars($amostra['area_referencia_json'] ?? '') ?>"><canvas id="area-referencia-canvas" class="border w-100" style="max-height:650px;object-fit:contain;touch-action:none" data-imagem="amostra_imagem.php?id=<?= (int)$id ?>"></canvas><p id="area-referencia-estado" class="small mt-2">Arraste sobre o tecido para marcar a amostra.</p><button type="button" id="limpar-area" class="btn btn-outline-secondary">Usar leitura automática</button><button id="salvar-area" class="btn btn-primary">Salvar área de referência</button></form></div>
<?php $extra_js=[BASE_URL.'/assets/js/motor_referencia_area.js?v='.(string)filemtime(__DIR__.'/../../assets/js/motor_referencia_area.js')]; require_once __DIR__ . '/../includes/footer.php'; ?>
