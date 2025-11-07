<?php
class EstoqueMovimentacao {
    private $conn;
    private $table_name = "movimentacoes_estoque";
    
    // Propriedades
    public $id;
    public $produto_id;
    public $tipo_movimentacao;
    public $quantidade;
    public $referencia_id;
    public $referencia_tipo;
    public $observacoes;
    public $usuario_id;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para verificar estoque disponível
    public function verificarDisponibilidade($produto_id, $data_inicio, $data_fim) {
        // Lógica aqui
    }
    public function verificarEstoqueSimples($produto_id, $quantidade_solicitada) {
    try {
        $query = "SELECT quantidade_total FROM produtos WHERE id = :produto_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':produto_id', $produto_id);
        $stmt->execute();
        
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produto) return false;
        
        return $produto['quantidade_total'] >= $quantidade_solicitada;
    } catch (Exception $e) {
        return false;
    }
}

public function obterEstoqueTotal($produto_id) {
    try {
        $query = "SELECT quantidade_total FROM produtos WHERE id = :produto_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':produto_id', $produto_id);
        $stmt->execute();
        
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        return $produto ? $produto['quantidade_total'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}
}