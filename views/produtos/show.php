<?php
// Inicia a sessão OBRIGATORIAMENTE no topo
if (session_status() == PHP_SESSION_NONE) { // Evita erro se já iniciada
    session_start();
}

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php'; // Inclui BASE_URL, PROJECT_ROOT, etc.
// ***** CORREÇÃO: Incluir helpers.php explicitamente AQUI *****
// Mesmo que config.php o inclua no final, fazer isso garante que as funções
// estarão disponíveis para ESTE script ANTES de serem chamadas.
require_once __DIR__ . '/../../utils/helpers.php';
// *************************************************************
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';


// --- Verificação de ID ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || (int)$_GET['id'] <= 0) {
    $_SESSION['error_message'] = "Erro: ID do produto inválido ou não fornecido para visualização.";
    redirect('views/produtos/index.php'); // Usa redirect de config ou helpers
}
$produto_id = (int)$_GET['id'];

// --- Conexão e Busca do Produto ---
$database = new Database();
$conn = $database->getConnection();
$produto = new Produto($conn);

$produto_data = $produto->lerPorId($produto_id); // Usando o método que retorna array

// --- Verifica se o produto foi encontrado ---
if (!$produto_data) {
    $_SESSION['error_message'] = "Produto com ID {$produto_id} não encontrado.";
    redirect('views/produtos/index.php');
}

// --- Carrega componentes técnicos e regras de conjunto comercial para exibição ---
$componentesProduto = [];
$gruposConjunto = [];

try {
    if (method_exists($produto, 'listarComponentes')) {
        $componentesProduto = $produto->listarComponentes($produto_id);
    } else {
        $stmtComponentes = $conn->prepare("
            SELECT
                pc.id,
                pc.produto_pai_id,
                pc.produto_filho_id,
                pc.quantidade,
                pc.obrigatorio,
                pc.observacoes,
                filho.codigo,
                filho.nome_produto,
                filho.tipo_produto,
                filho.controla_estoque,
                filho.quantidade_total,
                filho.preco_locacao
            FROM produto_composicao pc
            INNER JOIN produtos filho ON filho.id = pc.produto_filho_id
            WHERE pc.produto_pai_id = :produto_pai_id
            ORDER BY filho.nome_produto ASC
        ");
        $stmtComponentes->bindValue(':produto_pai_id', $produto_id, PDO::PARAM_INT);
        $stmtComponentes->execute();
        $componentesProduto = $stmtComponentes->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!empty($produto_data['eh_conjunto'])) {
        if (method_exists($produto, 'listarGruposConjunto')) {
            $gruposConjunto = $produto->listarGruposConjunto($produto_id);
        } else {
            $stmtGrupos = $conn->prepare("
                SELECT
                    pcg.*,
                    c.nome AS nome_categoria,
                    s.nome AS nome_subcategoria
                FROM produto_conjunto_grupos pcg
                LEFT JOIN categorias c ON c.id = pcg.categoria_id
                LEFT JOIN subcategorias s ON s.id = pcg.subcategoria_id
                WHERE pcg.produto_conjunto_id = :produto_conjunto_id
                ORDER BY pcg.ordem ASC, pcg.id ASC
            ");
            $stmtGrupos->bindValue(':produto_conjunto_id', $produto_id, PDO::PARAM_INT);
            $stmtGrupos->execute();
            $gruposConjunto = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Erro ao carregar detalhes complementares do produto {$produto_id}: " . $e->getMessage());
}

// --- Incluir Cabeçalho e Barra Lateral (APÓS verificar se o produto existe) ---
include_once __DIR__ . '/../includes/header.php';


// --- Preparar dados para exibição segura ---
$nome_produto_titulo = htmlspecialchars($produto_data['nome_produto'] ?? 'Detalhes do Produto', ENT_QUOTES, 'UTF-8');

// --- Lógica para determinar URL da foto (Considerando novo caminho) ---
$placeholderImgUrl = build_url('assets/img/product_placeholder.png'); // Placeholder padrão
$fotoUrlParaExibir = $placeholderImgUrl; // Assume placeholder inicialmente

if (!empty($produto_data['foto_path'])) {
    $caminho_foto_db = $produto_data['foto_path']; // Ex: 'assets/uploads/foto_velha.jpg' ou 'assets/uploads/produtos/foto_nova.jpg'

    // ***** AJUSTE DO CAMINHO DA IMAGEM *****
    // Define o caminho relativo base esperado para as novas fotos
    $novo_caminho_base_relativo = 'assets/uploads/produtos/';

    // Verifica se o caminho no DB JÁ CONTÉM o novo caminho base
    if (strpos($caminho_foto_db, $novo_caminho_base_relativo) === 0) {
        // Caminho já está no formato novo (ex: 'assets/uploads/produtos/img.jpg')
        $caminho_relativo_final = $caminho_foto_db;
    } else {
        // Tenta montar o caminho novo assumindo que o DB tem apenas o nome do arquivo
        // (ou um caminho antigo que não nos serve mais diretamente).
        // Pega apenas o nome base do arquivo do DB.
        $nome_arquivo = basename($caminho_foto_db);
        // Se o nome do arquivo não for vazio, monta o caminho completo novo
        if (!empty($nome_arquivo)) {
             $caminho_relativo_final = $novo_caminho_base_relativo . $nome_arquivo;
        } else {
            $caminho_relativo_final = null; // Não foi possível determinar o caminho
        }
    }
    // *****************************************

    // Se conseguimos montar um caminho relativo final, verificamos se o arquivo existe
    if ($caminho_relativo_final) {
        $caminho_fisico_completo = PROJECT_ROOT . '/' . ltrim($caminho_relativo_final, '/');

        if (file_exists($caminho_fisico_completo)) {
            $fotoUrlParaExibir = build_url($caminho_relativo_final); // Usa helper para construir URL completa
        } else {
             error_log("Arquivo de imagem não encontrado para produto ID {$produto_id} no caminho esperado: {$caminho_fisico_completo}");
             // Mantém o placeholder se não encontrar
        }
    }
}

?>

<!-- Content Wrapper. Contém o conteúdo da página -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Detalhes: <?= $nome_produto_titulo ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= build_url('views/dashboard/dashboard.php'); ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= build_url('views/produtos/index.php'); ?>">Produtos</a></li>
                        <li class="breadcrumb-item active">Detalhes</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Mensagens de Alerta -->
            <?php include_once __DIR__ . '/../includes/alert_messages.php'; ?>

            <!-- Card para exibir os detalhes -->
            <div class="card card-solid shadow-sm">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-info-circle mr-1"></i>Informações do Produto</h3>
                     <!-- Botões de Ação no Header -->
                     <div>
                        <a href="<?= build_url('views/produtos/index.php'); ?>" class="btn btn-sm btn-outline-secondary" title="Voltar para a lista">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                        <a href="<?= build_url('views/produtos/edit.php?id=' . $produto_id); ?>" class="btn btn-sm btn-outline-warning" title="Editar Produto">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <a href="<?= build_url('views/produtos/delete.php?id=' . $produto_id); ?>"
                           class="btn btn-sm btn-outline-danger"
                           title="Excluir Produto"
                           onclick="return confirm('Tem certeza que deseja excluir o produto \'<?= addslashes($nome_produto_titulo) ?>\'? Esta ação não pode ser desfeita.');">
                            <i class="fas fa-trash"></i> Excluir
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Coluna da Imagem (Esquerda) -->
                        <div class="col-12 col-md-4 text-center mb-4 mb-md-0">
                             <img src="<?= htmlspecialchars($fotoUrlParaExibir, ENT_QUOTES, 'UTF-8') ?>"
                                  alt="Foto de <?= $nome_produto_titulo ?>"
                                  class="img-fluid rounded shadow-sm"
                                  style="max-height: 350px; border: 1px solid #dee2e6; padding: 5px; background-color: #fff; object-fit: contain;">
                        </div>

                        <!-- Coluna de Detalhes (Direita) -->
                        <div class="col-12 col-md-8">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 col-lg-3">ID:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['id'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Código:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['codigo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Nome:</dt>
                                <dd class="col-sm-8 col-lg-9"><strong><?= $nome_produto_titulo /* Já sanitizado */ ?></strong></dd>

                                <dt class="col-sm-4 col-lg-3">Seção:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['nome_secao'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Categoria:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['nome_categoria'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Subcategoria:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['nome_subcategoria'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Tipo Produto:</dt>
                                <dd class="col-sm-8 col-lg-9">
                                    <span class="badge badge-info"><?= htmlspecialchars($produto_data['tipo_produto'] ?? 'SIMPLES', ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($produto_data['eh_conjunto'])): ?>
                                        <span class="badge badge-primary ml-1"><i class="fas fa-layer-group"></i> Conjunto comercial</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-4 col-lg-3">Controla Estoque:</dt>
                                <dd class="col-sm-8 col-lg-9">
                                    <span class="badge badge-<?= !empty($produto_data['controla_estoque']) ? 'success' : 'secondary' ?>">
                                        <?= !empty($produto_data['controla_estoque']) ? 'Sim' : 'Não' ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-4 col-lg-3">É Conjunto:</dt>
                                <dd class="col-sm-8 col-lg-9">
                                    <span class="badge badge-<?= !empty($produto_data['eh_conjunto']) ? 'primary' : 'secondary' ?>">
                                        <?= !empty($produto_data['eh_conjunto']) ? 'Sim' : 'Não' ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-4 col-lg-3">Descrição:</dt>
                                <dd class="col-sm-8 col-lg-9" style="white-space: pre-wrap;"><?= htmlspecialchars($produto_data['descricao_detalhada'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Dimensões:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['dimensoes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Cor:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['cor'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Material:</dt>
                                <dd class="col-sm-8 col-lg-9"><?= htmlspecialchars($produto_data['material'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Preço Locação:</dt>
                                <dd class="col-sm-8 col-lg-9 text-success"><strong>R$ <?= number_format($produto_data['preco_locacao'] ?? 0, 2, ',', '.') ?></strong></dd>

                                <dt class="col-sm-4 col-lg-3">Preço Venda:</dt>
                                <dd class="col-sm-8 col-lg-9 text-primary"><strong>R$ <?= number_format($produto_data['preco_venda'] ?? 0, 2, ',', '.') ?></strong></dd>

                                <dt class="col-sm-4 col-lg-3">Preço Custo:</dt>
                                <dd class="col-sm-8 col-lg-9 text-muted">R$ <?= number_format($produto_data['preco_custo'] ?? 0, 2, ',', '.') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Disponível Venda:</dt>
                                <dd class="col-sm-8 col-lg-9"><span class="badge badge-<?= !empty($produto_data['disponivel_venda']) ? 'success' : 'secondary' ?>"><?= !empty($produto_data['disponivel_venda']) ? 'Sim' : 'Não' ?></span></dd>

                                <dt class="col-sm-4 col-lg-3">Disponível Locação:</dt>
                                <dd class="col-sm-8 col-lg-9"><span class="badge badge-<?= !empty($produto_data['disponivel_locacao']) ? 'success' : 'secondary' ?>"><?= !empty($produto_data['disponivel_locacao']) ? 'Sim' : 'Não' ?></span></dd>

                                <?php // Seção de Quantidades ?>
                                <dt class="col-sm-12 mt-3 pt-2 border-top">Controle de Quantidades:</dt>
                                <dd class="col-sm-12">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0">
                                            Total em Estoque:
                                            <span class="badge badge-info badge-pill"><?= htmlspecialchars($produto_data['quantidade_total'] ?? 0) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0">
                                            Disponível Agora:
                                            <span class="badge badge-success badge-pill"><?= htmlspecialchars($produto_data['quantidade_disponivel'] ?? 0) ?></span>
                                        </li>
                                         <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0 text-muted">
                                            Reservado:
                                            <span class="badge badge-warning badge-pill"><?= htmlspecialchars($produto_data['quantidade_reservada'] ?? 0) ?></span>
                                        </li>
                                         <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0 text-muted">
                                            Lavanderia:
                                            <span class="badge badge-primary badge-pill"><?= htmlspecialchars($produto_data['quantidade_lavanderia'] ?? 0) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0 text-muted">
                                            Manutenção:
                                            <span class="badge badge-secondary badge-pill"><?= htmlspecialchars($produto_data['quantidade_manutencao'] ?? 0) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0 text-muted">
                                            Extraviado:
                                            <span class="badge badge-danger badge-pill"><?= htmlspecialchars($produto_data['quantidade_extraviada'] ?? 0) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0 text-muted">
                                            Bloqueado:
                                            <span class="badge badge-dark badge-pill"><?= htmlspecialchars($produto_data['quantidade_bloqueada'] ?? 0) ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-0 text-muted">
                                            Vendido:
                                            <span class="badge badge-light badge-pill border"><?= htmlspecialchars($produto_data['quantidade_vendida'] ?? 0) ?></span>
                                        </li>
                                    </ul>
                                </dd>

                                <dt class="col-sm-4 col-lg-3 mt-3 pt-2 border-top">Observações:</dt>
                                <dd class="col-sm-8 col-lg-9 mt-3 pt-2 border-top" style="white-space: pre-wrap;"><?= htmlspecialchars($produto_data['observacoes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>

                                <dt class="col-sm-4 col-lg-3 mt-3">Data Cadastro:</dt>
                                <dd class="col-sm-8 col-lg-9 mt-3 text-muted"><?= formatarDataHora($produto_data['data_cadastro'] ?? null, 'd/m/Y H:i') ?></dd>

                                <dt class="col-sm-4 col-lg-3">Última Atualização:</dt>
                                <dd class="col-sm-8 col-lg-9 text-muted"><?= formatarDataHora($produto_data['ultima_atualizacao'] ?? null, 'd/m/Y H:i') ?></dd>

                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($componentesProduto)): ?>
                        <hr>
                        <div class="card card-outline card-info mt-3">
                            <div class="card-header py-2">
                                <h3 class="card-title mb-0"><i class="fas fa-cubes mr-1"></i> Componentes Técnicos do Produto Composto</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive mb-0">
                                    <table class="table table-sm table-striped table-bordered mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Componente</th>
                                                <th style="width: 120px;" class="text-center">Qtd. por unidade</th>
                                                <th style="width: 110px;" class="text-center">Obrigatório</th>
                                                <th style="width: 120px;" class="text-center">Controla estoque</th>
                                                <th style="width: 100px;" class="text-center">Estoque</th>
                                                <th>Observações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($componentesProduto as $comp): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($comp['nome_produto'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <?php if (!empty($comp['codigo'])): ?>
                                                            <small class="text-muted d-block">Cód: <?= htmlspecialchars($comp['codigo'], ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($comp['tipo_produto'])): ?>
                                                            <small class="badge badge-light border"><?= htmlspecialchars($comp['tipo_produto'], ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center"><?= number_format((float)($comp['quantidade'] ?? 0), 2, ',', '.') ?></td>
                                                    <td class="text-center">
                                                        <span class="badge badge-<?= !empty($comp['obrigatorio']) ? 'success' : 'secondary' ?>">
                                                            <?= !empty($comp['obrigatorio']) ? 'Sim' : 'Não' ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-<?= !empty($comp['controla_estoque']) ? 'success' : 'secondary' ?>">
                                                            <?= !empty($comp['controla_estoque']) ? 'Sim' : 'Não' ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center"><?= htmlspecialchars($comp['quantidade_total'] ?? 0, ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= nl2br(htmlspecialchars($comp['observacoes'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($produto_data['eh_conjunto'])): ?>
                        <hr>
                        <div class="card card-outline card-primary mt-3">
                            <div class="card-header py-2">
                                <h3 class="card-title mb-0"><i class="fas fa-layer-group mr-1"></i> Regras do Conjunto Comercial</h3>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($gruposConjunto)): ?>
                                    <div class="table-responsive mb-0">
                                        <table class="table table-sm table-striped table-bordered mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width: 180px;">Grupo</th>
                                                    <th>Categoria</th>
                                                    <th>Subcategoria</th>
                                                    <th style="width: 130px;" class="text-center">Qtd. por conjunto</th>
                                                    <th style="width: 110px;" class="text-center">Obrigatório</th>
                                                    <th>Observações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gruposConjunto as $grupo): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($grupo['nome_grupo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                        <td><?= htmlspecialchars($grupo['nome_categoria'] ?? 'Todas/Não definida', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars($grupo['nome_subcategoria'] ?? 'Todas/Não definida', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-center"><span class="badge badge-info badge-pill"><?= number_format((float)($grupo['quantidade_por_conjunto'] ?? 0), 2, ',', '.') ?></span></td>
                                                        <td class="text-center">
                                                            <span class="badge badge-<?= !empty($grupo['obrigatorio']) ? 'success' : 'secondary' ?>">
                                                                <?= !empty($grupo['obrigatorio']) ? 'Sim' : 'Não' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= nl2br(htmlspecialchars($grupo['observacoes'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning m-3 mb-3">
                                        Este produto está marcado como conjunto comercial, mas ainda não possui regras cadastradas.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- /.card-body -->
                 <div class="card-footer text-right bg-light">
                     <a href="<?= build_url('views/produtos/index.php'); ?>" class="btn btn-secondary mr-2">
                         <i class="fas fa-arrow-left"></i> Voltar para a Lista
                     </a>
                      <a href="<?= build_url('views/produtos/edit.php?id=' . $produto_id); ?>" class="btn btn-warning">
                         <i class="fas fa-edit"></i> Editar este Produto
                     </a>
                 </div>
            </div>
            <!-- /.card -->
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php
// Incluir o Rodapé
include_once __DIR__ . '/../includes/footer.php';
?>