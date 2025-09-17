<?php
// Arquivo: views/pedidos/index.php

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../includes/functions.php';

// Garantir que a sessão esteja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Instanciar conexão e modelos
$database = new Database();
$conn = $database->getConnection();
$pedidoModel = new Pedido($conn);
$clienteModel = new Cliente($conn);

// --- Lógica de Filtros e Ordenação ---
$filtros = [];
$orderBy = 'p.id DESC'; // Ordenação padrão

// Pesquisa geral (número, código, nome do cliente, etc.)
if (isset($_GET['pesquisar']) && !empty(trim($_GET['pesquisar']))) {
    $filtros['pesquisar'] = trim($_GET['pesquisar']);
}

// Filtro por Cliente ID
if (isset($_GET['cliente_id']) && !empty($_GET['cliente_id'])) {
    $filtros['cliente_id'] = (int)$_GET['cliente_id'];
}

// ✅ CORRIGIDO: Filtro por Situação do Pedido
if (isset($_GET['situacao_pedido']) && !empty($_GET['situacao_pedido'])) {
    $filtros['situacao_pedido'] = $_GET['situacao_pedido'];
}

// Filtro por Data do Evento (De)
if (isset($_GET['data_evento_de']) && !empty($_GET['data_evento_de'])) {
    if (DateTime::createFromFormat('d/m/Y', $_GET['data_evento_de'])) {
        $filtros['data_evento_de'] = DateTime::createFromFormat('d/m/Y', $_GET['data_evento_de'])->format('Y-m-d');
    }
}

// Filtro por Data do Evento (Até)
if (isset($_GET['data_evento_ate']) && !empty($_GET['data_evento_ate'])) {
    if (DateTime::createFromFormat('d/m/Y', $_GET['data_evento_ate'])) {
        $filtros['data_evento_ate'] = DateTime::createFromFormat('d/m/Y', $_GET['data_evento_ate'])->format('Y-m-d');
    }
}

// Filtro por Tipo de Pedido
if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
    $filtros['tipo'] = $_GET['tipo'];
}

// ✅ CORRIGIDO: Ordenação
if (isset($_GET['orderBy']) && !empty($_GET['orderBy'])) {
    // Lista de colunas permitidas para ordenação
    $colunasPermitidas = [
        'p.id ASC', 'p.id DESC',
        'p.numero ASC', 'p.numero DESC',
        'p.codigo ASC', 'p.codigo DESC',
        'c.nome ASC', 'c.nome DESC',
        'p.data_pedido ASC', 'p.data_pedido DESC',
        'p.data_evento ASC', 'p.data_evento DESC',
        'p.data_entrega ASC', 'p.data_entrega DESC',
        'p.valor_final ASC', 'p.valor_final DESC',
        'p.situacao_pedido ASC', 'p.situacao_pedido DESC', // ✅ CORRIGIDO
        'p.tipo ASC', 'p.tipo DESC'
    ];

    if (in_array($_GET['orderBy'], $colunasPermitidas)) {
        $orderBy = $_GET['orderBy'];
    }
}

// Buscar dados
$stmtPedidos = $pedidoModel->listarTodos($filtros, $orderBy);
$pedidos = $stmtPedidos ? $stmtPedidos->fetchAll(PDO::FETCH_ASSOC) : [];

// Buscar clientes para o filtro
$stmtClientes = $clienteModel->listarTodos();
$clientes_para_filtro = $stmtClientes ? $stmtClientes->fetchAll(PDO::FETCH_ASSOC) : [];

// ✅ CORRIGIDO: Lista de status para o filtro (conforme banco)
$status_pedido_opcoes = [
    ['valor' => 'confirmado', 'texto' => 'Confirmado'],
    ['valor' => 'em_separacao', 'texto' => 'Em Separação'],
    ['valor' => 'entregue', 'texto' => 'Entregue'],
    ['valor' => 'devolvido_parcial', 'texto' => 'Devolvido Parcial'],
    ['valor' => 'finalizado', 'texto' => 'Finalizado'],
    ['valor' => 'cancelado', 'texto' => 'Cancelado']
];

// Lista de tipos de pedido para o filtro
$tipos_pedido_opcoes = [
    ['valor' => 'locacao', 'texto' => 'Locação'],
    ['valor' => 'venda', 'texto' => 'Venda'],
    ['valor' => 'misto', 'texto' => 'Misto (Locação e Venda)']
];

// Definir o título da página
$pageTitle = "Lista de Pedidos";

// Incluir o header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/dashboard/index.php">Início</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($pageTitle); ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php include_once __DIR__ . '/../includes/alert_messages.php'; ?>

            <!-- Card de Filtros -->
            <div class="card card-default collapsed-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-filter"></i>
                        Filtros
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="pesquisar">Pesquisar:</label>
                                <input type="text" name="pesquisar" id="pesquisar" class="form-control"
                                       value="<?php echo isset($_GET['pesquisar']) ? htmlspecialchars($_GET['pesquisar']) : ''; ?>"
                                       placeholder="Nº, Código, Cliente...">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="cliente_id">Cliente:</label>
                                <select name="cliente_id" id="cliente_id" class="form-control select2" style="width: 100%;">
                                    <option value="">Todos os Clientes</option>
                                    <?php foreach ($clientes_para_filtro as $cliente): ?>
                                        <option value="<?php echo $cliente['id']; ?>"
                                                <?php echo (isset($_GET['cliente_id']) && $_GET['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cliente['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <!-- ✅ CORRIGIDO: name="situacao_pedido" -->
                                <label for="situacao_pedido">Situação:</label>
                                <select name="situacao_pedido" id="situacao_pedido" class="form-control select2" style="width: 100%;">
                                    <option value="">Todas as Situações</option>
                                    <?php foreach ($status_pedido_opcoes as $status): ?>
                                        <option value="<?php echo $status['valor']; ?>"
                                                <?php echo (isset($_GET['situacao_pedido']) && $_GET['situacao_pedido'] == $status['valor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['texto']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label for="data_evento_de">Data Evento (De):</label>
                                <input type="text" name="data_evento_de" id="data_evento_de" class="form-control datepicker"
                                       value="<?php echo isset($_GET['data_evento_de']) ? htmlspecialchars($_GET['data_evento_de']) : ''; ?>"
                                       placeholder="dd/mm/aaaa" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="data_evento_ate">Data Evento (Até):</label>
                                <input type="text" name="data_evento_ate" id="data_evento_ate" class="form-control datepicker"
                                       value="<?php echo isset($_GET['data_evento_ate']) ? htmlspecialchars($_GET['data_evento_ate']) : ''; ?>"
                                       placeholder="dd/mm/aaaa" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="tipo">Tipo de Pedido:</label>
                                <select name="tipo" id="tipo" class="form-control select2" style="width: 100%;">
                                    <option value="">Todos os Tipos</option>
                                    <?php foreach ($tipos_pedido_opcoes as $tipo): ?>
                                        <option value="<?php echo $tipo['valor']; ?>"
                                                <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == $tipo['valor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo['texto']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                                    <i class="fas fa-eraser"></i> Limpar Filtros
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Aplicar Filtros
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Opções de Visualização -->
            <div class="mb-3">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-default active" id="btnViewTable">
                        <i class="fas fa-table"></i> Tabela
                    </button>
                    <button type="button" class="btn btn-default" id="btnViewCards">
                        <i class="fas fa-th-large"></i> Cards
                    </button>
                </div>
                <a href="<?php echo BASE_URL; ?>/views/pedidos/create.php" class="btn btn-success float-right">
                    <i class="fas fa-plus"></i> Novo Pedido
                </a>
            </div>

            <!-- Visualização em Tabela -->
            <div id="tableView">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i>
                            Pedidos Registrados
                        </h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-bordered table-striped text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.id ASC' ? 'p.id DESC' : 'p.id ASC')]); ?>">
                                            ID <?php echo sort_icon('p.id', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 80px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.numero ASC' ? 'p.numero DESC' : 'p.numero ASC')]); ?>">
                                            Número <?php echo sort_icon('p.numero', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 120px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.codigo ASC' ? 'p.codigo DESC' : 'p.codigo ASC')]); ?>">
                                            Código <?php echo sort_icon('p.codigo', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'c.nome ASC' ? 'c.nome DESC' : 'c.nome ASC')]); ?>">
                                            Cliente <?php echo sort_icon('c.nome', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.data_pedido ASC' ? 'p.data_pedido DESC' : 'p.data_pedido ASC')]); ?>">
                                            Data Pedido <?php echo sort_icon('p.data_pedido', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.data_evento ASC' ? 'p.data_evento DESC' : 'p.data_evento ASC')]); ?>">
                                            Data Evento <?php echo sort_icon('p.data_evento', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.data_entrega ASC' ? 'p.data_entrega DESC' : 'p.data_entrega ASC')]); ?>">
                                            Entrega <?php echo sort_icon('p.data_entrega', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.tipo ASC' ? 'p.tipo DESC' : 'p.tipo ASC')]); ?>">
                                            Tipo <?php echo sort_icon('p.tipo', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 120px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.valor_final ASC' ? 'p.valor_final DESC' : 'p.valor_final ASC')]); ?>">
                                            Valor Final <?php echo sort_icon('p.valor_final', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <!-- ✅ CORRIGIDO: p.situacao_pedido -->
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'p.situacao_pedido ASC' ? 'p.situacao_pedido DESC' : 'p.situacao_pedido ASC')]); ?>">
                                            Situação <?php echo sort_icon('p.situacao_pedido', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 180px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pedidos)): ?>
                                    <?php foreach ($pedidos as $ped): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ped['id']); ?></td>
                                            <td><?php echo htmlspecialchars($ped['numero'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($ped['codigo'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($ped['nome_cliente'] ?? '-'); ?></td>
                                            <td><?php echo formatar_data_br($ped['data_pedido']); ?></td>
                                            <td><?php echo $ped['data_evento'] ? formatar_data_br($ped['data_evento']) : '-'; ?></td>
                                            <td><?php echo $ped['data_entrega'] ? formatar_data_br($ped['data_entrega']) : '-'; ?></td>
                                            <td>
                                                <?php
                                                $tipo_texto = 'Desconhecido';
                                                switch ($ped['tipo'] ?? '') {
                                                    case 'locacao': $tipo_texto = 'Locação'; break;
                                                    case 'venda': $tipo_texto = 'Venda'; break;
                                                    case 'misto': $tipo_texto = 'Misto'; break;
                                                }
                                                echo htmlspecialchars($tipo_texto);
                                                ?>
                                            </td>
                                            <td><?php echo formatar_moeda_br($ped['valor_final']); ?></td>
                                            <td>
                                                <?php
                                                // ✅ CORRIGIDO: usar situacao_pedido
                                                $situacao = $ped['situacao_pedido'] ?? 'desconhecido';
                                                $situacao_texto = '';
                                                $situacao_classe = 'badge badge-light'; // Padrão

                                                switch (strtolower($situacao)) {
                                                    case 'confirmado':
                                                        $situacao_classe = 'badge badge-primary';
                                                        $situacao_texto = 'Confirmado';
                                                        break;
                                                    case 'em_separacao':
                                                        $situacao_classe = 'badge badge-warning';
                                                        $situacao_texto = 'Em Separação';
                                                        break;
                                                    case 'entregue':
                                                        $situacao_classe = 'badge badge-success';
                                                        $situacao_texto = 'Entregue';
                                                        break;
                                                    case 'devolvido_parcial':
                                                        $situacao_classe = 'badge badge-info';
                                                        $situacao_texto = 'Devolvido Parcial';
                                                        break;
                                                    case 'finalizado':
                                                        $situacao_classe = 'badge badge-dark';
                                                        $situacao_texto = 'Finalizado';
                                                        break;
                                                    case 'cancelado':
                                                        $situacao_classe = 'badge badge-danger';
                                                        $situacao_texto = 'Cancelado';
                                                        break;
                                                    default:
                                                        $situacao_texto = 'Desconhecido';
                                                        break;
                                                }
                                                ?>
                                                <span class="<?php echo $situacao_classe; ?>"><?php echo htmlspecialchars($situacao_texto); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo BASE_URL; ?>/views/pedidos/show.php?id=<?php echo $ped['id']; ?>"
                                                       class="btn btn-xs btn-info" title="Visualizar Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/views/pedidos/edit.php?id=<?php echo $ped['id']; ?>"
                                                       class="btn btn-xs btn-primary" title="Editar Pedido">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-xs btn-secondary btn-imprimir-pedido"
                                                            data-id="<?= $ped['id'] ?>"
                                                            data-numero="<?= htmlspecialchars($ped['numero']) ?>"
                                                            title="Imprimir Pedido">
                                                        <i class="fas fa-print"></i>
                                                    </button>

                                                    <!-- ✅ CORRIGIDO: Botões de Situação -->
                                                    <?php if ($ped['situacao_pedido'] === 'confirmado'): ?>
                                                        <button type="button" class="btn btn-xs btn-warning btnMudarStatus"
                                                                data-id="<?php echo $ped['id']; ?>" data-status="em_separacao"
                                                                title="Marcar como Em Separação">
                                                            <i class="fas fa-tools"></i>
                                                        </button>
                                                    <?php elseif ($ped['situacao_pedido'] === 'em_separacao'): ?>
                                                        <button type="button" class="btn btn-xs btn-success btnMudarStatus"
                                                                data-id="<?php echo $ped['id']; ?>" data-status="entregue"
                                                                title="Marcar como Entregue">
                                                            <i class="fas fa-truck"></i>
                                                        </button>
                                                    <?php elseif ($ped['situacao_pedido'] === 'entregue'): ?>
                                                        <button type="button" class="btn btn-xs btn-info btnMudarStatus"
                                                                data-id="<?php echo $ped['id']; ?>" data-status="devolvido_parcial"
                                                                title="Marcar como Devolvido">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <button type="button" class="btn btn-xs btn-danger" title="Excluir Pedido"
                                                            onclick="confirmDelete(<?php echo $ped['id']; ?>, '<?php echo htmlspecialchars(addslashes($ped['codigo'] ?? $ped['numero'] ?? $ped['id'])); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>

                                                    <button type="button" class="btn btn-xs btn-default" title="Mais Detalhes"
                                                            data-toggle="popover" data-html="true" data-trigger="click" data-placement="left"
                                                            data-content="
                                                            <strong>Local:</strong> <?php echo htmlspecialchars($ped['local_evento'] ?? '-'); ?><br>
                                                            <strong>Hora Evento:</strong> <?php echo $ped['hora_evento'] ? substr($ped['hora_evento'], 0, 5) : '-'; ?><br>
                                                            <strong>Devolução:</strong> <?php echo $ped['data_devolucao_prevista'] ? formatar_data_br($ped['data_devolucao_prevista']) : '-'; ?><br>
                                                            <strong>Subtotal Locação:</strong> <?php echo formatar_moeda_br($ped['subtotal_locacao']); ?><br>
                                                            <strong>Subtotal Venda:</strong> <?php echo formatar_moeda_br($ped['subtotal_venda']); ?><br>
                                                            <strong>Desconto:</strong> <?php echo formatar_moeda_br($ped['desconto']); ?><br>
                                                            <strong>Sinal:</strong> <?php echo formatar_moeda_br($ped['valor_sinal'] ?? 0); ?><br>
                                                            <strong>Pago:</strong> <?php echo formatar_moeda_br($ped['valor_pago'] ?? 0); ?><br>
                                                            <strong>Saldo:</strong> <?php echo formatar_moeda_br(max(0, ($ped['valor_final'] ?? 0) - ($ped['valor_pago'] ?? 0))); ?><br>
                                                            ">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center">Nenhum pedido encontrado com os filtros aplicados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer clearfix">
                        <div class="float-right">
                            <span class="text-muted">
                                Total de registros: <strong><?php echo count($pedidos); ?></strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visualização em Cards -->
            <div id="cardsView" style="display: none;">
                <div class="row">
                    <?php if (!empty($pedidos)): ?>
                        <?php foreach ($pedidos as $ped): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card card-outline
                                    <?php
                                    // ✅ CORRIGIDO: usar situacao_pedido
                                    switch (strtolower($ped['situacao_pedido'] ?? '')) {
                                        case 'confirmado': echo 'card-primary'; break;
                                        case 'em_separacao': echo 'card-warning'; break;
                                        case 'entregue': echo 'card-success'; break;
                                        case 'devolvido_parcial': echo 'card-info'; break;
                                        case 'cancelado': echo 'card-danger'; break;
                                        case 'finalizado': echo 'card-dark'; break;
                                        default: echo 'card-secondary'; break;
                                    }
                                    ?>
                                ">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php echo htmlspecialchars($ped['codigo'] ?? 'PED-' . $ped['id']); ?></h3>
                                        <div class="card-tools">
                                            <span class="badge
                                                <?php
                                                // ✅ CORRIGIDO: usar situacao_pedido
                                                switch (strtolower($ped['situacao_pedido'] ?? '')) {
                                                    case 'confirmado': echo 'badge-primary'; break;
                                                    case 'em_separacao': echo 'badge-warning'; break;
                                                    case 'entregue': echo 'badge-success'; break;
                                                    case 'devolvido_parcial': echo 'badge-info'; break;
                                                    case 'cancelado': echo 'badge-danger'; break;
                                                    case 'finalizado': echo 'badge-dark'; break;
                                                    default: echo 'badge-light'; break;
                                                }
                                                ?>
                                            ">
                                                <?php 
                                                $situacao_display = $ped['situacao_pedido'] ?? 'desconhecido';
                                                switch ($situacao_display) {
                                                    case 'em_separacao': echo 'Em Separação'; break;
                                                    case 'devolvido_parcial': echo 'Devolvido Parcial'; break;
                                                    default: echo htmlspecialchars(ucfirst(str_replace('_', ' ', $situacao_display))); break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-7">
                                                <h5><strong><?php echo htmlspecialchars($ped['nome_cliente'] ?? '-'); ?></strong></h5>
                                                <p class="text-muted">
                                                    <i class="fas fa-calendar"></i> Pedido: <?php echo formatar_data_br($ped['data_pedido']); ?><br>
                                                    <i class="fas fa-calendar-check"></i> Evento: <?php echo $ped['data_evento'] ? formatar_data_br($ped['data_evento']) : '-'; ?><br>
                                                    <i class="fas fa-truck"></i> Entrega: <?php echo $ped['data_entrega'] ? formatar_data_br($ped['data_entrega']) : '-'; ?><br>
                                                    <i class="fas fa-map-marker-alt"></i> Local: <?php echo htmlspecialchars(substr($ped['local_evento'] ?? '-', 0, 30)) . (strlen($ped['local_evento'] ?? '') > 30 ? '...' : ''); ?>
                                                </p>
                                            </div>
                                            <div class="col-5 text-right">
                                                <div class="h3"><?php echo formatar_moeda_br($ped['valor_final']); ?></div>
                                                <div class="text-muted">
                                                    <?php
                                                    $tipo_texto = 'Desconhecido';
                                                    switch ($ped['tipo'] ?? '') {
                                                        case 'locacao': $tipo_texto = 'Locação'; break;
                                                        case 'venda': $tipo_texto = 'Venda'; break;
                                                        case 'misto': $tipo_texto = 'Misto'; break;
                                                    }
                                                    echo htmlspecialchars($tipo_texto);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group btn-block">
                                            <a href="<?php echo BASE_URL; ?>/views/pedidos/show.php?id=<?php echo $ped['id']; ?>"
                                               class="btn btn-sm btn-info" title="Visualizar Detalhes">
                                                <i class="fas fa-eye"></i> Detalhes
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/views/pedidos/edit.php?id=<?php echo $ped['id']; ?>"
                                               class="btn btn-sm btn-primary" title="Editar Pedido">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Excluir Pedido"
                                                    onclick="confirmDelete(<?php echo $ped['id']; ?>, '<?php echo htmlspecialchars(addslashes($ped['codigo'] ?? $ped['numero'] ?? $ped['id'])); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                Nenhum pedido encontrado com os filtros aplicados.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Função para confirmar exclusão
function confirmDelete(id, identificador) {
    const deleteUrl = `<?php echo BASE_URL; ?>/views/pedidos/delete.php?id=${id}`;

    Swal.fire({
        title: 'Confirmar exclusão?',
        html: `Tem certeza que deseja excluir o pedido <strong>${identificador}</strong>?<br>Esta ação não pode ser desfeita.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = deleteUrl;
        }
    });
}

$(function () {
    // Inicializar Select2
    $('.select2').select2({
        theme: 'bootstrap4'
    });

    // Inicializar Datepicker
    $('.datepicker').datepicker({
        dateFormat: 'dd/mm/yy',
        changeMonth: true,
        changeYear: true,
        dayNames: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'],
        dayNamesMin: ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'],
        dayNamesShort: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
        monthNames: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
        monthNamesShort: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']
    });

    // Inicializar Popovers
    $('[data-toggle="popover"]').popover({
        html: true,
        container: 'body',
        template: '<div class="popover" role="tooltip"><div class="arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>'
    });

    // Alternar entre visualizações de tabela e cards
    $('#btnViewTable').click(function() {
        $('#tableView').show();
        $('#cardsView').hide();
        $(this).addClass('active');
        $('#btnViewCards').removeClass('active');
        localStorage.setItem('pedidosViewMode', 'table');
    });

    $('#btnViewCards').click(function() {
        $('#tableView').hide();
        $('#cardsView').show();
        $(this).addClass('active');
        $('#btnViewTable').removeClass('active');
        localStorage.setItem('pedidosViewMode', 'cards');
    });

    // Verificar preferência de visualização salva
    const savedViewMode = localStorage.getItem('pedidosViewMode');
    if (savedViewMode === 'cards') {
        $('#btnViewCards').click();
    }

    // Fechar popovers ao clicar fora deles
    $('body').on('click', function (e) {
        if ($(e.target).data('toggle') !== 'popover' && $(e.target).parents('.popover').length === 0) {
            $('[data-toggle="popover"]').popover('hide');
        }
    });
});

// === LÓGICA DE MUDANÇA DE SITUAÇÃO ===
$(document).on('click', '.btnMudarStatus', function() {
    const $btn = $(this);
    const pedidoId = $btn.data('id');
    const novaSituacao = $btn.data('status');
    
    let situacaoTexto = '';
    switch(novaSituacao) {
        case 'em_separacao': situacaoTexto = 'Em Separação'; break;
        case 'entregue': situacaoTexto = 'Entregue'; break;
        case 'devolvido_parcial': situacaoTexto = 'Devolvido Parcial'; break;
        case 'finalizado': situacaoTexto = 'Finalizado'; break;
        case 'cancelado': situacaoTexto = 'Cancelado'; break;
        default: situacaoTexto = novaSituacao; break;
    }

    Swal.fire({
        title: 'Alterar Situação',
        text: `Deseja alterar a situação do pedido para "${situacaoTexto}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Alterar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                dataType: 'json',
                data: { 
                    pedido_id: pedidoId,
                    nova_situacao: novaSituacao
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Sucesso!',
                            text: `Situação alterada para "${situacaoTexto}" com sucesso!`,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                        $btn.prop('disabled', false).html($btn.data('original-html'));
                    }
                },
                error: function() {
                    Swal.fire('Erro', 'Erro de comunicação com o servidor', 'error');
                    $btn.prop('disabled', false).html($btn.data('original-html'));
                }
            });
        }
    });
});

// === LÓGICA DE IMPRESSÃO COM OPÇÃO ===
$(document).on('click', '.btn-imprimir-pedido', function() {
    const pedidoId = $(this).data('id');
    const pedidoNumero = $(this).data('numero');

    Swal.fire({
        title: `Imprimir Pedido #${pedidoNumero}`,
        text: 'Escolha o tipo de impressão:',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fas fa-user"></i> Para Cliente',
        denyButtonText: '<i class="fas fa-tools"></i> Para Produção',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#007bff',
        denyButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            // Impressão para cliente
            imprimirPedido(pedidoId, 'cliente');
        } else if (result.isDenied) {
            // Impressão para produção
            imprimirPedido(pedidoId, 'producao');
        }
    });
});

// Função para imprimir pedido
function imprimirPedido(id, tipo) {
    // Abre uma nova janela com o pedido
    const url = `show.php?id=${id}&print=${tipo}`;
    const printWindow = window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');

    // Aguarda carregar e imprime
printWindow.onload = function() {
    setTimeout(function() {
        if (tipo === 'cliente') {
            printWindow.imprimirCliente();
        } else {
            printWindow.imprimirProducao();
        }
        // Fecha a janela após imprimir
        setTimeout(function() {
            printWindow.close();
        }, 2000);
    }, 1000);
};
}
</script>

<?php
/**
 * Função para gerar ícone de ordenação
 */
function sort_icon($column, $current_order) {
    $column_asc = $column . ' ASC';
    $column_desc = $column . ' DESC';

    if ($current_order === $column_asc) {
        return '<i class="fas fa-sort-up ml-1"></i>';
    } elseif ($current_order === $column_desc) {
        return '<i class="fas fa-sort-down ml-1"></i>';
    } else {
        return '<i class="fas fa-sort ml-1 text-muted"></i>';
    }
}
?>