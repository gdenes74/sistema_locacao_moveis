<?php
/**
 * Pendências de produção são planejamento operacional. Elas nunca movimentam
 * estoque nem alteram a disponibilidade dos produtos por conta própria.
 */
class ProducaoPendente
{
    private PDO $conn;
    private string $tabela = 'producao_pendencias';

    public const STATUS_VALIDOS = ['PENDENTE', 'PROGRAMADA', 'PRODUZIDA', 'CANCELADA'];
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

        $sql .= " ORDER BY FIELD(pp.status, 'PENDENTE', 'PROGRAMADA', 'PRODUZIDA', 'CANCELADA'),
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
        $this->registrarHistorico($id, 'CRIADA', null, $dados['status'], 'Pendência cadastrada manualmente.', $usuarioId);
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
        $pedido = $this->conn->prepare('SELECT id,cliente_id,data_evento,data_entrega,situacao_pedido FROM pedidos WHERE id=?');
        $pedido->execute([$pedidoId]); $cab=$pedido->fetch(PDO::FETCH_ASSOC);
        if (!$cab || strtolower((string)$cab['situacao_pedido']) !== 'confirmado') return 0;
        $q=$this->conn->prepare("SELECT DISTINCT ip.produto_id,ip.quantidade,ip.observacoes,p.nome_produto,f.id familia_id,COALESCE(f.nome,p.nome_produto) familia_nome
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
            $observacao=trim((string)$item['observacoes']);
            if (preg_match('/(?:^|[\r\n]|\|)\s*cor\s*:\s*([^|\r\n]+)/iu', $observacao, $m)) {
                $cor=trim($m[1]);
            } elseif (preg_match('/^\p{L}[\p{L}\s-]{0,32}$/u', $observacao)
                && count(preg_split('/\s+/u', $observacao)) <= 3) {
                // Mesmo formato curto aceito pelo formulário, por exemplo Neon.
                $cor=$observacao;
            } else {
                continue;
            }
            if($cor==='' || preg_match('/^(?:cor\s+)?a\s*definir$/iu',$cor)) continue;
            $normalizar=static function(string $v):string { $v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v)?:$v; $v=strtolower($v); return trim((string)preg_replace('/[^a-z0-9]+/','-',$v),'-'); };
            $slug=$normalizar($cor);
            $corId=$this->conn->prepare('SELECT c.id FROM cores_padrao c LEFT JOIN cor_aliases a ON a.cor_padrao_id=c.id AND a.ativo=1 WHERE c.ativo=1 AND (c.slug_normalizado=? OR a.alias_normalizado=?) LIMIT 1'); $corId->execute([$slug,$slug]); $idCor=$corId->fetchColumn();
            $tem=$this->conn->prepare('SELECT 1 FROM produto_variantes WHERE familia_variante_id=? AND cor_padrao_id=? AND ativo=1 AND eh_generica=0 LIMIT 1'); if($idCor){$tem->execute([$item['familia_id'],$idCor]); if($tem->fetchColumn()) continue;}
            $existe=$this->conn->prepare("SELECT id FROM {$this->tabela} WHERE pedido_id=? AND produto_id=? AND cor=? AND status IN ('PENDENTE','PROGRAMADA') LIMIT 1"); $existe->execute([$pedidoId,$item['produto_id'],$cor]); if($existe->fetchColumn()) continue;
            $this->criar(['origem_tipo'=>'PEDIDO','origem_id'=>$pedidoId,'pedido_id'=>$pedidoId,'cliente_id'=>$cab['cliente_id'],'produto_id'=>$item['produto_id'],'descricao_item'=>'Nova capa/cor: '.$item['nome_produto'],'cor'=>$cor,'quantidade_solicitada'=>$item['quantidade'],'quantidade_faltante'=>$item['quantidade'],'data_evento'=>$cab['data_evento'],'data_entrega'=>$cab['data_entrega'],'observacoes'=>'Cor sem variante física cadastrada na família '.$item['familia_nome'].'. Após produzir, cadastre/conte a capa real no estoque.','status'=>'PENDENTE'],$usuarioId);
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
        foreach ($forracoes->fetchAll(PDO::FETCH_ASSOC) as $forracao) {
            $qtd = max(0, (float)$forracao['quantidade'] * (float)$forracao['servicos_por_item']);
            if ($qtd <= 0) continue;
            $observacaoItem = trim((string)$forracao['observacoes']);
            $cor = null;
            if (preg_match('/(?:^|[\r\n|])\s*cor\s*:\s*([^|\r\n]+)/iu', $observacaoItem, $m)) $cor=trim($m[1]);
            elseif (preg_match('/^\p{L}[\p{L}\s-]{0,32}$/u', $observacaoItem)) $cor=$observacaoItem;
            $ficha = implode("\n\n", array_unique(array_filter([
                trim((string)$forracao['servico_descricao']),
                trim((string)$forracao['servico_observacoes']),
            ])));
            if (!empty($forracao['servico_material'])) $ficha .= ($ficha?"\n\n":'').'Material cadastrado: '.$forracao['servico_material'].'.';
            $descricao = 'Forração para o pedido: '.$forracao['nome_produto'];
            $observacoes = 'Serviço de forração a executar para este pedido. Não gera capa física nem movimenta estoque.'
                .($ficha?"\n\n".$ficha:'');
            $existe = $this->conn->prepare("SELECT id FROM {$this->tabela}
                WHERE pedido_id=? AND produto_id=? AND componente_id=?
                  AND status IN ('PENDENTE','PROGRAMADA') LIMIT 1");
            $existe->execute([$pedidoId,$forracao['produto_id'],$forracao['servico_id']]);
            $idPendente=(int)$existe->fetchColumn();
            $dados=['componente_id'=>(int)$forracao['servico_id'],'descricao_item'=>$descricao,
                'cor'=>$cor,'quantidade_solicitada'=>$qtd,'quantidade_faltante'=>$qtd,
                'data_entrega'=>$cab['data_entrega'],'observacoes'=>$observacoes];
            if($idPendente){$this->atualizarDados($idPendente,$dados,$usuarioId);continue;}
            $this->criar(array_merge($dados,[
                'origem_tipo'=>'PEDIDO','origem_id'=>$pedidoId,'pedido_id'=>$pedidoId,
                'cliente_id'=>$cab['cliente_id'],'produto_id'=>$forracao['produto_id'],
                'data_evento'=>$cab['data_evento'],'status'=>'PENDENTE'
            ]),$usuarioId);
            $criados++;
        }

        // Capas físicas existentes também podem faltar.  A produção deve ser
        // planejada para a diferença entre a necessidade deste pedido e o
        // estoque físico atual; não movimenta estoque e é idempotente.
        $faltas = $this->conn->prepare("SELECT ip.produto_id, ip.quantidade AS qtd_pedido,
                p.nome_produto, p.cor AS cor_produto, pc.produto_filho_id AS componente_id,
                pc.quantidade AS qtd_por_item, c.nome_produto AS componente_nome,
                c.cor AS componente_cor, c.material AS componente_material,
                c.descricao_detalhada AS componente_descricao, c.observacoes AS componente_observacoes,
                c.quantidade_total AS estoque_componente
            FROM itens_pedido ip
            JOIN produtos p ON p.id=ip.produto_id
            JOIN produto_composicao pc ON pc.produto_pai_id=p.id AND pc.quantidade>0
            JOIN produtos c ON c.id=pc.produto_filho_id
            WHERE ip.pedido_id=?
              AND c.nome_produto LIKE 'Capa %'
              AND COALESCE(c.quantidade_total,0) < (ip.quantidade * pc.quantidade)");
        $faltas->execute([$pedidoId]);
        foreach ($faltas->fetchAll(PDO::FETCH_ASSOC) as $falta) {
            $necessaria = (float)$falta['qtd_pedido'] * (float)$falta['qtd_por_item'];
            $estoque = max(0, (float)$falta['estoque_componente']);
            $faltante = max(0, $necessaria - $estoque);
            if ($faltante <= 0) continue;
            $fichaTecnica = implode("\n\n", array_unique(array_filter([
                trim((string)$falta['componente_descricao']), trim((string)$falta['componente_observacoes'])
            ])));
            if ($falta['componente_material']) $fichaTecnica .= ' Material cadastrado: '.$falta['componente_material'].'.';
            if ($falta['componente_cor']) $fichaTecnica .= ' Cor: '.$falta['componente_cor'].'.';
            $existe = $this->conn->prepare("SELECT id FROM {$this->tabela}
                WHERE pedido_id=? AND produto_id=? AND componente_id=?
                  AND status IN ('PENDENTE','PROGRAMADA') LIMIT 1");
            $existe->execute([$pedidoId, $falta['produto_id'], $falta['componente_id']]);
            $idPendente = $existe->fetchColumn();
            if ($idPendente) {
                // Recalcula a falta quando o estoque ou a quantidade do pedido
                // mudou, sem criar uma segunda ficha.
                $this->atualizarDados((int)$idPendente, [
                    'quantidade_solicitada' => $necessaria,
                    'quantidade_faltante' => $faltante,
                    'cor' => $falta['componente_cor'] ?: $falta['cor_produto'],
                    'descricao_item' => 'Reposição de capa: '.$falta['componente_nome'],
                    'observacoes' => 'Capa física existente com estoque insuficiente para este pedido. Estoque atual: '.$estoque.'. '.$fichaTecnica.' Após produzir, registre a quantidade conferida manualmente.',
                ], $usuarioId);
                continue;
            }
            $this->criar([
                'origem_tipo'=>'PEDIDO','origem_id'=>$pedidoId,'pedido_id'=>$pedidoId,
                'cliente_id'=>$cab['cliente_id'],'produto_id'=>$falta['produto_id'],
                'componente_id'=>$falta['componente_id'],
                'descricao_item'=>'Reposição de capa: '.$falta['componente_nome'],
                'cor'=>$falta['componente_cor'] ?: $falta['cor_produto'],
                'quantidade_solicitada'=>$necessaria,'quantidade_faltante'=>$faltante,
                'data_evento'=>$cab['data_evento'],'data_entrega'=>$cab['data_entrega'],
                'observacoes'=>'Capa física existente com estoque insuficiente para este pedido. Estoque atual: '.$estoque.'. '.$fichaTecnica.' Após produzir, registre a quantidade conferida manualmente.',
                'status'=>'PENDENTE'
            ], $usuarioId);
            $criados++;
        }
        return $criados;
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
