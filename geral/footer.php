<footer class="bg-gray-900 text-gray-500 py-6 mt-auto text-center text-xs border-t border-gray-800">
    <p>&copy; <?php echo date('Y'); ?> Didi Contas. Todos os direitos reservados.</p>
</footer>

<div id="modalDetalhes" class="fixed inset-0 bg-slate-900/40 backdrop-blur-md z-50 flex items-end sm:items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto transform translate-y-10 sm:translate-y-0 sm:scale-95 transition-all duration-300">

        <div class="flex justify-between items-center mb-4">
            <span id="mdCategoria" class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-md">CATEGORIA</span>
            <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-600 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="mdContainerImagem" class="mb-4">
            <div id="mdPlaceholder" class="w-full h-32 bg-gray-50 rounded-xl flex flex-col items-center justify-center text-gray-300 border border-dashed border-gray-200">
                <i class="fa-solid fa-cloud-lightning text-2xl text-blue-400 mb-1"></i>
                <span class="text-[10px] font-medium text-gray-400">Plano Digital Premium Ativo</span>
            </div>
        </div>

        <h3 id="mdTitulo" class="text-xl font-black text-gray-900 mb-2 leading-tight">Título do Produto</h3>
        <p id="mdDescricao" class="text-gray-600 text-sm mb-6 whitespace-pre-line leading-relaxed">Descrição detalhada vai aqui...</p>

        <div class="bg-gray-50 rounded-xl p-4 mb-4 flex justify-between items-center border border-gray-100">
            <span class="text-xs font-bold text-gray-500">Investimento do Plano:</span>
            <div class="flex items-baseline gap-0.5">
                <span class="text-xs text-blue-600 font-bold">R$</span>
                <span id="mdPreco" class="text-2xl font-black text-gray-900">0,00</span>
                <span id="mdCiclo" class="text-xs text-gray-400 font-medium ml-1">/mês</span>
            </div>
        </div>

        <div class="space-y-3">
            <label class="block text-xs font-bold text-gray-700 tracking-wide">SEU NOME:</label>
            <input type="text" id="nomeClienteInput" placeholder="Digite seu nome para o atendimento..." class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm transition">

            <div class="grid grid-cols-2 gap-2 pt-2">
                <button onclick="enviarMensagem('duvida')" class="py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition cursor-pointer text-center">
                    <i class="fa-regular fa-comment-dots mr-1"></i> Tirar Dúvidas
                </button>
                <button onclick="enviarMensagem('comprar')" class="py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer text-center flex items-center justify-center gap-1.5">
                    <i class="fa-brands fa-whatsapp text-sm"></i> Comprar Agora
                </button>
            </div>
        </div>

    </div>
</div>

<script>
    const NUMERO_WHATSAPP = "556193750626"; // Número oficial configurado
    let produtoSelecionado = null;

    function abrirDetalhes(produto) {
        produtoSelecionado = produto;

        // Preenche os campos textuais do Modal
        document.getElementById('mdCategoria').innerText = (produto.categoria_nome || 'GERAL').toUpperCase();
        document.getElementById('mdTitulo').innerText = produto.titulo;
        document.getElementById('mdDescricao').innerText = produto.descricao;

        // Formata o preço decimal vindo do banco
        const precoFormatado = parseFloat(produto.preco).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('mdPreco').innerText = precoFormatado;
        document.getElementById('mdCiclo').innerText = produto.ciclo === 'mensal' ? '/mês' : '/ano';

        // Gerencia o bloco de imagem (Se houver imagem cadastrada, exibe. Se não, mostra o placeholder)
        const containerImagem = document.getElementById('mdContainerImagem');
        if (produto.imagem_url && produto.imagem_url.trim() !== "") {
            containerImagem.innerHTML = `<img src="${produto.imagem_url}" class="w-full h-44 object-cover rounded-xl border border-gray-100" alt="${produto.titulo}">`;
        } else {
            containerImagem.innerHTML = `
                    <div class="w-full h-24 bg-gradient-to-br from-slate-50 to-gray-100 rounded-xl flex flex-col items-center justify-center text-gray-300 border border-gray-200/60">
                        <i class="fa-solid fa-circle-nodes text-2xl text-blue-500/70 mb-1 animate-pulse"></i>
                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase">Conexão Premium Ativa</span>
                    </div>`;
        }

        // Abre o modal com animações fluidas
        const modal = document.getElementById('modalDetalhes');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('translate-y-10', 'sm:scale-95');
        }, 10);

        document.getElementById('nomeClienteInput').focus();
    }

    function fecharModal() {
        const modal = document.getElementById('modalDetalhes');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('translate-y-10', 'sm:scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            produtoSelecionado = null;
            document.getElementById('nomeClienteInput').value = "";
        }, 300);
    }

    function enviarMensagem(tipoAcao) {
        const nomeUser = document.getElementById('nomeClienteInput').value.trim();
        if (!nomeUser) {
            alert("Por favor, digite o seu nome antes de prosseguir!");
            return;
        }

        const rotuloAcao = tipoAcao === 'comprar' ? '🛒 QUERO COMPRAR' : '💡 TENHO DÚVIDAS';
        const precoFormatado = parseFloat(produtoSelecionado.preco).toLocaleString('pt-BR', {
            minimumFractionDigits: 2
        });
        const textoMensagem =
            `\u{1F44B} Olá, Didi! Estou no seu site e achei um plano excelente.

\u{1F4CC} *PRODUTO:* ${produtoSelecionado.titulo}
\u{23F1} *CICLO:* ${cicloTexto}
\u{1F4B0} *VALOR:* R$ ${precoFormatado}
\u{1F3AF} *INTENÇÃO:* ${rotuloAcao}

\u{1F464} *MEU NOME:* ${nomeUser}

Poderia me atender para fecharmos?`;

        const urlFinal = `https://wa.me/${NUMERO_WHATSAPP}?text=${encodeURIComponent(textoMensagem)}`;
        window.open(urlFinal, '_blank');
        fecharModal();
    }

    // Fecha o modal ao clicar fora dele (na área acrílica)
    document.getElementById('modalDetalhes').addEventListener('click', function(e) {
        if (e.target === this) fecharModal();
    });
</script>
</body>

</html>