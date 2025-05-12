<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$conn = $database->getConnection();
$clienteModel = new Cliente($conn);

// Carregar clientes para autocomplete
$stmtClientes = $clienteModel->getAll();
$autocompleteData = [];
while ($row = $stmtClientes->fetch(PDO::FETCH_ASSOC)) {
    $autocompleteData[] = [
        'id' => $row['id'],
        'nome' => $row['nome'],
        'cpf_cnpj' => $row['cpf_cnpj'],
        'endereco' => $row['endereco']
    ];
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Novo Orçamento</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form action="store.php" method="POST">
                <div class="form-group">
                    <label for="cliente_nome">Cliente:</label>
                    <input type="text" id="cliente_nome" class="form-control" placeholder="Digite o nome ou CPF/CNPJ" required>
                    <!-- Campo oculto para armazenar o ID do cliente -->
                    <input type="hidden" name="cliente_id" id="cliente_id">
                </div>
                <div id="autocomplete-results" class="dropdown-menu"></div>

                <!-- Outros campos do formulário -->
                <div class="form-group">
                    <label for="data_orcamento">Data do Orçamento:</label>
                    <input type="date" name="data_orcamento" id="data_orcamento" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="data_evento">Data do Evento:</label>
                    <input type="date" name="data_evento" id="data_evento" class="form-control" required>
                </div>
                <!-- Adicione mais campos conforme necessário -->

                <button type="submit" class="btn btn-success">Salvar</button>
            </form>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>