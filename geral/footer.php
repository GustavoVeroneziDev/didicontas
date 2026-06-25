<footer style="position:relative;z-index:1;background:var(--bg2);border-top:1px solid var(--border);padding:1.5rem 1.25rem;text-align:center;">
    <p style="font-size:0.75rem;color:var(--text3);line-height:1.7;">
        &copy; <?php echo date('Y'); ?> Didi Contas. Todos os direitos reservados.<br>
        <span style="font-size:0.7rem;">Todos os planos sao revendas de acesso compartilhado.</span>
    </p>
</footer>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DE DETALHES / WHATSAPP
════════════════════════════════════════════════════════════════ -->
<div id="modalDetalhes"
    style="display:none;"
    aria-modal="true"
    role="dialog"
    aria-labelledby="mdTitulo">

    <!-- Backdrop -->
    <div id="modalBackdrop" onclick="fecharModal()"
        style="position:fixed;inset:0;background:rgba(7,10,18,0.85);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:100;opacity:0;transition:opacity 0.3s;"></div>

    <!-- Sheet / Dialog -->
    <div id="modalSheet"
        style="
             position:fixed;
             bottom:0; left:0; right:0;
             z-index:101;
             max-width:520px;
             margin:0 auto;
             background:var(--surface);
             border-top:1px solid var(--border2);
             border-radius:22px 22px 0 0;
             padding:0;
             max-height:92dvh;
             overflow-y:auto;
             transform:translateY(100%);
             transition:transform 0.35s cubic-bezier(0.32,0.72,0,1);
             padding-bottom: env(safe-area-inset-bottom);
         ">

        <!-- Drag handle -->
        <div style="display:flex;justify-content:center;padding:0.85rem 0 0.5rem;">
            <div style="width:36px;height:4px;border-radius:4px;background:var(--border2);"></div>
        </div>

        <!-- Modal header row -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:0 1.25rem 0.75rem;">
            <span id="mdCategoria"
                style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;
                         color:#60A5FA;background:rgba(59,130,246,0.1);
                         padding:0.25rem 0.7rem;border-radius:100px;">CATEGORIA</span>
            <button onclick="fecharModal()"
                style="width:30px;height:30px;border-radius:50%;background:var(--surface2);
                           border:1px solid var(--border);color:var(--text2);
                           cursor:pointer;font-size:0.85rem;display:flex;align-items:center;justify-content:center;
                           flex-shrink:0;transition:background 0.2s;-webkit-tap-highlight-color:transparent;"
                aria-label="Fechar modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Image / placeholder -->
        <div id="mdContainerImagem" style="padding:0 1.25rem 1rem;"></div>

        <!-- Title & description -->
        <div style="padding:0 1.25rem;">
            <h3 id="mdTitulo"
                style="font-size:1.25rem;font-weight:800;color:var(--text);
                       line-height:1.3;letter-spacing:-0.3px;margin-bottom:0.5rem;"></h3>
            <p id="mdDescricao"
                style="font-size:0.875rem;color:var(--text2);line-height:1.65;
                      white-space:pre-line;margin-bottom:1.25rem;"></p>
        </div>

        <!-- Price bar -->
        <div style="margin:0 1.25rem 1.25rem;
                    background:var(--surface2);border:1px solid var(--border);
                    border-radius:12px;padding:1rem;
                    display:flex;flex-direction:column;align-items:center;justify-content:center;
                    gap:4px;text-align:center;">
            <div style="display:flex;align-items:baseline;gap:4px;line-height:1;">
                <span style="font-size:0.8rem;font-weight:800;color:#60A5FA;">R$</span>
                <span id="mdPreco" style="font-size:2.1rem;font-weight:800;color:var(--text);letter-spacing:-1.5px;line-height:1;">0,00</span>
            </div>
            <span id="mdCiclo" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;
                                      letter-spacing:0.8px;color:var(--text2);margin-top:2px;">/ mês</span>
        </div>

        <!-- Input + buttons -->
        <div style="padding:0 1.25rem 1.5rem;display:flex;flex-direction:column;gap:0.75rem;">

            <div>
                <label style="display:block;font-size:0.72rem;font-weight:700;text-transform:uppercase;
                              letter-spacing:0.8px;color:var(--text2);margin-bottom:0.45rem;">
                    Seu nome para o atendimento
                </label>
                <input type="text"
                    id="nomeClienteInput"
                    placeholder="Digite seu nome..."
                    autocomplete="given-name"
                    style="width:100%;background:var(--surface2);border:1px solid var(--border);
                              border-radius:10px;color:var(--text);
                              font-family:'Plus Jakarta Sans',sans-serif;font-size:0.95rem;
                              padding:0.8rem 1rem;outline:none;transition:border-color 0.2s,box-shadow 0.2s;
                              -webkit-appearance:none;appearance:none;"
                    onfocus="this.style.borderColor='var(--accent)'"
                    onblur="this.style.borderColor='var(--border)'">
                <p id="nomeErro" aria-live="assertive"
                    style="display:none;font-size:0.72rem;font-weight:600;color:#F87171;margin-top:0.35rem;">
                    Informe seu nome antes de continuar.
                </p>
            </div>

            <!-- Action buttons -->
            <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:0.6rem;">
                <button onclick="enviarMensagem('duvida')"
                    style="padding:0.85rem 0.5rem;background:var(--surface2);
                               border:1px solid var(--border);border-radius:10px;
                               color:var(--text2);font-family:'Plus Jakarta Sans',sans-serif;
                               font-size:0.8rem;font-weight:700;cursor:pointer;
                               transition:all 0.2s;-webkit-tap-highlight-color:transparent;
                               display:flex;align-items:center;justify-content:center;gap:6px;"
                    onmouseover="this.style.borderColor='var(--border2)';this.style.color='var(--text)'"
                    onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text2)'">
                    <i class="fa-regular fa-comment-dots" style="font-size:0.9rem;"></i>
                    Tirar Duvidas
                </button>

                <button onclick="enviarMensagem('comprar')"
                    style="padding:0.85rem 0.5rem;background:#10B981;border:none;
                               border-radius:10px;color:#fff;
                               font-family:'Plus Jakarta Sans',sans-serif;
                               font-size:0.85rem;font-weight:800;cursor:pointer;
                               transition:background 0.2s,transform 0.15s;
                               -webkit-tap-highlight-color:transparent;
                               display:flex;align-items:center;justify-content:center;gap:7px;
                               box-shadow:0 4px 16px rgba(16,185,129,0.3);"
                    onmouseover="this.style.background='#059669'"
                    onmouseout="this.style.background='#10B981'"
                    onmousedown="this.style.transform='scale(0.98)'"
                    onmouseup="this.style.transform='scale(1)'">
                    <i class="fa-brands fa-whatsapp" style="font-size:1.05rem;"></i>
                    Comprar Agora
                </button>
            </div>

        </div><!-- /input+buttons -->
    </div><!-- /sheet -->
</div>

<style>
    /* Desktop: centralizar como dialog, nao sheet */
    @media(min-width: 640px) {
        #modalSheet {
            bottom: auto !important;
            top: 50% !important;
            left: 50% !important;
            right: auto !important;
            transform: translate(-50%, calc(-50% + 30px)) !important;
            border-radius: 18px !important;
            border: 1px solid var(--border2) !important;
            max-height: 88vh;
        }

        #modalSheet.open {
            transform: translate(-50%, -50%) !important;
        }
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%       { transform: translateX(-7px); }
        40%       { transform: translateX(7px); }
        60%       { transform: translateX(-4px); }
        80%       { transform: translateX(4px); }
    }

    #toastWA {
        position: fixed;
        bottom: calc(5.5rem + env(safe-area-inset-bottom));
        left: 50%;
        transform: translateX(-50%) translateY(14px);
        background: #10B981;
        color: #fff;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 0.65rem 1.25rem;
        border-radius: 100px;
        z-index: 300;
        opacity: 0;
        transition: opacity 0.25s, transform 0.25s;
        pointer-events: none;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    #toastWA.visivel {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
</style>

<!-- Toast de confirmação WhatsApp -->
<div id="toastWA" role="status" aria-live="polite">
    <i class="fa-brands fa-whatsapp"></i>
    Abrindo WhatsApp...
</div>

<script>
    /* ═══════════════════════════════════════════════
       CONFIGURACAO
    ═══════════════════════════════════════════════ */
    const NUMERO_WHATSAPP = "556193750626";
    let produtoSelecionado = null;

    /* ═══════════════════════════════════════════════
       ABRIR MODAL
    ═══════════════════════════════════════════════ */
    function abrirDetalhes(produto) {
        produtoSelecionado = produto;

        /* Preenche textos */
        document.getElementById('mdCategoria').innerText =
            (produto.categoria_nome || 'Geral').toUpperCase();

        document.getElementById('mdTitulo').innerText = produto.titulo;
        document.getElementById('mdDescricao').innerText = produto.descricao || '';

        const precoFormatado = parseFloat(produto.preco).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('mdPreco').innerText = precoFormatado;

        const CICLO_MODAL = {
            mensal: '/ mês',
            trimestral: '/ trimestre',
            semestral: '/ semestre',
            anual: '/ ano',
            vitalicio: 'pagamento único'
        };
        document.getElementById('mdCiclo').innerText = CICLO_MODAL[produto.ciclo] || ('/ ' + produto.ciclo);

        /* Imagem ou placeholder */
        const cImg = document.getElementById('mdContainerImagem');
        if (produto.imagem_url && produto.imagem_url.trim() !== '') {
            cImg.innerHTML = `<img src="${produto.imagem_url}"
                                   alt="${produto.titulo}"
                                   style="width:100%;height:180px;object-fit:cover;
                                          border-radius:12px;border:1px solid var(--border);display:block;">`;
        } else {
            cImg.innerHTML = `
                <div style="width:100%;height:90px;background:var(--surface2);
                            border-radius:12px;border:1px dashed var(--border2);
                            display:flex;flex-direction:column;align-items:center;
                            justify-content:center;gap:6px;">
                    <i class="fa-solid fa-circle-nodes" style="color:var(--accent);font-size:1.4rem;opacity:0.7;"></i>
                    <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;
                                 letter-spacing:1px;color:var(--text2);">Plano Digital Premium</span>
                </div>`;
        }

        /* Limpa input */
        document.getElementById('nomeClienteInput').value = '';

        /* Exibe */
        const wrapper = document.getElementById('modalDetalhes');
        const backdrop = document.getElementById('modalBackdrop');
        const sheet = document.getElementById('modalSheet');

        wrapper.style.display = 'block';
        document.body.style.overflow = 'hidden';

        /* Força reflow antes de animar */
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                backdrop.style.opacity = '1';
                const isMobile = window.innerWidth < 640;
                if (isMobile) {
                    sheet.style.transform = 'translateY(0)';
                } else {
                    sheet.classList.add('open');
                    sheet.style.transform = 'translate(-50%, -50%)';
                }
            });
        });

        /* Foca o input após a transição terminar (mais confiável que setTimeout fixo) */
        sheet.addEventListener('transitionend', function focusOnOpen() {
            sheet.removeEventListener('transitionend', focusOnOpen);
            document.getElementById('nomeClienteInput').focus();
        }, { once: true });
    }

    /* ═══════════════════════════════════════════════
       FECHAR MODAL
    ═══════════════════════════════════════════════ */
    function fecharModal() {
        const backdrop = document.getElementById('modalBackdrop');
        const sheet = document.getElementById('modalSheet');
        const isMobile = window.innerWidth < 640;

        backdrop.style.opacity = '0';
        if (isMobile) {
            sheet.style.transform = 'translateY(100%)';
        } else {
            sheet.classList.remove('open');
            sheet.style.transform = 'translate(-50%, calc(-50% + 30px))';
        }

        setTimeout(() => {
            document.getElementById('modalDetalhes').style.display = 'none';
            document.body.style.overflow = '';
            produtoSelecionado = null;
            document.getElementById('nomeClienteInput').value = '';
        }, 350);
    }

    /* Fecha ao pressionar Escape */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') fecharModal();
    });

    /* ═══════════════════════════════════════════════
       ENVIAR MENSAGEM WHATSAPP
       Sem emojis — maxima compatibilidade de aparelhos
    ═══════════════════════════════════════════════ */
    function enviarMensagem(tipoAcao) {
        const nomeUser = document.getElementById('nomeClienteInput').value.trim();

        if (!nomeUser) {
            const inp = document.getElementById('nomeClienteInput');
            const erro = document.getElementById('nomeErro');
            inp.style.borderColor = '#EF4444';
            inp.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.2)';
            inp.style.animation = 'shake 0.4s ease';
            if (erro) erro.style.display = 'block';
            inp.focus();
            setTimeout(() => {
                inp.style.borderColor = 'var(--border)';
                inp.style.boxShadow = 'none';
                inp.style.animation = '';
                if (erro) erro.style.display = 'none';
            }, 2500);
            return;
        }

        if (!produtoSelecionado) return;

        const precoFormatado = parseFloat(produtoSelecionado.preco).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        const CICLO_WA = {
            mensal: 'Mensal',
            trimestral: 'Trimestral',
            semestral: 'Semestral',
            anual: 'Anual',
            vitalicio: 'Vitalício'
        };
        const cicloTexto = CICLO_WA[produtoSelecionado.ciclo] || produtoSelecionado.ciclo;

        const interesse = tipoAcao === 'comprar' ?
            'Comprar o plano' :
            'Tirar duvidas sobre o plano';

        const observacao = tipoAcao === 'comprar' ?
            'Vi o produto no site e quero prosseguir com a compra.' :
            'Vi o produto no site e gostaria de mais informacoes antes de decidir.';

        /* Mensagem formatada sem emojis, compativel com todos os aparelhos */
        const textoMensagem =
            `*Cliente:* \`${nomeUser}\`
*Produto:* \`${produtoSelecionado.titulo}\`
*Plano:* \`${cicloTexto}\`
*Valor:* \`R$ ${precoFormatado}\`
*Interesse:* \`${interesse}\`

*OBSERVAÇÃO:*
\`${observacao}\``;

        const urlFinal = `https://wa.me/${NUMERO_WHATSAPP}?text=${encodeURIComponent(textoMensagem)}`;

        /* Toast de confirmação */
        const toast = document.getElementById('toastWA');
        if (toast) {
            toast.classList.add('visivel');
            setTimeout(() => toast.classList.remove('visivel'), 2500);
        }

        window.open(urlFinal, '_blank', 'noopener,noreferrer');
        fecharModal();
    }
</script>
</body>

</html>