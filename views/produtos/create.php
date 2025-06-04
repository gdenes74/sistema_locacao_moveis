<?php
// ADICIONE ESTAS 3 LINHAS NO INÍCIO
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_level'] = 'admin';
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Secao.php';
require_once __DIR__ . '/../../models/Categoria.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

// Inicializa a conexão e os modelos
$database = new Database();
$conn = $database->getConnection();

$produto = new Produto($conn);
$secaoModel = new Secao($conn);
$categoriaModel = new Categoria($conn);
$subcategoriaModel = new Subcategoria($conn);

$page_title = "Adicionar Novo Produto";
$error = null;
$success = null;

// Carrega as Seções, Categorias e Subcategorias
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

// Processamento do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atribuir dados do POST ao objeto Produto usando o método __set
    // O método __set já está implementado na classe Produto e fará a sanitização
    
    // Atribui subcategoria_id
    if (isset($_POST['subcategoria_id']) && !empty($_POST['subcategoria_id'])) {
        $produto->subcategoria_id = (int)$_POST['subcategoria_id'];
    } else {
        $error = "A subcategoria é obrigatória.";
    }
    
    // Atribui nome_produto
    if (isset($_POST['nome_produto']) && !empty($_POST['nome_produto'])) {
        $produto->nome_produto = trim($_POST['nome_produto']);
    } else {
        $error = "O nome do produto é obrigatório.";
    }
    
    // Atribui outros campos
    $produto->codigo = isset($_POST['codigo']) && !empty($_POST['codigo']) ? trim($_POST['codigo']) : null;
    $produto->descricao_detalhada = isset($_POST['descricao_detalhada']) ? trim($_POST['descricao_detalhada']) : null;
    $produto->dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $produto->cor = isset($_POST['cor']) ? trim($_POST['cor']) : null;
    $produto->material = isset($_POST['material']) ? trim($_POST['material']) : null;
    $produto->quantidade_total = isset($_POST['quantidade_total']) ? (int)$_POST['quantidade_total'] : 0;
    
    // Tratamento especial para os campos de preço
    if (isset($_POST['preco_locacao']) && !empty($_POST['preco_locacao'])) {
        $produto->preco_locacao = $_POST['preco_locacao']; // O método __set fará a conversão
    } else {
        $produto->preco_locacao = 0.00;
    }
    
    if (isset($_POST['preco_venda']) && !empty($_POST['preco_venda'])) {
        $produto->preco_venda = $_POST['preco_venda']; // O método __set fará a conversão
    } else {
        $produto->preco_venda = 0.00;
    }
    
    if (isset($_POST['preco_custo']) && !empty($_POST['preco_custo'])) {
        $produto->preco_custo = $_POST['preco_custo']; // O método __set fará a conversão
    } else {
        $produto->preco_custo = 0.00;
    }
    
    // Checkboxes
    $produto->disponivel_venda = isset($_POST['disponivel_venda']) ? 1 : 0;
    $produto->disponivel_locacao = isset($_POST['disponivel_locacao']) ? 1 : 0;
    
    // Observações
    $produto->observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : null;

    // Processar upload de foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../assets/uploads/produtos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $foto_name = uniqid('foto_') . '_' . basename($_FILES['foto']['name']);
        $foto_path = $upload_dir . $foto_name;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
            $produto->foto_path = 'assets/uploads/produtos/' . $foto_name;
        } else {
            $error = "Erro ao fazer upload da foto.";
        }
    }

    // Se não houver erro, tenta criar o produto
    if (!$error) {
        try {
            if ($produto->criar()) {
                $_SESSION['success'] = "Produto criado com sucesso!";
                header("Location: index.php");
                exit;
            } else {
                $error = "Erro ao criar o produto. Verifique os logs para mais detalhes.";
            }
        } catch (Exception $e) {
            $error = "Erro ao criar o produto: " . $e->getMessage();
        }
    }
}

// Inclui o cabeçalho
include_once __DIR__ . '/../includes/header.php';

?>

<!-- Conteúdo principal -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Adicionar Produto</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/dashboard">Início</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/produtos/index.php">Produtos</a></li>
                        <li class="breadcrumb-item active">Adicionar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Erro!</h5>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Sucesso!</h5>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Novo Produto</h3>
                </div>

                <form method="POST" action="create.php" enctype="multipart/form-data">
                    <div class="card-body">

                        <!-- Dropdowns dependentes -->
                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="secao_id">Seção *</label>
                                <select id="secao_id" name="secao_id" class="form-control" required>
                                    <option value="">-- Selecione a Seção --</option>
                                    <?php foreach ($secoes as $secao): ?>
                                        <option value="<?php echo htmlspecialchars($secao['id']); ?>">
                                            <?php echo htmlspecialchars($secao['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="categoria_id">Categoria *</label>
                                <select id="categoria_id" name="categoria_id" class="form-control" required disabled>
                                    <option value="">-- Selecione Seção Primeiro --</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="subcategoria_id">Subcategoria *</label>
                                <select id="subcategoria_id" name="subcategoria_id" class="form-control" required disabled>
                                    <option value="">-- Selecione Categoria Primeiro --</option>
                                </select>
                            </div>
                        </div>

                        <!-- Outros campos do formulário -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="nome_produto">Nome do Produto *</label>
                                    <input type="text" id="nome_produto" name="nome_produto" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="codigo">Código</label>
                                    <input type="text" id="codigo" name="codigo" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="descricao_detalhada">Descrição Detalhada</label>
                            <textarea id="descricao_detalhada" name="descricao_detalhada" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="dimensoes">Dimensões</label>
                                <input type="text" id="dimensoes" name="dimensoes" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="cor">Cor</label>
                                <input type="text" id="cor" name="cor" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="material">Material</label>
                                <input type="text" id="material" name="material" class="form-control">
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="quantidade_total">Quantidade Total *</label>
                                <input type="number" id="quantidade_total" name="quantidade_total" class="form-control" min="0" value="0" required>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mt-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="disponivel_venda" name="disponivel_venda" value="1" class="custom-control-input" checked>
                                        <label for="disponivel_venda" class="custom-control-label">Disponível para Venda</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mt-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="disponivel_locacao" name="disponivel_locacao" value="1" class="custom-control-input" checked>
                                        <label for="disponivel_locacao" class="custom-control-label">Disponível para Locação</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="preco_locacao">Preço Locação (R$)</label>
                                <input type="text" id="preco_locacao" name="preco_locacao" class="form-control money" placeholder="0,00">
                            </div>
                            <div class="col-md-4">
                                <label for="preco_venda">Preço Venda (R$)</label>
                                <input type="text" id="preco_venda" name="preco_venda" class="form-control money" placeholder="0,00">
                            </div>
                            <div class="col-md-4">
                                <label for="preco_custo">Preço Custo (R$)</label>
                                <input type="text" id="preco_custo" name="preco_custo" class="form-control money" placeholder="0,00">
                                <small class="text-muted">Referência interna.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="foto">Foto do Produto</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/*">
                                <label class="custom-file-label" for="foto" data-browse="Procurar">Escolher arquivo...</label>
                            </div>
                            <small class="form-text text-muted">Máximo 2MB (JPG, PNG, GIF, WebP)</small>
                        </div>

                        <div class="form-group">
                            <label for="observacoes">Observações Internas</label>
                            <textarea id="observacoes" name="observacoes" class="form-control" rows="2"></textarea>
                        </div>

                    </div>

                    <!-- Botões -->
                    <div class="card-footer text-right">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Salvar Produto</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<!-- Inclui o rodapé -->
<?php include_once __DIR__ . '/../includes/footer.php'; ?>

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