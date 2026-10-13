# Sistema MOBEL — instruções para agentes

## Contexto compartilhado

Antes de trabalhar neste repositório, leia:

1. `C:\Users\larak\Documents\Codex\Empresa Análises\MOBEL_Venda_2026\00_CONTEXTO\RESUMO_GERAL_PROJETO.md`;
2. `C:\Users\larak\Documents\Codex\Empresa Análises\MOBEL_Venda_2026\00_CONTEXTO\DECISOES_CONFIRMADAS.md`;
3. `C:\Users\larak\Documents\Codex\Empresa Análises\MOBEL_Venda_2026\00_CONTEXTO\PENDENCIAS_ATUAIS.md`;
4. `C:\Users\larak\Documents\Codex\Empresa Análises\MOBEL_Venda_2026\00_CONTEXTO\REGISTRO_DE_CONTINUIDADE.md`;
5. `README.md` e os documentos relevantes em `docs/` deste repositório.

## Estado e arquitetura

- Aplicação PHP/MariaDB em MVC simplificado.
- Execução local por XAMPP.
- Imagens de produtos em `assets/uploads/produtos`, fora do banco.
- Repositório remoto: `gdenes74/sistema_locacao_moveis`.
- Instalação atual é ambiente de desenvolvimento.
- Homologação e produção ainda não estão formalmente criadas.

## Segurança operacional

- Sempre verificar `git status` antes de editar.
- Preservar alterações locais da usuária; não descartar nem sobrescrever arquivos sem confirmação.
- Não executar alterações destrutivas no banco.
- Antes de migração ou SQL de escrita, exigir backup identificável e script reversível quando possível.
- Desenvolvimento, homologação e produção devem usar bancos separados.
- Não incluir credenciais, dumps com dados pessoais ou imagens sensíveis no Git.
- Não enviar commits ou alterações ao GitHub sem solicitação explícita da usuária.

## Regras do domínio

- COMPONENTE representa estoque e patrimônio físico.
- COMPOSTO representa produto comercial e não deve duplicar patrimônio.
- SERVICO não possui estoque físico.
- Componentes compartilhados devem limitar a disponibilidade temporal de todos os produtos que os utilizam.
- `preco_custo` é reposição atual; `preco_venda` é valor usado; `preco_locacao` é preço comercial.

## Qualidade

- Antes de grandes refatorações, caracterizar o comportamento atual com testes.
- Priorizar correções pequenas, reversíveis e verificáveis.
- Testar cadastro, composição, disponibilidade temporal, orçamento e pedido após mudanças relevantes.
- Melhorar separação de PHP, HTML, CSS e JavaScript gradualmente; não reescrever o sistema inteiro.
- Atualizar documentação e o registro central de continuidade ao concluir etapa relevante.

