# Versionamento e promoção de versões

Este repositório guarda o código do sistema. O ambiente XAMPP atual é de
**desenvolvimento**; um commit ou push não cria uma implantação de homologação
ou produção e não transfere o banco e as imagens locais.

## Papéis das branches

| Branch | Papel |
| --- | --- |
| `main` | Base estável; só recebe etapa aceita e reproduzível |
| `develop` | Integração do trabalho validado no desenvolvimento |
| `homolog` | Candidata para testes em ambiente e banco separados, quando existirem |
| `codex/*` ou `feature/*` | Trabalho temático e revisável antes da integração |

A antiga `v2-produto-sugerido-conjunto` registrou os conjuntos em outubro.
O trabalho posterior de catálogo visual e produção continua a V2 em commits
temáticos; não é necessário reescrever os commits históricos.

## Critérios de promoção

1. Inventariar o diff, os arquivos novos e dependências de banco.
2. Excluir credenciais, clientes, dumps, backups e fotografias privadas.
3. Executar checagens de sintaxe e testes proporcionais à mudança.
4. Conferir no navegador os fluxos afetados, inclusive pedido, orçamento,
   disponibilidade e impressão quando aplicável.
5. Para homologação, restaurar backup em banco separado e verificar migrações,
   mestre, máscara e amostras. Registrar evidências e rollback.
6. Somente após aceite da candidata, promover a `main` e criar tag semântica.

**Bloqueio conhecido para implantação pública:** a autenticação atual ainda
usa credenciais de teste definidas em `views/usuarios/login.php`. Ela deve ser
substituída e homologada antes de expor a aplicação fora do ambiente local.

Este fluxo separa histórico do Git, banco de dados e publicação das fotos.
Uma versão do código pode estar integrada em `develop` sem estar pronta para
uso em produção.
