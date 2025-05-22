<?php
// require_once '../config/database.php'; // Pode usar caminho relativo se preferir
require_once 'C:/xampp/htdocs/sistema-toalhas/config/database.php'; // Ou caminho absoluto

class Cliente {
    private $conn;
    private $table_name = "clientes";

    // Propriedades do Objeto Cliente
    public $id;
    public $nome;
    public $telefone;
    public $email;
    public $cpf_cnpj; // <<-- PROPRIEDADE ADICIONADA AQUI
    public $endereco;
    public $cidade;
    public $observacoes;
    public $data_cadastro;

    // Construtor
    public function __construct($db) {
        $this->conn = $db;
    }

    // --- MÉTODOS CRUD ---

    // Ler todos os clientes (para a lista principal - não inclui todos os detalhes para performance)
    public function listarTodos() {
        // Se precisar do CPF/CNPJ na lista, adicione c.cpf_cnpj aqui
        $query = "SELECT c.id, c.nome, c.telefone, c.email, c.cpf_cnpj, c.endereco, c.cidade, c.observacoes, c.data_cadastro
                  FROM " . $this->table_name . " c
                  ORDER BY c.nome ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Ler um único cliente pelo ID (incluindo todos os detalhes)
    public function getById($id) {
         $query = "SELECT
                    c.id, c.nome, c.telefone, c.email, c.cpf_cnpj, -- <<-- CAMPO ADICIONADO NA CONSULTA
                    c.endereco, c.cidade, c.observacoes, c.data_cadastro
                  FROM
                    " . $this->table_name . " c
                  WHERE
                    c.id = ?
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $row['id'];
            $this->nome = $row['nome'];
            $this->telefone = $row['telefone'];
            $this->email = $row['email'];
            $this->cpf_cnpj = $row['cpf_cnpj']; // <<-- ATRIBUIÇÃO DA PROPRIEDADE
            $this->endereco = $row['endereco'];
            $this->cidade = $row['cidade'];
            $this->observacoes = $row['observacoes'];
            $this->data_cadastro = $row['data_cadastro'];
            return true;
        }
        return false;
    }
    public function searchByTerm($term) {
        $query = "SELECT 
                    id, 
                    nome, 
                    telefone, 
                    email, 
                    cpf_cnpj, 
                    endereco, 
                    cidade, 
                    observacoes,
                    data_cadastro 
                  FROM " . $this->table_name . " 
                  WHERE 
                    nome LIKE :term OR 
                    email LIKE :term OR 
                    telefone LIKE :term OR 
                    cpf_cnpj LIKE :term
                  ORDER BY nome ASC";
    
        $stmt = $this->conn->prepare($query);
        $searchTerm = '%' . $term . '%';
        $stmt->bindParam(':term', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
    
        return $stmt;
    }
    public function searchByMultipleTerms($nome = '', $email = '', $cpfCnpj = '') {
        $query = "SELECT
                    id,
                    nome,
                    telefone,
                    email,
                    cpf_cnpj,
                    endereco,
                    cidade,
                    observacoes,
                    data_cadastro
                  FROM " . $this->table_name;
        $conditions = [];
        $params = [];
    
        if (!empty($nome)) {
            $conditions[] = "nome LIKE :nome";
            $params[':nome'] = '%' . $nome . '%';
        }
        if (!empty($email)) {
            $conditions[] = "email LIKE :email";
            $params[':email'] = '%' . $email . '%';
        }
        if (!empty($cpfCnpj)) {
            $conditions[] = "cpf_cnpj LIKE :cpf_cnpj";
            $params[':cpf_cnpj'] = '%' . $cpfCnpj . '%';
        }
    
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
    
        //Adicionando proteção contra SQL Injection
        foreach ($params as $key => $value) {
            $params[$key] = htmlspecialchars($value);
        }
    
        $query .= " ORDER BY nome ASC";
    
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
    
        return $stmt;
    }
    // Criar novo cliente
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    nome=:nome, telefone=:telefone, email=:email, cpf_cnpj=:cpf_cnpj, -- <<-- ADICIONADO AO INSERT
                    endereco=:endereco, cidade=:cidade, observacoes=:observacoes";

        $stmt = $this->conn->prepare($query);

        // Limpa os dados (sanitização)
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->telefone = htmlspecialchars(strip_tags($this->telefone));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->cpf_cnpj = htmlspecialchars(strip_tags($this->cpf_cnpj)); // <<-- SANITIZAÇÃO DO CPF/CNPJ
        $this->endereco = htmlspecialchars(strip_tags($this->endereco));
        $this->cidade = htmlspecialchars(strip_tags($this->cidade));
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));

        // Vincula os valores
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":telefone", $this->telefone);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":cpf_cnpj", $this->cpf_cnpj); // <<-- BIND DO CPF/CNPJ
        $stmt->bindParam(":endereco", $this->endereco);
        $stmt->bindParam(":cidade", $this->cidade);
        $stmt->bindParam(":observacoes", $this->observacoes);

        // Executa
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        // error_log("Erro ao criar cliente: " . implode(":", $stmt->errorInfo())); // Log de erro
        return false;
    }

    // Atualizar cliente existente
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    nome = :nome,
                    telefone = :telefone,
                    email = :email,
                    cpf_cnpj = :cpf_cnpj, -- <<-- ADICIONADO AO UPDATE
                    endereco = :endereco,
                    cidade = :cidade,
                    observacoes = :observacoes
                WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Limpa os dados
        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->telefone = htmlspecialchars(strip_tags($this->telefone));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->cpf_cnpj = htmlspecialchars(strip_tags($this->cpf_cnpj)); // <<-- SANITIZAÇÃO DO CPF/CNPJ
        $this->endereco = htmlspecialchars(strip_tags($this->endereco));
        $this->cidade = htmlspecialchars(strip_tags($this->cidade));
        $this->observacoes = htmlspecialchars(strip_tags($this->observacoes));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Vincula os novos valores
        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':telefone', $this->telefone);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':cpf_cnpj', $this->cpf_cnpj); // <<-- BIND DO CPF/CNPJ
        $stmt->bindParam(':endereco', $this->endereco);
        $stmt->bindParam(':cidade', $this->cidade);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':id', $this->id);

        // Executa
        if($stmt->execute()) {
            // Não precisa verificar rowCount aqui necessariamente,
            // pois o usuário pode salvar sem ter alterado nada.
            // A execução sem erro PDO já indica sucesso.
            return true;
        }
        // error_log("Erro ao atualizar cliente: " . implode(":", $stmt->errorInfo())); // Log de erro
        return false;
    }

    // Excluir cliente (método existente)
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return true; // Excluído com sucesso
            } else {
                return false; // ID não encontrado ou erro
            }
        }
        // error_log("Erro ao excluir cliente: " . implode(":", $stmt->errorInfo())); // Log de erro
        return false;
    }

    // NOVO MÉTODO: Alias para lerPorId(), usando getById() para buscar dados como array associativo
    public function lerPorId($id) {
        if ($this->getById($id)) {
            return [
                'id' => $this->id,
                'nome' => $this->nome,
                'telefone' => $this->telefone,
                'email' => $this->email,
                'cpf_cnpj' => $this->cpf_cnpj,
                'endereco' => $this->endereco,
                'cidade' => $this->cidade,
                'observacoes' => $this->observacoes,
                'data_cadastro' => $this->data_cadastro
            ];
        }
        return false;
    }

    // NOVO MÉTODO: Excluir cliente passando o ID diretamente
    public function excluir($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $id = htmlspecialchars(strip_tags($id));
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return true; // Excluído com sucesso
            } else {
                return false; // ID não encontrado ou erro
            }
        }
        // error_log("Erro ao excluir cliente: " . implode(":", $stmt->errorInfo())); // Log de erro
        return false;
    }
}
?>