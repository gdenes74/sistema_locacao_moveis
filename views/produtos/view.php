<?php
// Incluir arquivos essenciais
// Usar __DIR__ garante que os caminhos funcionem independentemente de onde o script é chamado
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php'; // Inclui a classe Produto

// Verificar se o usuário está logado e tem acesso (Opcional, mas recomendado)
/*
if (!isLoggedIn()) {
    redirect('views/usuarios/login.php');
}
if (!hasAccess('admin')) { // Exemplo: apenas admin pode ver/gerenciar produtos
     $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
     redirect('views/dashboard/dashboard.php'); // Ou redirecionar para outra página
}
*/

// Configurar o título da página
$page_title = "Gerenciar Produtos";

// Criar conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Instanciar o objeto Produto
$produto = new Produto($db);

// Obter todos os produtos chamando o método listarTodos()
$stmt = $produto->listarTodos("p.nome_produto ASC"); // Ordena por nome do produto

// Contar quantos produtos foram encontrados (se a consulta foi bem-sucedida)
$num = ($stmt) ? $stmt->rowCount() : 0;

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
                    <!-- Breadcrumb -->
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>views/dashboard/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Produtos</li>
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
                    <h3 class="card-title">Lista de Produtos Cadastrados</h3>
                    <div class="card-tools">
                        <!-- Botão para adicionar novo produto -->
                        <a href="create.php" class="btn btn-primary"> <!-- Mudado para btn-primary como em secoes -->
                            <i class="fas fa-plus"></i> Novo Produto
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <?php if (!$stmt && $num === 0): // Verifica se houve erro na consulta ?>
                         <div class='alert alert-danger'>Erro ao buscar produtos do banco de dados. Verifique os logs para mais detalhes.</div>
                    <?php else: ?>
                        <table id="tabelaProdutos" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Foto</th>
                                    <th>Código</th>
                                    <th>Nome</th>
                                    <th>Subcategoria</th>
                                    <th>Qtd. Disp.</th>
                                    <th>Preço Locação</th>
                                    <th>Preço Venda</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($num > 0): // Se encontrou produtos no banco ?>
                                    <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <?php extract($row); // Extrai as variáveis ($id, $nome_produto, etc.)

                                        // Lógica para exibir a foto (igual à versão anterior, mas dentro do padrão)
                                        $caminho_foto_completo = BASE_URL . UPLOAD_DIR_REL . ($foto_path ?? 'default_product.png'); // Caminho para tag <img>
                                        $caminho_fisico_foto = __DIR__ . '/../../' . UPLOAD_DIR_REL . ($foto_path ?? ''); // Caminho no servidor para file_exists
                                        $foto_existe = !empty($foto_path) && file_exists($caminho_fisico_foto);
                                        $placeholder = BASE_URL . 'assets/img/product_placeholder.png'; // Defina um placeholder padrão
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($id); ?></td>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($foto_existe ? $caminho_foto_completo : $placeholder); ?>"
                                                     alt="<?php echo htmlspecialchars($nome_produto); ?>"
                                                     style="width: 50px; height: auto; border-radius: 3px; object-fit: cover;">
                                            </td>
                                            <td><?php echo htmlspecialchars($codigo ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($nome_produto); ?></td>
                                            <td><?php echo htmlspecialchars($nome_subcategoria ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($quantidade_disponivel); ?></td>
                                            <td>R$ <?php echo number_format($preco_locacao ?? 0, 2, ',', '.'); ?></td>
                                            <td>R$ <?php echo number_format($preco_venda ?? 0, 2, ',', '.'); ?></td>
                                            <td>
                                                <!-- Botão Editar -->
                                                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-info btn-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- Botão Excluir -->
                                                <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este produto?\nATENÇÃO: Esta ação não pode ser desfeita e pode afetar registros relacionados.');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <!-- Opcional: Botão Visualizar -->
                                                <!-- <a href="show.php?id=<?php //echo $id; ?>" class="btn btn-secondary btn-sm" title="Visualizar"><i class="fas fa-eye"></i></a> -->
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: // Se não encontrou nenhum produto ?>
                                    <tr>
                                        <!-- Mensagem ocupa todas as colunas da tabela -->
                                        <td colspan="9" class="text-center">Nenhum produto cadastrado ainda. <a href="create.php">Cadastre o primeiro!</a></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>ID</th>
                                    <th>Foto</th>
                                    <th>Código</th>
                                    <th>Nome</th>
                                    <th>Subcategoria</th>
                                    <th>Qtd. Disp.</th>
                                    <th>Preço Locação</th>
                                    <th>Preço Venda</th>
                                    <th>Ações</th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; // Fim da verificação de erro na consulta ?>
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

<!-- Adicionar aqui quaisquer scripts JS específicos para esta página -->
<!-- Exemplo: Inicializar DataTables (comentado por padrão, como em secoes/index.php) -->
<!--
<script>
  $(function () {
    $("#tabelaProdutos").DataTable({
      "responsive": true,
      "lengthChange": false, // Pode ajustar estas opções
      "autoWidth": false,
      // "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"], // Descomente se quiser botões
      "language": { // Opcional: Tradução
            "url": "<?php echo BASE_URL; ?>assets/plugins/datatables-pt-br.json" // Ajuste o caminho se necessário
      }
    })//.buttons().container().appendTo('#tabelaProdutos_wrapper .col-md-6:eq(0)'); // Descomente se usar botões
  });
</script>
-->