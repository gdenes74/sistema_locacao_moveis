<?php
// Exibir mensagens de sucesso
if (!empty($_SESSION['message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php
        echo htmlspecialchars($_SESSION['message']);
        unset($_SESSION['message']); // Remove mensagem após exibi-la
        ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Exibir mensagens de erro -->
<?php if (!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php
        echo htmlspecialchars($_SESSION['error_message']);
        unset($_SESSION['error_message']); // Remove mensagem após exibi-la
        ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>
