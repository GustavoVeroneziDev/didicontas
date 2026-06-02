<?php
/* ═══════════════════════════════════════════════════════════
   DIDICONTAS — PAINEL ADMIN v4
   Com Live Preview e Correção de Máscara
   ═══════════════════════════════════════════════════════════ */

session_start();

$conexao_path = __DIR__ . '/../../../config/conexao.php';
if (!file_exists($conexao_path)) {
    die('<pre style="font:14px monospace;padding:2rem;color:#f87171;background:#0b0f1a;">ERRO: conexao.php não encontrado.</pre>');
}
require_once $conexao_path;

/* ─── Diretório de uploads de imagens ───────────────────── */
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('UPLOAD_URL',  'uploads/');
define('MAX_IMG_SIZE', 3 * 1024 * 1024);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    file_put_contents(UPLOAD_DIR . '.htaccess', "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh\n");
}

/* ─── Acesso ─────────────────────────────────────────────── */
define('ADMIN_USER',      'didi');
define('ADMIN_PASS',      '$2y$10$TROQUE_ESTE_HASH');
define('ADMIN_PASS_DEMO', 'didi2025');

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

function salvarImagem(string $field): ?string
{
    if (empty($_FILES[$field]['name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Erro no upload da imagem.', 'erro');
        return null;
    }
    if ($file['size'] > MAX_IMG_SIZE) {
        flash('Imagem muito grande. Máximo: 3MB.', 'erro');
        return null;
    }
    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed)) {
        flash('Formato não suportado.', 'erro');
        return null;
    }
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
    $name = uniqid('img_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        flash('Falha ao salvar.', 'erro');
        return null;
    }
    return UPLOAD_URL . $name;
}

$page   = $_GET['page']   ?? 'produtos';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ═══ AÇÕES POST ═════════════════════════════════════════ */
if ($action === 'login') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['senha'] ?? '';
    if (($user === ADMIN_USER) && (password_verify($pass, ADMIN_PASS) || $pass === ADMIN_PASS_DEMO)) {
        $_SESSION['dc_admin'] = true;
        header('Location: ?page=produtos');
        exit;
    }
    flash('Usuário ou senha inválidos.', 'erro');
    header('Location: ?page=login');
    exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($action === 'salvar_produto') {
    requireLogin();
    $id       = intval($_POST['id'] ?? 0);
    $titulo   = trim($_POST['titulo']   ?? '');
    $desc     = trim($_POST['descricao'] ?? '');

    /* CORREÇÃO DO CÁLCULO DE PREÇO: 1.500,50 -> 1500.50 */
    $preco_str = str_replace(['R$', ' '], '', $_POST['preco'] ?? '0');
    $preco_str = str_replace('.', '', $preco_str);
    $preco_str = str_replace(',', '.', $preco_str);
    $preco     = floatval($preco_str);

    $ciclo    = in_array($_POST['ciclo'] ?? '', ['mensal', 'anual']) ? $_POST['ciclo'] : 'mensal';
    $cat_id   = intval($_POST['categoria_id'] ?? 0);
    $status   = ($_POST['status'] ?? '') === 'ativo' ? 'ativo' : 'inativo';
    $destaque = isset($_POST['destaque']) ? 1 : 0;

    // Processamento Inteligente da Imagem
    $remover    = ($_POST['remover_imagem'] ?? '') === '1';
    $img_upload = salvarImagem('imagem_arquivo');
    $img_url    = trim($_POST['imagem_url'] ?? '');

    if ($remover) {
        $img_final = null;
    } elseif ($img_upload) {
        $img_final = $img_upload;
    } elseif ($img_url) {
        $img_final = $img_url;
    } else {
        $img_final = null;
        if ($id > 0) {
            $cur = $pdo->prepare("SELECT imagem_url FROM produtos WHERE id=?");
            $cur->execute([$id]);
            $img_final = $cur->fetchColumn() ?: null;
        }
    }

    if ($titulo === '') {
        flash('O título é obrigatório.', 'erro');
        header('Location: ?page=produtos');
        exit;
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE produtos SET titulo=?,descricao=?,preco=?,ciclo=?,categoria_id=?,status=?,destaque=?,imagem_url=? WHERE id=?")
            ->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img_final, $id]);
        flash('Produto atualizado com sucesso.');
    } else {
        $pdo->prepare("INSERT INTO produtos (titulo,descricao,preco,ciclo,categoria_id,status,destaque,imagem_url) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img_final]);
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
        $pdo->prepare("UPDATE produtos SET status=IF(status='ativo','inativo','ativo') WHERE id=?")->execute([$id]);
        flash('Status atualizado.');
    }
    header('Location: ?page=produtos');
    exit;
}

if ($action === 'salvar_categoria') {
    requireLogin();
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $icone = trim($_POST['icone'] ?? 'fa-solid fa-tag');
    if ($nome === '') {
        flash('O nome é obrigatório.', 'erro');
        header('Location: ?page=categorias');
        exit;
    }
    if ($id > 0) {
        $pdo->prepare("UPDATE categorias SET nome=?,icone=? WHERE id=?")->execute([$nome, $icone, $id]);
        flash('Categoria atualizada.');
    } else {
        $pdo->prepare("INSERT INTO categorias (nome,icone) VALUES (?,?)")->execute([$nome, $icone]);
        flash('Categoria criada.');
    }
    header('Location: ?page=categorias');
    exit;
}

if ($action === 'excluir_categoria') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id=?");
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            flash('Categoria tem produtos. Remova-os primeiro.', 'erro');
        } else {
            $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$id]);
            flash('Categoria removida.');
        }
    }
    header('Location: ?page=categorias');
    exit;
}

/* ═══ DADOS PARA EXIBIÇÃO ════════════════════════════════ */
if (isLogged()) {
    $categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stats = [
        'total'    => $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn(),
        'ativos'   => $pdo->query("SELECT COUNT(*) FROM produtos WHERE status='ativo'")->fetchColumn(),
        'inativos' => $pdo->query("SELECT COUNT(*) FROM produtos WHERE status='inativo'")->fetchColumn(),
        'cats'     => $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn(),
    ];

    if ($page === 'produtos') {
        $busca = trim($_GET['busca'] ?? '');
        $filtCat = intval($_GET['cat'] ?? 0);
        $filtStatus = $_GET['status'] ?? '';
        $where = ['1=1'];
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
        $stmt = $pdo->prepare("SELECT p.*,c.nome AS cat_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id=c.id WHERE " . implode(' AND ', $where) . " ORDER BY p.id DESC");
        $stmt->execute($params);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $editProd = null;
        if (isset($_GET['editar'])) {
            $s = $pdo->prepare("SELECT * FROM produtos WHERE id=?");
            $s->execute([intval($_GET['editar'])]);
            $editProd = $s->fetch(PDO::FETCH_ASSOC);
        }
    }
}
$flash = getFlash();

/* Biblioteca de ícones */
$ICONES = [
    'Streaming & Mídia' => [['fa-brands fa-youtube', 'YouTube'], ['fa-solid fa-film', 'Cinema'], ['fa-solid fa-tv', 'TV'], ['fa-solid fa-music', 'Música'], ['fa-solid fa-gamepad', 'Games']],
    'Inteligência Artificial' => [['fa-solid fa-robot', 'Robô / IA'], ['fa-solid fa-brain', 'Cérebro'], ['fa-solid fa-microchip', 'Microchip'], ['fa-solid fa-comment-dots', 'Chat AI'], ['fa-solid fa-bolt', 'Rápido']],
    'Design & Trabalho' => [['fa-solid fa-briefcase', 'Trabalho'], ['fa-solid fa-palette', 'Paleta'], ['fa-solid fa-pen-nib', 'Caneta'], ['fa-solid fa-image', 'Imagem'], ['fa-solid fa-cloud', 'Cloud']],
    'Geral' => [['fa-solid fa-star', 'Estrela'], ['fa-solid fa-tag', 'Tag'], ['fa-solid fa-crown', 'Coroa'], ['fa-solid fa-shield-halved', 'Escudo'], ['fa-solid fa-award', 'Prêmio']],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin — Didi Contas</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* [O CSS Base foi mantido compacto para poupar espaço, adicionei apenas o necessário para o Live Preview] */
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        html {
            scroll-behavior: smooth
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            font-size: 15px;
            line-height: 1.6
        }

        .layout {
            display: flex;
            min-height: 100vh
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
            transition: transform 0.3s
        }

        .content {
            margin-left: var(--sidebar);
            flex: 1;
            padding: 2rem;
            max-width: 1300px
        }

        @media(max-width:768px) {
            .sidebar {
                transform: translateX(-100%)
            }

            .sidebar.open {
                transform: none;
                box-shadow: 0 0 0 100vw rgba(0, 0, 0, 0.6)
            }

            .content {
                margin-left: 0;
                padding: 1rem
            }

            .mob-header {
                display: flex !important
            }

            .preview-wrapper {
                display: none;
            }
        }

        .sidebar-logo {
            padding: 1.5rem 1.25rem 1rem;
            font-size: 1.2rem;
            font-weight: 800;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo span {
            color: var(--yellow)
        }

        .nav-section {
            padding: 1rem 0.75rem 0.4rem;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text3)
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text2);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left
        }

        .nav-item:hover {
            background: var(--surface);
            color: var(--text)
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent);
            font-weight: 600
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 0.75rem 0
        }

        .mob-header {
            display: none;
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--bg2);
            border-bottom: 1px solid var(--border);
            padding: 0 1rem;
            height: 56px;
            align-items: center;
            justify-content: space-between
        }

        .page-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.75rem
        }

        .page-hd h1 {
            font-size: 1.4rem;
            font-weight: 800;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.75rem
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1rem
        }

        .stat-card .val {
            font-size: 1.75rem;
            font-weight: 800;
        }

        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 0.9rem 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
            margin-bottom: 1.25rem
        }

        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1.5rem
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem
        }

        @media(max-width:600px) {
            .form-grid {
                grid-template-columns: 1fr
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem
        }

        .form-group.full {
            grid-column: 1/-1
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text2)
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: var(--r-sm);
            color: var(--text);
            padding: 0.7rem 0.9rem;
            outline: none;
            width: 100%
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            cursor: pointer;
            border-radius: var(--r-sm);
            border: none;
            padding: 0.55rem 1.1rem;
            text-decoration: none;
            font-size: 0.82rem;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff
        }

        .btn-success {
            background: var(--green);
            color: #fff
        }

        .btn-ghost {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text2)
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #F87171
        }

        .btn-warn {
            background: rgba(245, 158, 11, 0.1);
            color: #FCD34D
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem
        }

        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px
        }

        th,
        td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }

        th {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text3);
            background: var(--surface)
        }

        .badge {
            display: inline-flex;
            padding: 0.2rem 0.6rem;
            border-radius: 100px;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .badge-green {
            background: rgba(16, 185, 129, 0.1);
            color: #34D399
        }

        .badge-red {
            background: rgba(239, 68, 68, 0.1);
            color: #F87171
        }

        .badge-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #93C5FD
        }

        .badge-gray {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text3)
        }

        /* Upload & Preview UI */
        .img-tabs {
            display: flex;
            gap: 3px;
            background: var(--surface3);
            border-radius: var(--r-sm);
            padding: 3px;
            margin-bottom: 0.75rem
        }

        .img-tab {
            flex: 1;
            padding: 0.45rem;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text3);
        }

        .img-tab.active {
            background: var(--accent);
            color: #fff
        }

        .img-panel {
            display: none
        }

        .img-panel.active {
            display: block
        }

        .upload-zone {
            border: 2px dashed var(--border2);
            border-radius: var(--r);
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            position: relative;
            background: var(--surface2)
        }

        .upload-zone input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%
        }

        .upload-zone i {
            font-size: 1.6rem;
            color: var(--text3);
            display: block;
            margin-bottom: 0.5rem
        }

        .img-preview-wrap {
            margin-top: 0.75rem;
            position: relative;
            display: none;
            width: 100%;
            max-width: 250px;
        }

        .img-preview-wrap img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: var(--r-sm);
            border: 1px solid var(--border)
        }

        .remove-img {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.9);
            border: none;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* LIVE PREVIEW CARD CSS (Mesmo do Front-End) */
        .preview-wrapper {
            width: 280px;
            flex-shrink: 0;
            position: sticky;
            top: 80px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .card-produto {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            pointer-events: none;
            transition: opacity 0.3s;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .card-destaque-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--yellow);
            color: #000;
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 0.2rem 0.5rem;
            border-radius: 100px;
            z-index: 2;
        }

        .card-body {
            padding: 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .card-cat {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #60A5FA;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.2rem 0.55rem;
            border-radius: 100px;
            width: fit-content;
        }

        .card-titulo {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
        }

        .card-footer {
            margin-top: auto;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preco-rs {
            font-size: 0.68rem;
            color: var(--text3);
            font-weight: 700;
        }

        .preco-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text);
        }

        .preco-ciclo {
            font-size: 0.68rem;
            color: var(--text3);
        }

        .card-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .preview-img-container {
            width: 100%;
            height: 130px;
            background: var(--surface3);
            display: none;
        }

        .preview-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .toggle-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface2);
            padding: 0.7rem 0.9rem;
            border-radius: var(--r-sm);
            border: 1px solid var(--border);
        }

        .toggle {
            position: relative;
            width: 46px;
            height: 26px;
            display: inline-flex;
            cursor: pointer;
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
            border-radius: 100px;
            transition: 0.2s;
            border: 1px solid var(--border2);
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
            transition: 0.2s;
        }

        .toggle input:checked+.toggle-slider {
            background: var(--accent);
            border-color: var(--accent);
        }

        .toggle input:checked+.toggle-slider::before {
            transform: translateX(20px);
            background: #fff;
        }
    </style>
</head>

<body>

    <?php if (!isLogged()): ?>
        <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;">
            <div class="form-card" style="width:100%;max-width:380px;">
                <p style="font-size:1.6rem;font-weight:800;text-align:center;">didi<span style="color:var(--yellow)">contas</span></p>
                <form method="POST" action="?page=login" style="margin-top:1.5rem;">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group" style="margin-bottom:1rem;"><label>Usuário</label><input type="text" name="usuario" required></div>
                    <div class="form-group" style="margin-bottom:1.5rem;"><label>Senha</label><input type="password" name="senha" required></div>
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Entrar no painel</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="mob-header">
            <span style="font-weight:800;">didi<span style="color:var(--yellow)">contas</span></span>
            <button onclick="document.getElementById('sidebar').classList.toggle('open')" style="background:none;border:none;color:#fff;font-size:1.5rem;"><i class="fa-solid fa-bars"></i></button>
        </div>

        <div class="layout">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo">didi<span>contas</span><small>Painel Admin</small></div>
                <div style="padding-top:1rem;">
                    <a href="?page=produtos" class="nav-item <?= $page === 'produtos' ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i> Produtos</a>
                    <a href="?page=categorias" class="nav-item <?= $page === 'categorias' ? 'active' : '' ?>"><i class="fa-solid fa-layer-group"></i> Categorias</a>
                    <a href="?page=produtos&novo=1" class="nav-item" style="margin-top:1rem;"><i class="fa-solid fa-plus"></i> Novo produto</a>
                    <a href="/geral/index.php" target="_blank" class="nav-item"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver vitrine</a>
                </div>
                <div class="sidebar-footer"><a href="?action=logout" class="nav-item"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></div>
            </aside>

            <main class="content">
                <?php if ($page === 'produtos'): ?>
                    <?php $isNovo = isset($_GET['novo']);
                    $isEditar = isset($editProd) && $editProd; ?>

                    <?php if ($isNovo || $isEditar): ?>
                        <div class="page-hd">
                            <div>
                                <h1><?= $isEditar ? 'Editar Produto' : 'Novo Produto' ?></h1>
                            </div>
                        </div>

                        <form method="POST" action="?page=produtos" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="salvar_produto">
                            <input type="hidden" name="id" value="<?= $isEditar ? intval($editProd['id']) : 0 ?>">

                            <div style="display:flex; flex-wrap:wrap; gap:1.5rem; align-items:flex-start;">

                                <div class="form-card" style="flex:1; min-width:320px;">
                                    <div class="form-grid">
                                        <div class="form-group full">
                                            <label>Título do produto *</label>
                                            <input type="text" name="titulo" required id="inpTitulo" value="<?= htmlspecialchars($editProd['titulo'] ?? '') ?>">
                                        </div>

                                        <div class="form-group full">
                                            <label>Descrição</label>
                                            <textarea name="descricao"><?= htmlspecialchars($editProd['descricao'] ?? '') ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label>Preço (R$) *</label>
                                            <input type="text" name="preco" required id="inpPreco" value="<?= $isEditar ? 'R$ ' . number_format($editProd['preco'], 2, ',', '.') : '' ?>" oninput="mascaraPreco(this)">
                                        </div>

                                        <div class="form-group">
                                            <label>Ciclo</label>
                                            <select name="ciclo" id="inpCiclo">
                                                <option value="mensal" <?= ($editProd['ciclo'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                                <option value="anual" <?= ($editProd['ciclo'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option>
                                            </select>
                                        </div>

                                        <div class="form-group full">
                                            <label>Categoria</label>
                                            <select name="categoria_id" id="inpCat">
                                                <option value="" data-nome="Geral" data-icone="fa-solid fa-tag">Sem categoria</option>
                                                <?php foreach ($categorias as $cat): ?>
                                                    <option value="<?= $cat['id'] ?>" data-nome="<?= htmlspecialchars($cat['nome']) ?>" data-icone="<?= htmlspecialchars($cat['icone']) ?>" <?= ($editProd['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cat['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group full">
                                            <label>Imagem do produto</label>
                                            <?php
                                            $imgAtual = $editProd['imagem_url'] ?? '';
                                            $isUploadLocal = ($imgAtual && !str_starts_with($imgAtual, 'http'));
                                            ?>
                                            <div class="img-tabs">
                                                <button type="button" class="img-tab <?= !$isUploadLocal ? 'active' : '' ?>" onclick="imgTab(this,'tab-url')"><i class="fa-solid fa-link"></i> Link da Internet</button>
                                                <button type="button" class="img-tab <?= $isUploadLocal ? 'active' : '' ?>" onclick="imgTab(this,'tab-upload')"><i class="fa-solid fa-upload"></i> Ficheiro Local</button>
                                            </div>

                                            <div class="img-panel <?= !$isUploadLocal ? 'active' : '' ?>" id="tab-url">
                                                <input type="text" name="imagem_url" id="imgUrlInput" placeholder="https://exemplo.com/imagem.png" value="<?= !$isUploadLocal ? htmlspecialchars($imgAtual) : '' ?>" oninput="previewUrl(this.value)">
                                            </div>

                                            <div class="img-panel <?= $isUploadLocal ? 'active' : '' ?>" id="tab-upload">
                                                <div class="upload-zone">
                                                    <input type="file" name="imagem_arquivo" id="imgFileInput" accept="image/*" onchange="previewFile(this)">
                                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                                    <p>Clique ou arraste a imagem aqui</p>
                                                </div>
                                            </div>

                                            <div class="img-preview-wrap" id="imgPreviewWrap" style="display: <?= $imgAtual ? 'block' : 'none' ?>;">
                                                <img id="imgPreview" src="<?= htmlspecialchars($imgAtual) ?>">
                                                <button type="button" class="remove-img" onclick="removerImagem()"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                            <input type="hidden" name="remover_imagem" id="removerImagemInput" value="">

                                            <?php if ($isUploadLocal): ?>
                                                <span class="hint" style="color:var(--yellow);font-size:0.75rem;margin-top:6px;display:block;" id="avisoUploadLocal">
                                                    <i class="fa-solid fa-image"></i> Imagem atual guardada no servidor.
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-group">
                                            <div class="toggle-wrap"><span style="font-size:0.8rem;font-weight:600;">Status Ativo</span><label class="toggle"><input type="checkbox" name="status" id="inpStatus" value="ativo" <?= ($editProd['status'] ?? 'ativo') === 'ativo' ? 'checked' : '' ?>><span class="toggle-slider"></span></label></div>
                                        </div>
                                        <div class="form-group">
                                            <div class="toggle-wrap"><span style="font-size:0.8rem;font-weight:600;">Destaque</span><label class="toggle"><input type="checkbox" name="destaque" id="inpDestaque" value="1" <?= !empty($editProd['destaque']) ? 'checked' : '' ?>><span class="toggle-slider"></span></label></div>
                                        </div>
                                    </div>

                                    <div style="margin-top:1.5rem;display:flex;gap:0.75rem;justify-content:flex-end;">
                                        <a href="?page=produtos" class="btn btn-ghost">Cancelar</a>
                                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                                    </div>
                                </div>

                                <div class="preview-wrapper">
                                    <p style="font-size: 0.75rem; font-weight: 700; color: var(--text3); text-transform: uppercase; margin-bottom: 0px; letter-spacing: 0.5px;">Pré-visualização do Card</p>
                                    <p style="font-size: 0.65rem; color: var(--text3); margin-bottom: 0.75rem;">É assim que ficará na vitrine.</p>

                                    <div class="card-produto" id="cardPreview">
                                        <div class="preview-img-container" id="prevImgWrap"><img id="prevImg" src=""></div>
                                        <span class="card-destaque-badge" id="prevDestaque" style="display:none;">Destaque</span>
                                        <div class="card-body">
                                            <span class="card-cat"><i id="prevCatIcon" class="fa-solid fa-tag"></i><span id="prevCatName">Geral</span></span>
                                            <h2 class="card-titulo" id="prevTitle">Título do Produto</h2>
                                            <div class="card-footer">
                                                <div style="display:flex; align-items:baseline; gap:2px;"><span class="preco-rs">R$</span><span class="preco-val" id="prevPrice">0,00</span><span class="preco-ciclo" id="prevCiclo">/mês</span></div>
                                                <span class="card-btn"><i class="fa-solid fa-plus"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="page-hd">
                            <div>
                                <h1>Produtos Cadastrados</h1>
                            </div> <a href="?page=produtos&novo=1" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Novo produto</a>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Preço</th>
                                        <th>Categoria</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $p): ?>
                                        <tr>
                                            <td style="font-weight:bold;"><?= htmlspecialchars($p['titulo']) ?></td>
                                            <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                                            <td><span class="badge badge-blue"><?= htmlspecialchars($p['cat_nome'] ?? 'Geral') ?></span></td>
                                            <td><a href="?page=produtos&editar=<?= $p['id'] ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-pen"></i> Editar</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>

        <script>
            /* ── Máscara Inteligente de Preço ── */
            function mascaraPreco(input) {
                let val = input.value.replace(/\D/g, '');
                if (!val) {
                    input.value = '';
                    atualizarPreviewCard();
                    return;
                }
                let num = parseInt(val, 10) / 100;
                input.value = 'R$ ' + num.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                atualizarPreviewCard();
            }

            /* ── Lógica de UX das Imagens ── */
            function imgTab(btn, id) {
                document.querySelectorAll('.img-tab').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.img-panel').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(id).classList.add('active');
                if (id === 'tab-url') document.getElementById('imgFileInput').value = '';
                if (id === 'tab-upload') document.getElementById('imgUrlInput').value = '';
            }

            function previewUrl(url) {
                const img = document.getElementById('imgPreview');
                if (url && url.trim()) {
                    img.src = url;
                    document.getElementById('imgPreviewWrap').style.display = 'block';
                    document.getElementById('removerImagemInput').value = '';
                } else {
                    document.getElementById('imgPreviewWrap').style.display = 'none';
                }
                atualizarPreviewCard();
            }

            function previewFile(input) {
                if (!input.files || !input.files[0]) return;
                const r = new FileReader();
                r.onload = e => {
                    document.getElementById('imgPreview').src = e.target.result;
                    document.getElementById('imgPreviewWrap').style.display = 'block';
                    document.getElementById('imgUrlInput').value = '';
                    const aviso = document.getElementById('avisoUploadLocal');
                    if (aviso) aviso.style.display = 'none';
                    atualizarPreviewCard();
                };
                r.readAsDataURL(input.files[0]);
            }

            function removerImagem() {
                document.getElementById('imgPreviewWrap').style.display = 'none';
                document.getElementById('imgPreview').src = '';
                document.getElementById('removerImagemInput').value = '1';
                document.getElementById('imgUrlInput').value = '';
                document.getElementById('imgFileInput').value = '';
                const aviso = document.getElementById('avisoUploadLocal');
                if (aviso) aviso.style.display = 'none';
                atualizarPreviewCard();
            }

            /* ── MOTOR DO LIVE PREVIEW ── */
            function atualizarPreviewCard() {
                if (!document.getElementById('cardPreview')) return;

                // Título
                document.getElementById('prevTitle').textContent = document.getElementById('inpTitulo').value || 'Título do Produto';
                // Preço
                let pText = document.getElementById('inpPreco').value.replace('R$ ', '');
                document.getElementById('prevPrice').textContent = pText || '0,00';
                // Ciclo
                document.getElementById('prevCiclo').textContent = document.getElementById('inpCiclo').value === 'mensal' ? '/mês' : '/ano';
                // Status (Escurece se Inativo)
                document.getElementById('cardPreview').style.opacity = document.getElementById('inpStatus').checked ? '1' : '0.5';
                // Destaque
                document.getElementById('prevDestaque').style.display = document.getElementById('inpDestaque').checked ? 'block' : 'none';

                // Categoria
                const catSel = document.getElementById('inpCat');
                const catOpt = catSel.options[catSel.selectedIndex];
                document.getElementById('prevCatName').textContent = catOpt.dataset.nome || 'Geral';
                document.getElementById('prevCatIcon').className = catOpt.dataset.icone || 'fa-solid fa-tag';

                // Imagem
                const isImgVisible = document.getElementById('imgPreviewWrap').style.display === 'block';
                document.getElementById('prevImgWrap').style.display = isImgVisible ? 'block' : 'none';
                if (isImgVisible) document.getElementById('prevImg').src = document.getElementById('imgPreview').src;
            }

            // Escutar eventos de digitação
            ['inpTitulo', 'inpPreco', 'inpCiclo', 'inpCat', 'inpStatus', 'inpDestaque'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', atualizarPreviewCard);
                    el.addEventListener('change', atualizarPreviewCard);
                }
            });
            window.onload = atualizarPreviewCard;
        </script>
    <?php endif; ?>
</body>

</html>