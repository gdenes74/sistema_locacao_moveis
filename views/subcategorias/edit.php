<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Subcategoria.php'; // Para buscar/atualizar a subcategoria
require_once __DIR__ . '/../../models/Categoria.php';   // Para listar categorias no select

// Verificar acesso (Opcional)
/*
if (!isLoggedIn()) { redirect('views/usuarios/login.php'); }
if (!hasAccess('admin')) {
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php');
}
*/

// 1. OBTER E VALIDAR O ID DA SUBCATEGORIA DA URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID da subcategoria não fornecido para edição.";
    redirect('views/subcategorias/index.php');
}
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
     $_SESSION['error_message'] = "ID da subcategoria inválido.";
     redirect('views/subcategorias/index.php');
}

// Conexão e Instâncias
$database = new Database();
$db = $database->getConnection();
$subcategoria = new Subcategoria($db);
$categoria = new Categoria($db); // Para listar categorias

$subcategoria->id = $id; // Define o ID na subcategoria a ser editada

// 2. BUSCAR OS DADOS ATUAIS DA SUBCATEGORIA
if (!$subcategoria->buscarPorId()) {
    $_SESSION['error_message'] = "Subcategoria com ID {$id} não encontrada.";
    redirect('views/subcategorias/index.php');
}
// Se encontrou, $subcategoria->nome, $subcategoria->descricao, $subcategoria->categoria_id estão preenchidos

// 3. BUSCAR TODAS AS CATEGORIAS PARA O SELECT (com nome da seção para agrupar)
$stmtCategorias = $categoria->listar();
$listaCategorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

$error = null; // Erros do formulário

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos obrigatórios
    if (empty($_POST['nome'])) {
        $error = "O campo 'Nome da Subcategoria' é obrigatório.";
    } elseif (empty($_POST['categoria_id'])) {
        $error = "Você deve selecionar uma 'Categoria'.";
    } else {
        // Atribuir os NOVOS valores do formulário ao objeto subcategoria
        // O ID já está definido
        $subcategoria->nome = $_POST['nome'];
        $subcategoria->categoria_id = $_POST['categoria_id']; // Pega o ID da categoria selecionada no POST
        $subcategoria->descricao = $_POST['descricao'] ?? '';

        // Tentar ATUALIZAR a subcategoria
        if ($subcategoria->atualizar()) {
            $_SESSION['message'] = "Subcategoria '" . htmlspecialchars($subcategoria->nome) . "' atualizada com sucesso!";
            redirect('views/subcategorias/index.php');
        } else {
            $error = "Erro ao atualizar a subcategoria. Verifique os dados ou tente novamente.";
            // Mantém os dados POSTADOS no formulário para o usuário corrigir
             $subcategoria->nome = $_POST['nome'];
             $subcategoria->descricao = $_POST['descricao'];
             $subcategoria->categoria_id = $_POST['categoria_id']; // Mantém a categoria selecionada no erro
        }
    }
}
// --- FIM DO PROCESSAMENTO ---

// Configurar título
$page_title = "Editar Subcategoria";

// Includes de layout
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
                    <h1><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($subcategoria->nome); ?></h1>
                    <!-- Mostra o nome atual no título -->
                </div>
                <div class="col-sm-6">
                    <!-- Breadcrumb -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/subcategorias/index.php">Subcategorias</a></li>
                        <li class="breadcrumb-item active">Editar</li>
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
                    <h3 class="card-title">Editar Dados da Subcategoria</h3>
                </div>
                <!-- Formulário -->
                <form method="POST" action="edit.php?id=<?php echo $subcategoria->id; ?>">
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
                            <select class="form-control select2" id="categoria_id" name="categoria_id" required style="width: 100%;">
                                <option value="">-- Selecione uma Categoria --</option>
                                <?php
                                // Agrupar categorias por seção
                                $categoriasAgrupadas = [];
                                foreach ($listaCategorias as $itemCat) { // Usei $itemCat para não conflitar com a instância $categoria
                                    $categoriasAgrupadas[$itemCat['secao_nome']][] = $itemCat;
                                }

                                foreach ($categoriasAgrupadas as $nomeSecao => $categoriasDaSecao):
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($nomeSecao); ?>">
                                        <?php foreach ($categoriasDaSecao as $itemCat): ?>
                                            <option value="<?php echo htmlspecialchars($itemCat['id']); ?>"
                                                <?php
                                                // Seleciona a categoria ATUAL da subcategoria OU a selecionada se deu erro
                                                $categoriaSelecionadaId = isset($_POST['categoria_id']) ? $_POST['categoria_id'] : $subcategoria->categoria_id;
                                                if ($categoriaSelecionadaId == $itemCat['id']) {
                                                    echo 'selected';
                                                }
                                                ?>
                                            >
                                                <?php echo htmlspecialchars($itemCat['nome']); ?>
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
                                value="<?php echo htmlspecialchars($subcategoria->nome); ?>"
                                <!-- O value vem do banco de dados -->
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
                            ><?php echo htmlspecialchars($subcategoria->descricao); ?></textarea>
                             <!-- O conteúdo vem do banco de dados -->
                        </div>

                    </div>
                    <!-- /.card-body -->

                    <div class="card-footer text-right">
                         <a href="index.php" class="btn btn-secondary">Cancelar</a>
                         <button type="submit" class="btn btn-primary">Salvar Alterações</button>
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

<!-- Opcional: Inicializar Select2 -->
<!--
<script>
$(function () {
  $('.select2').select2({ theme: 'bootstrap4' });
});
</script>
-->