<?php
/* ═══════════════════════════════════════════════════════════
   DIDICONTAS — PAINEL ADMIN v3
   ESTRUTURA:
   DIDICONTAS/
   ├── config/conexao.php
   └── geral/
       └── public/
           └── admin/
               └── index.php  ← ESTE ARQUIVO
   ═══════════════════════════════════════════════════════════ */

session_start();

$conexao_path = __DIR__ . '/../../../config/conexao.php';
if (!file_exists($conexao_path)) {
    die('<pre style="font:14px monospace;padding:2rem;color:#f87171;background:#0b0f1a;">
ERRO: conexao.php não encontrado.
Caminho tentado: ' . $conexao_path . '
Estrutura esperada:
  DIDICONTAS/config/conexao.php
  DIDICONTAS/geral/public/admin/index.php
</pre>');
}
require_once $conexao_path;

/* ─── Diretório de uploads de imagens ───────────────────── */
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('UPLOAD_URL',  'uploads/');
define('MAX_IMG_SIZE', 3 * 1024 * 1024); // 3MB

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    // Protege o diretório de execução de scripts
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

/* ─── Helper: salva imagem enviada ──────────────────────── */
function salvarImagem(string $field): ?string
{
    if (empty($_FILES[$field]['name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Erro no upload da imagem (código ' . $file['error'] . ').', 'erro');
        return null;
    }
    if ($file['size'] > MAX_IMG_SIZE) {
        flash('Imagem muito grande. Máximo: 3MB.', 'erro');
        return null;
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed)) {
        flash('Formato não suportado. Use JPG, PNG, WEBP ou GIF.', 'erro');
        return null;
    }

    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
    $name = uniqid('img_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        flash('Falha ao salvar imagem.', 'erro');
        return null;
    }
    return UPLOAD_URL . $name;
}

/* ─── Roteamento ─────────────────────────────────────────── */
$page   = $_GET['page']   ?? 'produtos';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ═══ AÇÕES POST ═════════════════════════════════════════ */

if ($action === 'login') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['senha'] ?? '';
    $ok   = ($user === ADMIN_USER) && (password_verify($pass, ADMIN_PASS) || $pass === ADMIN_PASS_DEMO);
    if ($ok) {
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
    $preco    = floatval(str_replace(['.', 'R$', ' '], ['', '', ''], str_replace(',', '.', $_POST['preco'] ?? '0')));
    $ciclo    = in_array($_POST['ciclo'] ?? '', ['mensal', 'anual']) ? $_POST['ciclo'] : 'mensal';
    $cat_id   = intval($_POST['categoria_id'] ?? 0);
    $status   = ($_POST['status'] ?? '') === 'ativo' ? 'ativo' : 'inativo';
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $img_url  = trim($_POST['imagem_url'] ?? '');

    // Upload tem prioridade sobre URL
    $img_upload = salvarImagem('imagem_arquivo');
    $img_final  = $img_upload ?? ($img_url ?: null);

    // Se editando e não enviou nova imagem, manter a atual
    if ($id > 0 && $img_final === null && !isset($_POST['remover_imagem'])) {
        $cur = $pdo->prepare("SELECT imagem_url FROM produtos WHERE id=?");
        $cur->execute([$id]);
        $img_final = $cur->fetchColumn() ?: null;
    }

    if ($titulo === '') {
        flash('O título é obrigatório.', 'erro');
        header('Location: ?page=produtos');
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE produtos SET titulo=?,descricao=?,preco=?,ciclo=?,categoria_id=?,status=?,destaque=?,imagem_url=? WHERE id=?");
        $stmt->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img_final, $id]);
        flash('Produto atualizado com sucesso.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO produtos (titulo,descricao,preco,ciclo,categoria_id,status,destaque,imagem_url) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img_final]);
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
    $id    = intval($_POST['id'] ?? 0);
    $nome  = trim($_POST['nome']  ?? '');
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
        $busca      = trim($_GET['busca'] ?? '');
        $filtCat    = intval($_GET['cat'] ?? 0);
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

/* ─── Biblioteca de ícones agrupados ────────────────────── */
$ICONES = [
    'Streaming & Entretenimento' => [
        ['fa-brands fa-youtube',    'YouTube'],
        ['fa-solid fa-film',        'Cinema'],
        ['fa-solid fa-tv',          'TV'],
        ['fa-solid fa-music',       'Música'],
        ['fa-solid fa-headphones',  'Headphones'],
        ['fa-solid fa-gamepad',     'Games'],
        ['fa-brands fa-twitch',     'Twitch'],
        ['fa-solid fa-podcast',     'Podcast'],
        ['fa-solid fa-clapperboard', 'Claquete'],
        ['fa-solid fa-compact-disc', 'CD/DVD'],
    ],
    'Inteligência Artificial' => [
        ['fa-solid fa-robot',          'Robô / IA'],
        ['fa-solid fa-brain',          'Cérebro'],
        ['fa-solid fa-microchip',      'Microchip'],
        ['fa-solid fa-wand-magic-sparkles', 'Varinha Mágica'],
        ['fa-solid fa-comment-dots',   'Chat AI'],
        ['fa-solid fa-bolt',           'Raio / Rápido'],
        ['fa-solid fa-circle-nodes',   'Redes'],
        ['fa-solid fa-network-wired',  'Rede'],
        ['fa-solid fa-satellite-dish', 'Satélite'],
        ['fa-solid fa-infinity',       'Infinito'],
    ],
    'Produtividade & Ferramentas' => [
        ['fa-solid fa-briefcase',    'Trabalho'],
        ['fa-solid fa-chart-line',   'Gráfico'],
        ['fa-solid fa-table-columns', 'Planilha'],
        ['fa-solid fa-file-code',    'Código'],
        ['fa-solid fa-diagram-project', 'Projeto'],
        ['fa-solid fa-calendar-check', 'Calendário'],
        ['fa-solid fa-list-check',   'Checklist'],
        ['fa-solid fa-envelope',     'E-mail'],
        ['fa-solid fa-database',     'Banco de Dados'],
        ['fa-solid fa-cloud',        'Cloud'],
    ],
    'Design & Criatividade' => [
        ['fa-solid fa-palette',      'Paleta'],
        ['fa-solid fa-pen-nib',      'Caneta'],
        ['fa-solid fa-vector-square', 'Vetor'],
        ['fa-solid fa-image',        'Imagem'],
        ['fa-solid fa-camera',       'Câmera'],
        ['fa-solid fa-photo-film',   'Foto/Filme'],
        ['fa-solid fa-paintbrush',   'Pincel'],
        ['fa-solid fa-crop-simple',  'Recorte'],
        ['fa-solid fa-layer-group',  'Camadas'],
        ['fa-solid fa-swatchbook',   'Amostras'],
    ],
    'Segurança & Tecnologia' => [
        ['fa-solid fa-shield-halved', 'Escudo'],
        ['fa-solid fa-lock',         'Cadeado'],
        ['fa-solid fa-key',          'Chave'],
        ['fa-solid fa-globe',        'Mundo/Internet'],
        ['fa-solid fa-wifi',         'Wi-Fi'],
        ['fa-solid fa-server',       'Servidor'],
        ['fa-solid fa-code',         'Código </>'],
        ['fa-solid fa-terminal',     'Terminal'],
        ['fa-solid fa-bug',          'Bug'],
        ['fa-solid fa-vpn',          'VPN'],
    ],
    'Educação & Conhecimento' => [
        ['fa-solid fa-graduation-cap', 'Formatura'],
        ['fa-solid fa-book-open',    'Livro Aberto'],
        ['fa-solid fa-chalkboard',   'Quadro'],
        ['fa-solid fa-flask',        'Laboratório'],
        ['fa-solid fa-microscope',   'Microscópio'],
        ['fa-solid fa-language',     'Idiomas'],
        ['fa-solid fa-certificate',  'Certificado'],
        ['fa-solid fa-pencil',       'Lápis'],
        ['fa-solid fa-lightbulb',    'Ideia'],
        ['fa-solid fa-atom',         'Átomo'],
    ],
    'Negócios & Finanças' => [
        ['fa-solid fa-dollar-sign',  'Dólar'],
        ['fa-solid fa-credit-card',  'Cartão'],
        ['fa-solid fa-sack-dollar',  'Dinheiro'],
        ['fa-solid fa-hand-holding-dollar', 'Receber'],
        ['fa-solid fa-shop',         'Loja'],
        ['fa-solid fa-store',        'Comércio'],
        ['fa-solid fa-receipt',      'Recibo'],
        ['fa-solid fa-percent',      'Desconto'],
        ['fa-solid fa-trending-up',  'Crescimento'],
        ['fa-solid fa-piggy-bank',   'Poupança'],
    ],
    'Geral & Outros' => [
        ['fa-solid fa-star',         'Estrela'],
        ['fa-solid fa-fire',         'Fogo/Hot'],
        ['fa-solid fa-tag',          'Tag'],
        ['fa-solid fa-gift',         'Presente'],
        ['fa-solid fa-gem',          'Gema/Premium'],
        ['fa-solid fa-crown',        'Coroa'],
        ['fa-solid fa-award',        'Prêmio'],
        ['fa-solid fa-thumbs-up',    'Curtiu'],
        ['fa-solid fa-heart',        'Favorito'],
        ['fa-solid fa-bell',         'Notificação'],
    ],
];
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

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px
        }

        ::-webkit-scrollbar-track {
            background: transparent
        }

        ::-webkit-scrollbar-thumb {
            background: var(--surface3);
            border-radius: 4px
        }

        /* Layout */
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
            max-width: 1200px
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
        }

        /* Sidebar */
        .sidebar-logo {
            padding: 1.5rem 1.25rem 1rem;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.3px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0
        }

        .sidebar-logo span {
            color: var(--yellow)
        }

        .sidebar-logo small {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text3);
            margin-top: 2px
        }

        .nav-section {
            padding: 1rem 0.75rem 0.4rem;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--text3)
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

        .nav-item i {
            width: 16px;
            text-align: center;
            font-size: 0.85rem;
            opacity: 0.8;
            flex-shrink: 0
        }

        .nav-item.active i {
            opacity: 1
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 0.75rem 0
        }

        /* Mobile header */
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
            justify-content: space-between
        }

        .mob-header .logo {
            font-size: 1rem;
            font-weight: 800
        }

        .mob-header .logo span {
            color: var(--yellow)
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
            justify-content: center
        }

        /* Page header */
        .page-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.75rem
        }

        .page-hd h1 {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.3px
        }

        .page-hd p {
            font-size: 0.8rem;
            color: var(--text3);
            margin-top: 2px
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.75rem
        }

        @media(min-width:640px) {
            .stats {
                grid-template-columns: repeat(4, 1fr)
            }
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1rem 1.1rem
        }

        .stat-card .lbl {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text3);
            margin-bottom: 0.4rem
        }

        .stat-card .val {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            letter-spacing: -1px
        }

        .stat-card .val.green {
            color: var(--green)
        }

        .stat-card .val.red {
            color: var(--red)
        }

        .stat-card .val.yellow {
            color: var(--yellow)
        }

        /* Filter bar */
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
            transition: border-color 0.2s
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: var(--accent)
        }

        .filter-bar input::placeholder {
            color: var(--text3)
        }

        .filter-bar select option {
            background: var(--bg2)
        }

        .search-icon-wrap {
            position: relative;
            flex: 1;
            min-width: 160px;
            max-width: 280px
        }

        .search-icon-wrap i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 0.8rem;
            pointer-events: none
        }

        .search-icon-wrap input {
            width: 100%;
            padding-left: 2.1rem
        }

        /* Table */
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            overflow: hidden
        }

        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px
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
            background: var(--surface)
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.12s
        }

        tbody tr:last-child {
            border-bottom: none
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.02)
        }

        tbody td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--text2);
            vertical-align: middle
        }

        tbody td:first-child {
            color: var(--text);
            font-weight: 600
        }

        .empty-row td {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text3)
        }

        .empty-row td i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 0.5rem;
            opacity: 0.4
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.2rem 0.6rem;
            border-radius: 100px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            white-space: nowrap
        }

        .badge-green {
            background: rgba(16, 185, 129, 0.1);
            color: #34D399
        }

        .badge-red {
            background: rgba(239, 68, 68, 0.1);
            color: #F87171
        }

        .badge-yellow {
            background: rgba(245, 158, 11, 0.12);
            color: #FCD34D
        }

        .badge-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #93C5FD
        }

        .badge-gray {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text3)
        }

        /* Buttons */
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
            -webkit-tap-highlight-color: transparent
        }

        .btn-primary {
            background: var(--accent);
            color: #fff
        }

        .btn-primary:hover {
            background: var(--accent-h);
            transform: translateY(-1px)
        }

        .btn-success {
            background: var(--green);
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25)
        }

        .btn-success:hover {
            background: #059669
        }

        .btn-ghost {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text2)
        }

        .btn-ghost:hover {
            border-color: var(--border2);
            color: var(--text)
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #F87171
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.18)
        }

        .btn-warn {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            color: #FCD34D
        }

        .btn-warn:hover {
            background: rgba(245, 158, 11, 0.18)
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem
        }

        .btn-icon {
            padding: 0.4rem 0.5rem
        }

        /* Forms */
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
            letter-spacing: 0.6px;
            color: var(--text2)
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
            width: 100%
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12)
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--text3)
        }

        .form-group select option {
            background: var(--bg2)
        }

        .form-group textarea {
            resize: vertical;
            min-height: 96px;
            line-height: 1.55
        }

        .form-group .hint {
            font-size: 0.72rem;
            color: var(--text3);
            line-height: 1.5
        }

        /* Toggle */
        .toggle-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem
        }

        .toggle-wrap .tgl-lbl {
            font-size: 0.875rem;
            color: var(--text2)
        }

        .toggle {
            position: relative;
            width: 46px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            flex-shrink: 0
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--surface3);
            border: 1px solid var(--border2);
            border-radius: 100px;
            transition: background 0.2s
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
            transition: transform 0.2s, background 0.2s
        }

        .toggle input:checked+.toggle-slider {
            background: var(--accent);
            border-color: var(--accent)
        }

        .toggle input:checked+.toggle-slider::before {
            transform: translateX(20px);
            background: #fff
        }

        /* Modal */
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
            transition: opacity 0.25s
        }

        .modal-backdrop.open {
            opacity: 1;
            pointer-events: all
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
            overflow-y: auto
        }

        .modal-backdrop.open .modal {
            transform: none
        }

        .modal h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.75rem
        }

        .modal p {
            font-size: 0.875rem;
            color: var(--text2);
            line-height: 1.6;
            margin-bottom: 1.25rem
        }

        .modal-actions {
            display: flex;
            gap: 0.6rem;
            justify-content: flex-end
        }

        /* Flash */
        .flash {
            padding: 0.8rem 1.1rem;
            border-radius: var(--r-sm);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .flash.ok {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #34D399
        }

        .flash.erro {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #F87171
        }

        /* Login */
        .login-screen {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--bg)
        }

        .login-box {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 18px;
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 380px
        }

        .login-logo {
            font-size: 1.6rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.2rem
        }

        .login-logo span {
            color: var(--yellow)
        }

        .login-box hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.5rem 0
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
            margin-top: 0.5rem
        }

        .login-btn:hover {
            background: var(--accent-h)
        }

        /* Misc */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.5rem 0
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            color: var(--text3);
            text-decoration: none;
            margin-bottom: 1.25rem;
            transition: color 0.15s
        }

        .back-link:hover {
            color: var(--text)
        }

        .price-preview {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -0.5px
        }

        /* ── Imagem upload ──────────────────────────────────────── */
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
            transition: all 0.2s;
            font-family: inherit
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
            transition: all 0.2s;
            position: relative;
            background: var(--surface2)
        }

        .upload-zone:hover,
        .upload-zone.drag-over {
            border-color: var(--accent);
            background: rgba(59, 130, 246, 0.05)
        }

        .upload-zone input[type=file] {
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

        .upload-zone p {
            font-size: 0.82rem;
            color: var(--text3);
            line-height: 1.5
        }

        .upload-zone strong {
            color: var(--accent)
        }

        .img-preview-wrap {
            margin-top: 0.75rem;
            position: relative;
            display: none
        }

        .img-preview-wrap img {
            width: 100%;
            max-height: 160px;
            object-fit: cover;
            border-radius: var(--r-sm);
            border: 1px solid var(--border)
        }

        .img-preview-wrap .remove-img {
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
            font-size: 0.75rem
        }

        /* ── Seletor de ícones ──────────────────────────────────── */
        .icon-picker-wrap {
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: var(--r);
            overflow: hidden
        }

        .icon-selected-preview {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.15s;
            user-select: none
        }

        .icon-selected-preview:hover {
            background: var(--surface3)
        }

        .icon-selected-preview .ico-big {
            font-size: 1.4rem;
            color: var(--accent);
            width: 32px;
            text-align: center
        }

        .icon-selected-preview .ico-info {
            flex: 1
        }

        .icon-selected-preview .ico-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text)
        }

        .icon-selected-preview .ico-class {
            font-size: 0.7rem;
            color: var(--text3);
            font-family: monospace
        }

        .icon-selected-preview .ico-chevron {
            color: var(--text3);
            font-size: 0.8rem;
            transition: transform 0.2s
        }

        .icon-picker-wrap.open .ico-chevron {
            transform: rotate(180deg)
        }

        .icon-picker-panel {
            display: none;
            border-top: 1px solid var(--border)
        }

        .icon-picker-wrap.open .icon-picker-panel {
            display: block
        }

        .icon-search {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid var(--border)
        }

        .icon-search input {
            width: 100%;
            background: var(--surface3);
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
            outline: none
        }

        .icon-search input:focus {
            border-color: var(--accent)
        }

        .icon-search input::placeholder {
            color: var(--text3)
        }

        .icon-groups {
            max-height: 320px;
            overflow-y: auto;
            padding: 0.5rem 0
        }

        .icon-group-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text3);
            padding: 0.6rem 0.9rem 0.3rem;
            display: block
        }

        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(52px, 1fr));
            gap: 4px;
            padding: 0 0.5rem 0.5rem
        }

        .icon-opt {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 0.5rem 0.25rem;
            border-radius: var(--r-sm);
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s;
            background: transparent;
            font-family: inherit
        }

        .icon-opt:hover {
            background: var(--surface3);
            border-color: var(--border)
        }

        .icon-opt.selected {
            background: rgba(59, 130, 246, 0.12);
            border-color: var(--accent)
        }

        .icon-opt i {
            font-size: 1.15rem;
            color: var(--text2)
        }

        .icon-opt.selected i {
            color: var(--accent)
        }

        .icon-opt span {
            font-size: 0.58rem;
            color: var(--text3);
            text-align: center;
            line-height: 1.2;
            max-width: 48px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .icon-opt.hidden-icon {
            display: none
        }
    </style>
</head>

<body>

    <?php if (!isLogged()): ?>
        <!-- ════════════ LOGIN ════════════ -->
        <div class="login-screen">
            <div class="login-box">
                <p class="login-logo">didi<span>contas</span></p>
                <p style="text-align:center;font-size:0.78rem;color:var(--text3);margin-top:4px;">Painel Administrativo</p>
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
                    <button type="submit" class="login-btn"><i class="fa-solid fa-right-to-bracket" style="margin-right:6px;"></i> Entrar no painel</button>
                </form>
                <p style="text-align:center;margin-top:1.5rem;font-size:0.73rem;color:var(--text3);">
                    <a href="/geral/index.php" style="color:var(--accent);text-decoration:none;">← Voltar para a vitrine</a>
                </p>
            </div>
        </div>

    <?php else: ?>
        <!-- ════════════ APP ════════════ -->

        <div class="mob-header">
            <span class="logo">didi<span>contas</span></span>
            <button class="mob-menu-btn" onclick="toggleSidebar()" aria-label="Menu">
                <i class="fa-solid fa-bars" id="menuIcon"></i>
            </button>
        </div>

        <div class="layout">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo">didi<span>contas</span><small>Painel Admin</small></div>
                <p class="nav-section">Catálogo</p>
                <a href="?page=produtos" class="nav-item <?= $page === 'produtos'  ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i> Produtos</a>
                <a href="?page=categorias" class="nav-item <?= $page === 'categorias' ? 'active' : '' ?>"><i class="fa-solid fa-layer-group"></i> Categorias</a>
                <p class="nav-section" style="margin-top:0.5rem;">Atalhos</p>
                <a href="?page=produtos&novo=1" class="nav-item"><i class="fa-solid fa-plus"></i> Novo produto</a>
                <a href="/geral/index.php" target="_blank" class="nav-item"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver vitrine</a>
                <div class="sidebar-footer">
                    <a href="?action=logout" class="nav-item" style="color:var(--text3);"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
                </div>
            </aside>

            <main class="content">
                <?php if ($flash): ?>
                    <div class="flash <?= $flash['type'] ?>">
                        <i class="fa-solid <?= $flash['type'] === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <?= htmlspecialchars($flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'produtos'): ?>
                    <?php $isNovo = $isEditar = false;
                    $isNovo = isset($_GET['novo']);
                    $isEditar = isset($editProd) && $editProd; ?>

                    <?php if ($isNovo || $isEditar): ?>
                        <!-- ════ FORMULÁRIO PRODUTO ════ -->
                        <a href="?page=produtos" class="back-link"><i class="fa-solid fa-arrow-left"></i> Voltar para produtos</a>
                        <div class="page-hd">
                            <div>
                                <h1><?= $isEditar ? 'Editar Produto' : 'Novo Produto' ?></h1>
                                <p><?= $isEditar ? 'Altere os dados e salve.' : 'Preencha os campos e publique na vitrine.' ?></p>
                            </div>
                        </div>

                        <form method="POST" action="?page=produtos" id="formProduto" enctype="multipart/form-data">
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
                                        <span class="hint">Aparece no modal quando o cliente clica em "Saber mais".</span>
                                    </div>

                                    <!-- PREÇO COM MÁSCARA -->
                                    <div class="form-group">
                                        <label>Preço (R$) *</label>
                                        <input type="text" name="preco" id="precoInput" required
                                            placeholder="R$ 0,00"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            value="<?= $isEditar ? 'R$ ' . number_format($editProd['preco'], 2, ',', '.') : '' ?>"
                                            oninput="mascaraPreco(this)">
                                        <span class="hint">Digite apenas números — a máscara é aplicada automaticamente.</span>
                                    </div>

                                    <div class="form-group" style="align-items:flex-start;">
                                        <label>Preview do preço</label>
                                        <p class="price-preview" id="precoPreview"><?= $isEditar ? 'R$ ' . number_format($editProd['preco'], 2, ',', '.') : 'R$ 0,00' ?></p>
                                    </div>

                                    <div class="form-group">
                                        <label>Ciclo</label>
                                        <select name="ciclo">
                                            <option value="mensal" <?= ($editProd['ciclo'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                            <option value="anual" <?= ($editProd['ciclo'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Categoria</label>
                                        <select name="categoria_id">
                                            <option value="">Sem categoria</option>
                                            <?php foreach ($categorias as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" <?= ($editProd['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- IMAGEM: URL ou UPLOAD -->
                                    <div class="form-group full">
                                        <label>Imagem do produto (opcional)</label>

                                        <?php $imgAtual = $editProd['imagem_url'] ?? ''; ?>

                                        <div class="img-tabs">
                                            <button type="button" class="img-tab active" onclick="imgTab(this,'tab-url')">
                                                <i class="fa-solid fa-link" style="margin-right:4px;"></i> Link (URL)
                                            </button>
                                            <button type="button" class="img-tab" onclick="imgTab(this,'tab-upload')">
                                                <i class="fa-solid fa-upload" style="margin-right:4px;"></i> Enviar arquivo
                                            </button>
                                        </div>

                                        <!-- Painel URL -->
                                        <div class="img-panel active" id="tab-url">
                                            <input type="url" name="imagem_url" id="imgUrlInput"
                                                placeholder="https://exemplo.com/imagem.png"
                                                value="<?= htmlspecialchars($imgAtual) ?>"
                                                oninput="previewUrl(this.value)">
                                            <span class="hint">Cole o link de qualquer imagem pública na internet.</span>
                                        </div>

                                        <!-- Painel Upload -->
                                        <div class="img-panel" id="tab-upload">
                                            <div class="upload-zone" id="uploadZone">
                                                <input type="file" name="imagem_arquivo" id="imgFileInput"
                                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                                    onchange="previewFile(this)">
                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                <p><strong>Clique ou arraste</strong> uma imagem aqui<br>JPG, PNG, WEBP ou GIF · Máximo 3MB</p>
                                            </div>
                                        </div>

                                        <!-- Preview compartilhado -->
                                        <div class="img-preview-wrap" id="imgPreviewWrap">
                                            <img id="imgPreview" src="<?= htmlspecialchars($imgAtual) ?>" alt="Preview">
                                            <button type="button" class="remove-img" onclick="removerImagem()" title="Remover imagem">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="remover_imagem" id="removerImagemInput" value="">

                                        <?php if ($imgAtual && !str_starts_with($imgAtual, 'http')): ?>
                                            <span class="hint" style="margin-top:4px;color:var(--yellow);">
                                                <i class="fa-solid fa-image" style="margin-right:3px;"></i>
                                                Imagem atual: arquivo salvo no servidor. Envie um novo para substituir.
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Toggles -->
                                    <div class="form-group">
                                        <label>Status</label>
                                        <div class="toggle-wrap" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:0.7rem 0.9rem;">
                                            <span class="tgl-lbl">Visível na vitrine</span>
                                            <label class="toggle">
                                                <input type="checkbox" name="status" value="ativo" <?= ($editProd['status'] ?? 'ativo') === 'ativo' ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Destaque</label>
                                        <div class="toggle-wrap" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:0.7rem 0.9rem;">
                                            <span class="tgl-lbl">Marcar como destaque</span>
                                            <label class="toggle">
                                                <input type="checkbox" name="destaque" value="1" <?= !empty($editProd['destaque']) ? 'checked' : '' ?>>
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

                    <?php else: ?>
                        <!-- ════ LISTA PRODUTOS ════ -->
                        <div class="page-hd">
                            <div>
                                <h1>Produtos</h1>
                                <p><?= $stats['total'] ?> cadastrados · <?= $stats['ativos'] ?> ativos</p>
                            </div>
                            <a href="?page=produtos&novo=1" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Novo produto</a>
                        </div>

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
                                        <option value="<?= $cat['id'] ?>" <?= ($filtCat ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="status" style="min-width:130px;">
                                    <option value="">Qualquer status</option>
                                    <option value="ativo" <?= ($filtStatus ?? '') === 'ativo'  ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inativo" <?= ($filtStatus ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
                                <?php if (!empty($_GET['busca']) || !empty($_GET['cat']) || !empty($_GET['status'])): ?>
                                    <a href="?page=produtos" class="btn btn-ghost btn-sm">Limpar</a>
                                <?php endif; ?>
                            </div>
                        </form>

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
                                                <td colspan="8"><i class="fa-solid fa-box-open"></i> Nenhum produto encontrado.</td>
                                            </tr>
                                            <?php else: foreach ($produtos as $p): ?>
                                                <tr>
                                                    <td style="color:var(--text3);font-weight:400;font-size:0.78rem;"><?= $p['id'] ?></td>
                                                    <td>
                                                        <div style="display:flex;align-items:center;gap:8px;">
                                                            <?php if ($p['imagem_url']): ?>
                                                                <img src="<?= htmlspecialchars($p['imagem_url']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;border-radius:6px;border:1px solid var(--border);flex-shrink:0;">
                                                            <?php endif; ?>
                                                            <span style="font-weight:700;color:var(--text);"><?= htmlspecialchars($p['titulo']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?= $p['cat_nome'] ? '<span class="badge badge-blue">' . htmlspecialchars($p['cat_nome']) . '</span>' : '<span class="badge badge-gray">—</span>' ?></td>
                                                    <td style="font-weight:700;color:var(--text);">R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                                                    <td><span class="badge badge-gray"><?= $p['ciclo'] ?></span></td>
                                                    <td><span class="badge <?= $p['status'] === 'ativo' ? 'badge-green' : 'badge-red' ?>"><i class="fa-solid fa-circle" style="font-size:0.45rem;"></i> <?= $p['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
                                                    <td><?= $p['destaque'] ? '<span class="badge badge-yellow"><i class="fa-solid fa-star" style="font-size:0.65rem;"></i> Sim</span>' : '<span class="badge badge-gray">—</span>' ?></td>
                                                    <td>
                                                        <div style="display:flex;gap:5px;justify-content:flex-end;">
                                                            <a href="?page=produtos&editar=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                                            <a href="?action=toggle_status&id=<?= $p['id'] ?>&page=produtos" class="btn btn-warn btn-sm btn-icon" title="<?= $p['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?>"><i class="fa-solid <?= $p['status'] === 'ativo' ? 'fa-eye-slash' : 'fa-eye' ?>"></i></a>
                                                            <button onclick="confirmarExclusao(<?= $p['id'] ?>,'<?= htmlspecialchars(addslashes($p['titulo'])) ?>')" class="btn btn-danger btn-sm btn-icon" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'categorias'): ?>
                    <!-- ════ CATEGORIAS ════ -->
                    <?php
                    $editCat = null;
                    if (isset($_GET['editar'])) {
                        $s = $pdo->prepare("SELECT * FROM categorias WHERE id=?");
                        $s->execute([intval($_GET['editar'])]);
                        $editCat = $s->fetch(PDO::FETCH_ASSOC);
                    }
                    $cntStmt = $pdo->query("SELECT categoria_id,COUNT(*) as total FROM produtos GROUP BY categoria_id");
                    $cntMap  = [];
                    foreach ($cntStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $cntMap[$row['categoria_id']] = $row['total'];
                    $iconeAtual = $editCat['icone'] ?? 'fa-solid fa-tag';
                    ?>
                    <div class="page-hd">
                        <div>
                            <h1>Categorias</h1>
                            <p><?= count($categorias) ?> categorias cadastradas</p>
                        </div>
                    </div>

                    <!-- Formulário de categoria -->
                    <div class="form-card" style="margin-bottom:1.5rem;">
                        <p style="font-size:0.85rem;font-weight:700;margin-bottom:1rem;color:var(--text2);">
                            <?= $editCat ? 'Editando: <strong style="color:var(--text)">' . htmlspecialchars($editCat['nome']) . '</strong>' : '➕ Nova categoria' ?>
                        </p>
                        <form method="POST" action="?page=categorias">
                            <input type="hidden" name="action" value="salvar_categoria">
                            <input type="hidden" name="id" value="<?= $editCat ? intval($editCat['id']) : 0 ?>">
                            <input type="hidden" name="icone" id="iconeHiddenInput" value="<?= htmlspecialchars($iconeAtual) ?>">

                            <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
                                <div class="form-group" style="flex:1;min-width:180px;">
                                    <label>Nome da categoria *</label>
                                    <input type="text" name="nome" placeholder="ex: Inteligência Artificial" required
                                        value="<?= htmlspecialchars($editCat['nome'] ?? '') ?>" autofocus>
                                </div>
                                <div style="display:flex;gap:0.5rem;padding-bottom:1px;">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $editCat ? 'Salvar' : 'Criar' ?></button>
                                    <?php if ($editCat): ?><a href="?page=categorias" class="btn btn-ghost">Cancelar</a><?php endif; ?>
                                </div>
                            </div>

                            <!-- Seletor visual de ícones -->
                            <div style="margin-top:1rem;">
                                <label style="display:block;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text2);margin-bottom:0.5rem;">Ícone da categoria</label>

                                <div class="icon-picker-wrap" id="iconPickerWrap">
                                    <!-- Preview do ícone selecionado -->
                                    <div class="icon-selected-preview" onclick="toggleIconPicker()">
                                        <i class="<?= htmlspecialchars($iconeAtual) ?> ico-big" id="iconPreviewEl"></i>
                                        <div class="ico-info">
                                            <div class="ico-name" id="iconPreviewName">Clique para escolher um ícone</div>
                                            <div class="ico-class" id="iconPreviewClass"><?= htmlspecialchars($iconeAtual) ?></div>
                                        </div>
                                        <i class="fa-solid fa-chevron-down ico-chevron"></i>
                                    </div>

                                    <!-- Painel do picker -->
                                    <div class="icon-picker-panel">
                                        <div class="icon-search">
                                            <input type="text" placeholder="Buscar ícone..." id="iconSearchInput" oninput="filtrarIcones(this.value)">
                                        </div>
                                        <div class="icon-groups" id="iconGroupsContainer">
                                            <?php foreach ($ICONES as $grupo => $icones): ?>
                                                <span class="icon-group-label" data-group="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars($grupo) ?></span>
                                                <div class="icon-grid" data-group="<?= htmlspecialchars($grupo) ?>">
                                                    <?php foreach ($icones as [$classe, $nome]): ?>
                                                        <button type="button"
                                                            class="icon-opt <?= $iconeAtual === $classe ? 'selected' : '' ?>"
                                                            data-class="<?= htmlspecialchars($classe) ?>"
                                                            data-name="<?= htmlspecialchars($nome) ?>"
                                                            onclick="selecionarIcone(this)">
                                                            <i class="<?= htmlspecialchars($classe) ?>"></i>
                                                            <span><?= htmlspecialchars($nome) ?></span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>

                    <!-- Tabela de categorias -->
                    <div class="table-wrap">
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ícone</th>
                                        <th>Nome</th>
                                        <th>Produtos</th>
                                        <th style="text-align:right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categorias)): ?>
                                        <tr class="empty-row">
                                            <td colspan="4"><i class="fa-solid fa-layer-group"></i> Nenhuma categoria cadastrada ainda.</td>
                                        </tr>
                                        <?php else: foreach ($categorias as $cat): ?>
                                            <tr>
                                                <td style="width:50px;">
                                                    <i class="<?= htmlspecialchars($cat['icone'] ?? 'fa-solid fa-tag') ?>" style="font-size:1.2rem;color:var(--accent);"></i>
                                                </td>
                                                <td style="font-weight:700;"><?= htmlspecialchars($cat['nome']) ?></td>
                                                <td><span class="badge <?= ($cntMap[$cat['id']] ?? 0) > 0 ? 'badge-blue' : 'badge-gray' ?>"><?= $cntMap[$cat['id']] ?? 0 ?> produto<?= ($cntMap[$cat['id']] ?? 0) !== 1 ? 's' : '' ?></span></td>
                                                <td>
                                                    <div style="display:flex;gap:5px;justify-content:flex-end;">
                                                        <a href="?page=categorias&editar=<?= $cat['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                                        <button onclick="confirmarExclusaoCat(<?= $cat['id'] ?>,'<?= htmlspecialchars(addslashes($cat['nome'])) ?>')" class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>

    <!-- Modal de confirmação de exclusão -->
    <div class="modal-backdrop" id="modalConfirm">
        <div class="modal">
            <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--red);margin-right:6px;"></i> Confirmar exclusão</h3>
            <p id="confirmMsg">Tem certeza que deseja remover este item? Essa ação não pode ser desfeita.</p>
            <div class="modal-actions">
                <button class="btn btn-ghost" onclick="fecharConfirm()">Cancelar</button>
                <a href="#" id="confirmLink" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Sim, excluir</a>
            </div>
        </div>
    </div>

    <script>
        /* ════════════════════════════════════════════════════════
   MÁSCARA DE PREÇO
════════════════════════════════════════════════════════ */
        function mascaraPreco(input) {
            let val = input.value.replace(/\D/g, ''); // só dígitos
            if (!val) {
                input.value = '';
                atualizarPreview('');
                return;
            }
            let num = parseInt(val, 10) / 100; // divide por 100 → centavos
            let fmt = 'R$ ' + num.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            input.value = fmt;
            atualizarPreview(fmt);
        }

        function atualizarPreview(fmt) {
            const prev = document.getElementById('precoPreview');
            if (!prev) return;
            prev.textContent = fmt || 'R$ 0,00';
        }
        // Inicializa preview se já tem valor (modo edição)
        document.addEventListener('DOMContentLoaded', () => {
            const inp = document.getElementById('precoInput');
            if (inp && inp.value) atualizarPreview(inp.value);
        });

        /* ════════════════════════════════════════════════════════
           IMAGEM: ABAS URL / UPLOAD
        ════════════════════════════════════════════════════════ */
        function imgTab(btn, panelId) {
            document.querySelectorAll('.img-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.img-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(panelId).classList.add('active');
            // Se mudou para URL, limpa o arquivo
            if (panelId === 'tab-url') {
                const fi = document.getElementById('imgFileInput');
                if (fi) fi.value = '';
            }
        }

        function previewUrl(url) {
            const wrap = document.getElementById('imgPreviewWrap');
            const img = document.getElementById('imgPreview');
            if (!wrap || !img) return;
            if (url && url.trim()) {
                img.src = url;
                wrap.style.display = 'block';
                document.getElementById('removerImagemInput').value = '';
            } else {
                wrap.style.display = 'none';
            }
        }

        function previewFile(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > 3 * 1024 * 1024) {
                alert('Arquivo muito grande. Máximo: 3MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                const wrap = document.getElementById('imgPreviewWrap');
                const img = document.getElementById('imgPreview');
                img.src = e.target.result;
                wrap.style.display = 'block';
                // Limpa URL se enviou arquivo
                const urlInp = document.getElementById('imgUrlInput');
                if (urlInp) urlInp.value = '';
            };
            reader.readAsDataURL(file);
        }

        function removerImagem() {
            document.getElementById('imgPreviewWrap').style.display = 'none';
            document.getElementById('imgPreview').src = '';
            document.getElementById('removerImagemInput').value = '1';
            const urlInp = document.getElementById('imgUrlInput');
            if (urlInp) urlInp.value = '';
            const fileInp = document.getElementById('imgFileInput');
            if (fileInp) fileInp.value = '';
        }
        // Drag & drop na zona de upload
        document.addEventListener('DOMContentLoaded', () => {
            const zone = document.getElementById('uploadZone');
            if (!zone) return;
            zone.addEventListener('dragover', e => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                const file = e.dataTransfer.files[0];
                if (file && file.type.startsWith('image/')) {
                    const inp = document.getElementById('imgFileInput');
                    // DataTransfer API
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    inp.files = dt.files;
                    previewFile(inp);
                }
            });
            // Inicializa preview de imagem atual (edição)
            const imgSrc = document.getElementById('imgPreview')?.src;
            if (imgSrc && imgSrc !== window.location.href) {
                document.getElementById('imgPreviewWrap').style.display = 'block';
            }
        });

        /* ════════════════════════════════════════════════════════
           SELETOR DE ÍCONES
        ════════════════════════════════════════════════════════ */
        function toggleIconPicker() {
            document.getElementById('iconPickerWrap').classList.toggle('open');
        }

        function selecionarIcone(btn) {
            const classe = btn.dataset.class;
            const nome = btn.dataset.name;
            // Atualiza hidden input
            document.getElementById('iconeHiddenInput').value = classe;
            // Atualiza preview
            const previewEl = document.getElementById('iconPreviewEl');
            previewEl.className = classe + ' ico-big';
            document.getElementById('iconPreviewName').textContent = nome;
            document.getElementById('iconPreviewClass').textContent = classe;
            // Marca selecionado
            document.querySelectorAll('.icon-opt').forEach(o => o.classList.remove('selected'));
            btn.classList.add('selected');
            // Fecha picker
            document.getElementById('iconPickerWrap').classList.remove('open');
        }

        function filtrarIcones(q) {
            q = q.toLowerCase().trim();
            const opts = document.querySelectorAll('.icon-opt');
            const labels = document.querySelectorAll('.icon-group-label');
            const grids = document.querySelectorAll('.icon-grid');

            opts.forEach(opt => {
                const match = !q || opt.dataset.name.toLowerCase().includes(q) || opt.dataset.class.toLowerCase().includes(q);
                opt.classList.toggle('hidden-icon', !match);
            });

            // Esconde grupos vazios
            grids.forEach((grid, i) => {
                const visivel = Array.from(grid.querySelectorAll('.icon-opt')).some(o => !o.classList.contains('hidden-icon'));
                grid.style.display = visivel ? '' : 'none';
                if (labels[i]) labels[i].style.display = visivel ? '' : 'none';
            });
        }
        // Fecha picker clicando fora
        document.addEventListener('click', e => {
            const wrap = document.getElementById('iconPickerWrap');
            if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
        });

        /* ════════════════════════════════════════════════════════
           SIDEBAR MOBILE
        ════════════════════════════════════════════════════════ */
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            const i = document.getElementById('menuIcon');
            s.classList.toggle('open');
            i.className = s.classList.contains('open') ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        }
        document.addEventListener('click', e => {
            const sidebar = document.getElementById('sidebar');
            const btn = document.querySelector('.mob-menu-btn');
            if (sidebar && btn && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !btn.contains(e.target)) {
                sidebar.classList.remove('open');
                document.getElementById('menuIcon').className = 'fa-solid fa-bars';
            }
        });

        /* ════════════════════════════════════════════════════════
           MODAL DE CONFIRMAÇÃO
        ════════════════════════════════════════════════════════ */
        function confirmarExclusao(id, titulo) {
            document.getElementById('confirmMsg').textContent = 'Tem certeza que deseja remover o produto "' + titulo + '"? Essa ação não pode ser desfeita.';
            document.getElementById('confirmLink').href = '?action=excluir_produto&id=' + id + '&page=produtos';
            document.getElementById('modalConfirm').classList.add('open');
        }

        function confirmarExclusaoCat(id, nome) {
            document.getElementById('confirmMsg').textContent = 'Tem certeza que deseja remover a categoria "' + nome + '"? Ela só pode ser removida se não tiver produtos vinculados.';
            document.getElementById('confirmLink').href = '?action=excluir_categoria&id=' + id + '&page=categorias';
            document.getElementById('modalConfirm').classList.add('open');
        }

        function fecharConfirm() {
            document.getElementById('modalConfirm').classList.remove('open');
        }
        document.getElementById('modalConfirm')?.addEventListener('click', e => {
            if (e.target.id === 'modalConfirm') fecharConfirm();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') fecharConfirm();
        });

        /* Flash auto-dismiss */
        const flash = document.querySelector('.flash');
        if (flash) setTimeout(() => {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    </script>
</body>

</html>