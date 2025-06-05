<?php
class Subcategoria {
    private $conn;
    private $table_name = "subcategorias"; // Nome da tabela no BD

    // Propriedades da Subcategoria
    public $id;
    public $categoria_id; // Chave estrangeira para a tabela 'categorias'
    public $nome;
    public $descricao;
    public $data_cadastro;

    // Propriedades adicionais para buscar dados relacionados (útil)
    public $categoria_nome; 
    public $secao_id;       // ID da seção (via categoria)
    public $secao_nome;     // Nome da seção (via categoria)


    // Construtor
    public function __construct($db) {
        $this->conn = $db;
    }

    // Listar todas as subcategorias, incluindo nome da categoria e da seção
    public function listar() {
        // Query com JOIN duplo: subcategorias -> categorias -> secoes
        $query = "SELECT 
                    sc.id, sc.categoria_id, sc.nome, sc.descricao, sc.data_cadastro,
                    c.nome as categoria_nome, 
                    c.secao_id,        -- Pega o ID da seção da tabela categorias
                    s.nome as secao_nome -- Pega o nome da seção da tabela secoes
                  FROM 
                    " . $this->table_name . " sc 
                  LEFT JOIN 
                    categorias c ON sc.categoria_id = c.id 
                  LEFT JOIN
                    secoes s ON c.secao_id = s.id
                  ORDER BY 
                    s.nome ASC, c.nome ASC, sc.nome ASC"; // Ordena por seção, categoria, subcategoria

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Buscar subcategorias APENAS PELO NOME DA SUBCATEGORIA, mas trazendo dados relacionados
    public function buscarPorTermo($termoPesquisa) {
        $query = "SELECT 
                    sc.id, sc.categoria_id, sc.nome, sc.descricao, sc.data_cadastro,
                    c.nome as categoria_nome, 
                    c.secao_id,
                    s.nome as secao_nome
                  FROM 
                    " . $this->table_name . " sc 
                  LEFT JOIN 
                    categorias c ON sc.categoria_id = c.id 
                  LEFT JOIN
                    secoes s ON c.secao_id = s.id
                  WHERE 
                    sc.nome LIKE :termo_subcategoria  -- <<--- ALTERAÇÃO AQUI: Busca apenas no nome da subcategoria
                  ORDER BY 
                    s.nome ASC, c.nome ASC, sc.nome ASC";

        $stmt = $this->conn->prepare($query);

        // Sanitizar o termo de pesquisa
        $termoPesquisaSanitizado = "%" . htmlspecialchars(strip_tags($termoPesquisa)) . "%";

        // Vincular o parâmetro do nome da subcategoria
        $stmt->bindParam(":termo_subcategoria", $termoPesquisaSanitizado);
        // Não precisamos mais vincular :termo_categoria e :termo_secao para a busca

        $stmt->execute();
        return $stmt;
    }

    // Buscar uma subcategoria pelo ID, incluindo dados relacionados
    public function buscarPorId() {
        $query = "SELECT 
                    sc.id, sc.categoria_id, sc.nome, sc.descricao, sc.data_cadastro,
                    c.nome as categoria_nome,
                    c.secao_id,
                    s.nome as secao_nome
                  FROM 
                    " . $this->table_name . " sc 
                  LEFT JOIN 
                    categorias c ON sc.categoria_id = c.id 
                  LEFT JOIN
                    secoes s ON c.secao_id = s.id
                  WHERE 
                    sc.id = :id 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id = $row['id'];
            $this->categoria_id = $row['categoria_id'];
            $this->nome = $row['nome'];
            $this->descricao = $row['descricao'];
            $this->data_cadastro = $row['data_cadastro'];
            $this->categoria_nome = $row['categoria_nome'];
            $this->secao_id = $row['secao_id'];
            $this->secao_nome = $row['secao_nome'];
            return true;
        }
        
        return false;
    }

    // Criar nova subcategoria
    public function criar() {
        $query = "INSERT INTO " . $this->table_name . " (categoria_id, nome, descricao) 
                  VALUES (:categoria_id, :nome, :descricao)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpar dados
        $this->categoria_id = htmlspecialchars(strip_tags($this->categoria_id));
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));
        
        // Vincular parâmetros
        $stmt->bindParam(":categoria_id", $this->categoria_id);
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":descricao", $this->descricao);
        
        // Executar
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    // Atualizar subcategoria existente
    public function atualizar() {
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    categoria_id = :categoria_id, 
                    nome = :nome, 
                    descricao = :descricao 
                  WHERE 
                    id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpar dados
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->categoria_id = htmlspecialchars(strip_tags($this->categoria_id));
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->descricao = htmlspecialchars(strip_tags($this->descricao));
        
        // Vincular parâmetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':categoria_id', $this->categoria_id);
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':descricao', $this->descricao);
        
        // Executar
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    // Excluir subcategoria
    public function excluir() {
        // 1. Verificar se existem PRODUTOS vinculados a esta subcategoria (IMPORTANTE!)
        //    Assumindo que a tabela 'produtos' tem uma coluna 'subcategoria_id'
        if ($this->temProdutosVinculados()) {
             // $_SESSION['error_message'] = "Não é possível excluir esta subcategoria pois existem produtos vinculados a ela.";
            return false; 
        }

        // 2. Se não houver produtos, prosseguir com a exclusão
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

    // Método auxiliar para verificar produtos vinculados
    private function temProdutosVinculados() {
        // Adapte o nome da tabela 'produtos' e da coluna 'subcategoria_id' se forem diferentes
        $query = "SELECT COUNT(*) as total FROM produtos WHERE subcategoria_id = :id"; 
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Retorna true se houver 1 ou mais produtos vinculados
        return $row['total'] > 0; 
    }

    // Método para buscar subcategorias por categoria (útil para selects dependentes)
    public function buscarPorCategoria($categoria_id) {
         $query = "SELECT id, nome 
                   FROM " . $this->table_name . " 
                   WHERE categoria_id = :categoria_id 
                   ORDER BY nome ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":categoria_id", $categoria_id);
        $stmt->execute();
        return $stmt;
    }
}
?>