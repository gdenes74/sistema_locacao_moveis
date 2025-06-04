<?php
class Orcamento {
    private $conn;
    private $table = 'orcamentos';

    // Propriedades do Orçamento
    public $id;
    public $numero;
    public $codigo;
    public $cliente_id;
    public $data_orcamento;
    public $data_validade;

    // NOVAS PROPRIEDADES (adicionadas aqui conforme a estrutura do seu banco após o ALTER TABLE)
    public $data_entrega;
    public $hora_entrega;

    // Propriedades existentes continuam
    public $data_evento;
    public $hora_evento;
    public $local_evento;
    public $data_devolucao_prevista;
    public $hora_devolucao;
    public $turno_entrega;
    public $turno_devolucao;
    public $tipo;
    public $status;
    public $valor_total_locacao;
    public $subtotal_locacao;
    public $valor_total_venda;
    public $subtotal_venda;
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
    public $data_cadastro;

    // Propriedades adicionadas para os dados do cliente
    public $nome_cliente;
    public $cliente_telefone;
    public $cliente_email;
    public $cliente_cpf_cnpj;
    public $cliente_endereco;
    public $cliente_cidade;
    public $cliente_observacoes;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listarTodos($filtros = [], $orderBy = 'o.id DESC') {
        $query = "SELECT
                    o.id, o.numero, o.codigo, o.cliente_id, o.data_orcamento, o.data_validade,
                    o.data_entrega, o.hora_entrega, -- << ADICIONADO
                    o.data_evento, o.hora_evento, o.local_evento, o.data_devolucao_prevista, o.hora_devolucao,
                    o.turno_entrega, o.turno_devolucao,
                    o.tipo, o.status, o.valor_total_locacao, o.subtotal_locacao,
                    o.valor_total_venda, o.subtotal_venda, o.desconto, o.taxa_domingo_feriado,
                    o.taxa_madrugada, o.taxa_horario_especial, o.taxa_hora_marcada,
                    o.frete_elevador, o.frete_escadas, o.frete_terreo, o.valor_final,
                    o.ajuste_manual, o.motivo_ajuste, o.observacoes, o.condicoes_pagamento,
                    o.usuario_id, o.data_cadastro,
                    c.nome AS nome_cliente
                FROM
                    {$this->table} o
                LEFT JOIN
                    clientes c ON o.cliente_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['pesquisar'])) {
            $query .= " AND (CAST(o.numero AS CHAR) LIKE :pesquisar_num OR o.codigo LIKE :pesquisar_cod OR c.nome LIKE :pesquisar_cliente)";
            $params[':pesquisar_num'] = "%" . $filtros['pesquisar'] . "%";
            $params[':pesquisar_cod'] = "%" . $filtros['pesquisar'] . "%";
            $params[':pesquisar_cliente'] = "%" . $filtros['pesquisar'] . "%";
        }
        if (!empty($filtros['cliente_id'])) {
            $query .= " AND o.cliente_id = :cliente_id";
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        if (!empty($filtros['status'])) {
            $query .= " AND o.status = :status";
            $params[':status'] = $filtros['status'];
        }
        // Adicionar filtro por data_entrega se necessário no futuro
        if (!empty($filtros['data_entrega_de']) && !empty($filtros['data_entrega_ate'])) {
            $query .= " AND o.data_entrega BETWEEN :data_entrega_de AND :data_entrega_ate";
            $params[':data_entrega_de'] = $filtros['data_entrega_de'];
            $params[':data_entrega_ate'] = $filtros['data_entrega_ate'];
        }


        if (!empty($orderBy)) {
            $allowedOrderBy = ['o.id DESC', 'o.id ASC', 'o.data_orcamento DESC', 'o.data_orcamento ASC', 'o.numero DESC', 'o.numero ASC', 'o.valor_final DESC', 'o.valor_final ASC', 'c.nome ASC', 'c.nome DESC', 'o.data_entrega DESC', 'o.data_entrega ASC']; // Adicionado data_entrega
            if (in_array($orderBy, $allowedOrderBy)) {
                $query .= " ORDER BY " . $orderBy;
            } else {
                $query .= " ORDER BY o.id DESC";
            }
        }

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::listarTodos: " . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        // A query já busca 'o.*', então data_entrega e hora_entrega já serão incluídas
        // se existirem na tabela após o ALTER TABLE.
        $query = "SELECT
                    o.*,
                    c.nome AS nome_cliente,
                    c.telefone AS cliente_telefone,
                    c.email AS cliente_email,
                    c.cpf_cnpj AS cliente_cpf_cnpj,
                    c.endereco AS cliente_endereco,
                    c.cidade AS cliente_cidade,
                    c.observacoes AS cliente_observacoes
                FROM
                    {$this->table} o
                LEFT JOIN
                    clientes c ON o.cliente_id = c.id
                WHERE
                    o.id = :id
                LIMIT 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                foreach ($row as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->$key = $value;
                        // A lógica de conversão de tipo já existente deve funcionar
                        // para os novos campos se eles forem DATE e TIME.
                        if (in_array($key, ['id', 'cliente_id', 'usuario_id', 'numero'])) {
                            $this->$key = (int)$value;
                        } elseif (in_array($key, ['valor_total_locacao', 'subtotal_locacao', 'valor_total_venda', 'subtotal_venda', 'desconto', 'taxa_domingo_feriado', 'taxa_madrugada', 'taxa_horario_especial', 'taxa_hora_marcada', 'frete_terreo', 'valor_final'])) {
                            $this->$key = (float)$value;
                        } elseif ($key === 'ajuste_manual') {
                            $this->$key = (bool)$value;
                        }
                    }
                }
                return $row; // Mantém o retorno do array para compatibilidade, mas as propriedades do objeto são preenchidas
            }
            return false;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::getById: " . $e->getMessage());
            return false;
        }
    }

    public function create() {
        if (empty($this->numero)) {
            error_log("Erro: Propriedade 'numero' não definida em Orcamento::create().");
            return false;
        }
        $this->codigo = "ORC-" . date('Y') . "-" . $this->numero;

        try {
            $this->conn->beginTransaction();

            $queryOrcamento = "INSERT INTO {$this->table}
                        (numero, codigo, cliente_id, data_orcamento, data_validade, 
                         data_entrega, hora_entrega, -- << ADICIONADO
                         data_evento, hora_evento, local_evento,
                         data_devolucao_prevista, hora_devolucao, turno_entrega, turno_devolucao,
                         tipo, status, valor_total_locacao, subtotal_locacao, valor_total_venda,
                         subtotal_venda, desconto, taxa_domingo_feriado, taxa_madrugada, taxa_horario_especial,
                         taxa_hora_marcada, frete_elevador, frete_escadas, frete_terreo, valor_final, ajuste_manual,
                         motivo_ajuste, observacoes, condicoes_pagamento, usuario_id)
                    VALUES
                        (:numero, :codigo, :cliente_id, :data_orcamento, :data_validade,
                         :data_entrega, :hora_entrega, -- << ADICIONADO
                         :data_evento, :hora_evento, :local_evento,
                         :data_devolucao_prevista, :hora_devolucao, :turno_entrega, :turno_devolucao,
                         :tipo, :status, :valor_total_locacao, :subtotal_locacao, :valor_total_venda,
                         :subtotal_venda, :desconto, :taxa_domingo_feriado, :taxa_madrugada, :taxa_horario_especial,
                         :taxa_hora_marcada, :frete_elevador, :frete_escadas, :frete_terreo, :valor_final, :ajuste_manual,
                         :motivo_ajuste, :observacoes, :condicoes_pagamento, :usuario_id)";

            $stmtOrcamento = $this->conn->prepare($queryOrcamento);

            $this->data_orcamento = $this->data_orcamento ?? date('Y-m-d');
            $this->data_validade = $this->data_validade ?? date('Y-m-d', strtotime('+30 days'));
            
            // Certificar que os novos campos sejam null se vazios
            $this->data_entrega = !empty($this->data_entrega) ? $this->data_entrega : null;
            $this->hora_entrega = !empty($this->hora_entrega) ? $this->hora_entrega : null;
            
            $this->data_evento = $this->data_evento ?? null;
            $this->data_devolucao_prevista = $this->data_devolucao_prevista ?? null;
            $this->hora_evento = $this->hora_evento ?? null;
            $this->hora_devolucao = $this->hora_devolucao ?? null;
            $this->status = $this->status ?? 'pendente';
            $this->tipo = $this->tipo ?? 'locacao';
            $this->ajuste_manual = (bool)($this->ajuste_manual ?? false);
            $this->usuario_id = $this->usuario_id ?? (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1);

            $this->turno_entrega = $this->turno_entrega ?? 'Manhã/Tarde (Horário Comercial)';
            $this->turno_devolucao = $this->turno_devolucao ?? 'Manhã/Tarde (Horário Comercial)';

            $stmtOrcamento->bindParam(':numero', $this->numero, PDO::PARAM_INT);
            $stmtOrcamento->bindParam(':codigo', $this->codigo);
            $stmtOrcamento->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmtOrcamento->bindParam(':data_orcamento', $this->data_orcamento);
            $stmtOrcamento->bindParam(':data_validade', $this->data_validade);

            $stmtOrcamento->bindParam(':data_entrega', $this->data_entrega); // << ADICIONADO
            $stmtOrcamento->bindParam(':hora_entrega', $this->hora_entrega); // << ADICIONADO

            $stmtOrcamento->bindParam(':data_evento', $this->data_evento);
            $stmtOrcamento->bindParam(':hora_evento', $this->hora_evento);
            $stmtOrcamento->bindParam(':local_evento', $this->local_evento);
            $stmtOrcamento->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista);
            $stmtOrcamento->bindParam(':hora_devolucao', $this->hora_devolucao);
            $stmtOrcamento->bindParam(':turno_entrega', $this->turno_entrega);
            $stmtOrcamento->bindParam(':turno_devolucao', $this->turno_devolucao);
            $stmtOrcamento->bindParam(':tipo', $this->tipo);
            $stmtOrcamento->bindParam(':status', $this->status);
            $stmtOrcamento->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmtOrcamento->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmtOrcamento->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmtOrcamento->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmtOrcamento->bindParam(':desconto', $this->desconto);
            $stmtOrcamento->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmtOrcamento->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmtOrcamento->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmtOrcamento->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmtOrcamento->bindParam(':frete_elevador', $this->frete_elevador);
            $stmtOrcamento->bindParam(':frete_escadas', $this->frete_escadas);
            $stmtOrcamento->bindParam(':frete_terreo', $this->frete_terreo);
            $stmtOrcamento->bindParam(':valor_final', $this->valor_final);
            $stmtOrcamento->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmtOrcamento->bindParam(':motivo_ajuste', $this->motivo_ajuste);
            $stmtOrcamento->bindParam(':observacoes', $this->observacoes);
            $stmtOrcamento->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
            $stmtOrcamento->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            if (!$stmtOrcamento->execute()) {
                $this->conn->rollBack();
                error_log("Erro ao inserir orçamento: " . print_r($stmtOrcamento->errorInfo(), true));
                return false;
            }
            $this->id = $this->conn->lastInsertId();

            $this->conn->commit();
            return $this->id;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Exceção PDO em Orcamento::create: " . $e->getMessage());
            return false;
        }
    }

    public function update() {
        $query = "UPDATE {$this->table} SET
                    cliente_id = :cliente_id,
                    data_orcamento = :data_orcamento,
                    data_validade = :data_validade,
                    data_entrega = :data_entrega, -- << ADICIONADO
                    hora_entrega = :hora_entrega, -- << ADICIONADO
                    data_evento = :data_evento,
                    hora_evento = :hora_evento,
                    local_evento = :local_evento,
                    data_devolucao_prevista = :data_devolucao_prevista,
                    hora_devolucao = :hora_devolucao,
                    turno_entrega = :turno_entrega,
                    turno_devolucao = :turno_devolucao,
                    tipo = :tipo,
                    status = :status,
                    valor_total_locacao = :valor_total_locacao,
                    subtotal_locacao = :subtotal_locacao,
                    valor_total_venda = :valor_total_venda,
                    subtotal_venda = :subtotal_venda,
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
                    usuario_id = :usuario_id
                WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);

            // Certificar que os novos campos sejam null se vazios
            $this->data_entrega = !empty($this->data_entrega) ? $this->data_entrega : null;
            $this->hora_entrega = !empty($this->hora_entrega) ? $this->hora_entrega : null;

            $this->data_orcamento = $this->data_orcamento ?? null;
            $this->data_validade = $this->data_validade ?? null;
            $this->data_evento = $this->data_evento ?? null;
            $this->data_devolucao_prevista = $this->data_devolucao_prevista ?? null;
            $this->hora_evento = $this->hora_evento ?? null;
            $this->hora_devolucao = $this->hora_devolucao ?? null;
            $this->ajuste_manual = (bool)($this->ajuste_manual ?? false);
            $this->usuario_id = $this->usuario_id ?? (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1);

            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':cliente_id', $this->cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_orcamento', $this->data_orcamento);
            $stmt->bindParam(':data_validade', $this->data_validade);

            $stmt->bindParam(':data_entrega', $this->data_entrega); // << ADICIONADO
            $stmt->bindParam(':hora_entrega', $this->hora_entrega); // << ADICIONADO

            $stmt->bindParam(':data_evento', $this->data_evento);
            $stmt->bindParam(':hora_evento', $this->hora_evento);
            $stmt->bindParam(':local_evento', $this->local_evento);
            $stmt->bindParam(':data_devolucao_prevista', $this->data_devolucao_prevista);
            $stmt->bindParam(':hora_devolucao', $this->hora_devolucao);
            $stmt->bindParam(':turno_entrega', $this->turno_entrega);
            $stmt->bindParam(':turno_devolucao', $this->turno_devolucao);
            $stmt->bindParam(':tipo', $this->tipo);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmt->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmt->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmt->bindParam(':desconto', $this->desconto);
            $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmt->bindParam(':frete_elevador', $this->frete_elevador);
            $stmt->bindParam(':frete_escadas', $this->frete_escadas);
            $stmt->bindParam(':frete_terreo', $this->frete_terreo);
            $stmt->bindParam(':valor_final', $this->valor_final);
            $stmt->bindParam(':ajuste_manual', $this->ajuste_manual, PDO::PARAM_BOOL);
            $stmt->bindParam(':motivo_ajuste', $this->motivo_ajuste);
            $stmt->bindParam(':observacoes', $this->observacoes);
            $stmt->bindParam(':condicoes_pagamento', $this->condicoes_pagamento);
            $stmt->bindParam(':usuario_id', $this->usuario_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::update: " . $e->getMessage());
            return false;
        }
    }

    // --- Os métodos restantes (delete, salvarItens, etc.) não precisam de alterações diretas ---
    // --- para data_entrega e hora_entrega, a menos que você queira alguma lógica específica ---
    // --- relacionada a eles nesses métodos. Por ora, vou mantê-los como estão. ---

    public function delete($id) {
        $this->deletarTodosItens($id);

        $query = "DELETE FROM {$this->table} WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::delete: " . $e->getMessage());
            return false;
        }
    }

    public function salvarItens($orcamento_id, $itens) {
        $inTransaction = $this->conn->inTransaction();
        if (!$inTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            $this->deletarTodosItens($orcamento_id);

            $query = "INSERT INTO itens_orcamento
                        (orcamento_id, produto_id, quantidade, tipo, preco_unitario, desconto, preco_final, ajuste_manual, motivo_ajuste, observacoes)
                      VALUES
                        (:orcamento_id, :produto_id, :quantidade, :tipo, :preco_unitario, :desconto, :preco_final, :ajuste_manual, :motivo_ajuste, :observacoes)";

            $stmt = $this->conn->prepare($query);
            foreach ($itens as $item) {
                $item['ajuste_manual'] = isset($item['ajuste_manual']) ? (bool)$item['ajuste_manual'] : false;
                $item['tipo'] = $item['tipo'] ?? 'locacao';

                $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
                $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                $stmt->bindParam(':quantidade', $item['quantidade'], PDO::PARAM_INT); // Assumindo que quantidade é sempre INT
                $stmt->bindParam(':tipo', $item['tipo']);
                $stmt->bindParam(':preco_unitario', $item['preco_unitario']);
                $stmt->bindParam(':desconto', $item['desconto']);
                $stmt->bindParam(':preco_final', $item['preco_final']);
                $stmt->bindParam(':ajuste_manual', $item['ajuste_manual'], PDO::PARAM_BOOL);
                $stmt->bindParam(':motivo_ajuste', $item['motivo_ajuste']);
                $stmt->bindParam(':observacoes', $item['observacoes']);

                if (!$stmt->execute()) {
                    error_log("Erro ao inserir item de orçamento: " . print_r($stmt->errorInfo(), true));
                    if (!$inTransaction) $this->conn->rollBack();
                    return false;
                }
            }

            if (!$inTransaction) $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::salvarItens: " . $e->getMessage());
            if (!$inTransaction) $this->conn->rollBack();
            return false;
        }
    }

    public function deletarTodosItens($orcamento_id) {
        $query = "DELETE FROM itens_orcamento WHERE orcamento_id = :orcamento_id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::deletarTodosItens: " . $e->getMessage());
            return false;
        }
    }

    public function getItens($orcamento_id) {
        $query = "SELECT io.*, p.nome_produto as nome_produto, p.codigo as codigo_produto
                  FROM itens_orcamento io
                  LEFT JOIN produtos p ON io.produto_id = p.id
                  WHERE io.orcamento_id = :orcamento_id
                  ORDER BY io.id ASC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::getItens: " . $e->getMessage());
            return false;
        }
    }

    public function verificarEAtualizarExpirados() {
        $query = "UPDATE {$this->table}
                  SET status = 'expirado'
                  WHERE data_validade < CURDATE() AND status = 'pendente'";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::verificarEAtualizarExpirados: " . $e->getMessage());
            return 0;
        }
    }

    public function updateStatus($id, $novoStatus) {
        $query = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $novoStatus);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::updateStatus: " . $e->getMessage());
            return false;
        }
    }

    public function recalcularValores($orcamento_id) {
        // Chamar getById para garantir que as propriedades do objeto ($this) estejam atualizadas
        // com os últimos valores do banco, incluindo as novas taxas e fretes do formulário.
        $dadosOrcamento = $this->getById($orcamento_id); // getById preenche $this e retorna $row
        if (!$dadosOrcamento) {
            error_log("Erro em Orcamento::recalcularValores: Não foi possível carregar o orçamento ID {$orcamento_id}.");
            return false;
        }

        $itens = $this->getItens($orcamento_id);
        $subtotal_locacao = 0.0;
        $subtotal_venda = 0.0;

        if (!empty($itens)) {
            foreach ($itens as $item) {
                if (isset($item['tipo']) && $item['tipo'] === 'venda') {
                    $subtotal_venda += (float)$item['preco_final'];
                } else { // Assume 'locacao' ou qualquer outro tipo como locação
                    $subtotal_locacao += (float)$item['preco_final'];
                }
            }
        }

        // Atribui os subtotais calculados às propriedades do objeto
        $this->subtotal_locacao = $subtotal_locacao;
        $this->subtotal_venda = $subtotal_venda;
        // Por enquanto, valor_total é igual ao subtotal. Poderia ter outras lógicas no futuro.
        $this->valor_total_locacao = $subtotal_locacao;
        $this->valor_total_venda = $subtotal_venda;

        // Usa as propriedades do objeto $this (que foram preenchidas/atualizadas por getById
        // e potencialmente pelo formulário antes de chamar recalcularValores)
        $total_taxas = (float)($this->taxa_domingo_feriado ?? 0) +
                       (float)($this->taxa_madrugada ?? 0) +
                       (float)($this->taxa_horario_especial ?? 0) +
                       (float)($this->taxa_hora_marcada ?? 0);

        // Considerando frete_terreo numérico. frete_elevador e frete_escadas são varchar no seu BD.
        $total_frete = (float)($this->frete_terreo ?? 0);
        // Se frete_elevador e frete_escadas pudessem ser numéricos e somados:
        // if (is_numeric($this->frete_elevador)) $total_frete += (float)$this->frete_elevador;
        // if (is_numeric($this->frete_escadas)) $total_frete += (float)$this->frete_escadas;


        $this->valor_final = ($this->subtotal_locacao + $this->subtotal_venda + $total_taxas + $total_frete) - (float)($this->desconto ?? 0);

        // Query para atualizar o orçamento no banco
        $query = "UPDATE {$this->table} SET
                  subtotal_locacao = :subtotal_locacao,
                  valor_total_locacao = :valor_total_locacao,
                  subtotal_venda = :subtotal_venda,
                  valor_total_venda = :valor_total_venda,
                  valor_final = :valor_final,
                  desconto = :desconto, /* Adicionado para garantir que o desconto do form seja salvo */
                  taxa_domingo_feriado = :taxa_domingo_feriado,
                  taxa_madrugada = :taxa_madrugada,
                  taxa_horario_especial = :taxa_horario_especial,
                  taxa_hora_marcada = :taxa_hora_marcada,
                  frete_terreo = :frete_terreo,
                  frete_elevador = :frete_elevador,
                  frete_escadas = :frete_escadas
                  /* motivo_ajuste, ajuste_manual, observacoes, condicoes_pagamento já são atualizados no update() principal */
                  WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':subtotal_locacao', $this->subtotal_locacao);
            $stmt->bindParam(':valor_total_locacao', $this->valor_total_locacao);
            $stmt->bindParam(':subtotal_venda', $this->subtotal_venda);
            $stmt->bindParam(':valor_total_venda', $this->valor_total_venda);
            $stmt->bindParam(':valor_final', $this->valor_final);
            $stmt->bindParam(':desconto', $this->desconto);
            $stmt->bindParam(':taxa_domingo_feriado', $this->taxa_domingo_feriado);
            $stmt->bindParam(':taxa_madrugada', $this->taxa_madrugada);
            $stmt->bindParam(':taxa_horario_especial', $this->taxa_horario_especial);
            $stmt->bindParam(':taxa_hora_marcada', $this->taxa_hora_marcada);
            $stmt->bindParam(':frete_terreo', $this->frete_terreo);
            $stmt->bindParam(':frete_elevador', $this->frete_elevador); // Mantém o tipo original
            $stmt->bindParam(':frete_escadas', $this->frete_escadas);   // Mantém o tipo original

            $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em Orcamento::recalcularValores (update): " . $e->getMessage());
            return false;
        }
    }


    public function obterUltimo() {
        $query = "SELECT o.*, c.nome as nome_cliente
                  FROM orcamentos o
                  LEFT JOIN clientes c ON o.cliente_id = c.id
                  ORDER BY o.id DESC
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt; // Retorna o PDOStatement para ser processado no controller
    }
}
?>