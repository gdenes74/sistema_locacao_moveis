<?php
/**
 * Pendências de produção são planejamento operacional. Elas nunca movimentam
 * estoque nem alteram a disponibilidade dos produtos por conta própria.
 */
class ProducaoPendente
{
    private PDO $conn;
    private string $tabela = 'producao_pendencias';

    public const STATUS_VALIDOS = ['PENDENTE', 'PROGRAMADA', 'PRODUZIDA', 'ENCERRADA', 'CANCELADA'];
    public const ORIGENS_VALIDAS = ['PEDIDO', 'ORCAMENTO', 'MANUAL'];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function schemaInstalado(): bool
    {
        try {
            return (bool) $this->conn->query("SHOW TABLES LIKE 'producao_pendencias'")->fetchColumn();
        } catch (Throwable $e) {
            error_log('[ProducaoPendente] Falha ao verificar schema: ' . $e->getMessage());
            return false;
        }
    }

    public function listar(array $filtros = []): array
    {
        $sql = "SELECT pp.*, c.nome AS cliente_nome,
                       p.nome_produto AS produto_nome,
                       cp.nome_produto AS componente_nome,
                       cp.tipo_produto AS componente_tipo,
                       cp.dimensoes AS componente_dimensoes,
                       cp.material AS componente_material,
                       cp.cor AS componente_cor_cadastrada,
                       cp.descricao_detalhada AS componente_descricao_detalhada,
                       cp.observacoes AS componente_observacoes
                FROM {$this->tabela} pp
                LEFT JOIN clientes c ON c.id = pp.cliente_id
                LEFT JOIN produtos p ON p.id = pp.produto_id
                LEFT JOIN produtos cp ON cp.id = pp.componente_id
                WHERE 1 = 1";
        $params = [];

        if (!empty($filtros['status']) && in_array($filtros['status'], self::STATUS_VALIDOS, true)) {
            $sql .= ' AND pp.status = :status';
            $params[':status'] = $filtros['status'];
        } elseif (empty($filtros['status'])) {
            $sql .= " AND pp.status <> 'CANCELADA'";
        }
        if (!empty($filtros['busca'])) {
            $sql .= ' AND (pp.descricao_item LIKE :busca1 OR pp.cor LIKE :busca2 OR c.nome LIKE :busca3 OR p.nome_produto LIKE :busca4)';
            $busca = '%' . trim((string) $filtros['busca']) . '%';
            $params[':busca1'] = $busca;
            $params[':busca2'] = $busca;
            $params[':busca3'] = $busca;
            $params[':busca4'] = $busca;
        }
        if (!empty($filtros['data_de'])) {
            $sql .= ' AND COALESCE(pp.data_entrega, pp.data_evento) >= :data_de';
            $params[':data_de'] = $this->normalizarData($filtros['data_de']);
        }
        if (!empty($filtros['data_ate'])) {
            $sql .= ' AND COALESCE(pp.data_entrega, pp.data_evento) <= :data_ate';
            $params[':data_ate'] = $this->normalizarData($filtros['data_ate']);
        }

        $sql .= " ORDER BY FIELD(pp.status, 'PENDENTE', 'PROGRAMADA', 'PRODUZIDA', 'ENCERRADA', 'CANCELADA'),
                          COALESCE(pp.data_entrega, pp.data_evento, '9999-12-31') ASC, pp.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obterPorId(int $id): ?array
    {
        $linhas = $this->listarPorCondicao('pp.id = :id', [':id' => $id]);
        $item=$linhas[0]??null;
        if($item && empty($item['componente_id'])) {
            require_once __DIR__.'/ReferenciaCorteProducao.php';
            $refs=ReferenciaCorteProducao::buscar($this->conn,$item);$textos=[];
            foreach($refs as $ref)if(strtoupper((string)$ref['tipo'])==='SERVICO') {
                $textos[]=$ref['nome']."\n".$ref['ficha_tecnica'];
                if(empty($item['componente_material']))$item['componente_material']=$ref['material'];
            }
            $item['referencia_corte_texto']=implode("\n\n",$textos);
        }
        return $item;
    }

    public function criar(array $dados, ?int $usuarioId = null): int
    {
        $dados = $this->normalizarDados($dados);
        $this->validar($dados);

        $sql = "INSERT INTO {$this->tabela}
            (origem_tipo, origem_id, pedido_id, orcamento_id, cliente_id, produto_id, componente_id,
             descricao_item, cor, quantidade_solicitada, quantidade_faltante, data_evento, data_entrega,
             observacoes, status, criado_por, atualizado_por)
            VALUES
            (:origem_tipo, :origem_id, :pedido_id, :orcamento_id, :cliente_id, :produto_id, :componente_id,
             :descricao_item, :cor, :quantidade_solicitada, :quantidade_faltante, :data_evento, :data_entrega,
             :observacoes, :status, :criado_por, :atualizado_por)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':origem_tipo' => $dados['origem_tipo'], ':origem_id' => $dados['origem_id'],
            ':pedido_id' => $dados['pedido_id'], ':orcamento_id' => $dados['orcamento_id'],
            ':cliente_id' => $dados['cliente_id'], ':produto_id' => $dados['produto_id'],
            ':componente_id' => $dados['componente_id'], ':descricao_item' => $dados['descricao_item'],
            ':cor' => $dados['cor'], ':quantidade_solicitada' => $dados['quantidade_solicitada'],
            ':quantidade_faltante' => $dados['quantidade_faltante'], ':data_evento' => $dados['data_evento'],
            ':data_entrega' => $dados['data_entrega'], ':observacoes' => $dados['observacoes'],
            ':status' => $dados['status'], ':criado_por' => $usuarioId, ':atualizado_por' => $usuarioId,
        ]);
        $id = (int) $this->conn->lastInsertId();
        $this->registrarHistorico($id, 'CRIADA', null, $dados['status'],
            $dados['origem_tipo'] === 'MANUAL' ? 'Pendência cadastrada manualmente.' : 'Ficha gerada a partir do documento de origem.', $usuarioId);
        return $id;
    }

    public function atualizarDados(int $id, array $dados, ?int $usuarioId = null): void
    {
        $atual = $this->obterPorId($id);
        if (!$atual) throw new RuntimeException('Pendência não encontrada.');
        // A origem continua vinculada ao documento que gerou a ficha.
        $campos = ['componente_id', 'descricao_item', 'cor', 'quantidade_solicitada',
            'quantidade_faltante', 'data_entrega', 'observacoes'];
        $editaveis = array_intersect_key($dados, array_flip($campos));
        $novo = $this->normalizarDados(array_merge($atual, $editaveis));
        $this->validar($novo);
        $propriaTransacao = !$this->conn->inTransaction();
        if ($propriaTransacao) $this->conn->beginTransaction();
        try {
            $params = [':id' => $id, ':usuario' => $usuarioId];
            $sets = [];
            $alterados = [];
            foreach ($campos as $campo) {
                $sets[] = "$campo = :$campo";
                $params[":$campo"] = $novo[$campo];
                if ((string)($atual[$campo] ?? '') !== (string)($novo[$campo] ?? '')) {
                    $alterados[$campo] = ['antes' => $atual[$campo] ?? null, 'depois' => $novo[$campo]];
                }
            }
            $stmt = $this->conn->prepare("UPDATE {$this->tabela} SET " . implode(', ', $sets)
                . ', atualizado_por=:usuario, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            $stmt->execute($params);
            if ($alterados) $this->registrarHistorico($id, 'DADOS_ALTERADOS', $atual['status'], $atual['status'],
                json_encode($alterados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $usuarioId);
            if ($propriaTransacao) $this->conn->commit();
        } catch (Throwable $erro) {
            if ($propriaTransacao && $this->conn->inTransaction()) $this->conn->rollBack();
            throw $erro;
        }
    }

    public function alterarStatus(int $id, string $novoStatus, ?string $observacao = null, ?int $usuarioId = null): void
    {
        if (!in_array($novoStatus, self::STATUS_VALIDOS, true)) {
            throw new InvalidArgumentException('Status de produção inválido.');
        }
        $atual = $this->obterPorId($id);
        if (!$atual) {
            throw new RuntimeException('Pendência de produção não encontrada.');
        }
        $stmt = $this->conn->prepare("UPDATE {$this->tabela}
            SET status = :status, atualizado_por = :usuario, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id");
        $stmt->execute([':status' => $novoStatus, ':usuario' => $usuarioId, ':id' => $id]);
        $this->registrarHistorico($id, 'STATUS_ALTERADO', $atual['status'], $novoStatus, $observacao, $usuarioId);
    }

    public function opcoesClientes(): array
    {
        return $this->conn->query('SELECT id, nome FROM clientes ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function opcoesProdutos(): array
    {
        return $this->conn->query('SELECT id, nome_produto FROM produtos ORDER BY nome_produto ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Calcula consumo estimado sem gravar nada; medidas em centímetros. */
    public function calcularTecido(float $quantidade, array $pecas, float $larguraTecido): array
    {
        $q = max(0, $quantidade); $largura = max(1, $larguraTecido); $area = 0.0; $memoria = [];
        foreach ($pecas as $peca) {
            $larg = max(0, (float)($peca['largura'] ?? 0)); $comp = max(0, (float)($peca['comprimento'] ?? 0));
            $folga = max(0, (float)($peca['folga'] ?? 0)); $n = max(1, (int)($peca['quantidade'] ?? 1));
            $l = $larg + $folga; $c = $comp + $folga; $areaPeca = $q * $n * ($l/100) * ($c/100); $area += $areaPeca;
            $memoria[] = ['nome'=>$peca['nome'] ?? 'Peça', 'quantidade'=>$q*$n, 'medida'=>sprintf('%.1f x %.1f cm',$l,$c), 'area_m2'=>$areaPeca];
        }
        return ['quantidade'=>$q,'largura_tecido_cm'=>$largura,'area_total_m2'=>$area,'metros_lineares_estimados'=>$area/($largura/100),'memoria'=>$memoria];
    }

    /**
     * Cria fichas somente para uma cor livre ainda sem variante física.
     * Nunca altera produtos, capas nem estoque. É idempotente por pedido/produto/cor.
     */
    public function sincronizarCoresNovasDoPedido(int $pedidoId, ?int $usuarioId = null): int
    {
        if (!$this->schemaInstalado() || $pedidoId <= 0) return 0;
        $pedido = $this->conn->prepare('SELECT id,cliente_id,data_evento,data_entrega,hora_entrega,turno_entrega,
            data_devolucao_prevista,hora_devolucao,turno_devolucao,situacao_pedido FROM pedidos WHERE id=?');
        $pedido->execute([$pedidoId]); $cab=$pedido->fetch(PDO::FETCH_ASSOC);
        if (!$cab) return 0;
        $ativos = [];
        if (strtolower((string)$cab['situacao_pedido']) !== 'confirmado') {
            $this->encerrarFichasSemNecessidade($pedidoId, $ativos, 'Pedido deixou de estar confirmado.', $usuarioId);
            return 0;
        }
        $q=$this->conn->prepare("SELECT DISTINCT ip.produto_id,ip.quantidade,ip.observacoes,p.nome_produto,p.tipo_produto,p.descricao_detalhada AS ficha_produto,f.id familia_id,COALESCE(f.nome,p.nome_produto) familia_nome
            FROM itens_pedido ip JOIN produtos p ON p.id=ip.produto_id
            LEFT JOIN produto_variantes pv ON pv.produto_id=p.id AND pv.ativo=1 AND pv.eh_generica=1
            LEFT JOIN familias_variantes f ON (f.id=pv.familia_variante_id OR f.produto_generico_id=p.id) AND f.ativo=1
            WHERE ip.pedido_id=? AND (f.modo_impacto_estoque='COMPONENTE_COR'
                OR (p.nome_produto LIKE '%Cor A Definir' AND EXISTS (
                    SELECT 1 FROM produto_composicao pc JOIN produtos s ON s.id=pc.produto_filho_id
                    WHERE pc.produto_pai_id=p.id AND s.tipo_produto='SERVICO' AND s.nome_produto LIKE 'Serviço Capa %'
                )))");
        $q->execute([$pedidoId]); $criados=0;
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item){
            $guardanapoGenerico=$item['tipo_produto']==='SIMPLES'
                && preg_match('/^guardanapo\s+cor\s+a\s+definir$/iu',(string)$item['nome_produto']);
            $observacao=trim((string)$item['observacoes']);
            if (preg_match('/(?:^|[\r\n]|\|)\s*cor\s*:\s*([^|\r\n]+)/iu', $observacao, $m)) {
                $cor=trim($m[1]);
            } elseif (preg_match('/^\p{L}[\p{L}\s-]{0,32}$/u', $observacao)
                && count(preg_split('/\s+/u', $observacao)) <= 3) {
                // Mesmo formato curto aceito pelo formulário, por exemplo Neon.
                $cor=$observacao;
            } elseif ($guardanapoGenerico) {
                $cor='A definir';
            } else {
                continue;
            }
            if($cor==='' || preg_match('/^(?:cor\s+)?a\s*definir$/iu',$cor)) {
                if (!$guardanapoGenerico) continue;
                $cor='A definir';
            }
            $normalizar=static function(string $v):string { $v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v)?:$v; $v=strtolower($v); return trim((string)preg_replace('/[^a-z0-9]+/','-',$v),'-'); };
            $slug=$normalizar($cor);
            $corId=$this->conn->prepare('SELECT c.id FROM cores_padrao c LEFT JOIN cor_aliases a ON a.cor_padrao_id=c.id AND a.ativo=1 WHERE c.ativo=1 AND (c.slug_normalizado=? OR a.alias_normalizado=?) LIMIT 1'); $corId->execute([$slug,$slug]); $idCor=$corId->fetchColumn();
            $tem=$this->conn->prepare('SELECT 1 FROM produto_variantes WHERE familia_variante_id=? AND cor_padrao_id=? AND ativo=1 AND eh_generica=0 LIMIT 1'); if($idCor && !$guardanapoGenerico){$tem->execute([$item['familia_id'],$idCor]); if($tem->fetchColumn()) continue;}
            $prefixo=$guardanapoGenerico ? 'Novo guardanapo/cor:' : 'Nova capa/cor:';
            $existe=$this->conn->prepare("SELECT id,status FROM {$this->tabela} WHERE pedido_id=? AND produto_id=? AND cor=? AND descricao_item LIKE ? AND status IN ('PENDENTE','PROGRAMADA','ENCERRADA') ORDER BY FIELD(status,'PENDENTE','PROGRAMADA','ENCERRADA'), id LIMIT 1");
            $existe->execute([$pedidoId,$item['produto_id'],$cor,$prefixo.'%']);
            $existente=$existe->fetch(PDO::FETCH_ASSOC) ?: [];
            $idExistente=(int)($existente['id'] ?? 0);
            if ($idExistente) {
                if (($existente['status'] ?? '') === 'ENCERRADA') {
                    $this->alterarStatus($idExistente,'PENDENTE','Necessidade voltou após alteração do pedido.',$usuarioId);
                }
                $ativos[$idExistente]=true;
                $this->atualizarDados($idExistente, ['quantidade_solicitada'=>$item['quantidade'],
                    'quantidade_faltante'=>$item['quantidade'],'data_entrega'=>$cab['data_entrega']], $usuarioId);
                continue;
            }
            $observacoesProducao=$guardanapoGenerico
                ? 'Guardanapo sob demanda da família '.$item['familia_nome'].'. Confira se já existe produto físico desta cor antes de produzir. Após autorizado e produzido, cadastre/conte as unidades reais no estoque; a ficha não aumenta o estoque automaticamente.'
                : 'Cor sem variante física cadastrada na família '.$item['familia_nome'].'. Após produzir, cadastre/conte a capa real no estoque.';
            if ($guardanapoGenerico && trim((string)$item['ficha_produto']) !== '')
                $observacoesProducao .= "\n\n".trim((string)$item['ficha_produto']);
            $idNovo=$this->criar(['origem_tipo'=>'PEDIDO','origem_id'=>$pedidoId,'pedido_id'=>$pedidoId,'cliente_id'=>$cab['cliente_id'],'produto_id'=>$item['produto_id'],'descricao_item'=>$prefixo.' '.$item['nome_produto'],'cor'=>$cor,'quantidade_solicitada'=>$item['quantidade'],'quantidade_faltante'=>$item['quantidade'],'data_evento'=>$cab['data_evento'],'data_entrega'=>$cab['data_entrega'],'observacoes'=>$observacoesProducao,'status'=>'PENDENTE'],$usuarioId);
            $ativos[$idNovo]=true;
            $criados++;
        }

        // Forração sob demanda é um serviço executado para todo pedido confirmado.
        // Não depende de falta de estoque, não cria capa física e não movimenta
        // patrimônio. A ficha leva a quantidade do item e o plano cadastrado no serviço.
        $forracoes = $this->conn->prepare("SELECT ip.produto_id, ip.quantidade, ip.observacoes,
                p.nome_produto, f.id AS familia_id, f.nome AS familia_nome,
                s.id AS servico_id, s.nome_produto AS servico_nome,
                s.descricao_detalhada AS servico_descricao, s.observacoes AS servico_observacoes,
                s.material AS servico_material, pc.quantidade AS servicos_por_item
            FROM itens_pedido ip
            JOIN produtos p ON p.id=ip.produto_id
            JOIN familias_variantes f ON f.ativo=1
                AND f.modo_impacto_estoque='FORRACAO_PRODUCAO'
                AND (f.produto_generico_id=p.id OR EXISTS (
                    SELECT 1 FROM produto_variantes pv
                    WHERE pv.familia_variante_id=f.id AND pv.produto_id=p.id
                      AND pv.ativo=1 AND pv.eh_generica=1
                ))
            JOIN produto_composicao pc ON pc.produto_pai_id=p.id AND pc.quantidade>0
            JOIN produtos s ON s.id=pc.produto_filho_id AND s.tipo_produto='SERVICO'
            WHERE ip.pedido_id=?");
        $forracoes->execute([$pedidoId]);
        // Um sofá pode ter serviço de capa do assento e do encosto. Eles
        // continuam tecnicamente separados na composição, mas a produção do
        // lote deve aparecer em uma única ficha de tecido por produto/cor.
        $forracoesAgrupadas=[];
        foreach ($forracoes->fetchAll(PDO::FETCH_ASSOC) as $linhaForracao) {
            $observacaoItem = trim((string)$linhaForracao['observacoes']);
            $cor = null;
            if (preg_match('/(?:^|[\r\n|])\s*cor\s*:\s*([^|\r\n]+)/iu', $observacaoItem, $m)) $cor=trim($m[1]);
            elseif (preg_match('/^\p{L}[\p{L}\s-]{0,32}$/u', $observacaoItem)) $cor=$observacaoItem;
            $chaveForracao=(int)$linhaForracao['produto_id'].'|'.mb_strtolower((string)$cor);
            if(!isset($forracoesAgrupadas[$chaveForracao])){
                $forracoesAgrupadas[$chaveForracao]=['base'=>$linhaForracao,'cor'=>$cor,'qtd'=>0,'servicos'=>[],'ids'=>[]];
            }
            $qtdLinha=max(0,(float)$linhaForracao['quantidade']*(float)$linhaForracao['servicos_por_item']);
            $forracoesAgrupadas[$chaveForracao]['qtd']=max($forracoesAgrupadas[$chaveForracao]['qtd'],$qtdLinha);
            $ficha = implode("\n\n", array_unique(array_filter([
                trim((string)$linhaForracao['servico_descricao']),
                trim((string)$linhaForracao['servico_observacoes']),
            ])));
            if (!empty($linhaForracao['servico_material'])) $ficha .= ($ficha?"\n\n":'').'Material cadastrado: '.$linhaForracao['servico_material'].'.';
            $forracoesAgrupadas[$chaveForracao]['servicos'][]=['id'=>(int)$linhaForracao['servico_id'],'nome'=>$linhaForracao['servico_nome'],'ficha'=>$ficha];
            $forracoesAgrupadas[$chaveForracao]['ids'][]=(int)$linhaForracao['servico_id'];
        }
        foreach ($forracoesAgrupadas as $grupoForracao) {
            $forracao=$grupoForracao['base']; $qtd=(float)$grupoForracao['qtd'];
            if ($qtd <= 0 || !$grupoForracao['servicos']) continue;
            $cor=$grupoForracao['cor'];
            $fichasGrupo=array_values(array_filter(array_map(static fn($s)=>trim((string)$s['ficha']),$grupoForracao['servicos'])));
            $nomesGrupo=array_values(array_filter(array_map(static fn($s)=>trim((string)$s['nome']),$grupoForracao['servicos'])));
            $ficha=implode("\n\n",array_unique($fichasGrupo));
            $descricao = 'Forração/costura para o pedido: '.$forracao['nome_produto'];
            $observacoes = 'Serviço de forração a executar para este pedido. Não gera capa física nem movimenta estoque.'
                .($nomesGrupo?"\n\nPartes de tecido nesta ficha: ".implode(' + ',$nomesGrupo).'.':'')
                .($ficha?"\n\n".$ficha:'');
            $idsGrupo=$grupoForracao['ids']; $primario=(int)$idsGrupo[0];
            $existe = $this->conn->prepare("SELECT id,status,componente_id FROM {$this->tabela}
                WHERE pedido_id=? AND produto_id=? AND componente_id IN (".implode(',',array_fill(0,count($idsGrupo),'?')).")
                  AND status IN ('PENDENTE','PROGRAMADA','ENCERRADA')
                ORDER BY FIELD(status,'PENDENTE','PROGRAMADA','ENCERRADA'), id");
            $existe->execute(array_merge([$pedidoId,$forracao['produto_id']],$idsGrupo));
            $existentes=$existe->fetchAll(PDO::FETCH_ASSOC);
            $idsPendentes=array_map(static fn(array $r): int => (int)$r['id'],$existentes);
            $idPendente=(int)($idsPendentes[0]??0);
            if($idPendente){
                $primario=(int)($existentes[0]['componente_id'] ?? 0);
                if (($existentes[0]['status'] ?? '') === 'ENCERRADA') {
                    $this->alterarStatus($idPendente,'PENDENTE','Necessidade de forração voltou após alteração do pedido.',$usuarioId);
                }
            }
            $dados=['componente_id'=>$primario,'descricao_item'=>$descricao,
                'cor'=>$cor,'quantidade_solicitada'=>$qtd,'quantidade_faltante'=>$qtd,
                'data_entrega'=>$cab['data_entrega'],'observacoes'=>$observacoes];
            if($idPendente){
                $ativos[$idPendente]=true;
                $this->atualizarDados($idPendente,$dados,$usuarioId);
                foreach(array_slice($idsPendentes,1) as $duplicado){
                    $this->alterarStatus((int)$duplicado,'CANCELADA','Consolidada na ficha de tecido #'.$idPendente.'.',$usuarioId);
                }
                continue;
            }
            $idNovo=$this->criar(array_merge($dados,[
                'origem_tipo'=>'PEDIDO','origem_id'=>$pedidoId,'pedido_id'=>$pedidoId,
                'cliente_id'=>$cab['cliente_id'],'produto_id'=>$forracao['produto_id'],
                'data_evento'=>$cab['data_evento'],'status'=>'PENDENTE'
            ]),$usuarioId);
            $ativos[$idNovo]=true;
            $criados++;
        }

        if (empty($cab['data_entrega']) || empty($cab['data_devolucao_prevista'])) {
            // A ausência de datas não prova que a necessidade física terminou.
            return $criados;
        }
        $criados += $this->sincronizarFaltasFisicas($pedidoId, $cab, $ativos, $usuarioId);
        $this->encerrarFichasSemNecessidade($pedidoId, $ativos,
            'Necessidade encerrada após atualização do pedido, cor, quantidade ou estoque no período.', $usuarioId);
        return $criados;
    }

    /** Estoque físico compartilhado é consultado uma vez por componente para o pedido inteiro. */
    private function sincronizarFaltasFisicas(int $pedidoId, array $cab, array &$ativos, ?int $usuarioId): int
    {
        if (empty($cab['data_entrega']) || empty($cab['data_devolucao_prevista'])) {
            // Sem janela confiável, não há como recalcular nem encerrar fichas físicas.
            return 0;
        }
        require_once __DIR__ . '/EstoqueMovimentacao.php';
        $estoque = new EstoqueMovimentacao($this->conn);
        // O conjunto pai não é físico; as linhas internas consomem estoque e geram faltas.
        $itens = $this->conn->prepare("SELECT ip.produto_id, SUM(ip.quantidade) AS quantidade,
                p.nome_produto, p.cor AS cor_produto, p.tipo_produto, p.controla_estoque
            FROM itens_pedido ip JOIN produtos p ON p.id=ip.produto_id
            WHERE ip.pedido_id=? AND ip.tipo_linha IN ('PRODUTO','ITEM_CONJUNTO') AND ip.tipo='locacao'
            GROUP BY ip.produto_id,p.nome_produto,p.cor,p.tipo_produto,p.controla_estoque
            ORDER BY ip.produto_id");
        $itens->execute([$pedidoId]);
        $itens = $itens->fetchAll(PDO::FETCH_ASSOC);
        $pools = [];
        foreach ($this->conn->query('SELECT produto_pai_id,produto_principal_id,produto_adicional_id
            FROM produto_estoque_alternativo')->fetchAll(PDO::FETCH_ASSOC) as $pool) {
            $pools[(int)$pool['produto_pai_id']] = $pool;
        }
        $produtoFisico = $this->conn->prepare('SELECT id,nome_produto,cor,material,
            descricao_detalhada,observacoes,dimensoes FROM produtos WHERE id=?');
        $genericoSimples=$this->conn->prepare("SELECT 1 FROM familias_variantes WHERE produto_generico_id=? AND ativo=1 AND modo_impacto_estoque='COMPONENTE_COR' LIMIT 1");
        $demanda = []; $porProduto = []; $contexto = []; $demandaPool = [];
        foreach ($itens as $item) {
            $pid = (int)$item['produto_id'];
            if ($item['tipo_produto']==='SIMPLES') {
                $genericoSimples->execute([$pid]);
                if ($genericoSimples->fetchColumn()) continue; // Cor sob demanda já possui ficha própria por cor.
            }
            $contexto[] = ['produto_id'=>$pid,'quantidade'=>(float)$item['quantidade']];
            $pecas = $this->obterPecasFisicasComposicao($pid);
            if (isset($pools[$pid])) {
                $pool = $pools[$pid];
                $principal = (int)$pool['produto_principal_id'];
                $pecas = array_values(array_filter($pecas,
                    static fn($peca)=>(int)$peca['id'] !== $principal));
                $adicional = (int)$pool['produto_adicional_id'];
                $produtoFisico->execute([$adicional]); $fisico = $produtoFisico->fetch(PDO::FETCH_ASSOC);
                if ($fisico) {
                    $pecas[] = ['id'=>$adicional,'por_item'=>1,
                        'nome_produto'=>'Estruturas de cubo para forração (estoque compartilhado)',
                        'cor'=>$fisico['cor'],'material'=>$fisico['material'],
                        'descricao_detalhada'=>$fisico['descricao_detalhada'],
                        'observacoes'=>$fisico['observacoes'],'dimensoes'=>$fisico['dimensoes']];
                    $demandaPool[$pid] = (float)$item['quantidade'];
                }
            }
            if (!$pecas && in_array($item['tipo_produto'], ['COMPONENTE','SIMPLES'], true)
                && (int)$item['controla_estoque'] === 1) {
                $pecas = [[ 'id'=>$pid, 'por_item'=>1, 'nome_produto'=>$item['nome_produto'],
                    'cor'=>$item['cor_produto'], 'material'=>null, 'descricao_detalhada'=>null,
                    'observacoes'=>null, 'dimensoes'=>null ]];
            }
            foreach ($pecas as $peca) {
                $cid = (int)$peca['id'];
                $qtd = (float)$item['quantidade'] * (float)$peca['por_item'];
                if (!isset($pools[$pid]) || $cid !== (int)$pools[$pid]['produto_adicional_id']) {
                    $demanda[$cid] = ($demanda[$cid] ?? 0) + $qtd;
                }
                $porProduto[$pid][$cid] = ['peca'=>$peca, 'quantidade'=>$qtd,
                    'produto'=>$item];
            }
        }
        $faltas = []; $saldos = [];
        foreach ($demanda as $cid=>$quantidade) {
            $consulta = $estoque->consultarDisponibilidadePeriodo((int)$cid,
                $cab['data_entrega'], $cab['hora_entrega'], $cab['turno_entrega'],
                $cab['data_devolucao_prevista'], $cab['hora_devolucao'], $cab['turno_devolucao'],
                (int)ceil($quantidade), $pedidoId);
            if (empty($consulta['consulta_periodo_valida'])) {
                throw new RuntimeException('Não foi possível consultar o período para o componente #'.$cid.'.');
            }
            $faltas[$cid] = max(0, (float)($consulta['faltante_orcamento'] ?? 0));
            $saldos[$cid] = (float)($consulta['livre_periodo'] ?? 0);
        }
        foreach ($demandaPool as $pid=>$quantidade) {
            $consulta = $estoque->consultarDisponibilidadePeriodo((int)$pid,
                $cab['data_entrega'], $cab['hora_entrega'], $cab['turno_entrega'],
                $cab['data_devolucao_prevista'], $cab['hora_devolucao'], $cab['turno_devolucao'],
                (int)ceil($quantidade), $pedidoId, $contexto);
            if (empty($consulta['consulta_periodo_valida'])) {
                throw new RuntimeException('Não foi possível consultar o estoque conjunto do produto #'.$pid.'.');
            }
            $cid = (int)$pools[$pid]['produto_adicional_id'];
            $faltas[$cid] = max(0,(float)($consulta['faltante_orcamento'] ?? 0));
            $saldos[$cid] = (float)($consulta['livre_periodo'] ?? 0);
        }
        $criados = 0; $atribuido = [];
        foreach ($porProduto as $pid=>$pecas) {
            $grupos = [];
            foreach ($pecas as $cid=>$info) {
                $nome = (string)$info['peca']['nome_produto'];
                $tipo = preg_match('/^capa\b/iu',$nome) ? 'CAPAS'
                    : (preg_match('/^colch[aã]o\b/iu',$nome) ? 'COLCHOES' : 'FISICO_'.$cid);
                $grupos[$tipo][$cid] = $info;
            }
            foreach ($grupos as $tipo=>$grupo) {
                $ids = array_keys($grupo); $faltantes = [];
                foreach ($grupo as $cid=>$info) {
                    if (($faltas[$cid] ?? 0) > 0 && !isset($atribuido[$cid])) {
                        $faltantes[$cid] = $faltas[$cid];
                        $atribuido[$cid] = true;
                    }
                }
                if (!$faltantes) continue;
                $principal = (int)array_key_first($faltantes);
                $necessaria = max(array_map(static fn($i)=>(float)$i['quantidade'], $grupo));
                $faltante = max($faltantes);
                $item = reset($grupo)['produto'];
                $rotulo = $tipo === 'CAPAS' ? 'Reposição de capas: '
                    : ($tipo === 'COLCHOES' ? 'Produção de colchão assento e encosto: '
                    : 'Produção de componente: ');
                $descricao = $rotulo.$item['nome_produto'];
                $detalhes = [];
                foreach ($grupo as $cid=>$info) {
                    $p = $info['peca'];
                    $detalhes[] = implode("\n", array_filter([
                        $p['nome_produto'].' — neste pedido: '.$info['quantidade'].'; livre antes: '.($saldos[$cid] ?? 0)
                            .'; faltam no período: '.($faltas[$cid] ?? 0).'.',
                        $p['dimensoes'] ? 'Dimensões: '.$p['dimensoes'] : null,
                        trim((string)$p['descricao_detalhada']), trim((string)$p['observacoes'])
                    ]));
                }
                $observacoes = 'Necessidade física calculada pelo estoque compartilhado no período. '
                    .'A produção não movimenta estoque automaticamente.' . "\n\n"
                    .implode("\n\n",array_unique($detalhes));
                $placeholders = implode(',',array_fill(0,count($ids),'?'));
                $busca = $this->conn->prepare("SELECT id,componente_id,observacoes,status FROM {$this->tabela}
                    WHERE pedido_id=? AND produto_id=? AND componente_id IN ($placeholders)
                      AND status IN ('PENDENTE','PROGRAMADA','ENCERRADA')
                      AND (descricao_item LIKE 'Produção de %' OR descricao_item LIKE 'Reposição de %')
                    ORDER BY FIELD(status,'PENDENTE','PROGRAMADA','ENCERRADA'), id");
                $busca->execute(array_merge([$pedidoId,$pid],$ids));
                $existentes = $busca->fetchAll(PDO::FETCH_ASSOC);
                if ($existentes) {
                    $id = (int)$existentes[0]['id']; $ativos[$id]=true;
                    $dados = ['componente_id'=>(int)$existentes[0]['componente_id'],
                        'descricao_item'=>$descricao, 'cor'=>$tipo==='CAPAS'
                            ? ($grupo[$principal]['peca']['cor'] ?: $item['cor_produto'])
                            : ($item['cor_produto'] ?: $grupo[$principal]['peca']['cor']),
                        'quantidade_solicitada'=>$necessaria,'quantidade_faltante'=>$faltante,
                        'data_entrega'=>$cab['data_entrega']];
                    if ($existentes[0]['status'] === 'PENDENTE') $dados['observacoes'] = $observacoes;
                    $this->atualizarDados($id,$dados,$usuarioId);
                    if ($existentes[0]['status'] === 'ENCERRADA') {
                        $this->alterarStatus($id,'PENDENTE','Necessidade voltou após atualização do pedido/estoque.', $usuarioId);
                    }
                    foreach (array_slice($existentes,1) as $duplicada) {
                        $this->alterarStatus((int)$duplicada['id'],'ENCERRADA',
                            'Ficha consolidada na #'.$id.'.',$usuarioId);
                    }
                } else {
                    $id = $this->criar(['origem_tipo'=>'PEDIDO','origem_id'=>$pedidoId,
                        'pedido_id'=>$pedidoId,'cliente_id'=>$cab['cliente_id'],'produto_id'=>$pid,
                        'componente_id'=>$principal,'descricao_item'=>$descricao,
                        'cor'=>$tipo==='CAPAS' ? ($grupo[$principal]['peca']['cor'] ?: $item['cor_produto'])
                            : ($item['cor_produto'] ?: $grupo[$principal]['peca']['cor']),
                        'quantidade_solicitada'=>$necessaria,'quantidade_faltante'=>$faltante,
                        'data_evento'=>$cab['data_evento'],'data_entrega'=>$cab['data_entrega'],
                        'observacoes'=>$observacoes,'status'=>'PENDENTE'],$usuarioId);
                    $ativos[$id]=true; $criados++;
                }
            }
        }
        // Um mesmo componente físico pode ser usado por produtos diferentes no
        // mesmo pedido (por exemplo, a estrutura compartilhada dos sofás). A
        // falta já foi calculada sobre a demanda total; portanto, não podemos
        // deixar uma ficha ativa por produto, pois isso duplica a produção.
        $this->consolidarFichasFisicasCompartilhadas($pedidoId, $ativos, $usuarioId);
        return $criados;
    }

    /** Mantém uma única ficha ativa por componente físico compartilhado no pedido. */
    private function consolidarFichasFisicasCompartilhadas(int $pedidoId, array &$ativos, ?int $usuarioId): void
    {
        $q = $this->conn->prepare("SELECT id,componente_id,quantidade_solicitada,quantidade_faltante
            FROM {$this->tabela}
            WHERE pedido_id=? AND status IN ('PENDENTE','PROGRAMADA')
              AND componente_id IS NOT NULL
              AND (descricao_item LIKE 'Produção de %' OR descricao_item LIKE 'Produção de componente: %'
                   OR descricao_item LIKE 'Produção de colchão%')
            ORDER BY componente_id,id");
        $q->execute([$pedidoId]);
        $porComponente = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $ficha) {
            $cid = (int)$ficha['componente_id'];
            $porComponente[$cid][] = $ficha;
        }
        foreach ($porComponente as $fichas) {
            if (count($fichas) < 2) continue;
            $principal = $fichas[0];
            $faltante = max(array_map(static fn($f)=>(float)$f['quantidade_faltante'], $fichas));
            $solicitada = max(array_map(static fn($f)=>(float)$f['quantidade_solicitada'], $fichas));
            $this->atualizarDados((int)$principal['id'], [
                'quantidade_solicitada'=>$solicitada,
                'quantidade_faltante'=>$faltante
            ], $usuarioId);
            $ativos[(int)$principal['id']] = true;
            foreach (array_slice($fichas, 1) as $duplicada) {
                unset($ativos[(int)$duplicada['id']]);
                $this->alterarStatus((int)$duplicada['id'], 'ENCERRADA',
                    'Ficha consolidada na pendência do componente físico compartilhado #'.(int)$principal['id'].'.', $usuarioId);
            }
        }
    }

    /** Desce por compostos intermediários até as peças físicas, sem somar o pai comercial. */
    private function obterPecasFisicasComposicao(int $produtoId, array $pilha = []): array
    {
        if (in_array($produtoId, $pilha, true)) {
            throw new RuntimeException('Ciclo na composição do produto #'.$produtoId.'.');
        }
        $pilha[] = $produtoId;
        $q = $this->conn->prepare("SELECT pc.produto_filho_id AS id, pc.quantidade AS por_item,
                c.nome_produto,c.cor,c.material,c.descricao_detalhada,c.observacoes,c.dimensoes,
                c.tipo_produto,c.controla_estoque
            FROM produto_composicao pc JOIN produtos c ON c.id=pc.produto_filho_id
            WHERE pc.produto_pai_id=? AND pc.quantidade>0 ORDER BY c.id");
        $q->execute([$produtoId]);
        $pecas = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $filho) {
            $fator = (float)$filho['por_item'];
            if ($filho['tipo_produto'] === 'COMPOSTO') {
                foreach ($this->obterPecasFisicasComposicao((int)$filho['id'], $pilha) as $peca) {
                    $peca['por_item'] = (float)$peca['por_item'] * $fator;
                    $cid = (int)$peca['id'];
                    if (isset($pecas[$cid])) $pecas[$cid]['por_item'] += $peca['por_item'];
                    else $pecas[$cid] = $peca;
                }
            } elseif (in_array($filho['tipo_produto'], ['COMPONENTE','SIMPLES'], true)
                && (int)$filho['controla_estoque'] === 1) {
                $cid = (int)$filho['id'];
                if (isset($pecas[$cid])) $pecas[$cid]['por_item'] += $fator;
                else $pecas[$cid] = [
                    'id'=>$cid, 'por_item'=>$fator, 'nome_produto'=>$filho['nome_produto'],
                    'cor'=>$filho['cor'], 'material'=>$filho['material'],
                    'descricao_detalhada'=>$filho['descricao_detalhada'],
                    'observacoes'=>$filho['observacoes'], 'dimensoes'=>$filho['dimensoes'],
                ];
            }
        }
        return array_values($pecas);
    }

    /** Mantém o histórico e cancela fichas que o pedido não exige mais. */
    private function encerrarFichasSemNecessidade(int $pedidoId, array $ativos, string $motivo, ?int $usuarioId): void
    {
        $q = $this->conn->prepare("SELECT id FROM {$this->tabela}
            WHERE pedido_id=? AND origem_tipo='PEDIDO' AND status IN ('PENDENTE','PROGRAMADA')
              AND (descricao_item LIKE 'Nova capa/cor:%' OR descricao_item LIKE 'Novo guardanapo/cor:%' OR descricao_item LIKE 'Forração/costura para o pedido:%'
                   OR descricao_item LIKE 'Produção de %' OR descricao_item LIKE 'Reposição de %')");
        $q->execute([$pedidoId]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (!isset($ativos[(int)$id])) {
                $this->alterarStatus((int)$id,'CANCELADA',$motivo,$usuarioId);
            }
        }
    }

    /** Reavalia pedidos afetados quando um estoque físico é corrigido no cadastro. */
    public function sincronizarPedidosAfetadosPorProduto(int $produtoId, ?int $usuarioId = null): int
    {
        if (!$this->schemaInstalado() || $produtoId <= 0) return 0;
        $q = $this->conn->prepare("SELECT DISTINCT p.id FROM pedidos p
            JOIN itens_pedido ip ON ip.pedido_id=p.id
            LEFT JOIN produto_composicao pc ON pc.produto_pai_id=ip.produto_id
            LEFT JOIN produto_estoque_alternativo pea ON pea.produto_pai_id=ip.produto_id
            WHERE p.situacao_pedido='confirmado'
              AND p.data_devolucao_prevista >= CURRENT_DATE()
              AND (ip.produto_id=? OR pc.produto_filho_id=?
                   OR pea.produto_principal_id=? OR pea.produto_adicional_id=?)
            UNION SELECT pp.pedido_id FROM {$this->tabela} pp
            JOIN pedidos p ON p.id=pp.pedido_id
            WHERE pp.componente_id=? AND pp.status IN ('PENDENTE','PROGRAMADA')
              AND p.situacao_pedido='confirmado' AND p.data_devolucao_prevista >= CURRENT_DATE()");
        $q->execute([$produtoId,$produtoId,$produtoId,$produtoId,$produtoId]);
        $ids = array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $propria = !$this->conn->inTransaction();
        if ($propria) $this->conn->beginTransaction();
        try {
            foreach ($ids as $id) $this->sincronizarCoresNovasDoPedido($id,$usuarioId);
            if ($propria) $this->conn->commit();
        } catch (Throwable $e) {
            if ($propria && $this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
        return count($ids);
    }

    private function listarPorCondicao(string $condicao, array $params): array
    {
        $sql = "SELECT pp.*, c.nome AS cliente_nome, p.nome_produto AS produto_nome,
                       p.descricao_detalhada AS produto_descricao_detalhada, p.observacoes AS produto_observacoes,
                       cp.nome_produto AS componente_nome, cp.dimensoes AS componente_dimensoes,
                       cp.tipo_produto AS componente_tipo,
                       cp.material AS componente_material, cp.cor AS componente_cor_cadastrada,
                       cp.descricao_detalhada AS componente_descricao_detalhada,
                       cp.observacoes AS componente_observacoes
                FROM {$this->tabela} pp
                LEFT JOIN clientes c ON c.id = pp.cliente_id
                LEFT JOIN produtos p ON p.id = pp.produto_id
                LEFT JOIN produtos cp ON cp.id = pp.componente_id
                WHERE {$condicao}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizarDados(array $dados): array
    {
        $inteiroOuNulo = static fn($valor): ?int => filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int) $valor : null;
        $textoOuNulo = static fn($valor): ?string => trim((string) $valor) !== '' ? trim((string) $valor) : null;
        $origem = strtoupper((string) ($dados['origem_tipo'] ?? 'MANUAL'));
        return [
            'origem_tipo' => in_array($origem, self::ORIGENS_VALIDAS, true) ? $origem : 'MANUAL',
            'origem_id' => $inteiroOuNulo($dados['origem_id'] ?? null),
            'pedido_id' => $inteiroOuNulo($dados['pedido_id'] ?? null),
            'orcamento_id' => $inteiroOuNulo($dados['orcamento_id'] ?? null),
            'cliente_id' => $inteiroOuNulo($dados['cliente_id'] ?? null),
            'produto_id' => $inteiroOuNulo($dados['produto_id'] ?? null),
            'componente_id' => $inteiroOuNulo($dados['componente_id'] ?? null),
            'descricao_item' => $textoOuNulo($dados['descricao_item'] ?? null),
            'cor' => $textoOuNulo($dados['cor'] ?? null),
            'quantidade_solicitada' => max(0, (float) str_replace(',', '.', (string) ($dados['quantidade_solicitada'] ?? 0))),
            'quantidade_faltante' => max(0, (float) str_replace(',', '.', (string) ($dados['quantidade_faltante'] ?? 0))),
            'data_evento' => $this->normalizarData($dados['data_evento'] ?? null),
            'data_entrega' => $this->normalizarData($dados['data_entrega'] ?? null),
            'observacoes' => $textoOuNulo($dados['observacoes'] ?? null),
            'status' => in_array(strtoupper((string) ($dados['status'] ?? 'PENDENTE')), self::STATUS_VALIDOS, true)
                ? strtoupper((string) $dados['status']) : 'PENDENTE',
        ];
    }

    private function validar(array $dados): void
    {
        if (!$dados['produto_id'] && !$dados['descricao_item']) {
            throw new InvalidArgumentException('Informe o produto ou descreva o item a produzir.');
        }
        if ($dados['quantidade_solicitada'] <= 0 && $dados['quantidade_faltante'] <= 0) {
            throw new InvalidArgumentException('Informe uma quantidade solicitada ou faltante maior que zero.');
        }
    }

    private function registrarHistorico(int $pendenciaId, string $acao, ?string $statusAnterior, ?string $statusNovo, ?string $observacao, ?int $usuarioId): void
    {
        $stmt = $this->conn->prepare('INSERT INTO producao_pendencias_historico
            (pendencia_id, acao, status_anterior, status_novo, observacao, usuario_id)
            VALUES (:pendencia_id, :acao, :status_anterior, :status_novo, :observacao, :usuario_id)');
        $stmt->execute([':pendencia_id' => $pendenciaId, ':acao' => $acao, ':status_anterior' => $statusAnterior,
            ':status_novo' => $statusNovo, ':observacao' => $observacao, ':usuario_id' => $usuarioId]);
    }

    private function normalizarData($data): ?string
    {
        $data = trim((string) $data);
        if ($data === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return $data;
        $dt = DateTime::createFromFormat('d/m/Y', $data);
        return $dt ? $dt->format('Y-m-d') : null;
    }
}
