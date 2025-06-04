<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
// Incluir AMBOS os modelos: Categoria e Secao (para buscar as seções para o select)
require_once __DIR__ . '/../../models/Categoria.php'; 
require_once __DIR__ . '/../../models/Secao.php'; 

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

// Instanciar Categoria (para salvar) e Secao (para listar no select)
$categoria = new Categoria($db);
$secao = new Secao($db);

// Buscar todas as seções disponíveis para preencher o <select>
$stmtSecoes = $secao->listar(); // Usa o método listar da classe Secao
$listaSecoes = $stmtSecoes->fetchAll(PDO::FETCH_ASSOC); // Pega todas as seções como array

$error = null; // Variável para erros do formulário

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos obrigatórios (nome e secao_id)
    if (empty($_POST['nome'])) {
        $error = "O campo 'Nome da Categoria' é obrigatório.";
    } elseif (empty($_POST['secao_id'])) {
        $error = "Você deve selecionar uma 'Seção'.";
    } else {
        // Atribuir valores do formulário
        $categoria->nome = $_POST['nome'];
        $categoria->secao_id = $_POST['secao_id']; // Pega o ID da seção selecionada
        $categoria->descricao = $_POST['descricao'] ?? ''; 

        // Tentar criar a categoria
        if ($categoria->criar()) {
            $_SESSION['message'] = "Categoria '" . htmlspecialchars($categoria->nome) . "' criada com sucesso!";
            redirect('views/categorias/index.php'); // Volta para a lista de categorias
        } else {
            $error = "Erro ao salvar a categoria. Verifique os dados ou tente novamente.";
        }
    }
}
// --- FIM DO PROCESSAMENTO ---

// Configurar título da página
$page_title = "Adicionar Nova Categoria";

// Incluir cabeçalho e sidebar
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

                        <!-- Campo Seção (SELECT - Obrigatório) -->
                        <div class="form-group">
                            <label for="secao_id">Seção <span class="text-danger">*</span></label>
                            <select class="form-control" id="secao_id" name="secao_id" required>
                                <option value="">-- Selecione uma Seção --</option>
                                <?php foreach ($listaSecoes as $itemSecao): ?>
                                    <option value="<?php echo htmlspecialchars($itemSecao['id']); ?>" 
                                        <?php 
                                        // Mantém a seção selecionada se houver erro no envio
                                        if (isset($_POST['secao_id']) && $_POST['secao_id'] == $itemSecao['id']) {
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
                         <button type="submit" class="btn btn-primary">Salvar Categoria</button>
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