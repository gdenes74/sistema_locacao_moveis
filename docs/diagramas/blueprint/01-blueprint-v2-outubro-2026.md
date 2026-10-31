← [Voltar aos diagramas](../README.md)

# Blueprint V2 — catálogo visual e produção

**Retrato do desenvolvimento local em 30/10/2026.** Esta visão amplia o
[blueprint original](00-blueprint-geral.md), que permanece como referência do
ciclo de locação. Não indica que exista implantação de homologação ou produção.

## 1. Catálogo: do patrimônio à apresentação comercial

```mermaid
flowchart LR
  F["SIMPLES / COMPONENTE<br/>quantidade e patrimônio físicos"]
  S["SERVICO<br/>forração ou confecção"]
  C["COMPOSTO<br/>opção comercial"]
  F -->|composição e estoque compartilhado| C
  S -->|instrução de produção| C
  C --> V["Família e variante<br/>regras editáveis"]
  V --> M["Foto mestre e máscara"]
  A["Amostra de cor<br/>revisada"] --> P["Prévia ilustrativa"]
  M --> P
  P --> R{"Revisão humana"}
  R -->|aprovada| PUB["Publicação da foto"]
  R -->|pendente| REV["Ajustar referência ou mestre"]
  REV --> P
```

Uma estrutura, capa ou assento físicos são contados uma vez. A quantidade de
cores ou de produtos montados não multiplica o estoque nem o patrimônio.
Para forração sob demanda, a foto é contextual ao item; não cria capa.

## 2. Orçamento, pedido e disponibilidade

```mermaid
flowchart LR
  CAT["Produto composto e família"] --> ORC["Orçamento"]
  ORC --> PED["Pedido"]
  COR["Cor na seleção ou observação"] --> ORC
  COR --> PED
  EST["Componentes reais"] --> DISP["Consulta por período"]
  ORC --> DISP
  PED --> DISP
  DISP --> RES["Resumo: estrutura, capa,<br/>assento/colchão e faltas"]
  PED -->|confirmado| SIN["Sincronizar pendências"]
  SIN --> FIC["Ficha de produção"]
```

Uma cor de capa existente pode apontar para a variante física correspondente.
Uma cor nova permanece identificada para produção. Na forração sem capa,
apenas a estrutura limita o estoque; tecido e serviço entram no planejamento.
O estado de disponibilidade depende do período e da composição cadastrada.

## 3. Produção, tecido e estoque

```mermaid
flowchart LR
  FIC["Ficha vinculada ao pedido<br/>ou cadastrada manualmente"]
  FIC --> TEC["Medidas técnicas do<br/>produto/componente/serviço"]
  TEC --> CAL["Plano de corte por quantidade<br/>e largura útil do tecido"]
  CAL --> IMP["Ficha / prévia A4"]
  FIC --> HIS["Status e histórico"]
  HIS -->|produção concluída| CON["Conferência da quantidade real"]
  CON -->|lançamento manual| EST["Estoque físico atualizado"]
```

A ficha não aumenta estoque por mudar de status. O cálculo de tecido usa as
peças descritas na ficha técnica; se existir apenas consumo linear, a tela
apresenta uma referência de compra, sem inventar o formato do molde. O plano
consolidado soma fichas por material e cor, mas não reaproveita automaticamente
retalhos entre produtos diferentes.

## Estado da V2

| Área | Situação neste retrato |
| --- | --- |
| Cadastro de famílias, vínculos, amostras e mestres | Implementado na instalação local, com revisão humana |
| Cor contextual em orçamento e pedido | Implementada; continuar homologação por família |
| Consulta temporal de componentes | Integrada; conferir produtos novos e composições |
| Pendências, ficha, impressão e plano de corte | Implementados; moldes dependem das medidas cadastradas |
| Migração completa para uma instalação limpa | Pendente: parte do esquema e dos dados visuais ainda é local |
| Ambientes separados de desenvolvimento, homologação e produção | Ainda não formalizados |

## Próxima etapa de cadastro

As mesas devem seguir a mesma separação entre tampo/pés físicos, produto
comercial composto e eventual serviço de forração. Cada apresentação precisa
de compatibilidade física e preço comercial próprios; o estoque e o
patrimônio continuam nos componentes. A aprovação de um mestre e a inclusão
de medidas de corte são etapas distintas da criação do produto.

Detalhes de código e limites estão em
[Motor de cores, produtos compostos e produção](../../arquitetura/motor-cores-e-producao.md).
