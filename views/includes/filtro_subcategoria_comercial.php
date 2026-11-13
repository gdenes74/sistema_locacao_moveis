<?php
$subcategoriasFiltro = $db->query("SELECT s.id, s.categoria_id, s.nome FROM subcategorias s WHERE s.id NOT IN (14,38,39,47) OR EXISTS (SELECT 1 FROM produtos p WHERE p.subcategoria_id=s.id) ORDER BY s.nome")->fetchAll(PDO::FETCH_ASSOC);
$iconesFiltro = $db->query('SELECT id, icone FROM categorias')->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<div class="form-group mt-2">
    <label for="busca_subcategoria_produto">Subcategoria:</label>
    <select id="busca_subcategoria_produto" class="form-control form-control-sm"><option value="">Todas as Subcategorias</option></select>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dados = <?= json_encode($subcategoriasFiltro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const categoria = document.getElementById('busca_categoria_produto');
    const subcategoria = document.getElementById('busca_subcategoria_produto');
    const icones = <?= json_encode($iconesFiltro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    Array.from(categoria.options).forEach(opcao => {
        if (opcao.value && icones[opcao.value]) opcao.textContent = icones[opcao.value] + ' ' + opcao.textContent;
    });
    categoria.after(subcategoria.parentElement);
    function preencher() {
        subcategoria.replaceChildren(new Option('Todas as Subcategorias', ''));
        dados.filter(s => !categoria.value || String(s.categoria_id) === categoria.value)
            .forEach(s => subcategoria.add(new Option(s.nome, s.id)));
    }
    preencher();
    // Captura ocorre antes da busca existente, evitando reaproveitar a subcategoria anterior.
    categoria.addEventListener('change', preencher, true);
    subcategoria.addEventListener('change', function () {
        window.jQuery('#busca_produto').trigger('change');
    });
});
</script>
<style>
#sugestoes_produtos { max-height: 440px !important; }
#sugestoes_produtos .foto-produto-sugestao { width: 88px !important; height: 88px !important; object-fit: contain !important; flex-shrink: 0; }
#sugestoes_produtos .item-sugestao-produto { white-space: normal; }
</style>
