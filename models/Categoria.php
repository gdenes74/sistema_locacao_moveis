<?php
class Categoria {
    private $conn;
    private $table_name = "categorias"; // Nome da tabela no BD

    // Propriedades da Categoria
    public $id;
    public $secao_id; // Chave estrangeira para a tabela 'secoes'
    public $nome;
    public $descricao;
    public $data_cadastro;

    // Propriedades adicionais para buscar dados da seção relacionada (opcional, mas útil)
    public $secao_nome; 

    // Construtor
    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todas as categorias, incluindo o nome da seção
    public function listar() {
        // Query que junta categorias com secoes para pegar o nome da seção
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
        // Query de inserção - note o campo secao_id
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
        // 1. Verificar se existem SUBCATEGORIAS vinculadas a esta categoria
        if ($this->temSubcategoriasVinculadas()) {
             // $_SESSION['error_message'] = "Não é possível excluir esta categoria pois existem subcategorias vinculadas a ela.";
            return false; 
        }

        // 2. Se não houver subcategorias, prosseguir com a exclusão
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

    // Método auxiliar para verificar subcategorias vinculadas
    private function temSubcategoriasVinculadas() {
        // Assume que a tabela de subcategorias se chama 'subcategorias' 
        // e tem uma coluna 'categoria_id'
        $query = "SELECT COUNT(*) as total FROM subcategorias WHERE categoria_id = :id"; 
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Retorna true se houver 1 ou mais subcategorias vinculadas
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