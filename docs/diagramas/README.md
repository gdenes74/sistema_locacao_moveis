← [Voltar para a documentação](../README.md)

# 📊 Diagramas

![Blueprint](https://img.shields.io/badge/Blueprint-System%20Architecture-blue)
![Documentation](https://img.shields.io/badge/Documentation-Visual-success)
![Project](https://img.shields.io/badge/Project-Mobel%20Festas-purple)

## Objetivo

Esta pasta concentra os diagramas visuais do Sistema Operacional de Locação para Eventos da Mobel Festas.

Os diagramas complementam a documentação textual e ajudam a compreender:

- Fluxos operacionais
- Regras de negócio
- Estrutura do banco de dados
- Arquitetura do sistema
- Evolução do projeto

---

## Estratégia Visual

A documentação visual foi dividida em diagramas menores e especializados.

Essa abordagem evita diagramas grandes demais, melhora a leitura, facilita impressão e torna a manutenção mais simples.

---

## Catálogo de Diagramas

| Status | Diagrama | Arquivo |
|--------|----------|---------|
| ✅ | Blueprint Geral | [00-blueprint-geral](blueprint/00-blueprint-geral.md) |
| ✅ | Visão Geral do Sistema | [01-visao-geral-sistema](dominios/01-visao-geral-sistema.md) |
| ✅ | Fluxo Comercial | [02-fluxo-comercial](fluxos/02-fluxo-comercial.md) |
| ✅ | Fluxo Operacional | [03-fluxo-operacional](fluxos/03-fluxo-operacional.md) |
| ✅ | Catálogo de Produtos | [04-catalogo-produtos](dominios/04-catalogo-produtos.md) |
| ✅ | Estoque Temporal | [05-estoque-temporal](dominios/05-estoque-temporal.md) |
| ✅ | Produtos Compostos | [06-produtos-compostos](dominios/06-produtos-compostos.md) |
| ✅ | Arquitetura MVC Simplificada | [07-arquitetura-mvc-simplificada](arquitetura/07-arquitetura-mvc-simplificada.md) |
| ✅ | ERD Comercial | [08-erd-comercial](erd/08-erd-comercial.md) |
| ✅ | ERD Catálogo | [09-erd-catalogo](erd/09-erd-catalogo.md) |
| ✅ | ERD Operação | [10-erd-operacao](erd/10-erd-operacao.md) |
| ✅ | ERD Completo Resumido | [11-erd-completo-resumido](erd/11-erd-completo-resumido.md) |
| ✅ | Roadmap Visual | [12-roadmap-visual](roadmap/12-roadmap-visual.md) |

---

## Observação Sobre o Blueprint

Materiais anteriores utilizavam o termo ERP, mas o conceito atual do projeto foi refinado.

A definição correta é:

```text
Sistema Operacional de Locação para Eventos
```

O projeto não tem como objetivo ser um ERP corporativo completo.

---

## Padrão Recomendado

Sempre que possível:

- Utilizar fontes grandes e legíveis.
- Evitar excesso de elementos em um único diagrama.
- Dividir diagramas por domínio.
- Manter versões editáveis em Markdown/Mermaid.
- Exportar PNG, SVG ou PDF quando necessário para apresentações.

---

## Situação Atual

Este conjunto inicial de diagramas foi criado como base para a documentação visual do projeto.

Pontos considerados:

- Remover linguagem de ERP.
- Não apresentar lavanderia/manutenção como módulos implementados.
- Reforçar controle temporal de estoque.
- Reforçar produtos compostos.
- Destacar o fluxo real da operação.

---

← [Voltar para a documentação](../README.md)
