<?php
require_once '../config/conexao.php';
require_once 'header.php';

// Busca categorias
$stmt_cat = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Busca produtos ativos com join na categoria
$query_prod = "SELECT p.*, c.nome AS categoria_nome, c.icone AS categoria_icone
               FROM produtos p
               LEFT JOIN categorias c ON p.categoria_id = c.id
               WHERE p.status = 'ativo'
               ORDER BY p.destaque DESC, p.id DESC";
$stmt_prod = $pdo->query($query_prod);
$produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* ─── Main Layout ───────────────────────────────────────────── */
    .main-container {
        max-width: 1320px;
        margin: 0 auto;
        padding: 1.5rem 1rem 4rem;
    }

    @media(min-width: 640px) {
        .main-container {
            padding: 2rem 1.5rem 5rem;
        }
    }

    /* ─── Hero ──────────────────────────────────────────────────── */
    .hero {
        text-align: center;
        padding: 2.5rem 1rem 2rem;
    }

    .hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        color: #60A5FA;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 0.35rem 0.9rem;
        border-radius: 100px;
        margin-bottom: 1rem;
    }

    .hero h1 {
        font-size: clamp(1.8rem, 5.5vw, 3rem);
        font-weight: 800;
        letter-spacing: -1px;
        line-height: 1.15;
        color: var(--text);
        margin-bottom: 0.75rem;
    }

    .hero h1 em {
        font-style: normal;
        background: linear-gradient(135deg, #3B82F6 0%, #6366F1 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hero p {
        color: var(--text2);
        font-size: 0.95rem;
        max-width: 440px;
        margin: 0 auto;
        line-height: 1.65;
    }

    /* ─── Filter Bar ────────────────────────────────────────────── */
    .filter-bar {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r);
        padding: 1rem;
        margin-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    /* Search */
    .search-wrap {
        position: relative;
    }

    .search-wrap .ico {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text2);
        font-size: 0.85rem;
        pointer-events: none;
    }

    .search-wrap input {
        width: 100%;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        color: var(--text);
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.9rem;
        padding: 0.65rem 2.4rem 0.65rem 2.6rem;
        outline: none;
        transition: border-color 0.2s;
        -webkit-appearance: none;
        appearance: none;
    }

    .search-wrap input::placeholder {
        color: var(--text3);
    }

    .search-wrap input:focus {
        border-color: var(--accent);
    }

    .btn-limpar-busca {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text2);
        cursor: pointer;
        font-size: 0.8rem;
        padding: 4px 6px;
        line-height: 1;
        border-radius: 4px;
        display: none;
        transition: color 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-limpar-busca:hover {
        color: var(--text);
    }

    /* Cycle toggle — scroll horizontal com pílulas */
    .ciclo-toggle {
        display: flex;
        gap: 5px;
        background: var(--surface2);
        border-radius: var(--r-sm);
        padding: 4px;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        flex-shrink: 0;
    }

    .ciclo-toggle::-webkit-scrollbar {
        display: none;
    }

    .btn-ciclo {
        flex-shrink: 0;
        padding: 0.48rem 0.9rem;
        border-radius: 6px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        background: transparent;
        color: var(--text2);
        transition: all 0.2s;
        letter-spacing: 0.2px;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-ciclo.active {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.35);
    }

    /* Badge de ciclo nos cards */
    .ciclo-badge {
        display: inline-flex;
        align-items: center;
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 0.15rem 0.45rem;
        border-radius: 100px;
        margin-left: 4px;
        background: rgba(255, 255, 255, 0.07);
        color: var(--text2);
        border: 1px solid var(--border2);
        vertical-align: middle;
    }

    /* Category chips — scroll limitado com botão "ver mais" */
    .cats-wrap {
        position: relative;
    }

    .cats-scroll {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 2px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        flex-wrap: nowrap;
        transition: max-height 0.35s ease;
    }

    .cats-scroll.expandida {
        flex-wrap: wrap;
        overflow-x: visible;
    }

    .cats-scroll::-webkit-scrollbar {
        display: none;
    }

    .btn-ver-mais-cats {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0.42rem 0.85rem;
        border-radius: 100px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid var(--border2);
        background: transparent;
        color: var(--text2);
        transition: all 0.2s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
        margin-top: 2px;
    }

    .btn-ver-mais-cats:hover {
        background: var(--surface2);
        color: var(--text);
    }

    .btn-filtro {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0.45rem 0.9rem;
        border-radius: 100px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid var(--border);
        background: var(--surface2);
        color: var(--text2);
        transition: all 0.2s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-filtro:hover {
        border-color: var(--border2);
        color: var(--text);
    }

    .btn-filtro.active {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }

    .btn-filtro i {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    /* ─── Section label ─────────────────────────────────────────── */
    .section-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--text2);
        margin-bottom: 1rem;
        padding-top: 0.25rem;
    }

    /* ─── Product Grid ──────────────────────────────────────────── */
    #gridProdutos {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    @media(min-width: 640px) {
        #gridProdutos {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
    }

    @media(min-width: 900px) {
        #gridProdutos {
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }
    }

    @media(min-width: 1200px) {
        #gridProdutos {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    /* ─── Card ──────────────────────────────────────────────────── */
    .card-produto {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r);
        overflow: hidden;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        transition: border-color 0.25s, transform 0.2s, box-shadow 0.25s;
        position: relative;
        -webkit-tap-highlight-color: transparent;
        animation: fadeUp 0.4s both;
    }

    .card-produto::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: var(--r);
        background: radial-gradient(circle at 0% 0%, var(--accent-glow), transparent 65%);
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
    }

    .card-produto:hover {
        border-color: rgba(59, 130, 246, 0.3);
        transform: translateY(-3px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
    }

    .card-produto:hover::after {
        opacity: 1;
    }

    .card-produto:active {
        transform: scale(0.98);
    }

    /* ─── Thumb do card ─────────────────────────────────────── */
    .card-thumb {
        width: 100%;
        height: 120px;
        overflow: hidden;
        position: relative;
        flex-shrink: 0;
    }

    .card-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: var(--img-pos, center center);
        display: block;
        transition: transform 0.35s ease;
    }

    .card-produto:hover .card-thumb img {
        transform: scale(1.04);
    }

    .card-thumb-placeholder {
        width: 100%;
        height: 120px;
        background: var(--surface2);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media(min-width: 640px) {
        .card-thumb {
            height: 140px;
        }
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
    }

    .card-body {
        padding: 0.9rem 0.9rem 0.75rem;
        display: flex;
        flex-direction: column;
        flex: 1;
        gap: 0.5rem;
    }

    @media(min-width: 640px) {
        .card-body {
            padding: 1.1rem;
        }
    }

    .card-cat {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #60A5FA;
        background: rgba(59, 130, 246, 0.1);
        padding: 0.2rem 0.55rem;
        border-radius: 100px;
        width: fit-content;
    }

    .card-cat i {
        font-size: 0.65rem;
    }

    .card-titulo {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    @media(min-width: 640px) {
        .card-titulo {
            font-size: 1rem;
        }
    }

    .card-footer {
        margin-top: auto;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-preco {
        display: flex;
        align-items: baseline;
        gap: 2px;
        line-height: 1;
    }

    .preco-rs {
        font-size: 0.68rem;
        color: #60A5FA;
        font-weight: 700;
    }

    .preco-val {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -0.5px;
    }

    @media(min-width: 640px) {
        .preco-val {
            font-size: 1.4rem;
        }
    }

    .preco-ciclo {
        font-size: 0.68rem;
        color: var(--text2);
        margin-left: 2px;
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
        flex-shrink: 0;
        transition: background 0.2s, transform 0.15s;
    }

    .card-produto:hover .card-btn {
        background: #2563EB;
        transform: scale(1.1);
    }

    /* ─── Empty state ───────────────────────────────────────────── */
    #avisoSemResultados {
        display: none;
        text-align: center;
        padding: 3.5rem 1rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r);
        margin-top: 1rem;
        color: var(--text2);
    }

    #avisoSemResultados i {
        font-size: 2rem;
        margin-bottom: 0.75rem;
        display: block;
        opacity: 0.5;
        color: var(--text3);
    }

    #avisoSemResultados p {
        font-size: 0.9rem;
        font-weight: 500;
    }

    /* ─── Botão Voltar ao Topo ──────────────────────────────────── */
    .btn-topo {
        position: fixed;
        bottom: calc(1.5rem + env(safe-area-inset-bottom));
        right: 1.25rem;
        z-index: 80;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: var(--surface2);
        border: 1px solid var(--border2);
        color: var(--text2);
        cursor: pointer;
        font-size: 0.9rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
        transition: opacity 0.25s, transform 0.25s, border-color 0.2s, color 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transform: translateY(10px);
        -webkit-tap-highlight-color: transparent;
    }

    .btn-topo.visivel {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }

    .btn-topo:hover {
        border-color: var(--accent);
        color: var(--accent);
    }

    /* ─── Animations ────────────────────────────────────────────── */
    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(14px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    /* ─── Admin hint (futuro) ───────────────────────────────────── */
    /* .admin-fab — botão flutuante para o painel admin, a ser ativado via classe `admin-mode` no body */
    .admin-fab {
        display: none;
        position: fixed;
        bottom: calc(1.5rem + env(safe-area-inset-bottom));
        right: 1.5rem;
        z-index: 80;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: var(--accent2);
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 1.1rem;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        transition: transform 0.2s, box-shadow 0.2s;
        align-items: center;
        justify-content: center;
    }

    body.admin-mode .admin-fab {
        display: flex;
    }

    .admin-fab:hover {
        transform: scale(1.08);
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.5);
    }
</style>

<main>
    <div class="main-container">

        <!-- Hero -->
        <div class="hero">
            <div class="hero-pill">
                <i class="fa-solid fa-bolt"></i>
                Planos originais com preco acessivel
            </div>
            <h1>Acesse o melhor<br>da net por <em>menos</em></h1>
            <p>IAs, streamings e ferramentas pro — tudo em um lugar. Clique, converse no WhatsApp e ative na hora.</p>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">

            <!-- Row 1: Search -->
            <div class="search-wrap">
                <span class="ico"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="pesquisaInput" onkeyup="filtrarVitrine()" placeholder="Buscar produto..." aria-label="Buscar produto">
                <button class="btn-limpar-busca" id="btnLimparBusca" onclick="limparPesquisa()" aria-label="Limpar busca">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Row 2: Ciclo toggle (todos + cada período) -->
            <div class="ciclo-toggle">
                <button onclick="filtrarCiclo('todos', this)" class="btn-ciclo active">Todos</button>
                <button onclick="filtrarCiclo('mensal', this)" class="btn-ciclo">Mensal</button>
                <button onclick="filtrarCiclo('trimestral', this)" class="btn-ciclo">Trimestral</button>
                <button onclick="filtrarCiclo('semestral', this)" class="btn-ciclo">Semestral</button>
                <button onclick="filtrarCiclo('anual', this)" class="btn-ciclo">Anual</button>
                <button onclick="filtrarCiclo('vitalicio', this)" class="btn-ciclo">Vitalício</button>
            </div>

            <!-- Row 3: Category chips + botão expandir -->
            <div class="cats-wrap">
                <div class="cats-scroll" id="catsScroll">
                    <button onclick="filtrarCategoria('todas', this)" class="btn-filtro active">
                        <i class="fa-solid fa-border-all"></i> Todas
                    </button>
                    <?php foreach ($categorias as $cat): ?>
                        <button onclick="filtrarCategoria('cat-<?php echo $cat['id']; ?>', this)" class="btn-filtro">
                            <i class="<?php echo htmlspecialchars($cat['icone'] ?? 'fa-solid fa-tag'); ?>"></i>
                            <?php echo htmlspecialchars($cat['nome']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if (count($categorias) > 4): ?>
                    <button class="btn-ver-mais-cats" id="btnVerMaisCats" onclick="toggleCats(this)">
                        <i class="fa-solid fa-chevron-down" id="iconVerMais"></i>
                        <span id="textoVerMais">Ver todas as categorias</span>
                    </button>
                <?php endif; ?>
            </div>

        </div>

        <!-- Section label -->
        <p class="section-label" id="sectionLabel">Todos os planos</p>

        <!-- Grid -->
        <div id="gridProdutos">
            <?php foreach ($produtos as $i => $prod): ?>
                <div onclick="abrirDetalhes(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES); ?>)"
                    class="card-produto"
                    data-categoria="cat-<?php echo $prod['categoria_id']; ?>"
                    data-ciclo="<?php echo $prod['ciclo']; ?>"
                    data-titulo="<?php echo strtolower(htmlspecialchars($prod['titulo'])); ?>"
                    style="animation-delay:<?php echo $i * 0.04; ?>s"
                    role="button"
                    tabindex="0"
                    aria-label="Ver detalhes de <?php echo htmlspecialchars($prod['titulo']); ?>">

                    <?php if (!empty($prod['destaque'])): ?>
                        <span class="card-destaque-badge">Destaque</span>
                    <?php endif; ?>

                    <?php
                    $imgPos = !empty($prod['imagem_pos']) ? htmlspecialchars($prod['imagem_pos']) : 'center center';
                    ?>
                    <?php if (!empty($prod['imagem_url'])): ?>
                        <div class="card-thumb">
                            <img src="<?php echo htmlspecialchars($prod['imagem_url']); ?>"
                                alt="<?php echo htmlspecialchars($prod['titulo']); ?>"
                                style="--img-pos: <?php echo $imgPos; ?>;"
                                loading="lazy">
                        </div>
                    <?php else: ?>
                        <div class="card-thumb-placeholder">
                            <i class="fa-solid fa-circle-nodes" style="color:var(--accent);font-size:1.6rem;opacity:0.4;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <span class="card-cat">
                            <i class="<?php echo htmlspecialchars($prod['categoria_icone'] ?? 'fa-solid fa-tag'); ?>"></i>
                            <?php echo htmlspecialchars($prod['categoria_nome'] ?? 'Geral'); ?>
                        </span>

                        <h2 class="card-titulo"><?php echo htmlspecialchars($prod['titulo']); ?></h2>

                        <div class="card-footer">
                            <div class="card-preco">
                                <span class="preco-rs">R$</span>
                                <span class="preco-val"><?php echo number_format($prod['preco'], 2, ',', '.'); ?></span>
                                <span class="preco-ciclo">/<?php
                                                            $labels = ['mensal' => 'mês', 'trimestral' => 'trim', 'semestral' => 'sem', 'anual' => 'ano', 'vitalicio' => 'único'];
                                                            echo $labels[$prod['ciclo']] ?? $prod['ciclo'];
                                                            ?></span>
                            </div>
                            <span class="card-btn">
                                <i class="fa-solid fa-plus"></i>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Aviso sem resultados -->
        <div id="avisoSemResultados">
            <i class="fa-solid fa-box-open"></i>
            <p>Nenhum plano encontrado para este filtro.</p>
        </div>

    </div><!-- /main-container -->
</main>

<!-- Botao Admin flutuante (ativo via body.admin-mode — para uso futuro) -->
<a href="admin/" class="admin-fab" title="Painel Admin">
    <i class="fa-solid fa-sliders"></i>
</a>

<!-- Botão Voltar ao Topo -->
<button class="btn-topo" id="btnTopo" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Voltar ao topo">
    <i class="fa-solid fa-arrow-up"></i>
</button>

<script>
    /* ── Estado dos filtros ──────────────────────────────────── */
    let categoriaFiltroAtual = 'todas';
    let cicloFiltroAtual = 'todos';
    let totalVisiveis = 0;

    /* ── Filtro de ciclo ────────────────────────────────────── */
    function filtrarCiclo(ciclo, botao) {
        cicloFiltroAtual = ciclo;
        document.querySelectorAll('.btn-ciclo').forEach(b => b.classList.remove('active'));
        botao.classList.add('active');
        filtrarVitrine();
    }

    /* ── Filtro de categoria ────────────────────────────────── */
    function filtrarCategoria(catId, botao) {
        categoriaFiltroAtual = catId;
        document.querySelectorAll('.btn-filtro').forEach(b => b.classList.remove('active'));
        botao.classList.add('active');
        filtrarVitrine();
    }

    /* ── Expandir/recolher categorias ───────────────────────── */
    function toggleCats(btn) {
        const scroll = document.getElementById('catsScroll');
        const icone = document.getElementById('iconVerMais');
        const texto = document.getElementById('textoVerMais');
        const aberto = scroll.classList.toggle('expandida');
        icone.className = aberto ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
        texto.textContent = aberto ? 'Recolher categorias' : 'Ver todas as categorias';
    }

    /* ── Limpar pesquisa ────────────────────────────────────── */
    function limparPesquisa() {
        document.getElementById('pesquisaInput').value = '';
        document.getElementById('btnLimparBusca').style.display = 'none';
        filtrarVitrine();
        document.getElementById('pesquisaInput').focus();
    }

    /* ── Motor de filtro principal ──────────────────────────── */
    function filtrarVitrine() {
        const pesquisa = document.getElementById('pesquisaInput').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.card-produto');
        totalVisiveis = 0;

        /* Mostra/oculta botão limpar */
        const btnLimpar = document.getElementById('btnLimparBusca');
        if (btnLimpar) btnLimpar.style.display = pesquisa ? 'block' : 'none';

        cards.forEach((card, i) => {
            const cat = card.dataset.categoria;
            const ciclo = card.dataset.ciclo;
            const titulo = card.dataset.titulo;

            const bateCat = categoriaFiltroAtual === 'todas' || cat === categoriaFiltroAtual;
            const bateCiclo = cicloFiltroAtual === 'todos' || ciclo === cicloFiltroAtual;
            const batePesquisa = titulo.includes(pesquisa);

            if (bateCat && bateCiclo && batePesquisa) {
                card.style.display = '';
                card.style.animationDelay = (totalVisiveis * 0.04) + 's';
                totalVisiveis++;
            } else {
                card.style.display = 'none';
            }
        });

        const label = document.getElementById('sectionLabel');
        if (label) {
            label.textContent = totalVisiveis + ' plano' + (totalVisiveis !== 1 ? 's' : '') + ' encontrado' + (totalVisiveis !== 1 ? 's' : '');
        }

        const aviso = document.getElementById('avisoSemResultados');
        aviso.style.display = totalVisiveis === 0 ? 'block' : 'none';
    }

    /* ── Acessibilidade: ativar card com teclado ─────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.card-produto').forEach(card => {
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    });

    /* ── Botão voltar ao topo ───────────────────────────────── */
    window.addEventListener('scroll', () => {
        const btn = document.getElementById('btnTopo');
        if (btn) btn.classList.toggle('visivel', window.scrollY > 350);
    }, { passive: true });

    window.onload = () => filtrarVitrine();
</script>

<?php require_once 'footer.php'; ?>