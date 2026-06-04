<?php
session_start();

$conexao_path = __DIR__ . '/../../../config/conexao.php';
if (!file_exists($conexao_path)) {
    die('<div class="alert alert-danger m-4">ERRO: conexao.php não encontrado.</div>');
}
require_once $conexao_path;

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', '/geral/public/admin/uploads/');

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
    $_SESSION['flash_gal'] = ['msg' => $msg, 'type' => $type];
}
function getFlash(): ?array
{
    if (isset($_SESSION['flash_gal'])) {
        $f = $_SESSION['flash_gal'];
        unset($_SESSION['flash_gal']);
        return $f;
    }
    return null;
}

requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ═══════════════════════════════════════
   AÇÃO: EXCLUIR UMA IMAGEM
   Remove o registro de todos os produtos
   e apaga o arquivo físico se for local
════════════════════════════════════════ */
if ($action === 'excluir') {
    $url = trim($_POST['url'] ?? '');
    if ($url) {
        // Nulifica nos produtos que usam essa imagem
        $pdo->prepare("UPDATE produtos SET imagem_url = NULL WHERE imagem_url = ?")->execute([$url]);

        // Se for arquivo local, apaga do disco
        if (strpos($url, UPLOAD_URL) === 0) {
            $filename = basename($url);
            $filepath = UPLOAD_DIR . $filename;
            if (file_exists($filepath)) @unlink($filepath);
        }
        flash('Imagem removida da galeria e dos produtos vinculados.');
    }
    header('Location: galeria.php');
    exit;
}

/* ═══════════════════════════════════════
   AÇÃO: EXCLUIR SELECIONADAS (lote)
════════════════════════════════════════ */
if ($action === 'excluir_lote') {
    $urls = $_POST['urls'] ?? [];
    $removidas = 0;
    foreach ($urls as $url) {
        $url = trim($url);
        if (!$url) continue;
        $pdo->prepare("UPDATE produtos SET imagem_url = NULL WHERE imagem_url = ?")->execute([$url]);
        if (strpos($url, UPLOAD_URL) === 0) {
            $filename = basename($url);
            $filepath = UPLOAD_DIR . $filename;
            if (file_exists($filepath)) @unlink($filepath);
        }
        $removidas++;
    }
    flash($removidas . ' imagem(ns) removida(s).', $removidas > 0 ? 'success' : 'warning');
    header('Location: galeria.php');
    exit;
}

/* ═══════════════════════════════════════
   AÇÃO: UPLOAD DIRETO
════════════════════════════════════════ */
if ($action === 'upload') {
    if (!empty($_FILES['imagem_upload']['name'])) {
        $file = $_FILES['imagem_upload'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
            if (in_array($mime, $allowed)) {
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'][$mime];
                $name = uniqid('img_', true) . '.' . $ext;
                move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name);
                flash('Imagem adicionada à galeria com sucesso!');
            } else {
                flash('Formato de imagem não suportado.', 'danger');
            }
        }
    }
    header('Location: galeria.php');
    exit;
}

/* ═══════════════════════════════════════
   AÇÃO: SALVAR IMAGEM RECORTADA
   Recebe base64, salva no disco e
   atualiza todos os produtos que usavam
   a URL original
════════════════════════════════════════ */
if ($action === 'salvar_crop') {
    $urlOriginal = trim($_POST['url_original'] ?? '');
    $base64      = $_POST['imagem_base64'] ?? '';
    $imgPos      = trim($_POST['imagem_pos'] ?? 'center center');

    if ($base64 && strpos($base64, 'data:image') === 0) {
        list($type, $data) = explode(';', $base64);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);
        $name = uniqid('img_crop_', true) . '.png';
        file_put_contents(UPLOAD_DIR . $name, $data);
        $novaUrl = UPLOAD_URL . $name;

        // Atualiza produtos que usavam a URL original
        if ($urlOriginal) {
            $pdo->prepare("UPDATE produtos SET imagem_url = ?, imagem_pos = ? WHERE imagem_url = ?")
                ->execute([$novaUrl, $imgPos, $urlOriginal]);

            // Remove o arquivo físico antigo se era local
            if (strpos($urlOriginal, UPLOAD_URL) === 0) {
                $oldFile = UPLOAD_DIR . basename($urlOriginal);
                if (file_exists($oldFile)) @unlink($oldFile);
            }
        } else {
            // Imagem nova sem produto vinculado — atualiza pos nos produtos que tiverem a nova url (nenhum ainda)
        }

        flash('Imagem recortada salva com sucesso.');
    } else {
        // Só atualizar a posição, sem novo crop
        if ($urlOriginal && $imgPos) {
            $pdo->prepare("UPDATE produtos SET imagem_pos = ? WHERE imagem_url = ?")
                ->execute([$imgPos, $urlOriginal]);
            flash('Posição de foco atualizada em todos os produtos com essa imagem.');
        }
    }
    header('Location: galeria.php');
    exit;
}

/* ═══════════════════════════════════════
   DADOS: busca imagens únicas + produtos
════════════════════════════════════════ */
$stmt = $pdo->query("
    SELECT
        p.imagem_url,
        p.imagem_pos,
        COUNT(*) AS total_produtos,
        GROUP_CONCAT(p.titulo ORDER BY p.titulo SEPARATOR ', ') AS produtos_nomes
    FROM produtos p
    WHERE p.imagem_url IS NOT NULL AND p.imagem_url != ''
    GROUP BY p.imagem_url, p.imagem_pos
    ORDER BY p.imagem_url ASC
");
$imagens_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
$imagens = [];
$urls_conhecidas = [];

// 1. Adiciona as imagens que já estão vinculadas a produtos
foreach ($imagens_db as $img) {
    $imagens[] = $img;
    $urls_conhecidas[] = $img['imagem_url'];
}

// 2. Lê a pasta física e adiciona as imagens soltas
$arquivos = glob(UPLOAD_DIR . '*.*');
if ($arquivos) {
    foreach ($arquivos as $arq) {
        if (is_file($arq) && basename($arq) !== '.htaccess') {
            $url = UPLOAD_URL . basename($arq);
            if (!in_array($url, $urls_conhecidas)) {
                $imagens[] = [
                    'imagem_url' => $url,
                    'imagem_pos' => 'center center',
                    'total_produtos' => 0,
                    'produtos_nomes' => ''
                ];
                $urls_conhecidas[] = $url;
            }
        }
    }
}
$flash = getFlash();
$totalImagens = count($imagens);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeria — Didi Contas Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="logoconfig.ico" type="image/x-icon">
    <style>
        /* ── Grid de cards da galeria ── */
        .galeria-admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .img-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .img-card:hover {
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }

        .img-card.selecionado {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35);
        }

        .img-card.quebrada {
            border-color: rgba(239, 68, 68, 0.5);
        }

        /* Área da thumbnail */
        .img-thumb-wrap {
            position: relative;
            width: 100%;
            height: 140px;
            background: var(--surface2);
            overflow: hidden;
        }

        .img-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }

        .img-card:hover .img-thumb-wrap img {
            transform: scale(1.04);
        }

        /* Overlay de erro */
        .img-erro-overlay {
            position: absolute;
            inset: 0;
            background: rgba(239, 68, 68, 0.12);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #f87171;
        }

        /* Checkbox de seleção */
        .check-overlay {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 10;
        }

        .check-overlay input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
            border-radius: 4px;
        }

        /* Badge de status */
        .status-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 10;
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            padding: 0.2rem 0.5rem;
            border-radius: 100px;
        }

        /* Info do card */
        .img-card-info {
            padding: 0.65rem 0.75rem;
        }

        .img-card-url {
            font-size: 0.68rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 0.3rem;
        }

        .img-card-prods {
            font-size: 0.72rem;
            color: var(--text-sub);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Botões de ação do card */
        .img-card-actions {
            display: flex;
            gap: 0.4rem;
            padding: 0 0.75rem 0.75rem;
        }

        .img-card-actions .btn {
            flex: 1;
            font-size: 0.72rem;
            padding: 0.35rem 0.5rem;
            justify-content: center;
        }

        /* Barra de filtros */
        .filtro-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-filtro-tab {
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text-sub);
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-filtro-tab.active,
        .btn-filtro-tab:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        /* Toolbar de seleção */
        #toolbarLote {
            display: none;
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* Modal de edição / crop */
        #modalEditor .modal-dialog {
            max-width: 680px;
        }

        #cropContainer {
            max-height: 380px;
            overflow: hidden;
            border-radius: 10px;
            background: #000;
        }

        #cropContainer img {
            max-width: 100%;
            display: block;
        }

        /* Presets de posição */
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }

        .btn-preset {
            padding: 0.4rem;
            font-size: 0.72rem;
            font-weight: 700;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-sub);
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }

        .btn-preset:hover,
        .btn-preset.active {
            border-color: var(--accent);
            color: var(--accent2);
            background: rgba(59, 130, 246, 0.1);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.35;
            display: block;
        }

        /* Modo: apenas links externos (url pública) vs arquivo local */
        .tag-local {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.25);
            font-size: 0.58rem;
            font-weight: 800;
            padding: 0.15rem 0.45rem;
            border-radius: 100px;
            letter-spacing: 0.3px;
            vertical-align: middle;
        }

        .tag-link {
            background: rgba(59, 130, 246, 0.12);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.2);
            font-size: 0.58rem;
            font-weight: 800;
            padding: 0.15rem 0.45rem;
            border-radius: 100px;
            letter-spacing: 0.3px;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        <!-- ── Sidebar ── -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand d-flex justify-content-between align-items-center">
                <div>didi<span>contas</span></div>
                <button class="btn btn-sm btn-outline-light d-md-none border-0" onclick="toggleMenu()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="nav flex-column nav-pills mt-3 flex-grow-1">
                <div class="text-uppercase text-muted fw-bold small px-4 mb-2 mt-2" style="font-size:0.65rem;">Catálogo</div>
                <a class="nav-link" href="index.php?page=produtos"><i class="fa-solid fa-box-open"></i> Produtos</a>
                <a class="nav-link" href="index.php?page=categorias"><i class="fa-solid fa-layer-group"></i> Categorias</a>
                <div class="text-uppercase text-muted fw-bold small px-4 mb-2 mt-4" style="font-size:0.65rem;">Mídia</div>
                <a class="nav-link active" href="galeria.php"><i class="fa-solid fa-images"></i> Galeria</a>
                <div class="text-uppercase text-muted fw-bold small px-4 mb-2 mt-4" style="font-size:0.65rem;">Ações</div>
                <a class="nav-link" href="index.php?page=produtos&novo=1"><i class="fa-solid fa-plus"></i> Novo Produto</a>
                <a class="nav-link" href="/geral/index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver Vitrine</a>
            </div>
            <div class="p-3 border-top" style="border-color:var(--border)!important;">
                <a class="nav-link text-danger" href="index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Sair do Painel</a>
            </div>
        </aside>

        <!-- ── Conteúdo ── -->
        <div class="content-area">

            <!-- Topbar mobile -->
            <div class="d-md-none bg-dark border-bottom p-3 d-flex justify-content-between align-items-center" style="border-color:var(--border)!important;">
                <div class="fw-bold fs-5">didi<span style="color:var(--yellow);">contas</span></div>
                <button class="btn btn-outline-light border-0" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
            </div>

            <main class="container-fluid p-3 p-md-4">

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flash['msg']) ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Cabeçalho -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1"><i class="fa-solid fa-images text-primary me-2"></i>Galeria de Imagens</h2>
                        <p class="text-muted small mb-0">
                            <?= $totalImagens ?> imagem(ns) exibidas ·
                            <span id="contQuebradasLabel" class="text-danger fw-bold"></span>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="galeria.php" enctype="multipart/form-data" id="formUploadDireto" class="m-0">
                            <input type="hidden" name="action" value="upload">
                            <input type="file" name="imagem_upload" id="inputUploadDireto" accept="image/*" class="d-none" onchange="document.getElementById('formUploadDireto').submit();">
                            <button type="button" class="btn btn-success" onclick="document.getElementById('inputUploadDireto').click();">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Enviar Imagem
                            </button>
                        </form>

                        <a href="index.php?page=produtos" class="btn btn-outline-light">
                            <i class="fa-solid fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>

                <!-- Toolbar de filtros + seleção em lote -->
                <div class="card p-3 mb-4">
                    <div class="filtro-bar mb-3">
                        <button class="btn-filtro-tab active" onclick="filtrar('todas', this)">
                            <i class="fa-solid fa-border-all me-1"></i> Todas <span class="ms-1 opacity-75">(<?= $totalImagens ?>)</span>
                        </button>
                        <button class="btn-filtro-tab" onclick="filtrar('validas', this)">
                            <i class="fa-solid fa-circle-check me-1"></i> Válidas
                        </button>
                        <button class="btn-filtro-tab" onclick="filtrar('quebradas', this)">
                            <i class="fa-solid fa-circle-xmark me-1"></i> Quebradas
                        </button>
                        <button class="btn-filtro-tab" onclick="filtrar('local', this)">
                            <i class="fa-solid fa-hard-drive me-1"></i> Arquivos locais
                        </button>
                        <button class="btn-filtro-tab" onclick="filtrar('link', this)">
                            <i class="fa-solid fa-link me-1"></i> Links externos
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <label class="d-flex align-items-center gap-2 small fw-bold" style="cursor:pointer;color:var(--text-sub);">
                            <input type="checkbox" id="checkTodos" onchange="toggleTodos(this)" style="accent-color:var(--accent);width:16px;height:16px;">
                            Selecionar tudo
                        </label>
                        <div id="toolbarLote">
                            <span class="text-muted small me-2"><span id="countSelecionados">0</span> selecionada(s)</span>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirLote()">
                                <i class="fa-solid fa-trash"></i> Excluir selecionadas
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grid de imagens -->
                <?php if (empty($imagens)): ?>
                    <div class="card empty-state">
                        <i class="fa-solid fa-photo-film"></i>
                        <p class="fw-bold">Nenhuma imagem na galeria ainda.</p>
                        <p class="small text-muted">Adicione imagens nos produtos para que apareçam aqui.</p>
                    </div>
                <?php else: ?>
                    <form id="formLote" method="POST" action="galeria.php">
                        <input type="hidden" name="action" value="excluir_lote">
                        <div class="galeria-admin-grid" id="galeriaGrid">
                            <?php foreach ($imagens as $img):
                                $url     = $img['imagem_url'];
                                $pos     = $img['imagem_pos'] ?? 'center center';
                                $prods   = $img['total_produtos'];
                                $nomes   = $img['produtos_nomes'];
                                $isLocal = strpos($url, UPLOAD_URL) === 0;
                                $urlEnc  = htmlspecialchars($url, ENT_QUOTES);
                                $posEnc  = htmlspecialchars($pos, ENT_QUOTES);
                            ?>
                                <div class="img-card"
                                    data-url="<?= $urlEnc ?>"
                                    data-pos="<?= $posEnc ?>"
                                    data-tipo="<?= $isLocal ? 'local' : 'link' ?>"
                                    data-status="carregando">

                                    <!-- Checkbox de seleção -->
                                    <div class="check-overlay">
                                        <input type="checkbox" name="urls[]" value="<?= $urlEnc ?>"
                                            onchange="atualizarSelecao()">
                                    </div>

                                    <!-- Badge tipo -->
                                    <span class="status-badge <?= $isLocal ? 'tag-local' : 'tag-link' ?>">
                                        <?= $isLocal ? 'LOCAL' : 'LINK' ?>
                                    </span>

                                    <!-- Thumbnail -->
                                    <div class="img-thumb-wrap">
                                        <img src="<?= $urlEnc ?>"
                                            alt=""
                                            style="object-position: <?= $posEnc ?>;"
                                            loading="lazy"
                                            onload="marcarValida(this)"
                                            onerror="marcarQuebrada(this)">
                                        <!-- Overlay de erro (oculto por padrão) -->
                                        <div class="img-erro-overlay" style="display:none;">
                                            <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                                            <span style="font-size:0.7rem;font-weight:700;">Link quebrado</span>
                                        </div>
                                    </div>

                                    <!-- Info -->
                                    <div class="img-card-info">
                                        <div class="img-card-url" title="<?= $urlEnc ?>">
                                            <?= basename($url) ?>
                                        </div>
                                        <div class="img-card-prods" title="<?= htmlspecialchars($nomes ?? '') ?>">
                                            <i class="fa-solid fa-box fa-xs me-1 opacity-50"></i>
                                            <?= $prods ?> produto<?= $prods != 1 ? 's' : '' ?>
                                            <?php if ($nomes): ?>
                                                · <span style="opacity:0.7;"><?= htmlspecialchars(mb_strimwidth($nomes, 0, 40, '…')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Ações -->
                                    <div class="img-card-actions">
                                        <button type="button" class="btn btn-outline-light"
                                            onclick="abrirEditor('<?= $urlEnc ?>', '<?= $posEnc ?>')"
                                            title="Recortar / Ajustar posição">
                                            <i class="fa-solid fa-crop-simple"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-outline-danger"
                                            onclick="confirmarExclusao('<?= $urlEnc ?>', <?= $prods ?>)"
                                            title="Excluir imagem">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <!-- ════════════════════════════════════════════
     MODAL: EDITOR / RECORTE / POSIÇÃO
════════════════════════════════════════════ -->
    <div class="modal fade" id="modalEditor" tabindex="-1" data-bs-theme="dark" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-sliders text-primary me-2"></i>Editar Imagem
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="fecharEditor()"></button>
                </div>

                <!-- Abas do editor -->
                <ul class="nav nav-pills px-4 pt-2 pb-0 gap-2" id="editorTabs">
                    <li class="nav-item">
                        <button class="nav-link active px-3 py-1" onclick="mudarAba('pos', this)">
                            <i class="fa-solid fa-crosshairs me-1"></i> Posição
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link px-3 py-1" onclick="mudarAba('crop', this)">
                            <i class="fa-solid fa-crop-simple me-1"></i> Recortar
                        </button>
                    </li>
                </ul>

                <div class="modal-body px-4 pt-3 pb-2">

                    <!-- ABA: Posição de foco -->
                    <div id="abaPos">
                        <p class="small text-muted mb-2">
                            Clique na imagem para definir o ponto de foco que será usado no card da vitrine.
                        </p>
                        <div id="posThumb"
                            style="position:relative;width:100%;height:220px;border-radius:10px;overflow:hidden;
                                border:1px solid var(--border);cursor:crosshair;background:var(--surface2);"
                            onclick="clicarFoco(event, this)">
                            <img id="posImg" src="" alt=""
                                style="width:100%;height:100%;object-fit:cover;display:block;">
                            <!-- Mira -->
                            <div id="miraFoco"
                                style="display:none;position:absolute;width:20px;height:20px;
                                    border-radius:50%;background:rgba(255,255,255,0.9);
                                    border:2px solid var(--accent);transform:translate(-50%,-50%);
                                    pointer-events:none;box-shadow:0 0 0 4px rgba(59,130,246,0.35),0 2px 8px rgba(0,0,0,0.5);">
                            </div>
                            <!-- Linhas guia -->
                            <div id="guiaH" style="display:none;position:absolute;width:100%;height:1px;background:rgba(59,130,246,0.5);pointer-events:none;transform:translateY(-50%);"></div>
                            <div id="guiaV" style="display:none;position:absolute;height:100%;width:1px;background:rgba(59,130,246,0.5);pointer-events:none;transform:translateX(-50%);"></div>
                        </div>

                        <!-- Presets 3x3 -->
                        <div class="preset-grid mt-3">
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('0% 0%')">↖ Topo Esq.</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('50% 0%')">↑ Topo</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('100% 0%')">↗ Topo Dir.</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('0% 50%')">← Esq.</button>
                            <button type="button" class="btn-preset active" onclick="aplicarFocoPreset('50% 50%')">• Centro</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('100% 50%')">→ Dir.</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('0% 100%')">↙ Base Esq.</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('50% 100%')">↓ Base</button>
                            <button type="button" class="btn-preset" onclick="aplicarFocoPreset('100% 100%')">↘ Base Dir.</button>
                        </div>

                        <div class="mt-2 d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0" style="white-space:nowrap;">Valor exato:</label>
                            <input type="text" id="inputFocoManual" class="form-control form-control-sm"
                                placeholder="50% 30%" oninput="aplicarFocoManual(this.value)">
                        </div>
                    </div>

                    <!-- ABA: Recorte Cropper.js -->
                    <div id="abaCrop" style="display:none;">
                        <p class="small text-muted mb-2">
                            Recorte a imagem. A versão recortada será salva e aplicada a todos os produtos que usam esta imagem.
                        </p>
                        <!-- Proporção -->
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="setAspecto(1/1)">1:1 Quadrado</button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="setAspecto(4/3)">4:3</button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="setAspecto(16/9)">16:9 Wide</button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="setAspecto(3/2)">3:2</button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="setAspecto(NaN)">Livre</button>
                        </div>
                        <div id="cropContainer">
                            <img id="cropImgTarget" src="" style="max-width:100%;display:block;">
                        </div>
                        <!-- Controles de rotação/zoom -->
                        <div class="d-flex gap-2 mt-2 justify-content-center flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="cropper.rotate(-90)" title="Girar -90°"><i class="fa-solid fa-rotate-left"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="cropper.rotate(90)" title="Girar +90°"><i class="fa-solid fa-rotate-right"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="cropper.zoom(0.1)" title="Zoom +"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="cropper.zoom(-0.1)" title="Zoom -"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="cropper.reset()" title="Resetar"><i class="fa-solid fa-arrows-rotate"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="cropper.scaleX(cropper.getData().scaleX * -1)" title="Espelhar horizontal"><i class="fa-solid fa-left-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 px-4 pb-4 gap-2">
                    <button type="button" class="btn btn-outline-light" onclick="fecharEditor()">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnSalvarEditor" onclick="salvarEditor()">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1" data-bs-theme="dark">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3 pb-4" id="excluirMsg" style="color:var(--text-sub);font-size:0.9rem;"></div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="galeria.php" style="display:inline;">
                        <input type="hidden" name="action" value="excluir">
                        <input type="hidden" name="url" id="excluirUrlInput">
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-trash me-1"></i> Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        /* ════════════════════════════════════════
   ESTADO GLOBAL
════════════════════════════════════════ */
        let cropper = null;
        let abaAtiva = 'pos'; // 'pos' | 'crop'
        let urlEditando = ''; // URL da imagem sendo editada
        let focoAtual = 'center center';
        let modalEditor = null;
        let modalExcluir = null;
        let contQuebradasEl = document.getElementById('contQuebradasLabel');
        let totalQuebradas = 0;

        document.addEventListener('DOMContentLoaded', () => {
            modalEditor = new bootstrap.Modal(document.getElementById('modalEditor'));
            modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluir'));
            verificarQuebradas();
        });

        /* ════════════════════════════════════════
           DETECÇÃO DE IMAGENS QUEBRADAS
        ════════════════════════════════════════ */
        function marcarValida(imgEl) {
            const card = imgEl.closest('.img-card');
            card.dataset.status = 'valida';
            card.querySelector('.img-erro-overlay').style.display = 'none';
            imgEl.style.display = 'block';
        }

        function marcarQuebrada(imgEl) {
            const card = imgEl.closest('.img-card');
            card.dataset.status = 'quebrada';
            card.classList.add('quebrada');
            card.querySelector('.img-erro-overlay').style.display = 'flex';
            imgEl.style.display = 'none';
            totalQuebradas++;
            if (totalQuebradas > 0) {
                contQuebradasEl.textContent = totalQuebradas + ' quebrada(s)';
            }
        }

        /* ════════════════════════════════════════
           FILTRO
        ════════════════════════════════════════ */
        function filtrar(tipo, btn) {
            document.querySelectorAll('.btn-filtro-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.img-card').forEach(card => {
                let mostrar = false;
                if (tipo === 'todas') mostrar = true;
                if (tipo === 'validas') mostrar = card.dataset.status === 'valida';
                if (tipo === 'quebradas') mostrar = card.dataset.status === 'quebrada';
                if (tipo === 'local') mostrar = card.dataset.tipo === 'local';
                if (tipo === 'link') mostrar = card.dataset.tipo === 'link';
                card.style.display = mostrar ? '' : 'none';
            });
        }

        function verificarQuebradas() {
            // Aguarda todos os onload/onerror dispararem
            setTimeout(() => {
                if (totalQuebradas === 0) contQuebradasEl.textContent = '';
            }, 4000);
        }

        /* ════════════════════════════════════════
           SELEÇÃO EM LOTE
        ════════════════════════════════════════ */
        function atualizarSelecao() {
            const checks = document.querySelectorAll('#galeriaGrid input[type="checkbox"]:checked');
            const toolbar = document.getElementById('toolbarLote');
            const count = document.getElementById('countSelecionados');
            count.textContent = checks.length;
            toolbar.style.display = checks.length > 0 ? 'inline-flex' : 'none';
            document.querySelectorAll('.img-card').forEach(card => {
                const cb = card.querySelector('input[type="checkbox"]');
                card.classList.toggle('selecionado', cb && cb.checked);
            });
        }

        function toggleTodos(masterCb) {
            document.querySelectorAll('#galeriaGrid input[type="checkbox"]').forEach(cb => {
                // Só marca cards visíveis
                const card = cb.closest('.img-card');
                if (card.style.display !== 'none') cb.checked = masterCb.checked;
            });
            atualizarSelecao();
        }

        function excluirLote() {
            const checks = document.querySelectorAll('#galeriaGrid input[type="checkbox"]:checked');
            if (checks.length === 0) return;
            if (!confirm(`Excluir ${checks.length} imagem(ns) selecionada(s) e desvincular de todos os produtos? Essa ação não pode ser desfeita.`)) return;
            document.getElementById('formLote').submit();
        }

        /* ════════════════════════════════════════
           EXCLUSÃO INDIVIDUAL
        ════════════════════════════════════════ */
        function confirmarExclusao(url, totalProd) {
            document.getElementById('excluirUrlInput').value = url;
            const aviso = totalProd > 0 ?
                `<strong class="text-white">${totalProd} produto(s)</strong> ficarão sem imagem.` :
                'Esta imagem não está vinculada a nenhum produto.';
            document.getElementById('excluirMsg').innerHTML =
                `Tem certeza que deseja excluir esta imagem da galeria?<br><br>${aviso}<br><br>
         <span style="font-size:0.78rem;opacity:0.6;word-break:break-all;">${escapeHtml(url)}</span>`;
            modalExcluir.show();
        }

        function escapeHtml(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /* ════════════════════════════════════════
           EDITOR (POSIÇÃO + CROP)
        ════════════════════════════════════════ */
        function abrirEditor(url, pos) {
            urlEditando = url;
            focoAtual = pos || 'center center';

            // Carrega imagens nas duas abas
            document.getElementById('posImg').src = url;
            document.getElementById('cropImgTarget').src = url;
            document.getElementById('inputFocoManual').value = focoAtual;

            // Aplica foco atual na aba de posição
            document.getElementById('posImg').style.objectPosition = focoAtual;

            // Reseta para aba Posição
            mudarAba('pos', document.querySelector('#editorTabs .nav-link'));

            modalEditor.show();
        }

        function fecharEditor() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            modalEditor.hide();
        }

        function mudarAba(aba, btnEl) {
            abaAtiva = aba;
            document.querySelectorAll('#editorTabs .nav-link').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');

            document.getElementById('abaPos').style.display = aba === 'pos' ? '' : 'none';
            document.getElementById('abaCrop').style.display = aba === 'crop' ? '' : 'none';

            if (aba === 'crop') {
                iniciarCropper();
            } else {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            }
        }

        /* ── Posição de foco ── */
        function clicarFoco(e, el) {
            const rect = el.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width * 100).toFixed(1);
            const y = ((e.clientY - rect.top) / rect.height * 100).toFixed(1);
            aplicarFoco(x + '% ' + y + '%', x + '%', y + '%');
        }

        function aplicarFocoPreset(pos) {
            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            aplicarFoco(pos, null, null);
            document.getElementById('inputFocoManual').value = pos;
        }

        function aplicarFocoManual(val) {
            if (val.trim()) aplicarFoco(val.trim(), null, null);
        }

        function aplicarFoco(pos, xPct, yPct) {
            focoAtual = pos;
            document.getElementById('posImg').style.objectPosition = pos;
            document.getElementById('inputFocoManual').value = pos;
            const mira = document.getElementById('miraFoco');
            const guiaH = document.getElementById('guiaH');
            const guiaV = document.getElementById('guiaV');
            if (xPct && yPct) {
                mira.style.display = 'block';
                guiaH.style.display = 'block';
                guiaV.style.display = 'block';
                mira.style.left = xPct;
                mira.style.top = yPct;
                guiaH.style.top = yPct;
                guiaV.style.left = xPct;
            } else {
                mira.style.display = 'none';
                guiaH.style.display = 'none';
                guiaV.style.display = 'none';
            }
        }

        /* ── Cropper ── */
        function iniciarCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            const img = document.getElementById('cropImgTarget');
            // Aguarda imagem carregar
            if (!img.complete || img.naturalWidth === 0) {
                img.onload = () => criarCropper(img);
            } else {
                criarCropper(img);
            }
        }

        function criarCropper(img) {
            cropper = new Cropper(img, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 0.9,
                background: false,
                movable: true,
                rotatable: true,
                scalable: true,
                zoomable: true,
            });
        }

        function setAspecto(ratio) {
            if (cropper) cropper.setAspectRatio(ratio);
        }

        /* ── Salvar editor ── */
        function salvarEditor() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'galeria.php';

            const campos = {
                action: 'salvar_crop',
                url_original: urlEditando,
                imagem_pos: focoAtual,
                imagem_base64: '',
            };

            if (abaAtiva === 'crop' && cropper) {
                const canvas = cropper.getCroppedCanvas({
                    width: 800,
                    height: 800,
                    imageSmoothingQuality: 'high'
                });
                campos.imagem_base64 = canvas.toDataURL('image/png');
            }

            for (const [k, v] of Object.entries(campos)) {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = k;
                inp.value = v;
                form.appendChild(inp);
            }

            document.body.appendChild(form);
            form.submit();
        }

        /* ════════════════════════════════════════
           SIDEBAR MOBILE
        ════════════════════════════════════════ */
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('show');
        }
    </script>
</body>

</html>