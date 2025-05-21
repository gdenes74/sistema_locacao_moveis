<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define o título da página. -->
    <title><?php echo htmlspecialchars($page_title ?? 'Sistema Toalhas'); ?></title>

    <!-- Bootstrap CSS (v4.6.2) - Apenas UM link é necessário -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome CSS (para ícones) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Estilos CSS básicos (mantidos como você tinha) + Estilos personalizados para o dashboard -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 60px; /* Aumentei um pouco para garantir espaço para o rodapé */
        }
        .main-content-container { /* Renomeei a classe para evitar conflito com '.container' do Bootstrap */
            background-color: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 20px;
            min-height: 70vh; /* Garante uma altura mínima */
        }
        .table thead th {
            background-color: #e9ecef; /* Mudei para um cinza mais claro (thead-light) */
            color: #495057;
            border-color: #dee2e6;
        }
        .card-title {
            font-size: 1.1rem; /* Ajuste leve no tamanho do título do card */
        }
        /* Estilos personalizados para os cards do dashboard */
        .bg-light-blue {
            background-color: #e3f2fd; /* Azul claro */
        }
        .bg-light-green {
            background-color: #e8f5e9; /* Verde claro */
        }
        .bg-light-yellow {
            background-color: #fffde7; /* Amarelo claro */
        }
        .card-custom {
            border-radius: 0.75rem; /* Arredondar os cantos */
            padding: 1.5rem; /* Adicionar espaçamento interno */
        }
        /* Adicione mais estilos personalizados aqui, se necessário */
    </style>
</head>
<body>
    <!-- Barra de Navegação (Navbar) - Se você usar, coloque aqui -->
    <?php // include_once __DIR__ . '/navbar.php'; // Exemplo se você separar a navbar ?>
    <!-- Ou cole o código HTML da navbar aqui -->

    <!-- Abre o container principal onde o conteúdo da página será carregado -->
    <!-- Este container será fechado no footer.php -->
    <div class="container main-content-container">
        <!-- O conteúdo específico da página (ex: views/produtos/index.php) começa aqui -->
        <!-- Ele será inserido entre a inclusão do header.php e do footer.php nos seus arquivos de view -->