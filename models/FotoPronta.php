<?php
declare(strict_types=1);

/** Aprovação de uma foto pronta, independente da aprovação do recorte para recoloração. */
final class FotoPronta
{
    public function __construct(private PDO $db) {}

    public function validar(int $produtoId, int $modeloId, string $fotoEsperada): array
    {
        $q = $this->db->prepare('SELECT id,nome_produto,cor,tipo_produto,foto_path FROM produtos WHERE id=?');
        $q->execute([$produtoId]);
        $produto = $q->fetch(PDO::FETCH_ASSOC);
        if (!$produto || $produto['tipo_produto'] !== 'COMPOSTO') throw new DomainException('Escolha um produto montado cadastrado.');
        if ((string)$produto['foto_path'] !== $fotoEsperada) throw new DomainException('A foto atual mudou; recarregue e compare novamente.');
        $q = $this->db->prepare('SELECT id,nome,familia_variante_id,produto_origem_id,arquivo_base,status_geometria,ativo FROM modelos_imagem WHERE id=?');
        $q->execute([$modeloId]);
        $modelo = $q->fetch(PDO::FETCH_ASSOC);
        if (!$modelo || !(int)$modelo['ativo'] || $modelo['status_geometria'] !== 'PENDENTE' || (int)$modelo['produto_origem_id'] !== $produtoId) {
            throw new DomainException('A foto enviada não pertence a este produto ou já foi revisada.');
        }
        $relativo = (string)$modelo['arquivo_base'];
        $raiz = realpath(__DIR__ . '/../assets/uploads/motor_cores/modelos');
        $arquivo = realpath(__DIR__ . '/../' . $relativo);
        if (!str_starts_with($relativo, 'assets/uploads/motor_cores/modelos/') || str_contains($relativo, '..') || !$raiz || !$arquivo || !str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR)) {
            throw new DomainException('Arquivo da foto enviada inválido.');
        }
        $dim = getimagesize($arquivo);
        if (!$dim || $dim['mime'] !== 'image/png' || $dim[0] !== 800 || $dim[1] !== 800) throw new DomainException('A foto pronta deve ser PNG de 800 × 800 pixels.');
        $q = $this->db->prepare("SELECT f.id,f.nome_produto,f.foto_path,COUNT(DISTINCT outros.produto_pai_id) AS usos
            FROM produto_composicao pc JOIN produtos f ON f.id=pc.produto_filho_id
            LEFT JOIN produto_composicao outros ON outros.produto_filho_id=f.id AND outros.quantidade>0
            WHERE pc.produto_pai_id=? AND pc.quantidade>0 AND f.tipo_produto='COMPONENTE'
              AND (f.nome_produto LIKE 'Capa %' OR f.nome_produto LIKE 'Assento %' OR f.nome_produto LIKE 'Encosto %' OR f.nome_produto LIKE 'Colchão%')
            GROUP BY f.id,f.nome_produto,f.foto_path");
        $q->execute([$produtoId]);
        $componentes = $q->fetchAll(PDO::FETCH_ASSOC);
        if (!$componentes) throw new DomainException('O produto não possui componentes coloridos cadastrados.');
        return compact('produto', 'modelo', 'arquivo', 'componentes');
    }

    public function aprovar(int $produtoId, int $modeloId, string $fotoEsperada, int $usuarioId): array
    {
        $destino = null;
        $backupDestino = null;
        $destinoCriado = false;
        $confirmado = false;
        try {
            $this->db->beginTransaction();
            $dados = $this->validar($produtoId, $modeloId, $fotoEsperada);
            $produto = $dados['produto'];
            $pasta = realpath(__DIR__ . '/../assets/uploads/produtos');
            $arquivoPasta = realpath(__DIR__ . '/../assets/uploads/motor_cores/arquivadas');
            if (!$pasta || !$arquivoPasta || !is_writable($pasta) || !is_writable($arquivoPasta)) throw new RuntimeException('Pasta de fotos indisponível.');
            $nome = MotorCores::normalizar((string)$produto['nome_produto']) . '_' . $produtoId . '.png';
            $novoRelativo = 'assets/uploads/produtos/' . $nome;
            $destino = $pasta . DIRECTORY_SEPARATOR . $nome;
            if (is_file($destino)) {
                if ($fotoEsperada !== $novoRelativo) throw new DomainException('O nome da foto de destino já está em uso; revisar manualmente.');
                $backupDestino = $arquivoPasta . DIRECTORY_SEPARATOR . $nome . '.' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                if (!rename($destino, $backupDestino)) throw new RuntimeException('Não foi possível preservar a foto anterior.');
            }
            $destinoCriado = true;
            if (!copy($dados['arquivo'], $destino)) throw new RuntimeException('Não foi possível gravar a foto aprovada.');
            $q = $this->db->prepare('UPDATE produtos SET foto_path=? WHERE id=? AND foto_path=?');
            $q->execute([$novoRelativo, $produtoId, $fotoEsperada]);
            if ($q->rowCount() !== 1 && $fotoEsperada !== $novoRelativo) throw new RuntimeException('Produto alterado durante a aprovação.');
            $vincular = $this->db->prepare("INSERT INTO fotos_componentes_contextuais
                (produto_montado_id,componente_id,foto_path,origem,modelo_imagem_id,aprovado_por,aprovado_em)
                VALUES (?,?,?,'FOTO_PRONTA',?,?,NOW())
                ON DUPLICATE KEY UPDATE foto_path=VALUES(foto_path),origem=VALUES(origem),modelo_imagem_id=VALUES(modelo_imagem_id),aprovado_por=VALUES(aprovado_por),aprovado_em=NOW()");
            $atualizarExclusivo = $this->db->prepare('UPDATE produtos SET foto_path=? WHERE id=?');
            $contextos = [];
            foreach ($dados['componentes'] as $c) {
                $vincular->execute([$produtoId, (int)$c['id'], $novoRelativo, $modeloId, $usuarioId]);
                if ((int)$c['usos'] === 1) $atualizarExclusivo->execute([$novoRelativo, (int)$c['id']]);
                $contextos[] = ['id'=>(int)$c['id'], 'compartilhado'=>(int)$c['usos'] > 1, 'foto_global_anterior'=>(string)$c['foto_path']];
            }
            $this->db->prepare("UPDATE modelos_imagem SET status_geometria='APROVADA', observacoes=CONCAT(COALESCE(observacoes,''), ?) WHERE id=?")
                ->execute([' | Foto do produto #' . $produtoId . ' aprovada; recorte pendente.', $modeloId]);
            $this->db->prepare('INSERT INTO historico_motor_cores (entidade,entidade_id,acao,estado_anterior,estado_novo,usuario_id) VALUES (?,?,?,?,?,?)')
                ->execute(['produtos',$produtoId,'FOTO_PRONTA_CONTEXTUAL_APROVADA',json_encode(['foto_path'=>$fotoEsperada]),json_encode(['foto_path'=>$novoRelativo,'modelo_id'=>$modeloId,'componentes'=>$contextos]),$usuarioId]);
            $this->db->commit();
            $confirmado = true;
            return ['foto_path'=>$novoRelativo,'componentes'=>$contextos];
        } catch (Throwable $e) {
            if ($confirmado) throw $e;
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($destinoCriado && $destino && is_file($destino)) @unlink($destino);
            if ($backupDestino && $destino && is_file($backupDestino)) @rename($backupDestino, $destino);
            throw $e;
        }
    }
}
