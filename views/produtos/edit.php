<?php

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Secao.php';
require_once __DIR__ . '/../../models/Categoria.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

$page_title = "Editar Produto";
$error = null;
$success = null;
$produto_data = null;

function normalizarMoedaProdutoEdit($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.00;
    }

    if (is_numeric($valor)) {
        return (float)$valor;
    }

    $valor = str_replace('R$', '', (string)$valor);
    $valor = trim($valor);
    $valor = preg_replace('/\s+/', '', $valor);

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } else {
        $valor = preg_replace('/[^0-9.\-]/', '', $valor);
    }

    return is_numeric($valor) ? (float)$valor : 0.00;
}

// --- Conexão e Instâncias ---
$database = new Database();
$db = $database->getConnection();
$produto = new Produto($db);
$secaoModel = new Secao($db);
$categoriaModel = new Categoria($db);
$subcategoriaModel = new Subcategoria($db);

// --- 1. Obter ID do Produto da URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID do produto inválido ou não fornecido.";
    redirect('views/produtos/index.php');
}
$produto_id = (int)$_GET['id'];

// --- 2. Buscar Dados do Produto Específico ---
$produto_data = $produto->lerPorId($produto_id);

// --- 3. Verificar se o Produto Foi Encontrado ---
if (!$produto_data) {
    $_SESSION['error'] = "Produto com ID {$produto_id} não encontrado.";
    redirect('views/produtos/index.php');
}

// --- 4. Buscar Seções, Categorias, Subcategorias, Produtos e Componentes ---
$componentesAtuais = [];
$produtosParaComposicao = [];

try {
    $secoes = $secaoModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $categorias = $categoriaModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $subcategorias = $subcategoriaModel->listar()->fetchAll(PDO::FETCH_ASSOC);

    $dataHierarchy = json_encode([
        'secoes' => $secoes,
        'categorias' => $categorias,
        'subcategorias' => $subcategorias,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Produtos disponíveis para serem usados como componentes.
    // Mantém amplo de propósito: o usuário pode usar SIMPLES, COMPONENTE ou até outro COMPOSTO como filho, se futuramente precisar.
    $stmtProdutos = $db->prepare("
        SELECT
            id,
            nome_produto,
            codigo,
            tipo_produto,
            controla_estoque,
            quantidade_total
        FROM produtos
        WHERE id <> :produto_id
        ORDER BY nome_produto ASC
    ");
    $stmtProdutos->bindValue(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmtProdutos->execute();
    $produtosParaComposicao = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

    // Componentes já vinculados ao produto atual.
    $stmtComponentes = $db->prepare("
        SELECT
            pc.id,
            pc.produto_pai_id,
            pc.produto_filho_id,
            pc.quantidade,
            pc.obrigatorio,
            pc.observacoes,
            filho.nome_produto AS nome_produto_filho,
            filho.codigo AS codigo_filho,
            filho.tipo_produto AS tipo_produto_filho,
            filho.controla_estoque AS controla_estoque_filho,
            filho.quantidade_total AS quantidade_total_filho
        FROM produto_composicao pc
        INNER JOIN produtos filho ON filho.id = pc.produto_filho_id
        WHERE pc.produto_pai_id = :produto_id
        ORDER BY filho.nome_produto ASC
    ");
    $stmtComponentes->bindValue(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmtComponentes->execute();
    $componentesAtuais = $stmtComponentes->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    error_log("Erro ao carregar dados do edit de produto: " . $e->getMessage());
}

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['produto_id']) || !filter_var($_POST['produto_id'], FILTER_VALIDATE_INT) || (int)$_POST['produto_id'] !== $produto_id) {
        $_SESSION['error'] = "Erro de submissão: ID do produto inconsistente.";
        redirect('views/produtos/index.php');
    }

    $produto->id = $produto_id;
    $produto->subcategoria_id = isset($_POST['subcategoria_id']) ? (int)$_POST['subcategoria_id'] : 0;

    $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
    $produto->codigo = !empty($codigo) ? $codigo : null;

    $produto->nome_produto = isset($_POST['nome_produto']) ? trim($_POST['nome_produto']) : '';
    $produto->descricao_detalhada = isset($_POST['descricao_detalhada']) ? trim($_POST['descricao_detalhada']) : null;
    $produto->dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $produto->cor = isset($_POST['cor']) ? trim($_POST['cor']) : null;
    $produto->material = isset($_POST['material']) ? trim($_POST['material']) : null;

    $tipoProdutoPermitidos = ['SIMPLES', 'COMPOSTO', 'COMPONENTE', 'SERVICO'];
    $tipoProdutoPost = isset($_POST['tipo_produto']) ? strtoupper(trim($_POST['tipo_produto'])) : 'SIMPLES';
    $produto->tipo_produto = in_array($tipoProdutoPost, $tipoProdutoPermitidos, true) ? $tipoProdutoPost : 'SIMPLES';

    $produto->controla_estoque = isset($_POST['controla_estoque']) ? 1 : 0;
    $produto->quantidade_total = isset($_POST['quantidade_total']) ? (int)$_POST['quantidade_total'] : 0;

    if ($produto->tipo_produto === 'SERVICO') {
        $produto->controla_estoque = 0;
        $produto->quantidade_total = 0;
    }

    $produto->preco_locacao = normalizarMoedaProdutoEdit($_POST['preco_locacao'] ?? 0);
    $produto->preco_venda = normalizarMoedaProdutoEdit($_POST['preco_venda'] ?? 0);
    $produto->preco_custo = normalizarMoedaProdutoEdit($_POST['preco_custo'] ?? 0);

    $produto->disponivel_venda = isset($_POST['disponivel_venda']) ? 1 : 0;
    $produto->disponivel_locacao = isset($_POST['disponivel_locacao']) ? 1 : 0;

    $produto->observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : null;

    // ----- TRATAMENTO DO UPLOAD DA NOVA FOTO -----
    $novaFotoPath = $_POST['foto_atual_path'] ?? '';
    $fotoAntigaPath = $_POST['foto_atual_path'] ?? '';
    $uploadOk = true;
    $uploadErrorMsg = '';

    if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['nova_foto'];
        $uploadDir = __DIR__ . '/../../assets/uploads/produtos/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024;

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!in_array($file['type'], $allowedTypes, true)) {
            $uploadErrorMsg = "Erro: Tipo de arquivo inválido ({$file['type']}). Apenas JPG, PNG, GIF, WebP são permitidos.";
            $uploadOk = false;
        } elseif ($file['size'] > $maxFileSize) {
            $uploadErrorMsg = "Erro: Arquivo muito grande ({$file['size']} bytes). Máximo de 2MB.";
            $uploadOk = false;
        }

        if ($uploadOk) {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $novoNomeArquivo = 'prod_' . $produto_id . '_' . time() . '.' . $fileExtension;
            $caminhoCompletoFisico = $uploadDir . $novoNomeArquivo;

            if (move_uploaded_file($file['tmp_name'], $caminhoCompletoFisico)) {
                $novaFotoPath = 'assets/uploads/produtos/' . $novoNomeArquivo;

                if (!empty($fotoAntigaPath) && $fotoAntigaPath !== 'assets/img/product_placeholder.png') {
                    $caminhoFisicoAntigo = __DIR__ . '/../../' . $fotoAntigaPath;
                    if (file_exists($caminhoFisicoAntigo)) {
                        @unlink($caminhoFisicoAntigo);
                    }
                }
            } else {
                $uploadErrorMsg = "Erro ao mover o arquivo enviado.";
                $uploadOk = false;
            }
        }
    } elseif (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadErrorMsg = "Erro no upload da foto: Código " . $_FILES['nova_foto']['error'];
        $uploadOk = false;
    }

    if (!$uploadOk) {
        $error = $uploadErrorMsg;
    } else {
        $produto->foto_path = $novaFotoPath;

        try {
            $db->beginTransaction();

            if (!$produto->atualizar()) {
                throw new Exception("Erro ao atualizar o produto. Verifique os logs ou tente novamente.");
            }

            // Atualiza a composição do produto.
            // Se não for COMPOSTO, remove vínculos antigos como pai para evitar lixo lógico.
            $stmtDeleteComposicao = $db->prepare("DELETE FROM produto_composicao WHERE produto_pai_id = :produto_pai_id");
            $stmtDeleteComposicao->bindValue(':produto_pai_id', $produto_id, PDO::PARAM_INT);
            $stmtDeleteComposicao->execute();

            if ($produto->tipo_produto === 'COMPOSTO') {
                $componentesIds = $_POST['componente_produto_id'] ?? [];
                $componentesQuantidades = $_POST['componente_quantidade'] ?? [];
                $componentesObrigatorios = $_POST['componente_obrigatorio'] ?? [];
                $componentesObservacoes = $_POST['componente_observacoes'] ?? [];

                $stmtInsertComponente = $db->prepare("
                    INSERT INTO produto_composicao
                    (
                        produto_pai_id,
                        produto_filho_id,
                        quantidade,
                        obrigatorio,
                        observacoes,
                        data_cadastro
                    )
                    VALUES
                    (
                        :produto_pai_id,
                        :produto_filho_id,
                        :quantidade,
                        :obrigatorio,
                        :observacoes,
                        NOW()
                    )
                ");

                $jaInseridos = [];

                foreach ($componentesIds as $idx => $produtoFilhoId) {
                    $produtoFilhoId = (int)$produtoFilhoId;

                    if ($produtoFilhoId <= 0 || $produtoFilhoId === $produto_id) {
                        continue;
                    }

                    // Evita duplicidade do mesmo componente no mesmo produto pai.
                    if (isset($jaInseridos[$produtoFilhoId])) {
                        continue;
                    }

                    $quantidadeComponente = normalizarMoedaProdutoEdit($componentesQuantidades[$idx] ?? 1);
                    if ($quantidadeComponente <= 0) {
                        $quantidadeComponente = 1.00;
                    }

                    $obrigatorio = isset($componentesObrigatorios[$idx]) ? 1 : 0;
                    $observacaoComponente = isset($componentesObservacoes[$idx]) ? trim($componentesObservacoes[$idx]) : null;

                    $stmtInsertComponente->bindValue(':produto_pai_id', $produto_id, PDO::PARAM_INT);
                    $stmtInsertComponente->bindValue(':produto_filho_id', $produtoFilhoId, PDO::PARAM_INT);
                    $stmtInsertComponente->bindValue(':quantidade', $quantidadeComponente, PDO::PARAM_STR);
                    $stmtInsertComponente->bindValue(':obrigatorio', $obrigatorio, PDO::PARAM_INT);
                    $stmtInsertComponente->bindValue(':observacoes', $observacaoComponente ?: null, PDO::PARAM_STR);
                    $stmtInsertComponente->execute();

                    $jaInseridos[$produtoFilhoId] = true;
                }
            }

            $db->commit();

            $_SESSION['message'] = "Produto '" . htmlspecialchars($produto->nome_produto) . "' atualizado com sucesso!";
            redirect('views/produtos/index.php');

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Erro ao atualizar o produto: " . $e->getMessage();
            error_log("[views/produtos/edit.php] Erro ao atualizar produto {$produto_id}: " . $e->getMessage());
        }
    }

    // Recarrega dados se deu erro para refletir o que foi digitado.
    if ($error) {
        $produto_data = $produto->lerPorId($produto_id);

        try {
            $stmtComponentes = $db->prepare("
                SELECT
                    pc.id,
                    pc.produto_pai_id,
                    pc.produto_filho_id,
                    pc.quantidade,
                    pc.obrigatorio,
                    pc.observacoes,
                    filho.nome_produto AS nome_produto_filho,
                    filho.codigo AS codigo_filho,
                    filho.tipo_produto AS tipo_produto_filho,
                    filho.controla_estoque AS controla_estoque_filho,
                    filho.quantidade_total AS quantidade_total_filho
                FROM produto_composicao pc
                INNER JOIN produtos filho ON filho.id = pc.produto_filho_id
                WHERE pc.produto_pai_id = :produto_id
                ORDER BY filho.nome_produto ASC
            ");
            $stmtComponentes->bindValue(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmtComponentes->execute();
            $componentesAtuais = $stmtComponentes->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao recarregar componentes após falha: " . $e->getMessage());
        }
    }
}

// Incluir o cabeçalho e a barra lateral
include_once __DIR__ . '/../includes/header.php';

$placeholderImgUrl = (defined('BASE_URL') ? BASE_URL : '/') . 'assets/img/product_placeholder.png';

$tipoAtual = strtoupper($produto_data['tipo_produto'] ?? 'SIMPLES');
$controlaEstoqueAtual = isset($produto_data['controla_estoque']) ? (int)$produto_data['controla_estoque'] : 1;

?>

<!-- Content Wrapper. Contém o conteúdo da página -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <small>Editando: <?php echo htmlspecialchars($produto_data['nome_produto'] ?? 'Produto não encontrado'); ?></small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/produtos/index.php">Produtos</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Formulário de Edição de Produto</h3>
                </div>

                <form method="POST" action="edit.php?id=<?php echo $produto_id; ?>" enctype="multipart/form-data">
                    <input type="hidden" name="produto_id" value="<?php echo $produto_id; ?>">
                    <input type="hidden" name="foto_atual_path" value="<?php echo htmlspecialchars($produto_data['foto_path'] ?? ''); ?>">

                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php include_once __DIR__ . '/../includes/alert_messages.php'; ?>

                        <!-- Dropdowns dependentes -->
                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="secao_id">Seção *</label>
                                <select id="secao_id" name="secao_id" class="form-control" required>
                                    <option value="">-- Selecione a Seção --</option>
                                    <?php foreach ($secoes as $secao): ?>
                                        <option value="<?php echo htmlspecialchars($secao['id']); ?>" <?php echo (isset($produto_data['secao_id']) && $produto_data['secao_id'] == $secao['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($secao['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="categoria_id">Categoria *</label>
                                <select id="categoria_id" name="categoria_id" class="form-control" required <?php echo empty($produto_data['secao_id']) ? 'disabled' : ''; ?>>
                                    <option value="">-- Selecione Categoria --</option>
                                    <?php if (!empty($produto_data['secao_id'])): ?>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <?php if ($categoria['secao_id'] == $produto_data['secao_id']): ?>
                                                <option value="<?php echo htmlspecialchars($categoria['id']); ?>" <?php echo (isset($produto_data['categoria_id']) && $produto_data['categoria_id'] == $categoria['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="subcategoria_id">Subcategoria *</label>
                                <select id="subcategoria_id" name="subcategoria_id" class="form-control" required <?php echo empty($produto_data['categoria_id']) ? 'disabled' : ''; ?>>
                                    <option value="">-- Selecione Subcategoria --</option>
                                    <?php if (!empty($produto_data['categoria_id'])): ?>
                                        <?php foreach ($subcategorias as $subcategoria): ?>
                                            <?php if ($subcategoria['categoria_id'] == $produto_data['categoria_id']): ?>
                                                <option value="<?php echo htmlspecialchars($subcategoria['id']); ?>" <?php echo (isset($produto_data['subcategoria_id']) && $produto_data['subcategoria_id'] == $subcategoria['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subcategoria['nome']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Linha 1: Nome, Código -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="nome_produto">Nome do Produto <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome_produto" name="nome_produto" required value="<?php echo htmlspecialchars($produto_data['nome_produto'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="codigo">Código <span class="text-info">(Opcional, Único)</span></label>
                                    <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($produto_data['codigo'] ?? ''); ?>">
                                    <small class="form-text text-muted">Deixe em branco para não usar código.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Linha 2: Descrição -->
                        <div class="form-group">
                            <label for="descricao_detalhada">Descrição Detalhada</label>
                            <textarea class="form-control" id="descricao_detalhada" name="descricao_detalhada" rows="3"><?php echo htmlspecialchars($produto_data['descricao_detalhada'] ?? ''); ?></textarea>
                        </div>

                        <!-- Linha 3: Dimensões, Cor, Material -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="dimensoes">Dimensões</label>
                                    <input type="text" class="form-control" id="dimensoes" name="dimensoes" value="<?php echo htmlspecialchars($produto_data['dimensoes'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="cor">Cor</label>
                                    <input type="text" class="form-control" id="cor" name="cor" value="<?php echo htmlspecialchars($produto_data['cor'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="material">Material</label>
                                    <input type="text" class="form-control" id="material" name="material" value="<?php echo htmlspecialchars($produto_data['material'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Linha 4: Tipo do Produto, Controle, Quantidade -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tipo_produto">Tipo do Produto *</label>
                                    <select id="tipo_produto" name="tipo_produto" class="form-control" required>
                                        <option value="SIMPLES" <?php echo $tipoAtual === 'SIMPLES' ? 'selected' : ''; ?>>SIMPLES - produto normal</option>
                                        <option value="COMPONENTE" <?php echo $tipoAtual === 'COMPONENTE' ? 'selected' : ''; ?>>COMPONENTE - peça/capa/estrutura</option>
                                        <option value="COMPOSTO" <?php echo $tipoAtual === 'COMPOSTO' ? 'selected' : ''; ?>>COMPOSTO - montado por componentes</option>
                                        <option value="SERVICO" <?php echo $tipoAtual === 'SERVICO' ? 'selected' : ''; ?>>SERVIÇO - sem estoque físico</option>
                                    </select>
                                    <small class="form-text text-muted">Ex.: capa de pufe = COMPONENTE; pufe forrado azul = COMPOSTO.</small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group mt-md-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="controla_estoque" name="controla_estoque" value="1" <?php echo $controlaEstoqueAtual === 1 ? 'checked' : ''; ?>>
                                        <label for="controla_estoque" class="custom-control-label">Controla estoque?</label>
                                    </div>
                                    <small class="form-text text-muted">Desmarque para composto calculado só pelos componentes.</small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="quantidade_total">Qtd. Total <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="quantidade_total" name="quantidade_total" min="0" step="1" required value="<?php echo htmlspecialchars($produto_data['quantidade_total'] ?? '0'); ?>">
                                    <small class="form-text text-muted">Para composto sem estoque próprio, mantenha 0.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Linha 5: Disponibilidades -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mt-md-2">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="disponivel_locacao" name="disponivel_locacao" value="1" <?php echo !empty($produto_data['disponivel_locacao']) ? 'checked' : ''; ?>>
                                        <label for="disponivel_locacao" class="custom-control-label">Disponível para Locação?</label>
                                    </div>
                                </div>
                             </div>

                             <div class="col-md-6">
                                <div class="form-group mt-md-2">
                                     <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="disponivel_venda" name="disponivel_venda" value="1" <?php echo !empty($produto_data['disponivel_venda']) ? 'checked' : ''; ?>>
                                        <label for="disponivel_venda" class="custom-control-label">Disponível para Venda?</label>
                                    </div>
                                </div>
                             </div>
                        </div>

                        <hr>

                        <!-- Foto Atual e Opção de Nova Foto -->
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <label>Foto Atual:</label>
                                <?php
                                    $fotoAtualUrl = $placeholderImgUrl;
                                    if (!empty($produto_data['foto_path'])) {
                                        $caminho_fisico = __DIR__ . '/../../' . $produto_data['foto_path'];
                                        if (file_exists($caminho_fisico)) {
                                             $fotoAtualUrl = rtrim(BASE_URL, '/') . '/' . ltrim($produto_data['foto_path'], '/');
                                        }
                                    }
                                ?>
                                <img src="<?php echo htmlspecialchars($fotoAtualUrl); ?>" alt="Foto Atual" class="img-thumbnail" style="max-width: 100px; max-height: 100px; margin-top: 5px;">
                            </div>
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label for="nova_foto">Trocar Foto (Opcional)</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="nova_foto" name="nova_foto" accept="image/png, image/jpeg, image/gif, image/webp">
                                        <label class="custom-file-label" for="nova_foto" data-browse="Procurar">Escolher novo arquivo...</label>
                                    </div>
                                    <small class="form-text text-muted">Max 2MB (JPG, PNG, GIF, WebP). Se nenhum arquivo for selecionado, a foto atual será mantida.</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Preços -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="preco_locacao">Preço Locação (R$)</label>
                                    <input type="text" class="form-control money" id="preco_locacao" name="preco_locacao" value="<?php echo number_format($produto_data['preco_locacao'] ?? 0, 2, ',', '.'); ?>">
                                </div>
                            </div>

                             <div class="col-md-4">
                                <div class="form-group">
                                    <label for="preco_venda">Preço Venda (R$)</label>
                                    <input type="text" class="form-control money" id="preco_venda" name="preco_venda" value="<?php echo number_format($produto_data['preco_venda'] ?? 0, 2, ',', '.'); ?>">
                                </div>
                             </div>

                             <div class="col-md-4">
                                <div class="form-group">
                                    <label for="preco_custo">Preço Custo (R$)</label>
                                    <input type="text" class="form-control money" id="preco_custo" name="preco_custo" value="<?php echo number_format($produto_data['preco_custo'] ?? 0, 2, ',', '.'); ?>">
                                    <small class="form-text text-muted">Referência interna.</small>
                                </div>
                             </div>
                        </div>

                        <!-- Observações -->
                        <div class="form-group">
                            <label for="observacoes">Observações Internas</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?php echo htmlspecialchars($produto_data['observacoes'] ?? ''); ?></textarea>
                        </div>

                        <!-- Composição -->
                        <div id="card_composicao" class="card card-outline card-info mt-4" style="<?php echo $tipoAtual === 'COMPOSTO' ? '' : 'display:none;'; ?>">
                            <div class="card-header">
                                <h3 class="card-title">Componentes deste produto composto</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    Use esta área para montar produtos como: <strong>Pufe forrado azul = Pufe Estrutura + Capa Pufe Azul</strong>.
                                    A quantidade aqui é quanto cada unidade do produto composto consome do componente.
                                </p>

                                <div id="componentes-container">
                                    <?php if (!empty($componentesAtuais)): ?>
                                        <?php foreach ($componentesAtuais as $componente): ?>
                                            <div class="row componente-row align-items-end mb-2">
                                                <div class="col-md-5">
                                                    <label>Componente</label>
                                                    <select name="componente_produto_id[]" class="form-control componente-select">
                                                        <option value="">-- Selecione --</option>
                                                        <?php foreach ($produtosParaComposicao as $produtoOpcao): ?>
                                                            <option value="<?php echo (int)$produtoOpcao['id']; ?>" <?php echo ((int)$produtoOpcao['id'] === (int)$componente['produto_filho_id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($produtoOpcao['nome_produto']); ?>
                                                                <?php if (!empty($produtoOpcao['tipo_produto'])): ?>
                                                                    (<?php echo htmlspecialchars($produtoOpcao['tipo_produto']); ?>)
                                                                <?php endif; ?>
                                                                - Estoque: <?php echo (int)($produtoOpcao['quantidade_total'] ?? 0); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="col-md-2">
                                                    <label>Qtd. usada</label>
                                                    <input type="text" name="componente_quantidade[]" class="form-control" value="<?php echo number_format((float)$componente['quantidade'], 2, ',', '.'); ?>">
                                                </div>

                                                <div class="col-md-2">
                                                    <label>Obrigatório</label>
                                                    <div class="custom-control custom-checkbox mt-2">
                                                        <input type="checkbox" class="custom-control-input componente-obrigatorio" id="obrigatorio_<?php echo (int)$componente['id']; ?>" name="componente_obrigatorio[]" value="1" <?php echo !empty($componente['obrigatorio']) ? 'checked' : ''; ?>>
                                                        <label class="custom-control-label" for="obrigatorio_<?php echo (int)$componente['id']; ?>">Sim</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <label>Observação</label>
                                                    <input type="text" name="componente_observacoes[]" class="form-control" value="<?php echo htmlspecialchars($componente['observacoes'] ?? ''); ?>">
                                                </div>

                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-danger btn-block remover-componente">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <button type="button" id="adicionar-componente" class="btn btn-outline-info mt-2">
                                    <i class="fas fa-plus mr-1"></i> Adicionar Componente
                                </button>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                         <a href="index.php" class="btn btn-secondary">Cancelar</a>
                         <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<template id="template-componente-row">
    <div class="row componente-row align-items-end mb-2">
        <div class="col-md-5">
            <label>Componente</label>
            <select name="componente_produto_id[]" class="form-control componente-select">
                <option value="">-- Selecione --</option>
                <?php foreach ($produtosParaComposicao as $produtoOpcao): ?>
                    <option value="<?php echo (int)$produtoOpcao['id']; ?>">
                        <?php echo htmlspecialchars($produtoOpcao['nome_produto']); ?>
                        <?php if (!empty($produtoOpcao['tipo_produto'])): ?>
                            (<?php echo htmlspecialchars($produtoOpcao['tipo_produto']); ?>)
                        <?php endif; ?>
                        - Estoque: <?php echo (int)($produtoOpcao['quantidade_total'] ?? 0); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label>Qtd. usada</label>
            <input type="text" name="componente_quantidade[]" class="form-control" value="1,00">
        </div>

        <div class="col-md-2">
            <label>Obrigatório</label>
            <div class="custom-control custom-checkbox mt-2">
                <input type="checkbox" class="custom-control-input componente-obrigatorio" name="componente_obrigatorio[]" value="1" checked>
                <label class="custom-control-label">Sim</label>
            </div>
        </div>

        <div class="col-md-2">
            <label>Observação</label>
            <input type="text" name="componente_observacoes[]" class="form-control" value="">
        </div>

        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-block remover-componente">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</template>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Inicialização do plugin de máscara monetária -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script>
$(document).ready(function() {
    $('.money').maskMoney({
        prefix: 'R$ ',
        allowNegative: false,
        thousands: '.',
        decimal: ',',
        affixesStay: false
    });

    if (typeof bsCustomFileInput !== 'undefined') {
        bsCustomFileInput.init();
    }
});
</script>

<!-- Lógica JavaScript para dropdowns dependentes e composição -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const dataHierarchy = <?php echo $dataHierarchy; ?>;

    const secaoSelect = document.getElementById("secao_id");
    const categoriaSelect = document.getElementById("categoria_id");
    const subcategoriaSelect = document.getElementById("subcategoria_id");
    const tipoProdutoSelect = document.getElementById("tipo_produto");
    const controlaEstoqueCheckbox = document.getElementById("controla_estoque");
    const quantidadeInput = document.getElementById("quantidade_total");
    const cardComposicao = document.getElementById("card_composicao");
    const componentesContainer = document.getElementById("componentes-container");
    const adicionarComponenteBtn = document.getElementById("adicionar-componente");
    const templateComponente = document.getElementById("template-componente-row");

    function atualizarVisibilidadeComposicao() {
        const tipo = tipoProdutoSelect.value;

        if (tipo === "COMPOSTO") {
            cardComposicao.style.display = "";
        } else {
            cardComposicao.style.display = "none";
        }

        if (tipo === "SERVICO") {
            controlaEstoqueCheckbox.checked = false;
            quantidadeInput.value = 0;
        }
    }

    function atualizarIdsCheckboxesObrigatorio() {
        const rows = componentesContainer.querySelectorAll(".componente-row");
        rows.forEach((row, index) => {
            const checkbox = row.querySelector(".componente-obrigatorio");
            const label = row.querySelector(".custom-control-label");

            if (checkbox && label) {
                const id = "componente_obrigatorio_" + index + "_" + Date.now();
                checkbox.id = id;
                label.setAttribute("for", id);
            }
        });
    }

    secaoSelect.addEventListener("change", function () {
        const secaoId = this.value;

        categoriaSelect.innerHTML = '<option value="">-- Selecione Categoria --</option>';
        categoriaSelect.disabled = true;

        subcategoriaSelect.innerHTML = '<option value="">-- Selecione Subcategoria --</option>';
        subcategoriaSelect.disabled = true;

        if (secaoId) {
            const categorias = dataHierarchy.categorias.filter(cat => cat.secao_id == secaoId);
            categorias.forEach(cat => {
                const option = document.createElement("option");
                option.value = cat.id;
                option.textContent = cat.nome;
                categoriaSelect.appendChild(option);
            });
            categoriaSelect.disabled = false;
        }
    });

    categoriaSelect.addEventListener("change", function () {
        const categoriaId = this.value;

        subcategoriaSelect.innerHTML = '<option value="">-- Selecione Subcategoria --</option>';
        subcategoriaSelect.disabled = true;

        if (categoriaId) {
            const subcategorias = dataHierarchy.subcategorias.filter(sub => sub.categoria_id == categoriaId);
            subcategorias.forEach(sub => {
                const option = document.createElement("option");
                option.value = sub.id;
                option.textContent = sub.nome;
                subcategoriaSelect.appendChild(option);
            });
            subcategoriaSelect.disabled = false;
        }
    });

    tipoProdutoSelect.addEventListener("change", atualizarVisibilidadeComposicao);

    if (adicionarComponenteBtn && templateComponente && componentesContainer) {
        adicionarComponenteBtn.addEventListener("click", function () {
            const clone = document.importNode(templateComponente.content, true);
            componentesContainer.appendChild(clone);
            atualizarIdsCheckboxesObrigatorio();
        });
    }

    if (componentesContainer) {
        componentesContainer.addEventListener("click", function (event) {
            const btn = event.target.closest(".remover-componente");
            if (btn) {
                const row = btn.closest(".componente-row");
                if (row) {
                    row.remove();
                    atualizarIdsCheckboxesObrigatorio();
                }
            }
        });
    }

    atualizarVisibilidadeComposicao();
    atualizarIdsCheckboxesObrigatorio();
});
</script>
