# Evolução do Sistema MOBEL

Este registro acompanha o código versionado. Dados de clientes, estoque, fotografias
enviadas e backups pertencem à instalação local e não são publicados neste repositório.

## 30/10/2026 — Marco de cores, composição e produção

O trabalho desde o último commit de 13/10 concentrou-se em transformar regras
operacionais da MOBEL em fluxos editáveis. Esta etapa reúne mudanças ainda em
validação na instalação local; não representa uma versão de produção.

### Catálogo e motor de cores

- Famílias, vínculos de produtos, cores e aliases passam a ter telas de configuração.
  Produtos com capa física e móveis com forração sob demanda seguem regras distintas.
- Mestres fotográficos, máscaras, amostras e prévias têm revisão humana antes da
  publicação. A simulação não substitui automaticamente a foto do produto.
- Uma cor escolhida para forração sem capa cria uma foto contextual na linha comercial;
  o estoque continua pertencendo à estrutura física compartilhada.
- Produtos com capa cadastrada podem resolver uma cor informada na observação para
  uma variante existente. Cor nova permanece identificada para planejamento.

### Orçamentos, pedidos e disponibilidade

- Criação, edição e visualização exibem cores e prévias contextuais quando há mestre
  e amostra compatíveis, preservando a descrição livre do item.
- O resumo de disponibilidade mostra componentes limitantes e faltas, sem tratar
  consumo de tecido sob demanda como estoque fictício.
- A interface de datas e os avisos foram ajustados para não encobrir diálogos.

### Produção e corte

- Pedidos confirmados podem gerar pendências de produção para componentes/capas
  insuficientes, cores novas e serviços de forração; há também cadastro manual.
- Cada ficha possui quantidade, prazo, status e histórico. Marcar como produzida
  **não movimenta estoque**; a quantidade física conferida é registrada no produto.
- A ficha e a calculadora consultam as medidas técnicas cadastradas. O plano
  apresenta cortes, aproveitamento, sobras e metragem por quantidade. O resumo
  consolidado agrupa metragem por material e cor, sem compensar automaticamente
  retalhos entre produtos diferentes.

### Evidência e limites deste marco

- Sintaxe conferida em 52 arquivos PHP e 12 JavaScripts alterados ou novos.
- O teste de recoloração preservou madeira, transparência e sombras.
- A revisão visual e operacional continua necessária por família e no navegador.
  Serviços com apenas consumo linear cadastrado exibem referência de compra,
  não um molde detalhado.
- A instalação local contém cadastros e imagens que o Git não distribui. Uma nova
  instalação precisa de migrações, referências e fotos aprovadas próprias.

## 13/10/2026 — Conjuntos comerciais

- `c13865c`: grupos fixos e filtros encadeados no cadastro de produtos.
- `c673535`: itens fixos em pedidos e orçamentos.
- `eaa658c`: regressão dos conjuntos e contexto operacional.

## Até 31/07/2026 — Base versionada anterior

Os commits anteriores registram cadastro, composição, estoque temporal, fluxo
comercial e documentação inicial. Veja `git log` para a sequência completa;
este changelog começa nos marcos operacionais de outubro, sem reescrever datas
ou resultados dos commits antigos.
