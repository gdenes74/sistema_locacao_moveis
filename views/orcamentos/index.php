<?php
// Arquivo: views/orcamentos/index.php

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Orcamento.php';
require_once __DIR__ . '/../../models/Cliente.php';
require_once __DIR__ . '/../includes/functions.php';

// Garantir que a sessão esteja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Instanciar conexão e modelos
$database = new Database();
$conn = $database->getConnection();
$orcamentoModel = new Orcamento($conn);
$clienteModel = new Cliente($conn);

// --- Lógica de Filtros e Ordenação ---
$filtros = [];
$orderBy = 'o.id DESC'; // Ordenação padrão

// Pesquisa geral (número, código, nome do cliente, etc.)
if (isset($_GET['pesquisar']) && !empty(trim($_GET['pesquisar']))) {
    $filtros['pesquisar'] = trim($_GET['pesquisar']);
}

// Filtro por Cliente ID
if (isset($_GET['cliente_id']) && !empty($_GET['cliente_id'])) {
    $filtros['cliente_id'] = (int)$_GET['cliente_id'];
}

// Filtro por Status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filtros['status'] = $_GET['status'];
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

// Filtro por Tipo de Orçamento
if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
    $filtros['tipo'] = $_GET['tipo'];
}

// Ordenação
if (isset($_GET['orderBy']) && !empty($_GET['orderBy'])) {
    // Lista de colunas permitidas para ordenação
    $colunasPermitidas = [
        'o.id ASC', 'o.id DESC',
        'o.numero ASC', 'o.numero DESC',
        'o.codigo ASC', 'o.codigo DESC',
        'c.nome ASC', 'c.nome DESC',
        'o.data_orcamento ASC', 'o.data_orcamento DESC',
        'o.data_validade ASC', 'o.data_validade DESC',
        'o.data_evento ASC', 'o.data_evento DESC',
        'o.valor_final ASC', 'o.valor_final DESC',
        'o.status ASC', 'o.status DESC',
        'o.tipo ASC', 'o.tipo DESC'
    ];
    
    if (in_array($_GET['orderBy'], $colunasPermitidas)) {
        $orderBy = $_GET['orderBy'];
    }
}

// Verificar e atualizar status de orçamentos expirados
$orcamentoModel->verificarEAtualizarExpirados();

// Buscar dados
$stmtOrcamentos = $orcamentoModel->listarTodos($filtros, $orderBy);
$orcamentos = $stmtOrcamentos ? $stmtOrcamentos->fetchAll(PDO::FETCH_ASSOC) : [];

// Buscar clientes para o filtro
$stmtClientes = $clienteModel->listarTodos();
$clientes_para_filtro = $stmtClientes ? $stmtClientes->fetchAll(PDO::FETCH_ASSOC) : [];

// Lista de status para o filtro
$status_orcamento_opcoes = [
    ['valor' => 'pendente', 'texto' => 'Pendente'],
    ['valor' => 'aprovado', 'texto' => 'Aprovado'],
    ['valor' => 'recusado', 'texto' => 'Recusado'],
    ['valor' => 'expirado', 'texto' => 'Expirado']
];

// Lista de tipos de orçamento para o filtro
$tipos_orcamento_opcoes = [
    ['valor' => 'locacao', 'texto' => 'Locação'],
    ['valor' => 'venda', 'texto' => 'Venda'],
    ['valor' => 'misto', 'texto' => 'Misto (Locação e Venda)']
];

// Definir o título da página
$pageTitle = "Lista de Orçamentos";

// Incluir o header
include_once __DIR__ . '/../includes/header.php';
?><div class="content-wrapper">
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
                                <label for="status">Status:</label>
                                <select name="status" id="status" class="form-control select2" style="width: 100%;">
                                    <option value="">Todos os Status</option>
                                    <?php foreach ($status_orcamento_opcoes as $status): ?>
                                        <option value="<?php echo $status['valor']; ?>" 
                                                <?php echo (isset($_GET['status']) && $_GET['status'] == $status['valor']) ? 'selected' : ''; ?>>
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
                                <label for="tipo">Tipo de Orçamento:</label>
                                <select name="tipo" id="tipo" class="form-control select2" style="width: 100%;">
                                    <option value="">Todos os Tipos</option>
                                    <?php foreach ($tipos_orcamento_opcoes as $tipo): ?>
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
                <a href="<?php echo BASE_URL; ?>/views/orcamentos/create.php" class="btn btn-success float-right">
                    <i class="fas fa-plus"></i> Novo Orçamento
                </a>
            </div>            <!-- Visualização em Tabela -->
            <div id="tableView">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i>
                            Orçamentos Registrados
                        </h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-bordered table-striped text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.id ASC' ? 'o.id DESC' : 'o.id ASC')]); ?>">
                                            ID <?php echo sort_icon('o.id', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 80px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.numero ASC' ? 'o.numero DESC' : 'o.numero ASC')]); ?>">
                                            Número <?php echo sort_icon('o.numero', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 120px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.codigo ASC' ? 'o.codigo DESC' : 'o.codigo ASC')]); ?>">
                                            Código <?php echo sort_icon('o.codigo', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'c.nome ASC' ? 'c.nome DESC' : 'c.nome ASC')]); ?>">
                                            Cliente <?php echo sort_icon('c.nome', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.data_orcamento ASC' ? 'o.data_orcamento DESC' : 'o.data_orcamento ASC')]); ?>">
                                            Data Orçam. <?php echo sort_icon('o.data_orcamento', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.data_validade ASC' ? 'o.data_validade DESC' : 'o.data_validade ASC')]); ?>">
                                            Validade <?php echo sort_icon('o.data_validade', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.data_evento ASC' ? 'o.data_evento DESC' : 'o.data_evento ASC')]); ?>">
                                            Data Evento <?php echo sort_icon('o.data_evento', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.tipo ASC' ? 'o.tipo DESC' : 'o.tipo ASC')]); ?>">
                                            Tipo <?php echo sort_icon('o.tipo', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 120px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.valor_final ASC' ? 'o.valor_final DESC' : 'o.valor_final ASC')]); ?>">
                                            Valor Final <?php echo sort_icon('o.valor_final', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 100px;">
                                        <a href="<?php echo build_url(['orderBy' => (isset($_GET['orderBy']) && $_GET['orderBy'] == 'o.status ASC' ? 'o.status DESC' : 'o.status ASC')]); ?>">
                                            Status <?php echo sort_icon('o.status', $orderBy); ?>
                                        </a>
                                    </th>
                                    <th style="width: 180px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orcamentos)): ?>
                                    <?php foreach ($orcamentos as $orc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($orc['id']); ?></td>
                                            <td><?php echo htmlspecialchars($orc['numero'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($orc['codigo'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($orc['nome_cliente'] ?? '-'); ?></td>
                                            <td><?php echo formatar_data_br($orc['data_orcamento']); ?></td>
                                            <td><?php echo formatar_data_br($orc['data_validade']); ?></td>
                                            <td><?php echo $orc['data_evento'] ? formatar_data_br($orc['data_evento']) : '-'; ?></td>
                                            <td>
                                                <?php 
                                                $tipo_texto = 'Desconhecido';
                                                switch ($orc['tipo'] ?? '') {
                                                    case 'locacao': $tipo_texto = 'Locação'; break;
                                                    case 'venda': $tipo_texto = 'Venda'; break;
                                                    case 'misto': $tipo_texto = 'Misto'; break;
                                                }
                                                echo htmlspecialchars($tipo_texto); 
                                                ?>
                                            </td>
                                            <td><?php echo formatar_moeda_br($orc['valor_final']); ?></td>
                                            <td>
                                                <?php 
                                                $status_texto = ucfirst($orc['status'] ?? 'desconhecido');
                                                $status_classe = 'badge badge-light'; // Padrão
                                                
                                                switch (strtolower($orc['status'] ?? '')) {
                                                    case 'pendente': 
                                                        $status_classe = 'badge badge-warning'; 
                                                        break;
                                                    case 'aprovado': 
                                                        $status_classe = 'badge badge-success'; 
                                                        break;
                                                    case 'recusado': 
                                                        $status_classe = 'badge badge-danger'; 
                                                        break;
                                                    case 'expirado': 
                                                        $status_classe = 'badge badge-secondary'; 
                                                        break;
                                                }
                                                ?>
                                                <span class="<?php echo $status_classe; ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo BASE_URL; ?>/views/orcamentos/show.php?id=<?php echo $orc['id']; ?>" 
                                                       class="btn btn-xs btn-info" title="Visualizar Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/views/orcamentos/edit.php?id=<?php echo $orc['id']; ?>" 
                                                       class="btn btn-xs btn-primary" title="Editar Orçamento">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?php echo BASE_URL; ?>/views/orcamentos/print.php?id=<?php echo $orc['id']; ?>" 
                                                        class="btn btn-xs btn-secondary" title="Imprimir Orçamento">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    
                                                    <?php if (strtolower($orc['status'] ?? '') === 'aprovado'): ?>
                                                        <a href="<?php echo BASE_URL; ?>/views/pedidos/create_from_orcamento.php?orcamento_id=<?php echo $orc['id']; ?>" 
                                                           class="btn btn-xs btn-success" title="Converter para Pedido" 
                                                           onclick="return confirm('Tem certeza que deseja converter este orçamento em um pedido?');">
                                                            <i class="fas fa-file-invoice-dollar"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-xs btn-danger" title="Excluir Orçamento"
                                                            onclick="confirmDelete(<?php echo $orc['id']; ?>, '<?php echo htmlspecialchars(addslashes($orc['codigo'] ?? $orc['numero'] ?? $orc['id'])); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-xs btn-default" title="Mais Detalhes"
                                                            data-toggle="popover" data-html="true" data-trigger="click" data-placement="left"
                                                            data-content="
                                                            <strong>Local:</strong> <?php echo htmlspecialchars($orc['local_evento'] ?? '-'); ?><br>
                                                            <strong>Hora Evento:</strong> <?php echo $orc['hora_evento'] ? substr($orc['hora_evento'], 0, 5) : '-'; ?><br>
                                                            <strong>Devolução:</strong> <?php echo $orc['data_devolucao_prevista'] ? formatar_data_br($orc['data_devolucao_prevista']) : '-'; ?><br>
                                                            <strong>Subtotal Locação:</strong> <?php echo formatar_moeda_br($orc['subtotal_locacao']); ?><br>
                                                            <strong>Subtotal Venda:</strong> <?php echo formatar_moeda_br($orc['subtotal_venda']); ?><br>
                                                            <strong>Desconto:</strong> <?php echo formatar_moeda_br($orc['desconto']); ?><br>
                                                            <strong>Taxas:</strong> <?php echo formatar_moeda_br(($orc['taxa_domingo_feriado'] ?? 0) + ($orc['taxa_madrugada'] ?? 0) + ($orc['taxa_horario_especial'] ?? 0) + ($orc['taxa_hora_marcada'] ?? 0)); ?><br>
                                                            <strong>Frete Térreo:</strong> <?php echo formatar_moeda_br($orc['frete_terreo']); ?><br>
                                                            <strong>Frete Elevador:</strong> <?php echo htmlspecialchars($orc['frete_elevador'] ?? 'confirmar'); ?><br>
                                                            <strong>Frete Escadas:</strong> <?php echo htmlspecialchars($orc['frete_escadas'] ?? 'confirmar'); ?><br>
                                                            ">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center">Nenhum orçamento encontrado com os filtros aplicados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer clearfix">
                        <div class="float-right">
                            <span class="text-muted">
                                Total de registros: <strong><?php echo count($orcamentos); ?></strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>            <!-- Visualização em Cards -->
            <div id="cardsView" style="display: none;">
                <div class="row">
                    <?php if (!empty($orcamentos)): ?>
                        <?php foreach ($orcamentos as $orc): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card card-outline 
                                    <?php 
                                    switch (strtolower($orc['status'] ?? '')) {
                                        case 'pendente': echo 'card-warning'; break;
                                        case 'aprovado': echo 'card-success'; break;
                                        case 'recusado': echo 'card-danger'; break;
                                        case 'expirado': echo 'card-secondary'; break;
                                        default: echo 'card-primary'; break;
                                    }
                                    ?>
                                ">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php echo htmlspecialchars($orc['codigo'] ?? 'ORC-' . $orc['id']); ?></h3>
                                        <div class="card-tools">
                                            <span class="badge 
                                                <?php 
                                                switch (strtolower($orc['status'] ?? '')) {
                                                    case 'pendente': echo 'badge-warning'; break;
                                                    case 'aprovado': echo 'badge-success'; break;
                                                    case 'recusado': echo 'badge-danger'; break;
                                                    case 'expirado': echo 'badge-secondary'; break;
                                                    default: echo 'badge-light'; break;
                                                }
                                                ?>
                                            ">
                                                <?php echo htmlspecialchars(ucfirst($orc['status'] ?? 'desconhecido')); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-7">
                                                <h5><strong><?php echo htmlspecialchars($orc['nome_cliente'] ?? '-'); ?></strong></h5>
                                                <p class="text-muted">
                                                    <i class="fas fa-calendar"></i> Orçamento: <?php echo formatar_data_br($orc['data_orcamento']); ?><br>
                                                    <i class="fas fa-calendar-check"></i> Evento: <?php echo $orc['data_evento'] ? formatar_data_br($orc['data_evento']) : '-'; ?><br>
                                                    <i class="fas fa-clock"></i> Hora: <?php echo $orc['hora_evento'] ? substr($orc['hora_evento'], 0, 5) : '-'; ?><br>
                                                    <i class="fas fa-map-marker-alt"></i> Local: <?php echo htmlspecialchars(substr($orc['local_evento'] ?? '-', 0, 30)) . (strlen($orc['local_evento'] ?? '') > 30 ? '...' : ''); ?>
                                                </p>
                                            </div>
                                            <div class="col-5 text-right">
                                                <div class="h3"><?php echo formatar_moeda_br($orc['valor_final']); ?></div>
                                                <div class="text-muted">
                                                    <?php 
                                                    $tipo_texto = 'Desconhecido';
                                                    switch ($orc['tipo'] ?? '') {
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
                                            <a href="<?php echo BASE_URL; ?>/views/orcamentos/show.php?id=<?php echo $orc['id']; ?>" 
                                               class="btn btn-sm btn-info" title="Visualizar Detalhes">
                                                <i class="fas fa-eye"></i> Detalhes
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/views/orcamentos/edit.php?id=<?php echo $orc['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Editar Orçamento">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Excluir Orçamento"
                                                    onclick="confirmDelete(<?php echo $orc['id']; ?>, '<?php echo htmlspecialchars(addslashes($orc['codigo'] ?? $orc['numero'] ?? $orc['id'])); ?>')">
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
                                Nenhum orçamento encontrado com os filtros aplicados.
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
    const deleteUrl = `<?php echo BASE_URL; ?>/views/orcamentos/delete.php?id=${id}`;
    
    Swal.fire({
        title: 'Confirmar exclusão?',
        html: `Tem certeza que deseja excluir o orçamento <strong>${identificador}</strong>?<br>Esta ação não pode ser desfeita.`,
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
        localStorage.setItem('orcamentosViewMode', 'table');
    });
    
    $('#btnViewCards').click(function() {
        $('#tableView').hide();
        $('#cardsView').show();
        $(this).addClass('active');
        $('#btnViewTable').removeClass('active');
        localStorage.setItem('orcamentosViewMode', 'cards');
    });
    
    // Verificar preferência de visualização salva
    const savedViewMode = localStorage.getItem('orcamentosViewMode');
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