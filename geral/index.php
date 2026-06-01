<?php
require_once '../config/conexao.php';
require_once 'header.php';

// Busca categorias
$stmt_cat = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Busca produtos ativos
$query_prod = "SELECT p.*, c.nome AS categoria_nome 
               FROM produtos p 
               LEFT JOIN categorias c ON p.categoria_id = c.id 
               WHERE p.status = 'ativo' 
               ORDER BY p.id DESC";
$stmt_prod = $pdo->query($query_prod);
$produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-6 max-w-7xl">

    <div class="bg-white rounded-2xl shadow-xs p-4 mb-6 border border-gray-100 flex flex-col gap-4">
        
        <div class="flex flex-col sm:flex-row gap-3 justify-between items-center">
            <div class="relative w-full sm:max-w-xs">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </span>
                <input type="text" id="pesquisaInput" onkeyup="filtrarVitrine()" placeholder="Pesquisar..." class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-gray-50/50">
            </div>

            <div class="flex bg-gray-100 p-1 rounded-xl border border-gray-200 w-full sm:w-auto justify-center">
                <button onclick="filtrarCiclo('mensal', this)" class="btn-ciclo active bg-white text-gray-900 font-bold px-5 py-1.5 rounded-lg text-xs shadow-xs transition cursor-pointer w-1/2 sm:w-auto">
                    Planos Mensais
                </button>
                <button onclick="filtrarCiclo('anual', this)" class="btn-ciclo text-gray-500 font-medium px-5 py-1.5 rounded-lg text-xs transition cursor-pointer w-1/2 sm:w-auto">
                    Planos Anuais
                </button>
            </div>
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-none snap-x">
            <button onclick="filtrarCategoria('todas', this)" class="btn-filtro active bg-blue-600 text-white font-medium px-4 py-1.5 rounded-xl text-xs transition shrink-0 cursor-pointer">
                Todas
            </button>
            <?php foreach ($categorias as $cat): ?>
                <button onclick="filtrarCategoria('cat-<?php echo $cat['id']; ?>', this)" class="btn-filtro bg-white text-gray-600 border border-gray-100 font-medium px-4 py-1.5 rounded-xl text-xs transition shrink-0 cursor-pointer flex items-center gap-1.5">
                    <i class="<?php echo htmlspecialchars($cat['icone']); ?> text-blue-500"></i>
                    <?php echo htmlspecialchars($cat['nome']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="gridProdutos" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-6">
        <?php foreach ($produtos as $prod): ?>
            <div onclick="abrirDetalhes(<?php echo htmlspecialchars(json_encode($prod)); ?>)" 
                 class="card-produto bg-white rounded-xl shadow-xs hover:shadow-md border border-gray-100 flex flex-col overflow-hidden cursor-pointer transition transform active:scale-98"
                 data-categoria="cat-<?php echo $prod['categoria_id']; ?>" 
                 data-ciclo="<?php echo $prod['ciclo']; ?>"
                 data-titulo="<?php echo strtolower(htmlspecialchars($prod['titulo'])); ?>">
                
                <div class="p-3 flex flex-col flex-grow justify-between">
                    <div>
                        <span class="inline-block text-[9px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded mb-1">
                            <?php echo htmlspecialchars($prod['categoria_nome'] ?? 'Geral'); ?>
                        </span>
                        <h2 class="text-sm md:text-base font-bold text-gray-900 leading-tight line-clamp-2"><?php echo htmlspecialchars($prod['titulo']); ?></h2>
                    </div>

                    <div class="mt-2 pt-2 border-t border-gray-50 flex justify-between items-center">
                        <div class="flex items-baseline gap-0.5">
                            <span class="text-[9px] text-gray-400 font-bold">R$</span>
                            <span class="text-base md:text-xl font-black text-gray-900"><?php echo number_format($prod['preco'], 2, ',', '.'); ?></span>
                            <span class="text-[9px] text-gray-400 ml-0.5">/<?php echo $prod['ciclo'] == 'mensal' ? 'mês' : 'ano'; ?></span>
                        </div>
                        <span class="text-blue-600 bg-blue-50 w-6 h-6 rounded-lg flex items-center justify-center text-xs">
                            <i class="fa-solid fa-plus"></i>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="avisoSemResultados" class="hidden text-center py-12 bg-white rounded-2xl border border-gray-100 mt-6">
        <i class="fa-solid fa-box-open text-gray-300 text-4xl mb-2"></i>
        <p class="text-gray-500 text-sm font-medium">Nenhum plano encontrado para este filtro.</p>
    </div>

</main>

<script>
    let categoriaFiltroAtual = 'todas';
    let cicloFiltroAtual = 'mensal'; // Padrão inicial

    function filtrarCiclo(ciclo, botao) {
        cicloFiltroAtual = ciclo;
        document.querySelectorAll('.btn-ciclo').forEach(btn => {
            btn.classList.remove('active', 'bg-white', 'text-gray-900', 'font-bold', 'shadow-xs');
            btn.classList.add('text-gray-500', 'font-medium');
        });
        botao.classList.add('active', 'bg-white', 'text-gray-900', 'font-bold', 'shadow-xs');
        botao.classList.remove('text-gray-500', 'font-medium');
        filtrarVitrine();
    }

    function filtrarCategoria(catId, botao) {
        categoriaFiltroAtual = catId;
        document.querySelectorAll('.btn-filtro').forEach(btn => {
            btn.classList.remove('active', 'bg-blue-600', 'text-white');
            btn.classList.add('bg-white', 'text-gray-600', 'border', 'border-gray-100');
        });
        botao.classList.add('active', 'bg-blue-600', 'text-white');
        botao.classList.remove('bg-white', 'text-gray-600', 'border', 'border-gray-100');
        filtrarVitrine();
    }

    function filtrarVitrine() {
        const pesquisa = document.getElementById('pesquisaInput').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.card-produto');
        let visiveis = 0;

        cards.forEach(card => {
            const cat = card.getAttribute('data-categoria');
            const ciclo = card.getAttribute('data-ciclo');
            const titulo = card.getAttribute('data-titulo');

            const bateCat = (categoriaFiltroAtual === 'todas' || cat === categoriaFiltroAtual);
            const bateCiclo = (ciclo === cicloFiltroAtual);
            const batePesquisa = (titulo.includes(pesquisa));

            if(bateCat && bateCiclo && batePesquisa) {
                card.classList.remove('hidden');
                visiveis++;
            } else {
                card.classList.add('hidden');
            }
        });

        document.getElementById('avisoSemResultados').classList.toggle('hidden', visiveis > 0);
    }

    // Inicializa filtrando por mensal no carregamento
    window.onload = () => { filtrarVitrine(); };
</script>

<?php require_once 'footer.php'; ?>