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
                    // Converte valores monetários para float
                    if (is_string($value)) {
                        $value = str_replace('.', '', $value); // Remove ponto (milhar)
                        $value = str_replace(',', '.', $value); // Troca vírgula por ponto (decimal)
                    }
                    $this->$name = floatval($value);
                    break;
                case 'disponivel_venda':
                case 'disponivel_locacao':
                    $this->$name = ($value == '1' || $value === true) ? 1 : 0;
                    break;
                default:
                    $this->$name = ($value !== null && !is_array($value)) ? trim($value) : $value;
            }
        }
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
        // ... dentro do método listarTodos() ...
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
// ... resto do método listarTodos() ...

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

        // Monta a cláusula WHERE
        if (!empty($whereConditions)) {
            $queryBase .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Ordenação segura
        $allowedOrderBy = ['p.nome_produto', 'p.id', 'c.nome', 's.nome', 'p.codigo', 'p.quantidade_total'];
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
                    cor, material, quantidade_total, preco_locacao, preco_venda, preco_custo,
                    disponivel_venda, disponivel_locacao, foto_path, observacoes, data_cadastro
                  )
                  VALUES
                  (
                    :subcategoria_id, :codigo, :nome_produto, :descricao_detalhada, :dimensoes,
                    :cor, :material, :quantidade_total, :preco_locacao, :preco_venda, :preco_custo,
                    :disponivel_venda, :disponivel_locacao, :foto_path, :observacoes, NOW()
                  )";

        $stmt = $this->conn->prepare($query);

        // Bind dos parâmetros com sanitização
        $stmt->bindValue(':subcategoria_id', (int)$this->subcategoria_id, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $this->codigo ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':nome_produto', $this->nome_produto, PDO::PARAM_STR);
        $stmt->bindValue(':descricao_detalhada', $this->descricao_detalhada ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':dimensoes', $this->dimensoes ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':cor', $this->cor ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':material', $this->material ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':quantidade_total', (int)$this->quantidade_total, PDO::PARAM_INT);
        $stmt->bindValue(':preco_locacao', !empty($this->preco_locacao) ? floatval(str_replace(',', '.', str_replace('.', '', $this->preco_locacao))) : 0.00, PDO::PARAM_STR);
        $stmt->bindValue(':preco_venda', !empty($this->preco_venda) ? floatval(str_replace(',', '.', str_replace('.', '', $this->preco_venda))) : 0.00, PDO::PARAM_STR);
        $stmt->bindValue(':preco_custo', !empty($this->preco_custo) ? floatval(str_replace(',', '.', str_replace('.', '', $this->preco_custo))) : 0.00, PDO::PARAM_STR);
        $stmt->bindValue(':disponivel_venda', (int)$this->disponivel_venda, PDO::PARAM_INT);
        $stmt->bindValue(':disponivel_locacao', (int)$this->disponivel_locacao, PDO::PARAM_INT);
        $stmt->bindValue(':foto_path', $this->foto_path ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':observacoes', $this->observacoes ?? null, PDO::PARAM_STR);

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

        // Bind dos parâmetros com sanitização
        $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
        $stmt->bindValue(':subcategoria_id', (int)$this->subcategoria_id, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $this->codigo ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':nome_produto', $this->nome_produto, PDO::PARAM_STR);
        $stmt->bindValue(':descricao_detalhada', $this->descricao_detalhada ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':dimensoes', $this->dimensoes ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':cor', $this->cor ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':material', $this->material ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':quantidade_total', (int)$this->quantidade_total, PDO::PARAM_INT);
        $stmt->bindValue(':preco_locacao', !empty($this->preco_locacao) ? floatval(str_replace(',', '.', str_replace('.', '', $this->preco_locacao))) : 0.00, PDO::PARAM_STR);
        $stmt->bindValue(':preco_venda', !empty($this->preco_venda) ? floatval(str_replace(',', '.', str_replace('.', '', $this->preco_venda))) : 0.00, PDO::PARAM_STR);
        $stmt->bindValue(':preco_custo', !empty($this->preco_custo) ? floatval(str_replace(',', '.', str_replace('.', '', $this->preco_custo))) : 0.00, PDO::PARAM_STR);
        $stmt->bindValue(':disponivel_venda', (int)$this->disponivel_venda, PDO::PARAM_INT);
        $stmt->bindValue(':disponivel_locacao', (int)$this->disponivel_locacao, PDO::PARAM_INT);
        $stmt->bindValue(':foto_path', $this->foto_path ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':observacoes', $this->observacoes ?? null, PDO::PARAM_STR);

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
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return true;
            } else {
                error_log("[Produto::excluir] Erro ao executar DELETE: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Produto::excluir] PDOException: " . $e->getMessage());
            return false;
        }
    }
}