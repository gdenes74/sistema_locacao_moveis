<?php
/**
 * Leitura das referências técnicas de corte para uma pendência.
 * Não cria fichas, não altera estoque e nunca troca a cor solicitada.
 */
final class ReferenciaCorteProducao
{
    /** Foto mestre aprovada da família: identificação visual, não molde. */
    public static function mestreAprovado(PDO $conn, array $item): ?array
    {
        $familia=(int)($item['familia_variante_id']??$item['familia_id']??0);
        $produto=(int)($item['produto_id']??0);
        if(!$familia && $produto){
            $s=$conn->prepare('SELECT familia_variante_id FROM produto_variantes WHERE produto_id=? AND ativo=1 ORDER BY eh_generica DESC,id LIMIT 1');
            $s->execute([$produto]);$familia=(int)$s->fetchColumn();
        }
        if(!$familia && $produto){
            $s=$conn->prepare('SELECT id FROM familias_variantes WHERE produto_generico_id=? AND ativo=1 LIMIT 1');
            $s->execute([$produto]);$familia=(int)$s->fetchColumn();
        }
        // Cadastros antigos podem não ter vínculo em produto_variantes.
        // A correspondência pelo nome da família preserva o mestre aprovado.
        if(!$familia && $produto){
            $s=$conn->prepare("SELECT f.id FROM familias_variantes f JOIN produtos p ON p.id=? WHERE f.ativo=1 AND p.nome_produto LIKE CONCAT(f.nome, '%') ORDER BY CHAR_LENGTH(f.nome) DESC,f.id LIMIT 1");
            $s->execute([$produto]);$familia=(int)$s->fetchColumn();
        }
        if(!$familia)return null;
        $s=$conn->prepare("SELECT id,nome FROM modelos_imagem WHERE familia_variante_id=? AND ativo=1 AND status_geometria='APROVADA' AND status_recorte='APROVADO' ORDER BY versao DESC,id DESC LIMIT 1");
        $s->execute([$familia]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    /** Fontes de moldes, separadas por capa. Uma cor nova usa os serviços da composição. */
    public static function planos(PDO $conn, array $item, float $larguraUtil): array
    {
        require_once __DIR__.'/PlanoCorteTecido.php';
        $fontes=[];
        if(!empty($item['componente_id'])) {
            $texto=PlanoCorteTecido::instrucoes(array_intersect_key($item,array_flip(['componente_descricao_detalhada','componente_observacoes'])));
            $pecas=PlanoCorteTecido::sugerirPecas($texto,$larguraUtil);
            if(!$pecas) {
                $texto=PlanoCorteTecido::instrucoes($item);
                $pecas=PlanoCorteTecido::sugerirPecas($texto,$larguraUtil);
            }
            // Uma ficha de SERVICO pode ter o corte descrito no produto comercial,
            // na estrutura ou no próprio serviço. Buscar todos antes de interpretar
            // permite que peças detalhadas prevaleçam sobre consumo de referência.
            // Capas físicas seguem isoladas.
            if(strtoupper((string)($item['componente_tipo']??''))==='SERVICO' && !empty($item['produto_id'])) {
                $s=$conn->prepare('SELECT descricao_detalhada,observacoes FROM produtos WHERE id=?');
                $s->execute([(int)$item['produto_id']]);
                $pai=$s->fetch(PDO::FETCH_ASSOC) ?: [];
                $partes=[$texto,PlanoCorteTecido::instrucoes(['produto_descricao_detalhada'=>$pai['descricao_detalhada']??'', 'produto_observacoes'=>$pai['observacoes']??''])];
                foreach(self::buscar($conn,$item) as $ref) {
                    if(in_array(strtoupper((string)$ref['tipo']),['SERVICO','COMPONENTE'],true))$partes[]=$ref['ficha_tecnica'];
                }
                $texto=implode("\n\n",array_unique(array_filter(array_map('trim',$partes))));
                $pecas=PlanoCorteTecido::sugerirPecas($texto,$larguraUtil);
            }
            return [['nome'=>$item['componente_nome'],'texto'=>$texto,'pecas'=>$pecas,'material'=>$item['componente_material']??'','quantidade'=>1]];
        }
        foreach(self::buscar($conn,$item) as $ref) {
            // Só os serviços de capa/forração do pufe entram no plano de confecção.
            if(strtoupper((string)$ref['tipo'])!=='SERVICO')continue;
            $pecas=PlanoCorteTecido::sugerirPecas($ref['ficha_tecnica'],$larguraUtil);
            if(!$pecas && stripos($ref['nome'],'Pufe')!==false) $pecas=PlanoCorteTecido::sugerirPecas(PlanoCorteTecido::instrucoes($item),$larguraUtil);
            $fontes[]=['nome'=>$ref['nome'],'texto'=>$ref['ficha_tecnica'],'pecas'=>$pecas,'material'=>$ref['material'],'quantidade'=>$ref['quantidade']];
        }
        if(!$fontes)$fontes[]=['nome'=>$item['produto_nome']??$item['descricao_item'],'texto'=>PlanoCorteTecido::instrucoes($item),'pecas'=>PlanoCorteTecido::sugerirPecas(PlanoCorteTecido::instrucoes($item),$larguraUtil),'material'=>'','quantidade'=>1];
        return $fontes;
    }
    public static function buscar(PDO $conn, array $item): array
    {
        $familia = (int)($item['familia_variante_id'] ?? $item['familia_id'] ?? 0);
        $produto = (int)($item['produto_id'] ?? 0);
        if ($familia <= 0 && $produto > 0) {
            $s = $conn->prepare('SELECT familia_variante_id FROM produto_variantes WHERE produto_id=? AND ativo=1 ORDER BY eh_generica DESC, id LIMIT 1');
            $s->execute([$produto]); $familia = (int)$s->fetchColumn();
        }
        if ($familia <= 0 && $produto > 0) {
            $s=$conn->prepare('SELECT id FROM familias_variantes WHERE produto_generico_id=? AND ativo=1 LIMIT 1');
            $s->execute([$produto]);$familia=(int)$s->fetchColumn();
        }
        $generico=$produto;
        if($familia>0) {
            $g = $conn->prepare('SELECT produto_generico_id FROM familias_variantes WHERE id=? AND ativo=1');
            $g->execute([$familia]); $generico = (int)$g->fetchColumn();
        }
        if ($generico <= 0) {
            $g = $conn->prepare('SELECT produto_id FROM produto_variantes WHERE familia_variante_id=? AND eh_generica=1 AND ativo=1 ORDER BY id LIMIT 1');
            $g->execute([$familia]); $generico = (int)$g->fetchColumn();
        }
        if($generico<=0)$generico=$produto;
        if ($generico <= 0) return [];
        $sql = 'SELECT pc.produto_filho_id AS produto_id, pc.quantidade, pc.observacoes AS composicao_observacoes,
                       p.nome_produto, p.tipo_produto, p.dimensoes, p.material,
                       p.descricao_detalhada, p.observacoes
                FROM produto_composicao pc JOIN produtos p ON p.id=pc.produto_filho_id
                WHERE pc.produto_pai_id=? AND (p.tipo_produto IN (\'SERVICO\',\'COMPONENTE\') OR p.nome_produto LIKE \'%Capa%\')
                ORDER BY pc.id';
        $s = $conn->prepare($sql); $s->execute([$generico]); $refs=[];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $texto = trim(implode("\n", array_filter([$r['descricao_detalhada'], $r['observacoes'], $r['composicao_observacoes']] )));
            $refs[] = [
                'produto_id'=>(int)$r['produto_id'], 'nome'=>$r['nome_produto'], 'quantidade'=>(float)$r['quantidade'],
                'tipo'=>$r['tipo_produto'], 'dimensoes'=>$r['dimensoes'], 'material'=>$r['material'],
                'ficha_tecnica'=>$texto, 'cor_solicitada'=>$item['cor'] ?? $item['cor_solicitada'] ?? null,
                'origem'=>'composicao_produto_generico', 'familia_variante_id'=>$familia,
            ];
        }
        return $refs;
    }
}
