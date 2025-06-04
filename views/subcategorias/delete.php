<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Subcategoria.php'; // Precisa da Subcategoria

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
    $_SESSION['error_message'] = "ID da subcategoria não fornecido para exclusão.";
    redirect('views/subcategorias/index.php');
}
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
     $_SESSION['error_message'] = "ID da subcategoria inválido.";
     redirect('views/subcategorias/index.php');
}

// Conexão e Instância
$database = new Database();
$db = $database->getConnection();
$subcategoria = new Subcategoria($db);
$subcategoria->id = $id;

// --- PROCESSAMENTO DA EXCLUSÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tentar excluir a subcategoria
    if ($subcategoria->excluir()) {
        $_SESSION['message'] = "Subcategoria excluída com sucesso!";
    } else {
        // Falha - buscar o nome para a mensagem e assumir que foi por causa de produtos
        $subcategoria->buscarPorId(); // Tenta buscar os dados só pra pegar o nome
        $nomeSubcategoria = $subcategoria->nome ? htmlspecialchars($subcategoria->nome) : "ID {$id}";
        $_SESSION['error_message'] = "Não foi possível excluir a subcategoria '{$nomeSubcategoria}'. Verifique se existem produtos vinculados a ela.";
    }
    // Redirecionar para a lista
    redirect('views/subcategorias/index.php');
}
// --- FIM DO PROCESSAMENTO ---

// --- EXIBIÇÃO DA PÁGINA DE CONFIRMAÇÃO (GET) ---

// Buscar dados da subcategoria para mostrar na confirmação
// O buscarPorId() já traz categoria e seção
if (!$subcategoria->buscarPorId()) {
    $_SESSION['error_message'] = "Subcategoria com ID {$id} não encontrada para exclusão.";
    redirect('views/subcategorias/index.php');
}

// Título
$page_title = "Confirmar Exclusão da Subcategoria";

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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/subcategorias/index.php">Subcategorias</a></li>
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
                    <p class="lead">Você tem certeza que deseja excluir permanentemente a subcategoria abaixo?</p>
                    <ul>
                        <li><strong>ID:</strong> <?php echo htmlspecialchars($subcategoria->id); ?></li>
                        <li><strong>Nome:</strong> <?php echo htmlspecialchars($subcategoria->nome); ?></li>
                        <li><strong>Categoria:</strong> <?php echo htmlspecialchars($subcategoria->categoria_nome); ?> (ID: <?php echo htmlspecialchars($subcategoria->categoria_id); ?>)</li>
                        <li><strong>Seção:</strong> <?php echo htmlspecialchars($subcategoria->secao_nome); ?> (ID: <?php echo htmlspecialchars($subcategoria->secao_id); ?>)</li>
                        <li><strong>Descrição:</strong> <?php echo htmlspecialchars($subcategoria->descricao); ?></li>
                    </ul>
                    <p class="text-danger font-weight-bold">
                        Esta ação não pode ser desfeita. A exclusão só será permitida se não houver produtos vinculados a esta subcategoria.
                    </p>

                    <!-- Formulário de confirmação -->
                    <form method="POST" action="delete.php?id=<?php echo $subcategoria->id; ?>">
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