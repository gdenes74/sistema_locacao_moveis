<?php
class Produto {
    // Propriedades correspondentes aos campos no banco de dados
    private $conn;
    private $table_name = "produtos";

    // Propriedades públicas para os atributos do produto
    public $id;
    public $subcategoria_id;
    public $codigo;
    public $nome_produto;
    public $descricao_detalhada;
    public $dimensoes;
    public $cor;
    public $material;
    public $tipo_produto;
    public $controla_estoque;
    public $quantidade_total;
    public $preco_locacao;
    public $preco_venda;
    public $preco_custo;
    public $disponivel_venda;
    public $disponivel_locacao;
    public $foto_path;
    public $observacoes;
    public $data_cadastro;
    public $ultima_atualizacao;

    // Construtor com conexão ao banco de dados
    public function __construct($db) {
        $this->conn = $db;

        // Defaults compatíveis com o banco e com o sistema antigo
        $this->tipo_produto = 'SIMPLES';
        $this->controla_estoque = 1;
        $this->quantidade_total = 0;
        $this->preco_locacao = 0.00;
        $this->preco_venda = 0.00;
        $this->preco_custo = 0.00;
        $this->disponivel_venda = 1;
        $this->disponivel_locacao = 1;
    }

    // Método mágico __set para compatibilidade com formulários
    public function __set($name, $value) {
        // Sanitização básica para evitar injeções ou valores inválidos
        if (property_exists($this, $name)) {
            switch ($name) {
                case 'id':
                case 'subcategoria_id':
                case 'quantidade_total':
                    $this->$name = (int)$value;
                    break;

                case 'preco_locacao':
                case 'preco_venda':
                case 'preco_custo':
                    $this->$name = $this->normalizarMoeda($value);
                    break;

                case 'disponivel_venda':
                case 'disponivel_locacao':
                case 'controla_estoque':
                    $this->$name = ($value == '1' || $value === true || $value === 'on') ? 1 : 0;
                    break;

                case 'tipo_produto':
                    $this->$name = $this->normalizarTipoProduto($value);
                    break;

                default:
                    $this->$name = ($value !== null && !is_array($value)) ? trim($value) : $value;
            }
        }
    }

    /**
     * Normaliza valores monetários vindos do formulário.
     * Aceita: 6, 6.00, 6,00, R$ 6,00, 1.200,50.
     */
    private function normalizarMoeda($valor): float {
        if ($valor === null || $valor === '') {
            return 0.00;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float)$valor;
        }

        if (is_string($valor)) {
            $valor = trim($valor);
            $valor = str_replace('R$', '', $valor);
            $valor = str_replace(' ', '', $valor);

            $temVirgula = strpos($valor, ',') !== false;
            $temPonto = strpos($valor, '.') !== false;

            if ($temVirgula && $temPonto) {
                // Formato brasileiro com milhar: 1.200,50
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } elseif ($temVirgula) {
                // Formato brasileiro simples: 6,00
                $valor = str_replace(',', '.', $valor);
            } else {
                // Formato decimal americano ou inteiro: 6.00 / 6
                // Mantém o ponto como separador decimal.
                $valor = preg_replace('/[^0-9.\-]/', '', $valor);
            }
        }

        return (float)$valor;
    }

    private function normalizarTipoProduto($tipo): string {
        $tipo = strtoupper(trim((string)$tipo));
        $tiposPermitidos = ['SIMPLES', 'COMPOSTO', 'COMPONENTE', 'SERVICO'];
        return in_array($tipo, $tiposPermitidos, true) ? $tipo : 'SIMPLES';
    }

    private function bindProdutoParams(PDOStatement $stmt): void {
        $stmt->bindValue(':subcategoria_id', (int)$this->subcategoria_id, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $this->codigo !== '' ? $this->codigo : null, PDO::PARAM_STR);
        $stmt->bindValue(':nome_produto', $this->nome_produto, PDO::PARAM_STR);
        $stmt->bindValue(':descricao_detalhada', $this->descricao_detalhada ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':dimensoes', $this->dimensoes ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':cor', $this->cor ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':material', $this->material ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':tipo_produto', $this->normalizarTipoProduto($this->tipo_produto ?? 'SIMPLES'), PDO::PARAM_STR);
        $stmt->bindValue(':controla_estoque', (int)($this->controla_estoque ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':quantidade_total', (int)($this->quantidade_total ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':preco_locacao', number_format($this->normalizarMoeda($this->preco_locacao ?? 0.00), 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':preco_venda', number_format($this->normalizarMoeda($this->preco_venda ?? 0.00), 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':preco_custo', number_format($this->normalizarMoeda($this->preco_custo ?? 0.00), 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':disponivel_venda', (int)($this->disponivel_venda ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':disponivel_locacao', (int)($this->disponivel_locacao ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':foto_path', $this->foto_path ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':observacoes', $this->observacoes ?? null, PDO::PARAM_STR);
    }

    /**
     * Lista todos os produtos, com filtros opcionais.
     * @param array $filtros Array associativo com filtros como ['pesquisar' => 'termo', 'categoria_id' => ID, 'subcategoria_id' => ID]
     * @param string $orderBy Ordenação (ex: "p.nome_produto ASC"). Deve ser sanitizado antes de passar.
     * @return PDOStatement|bool Retorna um PDOStatement em sucesso ou false em falha.
     */
    public function listarTodos(array $filtros = [], string $orderBy = "p.nome_produto ASC") {
        // Monta a query base com JOINs para nomes de categoria/subcategoria
        $queryBase = "SELECT
                        p.*,
                        s.nome as nome_subcategoria,
                        c.nome as nome_categoria,
                        se.nome as nome_secao
                      FROM
                        " . $this->table_name . " p
                        LEFT JOIN subcategorias s ON p.subcategoria_id = s.id
                        LEFT JOIN categorias c ON s.categoria_id = c.id
                        LEFT JOIN secoes se ON c.secao_id = se.id";

        $whereConditions = [];
        $bindParams = [];

        // Filtro por nome (LIKE em múltiplos termos)
        if (!empty($filtros['pesquisar'])) {
            $termos = explode(" ", trim($filtros['pesquisar']));
            $itemConditions = []; // Renomeado para clareza, para cada item (nome OU código)
            foreach ($termos as $i => $termo) {
                if (!empty($termo)) {
                    $placeholderNome = ":termoNome{$i}";
                    $placeholderCodigo = ":termoCodigo{$i}";

                    // Condição para buscar o termo no NOME OU no CÓDIGO
                    $itemConditions[] = "(p.nome_produto LIKE {$placeholderNome} OR p.codigo LIKE {$placeholderCodigo})";

                    $bindParams[$placeholderNome] = "%{$termo}%";
                    $bindParams[$placeholderCodigo] = "%{$termo}%";
                }
            }
            if (!empty($itemConditions)) {
                // Se houver múltiplos termos na pesquisa (ex: "toalha azul"), eles devem TODOS estar presentes (AND)
                // mas cada termo pode ser encontrado OU no nome OU no código.
                $whereConditions[] = "(" . implode(" AND ", $itemConditions) . ")";
            }
        }

        // Filtro por ID da Categoria
        if (!empty($filtros['categoria_id'])) {
            $whereConditions[] = "c.id = :categoria_id";
            $bindParams[':categoria_id'] = (int)$filtros['categoria_id'];
        }

        // Filtro por ID da Subcategoria
        if (!empty($filtros['subcategoria_id'])) {
            $whereConditions[] = "s.id = :subcategoria_id";
            $bindParams[':subcategoria_id'] = (int)$filtros['subcategoria_id'];
        }

        // Filtro por tipo de produto, quando usado futuramente
        if (!empty($filtros['tipo_produto'])) {
            $whereConditions[] = "p.tipo_produto = :tipo_produto";
            $bindParams[':tipo_produto'] = $this->normalizarTipoProduto($filtros['tipo_produto']);
        }

        // Filtro por controle de estoque, quando usado futuramente
        if (isset($filtros['controla_estoque']) && $filtros['controla_estoque'] !== '') {
            $whereConditions[] = "p.controla_estoque = :controla_estoque";
            $bindParams[':controla_estoque'] = (int)$filtros['controla_estoque'];
        }

        // Monta a cláusula WHERE
        if (!empty($whereConditions)) {
            $queryBase .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Ordenação segura
        $allowedOrderBy = ['p.nome_produto', 'p.id', 'c.nome', 's.nome', 'p.codigo', 'p.quantidade_total', 'p.tipo_produto'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : "p.nome_produto ASC"; // Default seguro
        $queryBase .= " ORDER BY " . $orderBy;

        try {
            $stmt = $this->conn->prepare($queryBase);

            // Bind dos parâmetros
            foreach ($bindParams as $placeholder => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($placeholder, $value, $paramType);
            }

            if ($stmt->execute()) {
                return $stmt; // Sucesso
            } else {
                error_log("[Produto::listarTodos] Erro ao executar query: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Produto::listarTodos] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um único produto pelo ID e retorna como ARRAY ASSOCIATIVO.
     * Ideal para preencher formulários de edição.
     * @param int $id O ID do produto.
     * @return array|null Retorna um array com os dados do produto ou null se não encontrado.
     */
    public function lerPorId(int $id): ?array {
        $query = "SELECT
                    p.*,
                    sub.nome as nome_subcategoria,
                    cat.id as categoria_id, cat.nome as nome_categoria,
                    sec.id as secao_id, sec.nome as nome_secao
                FROM
                    " . $this->table_name . " p
                    LEFT JOIN subcategorias sub ON p.subcategoria_id = sub.id
                    LEFT JOIN categorias cat ON sub.categoria_id = cat.id
                    LEFT JOIN secoes sec ON cat.secao_id = sec.id
                WHERE
                    p.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC); // Retorna como array
            }
        } catch (PDOException $exception) {
            error_log("Erro ao ler produto por ID {$id}: " . $exception->getMessage());
        }
        return null; // Retorna null se não encontrar ou der erro
    }

    /**
     * Cria um novo produto no banco de dados.
     * @return bool True em sucesso, false em falha.
     */
    public function criar(): bool {
        // Validações básicas
        if (empty($this->nome_produto) || empty($this->subcategoria_id)) {
            error_log("Tentativa de criar produto sem nome ou subcategoria.");
            return false;
        }

        // Query INSERT
        $query = "INSERT INTO " . $this->table_name . "
                  (
                    subcategoria_id, codigo, nome_produto, descricao_detalhada, dimensoes,
                    cor, material, tipo_produto, controla_estoque, quantidade_total,
                    preco_locacao, preco_venda, preco_custo,
                    disponivel_venda, disponivel_locacao, foto_path, observacoes, data_cadastro
                  )
                  VALUES
                  (
                    :subcategoria_id, :codigo, :nome_produto, :descricao_detalhada, :dimensoes,
                    :cor, :material, :tipo_produto, :controla_estoque, :quantidade_total,
                    :preco_locacao, :preco_venda, :preco_custo,
                    :disponivel_venda, :disponivel_locacao, :foto_path, :observacoes, NOW()
                  )";

        $stmt = $this->conn->prepare($query);

        // Bind dos parâmetros com sanitização
        $this->bindProdutoParams($stmt);

        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            } else {
                error_log("[Produto::criar] Erro ao executar INSERT: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Produto::criar] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um produto existente.
     * Assume que as propriedades do objeto já foram preenchidas.
     * @return bool True em sucesso, false em falha.
     */
    public function atualizar(): bool {
        // Validações básicas
        if (empty($this->id) || !filter_var($this->id, FILTER_VALIDATE_INT)) {
            error_log("Erro ao atualizar: ID do produto inválido ou ausente.");
            return false;
        }
        if (empty($this->nome_produto) || empty($this->subcategoria_id)) {
            error_log("Tentativa de atualizar produto (ID: {$this->id}) sem nome ou subcategoria.");
            return false;
        }

        // Query UPDATE
        $query = "UPDATE " . $this->table_name . "
                  SET
                    subcategoria_id = :subcategoria_id,
                    codigo = :codigo,
                    nome_produto = :nome_produto,
                    descricao_detalhada = :descricao_detalhada,
                    dimensoes = :dimensoes,
                    cor = :cor,
                    material = :material,
                    tipo_produto = :tipo_produto,
                    controla_estoque = :controla_estoque,
                    quantidade_total = :quantidade_total,
                    preco_locacao = :preco_locacao,
                    preco_venda = :preco_venda,
                    preco_custo = :preco_custo,
                    disponivel_venda = :disponivel_venda,
                    disponivel_locacao = :disponivel_locacao,
                    foto_path = :foto_path,
                    observacoes = :observacoes,
                    ultima_atualizacao = NOW()
                  WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Bind dos parâmetros
        $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
        $this->bindProdutoParams($stmt);

        try {
            if ($stmt->execute()) {
                return true;
            } else {
                error_log("[Produto::atualizar] Erro ao executar UPDATE: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Produto::atualizar] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui um produto do banco de dados.
     * @param int $id ID do produto a ser excluído
     * @return bool True em sucesso, false em falha
     */
    public function excluir(int $id): bool {
        // produto_composicao tem ON DELETE CASCADE para produto_pai_id,
        // mas produto_filho_id não tem cascade. Removemos vínculos onde ele é filho para evitar FK travando exclusão.
        try {
            $this->conn->beginTransaction();

            $stmtCompFilho = $this->conn->prepare("DELETE FROM produto_composicao WHERE produto_filho_id = :id");
            $stmtCompFilho->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtCompFilho->execute();

            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if (!$resultado) {
                error_log("[Produto::excluir] Erro ao executar DELETE: " . implode(" - ", $stmt->errorInfo()));
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("[Produto::excluir] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista produtos que podem ser usados como componentes.
     * Mantém COMPONENTE e SIMPLES para não travar produtos antigos ainda não classificados.
     */
    public function listarProdutosParaComposicao(?int $ignorarProdutoId = null): array {
        $query = "SELECT
                    id,
                    codigo,
                    nome_produto,
                    tipo_produto,
                    controla_estoque,
                    quantidade_total,
                    preco_locacao
                  FROM " . $this->table_name . "
                  WHERE ativo = ativo";

        // A tabela produtos atual não tem campo ativo. A condição acima é removida logo abaixo por segurança de compatibilidade.
        $query = "SELECT
                    id,
                    codigo,
                    nome_produto,
                    tipo_produto,
                    controla_estoque,
                    quantidade_total,
                    preco_locacao
                  FROM " . $this->table_name . "
                  WHERE tipo_produto IN ('SIMPLES', 'COMPONENTE', 'SERVICO', 'COMPOSTO')";

        if (!empty($ignorarProdutoId)) {
            $query .= " AND id <> :ignorarProdutoId";
        }

        $query .= " ORDER BY nome_produto ASC";

        try {
            $stmt = $this->conn->prepare($query);
            if (!empty($ignorarProdutoId)) {
                $stmt->bindValue(':ignorarProdutoId', (int)$ignorarProdutoId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[Produto::listarProdutosParaComposicao] PDOException: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista componentes de um produto composto.
     */
    public function listarComponentes(int $produtoPaiId): array {
        $query = "SELECT
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
                  ORDER BY filho.nome_produto ASC";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[Produto::listarComponentes] PDOException: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Adiciona um componente ao produto pai.
     */
    public function adicionarComponente(
        int $produtoPaiId,
        int $produtoFilhoId,
        float $quantidade = 1.00,
        int $obrigatorio = 1,
        ?string $observacoes = null
    ): bool {
        if ($produtoPaiId <= 0 || $produtoFilhoId <= 0 || $produtoPaiId === $produtoFilhoId) {
            return false;
        }

        if ($quantidade <= 0) {
            $quantidade = 1.00;
        }

        // Evita duplicar o mesmo componente no mesmo produto pai.
        $queryExiste = "SELECT id FROM produto_composicao
                        WHERE produto_pai_id = :produto_pai_id
                          AND produto_filho_id = :produto_filho_id
                        LIMIT 1";

        try {
            $stmtExiste = $this->conn->prepare($queryExiste);
            $stmtExiste->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
            $stmtExiste->bindValue(':produto_filho_id', $produtoFilhoId, PDO::PARAM_INT);
            $stmtExiste->execute();

            if ($stmtExiste->rowCount() > 0) {
                $idExistente = (int)$stmtExiste->fetchColumn();
                return $this->atualizarComponente($idExistente, $quantidade, $obrigatorio, $observacoes);
            }

            $query = "INSERT INTO produto_composicao
                      (produto_pai_id, produto_filho_id, quantidade, obrigatorio, observacoes, data_cadastro)
                      VALUES
                      (:produto_pai_id, :produto_filho_id, :quantidade, :obrigatorio, :observacoes, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
            $stmt->bindValue(':produto_filho_id', $produtoFilhoId, PDO::PARAM_INT);
            $stmt->bindValue(':quantidade', number_format($quantidade, 2, '.', ''), PDO::PARAM_STR);
            $stmt->bindValue(':obrigatorio', (int)$obrigatorio, PDO::PARAM_INT);
            $stmt->bindValue(':observacoes', $observacoes, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("[Produto::adicionarComponente] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um componente já vinculado.
     */
    public function atualizarComponente(
        int $composicaoId,
        float $quantidade = 1.00,
        int $obrigatorio = 1,
        ?string $observacoes = null
    ): bool {
        if ($composicaoId <= 0) {
            return false;
        }

        if ($quantidade <= 0) {
            $quantidade = 1.00;
        }

        $query = "UPDATE produto_composicao
                  SET quantidade = :quantidade,
                      obrigatorio = :obrigatorio,
                      observacoes = :observacoes
                  WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $composicaoId, PDO::PARAM_INT);
            $stmt->bindValue(':quantidade', number_format($quantidade, 2, '.', ''), PDO::PARAM_STR);
            $stmt->bindValue(':obrigatorio', (int)$obrigatorio, PDO::PARAM_INT);
            $stmt->bindValue(':observacoes', $observacoes, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("[Produto::atualizarComponente] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove um componente pelo ID da composição.
     */
    public function removerComponente(int $composicaoId): bool {
        if ($composicaoId <= 0) {
            return false;
        }

        $query = "DELETE FROM produto_composicao WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $composicaoId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("[Produto::removerComponente] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove todos os componentes de um produto pai.
     */
    public function removerTodosComponentes(int $produtoPaiId): bool {
        if ($produtoPaiId <= 0) {
            return false;
        }

        $query = "DELETE FROM produto_composicao WHERE produto_pai_id = :produto_pai_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("[Produto::removerTodosComponentes] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Salva a composição completa de um produto pai.
     * Espera array de itens com: produto_filho_id, quantidade, obrigatorio, observacoes.
     */
    public function salvarComposicao(int $produtoPaiId, array $componentes): bool {
        if ($produtoPaiId <= 0) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmtDelete = $this->conn->prepare("DELETE FROM produto_composicao WHERE produto_pai_id = :produto_pai_id");
            $stmtDelete->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
            $stmtDelete->execute();

            $queryInsert = "INSERT INTO produto_composicao
                            (produto_pai_id, produto_filho_id, quantidade, obrigatorio, observacoes, data_cadastro)
                            VALUES
                            (:produto_pai_id, :produto_filho_id, :quantidade, :obrigatorio, :observacoes, NOW())";
            $stmtInsert = $this->conn->prepare($queryInsert);

            $filhosJaInseridos = [];

            foreach ($componentes as $componente) {
                $produtoFilhoId = isset($componente['produto_filho_id']) ? (int)$componente['produto_filho_id'] : 0;

                if ($produtoFilhoId <= 0 || $produtoFilhoId === $produtoPaiId) {
                    continue;
                }

                if (isset($filhosJaInseridos[$produtoFilhoId])) {
                    continue;
                }

                $quantidade = isset($componente['quantidade']) ? (float)$componente['quantidade'] : 1.00;
                if ($quantidade <= 0) {
                    $quantidade = 1.00;
                }

                $obrigatorio = isset($componente['obrigatorio']) ? (int)$componente['obrigatorio'] : 1;
                $observacoes = $componente['observacoes'] ?? null;

                $stmtInsert->bindValue(':produto_pai_id', $produtoPaiId, PDO::PARAM_INT);
                $stmtInsert->bindValue(':produto_filho_id', $produtoFilhoId, PDO::PARAM_INT);
                $stmtInsert->bindValue(':quantidade', number_format($quantidade, 2, '.', ''), PDO::PARAM_STR);
                $stmtInsert->bindValue(':obrigatorio', $obrigatorio, PDO::PARAM_INT);
                $stmtInsert->bindValue(':observacoes', $observacoes, PDO::PARAM_STR);
                $stmtInsert->execute();

                $filhosJaInseridos[$produtoFilhoId] = true;
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("[Produto::salvarComposicao] PDOException: " . $e->getMessage());
            return false;
        }
    }
}
?>
