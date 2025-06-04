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

// Criar conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Instanciar o objeto Secao
$secao = new Secao($db);

$error = null; // Variável para armazenar mensagens de erro do formulário

// --- PROCESSAMENTO DO FORMULÁRIO QUANDO ENVIADO (MÉTODO POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se os campos obrigatórios foram enviados
    if (empty($_POST['nome'])) {
        $error = "O campo 'Nome' é obrigatório.";
    } else {
        // Atribuir os valores do formulário às propriedades do objeto Secao
        $secao->nome = $_POST['nome'];
        // Descrição é opcional, então pegamos mesmo que esteja vazia
        $secao->descricao = $_POST['descricao'] ?? ''; 

        // Tentar criar a seção no banco de dados
        if ($secao->criar()) {
            // Se deu certo:
            // 1. Definir uma mensagem de sucesso na sessão
            $_SESSION['message'] = "Seção '" . htmlspecialchars($secao->nome) . "' criada com sucesso!";
            // 2. Redirecionar de volta para a página de listagem (index.php)
            redirect('views/secoes/index.php');
        } else {
            // Se deu erro ao salvar no banco:
            $error = "Erro ao salvar a seção no banco de dados. Verifique se já não existe uma seção com este nome ou tente novamente.";
        }
    }
}
// --- FIM DO PROCESSAMENTO DO FORMULÁRIO ---

// Configurar o título da página (para a tag <title> e o cabeçalho H1)
$page_title = "Adicionar Nova Seção";

// Incluir o cabeçalho da página
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
                        <li class="breadcrumb-item active">Adicionar</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary"> <!-- Usando card-primary para destaque -->
                <div class="card-header">
                    <h3 class="card-title">Formulário de Cadastro</h3>
                </div>
                <!-- /.card-header -->
                <!-- Formulário HTML para criar a seção -->
                <form method="POST" action="create.php"> <!-- O action aponta para ele mesmo -->
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
                                value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" 
                            >
                            <!-- O 'value' preenche o campo com o valor anterior se der erro no envio -->
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
                             <!-- O conteúdo da textarea também é preenchido se der erro -->
                        </div>

                    </div>
                    <!-- /.card-body -->

                    <div class="card-footer text-right">
                         <a href="index.php" class="btn btn-secondary">Cancelar</a>
                         <button type="submit" class="btn btn-primary">Salvar Seção</button>
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