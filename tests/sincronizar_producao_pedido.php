<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/Database.php';
require_once __DIR__.'/../models/ProducaoPendente.php';
$pedido=(int)($argv[1]??0);
if($pedido<=0){fwrite(STDERR,"Informe o ID do pedido.\n");exit(2);}
$db=(new Database())->getConnection();
$criados=(new ProducaoPendente($db))->sincronizarCoresNovasDoPedido($pedido,null);
echo "Pedido {$pedido}: {$criados} ficha(s) nova(s); fichas existentes foram atualizadas sem duplicação.\n";
