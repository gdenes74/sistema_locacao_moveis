<?php

/**
 * Classe auxiliar para lidar com uploads de arquivos de forma segura.
 */
class FileUpload {

    private $uploadDir; // Diretório de destino físico no servidor
    private $maxSize;   // Tamanho máximo permitido em bytes
    private $allowedTypes; // Array de tipos MIME permitidos
    private $lastError = ''; // Mensagem do último erro ocorrido
    private $lastUploadReused = false; // Indica se o conteúdo já existia no diretório

    // Constantes para facilitar a configuração de tamanho
    const KB = 1024;
    const MB = 1048576; // 1024 * 1024

    /**
     * Construtor da classe.
     *
     * @param string $uploadDir O caminho para a pasta de uploads no servidor.
     * @param int $maxSize O tamanho máximo do arquivo em bytes (padrão 2MB).
     * @param array $allowedTypes Os tipos MIME permitidos (padrão imagens comuns).
     */
    public function __construct(string $uploadDir, int $maxSize = (2 * self::MB), array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
        // Garante que o diretório de upload exista e tenha permissão de escrita
        if (!is_dir($uploadDir)) {
            // Tenta criar o diretório recursivamente
            if (!mkdir($uploadDir, 0775, true)) { // Permissões podem precisar de ajuste no servidor
                 throw new Exception("Erro: O diretório de upload '{$uploadDir}' não existe e não pôde ser criado.");
            }
        }
        if (!is_writable($uploadDir)) {
            throw new Exception("Erro: O diretório de upload '{$uploadDir}' não tem permissão de escrita.");
        }

        $this->uploadDir = rtrim($uploadDir, '/') . '/'; // Garante a barra no final
        $this->maxSize = $maxSize;
        $this->allowedTypes = $allowedTypes;
    }

    /**
     * Processa o upload de um arquivo.
     *
     * @param array $fileInfo O array de informações do arquivo vindo de $_FILES['input_name'].
     * @param string $filePrefix Um prefixo opcional para o nome do arquivo salvo.
     * @return string O nome único do arquivo salvo no diretório de upload em caso de sucesso.
     * @throws Exception Em caso de erro durante o upload (arquivo não enviado, erro no upload, tamanho excedido, tipo inválido, falha ao mover).
     */
    public function upload(array $fileInfo, string $filePrefix = 'file_'): string {
        $this->lastError = ''; // Reseta o erro
        $this->lastUploadReused = false;

        // 1. Verificar erros básicos de upload do PHP
        if (!isset($fileInfo['error']) || is_array($fileInfo['error'])) {
            throw new Exception('Parâmetros de arquivo inválidos.');
        }
        switch ($fileInfo['error']) {
            case UPLOAD_ERR_OK:
                break; // Sem erro, continuar
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('Nenhum arquivo foi enviado.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('O arquivo enviado excede o limite de tamanho permitido.');
            default:
                throw new Exception('Erro desconhecido no upload do arquivo.');
        }

        // 2. Verificar o tamanho real do arquivo temporário
        $actualFileSize = filesize($fileInfo['tmp_name']);
        if ($actualFileSize === false) {
            throw new Exception('Não foi possível verificar o tamanho do arquivo enviado.');
        }

        if ($actualFileSize > $this->maxSize) {
            throw new Exception('O arquivo enviado excede o limite de tamanho permitido (' . self::getMaxSizeStr($this->maxSize) . ').');
        }

        // 3. Verificar o tipo MIME do arquivo (mais seguro que a extensão)
        // Usamos finfo para obter o tipo MIME real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileMimeType = $finfo->file($fileInfo['tmp_name']);
        if (false === array_search($fileMimeType, $this->allowedTypes, true)) {
             throw new Exception('Tipo de arquivo inválido (' . htmlspecialchars($fileMimeType) . '). Tipos permitidos: ' . implode(', ', $this->allowedTypes));
        }

        // 4. Reutilizar uma imagem idêntica que já esteja no diretório.
        // Isso evita criar foto_xxx_arquivo.jpg toda vez que uma foto já existente
        // em assets/uploads/produtos é escolhida novamente no formulário.
        $existingFileName = $this->findIdenticalFile(
            $fileInfo['tmp_name'],
            (int)$actualFileSize
        );

        if ($existingFileName !== null) {
            $this->lastUploadReused = true;
            return $existingFileName;
        }

        // 5. Gerar um nome de arquivo único e seguro.
        // A extensão é definida pelo MIME real, não pelo nome informado pelo navegador.
        $extensionsByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $extension = $extensionsByMime[$fileMimeType] ?? strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        
        // Gera nome único: prefixo + timestamp + string aleatória + .extensao
        $safeFileName = $filePrefix . dechex(time()) . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        // 6. Montar o caminho completo de destino
        $destinationPath = $this->uploadDir . $safeFileName;

        // 7. Mover o arquivo do diretório temporário para o destino final
        if (!move_uploaded_file($fileInfo['tmp_name'], $destinationPath)) {
            throw new Exception('Falha ao mover o arquivo enviado para o diretório de destino.');
        }

        // 8. Retornar o nome do arquivo salvo (sem o caminho completo)
        return $safeFileName;
    }

    /**
     * Procura um arquivo com o mesmo conteúdo do upload.
     *
     * Primeiro compara o tamanho e somente depois calcula o SHA-256. Entre
     * arquivos idênticos, dá preferência ao nome original, sem os prefixos
     * automáticos foto_ e prod_, para manter um caminho canônico.
     */
    private function findIdenticalFile(string $temporaryPath, int $fileSize): ?string {
        $entries = scandir($this->uploadDir);
        if ($entries === false) {
            return null;
        }

        $candidates = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }

            $candidatePath = $this->uploadDir . $entry;
            if (!is_file($candidatePath) || filesize($candidatePath) !== $fileSize) {
                continue;
            }

            $candidates[] = $entry;
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static function (string $a, string $b): int {
            $aAutomatic = preg_match('/^(foto_|prod_)/i', $a) === 1;
            $bAutomatic = preg_match('/^(foto_|prod_)/i', $b) === 1;

            if ($aAutomatic !== $bAutomatic) {
                return $aAutomatic <=> $bAutomatic;
            }

            $lengthComparison = strlen($a) <=> strlen($b);
            return $lengthComparison !== 0 ? $lengthComparison : strcasecmp($a, $b);
        });

        $uploadedHash = hash_file('sha256', $temporaryPath);
        if ($uploadedHash === false) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $candidateHash = hash_file('sha256', $this->uploadDir . $candidate);
            if ($candidateHash !== false && hash_equals($uploadedHash, $candidateHash)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Tenta excluir um arquivo do diretório de upload.
     *
     * @param string $filename O nome do arquivo (sem o caminho) a ser excluído.
     * @return bool True se o arquivo foi excluído com sucesso ou não existia, False se ocorreu um erro.
     */
    public function delete(string $filename): bool {
        $this->lastError = '';
        if (empty($filename)) {
            return true; // Nada a fazer se o nome do arquivo for vazio
        }

        $filePath = $this->uploadDir . basename($filename); // basename para segurança extra

        if (file_exists($filePath)) {
            if (is_writable($filePath)) {
                if (unlink($filePath)) {
                    return true; // Excluído com sucesso
                } else {
                    $this->lastError = "Erro ao tentar excluir o arquivo '{$filename}'.";
                    error_log($this->lastError); // Logar o erro é uma boa prática
                    return false; // Falha ao excluir
                }
            } else {
                 $this->lastError = "Erro: Sem permissão para excluir o arquivo '{$filename}'.";
                 error_log($this->lastError);
                 return false; // Sem permissão
            }
        }
        return true; // Arquivo não existia, considera sucesso
    }


    /**
     * Retorna a mensagem do último erro ocorrido.
     * @return string
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * Informa se o último upload reutilizou um arquivo idêntico já existente.
     */
    public function wasFileReused(): bool {
        return $this->lastUploadReused;
    }

    /**
     * Retorna uma string formatada representando o tamanho máximo permitido.
     * @param int|null $size Tamanho em bytes (opcional, usa $this->maxSize por padrão)
     * @return string
     */
    public static function getMaxSizeStr(?int $size = null): string {
        $size = $size ?? (2 * self::MB); // Linha CORRETA: Usa o cálculo do valor padrão
         if ($size >= self::MB) {
            return round($size / self::MB, 1) . ' MB';
        } elseif ($size >= self::KB) {
            return round($size / self::KB, 0) . ' KB';
        }
        return $size . ' bytes';
    }
}
