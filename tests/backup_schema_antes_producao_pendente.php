<?php
/** Backup de esquema antes da migração aditiva de Produção Pendente. */
require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();
$destinoDir = __DIR__ . '/backups';
if (!is_dir($destinoDir) && !mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
    throw new RuntimeException('Não foi possível criar a pasta de backup.');
}
$arquivo = $destinoDir . '/sistema_toalhas_antes_producao_pendente_' . date('Ymd_His') . '_schema.sql';
$linhas = ["-- Backup de esquema antes de criar Produção Pendente", "-- Gerado em " . date('c'), ''];
foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabela) {
    $nomeSeguro = str_replace('`', '``', $tabela);
    $row = $db->query("SHOW CREATE TABLE `{$nomeSeguro}`")->fetch(PDO::FETCH_ASSOC);
    $create = $row['Create Table'] ?? null;
    if ($create) {
        $linhas[] = "DROP TABLE IF EXISTS `{$nomeSeguro}`;";
        $linhas[] = $create . ';';
        $linhas[] = '';
    }
}
file_put_contents($arquivo, implode(PHP_EOL, $linhas));
echo $arquivo . PHP_EOL;
