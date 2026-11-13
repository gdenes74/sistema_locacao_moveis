<?php
$iconesCategoria = ['📦'=>'Genérico','🛋️'=>'Sofá / Lounge','🪑'=>'Cadeira / Banqueta','🟫'=>'Mesa / Madeira','🧊'=>'Cubo','🍸'=>'Bar / Bistrô','🧵'=>'Tecido / Forração','🌈'=>'Cores','🛏️'=>'Cama Thai','🔲'=>'Pufe','🍽️'=>'Sousplat / Mesa posta','◻️'=>'Guardanapo / Toalha','🧶'=>'Tapete'];
$iconeAtual = $_POST['icone_categoria'] ?? '';
if (!$iconeAtual && !empty($categoria->id)) {
    $consultaIcone = $db->prepare('SELECT icone FROM categorias WHERE id=?');
    $consultaIcone->execute([$categoria->id]);
    $iconeAtual = $consultaIcone->fetchColumn() ?: '📦';
}
?>
<div class="form-group">
 <label for="icone_categoria">Ícone da categoria</label>
 <select id="icone_categoria" name="icone_categoria" class="form-control">
 <?php foreach ($iconesCategoria as $icone => $rotulo): ?>
  <option value="<?= htmlspecialchars($icone, ENT_QUOTES, 'UTF-8') ?>" <?= $iconeAtual === $icone ? 'selected' : '' ?>><?= $icone . ' ' . htmlspecialchars($rotulo) ?></option>
 <?php endforeach; ?>
 </select>
 <small class="form-text text-muted">Identificação visual exibida na seleção de produtos de pedidos e orçamentos.</small>
</div>
