<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/Database.php';
require_once __DIR__.'/../models/ProducaoPendente.php';
$db=(new Database())->getConnection();
$pedido=$db->query("SELECT pe.id FROM pedidos pe
    JOIN itens_pedido ip ON ip.pedido_id=pe.id
    JOIN produtos p ON p.id=ip.produto_id
    JOIN familias_variantes f ON f.ativo=1 AND f.modo_impacto_estoque='FORRACAO_PRODUCAO'
      AND (f.produto_generico_id=p.id OR EXISTS (SELECT 1 FROM produto_variantes pv
          WHERE pv.familia_variante_id=f.id AND pv.produto_id=p.id AND pv.ativo=1 AND pv.eh_generica=1))
    WHERE LOWER(pe.situacao_pedido)='confirmado' ORDER BY pe.id DESC LIMIT 1")->fetchColumn();
if(!$pedido){echo "SEM_PEDIDO_CONFIRMADO_COM_FORRACAO\n";exit(2);}
$antes=$db->query('SELECT * FROM producao_pendencias ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$backup=__DIR__.'/backups/producao_pendencias_antes_teste_forracao_'.date('Ymd_His').'.json';
file_put_contents($backup,json_encode($antes,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$db->beginTransaction();
try{
    $motor=new ProducaoPendente($db);$motor->sincronizarCoresNovasDoPedido((int)$pedido,null);
    $sql="SELECT pp.id,pp.produto_id,pp.componente_id,pp.quantidade_faltante,pp.observacoes
        FROM producao_pendencias pp JOIN produtos s ON s.id=pp.componente_id AND s.tipo_produto='SERVICO'
        JOIN familias_variantes f ON f.produto_generico_id=pp.produto_id AND f.ativo=1 AND f.modo_impacto_estoque='FORRACAO_PRODUCAO'
        WHERE pp.pedido_id=".(int)$pedido." AND pp.status IN ('PENDENTE','PROGRAMADA')";
    $primeira=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if(!$primeira)throw new RuntimeException('A sincronização não criou a ficha do serviço de forração.');
    $motor->sincronizarCoresNovasDoPedido((int)$pedido,null);$segunda=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if(count($primeira)!==count($segunda))throw new RuntimeException('A segunda sincronização duplicou a ficha.');
    foreach($segunda as $ficha){
        if((float)$ficha['quantidade_faltante']<=0)throw new RuntimeException('Ficha sem quantidade.');
        if(stripos((string)$ficha['observacoes'],'movimenta estoque')===false)throw new RuntimeException('Ficha não distingue serviço de estoque físico: '.json_encode($ficha,JSON_UNESCAPED_UNICODE));
    }
    $db->rollBack();
    echo 'OK pedido='.(int)$pedido.' fichas='.count($segunda).' backup='.$backup."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
