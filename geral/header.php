<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0B0F1A">
    <title>Didi Contas - Premium Links & Assinaturas</title>

    <!-- Tailwind via CDN (v4 browser build) -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts: Clash Display + Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="icon" href="logo.ico" type="image/png">

    <style>
        /* ─── Design Tokens ─────────────────────────────────────── */
        :root {
            --bg: #0B0F1A;
            --bg2: #111827;
            --surface: #161D2E;
            --surface2: #1E2840;
            --border: rgba(255, 255, 255, 0.07);
            --border2: rgba(255, 255, 255, 0.12);
            --accent: #3B82F6;
            --accent2: #6366F1;
            --accent-glow: rgba(59, 130, 246, 0.15);
            --green: #10B981;
            --green-dim: rgba(16, 185, 129, 0.12);
            --text: #F1F5F9;
            --text2: #94A3B8;
            --text3: #64748B;
            --yellow: #F59E0B;
            --r: 14px;
            --r-sm: 8px;
            color-scheme: dark;
        }

        /* ─── Base ──────────────────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            /* Safe area for notched phones */
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }

        /* ─── Subtle Background Pattern ────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80vw 60vh at 60% -10%, rgba(59, 130, 246, 0.07) 0%, transparent 60%),
                radial-gradient(ellipse 50vw 40vh at 0% 80%, rgba(99, 102, 241, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* ─── Text Selection ───────────────────────────────────── */
        ::selection {
            background: rgba(59, 130, 246, 0.35);
            color: #fff;
        }

        /* ─── Focus Visible (teclado/acessibilidade) ────────────── */
        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ─── Reduced Motion ────────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ─── Scrollbar ─────────────────────────────────────────── */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--surface2);
            border-radius: 4px;
        }

        /* ─── Header ────────────────────────────────────────────── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 90;
            background: rgba(11, 15, 26, 0.9);
            backdrop-filter: blur(20px) saturate(1.5);
            -webkit-backdrop-filter: blur(20px) saturate(1.5);
            border-bottom: 1px solid var(--border);
        }

        .header-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 1.25rem;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
            text-decoration: none;
            flex-shrink: 0;
            line-height: 1;
        }

        .logo span {
            color: var(--yellow);
        }

        .header-tagline {
            font-size: 0.7rem;
            color: var(--text3);
            letter-spacing: 0.5px;
            display: none;
        }

        @media(min-width: 640px) {
            .header-tagline {
                display: block;
            }
        }

        .btn-wa-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--green);
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.55rem 1rem;
            border-radius: 100px;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
            flex-shrink: 0;
            letter-spacing: 0.2px;
        }

        .btn-wa-header:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-wa-header .fa-whatsapp {
            font-size: 1rem;
        }

        /* ─── Page wrapper ──────────────────────────────────────── */
        .page-wrap {
            position: relative;
            z-index: 1;
            flex: 1;
        }
    </style>
</head>

<body>
    <header class="site-header">
        <div class="header-inner">
            <div>
                <a href="/" class="logo">didi<span>contas</span></a>
                <p class="header-tagline">Os melhores planos Pro e IAs do mercado</p>
            </div>
            <a href="https://wa.me/556193750626" target="_blank" rel="noopener" class="btn-wa-header">
                <i class="fa-brands fa-whatsapp"></i>
                <span>Atendimento</span>
            </a>
        </div>
    </header>
    <div class="page-wrap">