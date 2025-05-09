<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Secao.php';
require_once __DIR__ . '/../../models/Categoria.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- Config Upload ---
if (!defined('UPLOAD_DIR_PRODUTOS')) define('UPLOAD_DIR_PRODUTOS', 'assets/uploads/produtos/');
if (!defined('UPLOAD_MAX_SIZE')) define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
if (!defined('UPLOAD_ALLOWED_TYPES')) define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

$database = new Database(); $db = $database->getConnection();
if (!$db) { error_log("DB Conn Failed - create.php"); die("DB Error."); }

$produto = new Produto($db); $secaoModel = new Secao($db);
$categoriaModel = new Categoria($db); $subcategoriaModel = new Subcategoria($db);

// --- Tratamento de Mensagens e Dados de Formulário ---
$error = $_SESSION['error_message'] ?? null; unset($_SESSION['error_message']);
$message = $_SESSION['message'] ?? null; unset($_SESSION['message']);
$form_data = $_SESSION['form_data'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : []);
unset($_SESSION['form_data']);

// --- Carregar Dados Hierárquicos ---
$secoes = []; $categorias = []; $subcategorias = [];
$jsDataHierarchy = ['secoes'=>[], 'categorias'=>[], 'subcategorias'=>[]]; // INICIALIZA estrutura JS vazia
try {
    $stmtSec = $secaoModel->listar(); $secoes = $stmtSec ? $stmtSec->fetchAll(PDO::FETCH_ASSOC) : [];
    $stmtCat = $categoriaModel->listar(); $categorias = $stmtCat ? $stmtCat->fetchAll(PDO::FETCH_ASSOC) : [];
    $stmtSub = $subcategoriaModel->listar(); $subcategorias = $stmtSub ? $stmtSub->fetchAll(PDO::FETCH_ASSOC) : [];
    // Preenche a estrutura JS SOMENTE se os dados foram carregados
    if (!empty($secoes) || !empty($categorias) || !empty($subcategorias)) {
        $jsDataHierarchy = ['secoes'=>$secoes, 'categorias'=>$categorias, 'subcategorias'=>$subcategorias];
    }
} catch (Exception $e) {
    $error = "Erro crítico ao carregar opções de seleção. Verifique tabelas e logs.";
    error_log("Hierarchy Load Err - create.php: " . $e->getMessage());
    // Mantém a estrutura JS vazia em caso de erro
}
// --- Fim Carregar Dados ---

// --- Processamento do Formulário POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = $_POST;
    $error = null;

    // Validações Essenciais (verificando se os IDs são inteiros > 0)
    if (empty($form_data['nome_produto'])) { $error = "Nome do Produto é obrigatório."; }
    elseif (empty($form_data['secao_id']) || !filter_var($form_data['secao_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) { $error = "Seleção de Seção inválida ou ausente."; }
    elseif (empty($form_data['categoria_id']) || !filter_var($form_data['categoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) { $error = "Seleção de Categoria inválida ou ausente."; }
    elseif (empty($form_data['subcategoria_id']) || !filter_var($form_data['subcategoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) { $error = "Seleção de Subcategoria inválida ou ausente."; }
    elseif (!isset($form_data['quantidade_total']) || !is_numeric($form_data['quantidade_total']) || (int)$form_data['quantidade_total'] < 0) { $error = "Quantidade Total inválida."; }

    // Upload Foto (Mesma lógica anterior, com pequenas melhorias nos logs)
    $uploaded_file_path = null; $temp_file_server_path = null;
    if ($error === null && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto']; $finfo = new finfo(FILEINFO_MIME_TYPE);
        $file_type = $finfo->file($file['tmp_name']);

        if (!in_array($file_type, UPLOAD_ALLOWED_TYPES)) { $error = "Tipo de arquivo de foto inválido (Permitidos: JPG, PNG, GIF, WebP)."; }
        elseif ($file['size'] > UPLOAD_MAX_SIZE) { $error = "Arquivo de foto muito grande (Máximo: " . (UPLOAD_MAX_SIZE / 1024 / 1024) . "MB)."; }
        else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $unique_filename = uniqid('prod_', true) . '.' . $ext;
            $destination_path_db = UPLOAD_DIR_PRODUTOS . $unique_filename;
            $destination_path_server = rtrim(dirname(__DIR__, 2), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($destination_path_db, '/'));
            $upload_dir_server = dirname($destination_path_server);

            if (!is_dir($upload_dir_server)) { if (!@mkdir($upload_dir_server, 0775, true)) { $error = "Falha crítica ao criar diretório de uploads."; error_log("Upload Mkdir Fail - create.php: " . $upload_dir_server); } }
            if ($error === null && !is_writable($upload_dir_server)) { $error = "Erro de permissão: Diretório de uploads sem permissão de escrita."; error_log("Upload Dir Write Err - create.php: " . $upload_dir_server); }
            if ($error === null) {
                if (move_uploaded_file($file['tmp_name'], $destination_path_server)) { $uploaded_file_path = $destination_path_db; $temp_file_server_path = $destination_path_server; }
                else { $error = "Falha ao salvar o arquivo de foto no servidor."; error_log("Move Upload Fail - create.php: " . $destination_path_server . " (from: ".$file['tmp_name'].")"); }
            }
        }
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
         $error = "Ocorreu um erro durante o upload da foto (Código: {$_FILES['foto']['error']})."; error_log("Upload Err Code - create.php: {$_FILES['foto']['error']}");
    }
    // --- Fim Upload Foto ---

    // --- Persistência no Banco de Dados ---
    if ($error === null) {
        try {
            $produto->atribuir($form_data);
            $produto->foto_path = $uploaded_file_path;
            if (!isset($form_data['quantidade_disponivel'])) $produto->quantidade_disponivel = $produto->quantidade_total;
            if (!isset($form_data['quantidade_reservada'])) $produto->quantidade_reservada = 0;
            //... (outras quantidades default = 0)

            if ($produto->criar()) {
                $_SESSION['message'] = "Produto '" . htmlspecialchars($produto->nome_produto) . "' adicionado!";
                unset($_SESSION['form_data']); header("Location: index.php"); exit;
            } else {
                $error = $_SESSION['error_message'] ?? "Erro desconhecido ao salvar no banco.";
                if ($temp_file_server_path && file_exists($temp_file_server_path)) { @unlink($temp_file_server_path); }
            }
        } catch (Exception $e) {
             $error = "Erro inesperado. Consulte logs."; error_log("Create Prod Exception - create.php: " . $e->getMessage());
             if ($temp_file_server_path && file_exists($temp_file_server_path)) { @unlink($temp_file_server_path); }
        }
    }
    // Se houve erro, guarda dados na sessão
    if ($error !== null) { $_SESSION['form_data'] = $form_data; }
}
// --- FIM DO PROCESSAMENTO POST ---

$page_title = "Adicionar Novo Produto";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2"><div class="col-sm-6"><h1><?= htmlspecialchars($page_title); ?></h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="../dashboard/dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="index.php">Produtos</a></li><li class="breadcrumb-item active">Adicionar</li></ol></div></div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Formulário de Cadastro</h3></div>
                <form method="POST" action="create.php" enctype="multipart/form-data" id="formProduto" novalidate>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><h5><i class="icon fas fa-ban"></i> Erro!</h5><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                        <?php if ($message): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><h5><i class="icon fas fa-check"></i> Sucesso!</h5><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

                        <div class="row">
                            <div class="col-md-8 form-group"><label for="nome_produto">Nome do Produto <span class="text-danger">*</span></label><input type="text" class="form-control <?= ($error && empty($form_data['nome_produto'])) ? 'is-invalid' : ''; ?>" id="nome_produto" name="nome_produto" required value="<?= htmlspecialchars($form_data['nome_produto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><div class="invalid-feedback">O nome é obrigatório.</div></div>
                            <div class="col-md-4 form-group"><label for="codigo">Código <span class="text-muted">(Opcional)</span></label><input type="text" class="form-control <?= (isset($error) && stripos($error, 'código') !== false) ? 'is-invalid' : ''; ?>" id="codigo" name="codigo" value="<?= htmlspecialchars($form_data['codigo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php if (isset($error) && stripos($error, 'código') !== false): ?><div class="invalid-feedback d-block">Código já em uso.</div><?php endif; ?></div>
                        </div>
                        <div class="row">
                             <div class="col-md-4 form-group"><label for="secao_id">Seção <span class="text-danger">*</span></label><select class="form-control <?= ($error && (empty($form_data['secao_id']) || !filter_var($form_data['secao_id'], FILTER_VALIDATE_INT))) ? 'is-invalid' : ''; ?>" id="secao_id" name="secao_id" required><option value="">-- Selecione --</option><?php foreach ($secoes as $sec): ?><option value="<?= $sec['id']; ?>" <?= (isset($form_data['secao_id']) && $form_data['secao_id'] == $sec['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($sec['nome'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select><div class="invalid-feedback">Selecione uma seção.</div></div>
                             <div class="col-md-4 form-group"><label for="categoria_id">Categoria <span class="text-danger">*</span></label><select class="form-control <?= ($error && (!empty($form_data['secao_id']) && (empty($form_data['categoria_id']) || !filter_var($form_data['categoria_id'], FILTER_VALIDATE_INT)))) ? 'is-invalid' : ''; ?>" id="categoria_id" name="categoria_id" required disabled><option value="">-- Selecione Seção --</option></select><div class="invalid-feedback">Selecione uma categoria.</div></div>
                             <div class="col-md-4 form-group"><label for="subcategoria_id">Subcategoria <span class="text-danger">*</span></label><select class="form-control <?= ($error && (!empty($form_data['categoria_id']) && (empty($form_data['subcategoria_id']) || !filter_var($form_data['subcategoria_id'], FILTER_VALIDATE_INT)))) ? 'is-invalid' : ''; ?>" id="subcategoria_id" name="subcategoria_id" required disabled><option value="">-- Selecione Categoria --</option></select><div class="invalid-feedback">Selecione a subcategoria.</div></div>
                        </div>
                        <div class="form-group"><label for="descricao_detalhada">Descrição</label><textarea class="form-control" id="descricao_detalhada" name="descricao_detalhada" rows="2"><?= htmlspecialchars($form_data['descricao_detalhada'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                        <div class="row">
                            <div class="col-md-4 form-group"><label for="dimensoes">Dimensões</label><input type="text" class="form-control" id="dimensoes" name="dimensoes" value="<?= htmlspecialchars($form_data['dimensoes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4 form-group"><label for="cor">Cor</label><input type="text" class="form-control" id="cor" name="cor" value="<?= htmlspecialchars($form_data['cor'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4 form-group"><label for="material">Material</label><input type="text" class="form-control" id="material" name="material" value="<?= htmlspecialchars($form_data['material'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                         </div>
                         <div class="row align-items-center mb-3">
                             <div class="col-lg-3 col-md-6 form-group"><label for="quantidade_total">Qtd. Total <span class="text-danger">*</span></label><input type="number" class="form-control <?= ($error && (!isset($form_data['quantidade_total']) || !is_numeric($form_data['quantidade_total']) || (int)$form_data['quantidade_total'] < 0)) ? 'is-invalid' : ''; ?>" id="quantidade_total" name="quantidade_total" min="0" step="1" required value="<?= htmlspecialchars($form_data['quantidade_total'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>"><div class="invalid-feedback">Inválido.</div></div>
                             <div class="col-lg-3 col-md-6 form-group d-flex align-items-center pt-lg-4"><div class="custom-control custom-switch custom-switch-lg"><input type="checkbox" class="custom-control-input" id="disponivel_locacao" name="disponivel_locacao" value="1" <?php echo (!isset($_POST['submit']) && empty($form_data)) || !empty($form_data['disponivel_locacao']) ? 'checked' : ''; ?>><label class="custom-control-label" for="disponivel_locacao">Locação?</label></div></div>
                             <div class="col-lg-2 col-md-6 form-group d-flex align-items-center pt-lg-4"><div class="custom-control custom-switch custom-switch-lg"><input type="checkbox" class="custom-control-input" id="disponivel_venda" name="disponivel_venda" value="1" <?= !empty($form_data['disponivel_venda']) ? 'checked' : ''; ?>><label class="custom-control-label" for="disponivel_venda">Venda?</label></div></div>
                             <div class="col-lg-4 col-md-6 form-group"><label for="foto">Foto</label><div class="custom-file"><input type="file" class="custom-file-input <?= (isset($error) && (stripos($error, 'foto') !== false || stripos($error, 'upload') !== false)) ? 'is-invalid' : ''; ?>" id="foto" name="foto" accept=".jpg,.jpeg,.png,.gif,.webp"><label class="custom-file-label" for="foto" data-browse="Procurar">Escolher...</label></div><small class="form-text text-muted">Max 2MB</small><?php if (isset($error) && (stripos($error, 'foto') !== false || stripos($error, 'upload') !== false)): ?><div class="text-danger small mt-1"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?></div>
                         </div><hr>
                         <div class="row">
                            <div class="col-md-4 form-group"><label for="preco_locacao">Preço Locação (R$)</label><input type="text" class="form-control money" id="preco_locacao" name="preco_locacao" value="<?= htmlspecialchars($form_data['preco_locacao'] ?? '0,00', ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4 form-group"><label for="preco_venda">Preço Venda (R$)</label><input type="text" class="form-control money" id="preco_venda" name="preco_venda" value="<?= htmlspecialchars($form_data['preco_venda'] ?? '0,00', ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="col-md-4 form-group"><label for="preco_custo">Preço Custo (R$)</label><input type="text" class="form-control money" id="preco_custo" name="preco_custo" value="<?= htmlspecialchars($form_data['preco_custo'] ?? '0,00', ENT_QUOTES, 'UTF-8'); ?>"><small class="text-muted">Ref. interna.</small></div>
                         </div>
                         <div class="form-group"><label for="observacoes">Obs. Internas</label><textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?= htmlspecialchars($form_data['observacoes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    </div>
                    <div class="card-footer text-right">
                         <a href="index.php" class="btn btn-secondary mr-2"><i class="fas fa-times mr-1"></i> Cancelar</a>
                         <button type="submit" name="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Salvar Produto</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ========================================== -->
<!--         SCRIPTS JS ESPECÍFICOS           -->
<!-- ========================================== -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script>
$(document).ready(function() {

    // ----- Plugins -----
    $('.money').maskMoney({prefix:'R$ ', allowNegative: false, thousands:'.', decimal:',', affixesStay: false, precision: 2}).maskMoney('mask');
    if (typeof bsCustomFileInput !== 'undefined') { bsCustomFileInput.init(); }
    else { $('.custom-file-input').on('change', function(e) { var fn = e.target.files.length ? e.target.files[0].name : 'Escolher...'; $(this).next('.custom-file-label').text(fn); }); }

    // ----- Dropdowns Dependentes -----
    const dataHierarchy = <?php echo json_encode($jsDataHierarchy, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    const secaoSelect = document.getElementById('secao_id');
    const categoriaSelect = document.getElementById('categoria_id');
    const subcategoriaSelect = document.getElementById('subcategoria_id');

    // Valores pré-selecionados (se vieram do PHP via $form_data)
    const selectedSecaoId = "<?= $form_data['secao_id'] ?? '' ?>";
    const selectedCategoriaId = "<?= $form_data['categoria_id'] ?? '' ?>";
    const selectedSubcategoriaId = "<?= $form_data['subcategoria_id'] ?? '' ?>";

    // --- DEBUG INICIAL --- (Descomente se necessário)
    // console.log('Script create.php carregado.');
    // console.log('Dados Hierarquia:', dataHierarchy);
    // console.log('IDs Pré-selecionados:', { secao: selectedSecaoId, categoria: selectedCategoriaId, subcategoria: selectedSubcategoriaId });
    // console.log('Elementos Select:', { secao: secaoSelect, categoria: categoriaSelect, subcategoria: subcategoriaSelect });

    // Função para limpar e resetar um select
    function resetSelect(selectElement, placeholderText) {
        if (!selectElement) return; // Sai se o elemento não existe
        selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
        selectElement.disabled = true;
        $(selectElement).removeClass('is-invalid'); // Remove classe de erro Bootstrap
    }

    // Função para popular CATEGORIAS
    function popularCategorias() {
        const secaoId = secaoSelect ? secaoSelect.value : null;
        // console.log('-> popularCategorias chamada com Seção ID:', secaoId); // DEBUG

        // Reseta selects filhos
        resetSelect(categoriaSelect, '-- Selecione Seção --');
        resetSelect(subcategoriaSelect, '-- Selecione Categoria --');

        if (secaoId && dataHierarchy.categorias && dataHierarchy.categorias.length > 0) {
            const categoriasFiltradas = dataHierarchy.categorias.filter(cat => cat.secao_id == secaoId);
            // console.log('   Categorias encontradas para esta seção:', categoriasFiltradas); // DEBUG

            if (categoriasFiltradas.length > 0) {
                 // Adiciona placeholder correto antes das opções
                 categoriaSelect.innerHTML = '<option value="">-- Selecione Categoria --</option>';
                 let categoriaFoiSelecionada = false;
                categoriasFiltradas.forEach(cat => {
                    const option = new Option(cat.nome, cat.id);
                    if (cat.id == selectedCategoriaId) {
                        option.selected = true;
                        categoriaFoiSelecionada = true; // Marca que uma pré-seleção ocorreu
                         // console.log(`   Pré-selecionando Categoria: ${cat.id} (${cat.nome})`); // DEBUG
                    }
                    categoriaSelect.appendChild(option);
                });
                categoriaSelect.disabled = false; // Habilita

                // Se uma categoria foi pré-selecionada, chama para popular subcategorias
                if (categoriaFoiSelecionada) {
                    // console.log('   Disparando popularSubcategorias devido à pré-seleção.'); // DEBUG
                    popularSubcategorias();
                }
            } else {
                 resetSelect(categoriaSelect, '-- Nenhuma Categoria --'); // Nenhuma encontrada
            }
        } else {
            // Mantém resetado se secaoId for nulo ou não houver categorias nos dados
             resetSelect(categoriaSelect, '-- Selecione Seção --');
        }
    }

    // Função para popular SUBCATEGORIAS
    function popularSubcategorias() {
        const categoriaId = categoriaSelect ? categoriaSelect.value : null;
         // console.log('-> popularSubcategorias chamada com Categoria ID:', categoriaId); // DEBUG

        // Reseta select filho
        resetSelect(subcategoriaSelect, '-- Selecione Categoria --');

        if (categoriaId && dataHierarchy.subcategorias && dataHierarchy.subcategorias.length > 0) {
            const subcategoriasFiltradas = dataHierarchy.subcategorias.filter(sub => sub.categoria_id == categoriaId);
             // console.log('   Subcategorias encontradas para esta categoria:', subcategoriasFiltradas); // DEBUG

            if (subcategoriasFiltradas.length > 0) {
                 // Adiciona placeholder correto
                 subcategoriaSelect.innerHTML = '<option value="">-- Selecione Subcategoria --</option>';
                subcategoriasFiltradas.forEach(sub => {
                    const option = new Option(sub.nome, sub.id);
                    if (sub.id == selectedSubcategoriaId) {
                        option.selected = true;
                         // console.log(`   Pré-selecionando Subcategoria: ${sub.id} (${sub.nome})`); // DEBUG
                    }
                    subcategoriaSelect.appendChild(option);
                });
                subcategoriaSelect.disabled = false; // Habilita
            } else {
                 resetSelect(subcategoriaSelect, '-- Nenhuma Subcategoria --'); // Nenhuma encontrada
            }
        } else {
             // Mantém resetado se categoriaId for nulo ou não houver subcategorias nos dados
             resetSelect(subcategoriaSelect, '-- Selecione Categoria --');
        }
    }

    // Adicionar Event Listeners (verifica se os elementos existem)
    if (secaoSelect) {
        secaoSelect.addEventListener('change', popularCategorias);
    } else {
         console.error('Elemento #secao_id não encontrado no DOM.'); // Erro crítico
    }

    if (categoriaSelect) {
        categoriaSelect.addEventListener('change', popularSubcategorias);
    } else {
         console.error('Elemento #categoria_id não encontrado no DOM.'); // Erro crítico
    }

    // Inicialização na Carga da Página
    // Só chama popularCategorias se uma seção estiver pré-selecionada E o elemento existir
    if (selectedSecaoId && secaoSelect && secaoSelect.value == selectedSecaoId) {
         // console.log('Iniciando: Seção pré-selecionada. Populando categorias...'); // DEBUG
        popularCategorias();
    } else {
        // Garante estado inicial correto se nenhuma seção estiver selecionada
        resetSelect(categoriaSelect, '-- Selecione Seção --');
        resetSelect(subcategoriaSelect, '-- Selecione Categoria --');
    }

}); // Fim do $(document).ready()
</script>