<?php
// Inclui a conexão que está dentro da pasta config
require_once '../config/conexao.php';

// Inclui o header que está dentro da pasta geral
require_once '../geral/header.php';
// Busca todas as categorias cadastradas para criar os botões de filtro
$stmt_cat = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Busca todos os produtos ativos juntamente com o nome da categoria deles
$query_prod = "SELECT p.*, c.nome AS categoria_nome 
               FROM produtos p 
               LEFT JOIN categorias c ON p.categoria_id = c.id 
               WHERE p.status = 'ativo' 
               ORDER BY p.id DESC";
$stmt_prod = $pdo->query($query_prod);
$produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container mx-auto px-4 py-8 max-w-7xl">

    <div class="bg-white rounded-2xl shadow-sm p-6 mb-8 border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full md:max-w-md">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="pesquisaInput" onkeyup="filtrarVitrine()" placeholder="O que você está procurando hoje?" class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition bg-gray-50/50">
        </div>

        <div class="flex flex-wrap gap-2 w-full md:w-auto justify-start md:justify-end">
            <button onclick="filtrarCategoria('todas', this)" class="btn-filtro active bg-blue-600 text-white font-medium px-4 py-2 rounded-xl text-sm transition shadow-sm cursor-pointer">
                Todas
            </button>
            <?php foreach ($categorias as $cat): ?>
                <button onclick="filtrarCategoria('cat-<?php echo $cat['id']; ?>', this)" class="btn-filtro bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 font-medium px-4 py-2 rounded-xl text-sm transition cursor-pointer flex items-center gap-2">
                    <i class="<?php echo htmlspecialchars($cat['icone']); ?> text-blue-500"></i>
                    <?php echo htmlspecialchars($cat['nome']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="gridProdutos" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (count($produtos) > 0): ?>
            <?php foreach ($produtos as $prod): ?>
                <div class="card-produto bg-white rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 flex flex-col overflow-hidden"
                    data-categoria="cat-<?php echo $prod['categoria_id']; ?>"
                    data-titulo="<?php echo strtolower(htmlspecialchars($prod['titulo'])); ?>">

                    <div class="p-5 flex-get flex flex-col h-full">
                        <span class="inline-block text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-md mb-3 self-start">
                            <?php echo htmlspecialchars($prod['categoria_nome'] ?? 'Geral'); ?>
                        </span>

                        <h2 class="text-xl font-bold text-gray-900 mb-2 truncate-2-lines"><?php echo htmlspecialchars($prod['titulo']); ?></h2>

                        <p class="text-gray-500 text-sm mb-4 line-clamp-3 flex-grow"><?php echo nl2br(htmlspecialchars($prod['descricao'])); ?></p>

                        <div class="mt-auto pt-4 border-t border-gray-50 flex items-baseline gap-1">
                            <span class="text-xs text-gray-400 font-semibold">R$</span>
                            <span class="text-2xl font-black text-gray-950"><?php echo number_format($prod['preco'], 2, ',', '.'); ?></span>
                            <span class="text-xs text-gray-400">/acesso</span>
                        </div>
                    </div>

                    <div class="bg-gray-50/70 px-5 py-4 border-t border-gray-50 grid grid-cols-2 gap-2.5">
                        <button onclick="abrirModalWhatsapp('saber_mais', '<?php echo addslashes($prod['titulo']); ?>', '<?php echo number_format($prod['preco'], 2, ',', '.'); ?>')" class="text-xs font-bold text-gray-600 hover:text-gray-900 border border-gray-200 bg-white hover:bg-gray-100 py-2.5 rounded-xl transition text-center cursor-pointer">
                            Saber Mais
                        </button>
                        <button onclick="abrirModalWhatsapp('comprar', '<?php echo addslashes($prod['titulo']); ?>', '<?php echo number_format($prod['preco'], 2, ',', '.'); ?>')" class="text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl shadow-sm transition text-center cursor-pointer flex items-center justify-center gap-1">
                            <i class="fa-solid fa-cart-shopping"></i> Comprar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-12 bg-white rounded-2xl border border-dashed border-gray-200">
                <i class="fa-solid fa-box-open text-gray-300 text-5xl mb-3"></i>
                <p class="text-gray-500 font-medium">Nenhum produto cadastrado ou ativo no momento.</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="avisoSemResultados" class="hidden text-center py-12 bg-white rounded-2xl border border-gray-200 mt-6">
        <i class="fa-solid fa-magnifying-glass text-gray-300 text-5xl mb-3"></i>
        <p class="text-gray-500 font-medium">Nenhum produto corresponde à sua pesquisa ou filtro.</p>
    </div>

</main>

<script>
    let categoriaFiltroAtual = 'todas';

    function filtrarCategoria(categoriaId, botaoClicado) {
        categoriaFiltroAtual = categoriaId;

        // Altera a classe visual dos botões
        document.querySelectorAll('.btn-filtro').forEach(btn => {
            btn.classList.remove('active', 'bg-blue-600', 'text-white', 'shadow-sm');
            btn.classList.add('bg-white', 'text-gray-600', 'border', 'border-gray-200');
        });

        botaoClicado.classList.remove('bg-white', 'text-gray-600', 'border', 'border-gray-200');
        botaoClicado.classList.add('active', 'bg-blue-600', 'text-white', 'shadow-sm');

        filtrarVitrine();
    }

    function filtrarVitrine() {
        const textoPesquisa = document.getElementById('pesquisaInput').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.card-produto');
        let itensVisiveis = 0;

        cards.forEach(card => {
            const catCard = card.getAttribute('data-categoria');
            const tituloCard = card.getAttribute('data-titulo');

            const bateCategoria = (categoriaFiltroAtual === 'todas' || catCard === categoriaFiltroAtual);
            const batePesquisa = (tituloCard.includes(textoPesquisa));

            if (bateCategoria && batePesquisa) {
                card.classList.remove('hidden');
                itensVisiveis++;
            } else {
                card.classList.add('hidden');
            }
        });

        // Mostra mensagem amigável caso a busca suma com todos os cards
        const aviso = document.getElementById('avisoSemResultados');
        if (itensVisiveis === 0 && cards.length > 0) {
            aviso.classList.remove('hidden');
        } else {
            aviso.classList.add('hidden');
        }
    }
</script>

<?php
require_once '../geral/footer.php';
?>