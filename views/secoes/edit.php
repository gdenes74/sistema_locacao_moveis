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
// Verifica se o 'id' foi passado na URL e se não está vazio
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID da seção não fornecido para edição.";
    redirect('views/secoes/index.php'); // Volta para a lista se não houver ID
}
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT); // Pega o ID e valida se é um inteiro
if ($id === false) {
     $_SESSION['error_message'] = "ID da seção inválido.";
     redirect('views/secoes/index.php');
}

// Criar conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Instanciar o objeto Secao
$secao = new Secao($db);
$secao->id = $id; // Define o ID no objeto seção

// 2. BUSCAR OS DADOS DA SEÇÃO ATUAL
// Tenta buscar a seção pelo ID fornecido
if (!$secao->buscarPorId()) {
    // Se não encontrar a seção com esse ID no banco
    $_SESSION['error_message'] = "Seção com ID {$id} não encontrada.";
    redirect('views/secoes/index.php'); // Volta para a lista
}
// Se chegou aqui, $secao->nome e $secao->descricao contêm os dados atuais

$error = null; // Variável para erros do formulário

// --- PROCESSAMENTO DO FORMULÁRIO QUANDO ENVIADO (MÉTODO POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se os campos obrigatórios foram enviados
    if (empty($_POST['nome'])) {
        $error = "O campo 'Nome' é obrigatório.";
    } else {
        // Atribuir os NOVOS valores do formulário às propriedades do objeto Secao
        // O ID já está definido no objeto $secao desde o início
        $secao->nome = $_POST['nome'];
        $secao->descricao = $_POST['descricao'] ?? ''; 

        // Tentar ATUALIZAR a seção no banco de dados
        if ($secao->atualizar()) {
            // Se deu certo:
            $_SESSION['message'] = "Seção '" . htmlspecialchars($secao->nome) . "' atualizada com sucesso!";
            redirect('views/secoes/index.php'); // Volta para a lista
        } else {
            // Se deu erro ao atualizar no banco:
            $error = "Erro ao atualizar a seção. Verifique os dados ou tente novamente.";
            // Mantém os dados POSTADOS no formulário para o usuário corrigir
             $secao->nome = $_POST['nome']; // Garante que o nome digitado permaneça no form
             $secao->descricao = $_POST['descricao']; // Garante que a descrição digitada permaneça no form
        }
    }
}
// --- FIM DO PROCESSAMENTO DO FORMULÁRIO ---

// Configurar o título da página
$page_title = "Editar Seção";

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
                    <h1><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($secao->nome); ?></h1> 
                    <!-- Mostra o nome atual da seção no título -->
                </div>
                <div class="col-sm-6">
                    <!-- Breadcrumb -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/secoes/index.php">Seções</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Editar Dados da Seção</h3>
                </div>
                <!-- Formulário HTML para editar a seção -->
                <form method="POST" action="edit.php?id=<?php echo $secao->id; ?>"> 
                <!-- Action aponta para ele mesmo, incluindo o ID na URL -->
                    <div class="card-body">
                        <!-- Exibir mensagem de erro, se houver -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Campo Nome (Obrigatório) -->
                        <div class="form-group">
                            <label for="nome">Nome da Seção <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="nome" 
                                name="nome" 
                                placeholder="Digite o nome da seção"
                                required 
                                value="<?php echo htmlspecialchars($secao->nome); ?>" 
                                <!-- O 'value' é preenchido com o dado atual vindo do banco -->
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
                            ><?php echo htmlspecialchars($secao->descricao); ?></textarea>
                             <!-- O conteúdo da textarea também é preenchido com o dado atual -->
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
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php 
// Incluir o rodapé da página
include_once __DIR__ . '/../includes/footer.php'; 
?>