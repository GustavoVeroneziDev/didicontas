<?php
session_start();

$conexao_path = __DIR__ . '/../../../config/conexao.php';
if (!file_exists($conexao_path)) {
    die('<div class="alert alert-danger m-4">ERRO: conexao.php não encontrado.</div>');
}
require_once $conexao_path;

define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('UPLOAD_URL',  '/geral/public/admin/uploads/');
define('MAX_IMG_SIZE', 3 * 1024 * 1024);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    file_put_contents(UPLOAD_DIR . '.htaccess', "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh\n");
}

/* ─── CREDENCIAIS ─── */
define('ADMIN_USER', 'didi');
define('ADMIN_PASS', '$2y$10$TROQUE_ESTE_HASH');
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
function flash(string $msg, string $type = 'success'): void
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
        flash('Erro no upload.', 'danger');
        return null;
    }
    if ($file['size'] > MAX_IMG_SIZE) {
        flash('Imagem muito grande. Máximo: 3MB.', 'danger');
        return null;
    }
    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
    if (!in_array($mime, $allowed)) {
        flash('Formato não suportado.', 'danger');
        return null;
    }
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'][$mime];
    $name = uniqid('img_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        flash('Falha ao salvar.', 'danger');
        return null;
    }
    return UPLOAD_URL . $name;
}

$page   = $_GET['page']   ?? 'produtos';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ═══ AÇÕES BACKEND ═════════════════════════════════════════ */
if ($action === 'login') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['senha'] ?? '';
    if (($user === ADMIN_USER) && (password_verify($pass, ADMIN_PASS) || $pass === ADMIN_PASS_DEMO)) {
        $_SESSION['dc_admin'] = true;
        header('Location: ?page=produtos');
        exit;
    }
    flash('Usuário ou senha inválidos.', 'danger');
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

    $preco_str = str_replace(['R$', ' '], '', $_POST['preco'] ?? '0');
    $preco_str = str_replace('.', '', $preco_str);
    $preco_str = str_replace(',', '.', $preco_str);
    $preco     = floatval($preco_str);

    $ciclo    = in_array($_POST['ciclo'] ?? '', ['mensal', 'anual']) ? $_POST['ciclo'] : 'mensal';
    $cat_id   = intval($_POST['categoria_id'] ?? 0);
    $status   = ($_POST['status'] ?? '') === 'ativo' ? 'ativo' : 'inativo';
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $img_pos  = trim($_POST['imagem_pos'] ?? 'center center');

    $remover    = ($_POST['remover_imagem'] ?? '') === '1';
    $img_upload = salvarImagem('imagem_arquivo');
    $img_url    = trim($_POST['imagem_url'] ?? ''); // Pode vir do input manual ou da Galeria
    $img_base64 = $_POST['imagem_base64'] ?? '';
    if ($remover) {
        $img_final = null;
    } elseif (!empty($img_base64)) {
        // Salva a imagem recortada em Base64
        list($type, $data) = explode(';', $img_base64);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $name = uniqid('img_crop_', true) . '.png';
        file_put_contents(UPLOAD_DIR . $name, $data);
        $img_final = UPLOAD_URL . $name;
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
        flash('O título é obrigatório.', 'danger');
        header('Location: ?page=produtos');
        exit;
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE produtos SET titulo=?,descricao=?,preco=?,ciclo=?,categoria_id=?,status=?,destaque=?,imagem_url=? WHERE id=?")->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img_final, $id]);
        flash('Produto atualizado.', 'success');
    } else {
        $pdo->prepare("INSERT INTO produtos (titulo,descricao,preco,ciclo,categoria_id,status,destaque,imagem_url) VALUES (?,?,?,?,?,?,?,?)")->execute([$titulo, $desc, $preco, $ciclo, $cat_id ?: null, $status, $destaque, $img_final]);
        flash('Produto criado.', 'success');
    }
    header('Location: ?page=produtos');
    exit;
}

if ($action === 'excluir_produto') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM produtos WHERE id=?")->execute([$id]);
        flash('Produto removido.', 'success');
    }
    header('Location: ?page=produtos');
    exit;
}
if ($action === 'toggle_status') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE produtos SET status=IF(status='ativo','inativo','ativo') WHERE id=?")->execute([$id]);
        flash('Status atualizado.', 'success');
    }
    header('Location: ?page=produtos');
    exit;
}
if ($action === 'toggle_destaque') {
    requireLogin();
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        // Inverte o valor: se for 1 vira 0, se for 0 vira 1
        $pdo->prepare("UPDATE produtos SET destaque=IF(destaque=1,0,1) WHERE id=?")->execute([$id]);
        flash('Destaque atualizado.', 'success');
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
        flash('O nome é obrigatório.', 'danger');
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
            flash('Categoria possui produtos vinculados.', 'danger');
        } else {
            $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$id]);
            flash('Categoria removida.');
        }
    }
    header('Location: ?page=categorias');
    exit;
}

/* ═══ DADOS DA VIEW ══════════════════════════════════════ */
if (isLogged()) {
    $categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Busca Inteligente: Todas as imagens únicas já usadas no banco de dados para a Galeria
    $stmt_galeria = $pdo->query("SELECT DISTINCT imagem_url FROM produtos WHERE imagem_url IS NOT NULL AND imagem_url != ''");
    $imagens_galeria = $stmt_galeria->fetchAll(PDO::FETCH_COLUMN);

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

$ICONES = [
    'Streaming & Mídia' => [['fa-brands fa-youtube', 'YouTube'], ['fa-solid fa-film', 'Cinema'], ['fa-solid fa-tv', 'TV'], ['fa-solid fa-music', 'Música'], ['fa-solid fa-gamepad', 'Games']],
    'Inteligência Artificial' => [['fa-solid fa-robot', 'Robô / IA'], ['fa-solid fa-brain', 'Cérebro'], ['fa-solid fa-microchip', 'Microchip'], ['fa-solid fa-comment-dots', 'Chat AI'], ['fa-solid fa-bolt', 'Rápido']],
    'Trabalho & Design' => [['fa-solid fa-briefcase', 'Trabalho'], ['fa-solid fa-palette', 'Paleta'], ['fa-solid fa-image', 'Imagem'], ['fa-solid fa-cloud', 'Cloud'], ['fa-solid fa-file-invoice-dollar', 'Fatura']],
    'Geral' => [['fa-solid fa-star', 'Estrela'], ['fa-solid fa-tag', 'Tag'], ['fa-solid fa-crown', 'Coroa'], ['fa-solid fa-shield-halved', 'Escudo'], ['fa-solid fa-award', 'Prêmio']],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Didi Contas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="logoconfig.ico" type="image/x-icon">
    <style>
        /* CSS Extra para a Galeria */
        .galeria-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .galeria-item {
            width: 100%;
            height: 70px;
            object-fit: contain;
            background: var(--surface);
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            padding: 4px;
        }

        .galeria-item:hover {
            border-color: var(--accent);
            transform: scale(1.05);
        }

        .galeria-item.selected {
            border-color: var(--green);
            background: rgba(16, 185, 129, 0.1);
        }
    </style>
</head>

<body>

    <?php if (!isLogged()): ?>
        <div class="d-flex align-items-center justify-content-center vh-100">
            <div class="card p-4 shadow-lg" style="width: 100%; max-width: 400px;">
                <div class="text-center mb-4">
                    <h2 class="fw-bold mb-0">didi<span style="color: var(--yellow);">contas</span></h2><small class="text-muted">Painel Administrativo</small>
                </div>
                <?php if ($flash): ?><div class="alert alert-<?= $flash['type'] ?> py-2 text-center small"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
                <form method="POST" action="?page=login">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3"><label class="form-label">Usuário</label><input type="text" name="usuario" class="form-control" required autofocus></div>
                    <div class="mb-4"><label class="form-label">Senha</label><input type="password" name="senha" class="form-control" required></div>
                    <button type="submit" class="btn btn-primary w-100 justify-content-center py-2"><i class="fa-solid fa-right-to-bracket"></i> Entrar no painel</button>
                </form>
                <div class="text-center mt-4"><a href="/geral/index.php" class="text-decoration-none small text-muted"><i class="fa-solid fa-arrow-left"></i> Voltar para a vitrine</a></div>
            </div>
        </div>

    <?php else: ?>
        <div class="wrapper">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-brand d-flex justify-content-between align-items-center">
                    <div>didi<span>contas</span></div><button class="btn btn-sm btn-outline-light d-md-none border-0" onclick="toggleMenu()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="nav flex-column nav-pills mt-3 flex-grow-1">
                    <div class="text-uppercase text-muted fw-bold small px-4 mb-2 mt-2" style="font-size: 0.65rem;">Catálogo</div>
                    <a class="nav-link <?= $page === 'produtos' ? 'active' : '' ?>" href="?page=produtos"><i class="fa-solid fa-box-open"></i> Produtos</a>
                    <a class="nav-link <?= $page === 'categorias' ? 'active' : '' ?>" href="?page=categorias"><i class="fa-solid fa-layer-group"></i> Categorias</a>
                    <div class="text-uppercase text-muted fw-bold small px-4 mb-2 mt-4" style="font-size: 0.65rem;">Mídia</div>
                    <a class="nav-link" href="galeria.php"><i class="fa-solid fa-images"></i> Galeria</a>
                    <div class="text-uppercase text-muted fw-bold small px-4 mb-2 mt-4" style="font-size: 0.65rem;">Ações</div>
                    <a class="nav-link" href="?page=produtos&novo=1"><i class="fa-solid fa-plus"></i> Novo Produto</a>
                    <a class="nav-link" href="/geral/index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver Vitrine</a>
                </div>
                <div class="p-3 border-top" style="border-color: var(--border) !important;"><a class="nav-link text-danger" href="?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Sair do Painel</a></div>
            </aside>

            <div class="content-area">
                <div class="d-md-none bg-dark border-bottom p-3 d-flex justify-content-between align-items-center" style="border-color: var(--border) !important;">
                    <div class="fw-bold fs-5">didi<span style="color: var(--yellow);">contas</span></div><button class="btn btn-outline-light border-0" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                </div>

                <main class="container-fluid p-3 p-md-4 max-w-100">
                    <?php if ($flash): ?><div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($flash['msg']) ?><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

                    <?php if ($page === 'produtos'): ?>
                        <?php $isNovo = isset($_GET['novo']);
                        $isEditar = isset($editProd) && $editProd; ?>

                        <?php if ($isNovo || $isEditar): ?>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h2 class="fw-bold mb-1"><?= $isEditar ? 'Editar Produto' : 'Novo Produto' ?></h2>
                                </div>
                                <a href="?page=produtos" class="btn btn-outline-light"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
                            </div>

                            <form method="POST" action="?page=produtos" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="salvar_produto">
                                <input type="hidden" name="id" value="<?= $isEditar ? intval($editProd['id']) : 0 ?>">

                                <div class="row g-4">
                                    <div class="col-lg-8">
                                        <div class="card p-4">
                                            <div class="row g-3">
                                                <div class="col-12"><label class="form-label">Título do Produto *</label><input type="text" name="titulo" id="inpTitulo" class="form-control" required value="<?= htmlspecialchars($editProd['titulo'] ?? '') ?>"></div>
                                                <div class="col-md-6"><label class="form-label">Preço (R$) *</label><input type="text" name="preco" id="inpPreco" class="form-control" required placeholder="R$ 0,00" value="<?= $isEditar ? 'R$ ' . number_format($editProd['preco'], 2, ',', '.') : '' ?>" oninput="mascaraPreco(this)"></div>
                                                <div class="col-md-6"><label class="form-label">Ciclo</label><select name="ciclo" id="inpCiclo" class="form-select">
                                                        <option value="mensal" <?= ($editProd['ciclo'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                                        <option value="anual" <?= ($editProd['ciclo'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option>
                                                    </select></div>
                                                <div class="col-12"><label class="form-label">Categoria</label><select name="categoria_id" id="inpCat" class="form-select">
                                                        <option value="" data-nome="Geral" data-icone="fa-solid fa-tag">Sem categoria</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>" data-nome="<?= htmlspecialchars($cat['nome']) ?>" data-icone="<?= htmlspecialchars($cat['icone']) ?>" <?= ($editProd['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?>
                                                    </select></div>
                                                <div class="col-12"><label class="form-label">Descrição</label><textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($editProd['descricao'] ?? '') ?></textarea></div>

                                                <!-- SEÇÃO DA IMAGEM E GALERIA -->
                                                <div class="col-12 mt-4">
                                                    <div class="p-3 rounded" style="background: var(--surface2); border: 1px solid var(--border);">
                                                        <label class="form-label text-white mb-3"><i class="fa-solid fa-image text-primary me-1"></i> Imagem do Produto</label>
                                                        <?php $imgAtual = $editProd['imagem_url'] ?? ''; ?>

                                                        <!-- ABAS ATUALIZADAS COM A GALERIA -->
                                                        <ul class="nav nav-pills mb-3" id="imgTabs">
                                                            <li class="nav-item">
                                                                <button class="nav-link px-3 py-1 active" type="button" data-bs-toggle="pill" data-bs-target="#tabUrl" onclick="document.getElementById('imgFileInput').value=''">
                                                                    <i class="fa-solid fa-link"></i> Link Web
                                                                </button>
                                                            </li>
                                                            <li class="nav-item">
                                                                <button class="nav-link px-3 py-1" type="button" data-bs-toggle="pill" data-bs-target="#tabUpload" onclick="document.getElementById('imgUrlInput').value=''">
                                                                    <i class="fa-solid fa-upload"></i> Ficheiro Local
                                                                </button>
                                                            </li>
                                                            <!-- NOVA ABA DE GALERIA -->
                                                            <li class="nav-item">
                                                                <button class="nav-link px-3 py-1" type="button" data-bs-toggle="pill" data-bs-target="#tabGaleria" onclick="document.getElementById('imgFileInput').value=''">
                                                                    <i class="fa-solid fa-images"></i> Galeria
                                                                </button>
                                                            </li>
                                                        </ul>

                                                        <div class="tab-content">
                                                            <!-- ABA LINK -->
                                                            <div class="tab-pane fade show active" id="tabUrl">
                                                                <input type="text" name="imagem_url" id="imgUrlInput" class="form-control" placeholder="https://exemplo.com/imagem.png" value="<?= htmlspecialchars($imgAtual) ?>" oninput="previewUrl(this.value)">
                                                                <div class="small text-muted mt-1">Cole o link ou escolha uma imagem da Galeria.</div>
                                                            </div>
                                                            <!-- Posição da imagem no card -->
                                                            <div style="margin-top:1rem;">
                                                                <label style="display:block;font-size:0.8rem;font-weight:700;color:var(--text2);margin-bottom:0.5rem;">
                                                                    Posição da imagem no card
                                                                </label>

                                                                <!-- Preview interativo -->
                                                                <div style="position:relative;width:100%;height:130px;border-radius:10px;overflow:hidden;
                border:1px solid var(--border);margin-bottom:0.6rem;cursor:crosshair;background:var(--surface2);"
                                                                    id="previewThumb" onclick="moverPosicao(event, this)">
                                                                    <img id="previewImg" src="" alt="preview"
                                                                        style="width:100%;height:100%;object-fit:cover;object-position:center center;display:none;">
                                                                    <div id="previewPlaceholder"
                                                                        style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:0.8rem;">
                                                                        Adicione uma URL de imagem para ver o preview
                                                                    </div>
                                                                    <!-- Mira -->
                                                                    <div id="miraPos"
                                                                        style="display:none;position:absolute;width:16px;height:16px;
                    border-radius:50%;background:white;border:2px solid var(--accent);
                    transform:translate(-50%,-50%);pointer-events:none;
                    box-shadow:0 0 0 3px rgba(59,130,246,0.4);">
                                                                    </div>
                                                                </div>

                                                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                                                                    <button type="button" onclick="setPosPreset('center center')" class="btn-pos-preset">Centro</button>
                                                                    <button type="button" onclick="setPosPreset('top center')" class="btn-pos-preset">Topo</button>
                                                                    <button type="button" onclick="setPosPreset('bottom center')" class="btn-pos-preset">Base</button>
                                                                    <button type="button" onclick="setPosPreset('center left')" class="btn-pos-preset">Esquerda</button>
                                                                    <button type="button" onclick="setPosPreset('center right')" class="btn-pos-preset">Direita</button>
                                                                </div>

                                                                <input type="text" name="imagem_pos" id="imagemPos"
                                                                    value="<?php echo htmlspecialchars($editProd['imagem_pos'] ?? 'center center'); ?>"
                                                                    placeholder="ex: center top"
                                                                    style="width:100%;background:var(--surface2);border:1px solid var(--border);
                  border-radius:8px;color:var(--text);font-size:0.85rem;padding:0.6rem 0.8rem;outline:none;">
                                                                <p style="font-size:0.7rem;color:var(--text3);margin-top:0.4rem;">
                                                                    Clique no preview para ajustar o ponto de foco, ou use os presets acima.
                                                                </p>
                                                            </div>

                                                            <style>
                                                                .btn-pos-preset {
                                                                    padding: 0.35rem 0.75rem;
                                                                    font-size: 0.75rem;
                                                                    font-weight: 600;
                                                                    background: var(--surface2);
                                                                    border: 1px solid var(--border);
                                                                    border-radius: 6px;
                                                                    color: var(--text2);
                                                                    cursor: pointer;
                                                                    font-family: inherit;
                                                                    transition: all 0.15s;
                                                                }

                                                                .btn-pos-preset:hover {
                                                                    border-color: var(--accent);
                                                                    color: var(--text);
                                                                }
                                                            </style>

                                                            <script>
                                                                // Atualiza preview ao digitar URL
                                                                document.querySelector('[name="imagem_url"]').addEventListener('input', function() {
                                                                    const img = document.getElementById('previewImg');
                                                                    const ph = document.getElementById('previewPlaceholder');
                                                                    if (this.value.trim()) {
                                                                        img.src = this.value.trim();
                                                                        img.style.display = 'block';
                                                                        ph.style.display = 'none';
                                                                    } else {
                                                                        img.style.display = 'none';
                                                                        ph.style.display = 'flex';
                                                                    }
                                                                });

                                                                // Clique no preview → define posição
                                                                function moverPosicao(e, el) {
                                                                    const rect = el.getBoundingClientRect();
                                                                    const x = ((e.clientX - rect.left) / rect.width * 100).toFixed(1);
                                                                    const y = ((e.clientY - rect.top) / rect.height * 100).toFixed(1);
                                                                    const pos = x + '% ' + y + '%';
                                                                    aplicarPos(pos, x + '%', y + '%');
                                                                }

                                                                function setPosPreset(pos) {
                                                                    aplicarPos(pos, null, null);
                                                                }

                                                                function aplicarPos(pos, xPct, yPct) {
                                                                    document.getElementById('imagemPos').value = pos;
                                                                    document.getElementById('previewImg').style.objectPosition = pos;

                                                                    // Mira visual
                                                                    const mira = document.getElementById('miraPos');
                                                                    if (xPct) {
                                                                        mira.style.display = 'block';
                                                                        mira.style.left = xPct;
                                                                        mira.style.top = yPct;
                                                                    } else {
                                                                        mira.style.display = 'none';
                                                                    }
                                                                }

                                                                // Inicializa preview se já tem imagem (modo edição)
                                                                window.addEventListener('DOMContentLoaded', () => {
                                                                    const urlField = document.querySelector('[name="imagem_url"]');
                                                                    if (urlField && urlField.value.trim()) {
                                                                        urlField.dispatchEvent(new Event('input'));
                                                                        const posAtual = document.getElementById('imagemPos').value || 'center center';
                                                                        document.getElementById('previewImg').style.objectPosition = posAtual;
                                                                    }
                                                                });
                                                            </script>

                                                            <!-- ABA UPLOAD -->
                                                            <div class="tab-pane fade" id="tabUpload">
                                                                <div class="p-4 text-center rounded position-relative" style="background: var(--bg); border: 1px dashed var(--border) !important; cursor: pointer;">
                                                                    <input type="file" name="imagem_arquivo" id="imgFileInput" accept="image/*" onchange="previewFile(this)" style="position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 10;">
                                                                    <i class="fa-solid fa-cloud-arrow-up fs-3 text-muted"></i>
                                                                    <div class="mt-2 text-muted small">Clique ou arraste a imagem aqui (Máx 3MB)</div>
                                                                </div>
                                                            </div>

                                                            <!-- ABA GALERIA -->
                                                            <div class="tab-pane fade" id="tabGaleria">
                                                                <?php if (empty($imagens_galeria)): ?>
                                                                    <div class="p-3 text-center text-muted small border rounded" style="background: var(--bg); border-color: var(--border) !important;">
                                                                        Nenhuma imagem salva na galeria ainda.
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="galeria-grid p-2 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                                                                        <?php foreach ($imagens_galeria as $imgUrl): ?>
                                                                            <img src="<?= htmlspecialchars($imgUrl) ?>" class="galeria-item" onclick="selecionarDaGaleria('<?= htmlspecialchars(addslashes($imgUrl)) ?>', this)" title="Usar esta imagem">
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <div id="imgPreviewWrap" class="mt-3 position-relative" style="display: <?= $imgAtual ? 'inline-block' : 'none' ?>; width: fit-content;">
                                                            <img id="imgPreview" src="<?= htmlspecialchars($imgAtual) ?>" style="width: 200px; height: 120px; object-fit: contain; background: #fff; border-radius: 8px; border: 1px solid var(--border); padding: 5px;">
                                                            <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-1 rounded-circle shadow-sm" style="width: 25px; height: 25px; padding: 0; display: flex; align-items: center; justify-content: center;" onclick="removerImagem()" title="Remover imagem"><i class="fa-solid fa-xmark small"></i></button>
                                                        </div>
                                                        <input type="hidden" name="remover_imagem" id="removerImagemInput" value="">
                                                        <input type="hidden" name="imagem_base64" id="imagemBase64Input" value="">
                                                    </div>
                                                </div>

                                                <div class="col-12 mt-4 border-top pt-3" style="border-color: var(--border) !important;">
                                                    <div class="d-flex gap-4">
                                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="status" id="inpStatus" value="ativo" <?= ($editProd['status'] ?? 'ativo') === 'ativo' ? 'checked' : '' ?>><label class="form-check-label ms-2 fw-bold text-white">Status Ativo</label></div>
                                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="destaque" id="inpDestaque" value="1" <?= !empty($editProd['destaque']) ? 'checked' : '' ?>><label class="form-check-label ms-2 fw-bold text-white">Destaque</label></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 d-flex justify-content-end gap-2">
                                            <a href="?page=produtos" class="btn btn-outline-light">Cancelar</a>
                                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i> Salvar Produto</button>
                                        </div>
                                    </div>

                                    <!-- DIREITA: LIVE PREVIEW -->
                                    <div class="col-lg-4 d-none d-lg-block">
                                        <div class="preview-sticky">
                                            <p class="form-label mb-2">Pré-visualização do Card</p>
                                            <div class="card-live-preview" id="cardPreview">
                                                <div id="prevImgWrap" style="display:none; padding: 10px; background: #fff;"><img id="prevImg" src="" style="height: 100px; object-fit: contain;"></div>
                                                <div class="p-3 position-relative">
                                                    <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2" id="prevDestaque" style="display:none;">Destaque</span>
                                                    <span class="badge bg-primary-soft mb-2"><i id="prevCatIcon" class="fa-solid fa-tag"></i> <span id="prevCatName">Geral</span></span>
                                                    <h5 class="fw-bold mb-3" id="prevTitle">Título do Produto</h5>
                                                    <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: var(--border) !important;">
                                                        <div class="d-flex align-items-baseline gap-1"><small class="text-muted fw-bold">R$</small>
                                                            <h4 class="fw-bold mb-0" id="prevPrice">0,00</h4><small class="text-muted" id="prevCiclo">/mês</small>
                                                        </div>
                                                        <div class="btn btn-primary rounded px-2 py-1"><i class="fa-solid fa-plus"></i></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- LISTA DE PRODUTOS -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h2 class="fw-bold mb-0">Produtos</h2>
                                    <p class="text-muted small mb-0"><?= $stats['total'] ?> cadastrados · <?= $stats['ativos'] ?> ativos</p>
                                </div>
                                <a href="?page=produtos&novo=1" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Novo Produto</a>
                            </div>
                            <div class="card p-3 mb-4">
                                <form method="GET" action="" class="row g-2 align-items-end">
                                    <input type="hidden" name="page" value="produtos">
                                    <div class="col-md-4"><label class="form-label">Buscar</label><input type="text" name="busca" class="form-control" placeholder="Nome..." value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">Categoria</label><select name="cat" class="form-select">
                                            <option value="">Todas</option><?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>" <?= ($filtCat ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?>
                                        </select></div>
                                    <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">
                                            <option value="">Todos</option>
                                            <option value="ativo" <?= ($filtStatus ?? '') === 'ativo'  ? 'selected' : '' ?>>Ativos</option>
                                            <option value="inativo" <?= ($filtStatus ?? '') === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                                        </select></div>
                                    <div class="col-md-2 d-flex gap-2"><button type="submit" class="btn btn-primary w-100 justify-content-center"><i class="fa-solid fa-filter"></i></button><?php if (!empty($_GET['busca']) || !empty($_GET['cat']) || !empty($_GET['status'])): ?><a href="?page=produtos" class="btn btn-outline-light"><i class="fa-solid fa-xmark"></i></a><?php endif; ?></div>
                                </form>
                            </div>
                            <div class="card overflow-hidden">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Produto</th>
                                                <th>Categoria</th>
                                                <th>Preço</th>
                                                <th>Status</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($produtos)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-2 mb-2 d-block"></i> Nenhum produto encontrado.</td>
                                                </tr>
                                                <?php else: foreach ($produtos as $p): ?>
                                                    <tr>
                                                        <td class="text-muted small"><?= $p['id'] ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2"><?php if ($p['imagem_url']): ?><img src="<?= htmlspecialchars($p['imagem_url']) ?>" style="width: 34px; height: 34px; object-fit: cover; object-position: center; background: #fff; border-radius: 8px; padding: 2px;"><?php endif; ?><span class="fw-bold"><?= htmlspecialchars($p['titulo']) ?></span><?= $p['destaque'] ? '<i class="fa-solid fa-star text-warning small ms-1"></i>' : '' ?></div>
                                                        </td>
                                                        <td><?= $p['cat_nome'] ? '<span class="badge bg-primary-soft">' . htmlspecialchars($p['cat_nome']) . '</span>' : '<span class="badge bg-secondary-soft">—</span>' ?></td>
                                                        <td class="fw-bold">R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                                                        <td><span class="badge <?= $p['status'] === 'ativo' ? 'bg-success-soft' : 'bg-danger-soft' ?>"><?= $p['status'] ?></span></td>
                                                        <td class="text-end">
                                                            <a href="?page=produtos&editar=<?= $p['id'] ?>" class="btn btn-sm btn-outline-light px-2 py-1" title="Editar"><i class="fa-solid fa-pen"></i></a>

                                                            <a href="?action=toggle_status&id=<?= $p['id'] ?>&page=produtos" class="btn btn-sm btn-outline-light px-2 py-1" title="Alterar Status"><i class="fa-solid <?= $p['status'] === 'ativo' ? 'fa-eye-slash' : 'fa-eye' ?>"></i></a>

                                                            <!-- NOVO BOTÃO DE DESTAQUE -->
                                                            <a href="?action=toggle_destaque&id=<?= $p['id'] ?>&page=produtos" class="btn btn-sm btn-outline-light px-2 py-1" title="<?= $p['destaque'] ? 'Remover Destaque' : 'Destacar' ?>">
                                                                <i class="fa-solid fa-star <?= $p['destaque'] ? 'text-warning' : '' ?>"></i>
                                                            </a>

                                                            <a href="javascript:void(0)" onclick="confirmarExclusao(<?= $p['id'] ?>, '<?= htmlspecialchars($p['titulo'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger px-2 py-1 border-0" title="Excluir"><i class="fa-solid fa-trash"></i></a>
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
                        <!-- CATEGORIAS -->
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
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h2 class="fw-bold mb-0">Categorias</h2>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="card p-4">
                                    <h5 class="fw-bold mb-3"><?= $editCat ? 'Editar Categoria' : 'Nova Categoria' ?></h5>
                                    <form method="POST" action="?page=categorias">
                                        <input type="hidden" name="action" value="salvar_categoria"><input type="hidden" name="id" value="<?= $editCat ? intval($editCat['id']) : 0 ?>"><input type="hidden" name="icone" id="iconeHiddenInput" value="<?= htmlspecialchars($editCat['icone'] ?? 'fa-solid fa-tag') ?>">
                                        <div class="mb-3"><label class="form-label">Nome</label><input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($editCat['nome'] ?? '') ?>"></div>
                                        <div class="mb-4"><label class="form-label">Ícone Escolhido</label>
                                            <div class="d-flex align-items-center gap-3 p-3 bg-dark rounded border" style="border-color: var(--border) !important;"><i id="previewIconeSel" class="<?= htmlspecialchars($editCat['icone'] ?? 'fa-solid fa-tag') ?> fs-3 text-primary"></i>
                                                <div class="small text-muted font-monospace" id="previewNomeSel"><?= htmlspecialchars($editCat['icone'] ?? 'fa-solid fa-tag') ?></div>
                                            </div>
                                        </div>
                                        <div class="d-grid gap-2"><button type="submit" class="btn btn-success justify-content-center"><i class="fa-solid fa-floppy-disk"></i> Salvar</button><?php if ($editCat): ?><a href="?page=categorias" class="btn btn-outline-light justify-content-center">Cancelar</a><?php endif; ?></div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="card p-4 mb-4">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Escolha um Ícone</h6>
                                    <div class="accordion accordion-flush" id="accordionIcones" style="--bs-accordion-bg: transparent; --bs-accordion-color: #fff; --bs-accordion-border-color: var(--border);">
                                        <?php $i = 0;
                                        foreach ($ICONES as $grupo => $icones): $i++; ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header"><button class="accordion-button <?= $i === 1 ? '' : 'collapsed' ?> shadow-none fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#col<?= $i ?>"><?= $grupo ?></button></h2>
                                                <div id="col<?= $i ?>" class="accordion-collapse collapse <?= $i === 1 ? 'show' : '' ?>" data-bs-parent="#accordionIcones">
                                                    <div class="accordion-body icon-grid pt-2">
                                                        <?php foreach ($icones as [$classe, $nome]): ?><div class="icon-btn" title="<?= $nome ?>" onclick="selecionarIcone('<?= $classe ?>', this)"><i class="<?= $classe ?>"></i></div><?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card overflow-hidden">
                                    <table class="table table-dark table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="50">Ico</th>
                                                <th>Nome</th>
                                                <th>Produtos</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categorias as $cat): ?>
                                                <tr>
                                                    <td><i class="<?= htmlspecialchars($cat['icone']) ?> text-primary"></i></td>
                                                    <td class="fw-bold"><?= htmlspecialchars($cat['nome']) ?></td>
                                                    <td><span class="badge bg-secondary-soft"><?= $cntMap[$cat['id']] ?? 0 ?> itens</span></td>
                                                    <td class="text-end"><a href="?page=categorias&editar=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-light px-2 py-1"><i class="fa-solid fa-pen"></i></a><a href="javascript:void(0)" onclick="confirmarExclusaoCat(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger px-2 py-1 border-0"><i class="fa-solid fa-trash"></i></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>

        <!-- MODAL CROPPER DE IMAGEM -->
        <div class="modal fade" id="cropModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" data-bs-theme="dark">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="background: var(--bg2); border: 1px solid var(--border);">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-crop-simple me-2 text-primary"></i> Recortar Imagem</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center p-4">
                        <div style="max-height: 50vh; overflow: hidden; border-radius: 8px;">
                            <img id="cropImageTarget" src="" style="max-width: 100%; display: block;">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" id="btnConfirmCrop">Confirmar Recorte</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL BOOTSTRAP DE EXCLUSÃO -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true" data-bs-theme="dark">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: var(--bg2); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger" id="deleteModalLabel"><i class="fa-solid fa-triangle-exclamation me-2"></i> Confirmar Exclusão</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="flex-shrink: 0;"></button>
                    </div>
                    <div class="modal-body text-muted pt-3 pb-4" style="font-size: 0.95rem;" id="confirmMsg"></div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                        <a href="#" id="confirmLink" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i> Sim, excluir</a>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function toggleMenu() {
                document.getElementById('sidebar').classList.toggle('show');
            }

            function selecionarIcone(classe, btn) {
                document.getElementById('iconeHiddenInput').value = classe;
                document.getElementById('previewIconeSel').className = classe + ' fs-3 text-primary';
                document.getElementById('previewNomeSel').innerText = classe;
                document.querySelectorAll('.icon-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }

            // Preview de Imagem (Upload/URL)
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

            function previewUrl(url) {
                const img = document.getElementById('imgPreview');
                const wrap = document.getElementById('imgPreviewWrap');
                if (url && url.trim()) {
                    img.src = url;
                    wrap.style.display = 'inline-block';
                    document.getElementById('removerImagemInput').value = '';
                } else {
                    wrap.style.display = 'none';
                }
                atualizarPreviewCard();
            }

            let cropper;
            let cropModalInstance;

            function previewFile(input) {
                if (!input.files || !input.files[0]) return;
                const reader = new FileReader();

                reader.onload = (e) => {
                    document.getElementById('cropImageTarget').src = e.target.result;
                    cropModalInstance.show();

                    // Aguarda o modal abrir totalmente para renderizar o Cropper
                    document.getElementById('cropModal').addEventListener('shown.bs.modal', function onModalShow() {
                        if (cropper) cropper.destroy();
                        cropper = new Cropper(document.getElementById('cropImageTarget'), {
                            aspectRatio: 1, // Fixa o recorte num Quadrado (1:1). Se quiser retangular mude para 16/9
                            viewMode: 2,
                            autoCropArea: 1,
                        });
                        document.getElementById('cropModal').removeEventListener('shown.bs.modal', onModalShow);
                    });
                };
                reader.readAsDataURL(input.files[0]);
            }

            document.getElementById('btnConfirmCrop')?.addEventListener('click', () => {
                if (!cropper) return;

                // Pega o recorte numa resolução legal (400x400)
                const canvas = cropper.getCroppedCanvas({
                    width: 400,
                    height: 400
                });
                const base64 = canvas.toDataURL('image/png');

                // Atualiza o preview e alimenta o input oculto
                document.getElementById('imgPreview').src = base64;
                document.getElementById('imgPreviewWrap').style.display = 'inline-block';
                document.getElementById('imagemBase64Input').value = base64;

                // Limpa as outras abas
                document.getElementById('imgUrlInput').value = '';
                document.getElementById('removerImagemInput').value = '';

                atualizarPreviewCard();
                cropModalInstance.hide();
            });

            function removerImagem() {
                document.getElementById('imgPreviewWrap').style.display = 'none';
                document.getElementById('imgPreview').src = '';
                document.getElementById('removerImagemInput').value = '1';
                document.getElementById('imgUrlInput').value = '';
                const fileInput = document.getElementById('imgFileInput');
                if (fileInput) fileInput.value = '';
                atualizarPreviewCard();
            }

            // NOVA FUNÇÃO: Selecionar da Galeria
            function selecionarDaGaleria(url, imgElement) {
                // Preenche o input de URL com a imagem da galeria
                document.getElementById('imgUrlInput').value = url;
                // Limpa o input de arquivo (se houver algo selecionado)
                const fileInput = document.getElementById('imgFileInput');
                if (fileInput) fileInput.value = '';

                // Remove a classe 'selected' de todas as imagens e adiciona na clicada
                document.querySelectorAll('.galeria-item').forEach(el => el.classList.remove('selected'));
                imgElement.classList.add('selected');

                // Dispara o preview visual
                previewUrl(url);

                // Opcional: Mudar o tab de volta para "Link Web" para o utilizador ver a URL inserida
                const tabUrlTrigger = document.querySelector('button[data-bs-target="#tabUrl"]');
                if (tabUrlTrigger) {
                    const tab = new bootstrap.Tab(tabUrlTrigger);
                    tab.show();
                }
            }

            // Live Preview Card
            function atualizarPreviewCard() {
                if (!document.getElementById('cardPreview')) return;
                document.getElementById('prevTitle').textContent = document.getElementById('inpTitulo').value || 'Título do Produto';
                let pText = document.getElementById('inpPreco').value.replace('R$ ', '');
                document.getElementById('prevPrice').textContent = pText || '0,00';
                document.getElementById('prevCiclo').textContent = document.getElementById('inpCiclo').value === 'mensal' ? '/mês' : '/ano';
                document.getElementById('cardPreview').style.opacity = document.getElementById('inpStatus').checked ? '1' : '0.5';
                document.getElementById('prevDestaque').style.display = document.getElementById('inpDestaque').checked ? 'block' : 'none';
                const catSel = document.getElementById('inpCat');
                const catOpt = catSel.options[catSel.selectedIndex];
                document.getElementById('prevCatName').textContent = catOpt.dataset.nome || 'Geral';
                document.getElementById('prevCatIcon').className = catOpt.dataset.icone || 'fa-solid fa-tag';
                const isImgVisible = document.getElementById('imgPreviewWrap').style.display !== 'none';
                document.getElementById('prevImgWrap').style.display = isImgVisible ? 'block' : 'none';
                if (isImgVisible) document.getElementById('prevImg').src = document.getElementById('imgPreview').src;
            }
            ['inpTitulo', 'inpPreco', 'inpCiclo', 'inpCat', 'inpStatus', 'inpDestaque'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', atualizarPreviewCard);
                    el.addEventListener('change', atualizarPreviewCard);
                }
            });
            window.onload = atualizarPreviewCard;

            let deleteModalInstance;
            document.addEventListener('DOMContentLoaded', () => {
                const modalEl = document.getElementById('deleteModal');
                if (modalEl) {
                    deleteModalInstance = new bootstrap.Modal(modalEl);
                }
                const cropModalEl = document.getElementById('cropModal');
                if (cropModalEl) cropModalInstance = new bootstrap.Modal(cropModalEl);
            });

            function confirmarExclusao(id, titulo) {
                document.getElementById('confirmMsg').innerHTML = `Tem certeza que deseja remover o produto <strong class="text-white">"${titulo}"</strong>?<br>Essa ação não pode ser desfeita.`;
                document.getElementById('confirmLink').href = '?action=excluir_produto&id=' + id + '&page=produtos';
                deleteModalInstance.show();
            }

            function confirmarExclusaoCat(id, nome) {
                document.getElementById('confirmMsg').innerHTML = `Tem certeza que deseja remover a categoria <strong class="text-white">"${nome}"</strong>?<br>Ela só pode ser removida se não tiver produtos vinculados.`;
                document.getElementById('confirmLink').href = '?action=excluir_categoria&id=' + id + '&page=categorias';
                deleteModalInstance.show();
            }
        </script>
    <?php endif; ?>
</body>

</html>