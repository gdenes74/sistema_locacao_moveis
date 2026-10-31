<?php

class MotorCores
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public static function normalizar(string $valor): string
    {
        $valor = trim($valor);
        $valor = strtr($valor, [
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n',
        ]);
        $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if ($transliterado !== false) {
            $valor = $transliterado;
        }
        $valor = strtolower($valor);
        $valor = preg_replace('/[^a-z0-9]+/', '-', $valor) ?? '';
        return trim($valor, '-');
    }

    public function resumo(): array
    {
        $tabelas = ['cores_padrao', 'cor_aliases', 'familias_variantes', 'produto_variantes', 'amostras_cor', 'modelos_imagem', 'variantes_imagem'];
        $resumo = [];
        foreach ($tabelas as $tabela) {
            $resumo[$tabela] = (int)$this->conn->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
        }
        return $resumo;
    }

    public function listarCores(): array
    {
        $sql = "SELECT c.*,
                       GROUP_CONCAT(a.alias_original ORDER BY a.prioridade, a.alias_original SEPARATOR ', ') AS aliases
                       ,(SELECT COUNT(*) FROM amostras_cor ax WHERE ax.cor_padrao_id=c.id AND ax.ativo=1) AS total_amostras
                       ,(SELECT COUNT(*) FROM amostras_cor ax WHERE ax.cor_padrao_id=c.id AND ax.ativo=1 AND ax.aprovada=1) AS amostras_aprovadas
                FROM cores_padrao c
                LEFT JOIN cor_aliases a ON a.cor_padrao_id = c.id AND a.ativo = 1
                GROUP BY c.id
                ORDER BY c.nome";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarFamilias(): array
    {
        $sql = "SELECT f.*, p.nome_produto AS produto_generico,
                       COUNT(DISTINCT pv.id) AS total_variantes,
                       COUNT(DISTINCT mi.id) AS total_modelos
                FROM familias_variantes f
                LEFT JOIN produtos p ON p.id = f.produto_generico_id
                LEFT JOIN produto_variantes pv ON pv.familia_variante_id = f.id
                LEFT JOIN modelos_imagem mi ON mi.familia_variante_id = f.id
                GROUP BY f.id
                ORDER BY f.nome";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vínculos administrativos; inativos ficam visíveis para auditoria, sem voltar ao motor. */
    public function listarVinculosProdutos(): array
    {
        $sql = "SELECT pv.*, f.nome AS familia_nome, f.produto_generico_id,
                       p.nome_produto, c.nome AS cor_nome,
                       pg.nome_produto AS produto_generico_nome
                FROM produto_variantes pv
                JOIN familias_variantes f ON f.id=pv.familia_variante_id
                JOIN produtos p ON p.id=pv.produto_id
                LEFT JOIN cores_padrao c ON c.id=pv.cor_padrao_id
                LEFT JOIN produtos pg ON pg.id=f.produto_generico_id
                ORDER BY f.nome, pv.ativo DESC, p.nome_produto";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarFamilia(array $dados, ?int $usuarioId): int
    {
        $id = (int)($dados['familia_id'] ?? 0);
        $nome = trim((string)($dados['nome'] ?? ''));
        $slug = self::normalizar(trim((string)($dados['slug'] ?? '')) ?: $nome);
        $modos = ['COMPONENTE_COR','ESTRUTURA_COMPARTILHADA','FORRACAO_PRODUCAO','SEM_IMPACTO_FISICO'];
        $politicas = ['MESMO_PRECO','PRECO_DA_VARIANTE'];
        $modo = (string)($dados['modo_impacto_estoque'] ?? 'SEM_IMPACTO_FISICO');
        $politica = (string)($dados['politica_preco'] ?? 'MESMO_PRECO');
        $generico = !empty($dados['produto_generico_id']) ? (int)$dados['produto_generico_id'] : null;
        if ($nome === '' || $slug === '' || !in_array($modo, $modos, true) || !in_array($politica, $politicas, true)) throw new InvalidArgumentException('Dados da família inválidos.');
        if ($generico) {
            $q=$this->conn->prepare('SELECT id FROM produtos WHERE id=?'); $q->execute([$generico]);
            if (!$q->fetchColumn()) throw new DomainException('Produto genérico inválido.');
        }
        $novo=['nome'=>$nome,'slug'=>$slug,'produto_generico_id'=>$generico,'modo_impacto_estoque'=>$modo,'politica_preco'=>$politica,'permite_cor_livre'=>!empty($dados['permite_cor_livre'])?1:0,'exige_revisao'=>!empty($dados['exige_revisao'])?1:0,'observacoes'=>trim((string)($dados['observacoes'] ?? '')) ?: null];
        $propriaTransacao=!$this->conn->inTransaction();
        if($propriaTransacao)$this->conn->beginTransaction();
        try {
            if ($id) {
                $q=$this->conn->prepare('SELECT * FROM familias_variantes WHERE id=? FOR UPDATE'); $q->execute([$id]); $anterior=$q->fetch(PDO::FETCH_ASSOC);
                if (!$anterior) throw new DomainException('Família não encontrada.');
                $sql='UPDATE familias_variantes SET nome=:nome,slug=:slug,produto_generico_id=:produto_generico_id,modo_impacto_estoque=:modo_impacto_estoque,politica_preco=:politica_preco,permite_cor_livre=:permite_cor_livre,exige_revisao=:exige_revisao,observacoes=:observacoes WHERE id=:id';
                $novo['id']=$id; $this->conn->prepare($sql)->execute($novo);
                $this->historico('familia',$id,'ATUALIZADA',$anterior,$novo,$usuarioId);
            } else {
                $sql='INSERT INTO familias_variantes (nome,slug,produto_generico_id,modo_impacto_estoque,politica_preco,permite_cor_livre,exige_revisao,observacoes,ativo) VALUES (:nome,:slug,:produto_generico_id,:modo_impacto_estoque,:politica_preco,:permite_cor_livre,:exige_revisao,:observacoes,1)';
                $this->conn->prepare($sql)->execute($novo); $id=(int)$this->conn->lastInsertId();
                $this->historico('familia',$id,'CRIADA',null,$novo,$usuarioId);
            }
            if($propriaTransacao)$this->conn->commit(); return $id;
        } catch (Throwable $e) { if ($propriaTransacao && $this->conn->inTransaction()) $this->conn->rollBack(); throw $e; }
    }

    public function desativarFamilia(int $id, ?int $usuarioId): void
    {
        $propriaTransacao=!$this->conn->inTransaction();if($propriaTransacao)$this->conn->beginTransaction();
        try { $q=$this->conn->prepare('SELECT * FROM familias_variantes WHERE id=? FOR UPDATE'); $q->execute([$id]); $antes=$q->fetch(PDO::FETCH_ASSOC); if(!$antes) throw new DomainException('Família não encontrada.');
            $this->conn->prepare('UPDATE familias_variantes SET ativo=0 WHERE id=?')->execute([$id]);
            $this->historico('familia',$id,'DESATIVADA',$antes,['ativo'=>0],$usuarioId); if($propriaTransacao)$this->conn->commit();
        } catch(Throwable $e){if($propriaTransacao && $this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }

    public function salvarVinculoProduto(array $dados, ?int $usuarioId): int
    {
        $id=(int)($dados['vinculo_id']??0); $familia=(int)($dados['familia_variante_id']??0); $produto=(int)($dados['produto_id']??0);
        $cor=!empty($dados['cor_padrao_id'])?(int)$dados['cor_padrao_id']:null; $acabamento=trim((string)($dados['tipo_acabamento']??''));
        $estrutura=trim((string)($dados['estrutura_chave']??'')); $generica=!empty($dados['eh_generica'])?1:0;
        if(!$familia||!$produto||$acabamento==='') throw new InvalidArgumentException('Família, produto e acabamento são obrigatórios.');
        $q=$this->conn->prepare('SELECT id FROM familias_variantes WHERE id=? AND ativo=1');$q->execute([$familia]);if(!$q->fetchColumn())throw new DomainException('Escolha uma família ativa.');
        $q=$this->conn->prepare('SELECT id FROM produtos WHERE id=?');$q->execute([$produto]);if(!$q->fetchColumn())throw new DomainException('Produto inválido.');
        if($cor){$q=$this->conn->prepare('SELECT id FROM cores_padrao WHERE id=? AND ativo=1');$q->execute([$cor]);if(!$q->fetchColumn())throw new DomainException('Cor inválida.');}
        $novo=['familia_variante_id'=>$familia,'produto_id'=>$produto,'cor_padrao_id'=>$cor,'estrutura_chave'=>$estrutura?:null,'tipo_acabamento'=>$acabamento,'eh_generica'=>$generica];
        $propriaTransacao=!$this->conn->inTransaction();
        if($propriaTransacao)$this->conn->beginTransaction(); try {
            if($id){$q=$this->conn->prepare('SELECT * FROM produto_variantes WHERE id=? FOR UPDATE');$q->execute([$id]);$antes=$q->fetch(PDO::FETCH_ASSOC);if(!$antes)throw new DomainException('Vínculo não encontrado.');$novo['id']=$id;
                $this->conn->prepare('UPDATE produto_variantes SET familia_variante_id=:familia_variante_id,produto_id=:produto_id,cor_padrao_id=:cor_padrao_id,estrutura_chave=:estrutura_chave,tipo_acabamento=:tipo_acabamento,eh_generica=:eh_generica WHERE id=:id')->execute($novo);
                $this->historico('produto_variante',$id,'ATUALIZADO',$antes,$novo,$usuarioId);
            }else{$this->conn->prepare('INSERT INTO produto_variantes (familia_variante_id,produto_id,cor_padrao_id,estrutura_chave,tipo_acabamento,eh_generica,ativo) VALUES (:familia_variante_id,:produto_id,:cor_padrao_id,:estrutura_chave,:tipo_acabamento,:eh_generica,1)')->execute($novo);$id=(int)$this->conn->lastInsertId();$this->historico('produto_variante',$id,'CRIADO',null,$novo,$usuarioId);}
            if($propriaTransacao)$this->conn->commit();return $id;
        }catch(Throwable $e){if($propriaTransacao && $this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }

    /** Configura uma família e o produto-base numa operação, sem mexer no estoque. */
    public function configurarProdutoNoMotor(array $dados, ?int $usuarioId): int
    {
        $produto=(int)($dados['produto_generico_id']??0);$id=(int)($dados['familia_id']??0);
        if($id){$s=$this->conn->prepare('SELECT ativo FROM familias_variantes WHERE id=?');$s->execute([$id]);if(!$s->fetchColumn())throw new DomainException('Reative a família antes de editar sua configuração.');}
        $s=$this->conn->prepare("SELECT tipo_produto FROM produtos WHERE id=?");$s->execute([$produto]);
        if(strtoupper((string)$s->fetchColumn())!=='COMPOSTO')throw new DomainException('Selecione um produto montado (composto). Cadastre a composição na edição do produto.');
        $s=$this->conn->prepare('SELECT COUNT(*) FROM produto_composicao WHERE produto_pai_id=? AND quantidade>0');$s->execute([$produto]);
        if(!(int)$s->fetchColumn())throw new DomainException('Este produto precisa de uma composição antes de entrar no motor.');
        $s=$this->conn->prepare('SELECT nome FROM familias_variantes WHERE produto_generico_id=? AND ativo=1 AND id<>? LIMIT 1');$s->execute([$produto,$id]);
        if($nome=$s->fetchColumn())throw new DomainException('O produto já está configurado em “'.$nome.'”. Edite essa família.');
        if(($dados['modo_impacto_estoque']??'')==='FORRACAO_PRODUCAO') {
            $s=$this->conn->prepare("SELECT COUNT(*) FROM produto_composicao pc JOIN produtos p ON p.id=pc.produto_filho_id WHERE pc.produto_pai_id=? AND p.tipo_produto='COMPONENTE' AND p.nome_produto LIKE 'Capa %'");$s->execute([$produto]);
            if((int)$s->fetchColumn())throw new DomainException('Este composto possui capa física. Use o modo de capas ou confira a composição.');
        }
        $propria=!$this->conn->inTransaction();if($propria)$this->conn->beginTransaction();
        try {
            $id=$this->salvarFamilia($dados,$usuarioId);
            $s=$this->conn->prepare('SELECT id FROM produto_variantes WHERE familia_variante_id=? AND produto_id=? ORDER BY ativo DESC,id LIMIT 1');$s->execute([$id,$produto]);$vinculo=$s->fetchColumn();
            $this->salvarVinculoProduto(['vinculo_id'=>$vinculo?:0,'familia_variante_id'=>$id,'produto_id'=>$produto,'eh_generica'=>1,'tipo_acabamento'=>($dados['modo_impacto_estoque']==='FORRACAO_PRODUCAO'?'FORRACAO':'CAPA')],$usuarioId);
            if($vinculo)$this->conn->prepare('UPDATE produto_variantes SET ativo=1 WHERE id=?')->execute([$vinculo]);
            $s=$this->conn->prepare('SELECT * FROM produto_variantes WHERE familia_variante_id=? AND eh_generica=1 AND produto_id<>? AND ativo=1');$s->execute([$id,$produto]);
            foreach($s->fetchAll(PDO::FETCH_ASSOC) as $antes){$this->conn->prepare('UPDATE produto_variantes SET ativo=0 WHERE id=?')->execute([$antes['id']]);$this->historico('produto_variante',(int)$antes['id'],'GENERICO_SUBSTITUIDO',$antes,['ativo'=>0],$usuarioId);}
            if($propria)$this->conn->commit();return $id;
        }catch(Throwable $e){if($propria&&$this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }

    public function reativarFamilia(int $id,?int $usuarioId): void
    {
        $s=$this->conn->prepare('SELECT * FROM familias_variantes WHERE id=?');$s->execute([$id]);$antes=$s->fetch(PDO::FETCH_ASSOC);
        if(!$antes)throw new DomainException('Família não encontrada.');
        $s=$this->conn->prepare('SELECT id FROM familias_variantes WHERE ativo=1 AND produto_generico_id=? AND id<>?');$s->execute([$antes['produto_generico_id'],$id]);
        if($s->fetchColumn())throw new DomainException('Já existe outra família ativa para este produto.');
        $this->conn->prepare('UPDATE familias_variantes SET ativo=1 WHERE id=?')->execute([$id]);
        $this->historico('familia',$id,'REATIVADA',$antes,['ativo'=>1],$usuarioId);
    }

    public function desativarVinculoProduto(int $id, ?int $usuarioId): void
    { $this->conn->beginTransaction(); try{$q=$this->conn->prepare('SELECT * FROM produto_variantes WHERE id=? FOR UPDATE');$q->execute([$id]);$antes=$q->fetch(PDO::FETCH_ASSOC);if(!$antes)throw new DomainException('Vínculo não encontrado.');$this->conn->prepare('UPDATE produto_variantes SET ativo=0 WHERE id=?')->execute([$id]);$this->historico('produto_variante',$id,'DESATIVADO',$antes,['ativo'=>0],$usuarioId);$this->conn->commit();}catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();throw $e;} }

    public function listarAmostras(): array
    {
        $sql = "SELECT a.*, c.nome AS cor_nome, p.nome_produto AS produto_origem,
                       f.nome AS familia_nome
                FROM amostras_cor a
                INNER JOIN cores_padrao c ON c.id = a.cor_padrao_id
                LEFT JOIN produtos p ON p.id = a.produto_origem_id
                LEFT JOIN familias_variantes f ON f.id = a.familia_variante_id
                WHERE a.ativo = 1
                ORDER BY a.criado_em DESC, a.id DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Confirma o tom uma vez por cor canônica e libera apenas variantes físicas compatíveis. */
    public function aprovarAmostraGlobal(int $amostraId, ?int $usuarioId): int
    {
        $this->conn->beginTransaction();
        try {
            $q = $this->conn->prepare('SELECT id, cor_padrao_id, familia_variante_id, ativo FROM amostras_cor WHERE id = ? FOR UPDATE');
            $q->execute([$amostraId]);
            $amostra = $q->fetch(PDO::FETCH_ASSOC);
            if (!$amostra || !(int)$amostra['ativo']) throw new DomainException('Amostra não encontrada ou inativa.');
            $this->conn->prepare('UPDATE amostras_cor SET aprovada=1, aprovado_por=?, aprovado_em=NOW() WHERE id=?')
                ->execute([$usuarioId, $amostraId]);
            $liberar = $this->conn->prepare("UPDATE variantes_imagem v
                SET v.amostra_cor_id=?, v.status_cor='APROVADA'
                WHERE v.cor_padrao_id=? AND v.produto_id IS NOT NULL
                  AND (? IS NULL OR EXISTS (SELECT 1 FROM modelos_imagem mm WHERE mm.id=v.modelo_imagem_id AND mm.familia_variante_id=?))
                  AND EXISTS (SELECT 1 FROM produto_composicao pc JOIN produtos filho ON filho.id=pc.produto_filho_id
                    WHERE pc.produto_pai_id=v.produto_id AND filho.tipo_produto='COMPONENTE'
                      AND (filho.nome_produto LIKE 'Capa %' OR filho.nome_produto LIKE 'Assento %' OR filho.nome_produto LIKE 'Encosto %' OR filho.nome_produto LIKE 'Colchão%')
                      AND filho.quantidade_total > 0)");
            $liberar->execute([$amostraId, (int)$amostra['cor_padrao_id'], $amostra['familia_variante_id'], $amostra['familia_variante_id']]);
            $this->conn->prepare('INSERT INTO historico_motor_cores (entidade,entidade_id,acao,estado_anterior,estado_novo,usuario_id) VALUES (?,?,?,?,?,?)')
                ->execute(['amostras_cor', $amostraId, $amostra['familia_variante_id'] === null ? 'COR_GLOBAL_APROVADA' : 'REFERENCIA_FAMILIA_APROVADA', json_encode(['aprovada'=>0]), json_encode(['aprovada'=>1,'variantes_liberadas'=>$liberar->rowCount()]), $usuarioId]);
            $this->conn->commit();
            return $liberar->rowCount();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function criarAmostra(array $dados, array $arquivo, ?int $usuarioId): int
    {
        $corId = (int)($dados['cor_padrao_id'] ?? 0);
        $q = $this->conn->prepare('SELECT id FROM cores_padrao WHERE id=? AND ativo=1');
        $q->execute([$corId]);
        if (!$q->fetchColumn()) throw new DomainException('Selecione uma cor canônica ativa.');
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new DomainException('Envie uma imagem de referência.');
        if (($arquivo['size'] ?? 0) > 8 * 1024 * 1024) throw new DomainException('A imagem deve ter no máximo 8 MB.');
        $info = @getimagesize($arquivo['tmp_name']);
        if (!$info || !in_array($info['mime'], ['image/png','image/jpeg','image/webp'], true)) throw new DomainException('Use PNG, JPG ou WebP.');
        $pasta = __DIR__ . '/../assets/uploads/motor_cores/amostras';
        if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) throw new RuntimeException('Pasta de amostras indisponível.');
        $ext = $info['mime'] === 'image/png' ? 'png' : ($info['mime'] === 'image/webp' ? 'webp' : 'jpg');
        $nome = 'amostra_' . $corId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $pasta . DIRECTORY_SEPARATOR . $nome;
        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) throw new RuntimeException('Não foi possível guardar a amostra.');
        $relativo = 'assets/uploads/motor_cores/amostras/' . $nome;
        try {
            $familiaId = !empty($dados['familia_variante_id']) ? (int)$dados['familia_variante_id'] : null;
            $ins = $this->conn->prepare("INSERT INTO amostras_cor (cor_padrao_id, familia_variante_id, familia_origem, arquivo_path, tipo_origem, material_tecido, observacoes, aprovada, ativo) VALUES (?,?,?,?,?,?,?,0,1)");
            $ins->execute([$corId, $familiaId, trim((string)($dados['familia_origem'] ?? 'Catálogo')), $relativo, strtoupper((string)($dados['tipo_origem'] ?? 'OUTRO')), trim((string)($dados['material_tecido'] ?? '')), trim((string)($dados['observacoes'] ?? ''))]);
            return (int)$this->conn->lastInsertId();
        } catch (Throwable $e) { @unlink($destino); throw $e; }
    }

    /** Produtos com foto que podem servir como referência visual, sem alterar o produto. */
    public function listarProdutosComFoto(): array
    {
        $sql = "SELECT p.id, p.nome_produto, p.cor, p.foto_path
                FROM produtos p
                WHERE p.foto_path IS NOT NULL AND TRIM(p.foto_path) <> ''
                ORDER BY p.nome_produto, p.id";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Edita os dados administrativos da referência e registra a mudança; não publica nem mexe em estoque. */
    public function editarAmostra(array $dados, array $arquivo, ?int $usuarioId): int
    {
        $id = (int)($dados['amostra_id'] ?? 0);
        $q = $this->conn->prepare('SELECT * FROM amostras_cor WHERE id=? AND ativo=1');
        $q->execute([$id]);
        $antes = $q->fetch(PDO::FETCH_ASSOC);
        if (!$antes) throw new DomainException('Referência de cor não encontrada.');
        $corId = (int)($dados['cor_padrao_id'] ?? $antes['cor_padrao_id']);
        $q = $this->conn->prepare('SELECT id FROM cores_padrao WHERE id=? AND ativo=1');
        $q->execute([$corId]);
        if (!$q->fetchColumn()) throw new DomainException('Selecione uma cor canônica ativa.');

        $arquivoNovo = null;
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if (($arquivo['size'] ?? 0) > 8 * 1024 * 1024) throw new DomainException('A imagem deve ter no máximo 8 MB.');
            $info = @getimagesize($arquivo['tmp_name']);
            if (!$info || !in_array($info['mime'], ['image/png','image/jpeg','image/webp'], true)) throw new DomainException('Use PNG, JPG ou WebP.');
            $pasta = __DIR__ . '/../assets/uploads/motor_cores/amostras';
            if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) throw new RuntimeException('Pasta de amostras indisponível.');
            $ext = $info['mime'] === 'image/png' ? 'png' : ($info['mime'] === 'image/webp' ? 'webp' : 'jpg');
            $nome = 'amostra_' . $corId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destino = $pasta . DIRECTORY_SEPARATOR . $nome;
            if (!move_uploaded_file($arquivo['tmp_name'], $destino)) throw new RuntimeException('Não foi possível guardar a nova referência.');
            $arquivoNovo = 'assets/uploads/motor_cores/amostras/' . $nome;
        }
        $novo = ['cor_padrao_id'=>$corId, 'familia_variante_id'=>!empty($dados['familia_variante_id']) ? (int)$dados['familia_variante_id'] : null, 'familia_origem'=>trim((string)($dados['familia_origem'] ?? '')), 'tipo_origem'=>strtoupper(trim((string)($dados['tipo_origem'] ?? 'OUTRO'))), 'material_tecido'=>trim((string)($dados['material_tecido'] ?? '')), 'observacoes'=>trim((string)($dados['observacoes'] ?? '')), 'arquivo_path'=>$arquivoNovo ?: $antes['arquivo_path']];
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare('UPDATE amostras_cor SET cor_padrao_id=?, familia_variante_id=?, familia_origem=?, tipo_origem=?, material_tecido=?, observacoes=?, arquivo_path=? WHERE id=?')
                ->execute([$novo['cor_padrao_id'],$novo['familia_variante_id'],$novo['familia_origem'] ?: null,$novo['tipo_origem'],$novo['material_tecido'] ?: null,$novo['observacoes'] ?: null,$novo['arquivo_path'],$id]);
            $this->conn->prepare('INSERT INTO historico_motor_cores (entidade,entidade_id,acao,estado_anterior,estado_novo,usuario_id) VALUES (?,?,?,?,?,?)')
                ->execute(['amostras_cor',$id,'REFERENCIA_EDITADA',json_encode($antes,JSON_UNESCAPED_UNICODE),json_encode($novo,JSON_UNESCAPED_UNICODE),$usuarioId]);
            $this->conn->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($arquivoNovo) @unlink(__DIR__ . '/../' . $arquivoNovo);
            throw $e;
        }
    }

    /** Cancela somente um rascunho de referência, mantendo o histórico para auditoria. */
    public function descartarAmostraPendente(int $id, ?int $usuarioId): void
    {
        $this->conn->beginTransaction();
        try {
            $q = $this->conn->prepare('SELECT * FROM amostras_cor WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $amostra = $q->fetch(PDO::FETCH_ASSOC);
            if (!$amostra || !(int)$amostra['ativo']) throw new DomainException('Referência pendente não encontrada.');
            if ((int)$amostra['aprovada']) throw new DomainException('Uma referência aprovada não pode ser cancelada aqui; edite-a ou cadastre uma nova referência.');
            $this->conn->prepare('UPDATE amostras_cor SET ativo=0, atualizado_em=NOW() WHERE id=?')->execute([$id]);
            $this->conn->prepare('INSERT INTO historico_motor_cores (entidade,entidade_id,acao,estado_anterior,estado_novo,usuario_id) VALUES (?,?,?,?,?,?)')
                ->execute(['amostras_cor', $id, 'REFERENCIA_PENDENTE_CANCELADA', json_encode(['ativo'=>1,'aprovada'=>0]), json_encode(['ativo'=>0,'aprovada'=>0]), $usuarioId]);
            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    /** Copia uma foto existente do catálogo para uma referência pendente. */
    public function criarAmostraDeProduto(array $dados, ?int $usuarioId): int
    {
        $produtoId = (int)($dados['produto_origem_id'] ?? 0);
        $q = $this->conn->prepare('SELECT id,nome_produto,foto_path FROM produtos WHERE id=? AND foto_path IS NOT NULL AND TRIM(foto_path)<>""');
        $q->execute([$produtoId]);
        $produto = $q->fetch(PDO::FETCH_ASSOC);
        if (!$produto) throw new DomainException('Escolha um produto com foto cadastrada.');
        $corId = (int)($dados['cor_padrao_id'] ?? 0);
        $q = $this->conn->prepare('SELECT id FROM cores_padrao WHERE id=? AND ativo=1'); $q->execute([$corId]);
        if (!$q->fetchColumn()) throw new DomainException('Selecione uma cor canônica ativa.');
        $origem = realpath(__DIR__ . '/../' . ltrim((string)$produto['foto_path'], '/\\'));
        $raiz = realpath(__DIR__ . '/../assets/uploads');
        if (!$origem || !$raiz || strncmp($origem, $raiz . DIRECTORY_SEPARATOR, strlen($raiz) + 1) !== 0 || !is_file($origem)) throw new DomainException('A foto do produto não está disponível para referência.');
        $pasta = __DIR__ . '/../assets/uploads/motor_cores/amostras'; if (!is_dir($pasta) && !mkdir($pasta,0775,true)) throw new RuntimeException('Pasta de amostras indisponível.');
        $ext = strtolower(pathinfo($origem, PATHINFO_EXTENSION)); if (!in_array($ext,['jpg','jpeg','png','webp'],true)) $ext='jpg';
        $nome = 'amostra_' . $corId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext; $destino=$pasta . DIRECTORY_SEPARATOR . $nome;
        if (!copy($origem,$destino)) throw new RuntimeException('Não foi possível copiar a foto para referência.');
        try {
            $familiaId = !empty($dados['familia_variante_id']) ? (int)$dados['familia_variante_id'] : null;
            $ins=$this->conn->prepare('INSERT INTO amostras_cor (cor_padrao_id,produto_origem_id,familia_variante_id,familia_origem,arquivo_path,tipo_origem,observacoes,aprovada,ativo) VALUES (?,?,?,?,?,?,?,0,1)');
            $ins->execute([$corId,$produtoId,$familiaId,'Produto do catálogo','assets/uploads/motor_cores/amostras/' . $nome,'FOTO_PRODUTO','Foto copiada de #' . $produtoId . ' ' . $produto['nome_produto'] . '. ' . trim((string)($dados['observacoes'] ?? ''))]);
            return (int)$this->conn->lastInsertId();
        } catch (Throwable $e) { @unlink($destino); throw $e; }
    }

    public function listarModelos(): array
    {
        $sql = "SELECT m.*, f.nome AS familia_nome, p.nome_produto AS produto_origem_nome,
                       COUNT(v.id) AS total_variantes,
                       SUM(CASE WHEN v.amostra_cor_id IS NOT NULL AND v.status_cor='APROVADA' THEN 1 ELSE 0 END) AS variantes_com_amostra
                FROM modelos_imagem m
                LEFT JOIN familias_variantes f ON f.id = m.familia_variante_id
                LEFT JOIN produtos p ON p.id = m.produto_origem_id
                LEFT JOIN variantes_imagem v ON v.modelo_imagem_id = m.id
                GROUP BY m.id
                ORDER BY m.atualizado_em DESC, m.id DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarVariantesImagem(): array
    {
        $sql = "SELECT v.*, m.nome AS modelo_nome, c.nome AS cor_nome, p.nome_produto, p.foto_path
                FROM variantes_imagem v
                INNER JOIN modelos_imagem m ON m.id=v.modelo_imagem_id
                INNER JOIN cores_padrao c ON c.id=v.cor_padrao_id
                LEFT JOIN produtos p ON p.id=v.produto_id
                ORDER BY m.nome, c.nome";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Estado de cada combinação já vinculada, inclusive as que ainda não podem gerar foto. */
    public function listarFilaMontagem(): array
    {
        $sql = "SELECT v.id AS variante_imagem_id, v.modelo_imagem_id AS modelo_id,
                       c.id AS cor_id, c.nome AS cor_nome, p.id AS produto_id,
                       p.nome_produto, p.foto_path, m.nome AS modelo_nome,
                       m.status_geometria, m.status_recorte, m.ativo AS modelo_ativo,
                       v.status_cor, v.status_publicacao, v.amostra_cor_id, v.arquivo_final,
                       a.aprovada AS amostra_aprovada, a.ativo AS amostra_ativa,
                       CASE WHEN p.id IS NULL THEN 0 ELSE 1 END AS vinculo_produto
                FROM variantes_imagem v
                JOIN modelos_imagem m ON m.id = v.modelo_imagem_id
                JOIN cores_padrao c ON c.id = v.cor_padrao_id AND c.ativo = 1
                LEFT JOIN produtos p ON p.id = v.produto_id
                LEFT JOIN amostras_cor a ON a.id = v.amostra_cor_id AND a.cor_padrao_id = c.id
                ORDER BY m.nome, c.nome";
        $linhas = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($linhas as &$linha) {
            $faltas = [];
            if (!$linha['produto_id'] || !$linha['vinculo_produto']) $faltas[] = 'ligar produto';
            if (!$linha['modelo_ativo'] || $linha['status_geometria'] !== 'APROVADA' || $linha['status_recorte'] !== 'APROVADO') $faltas[] = 'preparar mestre';
            if (!$linha['amostra_cor_id'] || !$linha['amostra_aprovada'] || !$linha['amostra_ativa']) $faltas[] = 'adicionar referência de cor';
            if ($linha['status_cor'] !== 'APROVADA') $faltas[] = 'liberar combinação';
            $linha['pendencias'] = $faltas;
            $linha['publicada'] = $linha['status_publicacao'] === 'PUBLICADA';
            $linha['foto_atual_publicada'] = !empty($linha['arquivo_final'])
                && $linha['arquivo_final'] === $linha['foto_path'];
        }
        unset($linha);
        return $linhas;
    }

    /** Apresentações comerciais ligadas a mestre e amostra aprovados. */
    public function listarOpcoesPrevia(): array
    {
        /*
         * A disponibilidade da montagem não pode vir de uma variante já criada:
         * ela vem das CAPAS físicas que formam cada produto composto.  Isso evita
         * oferecer, por exemplo, Verde Bandeira para um sofá que não possui as
         * duas capas dessa cor.  A variante/imagem só nasce depois da prévia ser
         * conferida e aprovada pelo administrador.
         */
        $cores = $this->conn->query("SELECT id,nome,slug_normalizado FROM cores_padrao WHERE ativo=1")
            ->fetchAll(PDO::FETCH_ASSOC);
        $porNome = [];
        foreach ($cores as $cor) $porNome[self::normalizar((string)$cor['nome'])] = (int)$cor['id'];
        foreach ($this->conn->query("SELECT cor_padrao_id,alias_normalizado FROM cor_aliases WHERE ativo=1") as $alias) {
            $porNome[(string)$alias['alias_normalizado']] = (int)$alias['cor_padrao_id'];
        }

        $amostras = [];
        $stmtAmostras = $this->conn->query("SELECT a.id,a.cor_padrao_id,a.familia_variante_id,a.produto_origem_id,a.area_referencia_json
            FROM amostras_cor a WHERE a.ativo=1 AND a.aprovada=1
            ORDER BY (a.familia_variante_id IS NULL) ASC, a.aprovado_em DESC, a.id DESC");
        foreach ($stmtAmostras as $amostra) {
            $corId = (int)$amostra['cor_padrao_id'];
            $escopo = $amostra['familia_variante_id'] === null ? 'global' : 'familia_' . (int)$amostra['familia_variante_id'];
            if (!isset($amostras[$corId][$escopo])) {
                $area = json_decode((string)($amostra['area_referencia_json'] ?? ''), true);
                $amostras[$corId][$escopo] = [
                    'id'=>(int)$amostra['id'],
                    'produto_origem_id'=>$amostra['produto_origem_id'] === null ? null : (int)$amostra['produto_origem_id'],
                    'area_referencia'=>is_array($area) && count($area) === 4 ? array_map('floatval', $area) : null,
                ];
            }
        }

        $produtos = $this->conn->query("SELECT p.id,p.nome_produto,p.cor,p.foto_path,p.subcategoria_id,
                    se.id AS secao_id,se.nome AS secao_nome,cat.id AS categoria_id,cat.nome AS categoria_nome,
                    sub.id AS subcategoria_id,sub.nome AS subcategoria_nome
                FROM produtos p
                LEFT JOIN subcategorias sub ON sub.id=p.subcategoria_id
                LEFT JOIN categorias cat ON cat.id=sub.categoria_id
                LEFT JOIN secoes se ON se.id=cat.secao_id
                WHERE p.tipo_produto='COMPOSTO'")->fetchAll(PDO::FETCH_ASSOC);
        $componentes = $this->conn->query("SELECT pc.produto_pai_id,pc.quantidade,
                    f.id,f.nome_produto,f.cor,f.quantidade_total
                FROM produto_composicao pc JOIN produtos f ON f.id=pc.produto_filho_id
                WHERE f.nome_produto LIKE 'Capa %'")->fetchAll(PDO::FETCH_ASSOC);
        $capasPorProduto = [];
        foreach ($componentes as $capa) {
            $pai = (int)$capa['produto_pai_id'];
            $nomeNormalizado = self::normalizar((string)$capa['nome_produto']);
            $corNormalizada = self::normalizar((string)$capa['cor']);
            $corId = $porNome[$corNormalizada] ?? null;
            if (!$corId) continue; // cor ainda não canônica: não pode ser montada
            $grupo = preg_replace('/-' . preg_quote($corNormalizada, '/') . '$/', '', $nomeNormalizado) ?: $nomeNormalizado;
            $disponivel = (int)floor(((int)$capa['quantidade_total']) / max(1, (float)$capa['quantidade']));
            $capasPorProduto[$pai][$grupo][$corId] = max(0, $disponivel);
        }

        $porId = [];
        foreach ($produtos as $produto) $porId[(int)$produto['id']] = $produto;
        $modelos = $this->conn->query("SELECT * FROM modelos_imagem
            WHERE ativo=1 AND status_geometria='APROVADA' AND status_recorte='APROVADO'
              AND (NULLIF(arquivo_mascara, '') IS NOT NULL OR slug='pufe-azul-celeste-limpo')")->fetchAll(PDO::FETCH_ASSOC);
        $variantesExistentes = [];
        foreach ($this->conn->query("SELECT id,modelo_imagem_id,produto_id,cor_padrao_id,status_publicacao,arquivo_final
                                     FROM variantes_imagem") as $variante) {
            $chave = (int)$variante['modelo_imagem_id'] . ':' . (int)$variante['produto_id'] . ':' . (int)$variante['cor_padrao_id'];
            $variantesExistentes[$chave] = $variante;
        }
        $resultado = [];
        foreach ($modelos as $modelo) {
            $origemId = (int)$modelo['produto_origem_id'];
            $gruposMestre = array_keys($capasPorProduto[$origemId] ?? []);
            sort($gruposMestre);
            if (!$gruposMestre) continue;
            foreach ($produtos as $produto) {
                $produtoId = (int)$produto['id'];
                $gruposProduto = array_keys($capasPorProduto[$produtoId] ?? []);
                sort($gruposProduto);
                if ($gruposProduto !== $gruposMestre) continue;
                /*
                 * A fotografia e a disponibilidade são decisões distintas.
                 * Uma cor pode ter referência aprovada e precisar de uma foto
                 * comercial mesmo quando alguma capa está zerada ou ainda não
                 * entrou fisicamente no acervo. Por isso a união abaixo oferece
                 * todas as cores conhecidas nas capas; a completude física é
                 * calculada separadamente e enviada para a tela apenas como
                 * aviso de estoque zero.
                 */
                $coresCompativeis = [];
                foreach ($gruposMestre as $grupo) {
                    foreach ($capasPorProduto[$produtoId][$grupo] as $corId => $quantidade) {
                        $coresCompativeis[$corId] = true;
                    }
                }
                foreach ($coresCompativeis as $corId => $_) {
                    $amostra = $amostras[$corId]['familia_' . (int)$modelo['familia_variante_id']]
                        ?? $amostras[$corId]['global'] ?? null;
                    if (!$amostra) continue;
                    $cor = null; foreach ($cores as $c) if ((int)$c['id'] === (int)$corId) { $cor = $c; break; }
                    if (!$cor) continue;
                    $resumo = [];
                    $quantidadesCapas = [];
                    foreach ($gruposMestre as $grupo) {
                        $quantidade = (int)($capasPorProduto[$produtoId][$grupo][$corId] ?? 0);
                        $quantidadesCapas[] = $quantidade;
                        $resumo[] = $grupo . ': ' . $quantidade . ' capa(s)';
                    }
                    $capasCompletas = !in_array(0, $quantidadesCapas, true);
                    $chaveVariante = (int)$modelo['id'] . ':' . $produtoId . ':' . (int)$corId;
                    $variante = $variantesExistentes[$chaveVariante] ?? null;
                    $resultado[] = [
                        'produto_id'=>$produtoId,'nome_produto'=>$produto['nome_produto'],'cor'=>$produto['cor'],'foto_path'=>$produto['foto_path'],
                        'secao_id'=>$produto['secao_id'],'secao_nome'=>$produto['secao_nome'],'categoria_id'=>$produto['categoria_id'],'categoria_nome'=>$produto['categoria_nome'],'subcategoria_id'=>$produto['subcategoria_id'],'subcategoria_nome'=>$produto['subcategoria_nome'],
                        'cor_id'=>(int)$corId,'cor_nome'=>$cor['nome'],'amostra_id'=>$amostra['id'],
                        'area_referencia'=>$amostra['area_referencia'],
                        'amostra_mesmo_mestre'=>$amostra['produto_origem_id'] !== null && $amostra['produto_origem_id'] === $origemId,
                        'modelo_id'=>(int)$modelo['id'],'modelo_nome'=>$modelo['nome'],'modelo_slug'=>$modelo['slug'],
                        'variante_imagem_id'=>$variante ? (int)$variante['id'] : null,
                        'status_publicacao'=>$variante['status_publicacao'] ?? 'RASCUNHO','arquivo_final'=>$variante['arquivo_final'] ?? null,
                        'foto_atual_publicada'=>$variante && !empty($variante['arquivo_final'])
                            && $variante['arquivo_final'] === $produto['foto_path'],
                        'composicao_resumo'=>implode(' | ', $resumo),
                        'capas_cadastradas'=>$capasCompletas ? min($quantidadesCapas) : 0,
                        'somente_visual'=>!$capasCompletas,
                        // O mestre legado do pufe pequeno já teve seu recorte
                        // visual aprovado antes de existir a máscara PNG. Ele
                        // usa a regra específica e validada de pufe pequeno;
                        // todos os mestres novos continuam exigindo máscara.
                        'modelo'=>$modelo['slug'] === 'pufe-azul-celeste-limpo' ? 'pequeno' : 'mascara'
                    ];
                }
            }
        }
        return $resultado;

        /* Consulta histórica mantida abaixo apenas como referência durante a migração. */
        $sql = "SELECT p.id AS produto_id, p.nome_produto, p.cor, p.foto_path,
                       se.id AS secao_id, se.nome AS secao_nome, cat.id AS categoria_id, cat.nome AS categoria_nome, sub.id AS subcategoria_id, sub.nome AS subcategoria_nome,
                       c.id AS cor_id, c.nome AS cor_nome, a.id AS amostra_id,
                       m.id AS modelo_id, m.nome AS modelo_nome, m.slug AS modelo_slug,
                       v.id AS variante_imagem_id, v.status_publicacao, v.arquivo_final,
                       (SELECT GROUP_CONCAT(CONCAT('#',f.id,' ',f.nome_produto,' · cor: ',COALESCE(NULLIF(f.cor,''),'—'),' · qtd: ',pc.quantidade,' · físico: ',COALESCE(f.quantidade_total,0)) ORDER BY pc.id SEPARATOR ' | ') FROM produto_composicao pc JOIN produtos f ON f.id=pc.produto_filho_id WHERE pc.produto_pai_id=p.id) AS composicao_resumo,
                       (SELECT MIN(FLOOR(capa.quantidade_total / pc.quantidade))
                          FROM produto_composicao pc
                          JOIN produtos capa ON capa.id = pc.produto_filho_id
                         WHERE pc.produto_pai_id = p.id AND pc.quantidade > 0
                           AND capa.nome_produto LIKE 'Capa %') AS capas_cadastradas,
                       CASE WHEN m.slug = 'pufe-thai-madeira-v2' THEN 'thai'
                            WHEN m.slug = 'pufe-azul-celeste-limpo' THEN 'pequeno'
                            ELSE 'mascara' END AS modelo
                FROM variantes_imagem v
                INNER JOIN modelos_imagem m ON m.id = v.modelo_imagem_id
                INNER JOIN cores_padrao c ON c.id = v.cor_padrao_id AND c.ativo = 1
                INNER JOIN amostras_cor a ON a.id = v.amostra_cor_id
                  AND a.cor_padrao_id = c.id AND a.ativo = 1 AND a.aprovada = 1
                INNER JOIN produtos p ON p.id = v.produto_id AND p.tipo_produto = 'COMPOSTO'
                LEFT JOIN subcategorias sub ON sub.id=p.subcategoria_id
                LEFT JOIN categorias cat ON cat.id=sub.categoria_id
                LEFT JOIN secoes se ON se.id=cat.secao_id
                INNER JOIN produto_variantes pv ON pv.produto_id = p.id
                  AND pv.familia_variante_id = m.familia_variante_id
                  AND pv.cor_padrao_id = c.id AND pv.ativo = 1 AND pv.eh_generica = 0
                WHERE m.ativo = 1 AND m.status_geometria = 'APROVADA'
                  AND m.status_recorte = 'APROVADO' AND v.status_cor = 'APROVADA'
                  AND EXISTS (SELECT 1 FROM produto_composicao pc
                              JOIN produtos capa ON capa.id = pc.produto_filho_id
                              WHERE pc.produto_pai_id = p.id AND pc.quantidade > 0
                                AND capa.nome_produto LIKE 'Capa %' AND capa.quantidade_total > 0)
                  AND (m.arquivo_mascara IS NOT NULL OR m.slug IN ('pufe-thai-madeira-v2','pufe-azul-celeste-limpo'))
                ORDER BY c.nome, m.nome, p.nome_produto";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCatalogoProdutos(): array
    {
        $sql = "SELECT p.id AS produto_id,p.nome_produto,p.cor,p.tipo_produto,p.quantidade_total,p.foto_path,
                       se.id secao_id,se.nome secao_nome,cat.id categoria_id,cat.nome categoria_nome,sub.id subcategoria_id,sub.nome subcategoria_nome,
                       (SELECT GROUP_CONCAT(CONCAT('#',f.id,' ',f.nome_produto,' · cor: ',COALESCE(NULLIF(f.cor,''),'—'),' · qtd: ',pc.quantidade,' · físico: ',COALESCE(f.quantidade_total,0)) ORDER BY pc.id SEPARATOR ' | ') FROM produto_composicao pc JOIN produtos f ON f.id=pc.produto_filho_id WHERE pc.produto_pai_id=p.id) composicao_resumo
                FROM produtos p LEFT JOIN subcategorias sub ON sub.id=p.subcategoria_id LEFT JOIN categorias cat ON cat.id=sub.categoria_id LEFT JOIN secoes se ON se.id=cat.secao_id
                ORDER BY se.nome,cat.nome,sub.nome,p.nome_produto";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criarCor(array $dados, ?int $usuarioId): int
    {
        $nome = trim((string)($dados['nome'] ?? ''));
        $slug = self::normalizar((string)($dados['slug'] ?? $nome));
        if ($nome === '' || $slug === '') {
            throw new InvalidArgumentException('Informe um nome de cor válido.');
        }
        $hex = strtoupper(trim((string)($dados['hex_referencia'] ?? '')));
        if ($hex !== '' && !preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            throw new InvalidArgumentException('A referência hexadecimal deve usar o formato #RRGGBB.');
        }

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("INSERT INTO cores_padrao
                (nome, slug_normalizado, familia_operacional, hex_referencia, automatico_permitido, observacoes, ativo)
                VALUES (:nome, :slug, :familia, :hex, :automatico, :observacoes, 1)");
            $stmt->execute([
                ':nome' => $nome,
                ':slug' => $slug,
                ':familia' => trim((string)($dados['familia_operacional'] ?? '')) ?: null,
                ':hex' => $hex ?: null,
                ':automatico' => !empty($dados['automatico_permitido']) ? 1 : 0,
                ':observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: null,
            ]);
            $id = (int)$this->conn->lastInsertId();
            $this->inserirAlias($id, $nome, null, 1, $usuarioId);
            $this->historico('cor', $id, 'CRIADA', null, $dados, $usuarioId);
            $this->conn->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function criarAlias(int $corId, string $alias, ?int $familiaId, ?int $usuarioId): int
    {
        $this->conn->beginTransaction();
        try {
            $id = $this->inserirAlias($corId, $alias, $familiaId, 100, $usuarioId);
            $this->historico('alias', $id, 'CRIADO', null, ['alias' => $alias, 'cor_id' => $corId], $usuarioId);
            $this->conn->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    private function inserirAlias(int $corId, string $alias, ?int $familiaId, int $prioridade, ?int $usuarioId): int
    {
        $alias = trim($alias);
        $normalizado = self::normalizar($alias);
        if ($corId <= 0 || $normalizado === '') {
            throw new InvalidArgumentException('Cor e alias são obrigatórios.');
        }
        $sqlConflito = "SELECT a.id, a.cor_padrao_id, c.nome FROM cor_aliases a INNER JOIN cores_padrao c ON c.id=a.cor_padrao_id
                        WHERE a.alias_normalizado=:alias AND a.ativo=1
                          AND a.familia_variante_id <=> :familia
                        LIMIT 1";
        $conflito = $this->conn->prepare($sqlConflito);
        $conflito->execute([':alias' => $normalizado, ':familia' => $familiaId]);
        $existente = $conflito->fetch(PDO::FETCH_ASSOC);
        if ($existente) {
            if ((int)$existente['cor_padrao_id'] === $corId) {
                throw new DomainException('Este alias já está cadastrado para essa cor neste escopo.');
            }
            throw new DomainException("O alias já aponta para a cor {$existente['nome']} neste escopo.");
        }
        $stmt = $this->conn->prepare("INSERT INTO cor_aliases
            (cor_padrao_id, alias_original, alias_normalizado, familia_variante_id, prioridade, ativo, aprovado_por, aprovado_em)
            VALUES (:cor, :original, :normalizado, :familia, :prioridade, 1, :usuario, NOW())");
        $stmt->execute([':cor'=>$corId, ':original'=>$alias, ':normalizado'=>$normalizado, ':familia'=>$familiaId, ':prioridade'=>$prioridade, ':usuario'=>$usuarioId]);
        return (int)$this->conn->lastInsertId();
    }

    public function criarFamilia(array $dados, ?int $usuarioId): int
    {
        $nome = trim((string)($dados['nome'] ?? ''));
        $slug = self::normalizar((string)($dados['slug'] ?? $nome));
        $modos = ['COMPONENTE_COR','ESTRUTURA_COMPARTILHADA','FORRACAO_PRODUCAO','SEM_IMPACTO_FISICO'];
        $politicas = ['MESMO_PRECO','PRECO_DA_VARIANTE'];
        $modo = (string)($dados['modo_impacto_estoque'] ?? 'SEM_IMPACTO_FISICO');
        $politica = (string)($dados['politica_preco'] ?? 'MESMO_PRECO');
        if ($nome === '' || $slug === '' || !in_array($modo, $modos, true) || !in_array($politica, $politicas, true)) {
            throw new InvalidArgumentException('Dados da família inválidos.');
        }
        $stmt = $this->conn->prepare("INSERT INTO familias_variantes
            (nome, slug, modo_impacto_estoque, politica_preco, permite_cor_livre, exige_revisao, observacoes)
            VALUES (:nome,:slug,:modo,:politica,:livre,:revisao,:observacoes)");
        $stmt->execute([
            ':nome'=>$nome, ':slug'=>$slug, ':modo'=>$modo, ':politica'=>$politica,
            ':livre'=>!empty($dados['permite_cor_livre']) ? 1 : 0,
            ':revisao'=>!empty($dados['exige_revisao']) ? 1 : 0,
            ':observacoes'=>trim((string)($dados['observacoes'] ?? '')) ?: null,
        ]);
        $id = (int)$this->conn->lastInsertId();
        $this->historico('familia', $id, 'CRIADA', null, $dados, $usuarioId);
        return $id;
    }

    public function aprovarModelo(int $id, ?int $usuarioId): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Modelo inválido.');
        $this->conn->beginTransaction();
        try {
            $q = $this->conn->prepare('SELECT arquivo_base, arquivo_mascara, status_geometria, status_recorte, produto_origem_id FROM modelos_imagem WHERE id = ? AND ativo = 1 FOR UPDATE');
            $q->execute([$id]);
            $modelo = $q->fetch(PDO::FETCH_ASSOC);
            if (!$modelo || $modelo['status_geometria'] !== 'PENDENTE' || $modelo['status_recorte'] !== 'PENDENTE') {
                throw new DomainException('Modelo inexistente ou já revisado.');
            }
            $raiz = realpath(__DIR__ . '/../assets/uploads/motor_cores/modelos');
            if (!$raiz) throw new RuntimeException('Pasta de mestres indisponível.');
            foreach (['arquivo_base', 'arquivo_mascara'] as $campo) {
                $relativo = (string)$modelo[$campo];
                if (!str_starts_with($relativo, 'assets/uploads/motor_cores/modelos/') || str_contains($relativo, '..')) {
                    throw new DomainException('Arquivo do mestre fora da pasta autorizada.');
                }
                $arquivo = realpath(__DIR__ . '/../' . $relativo);
                if (!$arquivo || !str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR)) throw new DomainException('Arquivo do mestre ausente.');
                $info = getimagesize($arquivo);
                if (!$info || $info['mime'] !== 'image/png' || $info[0] !== 800 || $info[1] !== 800) {
                    throw new DomainException('Mestre ou recorte inválido.');
                }
            }
            $this->conn->prepare("UPDATE modelos_imagem SET status_geometria = 'APROVADA', status_recorte = 'APROVADO' WHERE id = ?")->execute([$id]);
            $this->historico('modelos_imagem', $id, 'MESTRE_APROVADO', ['status' => 'PENDENTE'], ['status' => 'APROVADO'], $usuarioId);
            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function descartarModelo(int $id, ?int $usuarioId): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Modelo inválido.');
        $this->conn->beginTransaction();
        try {
            $q = $this->conn->prepare("SELECT id, nome, ativo, status_geometria, status_recorte FROM modelos_imagem WHERE id=? FOR UPDATE");
            $q->execute([$id]);
            $modelo = $q->fetch(PDO::FETCH_ASSOC);
            if (!$modelo || !(int)$modelo['ativo']) throw new DomainException('Modelo inexistente ou já descartado.');
            if ($modelo['status_geometria'] !== 'PENDENTE' || $modelo['status_recorte'] !== 'PENDENTE') {
                throw new DomainException('Somente um mestre ainda pendente pode ser descartado por esta tela.');
            }
            $variantes = $this->conn->prepare('SELECT COUNT(*) FROM variantes_imagem WHERE modelo_imagem_id=?');
            $variantes->execute([$id]);
            if ((int)$variantes->fetchColumn() > 0) throw new DomainException('Este mestre já possui produtos vinculados e não pode ser descartado por esta tela.');
            $this->conn->prepare('UPDATE modelos_imagem SET ativo=0 WHERE id=?')->execute([$id]);
            $this->historico('modelos_imagem', $id, 'MESTRE_DESCARTADO', ['ativo'=>1], ['ativo'=>0], $usuarioId);
            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    private function historico(string $entidade, int $id, string $acao, $anterior, $novo, ?int $usuarioId): void
    {
        $stmt = $this->conn->prepare("INSERT INTO historico_motor_cores
            (entidade, entidade_id, acao, estado_anterior, estado_novo, usuario_id)
            VALUES (:entidade,:id,:acao,:anterior,:novo,:usuario)");
        $stmt->execute([
            ':entidade'=>$entidade, ':id'=>$id, ':acao'=>$acao,
            ':anterior'=>$anterior === null ? null : json_encode($anterior, JSON_UNESCAPED_UNICODE),
            ':novo'=>$novo === null ? null : json_encode($novo, JSON_UNESCAPED_UNICODE),
            ':usuario'=>$usuarioId,
        ]);
    }
}
