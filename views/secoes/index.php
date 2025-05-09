<?php
// Incluir arquivos essenciais
// Usar __DIR__ garante que os caminhos funcionem independentemente de onde o script é chamado
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Secao.php'; // Inclui a classe Secao que acabamos de criar

// Verificar se o usuário está logado e tem acesso (Opcional, mas recomendado para áreas admin)
// Descomente se quiser restringir o acesso apenas a admins ou usuários logados
/*
if (!isLoggedIn()) {
    redirect('views/usuarios/login.php'); 
}
if (!hasAccess('admin')) { // Exemplo: apenas admin pode ver/gerenciar seções
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php'); // Ou redirecionar para outra página
}
*/

// Configurar o título da página
$page_title = "Gerenciar Seções";

// Criar conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Instanciar o objeto Secao
$secao = new Secao($db);

// Obter todas as seções chamando o método listar()
$stmt = $secao->listar();
$num = $stmt->rowCount(); // Contar quantas seções foram encontradas

// Incluir o cabeçalho da página (HTML, CSS, etc.)
include_once __DIR__ . '/../includes/header.php'; 
// Incluir a barra lateral (Menu)
include_once __DIR__ . '/../includes/sidebar.php'; 
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
                    <!-- Breadcrumb (opcional, pode ajustar os links) -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Seções</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Exibir mensagens de sucesso ou erro vindas da sessão -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['message']); // Limpa a mensagem após exibir ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['error_message']); // Limpa a mensagem após exibir ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Lista de Seções Cadastradas</h3>
                    <div class="card-tools">
                        <!-- Botão para adicionar nova seção -->
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Seção
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="tabelaSecoes" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Ações</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($num > 0): // Se encontrou seções no banco ?>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php extract($row); // Extrai as variáveis ($id, $nome, $descricao) ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($id); ?></td>
                                        <td><?php echo htmlspecialchars($nome); ?></td>
                                        <td><?php echo htmlspecialchars($descricao); ?></td>
                                        <td>
                                            <!-- Botão Editar -->
                                            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-info btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Botão Excluir -->
                                            <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta seção? \nATENÇÃO: Isso não será possível se houver categorias vinculadas a ela.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <!-- Você pode adicionar um botão 'Visualizar' se quiser uma página de detalhes -->
                                            <!-- <a href="view.php?id=<?php //echo $id; ?>" class="btn btn-secondary btn-sm" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a> -->
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: // Se não encontrou nenhuma seção ?>
                                <tr>
                                    <td colspan="4" class="text-center">Nenhuma seção cadastrada ainda.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
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
// Incluir o rodapé da página (HTML, Javascripts)
include_once __DIR__ . '/../includes/footer.php'; 
?>

<!-- Adicionar aqui quaisquer scripts JS específicos para esta página, se necessário -->
<!-- Exemplo: Inicializar DataTables se estiver usando -->
<!-- 
<script>
  $(function () {
    $("#tabelaSecoes").DataTable({
      "responsive": true, "lengthChange": false, "autoWidth": false,
      "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#tabelaSecoes_wrapper .col-md-6:eq(0)');
  });
</script> 
-->