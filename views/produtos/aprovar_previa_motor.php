<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respostaPrevia(int $codigo, array $dados): never {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respostaPrevia(405, ['ok' => false, 'erro' => 'Método inválido.']);
if (!isLoggedIn() || !hasAccess('admin')) respostaPrevia(403, ['ok' => false, 'erro' => 'Acesso restrito.']);
if (empty($_SESSION['csrf_motor_cores']) || !hash_equals($_SESSION['csrf_motor_cores'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    respostaPrevia(403, ['ok' => false, 'erro' => 'Sessão expirada.']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12 * 1024 * 1024) respostaPrevia(413, ['ok' => false, 'erro' => 'Prévia grande demais.']);
$entrada = json_decode(file_get_contents('php://input') ?: '', true);
$id = filter_var($entrada['variante_imagem_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$modeloId = filter_var($entrada['modelo_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$produtoId = filter_var($entrada['produto_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$corId = filter_var($entrada['cor_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$amostraId = filter_var($entrada['amostra_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$dadosImagem = $entrada['imagem_data'] ?? null;
$prefixo = 'data:image/png;base64,';
if ((!$id && (!$modeloId || !$produtoId || !$corId || !$amostraId)) || !is_string($dadosImagem) || !str_starts_with($dadosImagem, $prefixo)) respostaPrevia(400, ['ok' => false, 'erro' => 'Prévia inválida.']);
$binario = base64_decode(substr($dadosImagem, strlen($prefixo)), true);
$dim = $binario === false ? false : getimagesizefromstring($binario);
if (!$dim || $dim['mime'] !== 'image/png' || $dim[0] !== 800 || $dim[1] !== 800 || strlen($binario) > 8 * 1024 * 1024) {
    respostaPrevia(400, ['ok' => false, 'erro' => 'A prévia precisa ser PNG de 800 × 800 pixels.']);
}
$db = (new Database())->getConnection();
$pastaRelativa = 'assets/uploads/motor_cores/previas/';
$pasta = __DIR__ . '/../../' . $pastaRelativa;
if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) respostaPrevia(500, ['ok' => false, 'erro' => 'Pasta de prévias indisponível.']);
$arquivoNovo = $pastaRelativa . 'previa_' . ($id ?: 'novo') . '_' . bin2hex(random_bytes(8)) . '.png';
$fisicoNovo = __DIR__ . '/../../' . $arquivoNovo;
$arquivoAnterior = '';
try {
    $db->beginTransaction();
    /* A primeira aprovação de uma combinação cria seu vínculo. Antes, a tela já
       montava a prévia pelo estoque real, mas esta etapa ainda exigia um vínculo
       antigo criado manualmente — exatamente o erro que o usuário encontrou. */
    if (!$id) {
        $existente = $db->prepare('SELECT id FROM variantes_imagem WHERE modelo_imagem_id=? AND produto_id=? AND cor_padrao_id=? FOR UPDATE');
        $existente->execute([$modeloId,$produtoId,$corId]);
        $id = (int)$existente->fetchColumn();
        if (!$id) {
            $criar = $db->prepare("INSERT INTO variantes_imagem
                (modelo_imagem_id,cor_padrao_id,amostra_cor_id,produto_id,status_cor,status_publicacao,meramente_ilustrativa,observacoes)
                VALUES (?,?,?,?,'APROVADA','RASCUNHO',0,'Vínculo criado ao aprovar a prévia a partir das capas físicas.')");
            $criar->execute([$modeloId,$corId,$amostraId,$produtoId]);
            $id = (int)$db->lastInsertId();
        }
    }
    /* A combinação pode ter sido criada antes de existir a referência aprovada.
       A aprovação precisa sempre receber a amostra escolhida na tela, inclusive
       quando o navegador manda um vínculo existente. */
    $atualizar = $db->prepare("UPDATE variantes_imagem
        SET amostra_cor_id=?, status_cor='APROVADA'
        WHERE id=? AND modelo_imagem_id=? AND produto_id=? AND cor_padrao_id=?");
    $atualizar->execute([$amostraId,$id,$modeloId,$produtoId,$corId]);
    $q = $db->prepare("SELECT v.id, v.produto_id, v.cor_padrao_id, v.arquivo_previa, v.status_cor,
                             m.familia_variante_id, m.status_geometria, m.status_recorte, m.ativo AS modelo_ativo,
                             c.nome AS cor_nome, a.aprovada, a.ativo AS amostra_ativa,
                             p.tipo_produto, p.cor AS cor_produto
                      FROM variantes_imagem v
                      JOIN modelos_imagem m ON m.id = v.modelo_imagem_id
                      JOIN cores_padrao c ON c.id = v.cor_padrao_id AND c.ativo = 1
                      JOIN amostras_cor a ON a.id = v.amostra_cor_id AND a.cor_padrao_id = c.id
                      JOIN produtos p ON p.id = v.produto_id
                      WHERE v.id = ? FOR UPDATE");
    $q->execute([$id]);
    $v = $q->fetch(PDO::FETCH_ASSOC);
    if (!$v || $v['status_cor'] !== 'APROVADA' || $v['status_geometria'] !== 'APROVADA'
        || $v['status_recorte'] !== 'APROVADO' || !(int)$v['modelo_ativo']
        || !(int)$v['aprovada'] || !(int)$v['amostra_ativa'] || $v['tipo_produto'] !== 'COMPOSTO') {
        /* A identidade da cor é o cor_padrao_id, já validado no vínculo acima.
           Não comparar o rótulo de produto aqui: alterações de maiúsculas,
           acentos ou apelidos (como Fendi Escuro) não podem bloquear uma
           prévia já montada com a mesma cor cadastrada. */
        throw new DomainException('Mestre, amostra, cor ou produto não estão preparados.');
    }
    /*
     * A aprovação registra a foto comercial, não uma reserva de estoque.
     * Cores sem capa completa (por exemplo, uma capa ainda com quantidade
     * zero) continuam ilustrativas e o orçamento decide a indisponibilidade
     * pela composição física. Nunca bloquear a foto por esse motivo.
     */
    $arquivoAnterior = (string)$v['arquivo_previa'];
    if (file_put_contents($fisicoNovo, $binario, LOCK_EX) !== strlen($binario)) throw new RuntimeException('Não foi possível gravar a prévia.');
    $db->prepare("UPDATE variantes_imagem SET arquivo_previa = ?, status_publicacao = 'APROVADA', aprovado_por = ?, aprovado_em = NOW() WHERE id = ?")
       ->execute([$arquivoNovo, (int)$_SESSION['user_id'], $id]);
    $db->prepare('INSERT INTO historico_motor_cores (entidade, entidade_id, acao, estado_anterior, estado_novo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute(['variantes_imagem', $id, 'PREVIA_APROVADA', json_encode(['arquivo_previa' => $arquivoAnterior]), json_encode(['arquivo_previa' => $arquivoNovo, 'produto_id' => (int)$v['produto_id']]), (int)$_SESSION['user_id']]);
    $db->commit();
    if ($arquivoAnterior !== '' && str_starts_with($arquivoAnterior, $pastaRelativa) && !str_contains($arquivoAnterior, '..')) {
        try {
            $q = $db->prepare('SELECT COUNT(*) FROM variantes_imagem WHERE arquivo_previa = ?');
            $q->execute([$arquivoAnterior]);
            $antigo = realpath(__DIR__ . '/../../' . $arquivoAnterior);
            $raiz = realpath($pasta);
            if (!(int)$q->fetchColumn() && $antigo && $raiz && dirname($antigo) === $raiz) {
                $arquivadas = __DIR__ . '/../../assets/uploads/motor_cores/arquivadas';
                if (is_dir($arquivadas) || mkdir($arquivadas, 0775, true)) {
                    $destino = $arquivadas . DIRECTORY_SEPARATOR . basename($antigo) . '.' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                    if (rename($antigo, $destino)) {
                        $db->prepare('INSERT INTO historico_motor_cores (entidade, entidade_id, acao, estado_anterior, estado_novo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
                           ->execute(['variantes_imagem', $id, 'PREVIA_ANTIGA_ARQUIVADA', json_encode(['arquivo' => $arquivoAnterior]), json_encode(['arquivo_arquivado' => basename($destino)]), (int)$_SESSION['user_id']]);
                    }
                }
            }
        } catch (Throwable $arquivoErro) {
            error_log('[aprovar_previa_motor] Arquivamento pendente: ' . $arquivoErro->getMessage());
        }
    }
    respostaPrevia(200, ['ok' => true, 'variante_imagem_id' => $id]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if (is_file($fisicoNovo)) unlink($fisicoNovo);
    respostaPrevia($e instanceof DomainException ? 409 : 500, ['ok' => false, 'erro' => $e->getMessage()]);
}
