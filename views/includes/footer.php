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
    <!-- MELHORIA: Descomentado para habilitar tradução completa do Select2 para pt-BR -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

    <!-- Inputmask JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.pt-BR.min.js"></script>

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- jQuery UI JS (REMOVER SE NÃO USAR PARA OUTROS COMPONENTES - ex: sortable, draggable) -->
    <!-- <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> -->

    <!-- Moment.js e Tempusdominus (Necessários se usar o Date/Time Picker do Tempusdominus) -->
    <!-- Se você estiver usando APENAS o Bootstrap Datepicker (como parece ser o caso), pode remover Moment.js e Tempusdominus -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/pt-br.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script> -->


    <!-- DEFINIÇÃO DE CONSTANTES GLOBAIS PARA JAVASCRIPT -->
    <script>
        const BASE_URL = "<?= rtrim(BASE_URL, '/') ?>"; // Garante que não haja barra no final
        // Você pode mudar o placeholder se tiver um específico para produtos,
        // por exemplo, se tiver uma imagem genérica 'sem_imagem.png' em assets/img/
        const DEFAULT_PLACEHOLDER_IMAGE = BASE_URL + "/assets/img/avatar_placeholder.png"; // Usando avatar_placeholder.png como exemplo
    </script>

    <!-- SCRIPTS DE INICIALIZAÇÃO GLOBAIS (AGORA COM JQUERY E CONSTANTES GLOBAIS GARANTIDOS) -->
    <script>
    $(function () { // Equivalente a $(document).ready()
        // Inicializar Select2
        if (typeof $.fn.select2 === 'function') {
            $('.select2').select2({
                theme: 'bootstrap4',
                language: 'pt-BR', // Requer o arquivo i18n/pt-BR.js carregado
                placeholder: 'Selecione uma opção',
                allowClear: true,
                width: '100%'
            });
            $(document).on('select2:open', () => {
                // Para modais, foca no campo de busca do select2
                if ($('.modal.show').length > 0) {
                    setTimeout(function() { // Pequeno delay para garantir que o campo exista
                        let searchField = document.querySelector('.select2-container--open .select2-search__field');
                        if (searchField) {
                            searchField.focus();
                        }
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
            $('.money, .money-input, .money-display').inputmask('currency', {
                prefix: 'R$ ', groupSeparator: '.', radixPoint: ',', digits: 2,
                autoGroup: true, rightAlign: false,
                clearMaskOnLostFocus: false,
                numericInput: false // Garante que a digitação seja da esquerda para a direita
                 nullable: true
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
                "showDuration": "800", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000",
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

        // MELHORIA: Usando json_encode para injetar mensagens de sessão de forma mais segura.
        <?php if (isset($_SESSION['success_message'])): ?>
            window.mostrarMensagem('success', <?php echo json_encode($_SESSION['success_message']); ?>);
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            window.mostrarMensagem('error', <?php echo json_encode($_SESSION['error_message']); ?>);
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['warning_message'])): ?>
            window.mostrarMensagem('warning', <?php echo json_encode($_SESSION['warning_message']); ?>);
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_aviso'])): // Mantendo este nome de sessão se for usado em outro lugar ?>
            window.mostrarMensagem('warning', <?php echo json_encode($_SESSION['mensagem_aviso']); ?>);
            <?php unset($_SESSION['mensagem_aviso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_info'])): // Mantendo este nome de sessão se for usado em outro lugar ?>
            window.mostrarMensagem('info', <?php echo json_encode($_SESSION['mensagem_info']); ?>);
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

<!-- ================================================================== -->
<!-- O SCRIPT CUSTOMIZADO DO PRELOADER FOI REMOVIDO DAQUI.             -->
<!-- O AdminLTE AGORA CUIDARÁ DO TEMPO DO PRELOADER ATRAVÉS DO        -->
<!-- ATRIBUTO data-lte-preloader-delay NA TAG <body> NO header.php.   -->
<!-- ================================================================== -->

</body>
</html>