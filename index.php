<?php
// Redireciona para index.php dentro da página geral
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/geral') !== false) {
    header('Location: /didicontas/index.php');
    exit;
}
