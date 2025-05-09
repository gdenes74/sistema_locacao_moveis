<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
// Incluir AMBOS os modelos: Subcategoria (para salvar) e Categoria (para o select)
require_once __DIR__ . '/../../models/Subcategoria.php';
require_once __DIR__ . '/../../models/Categoria.php';

// Verificar acesso (Opcional)
/*
if (!isLoggedIn()) { redirect('views/usuarios/login.php'); }
if (!hasAccess('admin')) {
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php');
}
*/

// Conexão com o banco
$database = new Database();
$db = $database->getConnection();

// Instanciar Subcategoria (para salvar) e Categoria (para listar no select)
$subcategoria = new Subcategoria($db);
$categoria = new Categoria($db); // Usaremos para buscar as categorias

// Buscar todas as categorias disponíveis para preencher o <select>
// Queremos listar as categorias incluindo o nome da seção para melhor organização no select (opcional, mas ajuda)
// Vamos usar o método listar() da Categoria que já faz o JOIN com Secao
$stmtCategorias = $categoria->listar();
$listaCategorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC); // Pega todas como array

$error = null; // Variável para erros do formulário

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos obrigatórios (nome e categoria_id)
    if (empty($_POST['nome'])) {
        $error = "O campo 'Nome da Subcategoria' é obrigatório.";
    } elseif (empty($_POST['categoria_id'])) {
        $error = "Você deve selecionar uma 'Categoria'.";
    } else {
        // Atribuir valores do formulário
        $subcategoria->nome = $_POST['nome'];
        $subcategoria->categoria_id = $_POST['categoria_id']; // Pega o ID da categoria selecionada
        $subcategoria->descricao = $_POST['descricao'] ?? '';

        // Tentar criar a subcategoria
        if ($subcategoria->criar()) {
            $_SESSION['message'] = "Subcategoria '" . htmlspecialchars($subcategoria->nome) . "' criada com sucesso!";
            redirect('views/subcategorias/index.php'); // Volta para a lista de subcategorias
        } else {
            $error = "Erro ao salvar a subcategoria. Verifique os dados ou tente novamente.";
        }
    }
}
// --- FIM DO PROCESSAMENTO ---

// Configurar título da página
$page_title = "Adicionar Nova Subcategoria";

// Incluir cabeçalho e sidebar
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="col-sm-6">
                    <!-- Breadcrumb -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/subcategorias/index.php">Subcategorias</a></li>
                        <li class="breadcrumb-item active">Adicionar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Formulário de Cadastro</h3>
                </div>
                <!-- Formulário -->
                <form method="POST" action="create.php">
                    <div class="card-body">
                        <!-- Mensagem de erro -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                        <?php endif; ?>

                        <!-- Campo Categoria (SELECT - Obrigatório) -->
                        <div class="form-group">
                            <label for="categoria_id">Categoria <span class="text-danger">*</span></label>
                            <select class="form-control select2" id="categoria_id" name="categoria_id" required style="width: 100%;"> <!-- Adicionado style e class select2 (opcional) -->
                                <option value="">-- Selecione uma Categoria --</option>
                                <?php
                                // Agrupar categorias por seção para melhor visualização (opcional)
                                $categoriasAgrupadas = [];
                                foreach ($listaCategorias as $itemCategoria) {
                                    $categoriasAgrupadas[$itemCategoria['secao_nome']][] = $itemCategoria;
                                }

                                foreach ($categoriasAgrupadas as $nomeSecao => $categoriasDaSecao):
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($nomeSecao); ?>">
                                        <?php foreach ($categoriasDaSecao as $itemCategoria): ?>
                                            <option value="<?php echo htmlspecialchars($itemCategoria['id']); ?>"
                                                <?php
                                                // Mantém a categoria selecionada se houver erro no envio
                                                if (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $itemCategoria['id']) {
                                                    echo 'selected';
                                                }
                                                ?>
                                            >
                                                <?php echo htmlspecialchars($itemCategoria['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($listaCategorias)): ?>
                                <small class="form-text text-danger">Nenhuma categoria encontrada. Por favor, <a href="../categorias/create.php">cadastre uma categoria</a> primeiro.</small>
                            <?php endif; ?>
                        </div>

                        <!-- Campo Nome da Subcategoria (Obrigatório) -->
                        <div class="form-group">
                            <label for="nome">Nome da Subcategoria <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="nome"
                                name="nome"
                                placeholder="Digite o nome da subcategoria"
                                required
                                value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>"
                            >
                        </div>

                        <!-- Campo Descrição (Opcional) -->
                        <div class="form-group">
                            <label for="descricao">Descrição</label>
                            <textarea
                                class="form-control"
                                id="descricao"
                                name="descricao"
                                rows="3"
                                placeholder="Digite uma breve descrição (opcional)"
                            ><?php echo isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : ''; ?></textarea>
                        </div>

                    </div>
                    <!-- /.card-body -->

                    <div class="card-footer text-right">
                         <a href="index.php" class="btn btn-secondary">Cancelar</a>
                         <button type="submit" class="btn btn-primary">Salvar Subcategoria</button>
                    </div>
                </form>
            </div>
            <!-- /.card -->
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php
// Incluir o rodapé
include_once __DIR__ . '/../includes/footer.php';
?>

<!-- Opcional: Inicializar Select2 se você incluiu a classe CSS/JS -->
<!--
<script>
$(function () {
  //Initialize Select2 Elements
  $('.select2').select2({
      theme: 'bootstrap4' // Ou outro tema se estiver usando
  })
});
</script>
-->