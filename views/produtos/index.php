<?php

use function PHPSTORM_META\type;

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Secao.php';
require_once __DIR__ . '/../../models/Categoria.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

// Garante que a sessão seja iniciada SE AINDA NÃO FOI.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Conexão e Instâncias
$database = new Database();
$conn = $database->getConnection();
if (!$conn) {
    error_log("Falha crítica: Não foi possível conectar ao banco de dados.");
    die("Erro interno no servidor. Por favor, tente novamente mais tarde.");
}

$produto = new Produto($conn);
$secaoModel = new Secao($conn);
$categoriaModel = new Categoria($conn);
$subcategoriaModel = new Subcategoria($conn);

// --- Buscar Opções para Dropdowns de Filtro (Lógica Original Mantida) ---
$listaSecoesFiltro = [];
$listaCategoriasFiltro = [];
$listaSubcategoriasFiltro = [];
$erroCarregarFiltros = null;
try {
    // Busca Seções
    $stmtSec = $secaoModel->listar();
    $listaSecoesFiltro = $stmtSec ? $stmtSec->fetchAll(PDO::FETCH_ASSOC) : [];

    // Busca Categorias
    $stmtCat = $categoriaModel->listar();
    $listaCategoriasFiltro = $stmtCat ? $stmtCat->fetchAll(PDO::FETCH_ASSOC) : [];

    // Busca Subcategorias
    $stmtSub = $subcategoriaModel->listar();
    $listaSubcategoriasFiltro = $stmtSub ? $stmtSub->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (Exception $e) {
    $erroCarregarFiltros = "Erro ao carregar opções de filtro.";
    error_log("[index.php] Erro ao buscar filtros: " . $e->getMessage());
}
// --- Fim da busca por opções de filtro ---


// --- Capturar e Preparar Filtros do $_GET (Lógica Original Mantida e Expandida) ---
$filtros = [];
$filtros['pesquisar']       = isset($_GET['pesquisar']) && trim($_GET['pesquisar']) !== '' ? trim($_GET['pesquisar']) : null;
// Adiciona filtro de seção se ele existir no formulário
$filtros['secao_id']        = isset($_GET['secao_id']) && filter_var($_GET['secao_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['secao_id'] : null;
$filtros['categoria_id']    = isset($_GET['categoria_id']) && filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['categoria_id'] : null;
$filtros['subcategoria_id'] = isset($_GET['subcategoria_id']) && filter_var($_GET['subcategoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['subcategoria_id'] : null;

// Remove filtros nulos/vazios
$filtros = array_filter($filtros, function ($value) { return $value !== null && $value !== ''; });
// --- Fim da captura de filtros ---


// --- Buscar os Produtos USANDO os filtros (Lógica Original Mantida) ---
$orderBy = $_GET['orderBy'] ?? 'p.nome_produto ASC';
$resultados = [];
$numProdutos = 0;

/*
 ==========================================================================
 !! ALERTA MÁXIMO: VERIFIQUE A QUERY EM models/Produto.php -> listarTodos() !!
 ==========================================================================
 ESTA É A CAUSA MAIS PROVÁVEL PARA "MATERIAL" E OUTROS CAMPOS NÃO APARECEREM.
 A query PRECISA TER:
    - SELECT p.id, p.codigo, p.nome_produto, p.dimensoes, p.cor, p.material,
             p.quantidade_total, p.quantidade_disponivel,
             p.preco_locacao, p.preco_venda, p.preco_custo,
             p.disponivel_venda, p.disponivel_locacao, p.foto_path,
             s.nome AS nome_secao,
             c.nome AS nome_categoria,
             sc.nome AS nome_subcategoria
    - E os LEFT JOINs necessários:
             LEFT JOIN secoes s ON p.secao_id = s.id
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN subcategorias sc ON p.subcategoria_id = sc.id
 Se a query não buscar 'p.material', 's.nome', etc., eles NUNCA aparecerão aqui.
 ==========================================================================
*/
$stmtProdutos = $produto->listarTodos($filtros, $orderBy);

if ($stmtProdutos) {
    try {
        $resultados = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
        $numProdutos = count($resultados);
    } catch (PDOException $e) {
         error_log("[index.php] Erro ao fazer fetchAll dos produtos: " . $e->getMessage());
         $_SESSION['error_message'] = "Erro ao buscar dados dos produtos.";
         $resultados = [];
         $numProdutos = 0;
    }
} else {
    error_log("[index.php] Método listarTodos retornou false.");
    if (!isset($_SESSION['error_message'])) {
       $_SESSION['error_message'] = "Ocorreu um erro ao tentar listar os produtos.";
    }
     $resultados = [];
     $numProdutos = 0;
}
// --- Fim da busca de produtos ---

// Incluir cabeçalho e barra lateral (Lógica Original Mantida)
include_once __DIR__ . '/../includes/header.php';


// URL da imagem placeholder (Lógica Original Mantida - Usando build_url se existir)
$placeholderImgUrl = function_exists('build_url')
    ? build_url('assets/img/product_placeholder.png')
    : rtrim(BASE_URL, '/') . '/assets/img/product_placeholder.png';

?>

<!-- Content Wrapper (Estrutura Original Mantida) -->
<div class="content-wrapper">
    <!-- Content Header (Estrutura Original Mantida) -->
    <section class="content-header">
         <div class="container-fluid">
             <div class="row mb-2">
                <div class="col-sm-6"><h1>Gerenciar Produtos</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                         <li class="breadcrumb-item"><a href="<?= function_exists('build_url') ? build_url('views/dashboard/dashboard.php') : '../dashboard/dashboard.php' ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Produtos</li>
                    </ol>
                </div>
            </div>
             <div class="row mb-3">
                <div class="col-12 text-right">
                     <a href="<?= function_exists('build_url') ? build_url('views/produtos/create.php') : 'create.php' ?>" class="btn btn-success"><i class="fas fa-plus mr-1"></i> Novo Produto</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content (Estrutura Original Mantida) -->
    <section class="content">
        <div class="container-fluid">
            <!-- Mensagens de Alerta (Estrutura Original Mantida) -->
            <?php include_once __DIR__ . '/../includes/alert_messages.php'; ?>
            <?php if ($erroCarregarFiltros): ?>
                 <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($erroCarregarFiltros) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                 </div>
            <?php endif; ?>

            <!-- Formulário de Pesquisa (Estrutura Original Mantida - Adicionado Filtro Seção) -->
            <div class="card card-outline card-info mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filtrar Produtos</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    </div>
                </div>
                <div class="card-body p-2">
                     <form method="GET" action="<?= function_exists('build_url') ? build_url('views/produtos/index.php') : 'index.php' ?>">
                        <div class="row align-items-end">
                            <!-- Filtro Nome -->
                            <div class="col-lg-3 col-md-6 form-group mb-2 mb-md-0">
                                <label for="pesquisar" class="mb-1 small text-muted">Nome</label>
                                <input type="text" name="pesquisar" id="pesquisar" class="form-control form-control-sm" placeholder="Digite parte do nome..." value="<?= htmlspecialchars($_GET['pesquisar'] ?? '') ?>">
                            </div>
                             <!-- Filtro Seção -->
                            <div class="col-lg-2 col-md-6 form-group mb-2 mb-md-0">
                                <label for="secao_id_filtro" class="mb-1 small text-muted">Seção</label>
                                <select name="secao_id" id="secao_id_filtro" class="form-control form-control-sm" <?= empty($listaSecoesFiltro) ? 'disabled' : '' ?>>
                                    <option value="">-- Todas --</option>
                                    <?php foreach ($listaSecoesFiltro as $sec): ?>
                                        <option value="<?= $sec['id'] ?>" <?= (isset($_GET['secao_id']) && $_GET['secao_id'] == $sec['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sec['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Filtro Categoria -->
                            <div class="col-lg-2 col-md-6 form-group mb-2 mb-md-0">
                                 <label for="categoria_id_filtro" class="mb-1 small text-muted">Categoria</label>
                                <select name="categoria_id" id="categoria_id_filtro" class="form-control form-control-sm" <?= empty($listaCategoriasFiltro) ? 'disabled' : '' ?>>
                                    <option value="">-- Todas --</option>
                                    <?php foreach ($listaCategoriasFiltro as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= (isset($_GET['categoria_id']) && $_GET['categoria_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Filtro Subcategoria -->
                             <div class="col-lg-3 col-md-6 form-group mb-2 mb-md-0">
                                 <label for="subcategoria_id_filtro" class="mb-1 small text-muted">Subcategoria</label>
                                <select name="subcategoria_id" id="subcategoria_id_filtro" class="form-control form-control-sm" <?= empty($listaSubcategoriasFiltro) ? 'disabled' : '' ?>>
                                    <option value="">-- Todas --</option>
                                     <?php foreach ($listaSubcategoriasFiltro as $sub): ?>
                                        <option value="<?= $sub['id'] ?>" <?= (isset($_GET['subcategoria_id']) && $_GET['subcategoria_id'] == $sub['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sub['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Botões -->
                            <div class="col-lg-2 col-md-12 form-group mb-0 text-right text-lg-left">
                                <button type="submit" class="btn btn-primary btn-sm mr-1" title="Aplicar Filtros"><i class="fas fa-search fa-fw"></i></button>
                                 <a href="<?= function_exists('build_url') ? build_url('views/produtos/index.php') : 'index.php' ?>" class="btn btn-secondary btn-sm" title="Limpar Filtros"><i class="fas fa-times fa-fw"></i></a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Fim Formulário de Pesquisa -->

            <!-- Card da Tabela de Resultados (Estrutura Original Mantida) -->
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                     <h3 class="card-title mb-0">
                        Lista de Produtos
                        <span class="badge badge-pill badge-secondary ml-2" title="Total Encontrado"><?= $numProdutos ?></span>
                        <?= (!empty($filtros)) ? '<span class="badge badge-pill badge-info ml-2">Filtro Ativo</span>' : '' ?>
                    </h3>
                     <div class="card-tools"></div>
                </div>
                <div class="card-body p-0">
                    <?php if ($numProdutos === 0): ?>
                         <div class="alert alert-warning text-center rounded-0 m-0 border-0">
                            <i class="fas fa-info-circle mr-1"></i>
                            <?php if (!empty($filtros)): ?>
                                Nenhum produto encontrado com os filtros aplicados. <a href="<?= function_exists('build_url') ? build_url('views/produtos/index.php') : 'index.php' ?>" class="alert-link">Limpar filtros</a>.
                            <?php else: ?>
                                 Nenhum produto cadastrado ainda. <a href="<?= function_exists('build_url') ? build_url('views/produtos/create.php') : 'create.php' ?>" class="alert-link">Adicionar o primeiro produto</a>.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                           
                            <!-- ## INÍCIO DA TABELA - AGORA COM COLUNAS DO SHOW.PHP + FOTO/AÇÕES ## -->
                            
                            <table class="table table-bordered table-hover table-striped table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <!-- Colunas baseadas EXATAMENTE no show.php + Foto e Ações -->
                                        <th style="width: 50px;" class="text-center align-middle">Foto</th>
                                        <th style="width: 50px;" class="text-center align-middle">ID</th>
                                        <th style="width: 100px;" class="align-middle">Código</th>
                                        <th class="align-middle">Nome</th>
                                        <th style="width: 120px;" class="align-middle">Seção</th>
                                        <th style="width: 120px;" class="align-middle">Categoria</th>
                                        <th style="width: 120px;" class="align-middle">Subcategoria</th>
                                        <th style="width: 120px;" class="align-middle">Dimensões</th>
                                        <th style="width: 100px;" class="align-middle">Cor</th>
                                        <th style="width: 100px;" class="align-middle">Material</th>
                                        <th style="width: 100px;" class="text-right align-middle">Preço Loc.</th>
                                        <th style="width: 100px;" class="text-right align-middle">Preço Venda</th>
                                        <th style="width: 100px;" class="text-right align-middle">Preço Custo</th>
                                        <th style="width: 80px;" class="text-center align-middle">Venda?</th>
                                        <th style="width: 80px;" class="text-center align-middle">Loca?</th>
                                        <th style="width: 110px;" class="text-center align-middle">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Loop original mantido
                                    foreach ($resultados as $row):
                                        // ---- Extração e Sanitização (Lógica Original + Novos Campos) ----
                                        $id = (int)($row['id'] ?? 0);
                                        $foto_path_relativo = trim($row['foto_path'] ?? '');
                                        $codigo = htmlspecialchars(trim($row['codigo'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $nome_produto = htmlspecialchars(trim($row['nome_produto'] ?? 'Sem Nome'), ENT_QUOTES, 'UTF-8');

                                        // *** NOVOS CAMPOS - Verificar se existem em $row (vindo da query) ***
                                        $secao_nome = htmlspecialchars(trim($row['nome_secao'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                        $categoria_nome = htmlspecialchars(trim($row['nome_categoria'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); // Já existia
                                        $subcategoria_nome = htmlspecialchars(trim($row['nome_subcategoria'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); // Já existia
                                        $dimensoes = htmlspecialchars(trim($row['dimensoes'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $cor = htmlspecialchars(trim($row['cor'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        // *** MUITO IMPORTANTE: Usar ?? para evitar erro se 'material' não vier da query ***
                                        $material = htmlspecialchars(trim($row['material'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $preco_locacao_float = (float)($row['preco_locacao'] ?? 0);
                                        $preco_venda_float = (float)($row['preco_venda'] ?? 0);
                                        $preco_custo_float = (float)($row['preco_custo'] ?? 0);

                                        $preco_locacao = 'R$ ' . number_format($preco_locacao_float, 2, ',', '.'); // Já existia
                                        $preco_venda = 'R$ ' . number_format($preco_venda_float, 2, ',', '.');
                                        $preco_custo = 'R$ ' . number_format($preco_custo_float, 2, ',', '.');

                                        $disponivel_venda = filter_var($row['disponivel_venda'] ?? false, FILTER_VALIDATE_BOOLEAN); // Já existia
                                        $disponivel_locacao = filter_var($row['disponivel_locacao'] ?? false, FILTER_VALIDATE_BOOLEAN); // Já existia

                                        // ---- Lógica da Foto (IDÊNTICA À ORIGINAL do seu index.php) ----
                                        $fotoUrlParaExibir = $placeholderImgUrl;
                                        if (!empty($foto_path_relativo)) {
                                            $caminho_relativo_limpo = ltrim($foto_path_relativo, '/');

                                            // Usa PROJECT_ROOT se definido, senão dirname para robustez
                                            $raizProjeto = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2);
                                            // Constrói caminho físico usando DIRECTORY_SEPARATOR para compatibilidade
                                            $caminho_fisico_completo = rtrim($raizProjeto, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $caminho_relativo_limpo);

                                            if (file_exists($caminho_fisico_completo)) {
                                                // Usa build_url se disponível para montar a URL completa, senão concatena com BASE_URL
                                                $fotoUrlParaExibir = function_exists('build_url')
                                                    ? build_url($caminho_relativo_limpo)
                                                    : rtrim(BASE_URL, '/') . '/' . $caminho_relativo_limpo;
                                            } else {
                                                 error_log("[index.php] Foto não encontrada no caminho físico: " . $caminho_fisico_completo . " (Produto ID: {$id})");
                                                 // Mantém $fotoUrlParaExibir como $placeholderImgUrl
                                            }
                                        }
                                        // --- Fim da lógica da foto ---

                                        // ---- Preparação para JS (onclick confirm) (IDÊNTICA À ORIGINAL) ----
                                        $nomeProdutoJsEscapado = htmlspecialchars($nome_produto, ENT_QUOTES, 'UTF-8');
                                        $mensagemConfirmacaoJs = "Tem certeza que deseja excluir o produto '" . addslashes($nome_produto) . "'? Esta ação não pode ser desfeita.";
                                        $onClickConfirm = 'return confirm(\'' . $mensagemConfirmacaoJs . '\');';
                                        // --- Fim da preparação JS ---

                                        // URLs de Ação (IDÊNTICA À ORIGINAL - usando build_url se existir)
                                        $url_show = (function_exists('build_url') ? build_url("views/produtos/show.php?id={$id}") : "show.php?id={$id}");
                                        $url_edit = (function_exists('build_url') ? build_url("views/produtos/edit.php?id={$id}") : "edit.php?id={$id}");
                                        $url_delete = (function_exists('build_url') ? build_url("views/produtos/delete.php?id={$id}") : "delete.php?id={$id}");
                                        // --- Fim URLs Ação ---
                                    ?>
                                        <tr>
                                            <!-- Coluna Foto (Lógica Original Mantida) -->
                                            <td class="text-center align-middle p-1">
                                                <a href="#" data-toggle="modal" data-target="#modalFoto<?= $id ?>" title="Ver foto: <?= $nomeProdutoJsEscapado ?>">
                                                    <img src="<?= htmlspecialchars($fotoUrlParaExibir, ENT_QUOTES, 'UTF-8') ?>" alt="Foto" class="img-thumbnail" style="width: 40px; height: 40px; object-fit: cover;">
                                                </a>
                                            </td>
                                            <!-- Colunas de Dados (Agora incluindo as do show.php) -->
                                            <td class="text-center align-middle"><?= $id ?></td>
                                            <td class="align-middle"><?= $codigo ?></td>
                                            <td class="align-middle"><?= $nome_produto ?></td>
                                            <td class="align-middle"><?= $secao_nome ?></td>
                                            <td class="align-middle"><?= $categoria_nome ?></td>
                                            <td class="align-middle"><?= $subcategoria_nome ?></td>
                                            <td class="align-middle"><?= $dimensoes ?></td>
                                            <td class="align-middle"><?= $cor ?></td>
                                            <td class="align-middle"><?= $material ?></td>
                                            <td class="text-right align-middle"><?= $preco_locacao ?></td>
                                            <td class="text-right align-middle"><?= $preco_venda ?></td>
                                            <td class="text-right align-middle"><?= $preco_custo ?></td>
                                            <td class="text-center align-middle"><span class="badge badge-pill badge-<?= $disponivel_venda ? 'success' : 'secondary' ?>"><?= $disponivel_venda ? 'Sim' : 'Não' ?></span></td>
                                            <td class="text-center align-middle"><span class="badge badge-pill badge-<?= $disponivel_locacao ? 'success' : 'secondary' ?>"><?= $disponivel_locacao ? 'Sim' : 'Não' ?></span></td>
                                            <!-- Coluna Ações (Lógica Original Mantida) -->
                                            <td class="text-center align-middle">
                                                <a href="<?= htmlspecialchars($url_show, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-info btn-xs m-1" title="Ver Detalhes"><i class="fas fa-eye fa-fw"></i></a>
                                                <a href="<?= htmlspecialchars($url_edit, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-warning btn-xs m-1" title="Editar"><i class="fas fa-edit fa-fw"></i></a>
                                                <a href="<?= htmlspecialchars($url_delete, ENT_QUOTES, 'UTF-8') ?>"
                                                   class="btn btn-danger btn-xs m-1"
                                                   title="Excluir"
                                                   onclick="<?= $onClickConfirm ?>">
                                                    <i class="fas fa-trash fa-fw"></i>
                                                </a>
                                            </td>
                                        </tr>

                                        <!-- Modal da Foto (IDÊNTICO AO ORIGINAL do seu index.php) -->
                                        <div class="modal fade" id="modalFoto<?= $id ?>" tabindex="-1" aria-labelledby="modalFotoLabel<?= $id ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header py-2">
                                                        <h5 class="modal-title" id="modalFotoLabel<?= $id ?>"><?= $nomeProdutoJsEscapado ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                                                    </div>
                                                    <div class="modal-body text-center p-2">
                                                        <img src="<?= htmlspecialchars($fotoUrlParaExibir, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de <?= $nomeProdutoJsEscapado ?>" class="img-fluid" style="max-height: 75vh;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    // Fim do loop original mantido
                                    endforeach;
                                    ?>
                                </tbody>
                            </table>
                            <!-- #################################################################### -->
                            <!-- ##          FIM DA TABELA COM COLUNAS CORRIGIDAS                ## -->
                        </div><!-- /.table-responsive -->
                    <?php endif; // Fim do if $numProdutos > 0 ?>
                </div><!-- /.card-body -->
                 <?php if ($numProdutos > 0): // Footer original mantido ?>
                 <div class="card-footer clearfix py-2">
                    <span class="float-left text-muted small">Exibindo <?= $numProdutos; ?> produto(s)</span>
                     <!-- Paginação (se/quando implementada) -->
                 </div>
                 <?php endif; ?>
            </div><!-- /.card -->
        </div><!-- /.container-fluid -->
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php include_once __DIR__ . '/../includes/footer.php'; // Rodapé original mantido ?>
<!-- Data/Hora Atual (Oculto) -->
<div style="display:none;">
    UTC: 04/05/2025 21:31:39 (UTC)
    Brasília: 04/05/2025 18:31:39 (UTC-3)
</div>