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
    <!-- Se você estiver usando o jQuery local do AdminLTE, o caminho seria algo como: -->
    <!-- <script src="<?= BASE_URL ?>/assets/plugins/jquery/jquery.min.js"></script> -->

    <!-- 2. Bootstrap Bundle JS (inclui Popper.js - depende do jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- 3. AdminLTE App JS (depende do jQuery e Bootstrap) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>
    <!-- Se local: <script src="<?= BASE_URL ?>/assets/dist/js/adminlte.min.js"></script> -->


    <!-- 4. OUTROS PLUGINS (todos dependem do jQuery) -->

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script> -->

    <!-- Inputmask JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.pt-BR.min.js"></script>

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- jQuery UI JS (COMENTADO - REMOVER SE NÃO USAR PARA OUTROS COMPONENTES) -->
    <!-- <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> -->

    <!-- Moment.js e Tempusdominus (COMENTADOS - REMOVER SE NÃO USAR PARA OUTROS CAMPOS) -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/pt-br.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script> -->


    <!-- SCRIPTS DE INICIALIZAÇÃO GLOBAIS (AGORA COM JQUERY GARANTIDO) -->
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
                // Para modais, foca no campo de busca do select2
                if ($('.modal.show').length > 0) {
                    setTimeout(function() { // Pequeno delay para garantir que o campo exista
                        document.querySelector('.select2-container--open .select2-search__field').focus();
                    }, 100);
                }
            });
        } else {
            console.warn('Select2 não está carregado ou $.fn.select2 não é uma função.');
        }

        // Configurar máscaras de entrada com Inputmask
        if (typeof $.fn.inputmask !== 'undefined') {
            $('.telefone').inputmask('(99) 9999[9]-9999');
            $('.cpf').inputmask('999.999.999-99');
            $('.cnpj').inputmask('99.999.999/9999-99');
            $('.cep').inputmask('99999-999');
            $('.money').inputmask('currency', {
                prefix: 'R$ ', groupSeparator: '.', radixPoint: ',', digits: 2,
                autoGroup: true, rightAlign: false,
                clearMaskOnLostFocus: false
            });
        } else {
            console.warn('Inputmask não está carregado.');
        }

        // Inicializar tooltips do Bootstrap
        if (typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Configurar Toastr
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                "closeButton": true, "debug": false, "newestOnTop": true, "progressBar": true,
                "positionClass": "toast-top-right", "preventDuplicates": false, "onclick": null,
                "showDuration": "300", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000",
                "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut"
            };
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
            window.mostrarMensagem('success', '<?php echo addslashes(str_replace(["\r", "\n"], "\\n", $_SESSION['success_message'])); ?>');
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            window.mostrarMensagem('error', '<?php echo addslashes(str_replace(["\r", "\n"], "\\n", $_SESSION['error_message'])); ?>');
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['warning_message'])): ?>
            window.mostrarMensagem('warning', '<?php echo addslashes(str_replace(["\r", "\n"], "\\n", $_SESSION['warning_message'])); ?>');
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_aviso'])): ?>
            window.mostrarMensagem('warning', '<?php echo addslashes(str_replace(["\r", "\n"], "\\n", $_SESSION['mensagem_aviso'])); ?>');
            <?php unset($_SESSION['mensagem_aviso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_info'])): ?>
            window.mostrarMensagem('info', '<?php echo addslashes(str_replace(["\r", "\n"], "\\n", $_SESSION['mensagem_info'])); ?>');
            <?php unset($_SESSION['mensagem_info']); ?>
        <?php endif; ?>
    });
    </script>

    <?php
    if (isset($extra_js) && is_array($extra_js)) {
        foreach ($extra_js as $js_file) {
            echo '<script src="' . htmlspecialchars($js_file, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
        }
    }
    ?>
    <?php if (isset($custom_js) && !empty(trim($custom_js))): ?>
    <script>
    //<![CDATA[
    <?php echo $custom_js; ?>
    //]]>
    </script>
    <?php endif; ?>

</body>
</html>