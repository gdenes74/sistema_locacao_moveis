<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Categoria.php'; 
require_once __DIR__ . '/../../models/Secao.php'; // Precisamos das seções novamente

// Verificar acesso (Opcional)
/*
if (!isLoggedIn()) { redirect('views/usuarios/login.php'); }
if (!hasAccess('admin')) { 
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php');
}
*/

// 1. OBTER E VALIDAR O ID DA CATEGORIA DA URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID da categoria não fornecido para edição.";
    redirect('views/categorias/index.php'); 
}
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
     $_SESSION['error_message'] = "ID da categoria inválido.";
     redirect('views/categorias/index.php');
}

// Conexão e Instâncias
$database = new Database();
$db = $database->getConnection();
$categoria = new Categoria($db);
$secao = new Secao($db); // Para listar seções no select

$categoria->id = $id; // Define o ID na categoria a ser editada

// 2. BUSCAR OS DADOS ATUAIS DA CATEGORIA
if (!$categoria->buscarPorId()) {
    $_SESSION['error_message'] = "Categoria com ID {$id} não encontrada.";
    redirect('views/categorias/index.php'); 
}
// Se encontrou, $categoria->nome, $categoria->descricao, $categoria->secao_id estão preenchidos

// 3. BUSCAR TODAS AS SEÇÕES PARA O SELECT
$stmtSecoes = $secao->listar();
$listaSecoes = $stmtSecoes->fetchAll(PDO::FETCH_ASSOC);

$error = null; // Erros do formulário

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos obrigatórios
    if (empty($_POST['nome'])) {
        $error = "O campo 'Nome da Categoria' é obrigatório.";
    } elseif (empty($_POST['secao_id'])) {
        $error = "Você deve selecionar uma 'Seção'.";
    } else {
        // Atribuir os NOVOS valores do formulário ao objeto categoria
        // O ID já está definido
        $categoria->nome = $_POST['nome'];
        $categoria->secao_id = $_POST['secao_id']; // Pega o ID da seção selecionada no POST
        $categoria->descricao = $_POST['descricao'] ?? ''; 

        // Tentar ATUALIZAR a categoria
        if ($categoria->atualizar()) {
            $_SESSION['message'] = "Categoria '" . htmlspecialchars($categoria->nome) . "' atualizada com sucesso!";
            redirect('views/categorias/index.php'); 
        } else {
            $error = "Erro ao atualizar a categoria. Verifique os dados ou tente novamente.";
            // Mantém os dados POSTADOS no formulário para o usuário corrigir
             $categoria->nome = $_POST['nome']; 
             $categoria->descricao = $_POST['descricao'];
             $categoria->secao_id = $_POST['secao_id']; // Mantém a seção selecionada no erro
        }
    }
}
// --- FIM DO PROCESSAMENTO ---

// Configurar título
$page_title = "Editar Categoria";

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
                    <h1><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($categoria->nome); ?></h1>
                </div>
                <div class="col-sm-6">
                    <!-- Breadcrumb -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/categorias/index.php">Categorias</a></li>
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
                    <h3 class="card-title">Editar Dados da Categoria</h3>
                </div>
                <!-- Formulário -->
                <form method="POST" action="edit.php?id=<?php echo $categoria->id; ?>"> 
                    <div class="card-body">
                        <!-- Mensagem de erro -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                        <?php endif; ?>

                        <!-- Campo Seção (SELECT - Obrigatório) -->
                        <div class="form-group">
                            <label for="secao_id">Seção <span class="text-danger">*</span></label>
                            <select class="form-control" id="secao_id" name="secao_id" required>
                                <option value="">-- Selecione uma Seção --</option>
                                <?php foreach ($listaSecoes as $itemSecao): ?>
                                    <option value="<?php echo htmlspecialchars($itemSecao['id']); ?>" 
                                        <?php 
                                        // Seleciona a seção ATUAL da categoria OU a seção selecionada se deu erro
                                        $secaoSelecionadaId = isset($_POST['secao_id']) ? $_POST['secao_id'] : $categoria->secao_id;
                                        if ($secaoSelecionadaId == $itemSecao['id']) {
                                            echo 'selected';
                                        } 
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($itemSecao['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                             <?php if (empty($listaSecoes)): ?>
                                <small class="form-text text-danger">Nenhuma seção encontrada. Por favor, <a href="../secoes/create.php">cadastre uma seção</a> primeiro.</small>
                            <?php endif; ?>
                        </div>

                        <!-- Campo Nome da Categoria (Obrigatório) -->
                        <div class="form-group">
                            <label for="nome">Nome da Categoria <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="nome" 
                                name="nome" 
                                placeholder="Digite o nome da categoria"
                                required 
                                value="<?php echo htmlspecialchars($categoria->nome); ?>" 
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
                            ><?php echo htmlspecialchars($categoria->descricao); ?></textarea>
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