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
            <p>Alguma opção de configuração aqui./p>
-->
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

<!-- jQuery UI 1.11.4 (Se precisar de interações como draggable, sortable, ou o datepicker do jQuery UI) -->
<!-- Note que o AdminLTE 3 usa mais o Tempusdominus para date/time, mas jQuery UI ainda pode ser útil -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
  // Resolve conflito entre tooltip do jQuery UI e tooltip do Bootstrap (se ambos estiverem ativos)
  $.widget.bridge('uibutton', $.ui.button)
</script>

<!-- Bootstrap 4 JS -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS (Para selects melhorados) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/select2/js/select2.full.min.js"></script>

<!-- Moment.js (Necessário para Tempusdominus e Daterangepicker) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/moment/moment.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/plugins/moment/locale/pt-br.js"></script> <!-- Tradução para PT-BR -->


<!-- Tempusdominus Bootstrap 4 (Para Date/Time Picker) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>

<!-- Daterange picker (Para seleção de intervalo de datas) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/daterangepicker/daterangepicker.js"></script>

<!-- overlayScrollbars (Para barras de rolagem customizadas) -->
<script src="<?php echo BASE_URL; ?>/assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>

<!-- AdminLTE App JS (Lógica principal do AdminLTE) -->
<script src="<?php echo BASE_URL; ?>/assets/dist/js/adminlte.js"></script>

<!-- ChartJS (Para gráficos, se for usar) -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/plugins/chart.js/Chart.min.js"></script> -->

<!-- Sparkline (Para mini gráficos inline, se for usar) -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/plugins/sparklines/sparkline.js"></script> -->

<!-- JQVMap (Para mapas vetoriais, se for usar) -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/plugins/jqvmap/jquery.vmap.min.js"></script> -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/plugins/jqvmap/maps/jquery.vmap.usa.js"></script> --> <!-- Exemplo mapa EUA -->

<!-- Summernote (Editor de Texto Rico, se for usar) -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/plugins/summernote/summernote-bs4.min.js"></script> -->
<!-- <script src="<?php echo BASE_URL; ?>/assets/plugins/summernote/lang/summernote-pt-BR.js"></script> --> <!-- Tradução PT-BR -->


<!-- SCRIPTS DE INICIALIZAÇÃO GLOBAIS (OPCIONAL, mas recomendado) -->
<script>
$(function () {
    // Inicializar Select2 em todos os elementos com a classe .select2
    $('.select2').select2({
        theme: 'bootstrap4' // Usa o tema do Bootstrap 4 para o Select2
    });

    // Inicializar o Datepicker do jQuery UI (se estiver usando ele ao invés do Tempusdominus)
    // Tradução para Datepicker do jQuery UI (certifique-se que o jquery-ui.min.js está carregado)
    if (typeof $.datepicker !== 'undefined') {
        $.datepicker.regional['pt-BR'] = {
            closeText: 'Fechar', prevText: '&#x3C;Anterior', nextText: 'Próximo&#x3E;', currentText: 'Hoje',
            monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
            monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
            dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
            dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
            dayNamesMin: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
            weekHeader: 'Sm', dateFormat: 'dd/mm/yy', firstDay: 0, isRTL: false, showMonthAfterYear: false, yearSuffix: ''
        };
        $.datepicker.setDefaults($.datepicker.regional['pt-BR']);

        $('.datepicker').datepicker({
            dateFormat: 'dd/mm/yy',
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true
        });
    }


    // Inicializar Tempusdominus Bootstrap 4 Datepicker (para campos com a classe .datetimepicker-input)
    // Este é o date/time picker mais integrado com AdminLTE 3
    // Exemplo de uso no HTML:
    // <div class="input-group date" id="meuDatepicker" data-target-input="nearest">
    //     <input type="text" class="form-control datetimepicker-input" data-target="#meuDatepicker" name="data_exemplo"/>
    //     <div class="input-group-append" data-target="#meuDatepicker" data-toggle="datetimepicker">
    //         <div class="input-group-text"><i class="fa fa-calendar"></i></div>
    //     </div>
    // </div>
    $('[data-toggle="datetimepicker"]').datetimepicker({
        locale: 'pt-br', // Usa o locale do Moment.js carregado
        format: 'L', // Formato de data Localizado (ex: 13/05/2025)
        // Para incluir hora: format: 'L LT' (ex: 13/05/2025 05:20)
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

    // Inicializar tooltips do Bootstrap (para dicas ao passar o mouse)
    $('[data-toggle="tooltip"]').tooltip();

});
</script>

<!-- Scripts específicos da página (se houver) podem ser adicionados aqui pela view que inclui este footer,
     ou você pode ter uma seção de scripts no seu arquivo de view principal (ex: orcamentos/index.php)
     logo após incluir este footer.php.
     Exemplo:
     <?php
     if (isset($extra_js) && is_array($extra_js)) {
         foreach ($extra_js as $js_file) {
             echo '<script src="' . htmlspecialchars($js_file) . '"></script>';
         }
     }
     ?>
-->

</body>
</html>