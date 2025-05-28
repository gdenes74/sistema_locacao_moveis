<?php
// PARTE PHP: processamento AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {

    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/database.php'; // ajuste o caminho conforme necessário
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
        if ($termo === '') {
            echo json_encode([]);
            exit;
        }
        $sql = "SELECT id, nome FROM clientes WHERE nome LIKE ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $termo . '%']);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($clientes);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no banco de dados']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Busca Inline Clientes COM BANCO</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        #input-busca-cliente { width: 300px; padding: 10px; }
        #lista-clientes { margin-top: 20px; }
        #lista-clientes li { padding: 5px; }
    </style>
</head>
<body>
    <h3>Buscar Clientes (INLINE COM BANCO)</h3>
    <input id="input-busca-cliente" type="text" placeholder="Digite o nome do cliente" autocomplete="off" />
    <ul id="lista-clientes"></ul>

    <script>
    $(document).ready(function() {
        $('#input-busca-cliente').on('input', function() {
            var termo = $(this).val();
            if(termo.length < 1) {
                $('#lista-clientes').empty();
                return;
            }
            $.ajax({
                url: '', // O próprio arquivo
                data: { ajax: 1, termo: termo },
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#lista-clientes').empty();
                    if(data.error){
                        $('#lista-clientes').append('<li>'+data.error+'</li>');
                        return;
                    }
                    if(data.length === 0){
                        $('#lista-clientes').append('<li>Nenhum cliente encontrado.</li>');
                    } else {
                        data.forEach(function(cliente) {
                            $('#lista-clientes').append(
                                $('<li>').text(cliente.nome + ' (ID: ' + cliente.id + ')')
                            );
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $('#lista-clientes').empty().append(
                        $('<li>').text('Erro na busca.')
                    );
                    console.error('Erro na busca de clientes:', error, xhr.responseText);
                }
            });
        });
    });
    </script>
</body>
</html>