<?php
/** Migração reversível das famílias visuais de forração sob demanda. Execute após backup do banco. */
declare(strict_types=1);
require_once __DIR__ . '/../config/Database.php';

$modo = $argv[1] ?? '';
if (!in_array($modo, ['--apply', '--rollback'], true)) {
    fwrite(STDERR, "Use --apply ou --rollback após fazer backup do banco.\n");
    exit(2);
}
$db = (new Database())->getConnection();
$familias = [
    ['Coletivo Alto 2 Metros Forrado', 'coletivo-alto-2-metros-forrado', 618],
    ['Coletivo Baixo 2 Metros Forrado', 'coletivo-baixo-2-metros-forrado', 622],
    ['Aparador U 1,70 x 0,60 m Forrado', 'aparador-u-170-forrado', 626],
    ['Aparador U 2,00 x 0,60 m Forrado', 'aparador-u-200-forrado', 630],
];
$db->beginTransaction();
try {
    if ($modo === '--apply') {
        $inserir = $db->prepare("INSERT INTO familias_variantes
            (nome, slug, produto_generico_id, modo_impacto_estoque, politica_preco,
             permite_cor_livre, exige_revisao, observacoes, ativo)
            VALUES (?, ?, ?, 'FORRACAO_PRODUCAO', 'MESMO_PRECO', 1, 1,
                    'Foto ilustrativa contextual; sem capa física. Estoque pela estrutura compartilhada.', 1)");
        $buscar = $db->prepare('SELECT id FROM familias_variantes WHERE slug=?');
        foreach ($familias as [$nome, $slug, $produtoId]) {
            $buscar->execute([$slug]);
            if (!$buscar->fetchColumn()) $inserir->execute([$nome, $slug, $produtoId]);
        }
        $db->exec("UPDATE familias_variantes SET modo_impacto_estoque='FORRACAO_PRODUCAO',
            permite_cor_livre=1 WHERE id=17 AND slug='mesa-banco-180x050-forrado'");
    } else {
        $buscar = $db->prepare('SELECT id FROM familias_variantes WHERE slug=?');
        $referencias = $db->prepare('SELECT
            (SELECT COUNT(*) FROM modelos_imagem WHERE familia_variante_id=?) +
            (SELECT COUNT(*) FROM produto_variantes WHERE familia_variante_id=?) +
            (SELECT COUNT(*) FROM amostras_cor WHERE familia_variante_id=?)');
        $excluir = $db->prepare('DELETE FROM familias_variantes WHERE id=?');
        foreach ($familias as [, $slug]) {
            $buscar->execute([$slug]);
            $id = $buscar->fetchColumn();
            if (!$id) continue;
            $referencias->execute([$id, $id, $id]);
            if ((int)$referencias->fetchColumn() !== 0) {
                throw new RuntimeException("Família {$slug} já tem vínculos; restaure o backup após revisar os dados.");
            }
            $excluir->execute([$id]);
        }
        $db->exec("UPDATE familias_variantes SET modo_impacto_estoque='COMPONENTE_COR',
            permite_cor_livre=1 WHERE id=17 AND slug='mesa-banco-180x050-forrado'");
    }
    $db->commit();
    echo "Famílias de forração: {$modo} concluído.\n";
} catch (Throwable $erro) {
    $db->rollBack();
    fwrite(STDERR, $erro->getMessage() . "\n");
    exit(1);
}
