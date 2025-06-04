<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Categoria.php'; // Inclui a classe Categoria

// Verificar acesso (Opcional)
/*
if (!isLoggedIn()) { redirect('views/usuarios/login.php'); }
if (!hasAccess('admin')) { 
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php');
}
*/

// Configurar o título da página
$page_title = "Gerenciar Categorias";

// Conexão com o banco
$database = new Database();
$db = $database->getConnection();

// Instanciar Categoria
$categoria = new Categoria($db);

// Obter todas as categorias (o método listar() já faz o JOIN com secoes)
$stmt = $categoria->listar();
$num = $stmt->rowCount();

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
                        <li class="breadcrumb-item active">Categorias</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Mensagens de sessão -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Lista de Categorias Cadastradas</h3>
                    <div class="card-tools">
                        <!-- Botão para adicionar nova categoria -->
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Categoria
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="tabelaCategorias" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome da Categoria</th>
                                <th>Seção</th> <!-- Coluna para mostrar a seção -->
                                <th>Descrição</th>
                                <th>Ações</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($num > 0): ?>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php extract($row); // Extrai $id, $nome, $secao_id, $secao_nome, $descricao ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($id); ?></td>
                                        <td><?php echo htmlspecialchars($nome); ?></td>
                                        <td><?php echo htmlspecialchars($secao_nome); ?> (ID: <?php echo htmlspecialchars($secao_id); ?>)</td> 
                                        <!-- Exibe o nome da seção (vindo do JOIN) e o ID dela -->
                                        <td><?php echo htmlspecialchars($descricao); ?></td>
                                        <td>
                                            <!-- Botão Editar -->
                                            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-info btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Botão Excluir -->
                                            <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta categoria? \nATENÇÃO: Isso não será possível se houver subcategorias vinculadas a ela.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Nenhuma categoria cadastrada ainda.</td> 
                                    <!-- Colspan atualizado para 5 colunas -->
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>ID</th>
                                <th>Nome da Categoria</th>
                                <th>Seção</th>
                                <th>Descrição</th>
                                <th>Ações</th>
                            </tr>
                        </tfoot>
                    </table>
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
// Incluir o rodapé
include_once __DIR__ . '/../includes/footer.php'; 
?>

<!-- Adicionar JS específico, como DataTables, se necessário -->
<!-- 
<script>
  $(function () {
    $("#tabelaCategorias").DataTable({ /* opções */ });
  });
</script> 
-->