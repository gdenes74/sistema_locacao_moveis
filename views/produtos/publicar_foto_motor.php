<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/MotorCores.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $codigo, array $dados): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'erro' => 'Método inválido.']);
if (!isLoggedIn() || !hasAccess('admin')) responder(403, ['ok' => false, 'erro' => 'Acesso restrito.']);
$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_motor_cores']) || !hash_equals($_SESSION['csrf_motor_cores'], $token)) {
    responder(403, ['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.']);
}
$entrada = json_decode(file_get_contents('php://input') ?: '', true);
$varianteId = filter_var($entrada['variante_imagem_id'] ?? null, FILTER_VALIDATE_INT);
if (!$varianteId) responder(400, ['ok' => false, 'erro' => 'Prévia inválida.']);

$db = (new Database())->getConnection();
$pasta = realpath(__DIR__ . '/../../assets/uploads/produtos');
if ($pasta === false || !is_writable($pasta)) responder(500, ['ok' => false, 'erro' => 'Pasta de fotos indisponível.']);
$temporario = null;
$backupDestino = null;
$destino = null;
$destinoAnteriorMovido = false;
$destinoNovoCriado = false;
$publicacaoConfirmada = false;
$fotoAnterior = '';

try {
    $db->beginTransaction();
    $sql = "SELECT v.id, v.produto_id, v.cor_padrao_id, v.arquivo_final, v.arquivo_previa, v.status_publicacao,
                   m.arquivo_base, m.status_geometria, m.status_recorte, m.ativo AS modelo_ativo,
                   a.aprovada, a.ativo AS amostra_ativa, c.nome AS cor_nome,
                   p.nome_produto, p.cor AS cor_produto, p.foto_path, p.tipo_produto
            FROM variantes_imagem v
            INNER JOIN modelos_imagem m ON m.id = v.modelo_imagem_id
            INNER JOIN amostras_cor a ON a.id = v.amostra_cor_id AND a.cor_padrao_id = v.cor_padrao_id
            INNER JOIN cores_padrao c ON c.id = v.cor_padrao_id AND c.ativo = 1
            INNER JOIN produtos p ON p.id = v.produto_id
            WHERE v.id = ? AND v.status_cor = 'APROVADA' FOR UPDATE";
    $ler = $db->prepare($sql);
    $ler->execute([$varianteId]);
    $v = $ler->fetch(PDO::FETCH_ASSOC);
    if (!$v || $v['status_geometria'] !== 'APROVADA' || $v['status_recorte'] !== 'APROVADO'
        || !(int)$v['modelo_ativo'] || !(int)$v['aprovada'] || !(int)$v['amostra_ativa']
        || $v['tipo_produto'] !== 'COMPOSTO'
        || $v['status_publicacao'] !== 'APROVADA') {
        /* A variante já traz o cor_padrao_id e a amostra da mesma cor. Não
           comparar rótulos textuais aqui: maiúsculas, acentos e apelidos não
           podem impedir a publicação de uma prévia aprovada. */
        throw new DomainException('Produto, cor, mestre ou amostra não estão aprovados para esta montagem.');
    }
    $previaRelativa = (string)$v['arquivo_previa'];
    if (!str_starts_with($previaRelativa, 'assets/uploads/motor_cores/previas/') || str_contains($previaRelativa, '..')) {
        throw new DomainException('Prévia aprovada não encontrada.');
    }
    $raizPrevias = realpath(__DIR__ . '/../../assets/uploads/motor_cores/previas');
    $previaFisica = realpath(__DIR__ . '/../../' . $previaRelativa);
    if (!$raizPrevias || !$previaFisica || !str_starts_with($previaFisica, $raizPrevias . DIRECTORY_SEPARATOR)) {
        throw new DomainException('Arquivo da prévia aprovada ausente.');
    }
    $binario = file_get_contents($previaFisica);
    $dimensoes = $binario === false ? false : getimagesizefromstring($binario);
    if (!$dimensoes || $dimensoes['mime'] !== 'image/png' || strlen($binario) > 8 * 1024 * 1024) {
        throw new DomainException('Prévia aprovada inválida.');
    }
    $baseRelativa = (string)$v['arquivo_base'];
    if (!str_starts_with($baseRelativa, 'assets/uploads/motor_cores/modelos/') || str_contains($baseRelativa, '..')) {
        throw new DomainException('Modelo mestre fora da pasta autorizada.');
    }
    $base = realpath(__DIR__ . '/../../' . $baseRelativa);
    $raizModelos = realpath(__DIR__ . '/../../assets/uploads/motor_cores/modelos');
    if (!$base || !$raizModelos || !str_starts_with($base, $raizModelos . DIRECTORY_SEPARATOR)) {
        throw new DomainException('Modelo mestre não encontrado.');
    }
    $tamBase = getimagesize($base);
    if (!$tamBase || $tamBase[0] !== $tamBase[1] || $dimensoes[0] !== 800 || $dimensoes[1] !== 800) {
        throw new DomainException('A foto final precisa ser quadrada, com 800 × 800 pixels.');
    }

    $produtoId = (int)$v['produto_id'];
    $nomeArquivo = MotorCores::normalizar($v['nome_produto']) . '_' . $produtoId . '.png';
    $novoRelativo = 'assets/uploads/produtos/' . $nomeArquivo;
    $destino = $pasta . DIRECTORY_SEPARATOR . $nomeArquivo;
    $fotoAnterior = (string)$v['foto_path'];
    $temporario = $pasta . DIRECTORY_SEPARATOR . '.motor_' . bin2hex(random_bytes(12)) . '.tmp';
    if (file_put_contents($temporario, $binario, LOCK_EX) !== strlen($binario)) {
        throw new RuntimeException('Não foi possível gravar a prévia.');
    }
    if (is_file($destino)) {
        if ($fotoAnterior !== $novoRelativo) {
            throw new DomainException('Já existe um arquivo com o nome de destino. Revisar manualmente.');
        }
        $pastaArquivo = __DIR__ . '/../../assets/uploads/motor_cores/arquivadas';
        if (!is_dir($pastaArquivo) && !mkdir($pastaArquivo, 0775, true)) {
            throw new RuntimeException('Não foi possível abrir o histórico das fotos.');
        }
        $backupDestino = $pastaArquivo . DIRECTORY_SEPARATOR . $nomeArquivo . '.' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        if (!rename($destino, $backupDestino)) throw new RuntimeException('Não foi possível preservar a versão anterior.');
        $destinoAnteriorMovido = true;
    }
    if (!rename($temporario, $destino)) throw new RuntimeException('Não foi possível publicar o arquivo PNG.');
    $temporario = null;
    $destinoNovoCriado = true;

    $atualizarProduto = $db->prepare('UPDATE produtos SET foto_path = ? WHERE id = ? AND foto_path = ?');
    $atualizarProduto->execute([$novoRelativo, $produtoId, $fotoAnterior]);
    if ($atualizarProduto->rowCount() !== 1 && $fotoAnterior !== $novoRelativo) {
        throw new RuntimeException('O produto mudou durante a publicação.');
    }
    /*
     * Foto comercial é contextual: sofá, cama Thai, pufe etc. recebem cada qual
     * sua foto montada. Componentes e capas representam estoque e podem atender
     * mais de um composto; por isso conservam logo.jpg (ou a foto técnica já
     * existente) e nunca recebem a foto completa do produto-pai.
     */
    $atualizarVariante = $db->prepare("UPDATE variantes_imagem SET arquivo_final = ?, status_publicacao = 'PUBLICADA', meramente_ilustrativa = 1, publicado_em = NOW() WHERE id = ?");
    $atualizarVariante->execute([$novoRelativo, $varianteId]);
    $historico = $db->prepare('INSERT INTO historico_motor_cores (entidade, entidade_id, acao, estado_anterior, estado_novo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)');
    $historico->execute([
        'variantes_imagem', $varianteId, 'FOTO_PUBLICADA',
        json_encode(['foto_path' => $fotoAnterior, 'arquivo_final' => $v['arquivo_final'],
                     'versao_anterior_arquivada' => $backupDestino ? basename($backupDestino) : null], JSON_UNESCAPED_SLASHES),
        json_encode(['foto_path' => $novoRelativo, 'produto_id' => $produtoId], JSON_UNESCAPED_SLASHES),
        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
    ]);
    $db->commit();
    $publicacaoConfirmada = true;

    // A foto anterior só sai da pasta ativa se não for compartilhada por nenhum cadastro.
    $arquivada = false;
    try {
    if ($fotoAnterior !== $novoRelativo && str_starts_with($fotoAnterior, 'assets/uploads/produtos/')
        && !in_array(basename($fotoAnterior), ['logo.jpg', 'servico.jpg'], true)) {
        $referencias = 0;
        foreach ([
            ['produtos', 'foto_path'], ['amostras_cor', 'arquivo_path'],
            ['modelos_imagem', 'arquivo_base'], ['modelos_imagem', 'arquivo_mascara'],
            ['variantes_imagem', 'arquivo_previa'], ['variantes_imagem', 'arquivo_final'],
        ] as [$tabela, $campo]) {
            $q = $db->prepare("SELECT COUNT(*) FROM {$tabela} WHERE {$campo} = ?");
            $q->execute([$fotoAnterior]);
            $referencias += (int)$q->fetchColumn();
        }
        $antigoFisico = realpath(__DIR__ . '/../../' . $fotoAnterior);
        if ($referencias === 0 && $antigoFisico && dirname($antigoFisico) === $pasta) {
            $pastaArquivo = __DIR__ . '/../../assets/uploads/motor_cores/arquivadas';
            if ((is_dir($pastaArquivo) || mkdir($pastaArquivo, 0775, true))) {
                $alvoArquivo = $pastaArquivo . DIRECTORY_SEPARATOR . basename($antigoFisico) . '.' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                $arquivada = rename($antigoFisico, $alvoArquivo);
                if ($arquivada) {
                    try {
                        $db->prepare('INSERT INTO historico_motor_cores (entidade, entidade_id, acao, estado_anterior, estado_novo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)')
                           ->execute(['variantes_imagem', $varianteId, 'FOTO_ANTIGA_ARQUIVADA',
                              json_encode(['foto_path' => $fotoAnterior]),
                              json_encode(['arquivo_arquivado' => basename($alvoArquivo)]),
                              isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null]);
                    } catch (Throwable $registroErro) {
                        if (rename($alvoArquivo, $antigoFisico)) $arquivada = false;
                        error_log('[publicar_foto_motor] Arquivamento não registrado: ' . $registroErro->getMessage());
                    }
                }
            }
        }
    }
    } catch (Throwable $erroArquivo) {
        error_log('[publicar_foto_motor] Publicação salva, mas arquivamento pendente: ' . $erroArquivo->getMessage());
    }
    responder(200, ['ok' => true, 'produto_id' => $produtoId, 'foto_path' => $novoRelativo, 'anterior_arquivada' => $arquivada]);
} catch (Throwable $erro) {
    if ($publicacaoConfirmada) {
        error_log('[publicar_foto_motor] Publicação salva com aviso: ' . $erro->getMessage());
        responder(200, ['ok' => true, 'aviso' => 'Foto salva; confira o histórico de arquivos.']);
    }
    if ($db->inTransaction()) $db->rollBack();
    if ($temporario && is_file($temporario)) @unlink($temporario);
    if ($destinoNovoCriado && $destino && is_file($destino)) @unlink($destino);
    if ($destinoAnteriorMovido && $backupDestino && is_file($backupDestino)) @rename($backupDestino, $destino);
    error_log('[publicar_foto_motor] ' . $erro->getMessage());
    responder($erro instanceof DomainException ? 409 : 500, ['ok' => false, 'erro' => $erro->getMessage()]);
}
