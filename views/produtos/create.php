<?php
// ADICIONE ESTAS 3 LINHAS NO INÍCIO
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_level'] = 'admin';

// Incluir arquivos essenciais
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Produto.php';
require_once __DIR__ . '/../../models/Secao.php';
require_once __DIR__ . '/../../models/Categoria.php';
require_once __DIR__ . '/../../models/Subcategoria.php';

// Inicializa a conexão e os modelos
$database = new Database();
$conn = $database->getConnection();

$produto = new Produto($conn);
$secaoModel = new Secao($conn);
$categoriaModel = new Categoria($conn);
$subcategoriaModel = new Subcategoria($conn);

$page_title = "Adicionar Novo Produto";
$error = null;
$success = null;

/**
 * Normaliza valores monetários vindos do formulário.
 * Aceita: 6, 6.00, 6,00, R$ 6,00, 1.200,50.
 */
function normalizarMoedaProdutoCreate($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.00;
    }

    if (is_numeric($valor)) {
        return (float)$valor;
    }

    $valor = str_replace('R$', '', (string)$valor);
    $valor = trim($valor);
    $valor = str_replace(' ', '', $valor);

    // Se tiver vírgula, considera padrão brasileiro: 1.200,50
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

/**
 * Normaliza quantidade decimal da composição.
 */
function normalizarQuantidadeComposicao($valor): float
{
    if ($valor === null || $valor === '') {
        return 1.00;
    }

    $valor = trim((string)$valor);
    $valor = str_replace(',', '.', $valor);
    $numero = (float)$valor;

    return $numero > 0 ? $numero : 1.00;
}

/**
 * Fallback direto para gravar composição, sem criar nova arquitetura.
 * Se o model Produto.php tiver método salvarComposicao(), o código usa o model.
 * Caso contrário, grava diretamente na tabela produto_composicao.
 */
function salvarComposicaoProdutoCreate(PDO $conn, Produto $produtoModel, int $produtoPaiId, array $componentes): void
{
    $linhas = [];

    foreach ($componentes as $linha) {
        $produtoFilhoId = isset($linha['produto_filho_id']) ? (int)$linha['produto_filho_id'] : 0;

        if ($produtoFilhoId <= 0 || $produtoFilhoId === $produtoPaiId) {
            continue;
        }

        $linhas[] = [
            'produto_filho_id' => $produtoFilhoId,
            'quantidade' => normalizarQuantidadeComposicao($linha['quantidade'] ?? 1),
            'obrigatorio' => isset($linha['obrigatorio']) ? 1 : 0,
            'observacoes' => isset($linha['observacoes']) && trim($linha['observacoes']) !== '' ? trim($linha['observacoes']) : null,
        ];
    }

    if (empty($linhas)) {
        return;
    }

    if (method_exists($produtoModel, 'salvarComposicao')) {
        $produtoModel->salvarComposicao($produtoPaiId, $linhas);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO produto_composicao
        (produto_pai_id, produto_filho_id, quantidade, obrigatorio, observacoes)
        VALUES
        (:produto_pai_id, :produto_filho_id, :quantidade, :obrigatorio, :observacoes)");

    foreach ($linhas as $linha) {
        $stmt->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
        $stmt->bindValue(':produto_filho_id', (int)$linha['produto_filho_id'], PDO::PARAM_INT);
        $stmt->bindValue(':quantidade', number_format((float)$linha['quantidade'], 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':obrigatorio', (int)$linha['obrigatorio'], PDO::PARAM_INT);
        $stmt->bindValue(':observacoes', $linha['observacoes'], PDO::PARAM_STR);
        $stmt->execute();
    }
}

// Carrega as Seções, Categorias, Subcategorias e Produtos para composição
try {
    $secoes = $secaoModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $categorias = $categoriaModel->listar()->fetchAll(PDO::FETCH_ASSOC);
    $subcategorias = $subcategoriaModel->listar()->fetchAll(PDO::FETCH_ASSOC);

    $produtosParaComponentesStmt = $produto->listarTodos();
    $produtosParaComponentes = $produtosParaComponentesStmt ? $produtosParaComponentesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $dataHierarchy = json_encode([
        'secoes' => $secoes,
        'categorias' => $categorias,
        'subcategorias' => $subcategorias,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
    error_log("Erro ao carregar hierarquia/produtos: " . $e->getMessage());
    $secoes = $secoes ?? [];
    $categorias = $categorias ?? [];
    $subcategorias = $subcategorias ?? [];
    $produtosParaComponentes = $produtosParaComponentes ?? [];
    $dataHierarchy = json_encode([
        'secoes' => $secoes,
        'categorias' => $categorias,
        'subcategorias' => $subcategorias,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// Processamento do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atribui subcategoria_id
    if (isset($_POST['subcategoria_id']) && !empty($_POST['subcategoria_id'])) {
        $produto->subcategoria_id = (int)$_POST['subcategoria_id'];
    } else {
        $error = "A subcategoria é obrigatória.";
    }

    // Atribui nome_produto
    if (isset($_POST['nome_produto']) && !empty($_POST['nome_produto'])) {
        $produto->nome_produto = trim($_POST['nome_produto']);
    } else {
        $error = "O nome do produto é obrigatório.";
    }

    // Novos campos de tipo/estoque
    $tiposPermitidos = ['SIMPLES', 'COMPOSTO', 'COMPONENTE', 'SERVICO'];
    $tipoProduto = isset($_POST['tipo_produto']) ? strtoupper(trim($_POST['tipo_produto'])) : 'SIMPLES';
    if (!in_array($tipoProduto, $tiposPermitidos, true)) {
        $tipoProduto = 'SIMPLES';
    }

    $produto->tipo_produto = $tipoProduto;
    $produto->controla_estoque = isset($_POST['controla_estoque']) ? (int)$_POST['controla_estoque'] : 1;

    // Atribui outros campos
    $produto->codigo = isset($_POST['codigo']) && !empty($_POST['codigo']) ? trim($_POST['codigo']) : null;
    $produto->descricao_detalhada = isset($_POST['descricao_detalhada']) ? trim($_POST['descricao_detalhada']) : null;
    $produto->dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $produto->cor = isset($_POST['cor']) ? trim($_POST['cor']) : null;
    $produto->material = isset($_POST['material']) ? trim($_POST['material']) : null;
    $produto->quantidade_total = isset($_POST['quantidade_total']) ? (int)$_POST['quantidade_total'] : 0;

    // Campos de preço com normalização local para evitar envio de "R$ 6,00" ao banco
    $produto->preco_locacao = normalizarMoedaProdutoCreate($_POST['preco_locacao'] ?? 0);
    $produto->preco_venda = normalizarMoedaProdutoCreate($_POST['preco_venda'] ?? 0);
    $produto->preco_custo = normalizarMoedaProdutoCreate($_POST['preco_custo'] ?? 0);

    // Checkboxes
    $produto->disponivel_venda = isset($_POST['disponivel_venda']) ? 1 : 0;
    $produto->disponivel_locacao = isset($_POST['disponivel_locacao']) ? 1 : 0;

    // Observações
    $produto->observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : null;

    // Processar upload de foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../assets/uploads/produtos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $foto_name = uniqid('foto_') . '_' . basename($_FILES['foto']['name']);
        $foto_path = $upload_dir . $foto_name;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
            $produto->foto_path = 'assets/uploads/produtos/' . $foto_name;
        } else {
            $error = "Erro ao fazer upload da foto.";
        }
    }

    // Se não houver erro, tenta criar o produto
    if (!$error) {
        try {
            if ($produto->criar()) {
                $novoProdutoId = (int)$produto->id;

                if ($tipoProduto === 'COMPOSTO' && !empty($_POST['componentes']) && is_array($_POST['componentes'])) {
                    salvarComposicaoProdutoCreate($conn, $produto, $novoProdutoId, $_POST['componentes']);
                }

                $_SESSION['success'] = "Produto criado com sucesso!";
                header("Location: index.php");
                exit;
            } else {
                $error = "Erro ao criar o produto. Verifique os logs para mais detalhes.";
            }
        } catch (Exception $e) {
            $error = "Erro ao criar o produto: " . $e->getMessage();
            error_log("[views/produtos/create.php] Erro ao criar produto: " . $e->getMessage());
        }
    }
}

// Inclui o cabeçalho
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Conteúdo principal -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Adicionar Produto</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/dashboard">Início</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/produtos/index.php">Produtos</a></li>
                        <li class="breadcrumb-item active">Adicionar</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Erro!</h5>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Sucesso!</h5>
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Novo Produto</h3>
                </div>

                <form method="POST" action="create.php" enctype="multipart/form-data">
                    <div class="card-body">

                        <!-- Dropdowns dependentes -->
                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="secao_id">Seção *</label>
                                <select id="secao_id" name="secao_id" class="form-control" required>
                                    <option value="">-- Selecione a Seção --</option>
                                    <?php foreach ($secoes as $secao): ?>
                                        <option value="<?php echo htmlspecialchars($secao['id']); ?>">
                                            <?php echo htmlspecialchars($secao['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="categoria_id">Categoria *</label>
                                <select id="categoria_id" name="categoria_id" class="form-control" required disabled>
                                    <option value="">-- Selecione Seção Primeiro --</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="subcategoria_id">Subcategoria *</label>
                                <select id="subcategoria_id" name="subcategoria_id" class="form-control" required disabled>
                                    <option value="">-- Selecione Categoria Primeiro --</option>
                                </select>
                            </div>
                        </div>

                        <!-- Outros campos do formulário -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="nome_produto">Nome do Produto *</label>
                                    <input type="text" id="nome_produto" name="nome_produto" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="codigo">Código</label>
                                    <input type="text" id="codigo" name="codigo" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="tipo_produto">Tipo do Produto *</label>
                                <select id="tipo_produto" name="tipo_produto" class="form-control" required>
                                    <option value="SIMPLES" selected>SIMPLES - Produto normal</option>
                                    <option value="COMPONENTE">COMPONENTE - Peça/capa/parte usada por outro produto</option>
                                    <option value="COMPOSTO">COMPOSTO - Produto montado por componentes</option>
                                    <option value="SERVICO">SERVIÇO - Sem estoque físico</option>
                                </select>
                                <small class="text-muted">Ex.: capa de pufe = componente; pufe forrado azul = composto.</small>
                            </div>

                            <div class="col-md-4">
                                <label for="controla_estoque">Controla Estoque? *</label>
                                <select id="controla_estoque" name="controla_estoque" class="form-control" required>
                                    <option value="1" selected>Sim</option>
                                    <option value="0">Não</option>
                                </select>
                                <small class="text-muted">Composto pode controlar estoque próprio ou apenas pelos componentes.</small>
                            </div>

                            <div class="col-md-4">
                                <label for="quantidade_total">Quantidade Total *</label>
                                <input type="number" id="quantidade_total" name="quantidade_total" class="form-control" min="0" value="0" required>
                                <small class="text-muted" id="ajuda_quantidade">Para composto sem estoque próprio, deixe 0.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="descricao_detalhada">Descrição Detalhada</label>
                            <textarea id="descricao_detalhada" name="descricao_detalhada" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="dimensoes">Dimensões</label>
                                <input type="text" id="dimensoes" name="dimensoes" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="cor">Cor</label>
                                <input type="text" id="cor" name="cor" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="material">Material</label>
                                <input type="text" id="material" name="material" class="form-control">
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <div class="form-group mt-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="disponivel_venda" name="disponivel_venda" value="1" class="custom-control-input" checked>
                                        <label for="disponivel_venda" class="custom-control-label">Disponível para Venda</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mt-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="disponivel_locacao" name="disponivel_locacao" value="1" class="custom-control-input" checked>
                                        <label for="disponivel_locacao" class="custom-control-label">Disponível para Locação</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="preco_locacao">Preço Locação (R$)</label>
                                <input type="text" id="preco_locacao" name="preco_locacao" class="form-control money" placeholder="0,00">
                            </div>
                            <div class="col-md-4">
                                <label for="preco_venda">Preço Venda (R$)</label>
                                <input type="text" id="preco_venda" name="preco_venda" class="form-control money" placeholder="0,00">
                            </div>
                            <div class="col-md-4">
                                <label for="preco_custo">Preço Custo (R$)</label>
                                <input type="text" id="preco_custo" name="preco_custo" class="form-control money" placeholder="0,00">
                                <small class="text-muted">Referência interna.</small>
                            </div>
                        </div>

                        <div id="box_composicao" class="card card-outline card-info" style="display:none;">
                            <div class="card-header">
                                <h3 class="card-title">Componentes do Produto Composto</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-2">
                                    Use esta área para informar quais produtos físicos serão consumidos por este produto composto.
                                    Ex.: Pufe forrado azul = 1 Pufe Estrutura + 1 Capa pufe azul.
                                </p>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered" id="tabela_componentes">
                                        <thead>
                                            <tr>
                                                <th style="width: 45%;">Componente</th>
                                                <th style="width: 15%;">Qtd usada</th>
                                                <th style="width: 15%;">Obrigatório</th>
                                                <th>Observação</th>
                                                <th style="width: 70px;">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <button type="button" class="btn btn-sm btn-primary" id="btn_adicionar_componente">
                                    <i class="fas fa-plus mr-1"></i> Adicionar Componente
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="foto">Foto do Produto</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/*">
                                <label class="custom-file-label" for="foto" data-browse="Procurar">Escolher arquivo...</label>
                            </div>
                            <small class="form-text text-muted">Máximo 2MB (JPG, PNG, GIF, WebP)</small>
                        </div>

                        <div class="form-group">
                            <label for="observacoes">Observações Internas</label>
                            <textarea id="observacoes" name="observacoes" class="form-control" rows="2"></textarea>
                        </div>

                    </div>

                    <!-- Botões -->
                    <div class="card-footer text-right">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Salvar Produto</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<!-- Inclui o rodapé -->
<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Inicialização do plugin de máscara monetária -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script>
$(document).ready(function() {
    $('.money').maskMoney({
        prefix: 'R$ ',
        allowNegative: false,
        thousands: '.',
        decimal: ',',
        affixesStay: false
    });

    if (typeof bsCustomFileInput !== 'undefined') {
        bsCustomFileInput.init();
    }
});
</script>

<!-- Lógica JavaScript para os dropdowns dependentes e composição -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const dataHierarchy = <?php echo $dataHierarchy; ?>;

    const secaoSelect = document.getElementById("secao_id");
    const categoriaSelect = document.getElementById("categoria_id");
    const subcategoriaSelect = document.getElementById("subcategoria_id");

    secaoSelect.addEventListener("change", function () {
        const secaoId = this.value;

        categoriaSelect.innerHTML = '<option value="">-- Selecione Categoria --</option>';
        categoriaSelect.disabled = true;

        subcategoriaSelect.innerHTML = '<option value="">-- Selecione Subcategoria --</option>';
        subcategoriaSelect.disabled = true;

        if (secaoId) {
            const categorias = dataHierarchy.categorias.filter(cat => cat.secao_id == secaoId);
            categorias.forEach(cat => {
                const option = document.createElement("option");
                option.value = cat.id;
                option.textContent = cat.nome;
                categoriaSelect.appendChild(option);
            });
            categoriaSelect.disabled = false;
        }
    });

    categoriaSelect.addEventListener("change", function () {
        const categoriaId = this.value;

        subcategoriaSelect.innerHTML = '<option value="">-- Selecione Subcategoria --</option>';
        subcategoriaSelect.disabled = true;

        if (categoriaId) {
            const subcategorias = dataHierarchy.subcategorias.filter(sub => sub.categoria_id == categoriaId);
            subcategorias.forEach(sub => {
                const option = document.createElement("option");
                option.value = sub.id;
                option.textContent = sub.nome;
                subcategoriaSelect.appendChild(option);
            });
            subcategoriaSelect.disabled = false;
        }
    });

    const tipoProduto = document.getElementById('tipo_produto');
    const controlaEstoque = document.getElementById('controla_estoque');
    const quantidadeTotal = document.getElementById('quantidade_total');
    const ajudaQuantidade = document.getElementById('ajuda_quantidade');
    const boxComposicao = document.getElementById('box_composicao');
    const tabelaComponentesBody = document.querySelector('#tabela_componentes tbody');
    const btnAdicionarComponente = document.getElementById('btn_adicionar_componente');

    let componenteIndex = 0;

    const produtosOptionsHtml = `<?php foreach ($produtosParaComponentes as $p): ?>
        <option value="<?php echo (int)$p['id']; ?>">
            <?php echo htmlspecialchars(($p['codigo'] ? $p['codigo'] . ' - ' : '') . $p['nome_produto'], ENT_QUOTES, 'UTF-8'); ?>
        </option>
    <?php endforeach; ?>`;

    function atualizarTipoProduto() {
        const tipo = tipoProduto.value;

        if (tipo === 'COMPOSTO') {
            boxComposicao.style.display = 'block';
            controlaEstoque.value = '0';
            quantidadeTotal.value = quantidadeTotal.value || '0';
            ajudaQuantidade.textContent = 'Composto sem estoque próprio: deixe 0. Se o produto pai também for limitador, marque controla estoque = Sim e informe a quantidade.';
        } else if (tipo === 'SERVICO') {
            boxComposicao.style.display = 'none';
            controlaEstoque.value = '0';
            quantidadeTotal.value = '0';
            ajudaQuantidade.textContent = 'Serviço normalmente não controla estoque físico.';
        } else if (tipo === 'COMPONENTE') {
            boxComposicao.style.display = 'none';
            controlaEstoque.value = '1';
            ajudaQuantidade.textContent = 'Componente físico normalmente controla estoque. Ex.: capa de pufe azul.';
        } else {
            boxComposicao.style.display = 'none';
            controlaEstoque.value = '1';
            ajudaQuantidade.textContent = 'Produto simples normalmente controla estoque.';
        }
    }

    function adicionarLinhaComponente() {
        const idx = componenteIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="componentes[${idx}][produto_filho_id]" class="form-control form-control-sm componente-select">
                    <option value="">-- Selecione --</option>
                    ${produtosOptionsHtml}
                </select>
            </td>
            <td>
                <input type="number" name="componentes[${idx}][quantidade]" class="form-control form-control-sm" min="0.01" step="0.01" value="1.00">
            </td>
            <td class="text-center">
                <input type="checkbox" name="componentes[${idx}][obrigatorio]" value="1" checked>
            </td>
            <td>
                <input type="text" name="componentes[${idx}][observacoes]" class="form-control form-control-sm" placeholder="Ex.: consome 1 capa azul">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-xs btn-danger btn-remover-componente">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tabelaComponentesBody.appendChild(tr);
    }

    tipoProduto.addEventListener('change', atualizarTipoProduto);
    btnAdicionarComponente.addEventListener('click', adicionarLinhaComponente);

    tabelaComponentesBody.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remover-componente');
        if (btn) {
            btn.closest('tr').remove();
        }
    });

    atualizarTipoProduto();
});
</script>
