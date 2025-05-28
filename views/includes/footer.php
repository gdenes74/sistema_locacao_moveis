<?php
// views/includes/footer.php
if (!defined('BASE_URL')) {
    if (file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    } else {
        die('Erro Crítico: BASE_URL não está definida no footer.php.');
    }
}
?>
                </div> <!-- Fecha o col-md-9 ou col-md-12 aberto no header.php -->
        </div> <!-- Fecha o .row aberto no header.php -->
    </div> <!-- Fecha o .main-container (ou .container-fluid) aberto no header.php -->

    <footer class="text-center mt-5 mb-3">
        <p>&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Sistema Toalhas'; ?>. Todos os direitos reservados. Versão <?php echo defined('APP_VERSION') ? htmlspecialchars(APP_VERSION) : '1.0.0'; ?></p>
    </footer>

    <!-- SCRIPTS JS ESSENCIAIS via CDN -->

    <!-- jQuery via CDN (Se não estiver já no header, mas é melhor no head ou antes de scripts que o usam) -->
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> --> 
    <!-- O seu header.php já inclui o jQuery 3.6.0, então não precisa de novo -->


    <!-- Bootstrap Bundle JS (inclui Popper.js) via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery UI JS via CDN (Se realmente precisar) -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- Select2 JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
    <!-- Se usar o tema Select2 para Bootstrap 4, não precisa de JS adicional geralmente -->

    <!-- Inputmask JS via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script> <!-- Versão atualizada -->

    <!-- Moment.js via CDN (Necessário para alguns pickers de data/hora) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/pt-br.min.js"></script> <!-- Tradução -->

    <!-- Tempusdominus Bootstrap 4 JS via CDN (Se usar o datetimepicker do Bootstrap 4) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- Toastr JS (Para notificações) via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- SweetAlert2 JS (Para alertas) via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>


    <!-- SCRIPTS DE INICIALIZAÇÃO GLOBAIS (Como os que você tinha) -->
    <script>
    $(function () { // Equivalente a $(document).ready()
        // Inicializar Select2
        if (typeof $.fn.select2 === 'function') {
            $('.select2').select2({
                theme: 'bootstrap4', // Para usar com o tema do Bootstrap 4
                language: 'pt-BR',
                placeholder: 'Selecione uma opção',
                allowClear: true,
                width: '100%' // Garante que o select2 ocupe a largura disponível
            });
        } else {
            console.warn('Select2 não está carregado.');
        }

        // Configurar máscaras de entrada com Inputmask
        if (typeof $.fn.inputmask !== 'undefined') {
            $('.telefone').inputmask('(99) 9999[9]-9999');
            $('.cpf').inputmask('999.999.999-99');
            $('.cnpj').inputmask('99.999.999/9999-99');
            $('.cep').inputmask('99999-999');
            $('.date').inputmask('dd/mm/yyyy', { alias: 'datetime', inputFormat: 'dd/mm/yyyy', placeholder: '__/__/____' });
            $('.money').inputmask('currency', {
                prefix: 'R$ ', groupSeparator: '.', radixPoint: ',', digits: 2,
                autoGroup: true, rightAlign: false, unmaskAsNumber: true
            });
        } else {
            console.warn('Inputmask não está carregado.');
        }

        // Configurar Datepicker (jQuery UI Datepicker)
        // Se você for usar o Tempus Dominus, esta parte pode não ser necessária
        // ou pode ser para campos de data mais simples sem hora.
        if (typeof $.datepicker !== 'undefined') {
            $.datepicker.setDefaults($.datepicker.regional['pt-BR']); // Definido no seu footer original
             try { // Adicionado try-catch para o regional
                $.datepicker.regional['pt-BR'] = { /* ... suas configs regionais ... */ };
                $.datepicker.setDefaults($.datepicker.regional['pt-BR']);
            } catch(e) { console.warn("Erro ao configurar regional do jQuery UI Datepicker", e); }

            $('.datepicker').datepicker({ // Para campos com a classe 'datepicker'
                dateFormat: 'dd/mm/yy',
                changeMonth: true,
                changeYear: true
            });
        }

        // Configurar Tempus Dominus (se seus inputs usarem `data-toggle="datetimepicker"`)
        if (typeof $.fn.datetimepicker === 'function') {
            // Para campos de data
            $('.datetimepicker-input-date').datetimepicker({
                locale: 'pt-br',
                format: 'L', // Formato de data localizado (ex: 01/10/2023)
                icons: { /* ... ícones ... */ }
            });
            // Para campos de data e hora
            $('.datetimepicker-input-datetime').datetimepicker({
                locale: 'pt-br',
                format: 'L LTS', // Formato de data e hora localizado (ex: 01/10/2023 14:30:00)
                icons: { /* ... ícones ... */ }
            });
        }


        // Inicializar tooltips do Bootstrap
        if (typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Configurar Toastr
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                "closeButton": true, "progressBar": true, "positionClass": "toast-top-right",
                "timeOut": "5000", /* ... outras opções ... */
            };
        }

        // Função global para mostrar mensagens (adaptada para Toastr ou alert)
        window.mostrarMensagem = function(tipo, mensagem, titulo = '') {
            if (typeof toastr !== 'undefined') {
                switch(tipo) {
                    case 'success': toastr.success(mensagem, titulo); break;
                    case 'error': toastr.error(mensagem, titulo); break;
                    case 'warning': toastr.warning(mensagem, titulo); break;
                    default: toastr.info(mensagem, titulo); break;
                }
            } else {
                alert((titulo ? titulo + ": " : "") + mensagem); // Fallback
            }
        };
        
        // Mostrar mensagens da sessão (se houver)
        <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
            window.mostrarMensagem('success', '<?php echo addslashes($_SESSION['mensagem_sucesso']); ?>');
            <?php unset($_SESSION['mensagem_sucesso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_erro'])): ?>
            window.mostrarMensagem('error', '<?php echo addslashes($_SESSION['mensagem_erro']); ?>');
            <?php unset($_SESSION['mensagem_erro']); ?>
        <?php endif; ?>
        // Adicione para 'aviso' e 'info' se usar

    });
    </script>

    <!-- Scripts específicos da página (definidos pela view) -->
    <?php
    if (isset($extra_js) && is_array($extra_js)) {
        foreach ($extra_js as $js_file) {
            echo '<script src="' . htmlspecialchars($js_file) . '"></script>' . "\n";
        }
    }
    ?>
    <!-- Script customizado inline (definido pela view) -->
    <?php if (isset($custom_js) && !empty(trim($custom_js))): ?>
    <script>
    //<![CDATA[
    <?php echo $custom_js; ?>
    //]]>
    </script>
    <?php endif; ?>

</body>
</html>