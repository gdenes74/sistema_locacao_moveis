<?php
/**
 * Helpers gerais do sistema
 * Este arquivo contém funções auxiliares para uso em todo o sistema.
 */

// Certifique-se de sempre verificar se algum helper ainda não foi definido
if (!function_exists('build_url')) {
    /**
     * Constrói uma URL completa com base na BASE_URL definida em config.php
     * @param string $path Caminho relativo (ex: 'views/produtos/index.php')
     * @return string URL completa (ex: 'http://localhost/sistema-toalhas/views/produtos/index.php')
     */
    function build_url(string $path): string {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('formatarDataHora')) {
    /**
     * Formata uma data em um formato legível para humanos
     * @param string|null $dataSql Data e hora no formato do banco de dados (YYYY-MM-DD HH:MM:SS)
     * @param string $formato Formato desejado (padrão: 'd/m/Y H:i:s')
     * @return string Data formatada ou '-' se inválida
     */
    function formatarDataHora(?string $dataSql, string $formato = 'd/m/Y H:i:s'): string {
        if (empty($dataSql)) return '-';
        try {
            $date = new DateTime($dataSql);
            return $date->format($formato);
        } catch (Exception $e) {
            return '-'; // Retorna '-' em caso de erro na formatação
        }
    }
}

if (!function_exists('truncate_text')) {
    /**
     * Trunca um texto para o tamanho máximo especificado, adicionando "..." no final, se necessário
     * @param string $texto Texto a ser truncado
     * @param int $limite Tamanho máximo em caracteres
     * @return string Texto truncado
     */
    function truncate_text(string $texto, int $limite = 100): string {
        if (strlen($texto) > $limite) {
            return substr($texto, 0, $limite) . '...';
        }
        return $texto;
    }
}

if (!function_exists('numerosParaPorcentagem')) {
    /**
     * Converte um número em porcentagem com duas casas (ex: 0.25 para "25.00%")
     * @param float $num Número a ser formatado
     * @return string Porcentagem formatada
     */
    function numerosParaPorcentagem(float $num): string {
        return number_format($num * 100, 2) . '%';
    }
}