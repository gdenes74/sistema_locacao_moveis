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

    <!-- jQuery via CDN (Seu header.php já inclui o jQuery 3.6.0) -->

    <!-- Bootstrap Bundle JS (inclui Popper.js) via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery UI JS via CDN -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- Select2 JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>

    <!-- Inputmask JS via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

    <!-- Moment.js via CDN (Necessário para alguns pickers de data/hora) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/pt-br.min.js"></script>

    <!-- Tempusdominus Bootstrap 4 JS via CDN (Se usar o datetimepicker do Bootstrap 4) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- Toastr JS (Para notificações) via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- SweetAlert2 JS (Para alertas) via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>


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
        } else {
            console.warn('Select2 não está carregado.');
        }

        // Configurar máscaras de entrada com Inputmask
        if (typeof $.fn.inputmask !== 'undefined') {
            $('.telefone').inputmask('(99) 9999[9]-9999');
            $('.cpf').inputmask('999.999.999-99');
            $('.cnpj').inputmask('99.999.999/9999-99');
            $('.cep').inputmask('99999-999');
            // A máscara para .date pode ser removida daqui se o datepicker cuidar disso,
            // ou mantida se você tiver campos .date que não são datepickers.
            $('.date').inputmask('dd/mm/yyyy', { alias: 'datetime', inputFormat: 'dd/mm/yyyy', placeholder: '__/__/____' });
            $('.money').inputmask('currency', {
                prefix: 'R$ ', groupSeparator: '.', radixPoint: ',', digits: 2,
                autoGroup: true, rightAlign: false, unmaskAsNumber: true // unmaskAsNumber: true pode ser útil para obter o valor numérico
            });
        } else {
            console.warn('Inputmask não está carregado.');
        }

        // Configurar Datepicker (jQuery UI Datepicker)
        // ***********************************************************************************
        // ESTA SEÇÃO FOI COMENTADA PARA PERMITIR QUE AS PÁGINAS INDIVIDUAIS
        // (COMO create.php e edit.php) CONTROLEM A INICIALIZAÇÃO DO DATEPICKER
        // COM SUAS OPÇÕES ESPECÍFICAS (EX: onSelect para dia da semana).
        // ***********************************************************************************
        /*
        if (typeof $.datepicker !== 'undefined') {
            try {
                // Definições regionais para pt-BR (exemplo)
                $.datepicker.regional['pt-BR'] = {
                    closeText: 'Fechar',
                    prevText: '&#x3C;Anterior',
                    nextText: 'Próximo&#x3E;',
                    currentText: 'Hoje',
                    monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
                    monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun',
                    'Jul','Ago','Set','Out','Nov','Dez'],
                    dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
                    dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    dayNamesMin: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    weekHeader: 'Sm',
                    dateFormat: 'dd/mm/yy',
                    firstDay: 0,
                    isRTL: false,
                    showMonthAfterYear: false,
                    yearSuffix: ''
                };
                $.datepicker.setDefaults($.datepicker.regional['pt-BR']);
            } catch(e) {
                console.warn("Erro ao configurar regional do jQuery UI Datepicker no footer:", e);
            }

            // Inicialização genérica que foi comentada:
            // $('.datepicker').datepicker({
            //     dateFormat: 'dd/mm/yy',
            //     changeMonth: true,
            //     changeYear: true
            //     // Se precisar de showOn: 'button' globalmente, adicione aqui
            //     // e remova das inicializações específicas da página.
            // });
        } else {
            console.warn('jQuery UI Datepicker ($.datepicker) não está carregado.');
        }
        */
        // ***********************************************************************************
        // FIM DA SEÇÃO COMENTADA DO DATEPICKER
        // ***********************************************************************************


        // Configurar Tempus Dominus (se seus inputs usarem `data-toggle="datetimepicker"`)
        if (typeof $.fn.datetimepicker === 'function') {
            // Para campos de data
            $('.datetimepicker-input-date').datetimepicker({
                locale: 'pt-br',
                format: 'L',
                // Adicione seus ícones aqui se estiver usando FontAwesome ou similar
                // icons: { time: 'far fa-clock', date: 'far fa-calendar', ... }
            });
            // Para campos de data e hora
            $('.datetimepicker-input-datetime').datetimepicker({
                locale: 'pt-br',
                format: 'L LTS',
                // Adicione seus ícones aqui
            });
        }


        // Inicializar tooltips do Bootstrap
        if (typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Configurar Toastr
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
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
            window.mostrarMensagem('success', '<?php echo addslashes(str_replace("\n", "\\n", $_SESSION['mensagem_sucesso'])); ?>');
            <?php unset($_SESSION['mensagem_sucesso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_erro'])): ?>
            window.mostrarMensagem('error', '<?php echo addslashes(str_replace("\n", "\\n", $_SESSION['mensagem_erro'])); ?>');
            <?php unset($_SESSION['mensagem_erro']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_aviso'])): // Adicionando para consistência ?>
            window.mostrarMensagem('warning', '<?php echo addslashes(str_replace("\n", "\\n", $_SESSION['mensagem_aviso'])); ?>');
            <?php unset($_SESSION['mensagem_aviso']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['mensagem_info'])): // Adicionando para consistência ?>
            window.mostrarMensagem('info', '<?php echo addslashes(str_replace("\n", "\\n", $_SESSION['mensagem_info'])); ?>');
            <?php unset($_SESSION['mensagem_info']); ?>
        <?php endif; ?>

    });
    </script>

    <!-- Scripts específicos da página (definidos pela view como $extra_js) -->
    <?php
    if (isset($extra_js) && is_array($extra_js)) {
        foreach ($extra_js as $js_file) {
            // Certifique-se de que $js_file é um caminho seguro e validado
            echo '<script src="' . htmlspecialchars($js_file, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
        }
    }
    ?>
    <!-- Script customizado inline (definido pela view como $custom_js) -->
    <?php if (isset($custom_js) && !empty(trim($custom_js))): ?>
    <script>
    //<![CDATA[
    <?php echo $custom_js; // O $custom_js que vem da página será executado aqui ?>
    //]]>
    </script>
    <?php endif; ?>

</body>
</html>