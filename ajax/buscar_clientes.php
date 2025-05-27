<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();  // Use getConnection em vez de getInstance
    
    $sql = "SELECT id, nome, telefone, email, endereco 
            FROM clientes 
            WHERE nome LIKE :search 
            ORDER BY nome 
            LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $searchTerm = '%' . $search . '%';
    $stmt->bindParam(':search', $searchTerm);
    $stmt->execute();
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'text' => $row['nome'],
            'telefone' => $row['telefone'],
            'email' => $row['email'],
            'endereco' => $row['endereco']
        ];
    }
    
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    error_log('Erro ao buscar clientes: ' . $e->getMessage());
    echo json_encode(['error' => 'Erro ao buscar clientes']);
}