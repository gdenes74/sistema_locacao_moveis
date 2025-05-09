<?php
// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Cliente.php';

// Garante que a sessão seja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexão com o banco
$database = new Database();
$conn = $database->getConnection();
$clienteModel = new Cliente($conn);

// Configurando mensagens da sessão
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Variáveis para a pesquisa
$searchNome = filter_input(INPUT_GET, 'searchNome', FILTER_SANITIZE_SPECIAL_CHARS);
$searchEmail = filter_input(INPUT_GET, 'searchEmail', FILTER_SANITIZE_SPECIAL_CHARS);
$searchCpfCnpj = filter_input(INPUT_GET, 'searchCpfCnpj', FILTER_SANITIZE_SPECIAL_CHARS);

// Listar clientes com ou sem filtro de pesquisa
try {
    if (!empty($searchNome) || !empty($searchEmail) || !empty($searchCpfCnpj)) {
        $stmt = $clienteModel->searchByMultipleTerms($searchNome, $searchEmail, $searchCpfCnpj);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($clientes)) {
            $errorMessage = "Nenhum cliente encontrado com os critérios informados.";
        }
    } else {
        $stmt = $clienteModel->getAll();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($clientes)) {
            $errorMessage = "Nenhum cliente encontrado no banco de dados.";
        }
    }
} catch (Exception $e) {
    $clientes = [];
    $errorMessage = "Erro ao buscar clientes: " . $e->getMessage();
}

// Preparar dados para autocomplete (serão usados no JavaScript)
$autocompleteData = [];
foreach ($clientes as $cliente) {
    $autocompleteData[] = [
        'nome' => $cliente['nome'],
        'email' => $cliente['email'] ?? '',
        'cpf_cnpj' => $cliente['cpf_cnpj'] ?? ''
    ];
}

?>
<?php include_once __DIR__ . '/../includes/header.php'; ?>

<div class="content-wrapper">
    <!-- Incluindo o menu lateral (sidebar) -->
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Clientes</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Cliente
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Formulário de Pesquisa com Múltiplos Campos -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="index.php" class="form-row">
                        <div class="form-group col-md-4 mb-2">
                            <input type="text" name="searchNome" id="searchNome" class="form-control" placeholder="Pesquisar por nome" value="<?= htmlspecialchars($searchNome ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <input type="text" name="searchEmail" id="searchEmail" class="form-control" placeholder="Pesquisar por email" value="<?= htmlspecialchars($searchEmail ?? '') ?>">
                        </div>
                        <div class="form-group col-md-3 mb-2">
                            <input type="text" name="searchCpfCnpj" id="searchCpfCnpj" class="form-control" placeholder="Pesquisar por CPF/CNPJ" value="<?= htmlspecialchars($searchCpfCnpj ?? '') ?>">
                        </div>
                        <div class="form-group col-md-1 mb-2">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Pesquisar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover" id="clientesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th>CPF/CNPJ</th>
                                <th>Cidade</th>
                                <th>Endereço</th>
                                <th>Obs:</th>
                                <th>Data Cadastro</th>
                                <th></th> <!-- Coluna para os botões -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($clientes)): ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr data-nome="<?= htmlspecialchars($cliente['nome']) ?>"
                                        data-email="<?= htmlspecialchars($cliente['email'] ?? '') ?>"
                                        data-cpf-cnpj="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
                                        <td><?= htmlspecialchars($cliente['id']) ?></td>
                                        <td><?= htmlspecialchars($cliente['nome']) ?></td>
                                        <td><?= htmlspecialchars($cliente['telefone'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($cliente['email'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($cliente['cpf_cnpj'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($cliente['cidade'] ?? '-') ?></td>
                                        <td>
                                            <?php
                                                // Limitar o endereço a 50 caracteres
                                                $endereco = $cliente['endereco'] ?? '-';
                                                if (strlen($endereco) > 50) {
                                                    $endereco = substr($endereco, 0, 50) . '...';
                                                }
                                                echo htmlspecialchars($endereco);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                // Limitar observações a 50 caracteres
                                                $observacoes = $cliente['observacoes'] ?? '-';
                                                if (strlen($observacoes) > 50) {
                                                    $observacoes = substr($observacoes, 0, 50) . '...';
                                                }
                                                echo htmlspecialchars($observacoes);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                // Formatar data de cadastro
                                                echo !empty($cliente['data_cadastro']) 
                                                    ? date('d/m/Y H:i', strtotime($cliente['data_cadastro'])) 
                                                    : '-';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <!-- Ícones de ações -->
                                            <a href="show.php?id=<?= $cliente['id'] ?>" class="btn btn-info btn-sm" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $cliente['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#modalExcluir<?= $cliente['id'] ?>" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <!-- Modal de Exclusão -->
                                            <div class="modal fade" id="modalExcluir<?= $cliente['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirmar Exclusão</h5>
                                                            <button type="button" class="close" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Deseja realmente excluir o cliente <strong><?= htmlspecialchars($cliente['nome']) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                                            <a href="delete.php?id=<?= $cliente['id'] ?>" class="btn btn-danger">Excluir</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">Nenhum cliente encontrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Scripts para Autocomplete e Filtragem Dinâmica -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Dados para autocomplete (carregados do PHP)
    var clientesData = <?= json_encode($autocompleteData) ?>;

    // Autocomplete para o campo Nome
    $('#searchNome').autocomplete({
        source: function(request, response) {
            var term = request.term.toLowerCase();
            var matches = clientesData.filter(function(cliente) {
                return cliente.nome.toLowerCase().indexOf(term) !== -1;
            }).map(function(cliente) {
                return cliente.nome;
            });
            response(matches);
        },
        minLength: 2,
        // Adicionando tratamento para quando não há resultados
        response: function(event, ui) {
            if (!ui.content.length) {
                var noResult = { value:"",label:"Nenhum resultado encontrado" };
                ui.content.push(noResult);
            }
        }
    });

    // Autocomplete para o campo Email
    $('#searchEmail').autocomplete({
        source: function(request, response) {
            var term = request.term.toLowerCase();
            var matches = clientesData.filter(function(cliente) {
                return cliente.email && cliente.email.toLowerCase().indexOf(term) !== -1;
            }).map(function(cliente) {
                return cliente.email;
            });
            response(matches);
        },
        minLength: 2,
        // Adicionando tratamento para quando não há resultados
        response: function(event, ui) {
            if (!ui.content.length) {
                var noResult = { value:"",label:"Nenhum resultado encontrado" };
                ui.content.push(noResult);
            }
        }
    });

    // Autocomplete para o campo CPF/CNPJ
    $('#searchCpfCnpj').autocomplete({
        source: function(request, response) {
            var term = request.term.toLowerCase();
            var matches = clientesData.filter(function(cliente) {
                return cliente.cpf_cnpj && cliente.cpf_cnpj.toLowerCase().indexOf(term) !== -1;
            }).map(function(cliente) {
                return cliente.cpf_cnpj;
            });
            response(matches);
        },
        minLength: 2,
        // Adicionando tratamento para quando não há resultados
        response: function(event, ui) {
            if (!ui.content.length) {
                var noResult = { value:"",label:"Nenhum resultado encontrado" };
                ui.content.push(noResult);
            }
        }
    });

    // Filtragem dinâmica na tabela (opcional, se quiser filtrar sem recarregar a página)
    function filterTable() {
        var nomeTerm = $('#searchNome').val().toLowerCase();
        var emailTerm = $('#searchEmail').val().toLowerCase();
        var cpfCnpjTerm = $('#searchCpfCnpj').val().toLowerCase();

        $('#clientesTable tbody tr').each(function() {
            var nome = $(this).data('nome').toLowerCase();
            var email = $(this).data('email').toLowerCase();
            var cpfCnpj = $(this).data('cpf-cnpj').toLowerCase();

            var show = true;
            if (nomeTerm && nome.indexOf(nomeTerm) === -1) show = false;
            if (emailTerm && email.indexOf(emailTerm) === -1) show = false;
            if (cpfCnpjTerm && cpfCnpj.indexOf(cpfCnpjTerm) === -1) show = false;

            $(this).toggle(show);
        });
    }

    // Aplicar filtragem dinâmica ao digitar (opcional)
    $('#searchNome, #searchEmail, #searchCpfCnpj').on('input', function() {
        filterTable();
    });

    //Manter a busca ao selecionar um item no autocomplete
    $( "#searchNome" ).on( "autocompleteselect", function( event, ui ) {
        filterTable();
    } );
    $( "#searchEmail" ).on( "autocompleteselect", function( event, ui ) {
        filterTable();
    } );
    $( "#searchCpfCnpj" ).on( "autocompleteselect", function( event, ui ) {
        filterTable();
    } );
});
</script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>