<?php
/* ═══════════════════════════════════════════════════════════
   DIDICONTAS — PAINEL ADMIN
   Arquivo único: autenticação + CRUD produtos + categorias
   Coloque em: /public/admin/index.php
   ═══════════════════════════════════════════════════════════ */

session_start();
require_once '../../config/conexao.php';

/* ─── Configuração de acesso ──────────────────────────────── */
define('ADMIN_USER', 'didi');
define('ADMIN_PASS', '$2y$10$TROQUE_ESTE_HASH'); // gerado abaixo

/* Para gerar o hash da sua senha, rode UMA VEZ no terminal:
   php -r "echo password_hash('SUA_SENHA_AQUI', PASSWORD_DEFAULT);"
   Cole o resultado acima e apague esta instrução.
   Senha padrão de DEMONSTRAÇÃO: didi2025
*/
define('ADMIN_PASS_DEMO', 'didi2025'); // fallback para demo (remova em produção)

/* ─── Helper de autenticação ──────────────────────────────── */
function isLogged(): bool
{
    return isset($_SESSION['dc_admin']) && $_SESSION['dc_admin'] === true;
}
function requireLogin(): void
{
    if (!isLogged()) {
        header('Location: ?page=login');
        exit;
    }
}
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* ─── Roteamento ──────────────────────────────────────────── */
$page   = $_GET['page']   ?? 'produtos';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ══════════════════════════════════════════════════════════
   AÇÕES POST (processamento antes do HTML)
══════════════════════════════════════════════════════════ */

/* LOGIN */
if ($action === 'login') {
    $user  = trim($_POST['usuario'] ?? '');
    $pass  = $_POST['senha'] ?? '';
    $ok    = ($user === ADMIN_USER) &&
        (password_verify($pass, ADMIN_PASS) || $pass === ADMIN_PASS_DEMO);
    if ($ok) {
        $_SESSION['dc_admin'] = true;
        header('Location: ?page=produtos');
        exit;
    }
    flash('Usuário ou senha inválidos.', 'erro');
    header('Location: ?page=login');
    exit;
}

/* LOGOUT */
if ($action === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

/* ── CRUD PRODUTOS ── */
if ($action === 'salvar_produto') {
    requireLogin();
    $id       = intval($_POST['id'] ?? 0);
    $titulo   = trim($_POST['titulo']   ?? '');
    $desc     = trim($_POST['descricao'] ?? '');
    $preco    = floatval(str_replace(',', '.', $_POST['preco'] ?? '0'));
    $ciclo    = in_array($_POST['ciclo'] ?? '', ['mensal', 'anual']) ? $_POST['ciclo'] : 'mensal';
    $cat_id   = intval($_POST['categoria_id'] ?? 0);
    $status   = $_POST['status']   === 'ativo' ? 'ativo' : 'inativo';
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $img      = trim($_POST['imagem_url'] ?? '');

    if ($titulo === '') {
        flash('O título é obrigatório.', 'erro');
        header('Location: ?page=produtos');
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE produtos SET titulo=?, descricao=?, preco=?, ciclo=?, categoria_id=?, status=?, destaque=?, imagem_url=? WHERE id=?");
        $stmt->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img ?: null, $id]);
        flash('Produto atualizado com sucesso.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO produtos (titulo, descricao, preco, ciclo, categoria_id, status, destaque, imagem_url) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img ?: null]);
        flash('Produto criado com sucesso.');
    }
    header('Location: ?page=produtos');
    exit;
}

if ($action === 'excluir_produto') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM produtos WHERE id=?")->execute([$id]);
        flash('Produto removido.');
    }
    header('Location: ?page=produtos');
    exit;
}

if ($action === 'toggle_status') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE produtos SET status = IF(status='ativo','inativo','ativo') WHERE id=?")->execute([$id]);
        flash('Status atualizado.');
    }
    header('Location: ?page=produtos');
    exit;
}

/* ── CRUD CATEGORIAS ── */
if ($action === 'salvar_categoria') {
    requireLogin();
    $id    = intval($_POST['id'] ?? 0);
    $nome  = trim($_POST['nome']  ?? '');
    $icone = trim($_POST['icone'] ?? 'fa-solid fa-tag');

    if ($nome === '') {
        flash('O nome é obrigatório.', 'erro');
        header('Location: ?page=categorias');
        exit;
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE categorias SET nome=?, icone=? WHERE id=?")->execute([$nome, $icone, $id]);
        flash('Categoria atualizada.');
    } else {
        $pdo->prepare("INSERT INTO categorias (nome, icone) VALUES (?,?)")->execute([$nome, $icone]);
        flash('Categoria criada.');
    }
    header('Location: ?page=categorias');
    exit;
}

if ($action === 'excluir_categoria') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $count = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id=?");
        $count->execute([$id]);
        if ($count->fetchColumn() > 0) {
            flash('Esta categoria possui produtos vinculados. Remova-os primeiro.', 'erro');
        } else {
            $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$id]);
            flash('Categoria removida.');
        }
    }
    header('Location: ?page=categorias');
    exit;
}

/* ══════════════════════════════════════════════════════════
   DADOS PARA EXIBIÇÃO
══════════════════════════════════════════════════════════ */
if (isLogged()) {
    $categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stats = [
        'total'    => $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn(),
        'ativos'   => $pdo->query("SELECT COUNT(*) FROM produtos WHERE status='ativo'")->fetchColumn(),
        'inativos' => $pdo->query("SELECT COUNT(*) FROM produtos WHERE status='inativo'")->fetchColumn(),
        'cats'     => $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn(),
    ];

    if ($page === 'produtos') {
        $busca    = trim($_GET['busca'] ?? '');
        $filtCat  = intval($_GET['cat'] ?? 0);
        $filtStatus = $_GET['status'] ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($busca) {
            $where[] = 'p.titulo LIKE ?';
            $params[] = "%$busca%";
        }
        if ($filtCat) {
            $where[] = 'p.categoria_id = ?';
            $params[] = $filtCat;
        }
        if ($filtStatus) {
            $where[] = 'p.status = ?';
            $params[] = $filtStatus;
        }

        $sql = "SELECT p.*, c.nome AS cat_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id=c.id WHERE " . implode(' AND ', $where) . " ORDER BY p.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Produto em edição?
        $editProd = null;
        if (isset($_GET['editar'])) {
            $s = $pdo->prepare("SELECT * FROM produtos WHERE id=?");
            $s->execute([intval($_GET['editar'])]);
            $editProd = $s->fetch(PDO::FETCH_ASSOC);
        }
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin — Didi Contas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ─── Tokens ─────────────────────────────────────────────── */
        :root {
            --bg: #0B0F1A;
            --bg2: #111827;
            --surface: #161D2E;
            --surface2: #1E2840;
            --surface3: #242E47;
            --border: rgba(255, 255, 255, 0.07);
            --border2: rgba(255, 255, 255, 0.13);
            --accent: #3B82F6;
            --accent-h: #2563EB;
            --green: #10B981;
            --red: #EF4444;
            --yellow: #F59E0B;
            --text: #F1F5F9;
            --text2: #94A3B8;
            --text3: #475569;
            --r: 12px;
            --r-sm: 8px;
            --sidebar: 240px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            font-size: 15px;
            line-height: 1.6;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--surface3);
            border-radius: 4px;
        }

        /* ─── Layout ─────────────────────────────────────────────── */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar);
            flex-shrink: 0;
            background: var(--bg2);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
            overflow-y: auto;
            transition: transform 0.3s;
        }

        .content {
            margin-left: var(--sidebar);
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
        }

        @media(max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: none;
                box-shadow: 0 0 0 100vw rgba(0, 0, 0, 0.6);
            }

            .content {
                margin-left: 0;
                padding: 1rem;
            }

            .mob-header {
                display: flex !important;
            }
        }

        /* ─── Sidebar internals ──────────────────────────────────── */
        .sidebar-logo {
            padding: 1.5rem 1.25rem 1rem;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.3px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .sidebar-logo span {
            color: var(--yellow);
        }

        .sidebar-logo small {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text3);
            margin-top: 2px;
        }

        .nav-section {
            padding: 1rem 0.75rem 0.4rem;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--text3);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1.25rem;
            margin: 1px 0;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text2);
            text-decoration: none;
            border-radius: 0;
            transition: background 0.15s, color 0.15s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }

        .nav-item:hover {
            background: var(--surface);
            color: var(--text);
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent);
            font-weight: 600;
        }

        .nav-item i {
            width: 16px;
            text-align: center;
            font-size: 0.85rem;
            opacity: 0.8;
            flex-shrink: 0;
        }

        .nav-item.active i {
            opacity: 1;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 0.75rem 0;
        }

        /* ─── Mobile header ──────────────────────────────────────── */
        .mob-header {
            display: none;
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(11, 15, 26, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 0 1rem;
            height: 56px;
            align-items: center;
            justify-content: space-between;
        }

        .mob-header .logo {
            font-size: 1rem;
            font-weight: 800;
        }

        .mob-header .logo span {
            color: var(--yellow);
        }

        .mob-menu-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--r-sm);
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text2);
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ─── Page header ────────────────────────────────────────── */
        .page-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        .page-hd h1 {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .page-hd p {
            font-size: 0.8rem;
            color: var(--text3);
            margin-top: 2px;
        }

        /* ─── Stat cards ─────────────────────────────────────────── */
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        @media(min-width:640px) {
            .stats {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1rem 1.1rem;
        }

        .stat-card .lbl {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text3);
            margin-bottom: 0.4rem;
        }

        .stat-card .val {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-card .val.green {
            color: var(--green);
        }

        .stat-card .val.yellow {
            color: var(--yellow);
        }

        .stat-card .val.red {
            color: var(--red);
        }

        /* ─── Filter bar ─────────────────────────────────────────── */
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 0.9rem 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .filter-bar input,
        .filter-bar select {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 0.85rem;
            padding: 0.55rem 0.85rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: var(--accent);
        }

        .filter-bar input::placeholder {
            color: var(--text3);
        }

        .filter-bar select option {
            background: var(--bg2);
        }

        .filter-bar input {
            min-width: 200px;
            flex: 1;
        }

        .search-icon-wrap {
            position: relative;
            flex: 1;
            min-width: 160px;
            max-width: 280px;
        }

        .search-icon-wrap i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 0.8rem;
            pointer-events: none;
        }

        .search-icon-wrap input {
            width: 100%;
            padding-left: 2.1rem;
        }

        /* ─── Table ──────────────────────────────────────────────── */
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            overflow: hidden;
        }

        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        thead th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            background: var(--surface);
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.12s;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        tbody td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--text2);
            vertical-align: middle;
        }

        tbody td:first-child {
            color: var(--text);
            font-weight: 600;
        }

        .empty-row td {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text3);
        }

        .empty-row td i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 0.5rem;
            opacity: 0.4;
        }

        /* ─── Badges ─────────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.2rem 0.6rem;
            border-radius: 100px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .badge-green {
            background: rgba(16, 185, 129, 0.1);
            color: #34D399;
        }

        .badge-red {
            background: rgba(239, 68, 68, 0.1);
            color: #F87171;
        }

        .badge-yellow {
            background: rgba(245, 158, 11, 0.12);
            color: #FCD34D;
        }

        .badge-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #93C5FD;
        }

        .badge-gray {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text3);
        }

        /* ─── Buttons ────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            border-radius: var(--r-sm);
            border: none;
            transition: all 0.18s;
            text-decoration: none;
            white-space: nowrap;
            font-size: 0.82rem;
            padding: 0.55rem 1.1rem;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--accent-h);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--green);
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-ghost {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text2);
        }

        .btn-ghost:hover {
            border-color: var(--border2);
            color: var(--text);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #F87171;
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.18);
        }

        .btn-warn {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            color: #FCD34D;
        }

        .btn-warn:hover {
            background: rgba(245, 158, 11, 0.18);
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
        }

        .btn-icon {
            padding: 0.4rem 0.5rem;
        }

        /* ─── Forms ──────────────────────────────────────────────── */
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media(max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text2);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: var(--r-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 0.9rem;
            padding: 0.7rem 0.9rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--text3);
        }

        .form-group select option {
            background: var(--bg2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 96px;
            line-height: 1.55;
        }

        .form-group .hint {
            font-size: 0.72rem;
            color: var(--text3);
            line-height: 1.5;
        }

        /* Toggle */
        .toggle-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .toggle-wrap .tgl-lbl {
            font-size: 0.875rem;
            color: var(--text2);
        }

        .toggle {
            position: relative;
            width: 46px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--surface3);
            border: 1px solid var(--border2);
            border-radius: 100px;
            transition: background 0.2s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            background: var(--text2);
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: transform 0.2s, background 0.2s;
        }

        .toggle input:checked+.toggle-slider {
            background: var(--accent);
            border-color: var(--accent);
        }

        .toggle input:checked+.toggle-slider::before {
            transform: translateX(20px);
            background: #fff;
        }

        /* ─── Modal ──────────────────────────────────────────────── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 200;
            background: rgba(7, 10, 18, 0.88);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s;
        }

        .modal-backdrop.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 16px;
            padding: 1.75rem;
            width: 100%;
            max-width: 440px;
            transform: scale(0.96) translateY(10px);
            transition: transform 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-backdrop.open .modal {
            transform: none;
        }

        .modal h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .modal p {
            font-size: 0.875rem;
            color: var(--text2);
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.6rem;
            justify-content: flex-end;
        }

        /* ─── Flash ──────────────────────────────────────────────── */
        .flash {
            padding: 0.8rem 1.1rem;
            border-radius: var(--r-sm);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .flash.ok {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #34D399;
        }

        .flash.erro {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #F87171;
        }

        /* ─── Login ──────────────────────────────────────────────── */
        .login-screen {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--bg);
        }

        .login-box {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 18px;
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 380px;
        }

        .login-logo {
            font-size: 1.6rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.2rem;
        }

        .login-logo span {
            color: var(--yellow);
        }

        .login-box hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.5rem 0;
        }

        .login-btn {
            width: 100%;
            padding: 0.85rem;
            background: var(--accent);
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            border-radius: var(--r-sm);
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 0.5rem;
        }

        .login-btn:hover {
            background: var(--accent-h);
        }

        /* ─── Misc ───────────────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.5rem 0;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            color: var(--text3);
            text-decoration: none;
            margin-bottom: 1.25rem;
            transition: color 0.15s;
        }

        .back-link:hover {
            color: var(--text);
        }

        .price-preview {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -0.5px;
        }
    </style>
</head>

<body>

    <?php if (!isLogged()): /* ══════ TELA DE LOGIN ══════ */ ?>
        <div class="login-screen">
            <div class="login-box">
                <p class="login-logo">didi<span>contas</span></p>
                <p style="text-align:center;font-size:0.78rem;color:var(--text3);margin-top:4px;margin-bottom:0;">Painel Administrativo</p>
                <hr>

                <?php if ($flash): ?>
                    <div class="flash <?= $flash['type'] ?>">
                        <i class="fa-solid <?= $flash['type'] === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <?= htmlspecialchars($flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="?page=login">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group" style="margin-bottom:0.85rem;">
                        <label>Usuário</label>
                        <input type="text" name="usuario" placeholder="admin" autocomplete="username" required autofocus>
                    </div>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <label>Senha</label>
                        <input type="password" name="senha" placeholder="••••••••" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="login-btn">
                        <i class="fa-solid fa-right-to-bracket" style="margin-right:6px;"></i> Entrar no painel
                    </button>
                </form>

                <p style="text-align:center;margin-top:1.5rem;font-size:0.73rem;color:var(--text3);">
                    <a href="../" style="color:var(--accent);text-decoration:none;">← Voltar para a vitrine</a>
                </p>
            </div>
        </div>

    <?php else: /* ══════ APP PRINCIPAL ══════ */ ?>

        <!-- Mobile header -->
        <div class="mob-header">
            <span class="logo">didi<span>contas</span></span>
            <button class="mob-menu-btn" onclick="toggleSidebar()" aria-label="Menu">
                <i class="fa-solid fa-bars" id="menuIcon"></i>
            </button>
        </div>

        <div class="layout">

            <!-- SIDEBAR -->
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo">
                    didi<span>contas</span>
                    <small>Painel Admin</small>
                </div>

                <p class="nav-section">Catálogo</p>
                <a href="?page=produtos" class="nav-item <?= $page === 'produtos'   ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i> Produtos</a>
                <a href="?page=categorias" class="nav-item <?= $page === 'categorias' ? 'active' : '' ?>"><i class="fa-solid fa-layer-group"></i> Categorias</a>

                <p class="nav-section" style="margin-top:0.5rem;">Atalhos</p>
                <a href="?page=produtos&novo=1" class="nav-item"><i class="fa-solid fa-plus"></i> Novo produto</a>
                <a href="../" target="_blank" class="nav-item"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver vitrine</a>

                <div class="sidebar-footer">
                    <a href="?action=logout" class="nav-item" style="color:var(--text3);">
                        <i class="fa-solid fa-right-from-bracket"></i> Sair
                    </a>
                </div>
            </aside>

            <!-- CONTEÚDO -->
            <main class="content">

                <?php if ($flash): ?>
                    <div class="flash <?= $flash['type'] ?>">
                        <i class="fa-solid <?= $flash['type'] === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <?= htmlspecialchars($flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <!-- ═══ PÁGINA: PRODUTOS ════════════════════════════ -->
                <?php if ($page === 'produtos'): ?>

                    <?php $isNovo   = isset($_GET['novo']);
                    $isEditar = isset($editProd) && $editProd; ?>

                    <?php if ($isNovo || $isEditar): /* ── Formulário ── */ ?>

                        <a href="?page=produtos" class="back-link"><i class="fa-solid fa-arrow-left"></i> Voltar para produtos</a>

                        <div class="page-hd">
                            <div>
                                <h1><?= $isEditar ? 'Editar Produto' : 'Novo Produto' ?></h1>
                                <p><?= $isEditar ? 'Altere os dados e salve.' : 'Preencha os campos e publique na vitrine.' ?></p>
                            </div>
                        </div>

                        <form method="POST" action="?page=produtos" id="formProduto">
                            <input type="hidden" name="action" value="salvar_produto">
                            <input type="hidden" name="id" value="<?= $isEditar ? intval($editProd['id']) : 0 ?>">

                            <div class="form-card">
                                <div class="form-grid">

                                    <div class="form-group full">
                                        <label>Título do produto *</label>
                                        <input type="text" name="titulo" placeholder="ex: ChatGPT Plus" required
                                            value="<?= htmlspecialchars($editProd['titulo'] ?? '') ?>">
                                    </div>

                                    <div class="form-group full">
                                        <label>Descrição / Especificações</label>
                                        <textarea name="descricao" placeholder="Descreva o que está incluso no plano..."><?= htmlspecialchars($editProd['descricao'] ?? '') ?></textarea>
                                        <span class="hint">Esta descrição aparece no modal quando o cliente clica em "Saber mais".</span>
                                    </div>

                                    <div class="form-group">
                                        <label>Preço (R$) *</label>
                                        <input type="number" name="preco" step="0.01" min="0" required
                                            placeholder="29.90" id="precoInput"
                                            value="<?= $isEditar ? number_format($editProd['preco'], 2, '.', '') : '' ?>"
                                            oninput="atualizarPreview()">
                                        <span class="hint">Valor cobrado no ciclo escolhido.</span>
                                    </div>

                                    <div class="form-group" style="align-items:flex-start;">
                                        <label>Preview do preço</label>
                                        <p class="price-preview" id="precoPreview">
                                            <?= $isEditar ? 'R$ ' . number_format($editProd['preco'], 2, ',', '.') : 'R$ 0,00' ?>
                                        </p>
                                    </div>

                                    <div class="form-group">
                                        <label>Ciclo</label>
                                        <select name="ciclo">
                                            <option value="mensal" <?= ($editProd['ciclo'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                            <option value="anual" <?= ($editProd['ciclo'] ?? '') === 'anual'  ? 'selected' : '' ?>>Anual</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Categoria</label>
                                        <select name="categoria_id">
                                            <option value="">Sem categoria</option>
                                            <?php foreach ($categorias as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"
                                                    <?= ($editProd['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group full">
                                        <label>URL da imagem (opcional)</label>
                                        <input type="url" name="imagem_url"
                                            placeholder="https://exemplo.com/imagem.png"
                                            value="<?= htmlspecialchars($editProd['imagem_url'] ?? '') ?>">
                                        <span class="hint">Exibida no modal de detalhes. Deixe vazio para usar o placeholder padrão.</span>
                                    </div>

                                    <div class="form-group">
                                        <label>Status</label>
                                        <div class="toggle-wrap" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:0.7rem 0.9rem;margin-top:0;">
                                            <span class="tgl-lbl">Visível na vitrine</span>
                                            <label class="toggle">
                                                <input type="checkbox" name="status" value="ativo"
                                                    <?= ($editProd['status'] ?? 'ativo') === 'ativo' ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Destaque</label>
                                        <div class="toggle-wrap" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:0.7rem 0.9rem;margin-top:0;">
                                            <span class="tgl-lbl">Marcar como destaque</span>
                                            <label class="toggle">
                                                <input type="checkbox" name="destaque" value="1"
                                                    <?= !empty($editProd['destaque']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                </div><!-- /form-grid -->

                                <hr class="divider">

                                <div style="display:flex;gap:0.75rem;justify-content:flex-end;flex-wrap:wrap;">
                                    <a href="?page=produtos" class="btn btn-ghost">Cancelar</a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                        <?= $isEditar ? 'Salvar alterações' : 'Publicar produto' ?>
                                    </button>
                                </div>
                            </div>
                        </form>

                    <?php else: /* ── Lista de produtos ── */ ?>

                        <div class="page-hd">
                            <div>
                                <h1>Produtos</h1>
                                <p><?= $stats['total'] ?> cadastrados · <?= $stats['ativos'] ?> ativos</p>
                            </div>
                            <a href="?page=produtos&novo=1" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i> Novo produto
                            </a>
                        </div>

                        <!-- Stats -->
                        <div class="stats">
                            <div class="stat-card">
                                <p class="lbl">Total</p>
                                <p class="val"><?= $stats['total'] ?></p>
                            </div>
                            <div class="stat-card">
                                <p class="lbl">Ativos</p>
                                <p class="val green"><?= $stats['ativos'] ?></p>
                            </div>
                            <div class="stat-card">
                                <p class="lbl">Inativos</p>
                                <p class="val red"><?= $stats['inativos'] ?></p>
                            </div>
                            <div class="stat-card">
                                <p class="lbl">Categorias</p>
                                <p class="val yellow"><?= $stats['cats'] ?></p>
                            </div>
                        </div>

                        <!-- Filtros -->
                        <form method="GET" action="">
                            <input type="hidden" name="page" value="produtos">
                            <div class="filter-bar">
                                <div class="search-icon-wrap">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="busca" placeholder="Buscar por título..." value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                                </div>
                                <select name="cat" style="min-width:150px;">
                                    <option value="">Todas as categorias</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($filtCat ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="status" style="min-width:130px;">
                                    <option value="">Qualquer status</option>
                                    <option value="ativo" <?= ($filtStatus ?? '') === 'ativo'  ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inativo" <?= ($filtStatus ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-filter"></i> Filtrar
                                </button>
                                <?php if (!empty($_GET['busca']) || !empty($_GET['cat']) || !empty($_GET['status'])): ?>
                                    <a href="?page=produtos" class="btn btn-ghost btn-sm">Limpar</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Tabela -->
                        <div class="table-wrap">
                            <div class="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Produto</th>
                                            <th>Categoria</th>
                                            <th>Preço</th>
                                            <th>Ciclo</th>
                                            <th>Status</th>
                                            <th>Destaque</th>
                                            <th style="text-align:right;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($produtos)): ?>
                                            <tr class="empty-row">
                                                <td colspan="8">
                                                    <i class="fa-solid fa-box-open"></i>
                                                    Nenhum produto encontrado.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($produtos as $p): ?>
                                                <tr>
                                                    <td style="color:var(--text3);font-weight:400;font-size:0.78rem;"><?= $p['id'] ?></td>
                                                    <td>
                                                        <span style="font-weight:700;color:var(--text);"><?= htmlspecialchars($p['titulo']) ?></span>
                                                        <?php if ($p['imagem_url']): ?>
                                                            <span class="badge badge-blue" style="margin-left:4px;vertical-align:middle;font-size:0.6rem;">
                                                                <i class="fa-solid fa-image"></i> IMG
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($p['cat_nome']): ?>
                                                            <span class="badge badge-blue"><?= htmlspecialchars($p['cat_nome']) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-gray">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="font-weight:700;color:var(--text);">
                                                        R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                                    </td>
                                                    <td><span class="badge badge-gray"><?= $p['ciclo'] ?></span></td>
                                                    <td>
                                                        <span class="badge <?= $p['status'] === 'ativo' ? 'badge-green' : 'badge-red' ?>">
                                                            <i class="fa-solid fa-circle" style="font-size:0.45rem;"></i>
                                                            <?= $p['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($p['destaque']): ?>
                                                            <span class="badge badge-yellow"><i class="fa-solid fa-star" style="font-size:0.65rem;"></i> Sim</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-gray">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:nowrap;">
                                                            <a href="?page=produtos&editar=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Editar">
                                                                <i class="fa-solid fa-pen-to-square"></i>
                                                            </a>
                                                            <a href="?action=toggle_status&id=<?= $p['id'] ?>&page=produtos"
                                                                class="btn btn-warn btn-sm btn-icon"
                                                                title="<?= $p['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?>">
                                                                <i class="fa-solid <?= $p['status'] === 'ativo' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                                            </a>
                                                            <button onclick="confirmarExclusao(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['titulo'])) ?>')"
                                                                class="btn btn-danger btn-sm btn-icon" title="Excluir">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php endif; /* fim formulário vs lista */ ?>

                    <!-- ═══ PÁGINA: CATEGORIAS ══════════════════════════ -->
                <?php elseif ($page === 'categorias'): ?>

                    <?php
                    $editCat = null;
                    if (isset($_GET['editar'])) {
                        $s = $pdo->prepare("SELECT * FROM categorias WHERE id=?");
                        $s->execute([intval($_GET['editar'])]);
                        $editCat = $s->fetch(PDO::FETCH_ASSOC);
                    }
                    // Contagem de produtos por categoria
                    $cntStmt = $pdo->query("SELECT categoria_id, COUNT(*) as total FROM produtos GROUP BY categoria_id");
                    $cntMap  = [];
                    foreach ($cntStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $cntMap[$row['categoria_id']] = $row['total'];
                    }
                    ?>

                    <div class="page-hd">
                        <div>
                            <h1>Categorias</h1>
                            <p><?= count($categorias) ?> categorias cadastradas</p>
                        </div>
                    </div>

                    <!-- Formulário inline -->
                    <div class="form-card" style="margin-bottom:1.5rem;">
                        <p style="font-size:0.85rem;font-weight:700;margin-bottom:1rem;color:var(--text2);">
                            <?= $editCat ? '✏️ Editando categoria: <strong style="color:var(--text)">' . htmlspecialchars($editCat['nome']) . '</strong>' : '➕ Nova categoria' ?>
                        </p>
                        <form method="POST" action="?page=categorias" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
                            <input type="hidden" name="action" value="salvar_categoria">
                            <input type="hidden" name="id" value="<?= $editCat ? intval($editCat['id']) : 0 ?>">

                            <div class="form-group" style="flex:1;min-width:180px;">
                                <label>Nome da categoria *</label>
                                <input type="text" name="nome" placeholder="ex: Inteligência Artificial" required
                                    value="<?= htmlspecialchars($editCat['nome'] ?? '') ?>" autofocus>
                            </div>

                            <div class="form-group" style="width:200px;">
                                <label>Ícone Font Awesome</label>
                                <input type="text" name="icone"
                                    placeholder="fa-solid fa-robot"
                                    value="<?= htmlspecialchars($editCat['icone'] ?? 'fa-solid fa-tag') ?>">
                                <span class="hint">Ex: <code>fa-solid fa-robot</code> · <code>fa-brands fa-youtube</code></span>
                            </div>

                            <div style="display:flex;gap:0.5rem;padding-bottom:1px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    <?= $editCat ? 'Salvar' : 'Criar' ?>
                                </button>
                                <?php if ($editCat): ?>
                                    <a href="?page=categorias" class="btn btn-ghost">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <p style="margin-top:0.9rem;font-size:0.72rem;color:var(--text3);">
                            <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
                            Encontre ícones em <a href="https://fontawesome.com/icons" target="_blank" style="color:var(--accent);text-decoration:none;">fontawesome.com/icons</a> — copie a classe completa.
                        </p>
                    </div>

                    <!-- Tabela de categorias -->
                    <div class="table-wrap">
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ícone</th>
                                        <th>Nome</th>
                                        <th>Classe do ícone</th>
                                        <th>Produtos</th>
                                        <th style="text-align:right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categorias)): ?>
                                        <tr class="empty-row">
                                            <td colspan="5">
                                                <i class="fa-solid fa-layer-group"></i>
                                                Nenhuma categoria cadastrada ainda.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categorias as $cat): ?>
                                            <tr>
                                                <td style="font-size:1.2rem;width:50px;">
                                                    <i class="<?= htmlspecialchars($cat['icone'] ?? 'fa-solid fa-tag') ?>" style="color:var(--accent);"></i>
                                                </td>
                                                <td style="font-weight:700;"><?= htmlspecialchars($cat['nome']) ?></td>
                                                <td><code style="font-size:0.75rem;color:var(--text3);background:var(--surface2);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($cat['icone'] ?? '') ?></code></td>
                                                <td>
                                                    <span class="badge <?= ($cntMap[$cat['id']] ?? 0) > 0 ? 'badge-blue' : 'badge-gray' ?>">
                                                        <?= $cntMap[$cat['id']] ?? 0 ?> produto<?= ($cntMap[$cat['id']] ?? 0) !== 1 ? 's' : '' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="display:flex;gap:5px;justify-content:flex-end;">
                                                        <a href="?page=categorias&editar=<?= $cat['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Editar">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </a>
                                                        <button onclick="confirmarExclusaoCat(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['nome'])) ?>')"
                                                            class="btn btn-danger btn-sm btn-icon" title="Excluir">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; /* fim páginas */ ?>

            </main>
        </div><!-- /layout -->

    <?php endif; /* fim isLogged */ ?>

    <!-- ═══ MODAL DE CONFIRMAÇÃO DE EXCLUSÃO ═══════════════════ -->
    <div class="modal-backdrop" id="modalConfirm">
        <div class="modal">
            <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--red);margin-right:6px;"></i> Confirmar exclusão</h3>
            <p id="confirmMsg">Tem certeza que deseja remover este item? Essa ação não pode ser desfeita.</p>
            <div class="modal-actions">
                <button class="btn btn-ghost" onclick="fecharConfirm()">Cancelar</button>
                <a href="#" id="confirmLink" class="btn btn-danger">
                    <i class="fa-solid fa-trash"></i> Sim, excluir
                </a>
            </div>
        </div>
    </div>

    <script>
        /* ── Sidebar mobile ────────────────────────────────────────── */
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            const i = document.getElementById('menuIcon');
            s.classList.toggle('open');
            i.className = s.classList.contains('open') ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        }
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const btn = document.querySelector('.mob-menu-btn');
            if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !btn.contains(e.target)) {
                sidebar.classList.remove('open');
                document.getElementById('menuIcon').className = 'fa-solid fa-bars';
            }
        });

        /* ── Preview de preço ──────────────────────────────────────── */
        function atualizarPreview() {
            const val = parseFloat(document.getElementById('precoInput')?.value || 0);
            const prev = document.getElementById('precoPreview');
            if (prev) {
                prev.textContent = isNaN(val) ?
                    'R$ 0,00' :
                    'R$ ' + val.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
            }
        }

        /* ── Modal de confirmação ──────────────────────────────────── */
        function confirmarExclusao(id, titulo) {
            document.getElementById('confirmMsg').textContent =
                'Tem certeza que deseja remover o produto "' + titulo + '"? Essa ação não pode ser desfeita.';
            document.getElementById('confirmLink').href = '?action=excluir_produto&id=' + id + '&page=produtos';
            abrirConfirm();
        }

        function confirmarExclusaoCat(id, nome) {
            document.getElementById('confirmMsg').textContent =
                'Tem certeza que deseja remover a categoria "' + nome + '"? Ela só pode ser removida se não tiver produtos vinculados.';
            document.getElementById('confirmLink').href = '?action=excluir_categoria&id=' + id + '&page=categorias';
            abrirConfirm();
        }

        function abrirConfirm() {
            document.getElementById('modalConfirm').classList.add('open');
        }

        function fecharConfirm() {
            document.getElementById('modalConfirm').classList.remove('open');
        }
        document.getElementById('modalConfirm')?.addEventListener('click', function(e) {
            if (e.target === this) fecharConfirm();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharConfirm();
        });

        /* ── Flash auto-dismiss ──────────────────────────────────────── */
        const flash = document.querySelector('.flash');
        if (flash) setTimeout(() => {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 4000);

        /* ── Novo produto via sidebar link ───────────────────────────── */
        const params = new URLSearchParams(window.location.search);
        if (params.get('novo') === '1') {
            document.querySelector('input[name="titulo"]')?.focus();
        }
    </script>
</body>

</html>