-- Executar após backup, somente se a coluna ainda não existir.
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS icone VARCHAR(32) NULL;
