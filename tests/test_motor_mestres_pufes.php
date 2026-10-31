<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/MotorCores.php';
$db = (new Database())->getConnection();
$motor = new MotorCores($db);
$falhas = [];
$ok = static function ($cond, $msg) use (&$falhas) { echo ($cond?'[OK] ':'[FALHA] ').$msg.PHP_EOL; if (!$cond) $falhas[]=$msg; };
$familias = $motor->listarFamilias();
$modelos = $motor->listarModelos();
$variantes = $motor->listarVariantesImagem();
$ok(count($familias) >= 2, 'Famílias persistidas');
$porSlug = [];
foreach ($modelos as $m) $porSlug[$m['slug']]=$m;
$ok(isset($porSlug['pufe-thai-madeira-v2']) && $porSlug['pufe-thai-madeira-v2']['status_geometria']==='APROVADA' && $porSlug['pufe-thai-madeira-v2']['status_recorte']==='APROVADO', 'Mestre Thai aprovado');
$ok(isset($porSlug['pufe-azul-celeste-limpo']) && $porSlug['pufe-azul-celeste-limpo']['status_recorte']==='APROVADO', 'Recorte limpo do mestre pequeno registrado');
$q=$db->query("SELECT COUNT(*) FROM produtos p LEFT JOIN produto_variantes pv ON pv.produto_id=p.id LEFT JOIN familias_variantes f ON f.id=pv.familia_variante_id WHERE (p.nome_produto LIKE 'Pufe Thai Cor %' OR p.nome_produto LIKE 'Pufe Forrado %') AND p.nome_produto NOT LIKE '%Cor a Definir%' AND p.tipo_produto='COMPOSTO' AND f.id IS NULL");
$ok((int)$q->fetchColumn()===0, 'Todos os pufes físicos têm vínculo de família');
$porProduto=[]; foreach ($variantes as $v) $porProduto[(int)$v['produto_id']]=$v;
foreach ([34,36,314] as $id) $ok(isset($porProduto[$id]) && $porProduto[$id]['amostra_cor_id'] && $porProduto[$id]['status_publicacao']==='RASCUNHO', "Produto #{$id} com amostra e sem publicação pelo motor");
$thaiSeco = $porProduto[499] ?? null;
$ok($thaiSeco && $thaiSeco['amostra_cor_id'] && $thaiSeco['status_publicacao']==='PUBLICADA'
    && $thaiSeco['arquivo_final']==='assets/uploads/produtos/pufe-thai-cor-verde-seco_499.png'
    && is_file(__DIR__ . '/../' . $thaiSeco['arquivo_final']), 'Pufe Thai Verde Seco publicado com arquivo íntegro');
foreach ([293,488,498] as $id) $ok(isset($porProduto[$id]) && !$porProduto[$id]['amostra_cor_id'] && $porProduto[$id]['status_cor']==='PENDENTE', "Produto #{$id} sem amostra, não gerável");
$ok((int)$db->query("SELECT COUNT(*) FROM variantes_imagem WHERE status_publicacao='PUBLICADA' AND modelo_imagem_id IN (SELECT id FROM modelos_imagem WHERE slug IN ('pufe-azul-celeste-limpo','pufe-thai-madeira-v2'))")->fetchColumn()===1, 'Apenas o Thai Verde Seco foi publicado pelo motor');
$ok((int)$db->query("SELECT COUNT(*) FROM cores_padrao WHERE slug_normalizado IN ('bordo','azul-tiffany','laranja')")->fetchColumn()===3, 'Vocabulário físico canônico preserva tons distintos');
if ($falhas) exit(1);
