        <!-- Fim do conteúdo específico da página -->

        </div> <!-- Fecha a div class="container main-content-container" aberta no header.php -->

<!-- Rodapé da Página (Opcional) -->
<footer class="text-center mt-4 mb-4">
    <small class="text-muted">Sistema Toalhas &copy; <?php echo date("Y"); ?></small>
</footer>


<!-- =============================================== -->
<!-- SCRIPTS JAVASCRIPT - COLOCADOS AQUI NO FINAL -->
<!-- =============================================== -->

<!-- 1. jQuery (Necessário para Bootstrap 4 JS) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- 2. Popper.js (Necessário para componentes como dropdowns, tooltips e o Modal do Bootstrap 4) -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<!-- Alternativa Popper v2 (se usar BS5 ou precisar de versão mais nova):
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.9.3/umd/popper.min.js"></script>
-->

<!-- 3. Bootstrap JS (v4.6.2 - Deve vir DEPOIS do jQuery e Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

<!-- 4. Outros Scripts JS (Ex: máscara de dinheiro, scripts personalizados) -->
<!-- Certifique-se que jQuery já está carregado antes destes -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>

<!-- Seu script personalizado (onde você colocou a lógica do .custom-file-input e .money) -->
<script>
    $(document).ready(function(){
      // Máscara de dinheiro
      $('.money').maskMoney({
          prefix: 'R$ ',
          allowNegative: false,
          thousands: '.',
          decimal: ',',
          affixesStay: true
      });

      // Script para mostrar nome do arquivo no input file
      $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split("").pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName || 'Escolher arquivo...');
      });

      // Ativar tooltips do Bootstrap (se você usar o atributo 'title' em algum lugar)
      $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<!-- Adicione mais links de scripts aqui se necessário -->
<!-- <script src="<?php // echo BASE_URL; ?>assets/js/main.js"></script> -->


</body>
</html>