<?php
// Configurações gerais do sistema
define('BASE_URL', 'http://localhost/sistema-toalhas/'); // URL base do sistema **IMPORTANTE: Terminar com / **
define('BASE_PATH', __DIR__); // Caminho absoluto para a pasta config (pode ser útil, mas __DIR__ já faz isso localmente)
define('PROJECT_ROOT', dirname(__DIR__)); // Caminho absoluto para a raiz do projeto (C:\xampp\htdocs\sistema-toalhas) - MAIS ÚTIL
define('APP_NAME', 'Sistema de Controle de Toalhas');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'America/Sao_Paulo'); // Fuso horário do sistema

// Configurações de upload
define('UPLOAD_DIR_REL', 'assets/uploads/'); // Diretório relativo para uploads (a partir da BASE_URL)
define('UPLOAD_DIR_ABS', PROJECT_ROOT . '/' . UPLOAD_DIR_REL); // Caminho absoluto para uploads usando PROJECT_ROOT
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB em bytes (mais legível)
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']); // Tipos permitidos

// --- CONFIGURAÇÕES DA SESSÃO ---

// Define o fuso horário global
date_default_timezone_set(TIMEZONE);

// Apenas configure e inicie a sessão se nenhuma ainda foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configurações do tempo de expiração no servidor (Ex: 2 horas)
    ini_set('session.gc_maxlifetime', 7200);

    // Configurações do cookie da sessão (Ex: 2 horas, path /, domínio atual, secure (se HTTPS), httponly)
    $secure_cookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; // Detecta HTTPS
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '', // Domínio atual
        'secure' => $secure_cookie,
        'httponly' => true, // Essencial para segurança
        'samesite' => 'Lax' // 'Lax' ou 'Strict' para proteção CSRF
    ]);

    // Inicia a sessão
    session_start();
}

// --- FUNÇÕES AUXILIARES ESSENCIAIS (Definidas aqui por dependência mínima) ---

/**
 * Redireciona para um caminho relativo ao BASE_URL
 * @param string $path O caminho relativo para redirecionar (ex: 'views/produtos/index.php')
 */
function redirect(string $path): void { // Adicionado tipo de retorno void
    // Garante que BASE_URL termine com / e path não comece com /
    $baseUrl = rtrim(BASE_URL, '/') . '/';
    $targetPath = ltrim($path, '/');
    header('Location: ' . $baseUrl . $targetPath);
    exit; // Termina o script após o redirecionamento
}

/**
 * Verifica se um usuário está logado (verifica 'user_id' na sessão)
 * @return bool
 */
function isLoggedIn(): bool { // Adicionado tipo de retorno bool
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); // Verifica também se não está vazio
}

/**
 * Verifica o nível de acesso do usuário logado
 * @param string|array $required_level Nível ('admin', 'operador') ou array de níveis permitidos
 * @return bool True se o usuário tem o nível de acesso necessário, false caso contrário.
 */
function hasAccess($required_level): bool { // Adicionado tipo de retorno bool
    if (!isLoggedIn()) {
        return false; // Não logado, não tem acesso
    }

    // Obtém o nível do usuário da sessão (com fallback para null se não definido)
    $user_level = $_SESSION['user_level'] ?? null;

    // Se o nível do usuário não está definido na sessão, negar acesso por segurança
    if ($user_level === null) {
        error_log("Aviso: Nível de usuário não definido na sessão para user_id: " . ($_SESSION['user_id'] ?? 'N/A'));
        return false;
    }

    // Nível 'admin' tem acesso irrestrito
    if ($user_level === 'admin') {
        return true;
    }

    // Se o nível requerido for um array, verifica se o nível do usuário está nele
    if (is_array($required_level)) {
        return in_array($user_level, $required_level, true); // Usar comparação estrita (true)
    }

    // Se o nível requerido for uma string, compara diretamente
    return $user_level === $required_level;
}


// --- INCLUSÃO DOS HELPERS GERAIS ---
// Colocamos no final para garantir que todas as constantes (BASE_URL, etc