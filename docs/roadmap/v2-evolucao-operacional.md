# V2 – Evolução Operacional do Sistema Mobel Festas

## V2.0.1 – Bloqueio de kits/conjuntos com preço zerado

### Objetivo

Evitar que kits, conjuntos ou montagens comerciais sejam salvos em orçamento ou pedido com preço unitário zerado.

Essa regra foi criada porque alguns kits e montagens usam itens internos apenas para composição operacional, produção e controle de estoque, mas o preço comercial deve ser informado manualmente na linha principal do conjunto.

### Regra implementada

Quando uma linha do tipo `CONJUNTO` estiver com preço unitário zerado, o sistema bloqueia o salvamento e exibe alerta para o usuário informar o preço.

A validação foi aplicada em:

* `views/orcamentos/create.php`
* `views/orcamentos/edit.php`
* `views/pedidos/create.php`
* `views/pedidos/edit.php`

### Regras preservadas

Esta alteração não modificou:

* lógica de estoque temporal;
* filhos de conjunto (`ITEM_CONJUNTO`);
* título de seção (`CABECALHO_SECAO`);
* ordenação/arrastar linhas;
* impressão cliente;
* impressão produção;
* lógica de observação/cor;
* lógica de produtos compostos.

### Resultado

Kits/conjuntos comerciais não podem mais ser salvos com preço zerado por esquecimento do atendente, preservando a lógica operacional já existente do sistema.
