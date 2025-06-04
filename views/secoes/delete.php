<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Secao.php'; // Inclui a classe Secao

// Verificar se o usuário está logado e tem acesso (Opcional, mas recomendado)
/*
if (!isLoggedIn()) {
    redirect('views/usuarios/login.php'); 
}
if (!hasAccess('admin')) { 
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php');
}
*/

// 1. OBTER O ID DA URL E VALIDAR
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID da seção não fornecido para exclusão.";
    redirect('views/secoes/index.php'); 
}
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
     $_SESSION['error_message'] = "ID da seção inválido.";
     redirect('views/secoes/index.php');
}

// Criar conexão e instanciar Secao
$database = new Database();
$db = $database->getConnection();
$secao = new Secao($db);
$secao->id = $id;

// --- PROCESSAMENTO DA EXCLUSÃO (QUANDO O BOTÃO CONFIRMAR É CLICADO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tentar excluir a seção
    if ($secao->excluir()) {
        // Sucesso na exclusão
        $_SESSION['message'] = "Seção excluída com sucesso!";
    } else {
        // Falha na exclusão
        // Verificar se a falha foi por causa de categorias vinculadas (se o método temCategoriasVinculadas foi chamado internamente e retornou true)
        // Precisamos buscar o nome da seção para a mensagem de erro
        $secao->buscarPorId(); // Busca os dados só para pegar o nome
        $nomeSecao = $secao->nome ? htmlspecialchars($secao->nome) : "ID {$id}";

        // A mensagem de erro pode ser mais específica se soubermos o motivo
        // O método excluir() já tem a lógica de não excluir se tiver categorias.
        // Podemos assumir que essa é a causa mais provável de falha controlada.
        $_SESSION['error_message'] = "Não foi possível excluir a seção '{$nomeSecao}'. Verifique se existem categorias vinculadas a ela.";
        // Se fosse um erro de banco inesperado, a mensagem poderia ser outra.
    }
    // Redirecionar de volta para a lista em ambos os casos (sucesso ou falha controlada)
    redirect('views/secoes/index.php');
}
// --- FIM DO PROCESSAMENTO DA EXCLUSÃO ---

// --- EXIBIÇÃO DA PÁGINA DE CONFIRMAÇÃO (QUANDO ACESSADO VIA GET) ---

// Buscar os dados da seção para mostrar na confirmação
if (!$secao->buscarPorId()) {
    $_SESSION['error_message'] = "Seção com ID {$id} não encontrada para exclusão.";
    redirect('views/secoes/index.php'); 
}

// Configurar o título da página
$page_title = "Confirmar Exclusão da Seção";

// Incluir cabeçalho e sidebar
include_once __DIR__ . '/../includes/header.php'; 

?>

<!-- Content Wrapper. Contém o conteúdo da página -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/secoes/index.php">Seções</a></li>
                        <li class="breadcrumb-item active">Excluir</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-danger"> <!-- Usando card-danger para alerta -->
                <div class="card-header">
                    <h3 class="card-title">Atenção!</h3>
                </div>
                <div class="card-body">
                    <p class="lead">Você tem certeza que deseja excluir permanentemente a seção abaixo?</p>
                    <ul>
                        <li><strong>ID:</strong> <?php echo htmlspecialchars($secao->id); ?></li>
                        <li><strong>Nome:</strong> <?php echo htmlspecialchars($secao->nome); ?></li>
                        <li><strong>Descrição:</strong> <?php echo htmlspecialchars($secao->descricao); ?></li>
                    </ul>
                    <p class="text-danger font-weight-bold">
                        Esta ação não pode ser desfeita. A exclusão só será permitida se não houver categorias vinculadas a esta seção.
                    </p>
                    
                    <!-- Formulário que envia o POST para confirmar a exclusão -->
                    <form method="POST" action="delete.php?id=<?php echo $secao->id; ?>"> 
                        <!-- Action aponta para ele mesmo, incluindo o ID -->
                        <div class="text-right">
                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                        </div>
                    </form>

                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php 
// Incluir o rodapé da página
include_once __DIR__ . '/../includes/footer.php'; 
?>