<?php
class NumeracaoSequencial {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function gerarProximoNumero($tipo) {
        try {
            // Buscar o último número do tipo
            $query = "SELECT MAX(numero) as ultimo_numero FROM numeracao_sequencial WHERE tipo = :tipo";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $ultimoNumero = $resultado['ultimo_numero'] ?? 0;
            $proximoNumero = intval($ultimoNumero) + 1;
            
            // Inserir o novo número
            $inserir = "INSERT INTO numeracao_sequencial (numero, tipo, data_atribuicao) VALUES (:numero, :tipo, NOW())";
            $stmt = $this->db->prepare($inserir);
            $stmt->bindParam(':numero', $proximoNumero, PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->execute();
            
            return $proximoNumero;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao gerar número sequencial: " . $e->getMessage());
        }
    }
    
    public function formatarNumeroOrcamento($numero) {
        return 'ORC-' . date('Y') . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
    
    public function formatarNumeroPedido($numero) {
        return 'PED-' . date('Y') . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
}
?>