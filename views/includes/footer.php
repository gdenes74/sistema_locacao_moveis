<?php
// Arquivo: views/includes/footer.php

// BASE_URL deve estar definida (config.php)
if (!defined('BASE_URL')) {
    die('Erro Crítico: BASE_URL não está definida no footer.php. Verifique se config/config.php foi incluído corretamente.');
}
?>

    <!-- Control Sidebar (Opcional, para configurações de tema do AdminLTE) -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content vai aqui -->
        <div class="p-3">
            <!--<h5>Configurações de Tema</h5>
            <p>Alguma opção de configuração aqui.</p>-->
        </div>
    </aside>
    <!-- /.control-sidebar -->

    <!-- Main Footer (Rodapé principal visível na página) -->
    <footer class="main-footer">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="<?php echo BASE_URL; ?>"><?php echo defined('APP_NAME') ? APP_NAME : 'Seu Sistema'; ?></a>.</strong>
        Todos os direitos reservados.
        <div class="float-right d-none d-sm-inline-block">
            <b>Versão</b> <?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?>
        </div>
    </footer>

</div>
<!-- ./wrapper (Esta div foi aberta no header.php) -->

<!-- SCRIPTS JS ESSENCIAIS -->

<!-- jQuery -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/jquery/jquery.min.js"></script>

<!-- jQuery UI 1.11.4 -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
  // Resolve conflito entre tooltip do jQuery UI e tooltip do Bootstrap
  $.widget.bridge('uibutton', $.ui.button)
</script>

<!-- Bootstrap 4 JS -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS (Para selects melhorados) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/select2/js/select2.full.min.js"></script>

<!-- InputMask (Para máscaras de entrada) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/inputmask/jquery.inputmask.min.js"></script>

<!-- Moment.js (Necessário para Tempusdominus e Daterangepicker) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/moment/moment.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/plugins/moment/locale/pt-br.js"></script>

<!-- Tempusdominus Bootstrap 4 (Para Date/Time Picker) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>

<!-- Daterange picker -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/daterangepicker/daterangepicker.js"></script>

<!-- overlayScrollbars -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>

<!-- AdminLTE App JS -->
<script src="<?php echo BASE_URL; ?>/assets/dist/js/adminlte.js"></script>

<!-- SweetAlert2 (Para alertas bonitos) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Toastr (Para notificações) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/toastr/toastr.min.js"></script>

<!-- SCRIPTS DE INICIALIZAÇÃO GLOBAIS -->
<script>
$(function () {
    // Inicializar Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        language: 'pt-BR',
        placeholder: 'Selecione uma opção',
        allowClear: true
    });

    // Inicializar Select2 com busca AJAX para clientes
    $('#cliente_id').select2({
        theme: 'bootstrap4',
        language: 'pt-BR',
        placeholder: 'Digite para buscar um cliente...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '<?php echo BASE_URL; ?>/ajax/buscar_clientes.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    page: params.page
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.items,
                    pagination: {
                        more: (params.page * 30) < data.total_count
                    }
                };
            },
            cache: true
        }
    });

    // Configurar máscaras de entrada
    if (typeof $.fn.inputmask !== 'undefined') {
        // Máscara para telefone
        $('.telefone').inputmask('(99) 9999[9]-9999', {
            removeMaskOnSubmit: true
        });
        
        // Máscara para CPF
        $('.cpf').inputmask('999.999.999-99', {
            removeMaskOnSubmit: true
        });
        
        // Máscara para CNPJ
        $('.cnpj').inputmask('99.999.999/9999-99', {
            removeMaskOnSubmit: true
        });
        
        // Máscara para CEP
        $('.cep').inputmask('99999-999', {
            removeMaskOnSubmit: true
        });
        
        // Máscara para valores monetários
        $('.money').inputmask('currency', {
            prefix: 'R$ ',
            rightAlign: false,
            radixPoint: ',',
            groupSeparator: '.',
            digits: 2,
            autoGroup: true,
            removeMaskOnSubmit: true
        });
    }

    // Configurar Datepicker do jQuery UI
    if (typeof $.datepicker !== 'undefined') {
        $.datepicker.regional['pt-BR'] = {
            closeText: 'Fechar',
            prevText: '&#x3C;Anterior',
            nextText: 'Próximo&#x3E;',
            currentText: 'Hoje',
            monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
            monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
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

        $('.datepicker').datepicker({
            dateFormat: 'dd/mm/yy',
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true
        });
    }

    // Configurar Tempusdominus
    $('[data-toggle="datetimepicker"]').datetimepicker({
        locale: 'pt-br',
        format: 'L',
        icons: {
            time: 'far fa-clock',
            date: 'far fa-calendar-alt',
            up: 'fas fa-arrow-up',
            down: 'fas fa-arrow-down',
            previous: 'fas fa-chevron-left',
            next: 'fas fa-chevron-right',
            today: 'far fa-calendar-check',
            clear: 'fas fa-trash',
            close: 'fas fa-times'
        }
    });

    // Inicializar tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Configurar Toastr
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

    // Função global para mostrar mensagens
    window.mostrarMensagem = function(tipo, mensagem) {
        switch(tipo) {
            case 'success':
                toastr.success(mensagem);
                break;
            case 'error':
                toastr.error(mensagem);
                break;
            case 'warning':
                toastr.warning(mensagem);
                break;
            case 'info':
                toastr.info(mensagem);
                break;
        }
    };

    // Função para confirmar exclusão
    window.confirmarExclusao = function(url, mensagem) {
        Swal.fire({
            title: 'Tem certeza?',
            text: mensagem || "Você não poderá reverter esta ação!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
        return false;
    };

});
</script>

<!-- Scripts específicos da página -->
<?php
if (isset($extra_js) && is_array($extra_js)) {
    foreach ($extra_js as $js_file) {
        echo '<script src="' . htmlspecialchars($js_file) . '"></script>' . "\n";
    }
}
?>

<!-- Script customizado inline (se houver) -->
<?php if (isset($custom_js)): ?>
<script>
<?php echo $custom_js; ?>
</script>
<?php endif; ?>

</body>
</html>