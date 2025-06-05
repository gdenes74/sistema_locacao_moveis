<?php
class Categoria {
    private $conn;
    private $table_name = "categorias";

    // Propriedades da Categoria
    public $id;
    public $secao_id;
    public $nome;
    public $descricao;
    public $data_cadastro;

    // Propriedades adicionais
    public $secao_nome;

    // Construtor
    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todas as categorias, incluindo o nome da seção
    public function listar() {
        $query = "SELECT
                    c.id, c.secao_id, c.nome, c.descricao, c.data_cadastro,
                    s.nome as secao_nome
                  FROM
                    " . $this->table_name . " c
                  LEFT JOIN
                    secoes s ON c.secao_id = s.id
                  ORDER BY
                    s.nome ASC, c.nome ASC"; // Ordenar por nome da seção, depois nome da categoria

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Buscar categorias APENAS PELO NOME DA CATEGORIA, mas ainda trazendo o nome da seção
    public function buscarPorTermo($termoPesquisa) {
        $query = "SELECT
                    c.id, c.secao_id, c.nome, c.descricao, c.data_cadastro,
                    s.nome as secao_nome  -- Continuamos pegando o nome da seção para exibir
                  FROM
                    " . $this->table_name . " c
                  LEFT JOIN
                    secoes s ON c.secao_id = s.id
                  WHERE
                    c.nome LIKE :termo_categoria  -- BUSCA APENAS NO NOME DA CATEGORIA
                  ORDER BY
                    s.nome ASC, c.nome ASC";

        $stmt = $this->conn->prepare($query);

        // Sanitizar o termo de pesquisa
        $termoPesquisaSanitizado = "%" . htmlspecialchars(strip_tags($termoPesquisa)) . "%";

        // Vincular o parâmetro do nome da categoria
        $stmt->bindParam(":termo_categoria", $termoPesquisaSanitizado);

        $stmt->execute();
        return $stmt; // Retorna o statement PDO para ser iterado na view
    }


    // Buscar uma categoria pelo ID, incluindo nome da seção
    public function buscarPorId() {
        $query = "SELECT
                    c.id, c.secao_id, c.nome, c.descricao, c.data_cadastro,
                    s.nome as secao_nome
                  FROM
                    " . $this->table_name . " c
                  LEFT JOIN
                    secoes s ON c.secao_id = s.id
                  WHERE
                    c.id = :id
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $row['id'];
            $this->secao_id = $row['secao_id'];
            $this->nome = $row['nome'];
            $this->descricao = $row['descricao'];
            $this->data_cadastro = $row['data_cadastro'];
            $this->secao_nome = $row['secao_nome']; // Pega o nome da seção do JOIN
            return true;
        }

        return false;
    }

    // Criar nova categoria
    public function criar() {
        // Query de inserção
        $query = "INSERT INTO " . $this->table_name . " (secao_id, nome, descricao)
                  VALUES (:secao_id, :nome, :descricao)";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->secao_id = htmlspecialchars(strip_tags($this->secao_id));
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));

        // Vincular parâmetros
        $stmt->bindParam(":secao_id", $this->secao_id);
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":descricao", $this->descricao);

        // Executar
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    // Atualizar categoria existente
    public function atualizar() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                    secao_id = :secao_id,
                    nome = :nome,
                    descricao = :descricao
                  WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->secao_id = htmlspecialchars(strip_tags($this->secao_id));
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));

        // Vincular parâmetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':secao_id', $this->secao_id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':descricao', $this->descricao);

        // Executar
        if($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Excluir categoria
    public function excluir() {
        if ($this->temSubcategoriasVinculadas()) {
            return false;
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Método auxiliar para verificar subcategorias vinculadas
    private function temSubcategoriasVinculadas() {
        $query = "SELECT COUNT(*) as total FROM subcategorias WHERE categoria_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] > 0;
    }

    // Método para buscar categorias por seção (útil para selects dependentes)
    public function buscarPorSecao($secao_id) {
         $query = "SELECT id, nome
                   FROM " . $this->table_name . "
                   WHERE secao_id = :secao_id
                   ORDER BY nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":secao_id", $secao_id);
        $stmt->execute();
        return $stmt;
    }
}
?>