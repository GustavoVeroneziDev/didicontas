<footer class="bg-gray-900 text-gray-400 py-8 mt-auto border-t border-gray-800">
    <div class="container mx-auto px-4 text-center">
        <p class="font-medium text-gray-300">&copy; <?php echo date('Y'); ?> Didi Contas. Todos os direitos reservados.</p>
        <p class="text-xs mt-2 text-gray-500">Desenvolvido com foco em alta performance e conversão.</p>
    </div>
</footer>

<div id="modalWhatsapp" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 mx-4 transform scale-95 transition-transform duration-300">
        <h3 class="text-xl font-bold text-gray-900 mb-2">Quase lá!</h3>
        <p class="text-gray-600 text-sm mb-4">Insira seu nome abaixo para iniciarmos o seu atendimento personalizado no WhatsApp.</p>

        <input type="text" id="inputNomeUsuario" placeholder="Digite seu nome..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition mb-4">

        <div class="flex gap-3 justify-end">
            <button onclick="fecharModal()" class="px-4 py-2 text-gray-500 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
            <button onclick="confirmarRedirecionamento()" class="px-5 py-2 bg-emerald-500 hover:bg-emerald-600 text-white font-medium rounded-lg shadow transition">Ir para o WhatsApp</button>
        </div>
    </div>
</div>

<script>
    // Altere para o número de WhatsApp do Didicontas (com DDD e Código do País. Ex: 5511999999999)
    const NUMERO_WHATSAPP = "5500000000000";

    let dadosProdutoAtual = null;

    function abrirModalWhatsapp(acao, produto, preco) {
        // Guarda temporariamente os dados do card clicado
        dadosProdutoAtual = {
            acao,
            produto,
            preco
        };

        const modal = document.getElementById('modalWhatsapp');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);

        document.getElementById('inputNomeUsuario').focus();
    }

    function fecharModal() {
        const modal = document.getElementById('modalWhatsapp');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            dadosProdutoAtual = null;
            document.getElementById('inputNomeUsuario').value = "";
        }, 300);
    }

    function confirmarRedirecionamento() {
        const nomeUsuario = document.getElementById('inputNomeUsuario').value.trim();

        if (!nomeUsuario) {
            alert("Por favor, digite o seu nome para continuar.");
            return;
        }

        // Define o verbo com base na ação escolhida
        const intencao = dadosProdutoAtual.acao === 'comprar' ? 'comprar' : 'saber mais sobre';

        // Monta o texto da mensagem
        const mensagem = `Olá, sou o ${nomeUsuario}, quero ${intencao} o produto: ${dadosProdutoAtual.produto} - Valor: R$ ${dadosProdutoAtual.preco}`;

        // Codifica o texto para formato de URL segura
        const mensagemCodificada = encodeURIComponent(mensagem);

        // Constrói o link final do WhatsApp
        const urlFinal = `https://wa.me/${NUMERO_WHATSAPP}?text=${mensagemCodificada}`;

        // Abre em uma nova aba e fecha o modal
        window.open(urlFinal, '_blank');
        fecharModal();
    }

    // Permite enviar ao apertar a tecla "Enter" dentro do input
    document.getElementById('inputNomeUsuario').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            confirmarRedirecionamento();
        }
    });
</script>
</body>

</html>