<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Secao.php';
require_once __DIR__ . '/../../models/Categoria.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

// Verificar login/acesso (descomentar/implementar se necessário)
// ...

$page_title = "Editar Produto"; // Define o título da página
$error = null; // Para mensagens de erro
$success = null; // Para mensagens de sucesso (não usaremos aqui, mas bom ter)
$produto_data = null; // Para armazenar os dados do produto a ser editado

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
    redirect('views/produtos/index.php'); // Redireciona se ID for inválido
}
$produto_id = (int)$_GET['id'];

// --- 2. Buscar Dados do Produto Específico ---
$produto_data = $produto->lerPorId($produto_id);

// --- 3. Verificar se o Produto Foi Encontrado ---
if (!$produto_data) {
    $_SESSION['error'] = "Produto com ID {$produto_id} não encontrado.";
    redirect('views/produtos/index.php');
}

// --- 4. Buscar Seções, Categorias e Subcategorias para os Selects ---
try {
    $secoes = $secaoModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $categorias = $categoriaModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $subcategorias = $subcategoriaModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $dataHierarchy = json_encode([
        'secoes' => $secoes,
        'categorias' => $categorias,
        'subcategorias' => $subcategorias,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    error_log("Erro ao carregar hierarquia: " . $e->getMessage());
}

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar ID vindo do POST (segurança extra)
    if (!isset($_POST['produto_id']) || !filter_var($_POST['produto_id'], FILTER_VALIDATE_INT) || (int)$_POST['produto_id'] !== $produto_id) {
        $_SESSION['error'] = "Erro de submissão: ID do produto inconsistente.";
        redirect('views/produtos/index.php');
    }

    // 2. Atribuir dados do POST ao objeto Produto
    $produto->id = $produto_id; // Garante que o ID correto está no objeto
    $produto->subcategoria_id = isset($_POST['subcategoria_id']) ? (int)$_POST['subcategoria_id'] : 0;
    
    // Tratamento especial para o código - se estiver vazio, define como NULL
    $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
    $produto->codigo = !empty($codigo) ? $codigo : null;
    
    $produto->nome_produto = isset($_POST['nome_produto']) ? trim($_POST['nome_produto']) : '';
    $produto->descricao_detalhada = isset($_POST['descricao_detalhada']) ? trim($_POST['descricao_detalhada']) : null;
    $produto->dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $produto->cor = isset($_POST['cor']) ? trim($_POST['cor']) : null;
    $produto->material = isset($_POST['material']) ? trim($_POST['material']) : null;
    $produto->quantidade_total = isset($_POST['quantidade_total']) ? (int)$_POST['quantidade_total'] : 0;
    
    // Tratamento especial para os campos de preço
    if (isset($_POST['preco_locacao'])) {
        $produto->preco_locacao = $_POST['preco_locacao'];
    }
    if (isset($_POST['preco_venda'])) {
        $produto->preco_venda = $_POST['preco_venda'];
    }
    if (isset($_POST['preco_custo'])) {
        $produto->preco_custo = $_POST['preco_custo'];
    }
    
    // Checkboxes
    $produto->disponivel_venda = isset($_POST['disponivel_venda']) ? 1 : 0;
    $produto->disponivel_locacao = isset($_POST['disponivel_locacao']) ? 1 : 0;
    
    $produto->observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : null;

    // ----- TRATAMENTO DO UPLOAD DA NOVA FOTO -----
    $novaFotoPath = $_POST['foto_atual_path']; // Começa com o caminho da foto atual
    $fotoAntigaPath = $_POST['foto_atual_path'];
    $uploadOk = true;
    $uploadErrorMsg = '';

    // Verifica se um NOVO arquivo foi enviado
    if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['nova_foto'];
        $uploadDir = __DIR__ . '/../../assets/uploads/produtos/'; // Caminho FÍSICO no servidor
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        // Cria o diretório se não existir
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Validar tipo
        if (!in_array($file['type'], $allowedTypes)) {
            $uploadErrorMsg = "Erro: Tipo de arquivo inválido ({$file['type']}). Apenas JPG, PNG, GIF, WebP são permitidos.";
            $uploadOk = false;
        }
        // Validar tamanho
        elseif ($file['size'] > $maxFileSize) {
            $uploadErrorMsg = "Erro: Arquivo muito grande ({$file['size']} bytes). Máximo de 2MB.";
            $uploadOk = false;
        }

        if ($uploadOk) {
            // Gerar nome único para o arquivo
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $novoNomeArquivo = 'prod_' . $produto_id . '_' . time() . '.' . $fileExtension;
            $caminhoCompletoFisico = $uploadDir . $novoNomeArquivo;

            // Tentar mover o arquivo
            if (move_uploaded_file($file['tmp_name'], $caminhoCompletoFisico)) {
                // Sucesso no upload! Atualiza o path que será salvo no banco
                $novaFotoPath = 'assets/uploads/produtos/' . $novoNomeArquivo;

                // Tenta apagar a foto antiga (se existir e for diferente do placeholder)
                if (!empty($fotoAntigaPath) && $fotoAntigaPath !== 'assets/img/product_placeholder.png') {
                    $caminhoFisicoAntigo = __DIR__ . '/../../' . $fotoAntigaPath;
                    if (file_exists($caminhoFisicoAntigo)) {
                        @unlink($caminhoFisicoAntigo); // @ para suprimir erros se não conseguir deletar
                    }
                }
            } else {
                $uploadErrorMsg = "Erro ao mover o arquivo enviado.";
                $uploadOk = false;
            }
        }
    } elseif (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Se houve um erro no upload (diferente de "nenhum arquivo enviado")
        $uploadErrorMsg = "Erro no upload da foto: Código " . $_FILES['nova_foto']['error'];
        $uploadOk = false;
    }

    // Se o upload falhou, define o erro e impede a atualização
    if (!$uploadOk) {
        $error = $uploadErrorMsg;
    } else {
        // Atualiza o caminho da foto no objeto (seja o novo ou o antigo)
        $produto->foto_path = $novaFotoPath;

        // Tentar atualizar o produto no banco de dados
        try {
            if ($produto->atualizar()) {
                $_SESSION['message'] = "Produto '" . htmlspecialchars($produto->nome_produto) . "' atualizado com sucesso!";
                // Redireciona para a lista de produtos
                redirect('views/produtos/index.php');
            } else {
                $error = "Erro ao atualizar o produto. Verifique os logs ou tente novamente.";
            }
        } catch (Exception $e) {
            $error = "Exceção ao atualizar o produto: " . $e->getMessage();
        }
    }
}
// --- FIM DA LÓGICA DE PROCESSAMENTO ---


// Incluir o cabeçalho e a barra lateral
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';

// Define o caminho do placeholder
$placeholderImgUrl = (defined('BASE_URL') ? BASE_URL : '/') . 'assets/img/product_placeholder.png';

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
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-warning"> <!-- Mudei para card-warning para indicar edição -->
                <div class="card-header">
                    <h3 class="card-title">Formulário de Edição de Produto</h3>
                </div>
                <!-- Formulário HTML com enctype e preenchimento de dados -->
                <!-- O action aponta para si mesmo, incluindo o ID na URL -->
                <form method="POST" action="edit.php?id=<?php echo $produto_id; ?>" enctype="multipart/form-data">

                    <!-- Campo Oculto Essencial para o ID -->
                    <input type="hidden" name="produto_id" value="<?php echo $produto_id; ?>">
                    <!-- Campo Oculto para guardar caminho da foto atual -->
                    <input type="hidden" name="foto_atual_path" value="<?php echo htmlspecialchars($produto_data['foto_path'] ?? ''); ?>">

                    <div class="card-body">
                        <!-- Mensagens de Erro/Sucesso -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        <?php include_once __DIR__ . '/../includes/alert_messages.php'; // Para mensagens da sessão ?>

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
                             <div class="col-md-4"><div class="form-group"><label for="dimensoes">Dimensões</label><input type="text" class="form-control" id="dimensoes" name="dimensoes" value="<?php echo htmlspecialchars($produto_data['dimensoes'] ?? ''); ?>"></div></div>
                             <div class="col-md-4"><div class="form-group"><label for="cor">Cor</label><input type="text" class="form-control" id="cor" name="cor" value="<?php echo htmlspecialchars($produto_data['cor'] ?? ''); ?>"></div></div>
                             <div class="col-md-4"><div class="form-group"><label for="material">Material</label><input type="text" class="form-control" id="material" name="material" value="<?php echo htmlspecialchars($produto_data['material'] ?? ''); ?>"></div></div>
                         </div>

                         <!-- Linha 4: Quantidade, Disponibilidades -->
                         <div class="row">
                             <div class="col-md-4">
                                <div class="form-group">
                                    <label for="quantidade_total">Qtd. Total <span class="text-danger">*</span></label>
                                    <!-- Nota: Editar quantidades disponíveis/reservadas pode exigir lógica adicional -->
                                    <input type="number" class="form-control" id="quantidade_total" name="quantidade_total" min="0" step="1" required value="<?php echo htmlspecialchars($produto_data['quantidade_total'] ?? '0'); ?>">
                                </div>
                             </div>
                             <div class="col-md-4">
                                <div class="form-group mt-md-4"> <!-- Ajuste de margem -->
                                     <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="disponivel_locacao" name="disponivel_locacao" value="1" <?php echo !empty($produto_data['disponivel_locacao']) ? 'checked' : ''; ?>>
                                        <label for="disponivel_locacao" class="custom-control-label">Locação?</label>
                                    </div>
                                </div>
                             </div>
                             <div class="col-md-4">
                                <div class="form-group mt-md-4">
                                     <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="disponivel_venda" name="disponivel_venda" value="1" <?php echo !empty($produto_data['disponivel_venda']) ? 'checked' : ''; ?>>
                                        <label for="disponivel_venda" class="custom-control-label">Venda?</label>
                                    </div>
                                </div>
                             </div>
                         </div>

                         <hr>

                         <!-- Linha 5: Foto Atual e Opção de Nova Foto -->
                         <div class="row align-items-center">
                            <div class="col-md-3">
                                <label>Foto Atual:</label>
                                <?php
                                    $fotoAtualUrl = $placeholderImgUrl; // Default
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
                                        <!-- Atenção ao NOME do input: 'nova_foto' -->
                                        <input type="file" class="custom-file-input" id="nova_foto" name="nova_foto" accept="image/png, image/jpeg, image/gif, image/webp">
                                        <label class="custom-file-label" for="nova_foto" data-browse="Procurar">Escolher novo arquivo...</label>
                                    </div>
                                    <small class="form-text text-muted">Max 2MB (JPG, PNG, GIF, WebP). Se nenhum arquivo for selecionado, a foto atual será mantida.</small>
                                </div>
                            </div>
                         </div>

                         <hr>

                         <!-- Linha 6: Preços -->
                         <div class="row">
                            <div class="col-md-4"><div class="form-group"><label for="preco_locacao">Preço Locação (R$)</label><input type="text" class="form-control money" id="preco_locacao" name="preco_locacao" value="<?php echo number_format($produto_data['preco_locacao'] ?? 0, 2, ',', '.'); ?>"></div></div>
                             <div class="col-md-4"><div class="form-group"><label for="preco_venda">Preço Venda (R$)</label><input type="text" class="form-control money" id="preco_venda" name="preco_venda" value="<?php echo number_format($produto_data['preco_venda'] ?? 0, 2, ',', '.'); ?>"></div></div>
                             <div class="col-md-4"><div class="form-group"><label for="preco_custo">Preço Custo (R$)</label><input type="text" class="form-control money" id="preco_custo" name="preco_custo" value="<?php echo number_format($produto_data['preco_custo'] ?? 0, 2, ',', '.'); ?>"><small class="form-text text-muted">Referência interna.</small></div></div>
                         </div>

                        <!-- Linha 7: Observações -->
                         <div class="form-group">
                            <label for="observacoes">Observações Internas</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?php echo htmlspecialchars($produto_data['observacoes'] ?? ''); ?></textarea>
                        </div>

                    </div>
                    <!-- /.card-body -->

                    <div class="card-footer text-right">
                         <a href="index.php" class="btn btn-secondary">Cancelar</a>
                         <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Salvar Alterações</button>
                    </div>
                </form>
            </div>
            <!-- /.card -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php
// Incluir o rodapé da página (que já tem os scripts JS)
include_once __DIR__ . '/../includes/footer.php';
?>

<!-- Inicialização do plugin de máscara monetária -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializa a máscara para campos monetários
    $('.money').maskMoney({
        prefix: 'R$ ',
        allowNegative: false,
        thousands: '.',
        decimal: ',',
        affixesStay: false
    });
    
    // Inicializa o plugin para o input de arquivo personalizado
    if (typeof bsCustomFileInput !== 'undefined') {
        bsCustomFileInput.init();
    }
});
</script>

<!-- Lógica JavaScript para os dropdowns dependentes -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const dataHierarchy = <?php echo $dataHierarchy; ?>;

    const secaoSelect = document.getElementById("secao_id");
    const categoriaSelect = document.getElementById("categoria_id");
    const subcategoriaSelect = document.getElementById("subcategoria_id");

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
});
</script>