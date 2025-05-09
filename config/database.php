<?php
/**
 * Classe Database - Responsável pela conexão com o banco de dados usando PDO
 */
class Database {
    // Configurações de conexão com o banco de dados (ajuste conforme seu ambiente)
    private $host = 'localhost'; // Host do banco de dados
    private $dbname = 'sistema_toalhas'; // Nome do banco de dados
    private $username = 'root'; // Usuário do banco de dados
    private $password = 'mobel'; // Senha do banco de dados
    private $conn; // Variável para armazenar a conexão PDO

    /**
     * Obtém a conexão com o banco de dados
     * @return PDO|null Retorna a conexão PDO ou null em caso de erro
     */
    public function getConnection(): ?PDO {
        $this->conn = null;
        try {
            // Cria uma nova conexão PDO com charset UTF-8 para suportar acentos
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            // Configura PDO para lançar exceções em caso de erro
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Define o modo de fetch padrão como associativo
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Desativa a emulação de prepared statements para maior segurança
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            // Registra o erro no log para depuração
            error_log("[Database] Erro de conexão com o banco de dados: " . $e->getMessage());
            // Exibe o erro apenas para depuração (pode ser removido em produção)
            die("Erro de conexão com o banco de dados: " . $e->getMessage());
        }
        return $this->conn;
    }
}
?>