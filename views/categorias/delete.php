<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Categoria.php'; // Precisa da Categoria para excluir e buscar dados

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
    $_SESSION['error_message'] = "ID da categoria não fornecido para exclusão.";
    redirect('views/categorias/index.php'); 
}
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
     $_SESSION['error_message'] = "ID da categoria inválido.";
     redirect('views/categorias/index.php');
}

// Conexão e Instância
$database = new Database();
$db = $database->getConnection();
$categoria = new Categoria($db);
$categoria->id = $id;

// --- PROCESSAMENTO DA EXCLUSÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tentar excluir a categoria
    if ($categoria->excluir()) {
        $_SESSION['message'] = "Categoria excluída com sucesso!";
    } else {
        // Falha - buscar o nome para a mensagem e assumir que foi por causa de subcategorias
        $categoria->buscarPorId(); 
        $nomeCategoria = $categoria->nome ? htmlspecialchars($categoria->nome) : "ID {$id}";
        $_SESSION['error_message'] = "Não foi possível excluir a categoria '{$nomeCategoria}'. Verifique se existem subcategorias vinculadas a ela.";
    }
    // Redirecionar para a lista
    redirect('views/categorias/index.php');
}
// --- FIM DO PROCESSAMENTO ---

// --- EXIBIÇÃO DA PÁGINA DE CONFIRMAÇÃO (GET) ---

// Buscar dados da categoria para mostrar na confirmação
// Inclui o JOIN para pegar o nome da seção também (embora não seja estritamente necessário para a exclusão, é bom para mostrar informação completa)
if (!$categoria->buscarPorId()) {
    $_SESSION['error_message'] = "Categoria com ID {$id} não encontrada para exclusão.";
    redirect('views/categorias/index.php'); 
}

// Título
$page_title = "Confirmar Exclusão da Categoria";

// Includes
include_once __DIR__ . '/../includes/header.php'; 
 
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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/categorias/index.php">Categorias</a></li>
                        <li class="breadcrumb-item active">Excluir</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-danger"> <!-- Card de perigo -->
                <div class="card-header">
                    <h3 class="card-title">Atenção!</h3>
                </div>
                <div class="card-body">
                    <p class="lead">Você tem certeza que deseja excluir permanentemente a categoria abaixo?</p>
                    <ul>
                        <li><strong>ID:</strong> <?php echo htmlspecialchars($categoria->id); ?></li>
                        <li><strong>Nome:</strong> <?php echo htmlspecialchars($categoria->nome); ?></li>
                        <li><strong>Seção:</strong> <?php echo htmlspecialchars($categoria->secao_nome); ?> (ID: <?php echo htmlspecialchars($categoria->secao_id); ?>)</li> 
                        <!-- Mostra a seção também -->
                        <li><strong>Descrição:</strong> <?php echo htmlspecialchars($categoria->descricao); ?></li>
                    </ul>
                    <p class="text-danger font-weight-bold">
                        Esta ação não pode ser desfeita. A exclusão só será permitida se não houver subcategorias vinculadas a esta categoria.
                    </p>
                    
                    <!-- Formulário de confirmação -->
                    <form method="POST" action="delete.php?id=<?php echo $categoria->id; ?>"> 
                        <div class="text-right">
                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                        </div>
                    </form>

                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php 
// Rodapé
include_once __DIR__ . '/../includes/footer.php'; 
?>