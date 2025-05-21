<?php

/**
 * Constrói uma URL com base nos parâmetros GET atuais, mesclando com novos parâmetros.
 * Útil para filtros e ordenação, mantendo o estado da página.
 *
 * @param array $new_params Novos parâmetros para adicionar ou sobrescrever na URL.
 * @return string A URL completa com a query string.
 */
function build_url($new_params = []) {
    // Pega a URL base da página atual (sem a query string)
    $base_url = strtok($_SERVER["REQUEST_URI"], '?');
    
    // Mescla os parâmetros GET atuais com os novos parâmetros
    // Os novos parâmetros sobrescrevem os existentes se tiverem a mesma chave
    $merged_params = array_merge($_GET, $new_params);
    
    // Constrói a nova query string
    $query_string = http_build_query($merged_params);
    
    return $base_url . '?' . $query_string;
}

/**
 * Formata uma data (geralmente do formato YYYY-MM-DD) para o padrão brasileiro (DD/MM/YYYY).
 *
 * @param string|null $data_mysql A data no formato do banco de dados ou null.
 * @return string A data formatada ou '-' se a entrada for inválida/vazia.
 */
function formatar_data_br($data_mysql) {
    if (empty($data_mysql) || $data_mysql === '0000-00-00' || $data_mysql === '0000-00-00 00:00:00') {
        return '-';
    }
    try {
        // Tenta criar um objeto DateTime para validar e formatar
        $date_obj = new DateTime($data_mysql);
        return $date_obj->format('d/m/Y');
    } catch (Exception $e) {
        // Se a data for inválida, retorna '-'
        error_log("Erro ao formatar data BR: " . $data_mysql . " - " . $e->getMessage());
        return '-';
    }
}

/**
 * Formata um valor numérico como moeda no padrão brasileiro (R$ 1.234,56).
 *
 * @param float|int|string|null $valor O valor a ser formatado.
 * @return string O valor formatado como moeda ou '-' se a entrada não for numérica.
 */
function formatar_moeda_br($valor) {
    if (!is_numeric($valor)) {
        // Se o valor for null, ou uma string não numérica (exceto se for um número como string)
        // pode retornar '-' ou R$ 0,00 dependendo da preferência.
        // Para ser mais seguro, vamos verificar se é estritamente numérico.
        if (is_string($valor) && !is_numeric(str_replace(',', '.', $valor))) { // Tenta converter vírgula para ponto para validar
            return '-';
        } elseif (!is_string($valor) && $valor === null) {
            return '-';
        }
    }
    // Garante que o valor seja float
    $valor_float = floatval(str_replace(',', '.', (string)$valor));
    return 'R$ ' . number_format($valor_float, 2, ',', '.');
}

?>