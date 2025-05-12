<?php
class Orcamento {
    private $conn;
    private $table_name = "orcamentos";

    // Atributos da tabela orçamentos
    public $id;
    public $numero;
    public $codigo;
    public $cliente_id;
    public $consulta_id;
    public $data_orcamento;
    public $data_validade;
    public $data_evento;
    public $hora_evento;
    public $local_evento;
    public $data_devolucao_prevista;
    public $tipo;
    public $status;
    public $subtotal_locacao;
    public $valor_total_locacao;
    public $subtotal_venda;
    public $valor_total_venda;
    public $desconto;
    public $taxa_domingo_feriado;
    public $taxa_madrugada;
    public $taxa_horario_especial;
    public $taxa_hora_marcada;
    public $frete_elevador;
    public $frete_escadas;
    public $frete_terreo;
    public $valor_final;
    public $ajuste_manual;
    public $motivo_ajuste;
    public $observacoes;
    public $condicoes_pagamento;
    public $usuario_id;
    public $data_criacao;
    public $data_atualizacao;

    // Atributos adicionais de joins
    public $nome_cliente;

    /**
     * Construtor
     * @param PDO $db Objeto de conexão PDO
     */
    public function __construct($db) {
        if ($db instanceof PDO) {
            $this->conn = $db;
        } else {
            error_log("Erro Crítico: Conexão inválida passada para Orcamento.");
            die("Erro interno: Falha de conexão.");
        }
    }

    /**
     * Lista todos os orçamentos com colunas selecionadas para a view index.php.
     * @param array $filtros Array associativo com filtros (ex: ['pesquisar' => 'valor', 'status' => 'pendente'])
     * @param string $orderBy Coluna para ordenação
     * @return PDOStatement|false Statement PDO ou false em caso de erro
     */
    public function listarTodos($filtros = [], $orderBy = "o.data_orcamento DESC") {
        $queryBase = "SELECT
                        o.id, o.numero, o.codigo, o.cliente_id, o.data_orcamento, o.data_validade,
                        o.status, o.tipo, o.valor_final,
                        c.nome AS nome_cliente
                      FROM
                        " . $this->table_name . " o
                      LEFT JOIN clientes c ON o.cliente_id = c.id";

        $whereConditions = [];
        $bindParams = [];

        // Filtro por nome do cliente ou número do orçamento
        if (!empty($filtros['pesquisar'])) {
            $termos = explode(" ", trim($filtros['pesquisar']));
            $termoConditions = [];
            foreach ($termos as $i => $termo) {
                if (!empty($termo)) {
                    $placeholder = ":termo{$i}";
                    $termoConditions[] = "(c.nome LIKE {$placeholder} OR o.numero LIKE {$placeholder} OR o.codigo LIKE {$placeholder})";
                    $bindParams[$placeholder] = "%{$termo}%";
                }
            }
            if (!empty($termoConditions)) {
                $whereConditions[] = "(" . implode(" AND ", $termoConditions) . ")";
            }
        }

        // Filtro por status
        if (!empty($filtros['status'])) {
            $whereConditions[] = "o.status = :status";
            $bindParams[':status'] = $filtros['status'];
        }

        // Monta a cláusula WHERE
        if (!empty($whereConditions)) {
            $queryBase .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Ordenação segura
        $allowedOrderBy = ['o.data_orcamento', 'o.numero', 'o.valor_final', 'c.nome', 'o.status'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : "o.data_orcamento DESC";
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
                error_log("[Orcamento::listarTodos] Erro ao executar query: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Orcamento::listarTodos] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um único orçamento pelo ID e retorna como array associativo.
     * @param int $id O ID do orçamento
     * @return bool Retorna true se encontrado, false se não encontrado
     */
    public function getById($id) {
        $query = "SELECT o.*, c.nome AS nome_cliente 
                  FROM " . $this->table_name . " o 
                  LEFT JOIN clientes c ON o.cliente_id = c.id 
                  WHERE o.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                foreach ($row as $key => $value) {
                    $this->$key = $value;
                }
                return true;
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar orçamento por ID {$id}: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Cria um novo orçamento no banco de dados.
     * @return bool Retorna true se criado com sucesso, false em caso de erro
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (
                    numero, codigo, cliente_id, consulta_id, data_orcamento, data_validade,
                    data_evento, hora_evento, local_evento, data_devolucao_prevista, tipo,
                    status, subtotal_locacao, valor_total_locacao, subtotal_venda, valor_total_venda,
                    desconto, taxa_domingo_feriado, taxa_madrugada, taxa_horario_especial,
                    taxa_hora_marcada, frete_elevador, frete_escadas, frete_terreo, valor_final,
                    ajuste_manual, motivo_ajuste, observacoes, condicoes_pagamento, usuario_id,
                    data_criacao
                  ) VALUES (
                    :numero, :codigo, :cliente_id, :consulta_id, :data_orcamento, :data_validade,
                    :data_evento, :hora_evento, :local_evento, :data_devolucao_prevista, :tipo,
                    :status, :subtotal_locacao, :valor_total_locacao, :subtotal_venda, :valor_total_venda,
                    :desconto, :taxa_domingo_feriado, :taxa_madrugada, :taxa_horario_especial,
                    :taxa_hora_marcada, :frete_elevador, :frete_escadas, :frete_terreo, :valor_final,
                    :ajuste_manual, :motivo_ajuste, :observacoes, :condicoes_pagamento, :usuario_id,
                    NOW()
                  )";
        $stmt = $this->conn->prepare($query);

        // Bind dos parâmetros
        $stmt->bindParam(':numero', $this->numero, PDO::PARAM_STR);
        $stmt->bindParam(':codigo', $this->codigo, PDO::PARAM_STR);
        $stmt->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
        $stmt->bindParam(':consulta_id', $this->consulta_id, PDO::PARAM_INT);
        $stmt->bindParam(':data_orcamento', $this->data_orcamento, PDO::PARAM_STR);
        $stmt->bindParam(':data_validade', $this->data_validade, PDO::PARAM_STR);
        $stmt->bindParam(':data_evento', $this->data_evento, PDO::PARAM_STR);
        $stmt->bindParam(':hora_evento', $this->hora_evento, PDO::PARAM_STR);
        $stmt->bindParam(':local_evento', $this->local_evento, PDO::PARAM_STR);
        $stmt->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $this->tipo, PDO::PARAM_STR);
        $stmt->bindParam(':status', $this->status, PDO::PARAM_STR);
        $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao, PDO::PARAM_STR);
        $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao, PDO::PARAM_STR);
        $stmt->bindParam(':subtotal_venda', $this->subtotal_venda, PDO::PARAM_STR);
        $stmt->bindParam(':valor_total_venda', $this->valor_total_venda, PDO::PARAM_STR);
        $stmt->bindParam(':desconto', $this->desconto, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada, PDO::PARAM_STR);
        $stmt->bindParam(':frete_elevador', $this->frete_elevador, PDO::PARAM_STR);
        $stmt->bindParam(':frete_escadas', $this->frete_escadas, PDO::PARAM_STR);
        $stmt->bindParam(':frete_terreo', $this->frete_terreo, PDO::PARAM_STR);
        $stmt->bindParam(':valor_final', $this->valor_final, PDO::PARAM_STR);
        $stmt->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_INT);
        $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste, PDO::PARAM_STR);
        $stmt->bindParam(':observacoes', $this->observacoes, PDO::PARAM_STR);
        $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento, PDO::PARAM_STR);
        $stmt->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            } else {
                error_log("[Orcamento::create] Erro ao criar orçamento: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Orcamento::create] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um orçamento existente no banco de dados.
     * @return bool Retorna true se atualizado com sucesso, false em caso de erro
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET
                    cliente_id = :cliente_id,
                    consulta_id = :consulta_id,
                    data_orcamento = :data_orcamento,
                    data_validade = :data_validade,
                    data_evento = :data_evento,
                    hora_evento = :hora_evento,
                    local_evento = :local_evento,
                    data_devolucao_prevista = :data_devolucao_prevista,
                    tipo = :tipo,
                    status = :status,
                    subtotal_locacao = :subtotal_locacao,
                    valor_total_locacao = :valor_total_locacao,
                    subtotal_venda = :subtotal_venda,
                    valor_total_venda = :valor_total_venda,
                    desconto = :desconto,
                    taxa_domingo_feriado = :taxa_domingo_feriado,
                    taxa_madrugada = :taxa_madrugada,
                    taxa_horario_especial = :taxa_horario_especial,
                    taxa_hora_marcada = :taxa_hora_marcada,
                    frete_elevador = :frete_elevador,
                    frete_escadas = :frete_escadas,
                    frete_terreo = :frete_terreo,
                    valor_final = :valor_final,
                    ajuste_manual = :ajuste_manual,
                    motivo_ajuste = :motivo_ajuste,
                    observacoes = :observacoes,
                    condicoes_pagamento = :condicoes_pagamento,
                    usuario_id = :usuario_id,
                    data_atualizacao = NOW()
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        // Bind dos parâmetros
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
        $stmt->bindParam(':consulta_id', $this->consulta_id, PDO::PARAM_INT);
        $stmt->bindParam(':data_orcamento', $this->data_orcamento, PDO::PARAM_STR);
        $stmt->bindParam(':data_validade', $this->data_validade, PDO::PARAM_STR);
        $stmt->bindParam(':data_evento', $this->data_evento, PDO::PARAM_STR);
        $stmt->bindParam(':hora_evento', $this->hora_evento, PDO::PARAM_STR);
        $stmt->bindParam(':local_evento', $this->local_evento, PDO::PARAM_STR);
        $stmt->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $this->tipo, PDO::PARAM_STR);
        $stmt->bindParam(':status', $this->status, PDO::PARAM_STR);
        $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao, PDO::PARAM_STR);
        $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao, PDO::PARAM_STR);
        $stmt->bindParam(':subtotal_venda', $this->subtotal_venda, PDO::PARAM_STR);
        $stmt->bindParam(':valor_total_venda', $this->valor_total_venda, PDO::PARAM_STR);
        $stmt->bindParam(':desconto', $this->desconto, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial, PDO::PARAM_STR);
        $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada, PDO::PARAM_STR);
        $stmt->bindParam(':frete_elevador', $this->frete_elevador, PDO::PARAM_STR);
        $stmt->bindParam(':frete_escadas', $this->frete_escadas, PDO::PARAM_STR);
        $stmt->bindParam(':frete_terreo', $this->frete_terreo, PDO::PARAM_STR);
        $stmt->bindParam(':valor_final', $this->valor_final, PDO::PARAM_STR);
        $stmt->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_INT);
        $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste, PDO::PARAM_STR);
        $stmt->bindParam(':observacoes', $this->observacoes, PDO::PARAM_STR);
        $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento, PDO::PARAM_STR);
        $stmt->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                return true;
            } else {
                error_log("[Orcamento::update] Erro ao atualizar orçamento ID {$this->id}: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Orcamento::update] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui um orçamento do banco de dados.
     * @param int $id O ID do orçamento a ser excluído
     * @return bool Retorna true se excluído com sucesso, false em caso de erro
     */
    public function delete($id) {
        // Excluir itens associados
        $queryItens = "DELETE FROM itens_orcamento WHERE orcamento_id = :orcamento_id";
        $stmtItens = $this->conn->prepare($queryItens);
        $stmtItens->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        try {
            $stmtItens->execute();
        } catch (PDOException $e) {
            error_log("[Orcamento::delete] Erro ao excluir itens do orçamento ID {$id}: " . $e->getMessage());
        }

        // Excluir registro de numeração sequencial, se houver
        $queryNumeracao = "DELETE FROM numeracao_sequencial WHERE entidade = 'orcamento' AND entidade_id = :entidade_id";
        $stmtNumeracao = $this->conn->prepare($queryNumeracao);
        $stmtNumeracao->bindParam(':entidade_id', $id, PDO::PARAM_INT);
        try {
            $stmtNumeracao->execute();
        } catch (PDOException $e) {
            error_log("[Orcamento::delete] Erro ao excluir numeração sequencial do orçamento ID {$id}: " . $e->getMessage());
        }

        // Excluir o orçamento
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            if ($stmt->execute()) {
                return true;
            } else {
                error_log("[Orcamento::delete] Erro ao excluir orçamento ID {$id}: " . implode(" - ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("[Orcamento::delete] PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Salva os itens de um orçamento no banco de dados.
     * @param int $orcamento_id ID do orçamento
     * @param array $itens Array de itens a serem salvos
     * @return bool Retorna true se salvos com sucesso, false em caso de erro
     */
    public function salvarItens($orcamento_id, $itens) {
        if (empty($itens)) {
            return true; // Sem itens para salvar
        }

        $query = "INSERT INTO itens_orcamento (
                    orcamento_id, produto_id, quantidade, tipo, preco_unitario, desconto,
                    preco_final, ajuste_manual, motivo_ajuste, observacoes
                  ) VALUES (
                    :orcamento_id, :produto_id, :quantidade, :tipo, :preco_unitario, :desconto,
                    :preco_final, :ajuste_manual, :motivo_ajuste, :observacoes
                  )";
        $stmt = $this->conn->prepare($query);

        $success = true;
        foreach ($itens as $item) {
            try {
                $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
                $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                $stmt->bindParam(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmt->bindParam(':tipo', $item['tipo'], PDO::PARAM_STR);
                $stmt->bindParam(':preco_unitario', $item['preco_unitario'], PDO::PARAM_STR);
                $stmt->bindParam(':desconto', $item['desconto'], PDO::PARAM_STR);
                $stmt->bindParam(':preco_final', $item['preco_final'], PDO::PARAM_STR);
                $stmt->bindParam(':ajuste_manual', $item['ajuste_manual'], PDO::PARAM_INT);
                $stmt->bindParam(':motivo_ajuste', $item['motivo_ajuste'], PDO::PARAM_STR);
                $stmt->bindParam(':observacoes', $item['observacoes'], PDO::PARAM_STR);

                if (!$stmt->execute()) {
                    error_log("[Orcamento::salvarItens] Erro ao salvar item para orçamento ID {$orcamento_id}: " . implode(" - ", $stmt->errorInfo()));
                    $success = false;
                }
            } catch (PDOException $e) {
                error_log("[Orcamento::salvarItens] PDOException ao salvar item para orçamento ID {$orcamento_id}: " . $e->getMessage());
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Busca os itens de um orçamento.
     * @param int $orcamento_id ID do orçamento
     * @return array Array de itens do orçamento
     */
    public function getItens($orcamento_id) {
        $query = "SELECT io.*, p.nome_produto 
                  FROM itens_orcamento io 
                  LEFT JOIN produtos p ON io.produto_id = p.id 
                  WHERE io.orcamento_id = :orcamento_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        try {
            if ($stmt->execute()) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("[Orcamento::getItens] PDOException ao buscar itens do orçamento ID {$orcamento_id}: " . $e->getMessage());
        }
        return [];
    }

    /**
     * Gera um número sequencial para o orçamento baseado no ano atual.
     * @param string $ano Ano para o número sequencial (padrão: ano atual)
     * @return string Número sequencial no formato ANO-XXXXX
     */
    public function gerarNumeroSequencial($ano = null) {
        if (!$ano) {
            $ano = date('Y');
        }
        $prefixo = $ano . '-';
        $queryUltimo = "SELECT numero FROM numeracao_sequencial 
                        WHERE entidade = 'orcamento' AND ano = :ano 
                        ORDER BY numero DESC LIMIT 1";
        $stmtUltimo = $this->conn->prepare($queryUltimo);
        $stmtUltimo->bindParam(':ano', $ano, PDO::PARAM_STR);
        try {
            $stmtUltimo->execute();
            $ultimoNumero = $stmtUltimo->fetchColumn();
            if ($ultimoNumero) {
                $sequencial = (int)substr($ultimoNumero, strpos($ultimoNumero, '-') + 1) + 1;
            } else {
                $sequencial = 1;
            }
            $novoNumero = $prefixo . str_pad($sequencial, 5, '0', STR_PAD_LEFT);
            return $novoNumero;
        } catch (PDOException $e) {
            error_log("[Orcamento::gerarNumeroSequencial] PDOException ao gerar número sequencial: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra o número sequencial no banco após criar o orçamento.
     * @param int $orcamento_id ID do orçamento
     * @param string $numero Número sequencial gerado
     * @param string $ano Ano do número sequencial
     * @return bool Retorna true se registrado com sucesso, false em caso de erro
     */
    public function registrarNumeroSequencial($orcamento_id, $numero, $ano) {
        $query = "INSERT INTO numeracao_sequencial (entidade, entidade_id, numero, ano, data_criacao) 
                  VALUES ('orcamento', :entidade_id, :numero, :ano, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':entidade_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->bindParam(':numero', $numero, PDO::PARAM_STR);
        $stmt->bindParam(':ano', $ano, PDO::PARAM_STR);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("[Orcamento::registrarNumeroSequencial] PDOException ao registrar número sequencial para orçamento ID {$orcamento_id}: " . $e->getMessage());
            return false;
        }
    }
}
?>