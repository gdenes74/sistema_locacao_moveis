# Forração visual sem capa física — 2026-10-23

Esta etapa cobre Mesa Banco, Coletivo Alto/Baixo 2 m e Aparador U 1,70/2,00. Cada produto forrado é `COMPOSTO`: reserva apenas a estrutura física compartilhada e inclui um `SERVICO` de forração sem estoque. Não há componente `Capa` por cor.

## Cadastro e uso

- Uma família `FORRACAO_PRODUCAO` aponta para cada produto forrado `Cor A Definir`.
- Um mestre aprovado precisa pertencer exatamente a esse produto; a máscara marca só o tecido.
- No orçamento e no pedido (criação e edição), cada linha desse produto oferece as cores canônicas com amostra aprovada, sem duplicatas. Ao escolher uma cor, a observação guarda `Cor: Nome` e o navegador monta uma foto ilustrativa com mestre + máscara + amostra. O `produto_id`, preço e composição não mudam.
- Na visualização/impressão, a observação recompõe a foto ilustrativa após o carregamento da página. Se a cor não foi escolhida, é desconhecida, ou ainda falta mestre aprovado, permanece a foto de serviço. O texto livre continua na observação.
- A imagem é contextual à linha; não publicar a simulação sobre `produtos.foto_path` do produto genérico. O estoque temporal continua calculado pela composição existente. A escolha visual não compra tecido nem reserva capa.

## Limites e evolução posterior

- A foto contextual é montada no navegador. Imprimir/exportar somente após a prévia terminar de carregar; processos sem navegador não recebem essa imagem ainda.
- As famílias com capas físicas (pufe pequeno/Thai, Cama Thai, Namoradeira, Sofá com/sem braços) mantêm fluxo separado. A etapa posterior acrescentou sugestão de variante cadastrada pela observação e confirmação da troca na linha comercial. A cor textual, por si só, não reserva capa; a disponibilidade precisa ser recalculada para o produto efetivamente escolhido.
- Para novas mesas forradas, cadastrar estrutura, serviço, produto composto, família `FORRACAO_PRODUCAO` com `produto_generico_id` e mestre aprovado. A seleção visual então aparece sem acrescentar capa fictícia.

O estado integrado em 30/10/2026 está descrito em
[Motor de cores, produtos compostos e produção](../arquitetura/motor-cores-e-producao.md).

## Migração

Backup anterior: `Sistema_MOBEL/03_BACKUPS/BANCO/sistema_toalhas_antes_forrcao_20261023_1807.sql` (41 tabelas, SHA-256 `110adb1303d17d0769b7ac5db7105546a0e11366acba1ffa69cb854a26b10c1a`).

`tests/registrar_familias_forracao.php --apply` é idempotente e `--rollback` reverte as famílias novas enquanto não possuírem mestres/variantes/amostras; quando já houver vínculos, restaurar o backup com revisão manual. O mestre do banco #14 não foi alterado.
