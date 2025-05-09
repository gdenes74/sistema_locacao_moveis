<?php
class Secao {
    private $conn;
    private $table_name = "secoes"; // Nome correto da tabela no BD

    // Propriedades da Seção (correspondentes às colunas da tabela)
    public $id;
    public $nome;
    public $descricao;
    public $data_cadastro; // Adicionando, caso queira rastrear quando foi criada

    // Construtor com a conexão do banco de dados
    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todas as seções
    public function listar() {
        $query = "SELECT id, nome, descricao, data_cadastro 
                  FROM " . $this->table_name . " 
                  ORDER BY nome ASC"; // Ordenar por nome para melhor visualização

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt; // Retorna o statement PDO para ser iterado na view
    }

    // Buscar uma seção pelo ID
    public function buscarPorId() {
        $query = "SELECT id, nome, descricao, data_cadastro 
                  FROM " . $this->table_name . " 
                  WHERE id = :id 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);

        // Vincular o ID
        $stmt->bindParam(":id", $this->id);

        // Executar a query
        $stmt->execute();

        // Obter a linha
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Definir valores para as propriedades do objeto, se a seção for encontrada
        if($row) {
            $this->id = $row['id']; // Certificar que o ID está correto
            $this->nome = $row['nome'];
            $this->descricao = $row['descricao'];
            $this->data_cadastro = $row['data_cadastro'];
            return true; // Seção encontrada
        }

        return false; // Seção não encontrada
    }

    // Criar nova seção
    public function criar() {
        // Query de inserção
        $query = "INSERT INTO " . $this->table_name . " (nome, descricao) 
                  VALUES (:nome, :descricao)";
                  // data_cadastro pode ser DEFAULT CURRENT_TIMESTAMP no BD

        $stmt = $this->conn->prepare($query);

        // Limpar dados (sanitização básica)
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));

        // Vincular parâmetros
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":descricao", $this->descricao);

        // Executar a query
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId(); // Obter o ID inserido
            return true;
        }

        // Imprimir erro se falhar (útil para debug)
        // printf("Erro: %s.\n", $stmt->errorInfo()[2]); 
        return false;
    }

    // Atualizar seção existente
    public function atualizar() {
        $query = "UPDATE " . $this->table_name . " 
                  SET nome = :nome, descricao = :descricao 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));

        // Vincular parâmetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':descricao', $this->descricao);

        // Executar a query
        if($stmt->execute()) {
            // Verificar se alguma linha foi realmente afetada
            // return $stmt->rowCount() > 0; 
            return true; // Retorna true mesmo se os dados forem os mesmos
        }
        
        return false;
    }

    // Excluir seção
    public function excluir() {
        // 1. Verificar se existem categorias vinculadas a esta seção
        if ($this->temCategoriasVinculadas()) {
            // Não permitir exclusão se houver categorias
            // Poderíamos definir uma mensagem de erro aqui para exibir na view
            // $_SESSION['error_message'] = "Não é possível excluir esta seção pois existem categorias vinculadas a ela.";
            return false; 
        }

        // 2. Se não houver categorias, prosseguir com a exclusão
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Limpar ID
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Vincular ID
        $stmt->bindParam(':id', $this->id);

        // Executar
        if($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Método auxiliar para verificar categorias vinculadas
    private function temCategoriasVinculadas() {
        $query = "SELECT COUNT(*) as total FROM categorias WHERE secao_id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] > 0; // Retorna true se houver 1 ou mais categorias
    }
}
?>