<?php
class ConfiguracaoTexto
{
    private $conn;
    private $table_name = "configuracoes_textos";

    public $id;
    public $chave;
    public $titulo;
    public $conteudo;
    public $ativo;
    public $usuario_id;
    public $data_cadastro;
    public $ultima_atualizacao;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->ativo = 1;
    }

    private function limparChave($chave)
    {
        $chave = strtolower(trim((string)$chave));
        $chave = preg_replace('/[^a-z0-9_\-]/', '_', $chave);
        $chave = preg_replace('/_+/', '_', $chave);
        return trim($chave, '_');
    }

    public function listarTodos($filtros = [])
    {
        $query = "SELECT id, chave, titulo, conteudo, ativo, usuario_id, data_cadastro, ultima_atualizacao
                  FROM {$this->table_name}
                  WHERE 1=1";
        $params = [];

        if (!empty($filtros['pesquisar'])) {
            $query .= " AND (chave LIKE :pesquisar OR titulo LIKE :pesquisar OR conteudo LIKE :pesquisar)";
            $params[':pesquisar'] = '%' . trim($filtros['pesquisar']) . '%';
        }

        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $query .= " AND ativo = :ativo";
            $params[':ativo'] = (int)$filtros['ativo'];
        }

        $query .= " ORDER BY ativo DESC, titulo ASC, chave ASC";

        try {
            $stmt = $this->conn->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::listarTodos: " . $e->getMessage());
            return false;
        }
    }

    public function buscarPorId()
    {
        $query = "SELECT id, chave, titulo, conteudo, ativo, usuario_id, data_cadastro, ultima_atualizacao
                  FROM {$this->table_name}
                  WHERE id = :id
                  LIMIT 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $this->id = (int)$row['id'];
            $this->chave = $row['chave'];
            $this->titulo = $row['titulo'];
            $this->conteudo = $row['conteudo'];
            $this->ativo = (int)$row['ativo'];
            $this->usuario_id = $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null;
            $this->data_cadastro = $row['data_cadastro'];
            $this->ultima_atualizacao = $row['ultima_atualizacao'];
            return true;
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::buscarPorId: " . $e->getMessage());
            return false;
        }
    }

    public function buscarPorChave($chave)
    {
        $query = "SELECT id, chave, titulo, conteudo, ativo, usuario_id, data_cadastro, ultima_atualizacao
                  FROM {$this->table_name}
                  WHERE chave = :chave
                  LIMIT 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':chave', $this->limparChave($chave), PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $this->id = (int)$row['id'];
            $this->chave = $row['chave'];
            $this->titulo = $row['titulo'];
            $this->conteudo = $row['conteudo'];
            $this->ativo = (int)$row['ativo'];
            $this->usuario_id = $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null;
            $this->data_cadastro = $row['data_cadastro'];
            $this->ultima_atualizacao = $row['ultima_atualizacao'];
            return true;
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::buscarPorChave: " . $e->getMessage());
            return false;
        }
    }

    public function criar()
    {
        $this->chave = $this->limparChave($this->chave);
        $this->titulo = trim((string)$this->titulo);
        $this->conteudo = trim((string)$this->conteudo);
        $this->ativo = !empty($this->ativo) ? 1 : 0;
        $this->usuario_id = !empty($this->usuario_id) ? (int)$this->usuario_id : null;

        if ($this->chave === '' || $this->titulo === '') {
            return false;
        }

        $query = "INSERT INTO {$this->table_name}
                  (chave, titulo, conteudo, ativo, usuario_id)
                  VALUES
                  (:chave, :titulo, :conteudo, :ativo, :usuario_id)";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':chave', $this->chave, PDO::PARAM_STR);
            $stmt->bindValue(':titulo', $this->titulo, PDO::PARAM_STR);
            $stmt->bindValue(':conteudo', $this->conteudo, PDO::PARAM_STR);
            $stmt->bindValue(':ativo', $this->ativo, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $this->usuario_id, $this->usuario_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->id = (int)$this->conn->lastInsertId();
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::criar: " . $e->getMessage());
            return false;
        }
    }

    public function atualizar()
    {
        $this->id = (int)$this->id;
        $this->chave = $this->limparChave($this->chave);
        $this->titulo = trim((string)$this->titulo);
        $this->conteudo = trim((string)$this->conteudo);
        $this->ativo = !empty($this->ativo) ? 1 : 0;
        $this->usuario_id = !empty($this->usuario_id) ? (int)$this->usuario_id : null;

        if ($this->id <= 0 || $this->chave === '' || $this->titulo === '') {
            return false;
        }

        $query = "UPDATE {$this->table_name}
                  SET chave = :chave,
                      titulo = :titulo,
                      conteudo = :conteudo,
                      ativo = :ativo,
                      usuario_id = :usuario_id
                  WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindValue(':chave', $this->chave, PDO::PARAM_STR);
            $stmt->bindValue(':titulo', $this->titulo, PDO::PARAM_STR);
            $stmt->bindValue(':conteudo', $this->conteudo, PDO::PARAM_STR);
            $stmt->bindValue(':ativo', $this->ativo, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $this->usuario_id, $this->usuario_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::atualizar: " . $e->getMessage());
            return false;
        }
    }

    public function desativar()
    {
        if (empty($this->id)) {
            return false;
        }

        $query = "UPDATE {$this->table_name} SET ativo = 0 WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::desativar: " . $e->getMessage());
            return false;
        }
    }

    public static function obterConteudo($db, $chave, $fallback = '')
    {
        try {
            $stmt = $db->prepare("SELECT conteudo FROM configuracoes_textos WHERE chave = :chave AND ativo = 1 LIMIT 1");
            $stmt->bindValue(':chave', trim((string)$chave), PDO::PARAM_STR);
            $stmt->execute();
            $conteudo = $stmt->fetchColumn();
            return $conteudo !== false ? (string)$conteudo : (string)$fallback;
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::obterConteudo: " . $e->getMessage());
            return (string)$fallback;
        }
    }

    public static function carregarMapa($db, array $chaves)
    {
        $mapa = [];
        foreach ($chaves as $chave) {
            $mapa[$chave] = '';
        }

        if (empty($chaves)) {
            return $mapa;
        }

        try {
            $placeholders = [];
            $params = [];
            foreach ($chaves as $i => $chave) {
                $param = ':chave' . $i;
                $placeholders[] = $param;
                $params[$param] = $chave;
            }

            $sql = "SELECT chave, conteudo FROM configuracoes_textos WHERE ativo = 1 AND chave IN (" . implode(',', $placeholders) . ")";
            $stmt = $db->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $mapa[$row['chave']] = $row['conteudo'];
            }

            return $mapa;
        } catch (PDOException $e) {
            error_log("Erro em ConfiguracaoTexto::carregarMapa: " . $e->getMessage());
            return $mapa;
        }
    }
}
?>
