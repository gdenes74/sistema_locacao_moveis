<?php
class NumeracaoSequencial {
    private $db;
    
    /**
     * Construtor da classe NumeracaoSequencial.
     *
     * @param PDO $database Uma instância da conexão PDO com o banco de dados.
     */
    public function __construct(PDO $database) {
        $this->db = $database;
    }
    
    /**
     * Gera o próximo número sequencial para um determinado tipo de documento.
     * Utiliza transação e bloqueio de linha para garantir atomicidade (no-gap para o contador)
     * e evitar condições de corrida em ambientes multiusuário.
     *
     * @param string $tipo O tipo de documento para o qual gerar o número (ex: 'orcamento', 'pedido').
     * @return int O próximo número sequencial disponível para uso.
     * @throws Exception Se ocorrer um erro irrecuperável ao gerar o número.
     */
    public function gerarProximoNumero($tipo) {
        try {
            // Iniciar uma transação para garantir que a leitura e a gravação
            // do contador sequencial sejam atômicas.
           //REMOVIDO $this->db->beginTransaction();

            // 1. Tentar selecionar o próximo número disponível para o tipo especificado.
            // A cláusula `FOR UPDATE` BLOQUEIA a linha selecionada na tabela `sequencias`,
            // prevenindo que outras transações a modifiquem ou a leiam com `FOR UPDATE`
            // até que esta transação seja commitada ou revertida.
            // Isso resolve a condição de corrida para a GERAÇÃO do número.
            $query = "SELECT proximo_numero_disponivel FROM sequencias WHERE tipo = :tipo FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            $proximoNumero = 1; // Valor padrão inicial, caso o tipo não exista ainda

            if ($resultado) {
                // Se o tipo já existe na tabela 'sequencias', pegamos o número atual.
                $proximoNumero = $resultado['proximo_numero_disponivel'];
            } else {
                // Se o tipo não existe, ele será inserido na tabela 'sequencias' com o valor inicial (1).
                $insert_type_query = "INSERT INTO sequencias (tipo, proximo_numero_disponivel) VALUES (:tipo, 1)";
                $insert_type_stmt = $this->db->prepare($insert_type_query);
                $insert_type_stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
                if(!$insert_type_stmt->execute()) {//Verifica se o insert foi bem sucedido
                throw new PDOException("Falha ao inserir novo tipo de sequência:".$tipo);
             }

                // $proximoNumero já é 1
            }
            
            // 2. Atualizar o `proximo_numero_disponivel` para o PRÓXIMO valor na tabela `sequencias`.
            // Isso significa que o número que ACABAMOS de pegar (e vamos retornar)
            // é o `proximo_numero_disponivel` ANTES desta atualização.
            $update_query = "UPDATE sequencias SET proximo_numero_disponivel = proximo_numero_disponivel + 1 WHERE tipo = :tipo";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
             if (!$update_stmt->execute()) { // Verifica se o update foi bem sucedido
                 throw new PDOException("Falha ao atualizar o próximo número para a sequência: " . $tipo);
            }
            
            // Confirmar a transação. Se todas as operações foram bem-sucedidas,
            // as alterações no banco de dados são salvas permanentemente.
            //removido $this->db->commit();
            
            // Retorna o número que foi reservado nesta transação para ser usado.
            return $proximoNumero;
            
        } catch (PDOException $e) {
            // REMOVIDO: if ($this->db->inTransaction()) { $this->db->rollBack(); }
            // Apenas loga e relança a exceção. O rollback será feito pelo chamador.
            error_log("Erro PDO ao gerar número sequencial ({$tipo}): " . $e->getMessage());
            throw new Exception("Erro ao gerar número sequencial (DB): " . $e->getMessage());
        } catch (Exception $e) { // Captura outras exceções que não sejam PDOException
            // REMOVIDO: if ($this->db->inTransaction()) { $this->db->rollBack(); }
            error_log("Erro geral ao gerar número sequencial ({$tipo}): " . $e->getMessage());
            throw new Exception("Erro geral ao gerar número sequencial: " . $e->getMessage());
        }
    }
    
    
    /**
     * Formata o número de um orçamento com prefixo e ano.
     * @param int $numero O número sequencial bruto do orçamento.
     * @return string O número do orçamento formatado.
     */
    public function formatarNumeroOrcamento($numero) {
        return 'ORC-' . date('Y') . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Formata o número de um pedido com prefixo e ano.
     * @param int $numero O número sequencial bruto do pedido.
     * @return string O número do pedido formatado.
     */
    public function formatarNumeroPedido($numero) {
        return 'PED-' . date('Y') . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
}
?>