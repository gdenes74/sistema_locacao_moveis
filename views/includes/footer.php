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
            </div> <!-- Fecha o .content-wrapper aberto no header.php -->

    <footer class="main-footer text-center">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#"><?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Sistema Toalhas'; ?></a>.</strong>
        Todos os direitos reservados.
        <div class="float-right d-none d-sm-inline-block">
            <b>Versão</b> <?php echo defined('APP_VERSION') ? htmlspecialchars(APP_VERSION) : '1.0.0'; ?>
        </div>
    </footer>

    <aside class="control-sidebar control-sidebar-dark"></aside>
</div> <!-- Fecha o .wrapper aberto no header.php -->

    <!-- SCRIPTS JS ESSENCIAIS -->

    <!-- 1. jQuery (SEMPRE PRIMEIRO) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- 2. jQuery UI (ESSENCIAL PARA O 'SORTABLE'. DEVE VIR DEPOIS DO JQUERY) -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- 3. Bootstrap Bundle JS (inclui Popper.js - depende do jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- 4. AdminLTE App JS (depende do jQuery e Bootstrap) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>

    <!-- 5. OUTROS PLUGINS (todos dependem do jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.pt-BR.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- DEFINIÇÃO DE CONSTANTES GLOBAIS PARA JAVASCRIPT -->
    <script>
        const BASE_URL = "<?= rtrim(BASE_URL, '/') ?>";
        const DEFAULT_PLACEHOLDER_IMAGE = BASE_URL + "/assets/img/avatar_placeholder.png";
    </script>

    <!-- SCRIPTS DE INICIALIZAÇÃO GLOBAIS -->
    <script>
    $(function () { // Equivalente a $(document).ready()
        // Inicializar Select2
        if (typeof $.fn.select2 === 'function') {
            $('.select2').select2({
                theme: 'bootstrap4',
                language: 'pt-BR',
                placeholder: 'Selecione uma opção',
                allowClear: true,
                width: '100%'
            });
            $(document).on('select2:open', () => {
                if ($('.modal.show').length > 0) {
                    setTimeout(function() {
                        let searchField = document.querySelector('.select2-container--open .select2-search__field');
                        if (searchField) { searchField.focus(); }
                    }, 100);
                }
            });
        } else { console.warn('Select2 não está carregado.'); }

        // Configurar máscaras de entrada com Inputmask
        if (typeof $.fn.inputmask !== 'undefined') {
            $('.telefone').inputmask('(99) 9999[9]-9999');
            $('.cpf').inputmask('999.999.999-99');
            $('.cnpj').inputmask('99.999.999/9999-99');
            $('.cep').inputmask('99999-999');
            $('.money, .money-input, .money-display').inputmask('currency', {
                prefix: 'R$ ', groupSeparator: '.', radixPoint: ',', digits: 2,
                autoGroup: true, rightAlign: false,
                clearMaskOnLostFocus: false,
                numericInput: false, // <<-- VÍRGULA CORRIGIDA AQUI
                nullable: true
            });
        } else { console.warn('Inputmask não está carregado.'); }
        
        $(document).on('focus', '.money, .money-input', function() {
            $(this).select();
        });

        // Inicializar tooltips do Bootstrap
        if (typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Configurar Toastr
        if (typeof toastr !== 'undefined') {
            toastr.options = {"closeButton": true, "progressBar": true, "positionClass": "toast-top-right"};
        }

        window.mostrarMensagem = function(tipo, mensagem, titulo = '') {
            if (typeof toastr !== 'undefined') {
                switch(tipo) {
                    case 'success': toastr.success(mensagem, titulo); break;
                    case 'error': toastr.error(mensagem, titulo); break;
                    case 'warning': toastr.warning(mensagem, titulo); break;
                    default: toastr.info(mensagem, titulo); break;
                }
            } else {
                alert((titulo ? titulo + ": " : "") + mensagem);
            }
        };

        <?php if (isset($_SESSION['success_message'])): ?>
            window.mostrarMensagem('success', <?php echo json_encode($_SESSION['success_message']); ?>);
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            window.mostrarMensagem('error', <?php echo json_encode($_SESSION['error_message']); ?>);
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    });
    </script>

    <?php
    if (isset($extra_js) && is_array($extra_js)) {
        foreach ($extra_js as $js_file) {
            echo '<script src="' . htmlspecialchars($js_file, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
        }
    }
    if (isset($custom_js) && !empty(trim($custom_js))): ?>
    <script>
    //<![CDATA[
    <?php echo $custom_js; ?>
    //]]>
    </script>
    <?php endif; ?>

</body>
</html>