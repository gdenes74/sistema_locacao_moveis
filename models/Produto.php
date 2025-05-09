<?php

// Garante que a sessão seja iniciada SE AINDA NÃO FOI.
// É mais seguro colocar isso no config.php ou no início dos scripts que usam a sessão.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class Produto {
    // Conexão e nome da tabela
    private $conn;
    private $table_name = "produtos";

    // --- TODOS OS ATRIBUTOS DA TABELA 'produtos' ---
    public $id;
    public $subcategoria_id;
    public $codigo;
    public $nome_produto;
    public $descricao_detalhada;
    public $dimensoes;
    public $cor;
    public $material;
    public $quantidade_total = 0;
    public $quantidade_disponivel = 0;
    public $quantidade_reservada = 0;
    public $quantidade_lavanderia = 0;
    public $quantidade_manutencao = 0;
    public $quantidade_extraviada = 0;
    public $quantidade_bloqueada = 0;
    public $quantidade_vendida = 0;
    public $preco_locacao = 0.00;
    public $preco_venda = 0.00;
    public $preco_custo = 0.00;
    public $foto_path;
    public $disponivel_venda = 1; // true
    public $disponivel_locacao = 1; // true
    public $observacoes;
    public $data_cadastro; // Gerenciado pelo BD
    public $ultima_atualizacao; // Gerenciado pelo BD

    // --- ATRIBUTOS ADICIONAIS DOS JOINS ---
    public $nome_subcategoria;
    public $nome_categoria;
    public $nome_secao;

    /**
     * Construtor
     * @param PDO $db Objeto de conexão PDO
     */
    public function __construct($db) {
        if ($db instanceof PDO) {
            $this->conn = $db;
        } else {
            error_log("Erro Crítico: Conexão inválida passada para Produto.");
            die("Erro interno: Falha de conexão.");
        }
    }

    /**
     * Lista todos os produtos com colunas selecionadas para a view index.php.
     * ATUALIZADO para incluir foto, código, disponibilidade, seção, categoria e subcategoria.
     * @param string $orderBy Coluna para ordenação
     * @return PDOStatement|false Statement PDO ou false em caso de erro.
     */
        /**
     * Lista produtos com colunas selecionadas, com opção de filtros.
     * @param array $filtros Array associativo com os filtros (ex: ['pesquisar' => 'valor', 'categoria' => 'valor'])
     * @param string $orderBy Coluna para ordenação
     * @return PDOStatement|false Statement PDO ou false em caso de erro.
     */
      /**
     * Lista produtos com colunas selecionadas, com opção de filtros.
     * @param array $filtros Array associativo com os filtros (ex: ['pesquisar'=>'valor', 'categoria_id'=>1, 'subcategoria_id'=>5])
     * @param string $orderBy Coluna para ordenação
     * @return PDOStatement|false Statement PDO ou false em caso de erro.
     */
    public function listarTodos($filtros = [], $orderBy = "p.nome_produto ASC") {
        $queryBase = "SELECT
                        p.id, p.foto_path, p.codigo, p.nome_produto, p.cor, p.material,p.dimensoes,
                        p.quantidade_total, p.quantidade_disponivel,
                        p.preco_locacao, p.preco_venda,
                        p.disponivel_venda, p.disponivel_locacao,
                        s.id AS subcategoria_id, s.nome AS nome_subcategoria, -- Inclui ID e nome
                        c.id AS categoria_id, c.nome AS nome_categoria,       -- Inclui ID e nome
                        se.id AS secao_id, se.nome AS nome_secao             -- Inclui ID e nome
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
            $termoConditions = [];
            foreach ($termos as $i => $termo) {
                if (!empty($termo)) {
                    $placeholder = ":termo{$i}";
                    $termoConditions[] = "p.nome_produto LIKE {$placeholder}";
                    $bindParams[$placeholder] = "%{$termo}%";
                }
            }
            if (!empty($termoConditions)) {
                $whereConditions[] = "(" . implode(" AND ", $termoConditions) . ")";
            }
        }

        // Filtro por ID da Categoria
        if (!empty($filtros['categoria_id'])) {
            $whereConditions[] = "c.id = :categoria_id"; // Usa c.id
            $bindParams[':categoria_id'] = (int)$filtros['categoria_id'];
        }

        // Filtro por ID da Subcategoria
        if (!empty($filtros['subcategoria_id'])) {
            $whereConditions[] = "s.id = :subcategoria_id"; // Usa s.id
            $bindParams[':subcategoria_id'] = (int)$filtros['subcategoria_id'];
        }

        // Monta a cláusula WHERE
        if (!empty($whereConditions)) {
            $queryBase .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Ordenação segura
        $allowedOrderBy = ['p.nome_produto', 'p.id', 'c.nome', 's.nome', 'p.codigo', 'p.quantidade_disponivel']; // Adicione colunas permitidas
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : "p.nome_produto ASC"; // Default seguro
        $queryBase .= " ORDER BY " . $orderBy;

        // --- Linhas de Depuração (Descomente se necessário) ---
        // error_log("DEBUG SQL: " . $queryBase);
        // error_log("DEBUG Params: " . print_r($bindParams, true));
        // -----------------------------------------------------

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
    public function lerPorId(int $id): ?array
    {
        $query = "SELECT
                    p.*, -- Seleciona todas as colunas de produtos
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
     * Atualiza um produto existente (Versão ajustada para receber dados do form).
     * Assume que as propriedades do objeto já foram preenchidas (ex: via atribuir($_POST)).
     * @return bool True em sucesso, false em falha.
     */
    public function atualizar() : bool // Adicionado tipo de retorno bool
    {
        // Validações básicas
         if (empty($this->id) || !filter_var($this->id, FILTER_VALIDATE_INT)) {
            error_log("Erro ao atualizar: ID do produto inválido ou ausente.");
            // Poderia definir $_SESSION['error_message'] aqui também se quisesse feedback na view
            return false;
        }
        if (empty($this->nome_produto) || empty($this->subcategoria_id)) {
             error_log("Tentativa de atualizar produto (ID: {$this->id}) sem nome ou subcategoria.");
              // Poderia definir $_SESSION['error_message'] aqui também
             return false;
        }

        // Query UPDATE - Incluindo data_atualizacao
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
                    -- Nota: quantidade_disponivel e outras quantidades podem precisar de lógica separada
                    -- dependendo das regras de negócio (ex: não alterar diretamente aqui).
                    preco_locacao = :preco_locacao,
                    preco_venda = :preco_venda,
                    preco_custo = :preco_custo,
                    disponivel_venda = :disponivel_venda,
                    disponivel_locacao = :disponivel_locacao,
                    foto_path = :foto_path,
                    observacoes = :observacoes,
                    ultima_atualizacao = NOW() -- Atualiza timestamp
                  WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // *** SANITIZAÇÃO E PREPARAÇÃO DOS DADOS ANTES DO BIND ***

        // IDs e Quantidades
        $this->id = filter_var($this->id, FILTER_VALIDATE_INT);
        $this->subcategoria_id = filter_var($this->subcategoria_id, FILTER_VALIDATE_INT);
        $this->quantidade_total = filter_var($this->quantidade_total, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        // Se precisar atualizar outras quantidades, sanitize aqui também.

        // Strings (usando trim e htmlspecialchars)
        $this->codigo = !empty($this->codigo) ? htmlspecialchars(trim($this->codigo), ENT_QUOTES, 'UTF-8') : null;
        $this->nome_produto = htmlspecialchars(trim($this->nome_produto), ENT_QUOTES, 'UTF-8');
        $this->descricao_detalhada = !empty($this->descricao_detalhada) ? htmlspecialchars(trim($this->descricao_detalhada), ENT_QUOTES, 'UTF-8') : null;
        $this->dimensoes = !empty($this->dimensoes) ? htmlspecialchars(trim($this->dimensoes), ENT_QUOTES, 'UTF-8') : null;
        $this->cor = !empty($this->cor) ? htmlspecialchars(trim($this->cor), ENT_QUOTES, 'UTF-8') : null;
        $this->material = !empty($this->material) ? htmlspecialchars(trim($this->material), ENT_QUOTES, 'UTF-8') : null;
        // foto_path será tratado na lógica do POST (upload) e sanitizado lá antes de ser atribuído aqui.
        $this->foto_path = !empty($this->foto_path) ? htmlspecialchars(trim($this->foto_path), ENT_QUOTES, 'UTF-8') : null;
        $this->observacoes = !empty($this->observacoes) ? htmlspecialchars(trim($this->observacoes), ENT_QUOTES, 'UTF-8') : null;

        // Preços (Convertendo do formato 'R$ 1.234,56' para '1234.56')
        $preco_loc_db = $this->formatarPrecoParaBanco($this->preco_locacao);
        $preco_ven_db = $this->formatarPrecoParaBanco($this->preco_venda);
        $preco_cus_db = $this->formatarPrecoParaBanco($this->preco_custo);

        // Booleanos (Convertendo 'on' ou 1 para 1, ausente ou 0 para 0)
        $disp_venda = !empty($this->disponivel_venda) ? 1 : 0;
        $disp_locacao = !empty($this->disponivel_locacao) ? 1 : 0;


        // *** BIND DOS VALORES SANITIZADOS ***
        $stmt->bindParam(':subcategoria_id', $this->subcategoria_id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo', $this->codigo, PDO::PARAM_STR);
        $stmt->bindParam(':nome_produto', $this->nome_produto, PDO::PARAM_STR);
        $stmt->bindParam(':descricao_detalhada', $this->descricao_detalhada, PDO::PARAM_STR);
        $stmt->bindParam(':dimensoes', $this->dimensoes, PDO::PARAM_STR);
        $stmt->bindParam(':cor', $this->cor, PDO::PARAM_STR);
        $stmt->bindParam(':material', $this->material, PDO::PARAM_STR);
        $stmt->bindParam(':quantidade_total', $this->quantidade_total, PDO::PARAM_INT);
        // Bind outras quantidades se necessário

        $stmt->bindParam(':preco_locacao', $preco_loc_db, PDO::PARAM_STR); // PDO::PARAM_STR para decimais
        $stmt->bindParam(':preco_venda', $preco_ven_db, PDO::PARAM_STR);
        $stmt->bindParam(':preco_custo', $preco_cus_db, PDO::PARAM_STR);

        $stmt->bindParam(':disponivel_venda', $disp_venda, PDO::PARAM_INT); // Usar INT para booleanos 0/1
        $stmt->bindParam(':disponivel_locacao', $disp_locacao, PDO::PARAM_INT);

        $stmt->bindParam(':foto_path', $this->foto_path, PDO::PARAM_STR);
        $stmt->bindParam(':observacoes', $this->observacoes, PDO::PARAM_STR);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT); // Bind do ID no WHERE

        // Executar a query
        try {
            if ($stmt->execute()) {
                // RowCount pode ser 0 se nenhum dado mudou, mas a execução foi OK.
                // Consideramos sucesso se executou sem erro SQL.
                return true;
            } else {
                 // Erro na execução, mas não PDOException (menos comum)
                 error_log("Erro não-exceção ao executar atualização para produto ID {$this->id}: " . implode(" - ", $stmt->errorInfo()));
                 $_SESSION['error_message'] = "Erro desconhecido ao atualizar o produto."; // Define erro para view
                 return false;
            }
        } catch (PDOException $exception) {
             // Trata erro de código duplicado (UNIQUE constraint)
             if ($exception->getCode() == '23000' && strpos($exception->getMessage(), 'Duplicate entry') !== false) {
                  error_log("Erro ao atualizar produto (ID: {$this->id}): Código '{$this->codigo}' já existe para outra entrada.");
                  $_SESSION['error_message'] = "Erro ao atualizar: O código '" . htmlspecialchars($this->codigo ?? '') . "' já está em uso."; // Define erro para view
             } else {
                error_log("Erro SQL ao atualizar produto ID {$this->id}: " . $exception->getMessage());
                $_SESSION['error_message'] = "Erro no banco de dados ao tentar atualizar. Verifique os logs."; // Define erro genérico para view
             }
             return false; // Retorna false se a execução falhou
        }
    }

     /**
      * Função auxiliar para formatar preço 'R$ 1.234,56' ou '1.234,56' para '1234.56'.
      * @param mixed $preco_formatado
      * @return string
      */
     private function formatarPrecoParaBanco($preco_formatado): string
     {
        if ($preco_formatado === null || $preco_formatado === '') return '0.00';

        // Remove 'R$ ' e espaços em branco
        $preco = trim(str_replace('R$', '', $preco_formatado));

        // Remove o separador de milhar (.)
        $preco = str_replace('.', '', $preco);

        // Troca o separador decimal (,) por ponto (.)
        $preco = str_replace(',', '.', $preco);

        // Valida se é um número float e garante formato com 2 casas decimais
        $preco_float = filter_var($preco, FILTER_VALIDATE_FLOAT);
        if ($preco_float === false || $preco_float < 0) {
            return '0.00';
        }
        return number_format($preco_float, 2, '.', ''); // Formato X.XX sem separador de milhar
    }

    // --- Fim dos métodos adicionados ---

    /**
     * Cria um novo produto.
     * @return bool True em sucesso, false em falha.
     */
    public function criar() {
        if (empty($this->nome_produto) || empty($this->subcategoria_id)) {
             error_log("Tentativa de criar produto sem nome ou subcategoria.");
             $_SESSION['error_message'] = "Nome do Produto e Subcategoria são obrigatórios."; // Feedback para view
             return false;
        }

        $query = "INSERT INTO " . $this->table_name . " SET
                    subcategoria_id=:subcategoria_id, codigo=:codigo, nome_produto=:nome_produto,
                    descricao_detalhada=:descricao_detalhada, dimensoes=:dimensoes, cor=:cor, material=:material,
                    quantidade_total=:quantidade_total, quantidade_disponivel=:quantidade_disponivel,
                    quantidade_reservada=:quantidade_reservada, quantidade_lavanderia=:quantidade_lavanderia,
                    quantidade_manutencao=:quantidade_manutencao, quantidade_extraviada=:quantidade_extraviada,
                    quantidade_bloqueada=:quantidade_bloqueada, quantidade_vendida=:quantidade_vendida,
                    preco_locacao=:preco_locacao, preco_venda=:preco_venda, preco_custo=:preco_custo,
                    foto_path=:foto_path, disponivel_venda=:disponivel_venda,
                    disponivel_locacao=:disponivel_locacao, observacoes=:observacoes";

        $stmt = $this->conn->prepare($query);
        $this->sanitizarDados(); // Sanitiza ANTES de vincular

        // Bind dos parâmetros (todos os campos)
        $stmt->bindParam(":subcategoria_id", $this->subcategoria_id, PDO::PARAM_INT);
        $stmt->bindParam(":codigo", $this->codigo, PDO::PARAM_STR);
        $stmt->bindParam(":nome_produto", $this->nome_produto, PDO::PARAM_STR);
        $stmt->bindParam(":descricao_detalhada", $this->descricao_detalhada, PDO::PARAM_STR);
        $stmt->bindParam(":dimensoes", $this->dimensoes, PDO::PARAM_STR);
        $stmt->bindParam(":cor", $this->cor, PDO::PARAM_STR);
        $stmt->bindParam(":material", $this->material, PDO::PARAM_STR);
        $stmt->bindParam(":quantidade_total", $this->quantidade_total, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_disponivel", $this->quantidade_disponivel, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_reservada", $this->quantidade_reservada, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_lavanderia", $this->quantidade_lavanderia, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_manutencao", $this->quantidade_manutencao, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_extraviada", $this->quantidade_extraviada, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_bloqueada", $this->quantidade_bloqueada, PDO::PARAM_INT);
        $stmt->bindParam(":quantidade_vendida", $this->quantidade_vendida, PDO::PARAM_INT);
        $stmt->bindParam(":preco_locacao", $this->preco_locacao, PDO::PARAM_STR);
        $stmt->bindParam(":preco_venda", $this->preco_venda, PDO::PARAM_STR);
        $stmt->bindParam(":preco_custo", $this->preco_custo, PDO::PARAM_STR);
        $stmt->bindParam(":foto_path", $this->foto_path, PDO::PARAM_STR);
        $stmt->bindParam(":disponivel_venda", $this->disponivel_venda, PDO::PARAM_BOOL);
        $stmt->bindParam(":disponivel_locacao", $this->disponivel_locacao, PDO::PARAM_BOOL);
        $stmt->bindParam(":observacoes", $this->observacoes, PDO::PARAM_STR);

        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            } else {
                 error_log("Erro não-exceção ao executar criação: " . implode(" - ", $stmt->errorInfo()));
                 $_SESSION['error_message'] = "Erro desconhecido ao criar o produto.";
                 return false;
            }
        } catch (PDOException $exception) {
             if ($exception->getCode() == '23000' && strpos($exception->getMessage(), 'Duplicate entry') !== false) {
                 error_log("Erro ao criar produto: Código '{$this->codigo}' já existe.");
                 $_SESSION['error_message'] = "Erro ao criar: O código '" . htmlspecialchars($this->codigo ?? '') . "' já está em uso.";
             } else {
                 error_log("Erro SQL ao criar produto: " . $exception->getMessage());
                 $_SESSION['error_message'] = "Erro no banco de dados ao tentar criar. Verifique os logs.";
             }
             return false;
        }
    }


    /**
     * Exclui um produto pelo ID.
     * Define $_SESSION['error_message'] em caso de falha, especialmente por FK.
     * @param int $id O ID do produto a ser excluído.
     * @return bool True se a exclusão foi bem-sucedida, false caso contrário.
     */
    public function excluir($id) {
        // Sanitizar o ID
        $id_sanitized = filter_var($id, FILTER_VALIDATE_INT);
        if ($id_sanitized === false || $id_sanitized <= 0) {
            error_log("ID inválido fornecido para exclusão: " . print_r($id, true));
            $_SESSION['error_message'] = "ID inválido fornecido para exclusão."; // Mensagem para o usuário
            return false;
        }

        // ADICIONAL: Verificar dependências ANTES de excluir, se necessário
        // Ex: if ($this->temLocacoesAtivas($id_sanitized)) { $_SESSION['error_message'] = "..."; return false; }
        // (Isso exigiria criar um método temLocacoesAtivas($id) que consulta outras tabelas)

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id_sanitized, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                // Verifica se alguma linha foi realmente afetada
                if ($stmt->rowCount() > 0) {
                    return true; // Exclusão bem-sucedida
                } else {
                    // Nenhuma linha afetada - produto com esse ID não existia
                    error_log("Tentativa de excluir produto ID {$id_sanitized} que não foi encontrado.");
                    $_SESSION['error_message'] = "Produto não encontrado para exclusão (ID: {$id_sanitized}).";
                    return false;
                }
            } else {
                // Erro na execução, mas não PDOException
                 error_log("Erro não-exceção ao executar exclusão para produto ID {$id_sanitized}: " . implode(" - ", $stmt->errorInfo()));
                 $_SESSION['error_message'] = "Erro desconhecido ao excluir o produto.";
                 return false;
            }
        } catch (PDOException $exception) {
            // Trata erro de chave estrangeira (FK)
            if ($exception->getCode() == '23000') { // Erro de Integridade Referencial
                 error_log("Erro ao excluir produto (ID: {$id_sanitized}): Referenciado em outra tabela. " . $exception->getMessage());
                 // ---> MENSAGEM PARA O USUÁRIO <---
                 $_SESSION['error_message'] = "Produto ID {$id_sanitized} não pode ser excluído pois está em uso (ex: em locações, vendas ou movimentações).";
            } else {
                // Outro erro PDO
                error_log("Erro PDO geral ao excluir produto (ID: {$id_sanitized}): " . $exception->getMessage());
                $_SESSION['error_message'] = "Ocorreu um erro no banco de dados ao tentar excluir o produto ID {$id_sanitized}.";
            }
             return false; // Retorna false em caso de erro PDO
        }
    }

    /**
     * Método auxiliar para sanitizar os dados das propriedades do objeto.
     * (Usado principalmente antes do INSERT em criar())
     */
    private function sanitizarDados() {
        // Strings
        $this->codigo = !empty($this->codigo) ? htmlspecialchars(strip_tags(trim($this->codigo))) : null;
        $this->nome_produto = htmlspecialchars(strip_tags(trim($this->nome_produto)));
        $this->descricao_detalhada = !empty($this->descricao_detalhada) ? htmlspecialchars(strip_tags($this->descricao_detalhada)) : null;
        $this->dimensoes = !empty($this->dimensoes) ? htmlspecialchars(strip_tags(trim($this->dimensoes))) : null;
        $this->cor = !empty($this->cor) ? htmlspecialchars(strip_tags(trim($this->cor))) : null;
        $this->material = !empty($this->material) ? htmlspecialchars(strip_tags(trim($this->material))) : null;
        $this->foto_path = !empty($this->foto_path) ? htmlspecialchars(strip_tags(trim($this->foto_path))) : null;
        $this->observacoes = !empty($this->observacoes) ? htmlspecialchars(strip_tags($this->observacoes)) : null;

        // Inteiros (IDs e Quantidades)
        // Validação de subcategoria_id deve garantir que seja > 0 se for obrigatório
        $this->subcategoria_id = filter_var($this->subcategoria_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $this->quantidade_total = filter_var($this->quantidade_total, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_disponivel = filter_var($this->quantidade_disponivel, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_reservada = filter_var($this->quantidade_reservada, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_lavanderia = filter_var($this->quantidade_lavanderia, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_manutencao = filter_var($this->quantidade_manutencao, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_extraviada = filter_var($this->quantidade_extraviada, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_bloqueada = filter_var($this->quantidade_bloqueada, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
        $this->quantidade_vendida = filter_var($this->quantidade_vendida, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);

        // Decimais (Preços) - Usando a função formatarDecimal agora
        $this->preco_locacao = $this->formatarDecimal($this->preco_locacao);
        $this->preco_venda = $this->formatarDecimal($this->preco_venda);
        $this->preco_custo = $this->formatarDecimal($this->preco_custo);

        // Booleanos (Convertendo 'on'/1 para 1, outros para 0)
        $this->disponivel_venda = filter_var($this->disponivel_venda, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $this->disponivel_locacao = filter_var($this->disponivel_locacao, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    /**
     * Formata um valor para o padrão decimal do banco (XXXX.XX).
     * @param mixed $valor O valor a ser formatado.
     * @return string Valor formatado.
     */
    private function formatarDecimal($valor): string {
        // Trata nulo ou string vazia
        if ($valor === null || $valor === '') return '0.00';

        // Remove caracteres não numéricos, exceto vírgula e ponto
        $valorLimp = preg_replace('/[^0-9,.]/', '', (string)$valor);
        // Troca vírgula por ponto (padrão BR para US)
        $valorLimp = str_replace(',', '.', $valorLimp);
        // Valida se é um float válido
        $valorFloat = filter_var($valorLimp, FILTER_VALIDATE_FLOAT);

        // Se não for um float válido ou for negativo, retorna 0.00
        if ($valorFloat === false || $valorFloat < 0) {
            return '0.00';
        }
        // Formata com 2 casas decimais, ponto como separador decimal, sem separador de milhar
        return number_format($valorFloat, 2, '.', '');
    }

    /**
     * Atribui dados de um array às propriedades do objeto.
     * @param array $dados Array associativo (ex: $_POST).
     */
    public function atribuir(array $dados) {
        foreach ($dados as $chave => $valor) {
            if (property_exists($this, $chave)) {
                // Aplica trim() em strings para remover espaços extras
                $this->$chave = is_string($valor) ? trim($valor) : $valor;
            }
        }
         // Tratamento especial para checkboxes que podem não vir no POST se desmarcados
         if (!isset($dados['disponivel_venda'])) {
             $this->disponivel_venda = 0; // Ou false
         }
         if (!isset($dados['disponivel_locacao'])) {
             $this->disponivel_locacao = 0; // Ou false
         }
    }

} // Fim da classe Produto
?>