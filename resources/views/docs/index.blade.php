<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $menu[$slug] ?? $slug }} — Documentación sientiaERP</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', 'Inter', system-ui, sans-serif;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ── Header fijo ── */
        #docs-header {
            height: 56px;
            flex-shrink: 0;
            z-index: 50;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }

        /* ── Zona principal que ocupa el resto del viewport ── */
        #docs-body {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* ── Sidebar con scroll propio ── */
        #docs-sidebar {
            width: 228px;
            flex-shrink: 0;
            overflow-y: auto;
            background: #f8fafc;
            border-right: 1px solid #e5e7eb;
            padding: 1.25rem 0.875rem;
        }

        /* ── Área de contenido con scroll propio ── */
        #docs-content {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem 2rem 3rem;
        }

        /* Scrollbar personalizada */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

        /* ── Prose / Markdown ── */
        .prose { color: #1f2937; line-height: 1.75; max-width: 860px; }
        .prose h1 { font-size: 1.875rem; font-weight: 800; padding-bottom: 0.75rem; border-bottom: 2px solid #e5e7eb; margin: 0 0 1.5rem; color: #111827; }
        .prose h2 { font-size: 1.25rem; font-weight: 700; color: #7c3aed; margin: 2.5rem 0 0.75rem; padding-bottom: 0.375rem; border-bottom: 1px solid #ede9fe; }
        .prose h3 { font-size: 1rem; font-weight: 700; color: #111827; margin: 1.5rem 0 0.5rem; }
        .prose h4 { font-size: 0.9rem; font-weight: 700; color: #374151; margin: 1.25rem 0 0.375rem; }
        .prose p { margin: 0.625rem 0; color: #374151; }
        .prose ul, .prose ol { padding-left: 1.5rem; margin: 0.625rem 0; }
        .prose li { margin: 0.2rem 0; color: #374151; }
        .prose li > ul, .prose li > ol { margin: 0.2rem 0; }
        .prose a { color: #7c3aed; font-weight: 500; text-decoration: none; }
        .prose a:hover { text-decoration: underline; }
        .prose strong { color: #111827; font-weight: 700; }
        .prose em { color: #374151; }
        .prose code {
            color: #db2777;
            background: #fdf2f8;
            padding: 0.1rem 0.35rem;
            border-radius: 0.25rem;
            font-size: 0.82em;
            font-weight: 600;
            font-family: 'Fira Code', 'Consolas', monospace;
        }
        .prose pre {
            background: #0f172a;
            color: #e2e8f0;
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem;
            overflow-x: auto;
            border: 1px solid #1e293b;
            margin: 1rem 0;
        }
        .prose pre code {
            color: inherit;
            background: transparent;
            padding: 0;
            font-size: 0.85em;
        }
        .prose blockquote {
            border-left: 4px solid #7c3aed;
            background: #f5f3ff;
            border-radius: 0 0.625rem 0.625rem 0;
            padding: 0.75rem 1.25rem;
            margin: 1rem 0;
            font-style: normal;
        }
        .prose blockquote p { color: #4c1d95; margin: 0; }
        .prose blockquote strong { color: #4c1d95; }
        .prose table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin: 1rem 0; display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .prose thead tr { background: #f3f4f6; }
        .prose th { font-weight: 700; text-align: left; padding: 0.6rem 0.875rem; border: 1px solid #e5e7eb; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; }
        .prose td { padding: 0.6rem 0.875rem; border: 1px solid #e5e7eb; vertical-align: top; }
        .prose tbody tr:nth-child(even) { background: #f9fafb; }
        .prose tbody tr:hover { background: #f3f0ff; }
        .prose img { border-radius: 0.75rem; max-width: 100%; height: auto; }
        .prose hr { border: none; border-top: 2px solid #f3f4f6; margin: 2.5rem 0; }

        /* Nav item activo en sidebar */
        .nav-item-active {
            background: #7c3aed;
            color: white !important;
            box-shadow: 0 4px 12px rgba(124,58,237,0.25);
        }
        .nav-item-active svg { color: white; }

        /* Responsive: sidebar oculto en móvil */
        @media (max-width: 767px) {
            #docs-sidebar { display: none; }
            #docs-content { padding: 1rem; }
        }

        /* Print */
        @media print {
            #docs-header, #docs-sidebar, .no-print { display: none !important; }
            #docs-body { display: block; overflow: visible; }
            #docs-content { overflow: visible; padding: 0; }
            .prose { max-width: 100%; }
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">

    {{-- ════════════ HEADER FIJO (no sticky, layout flex) ════════════ --}}
    <header id="docs-header" class="flex items-center px-4 sm:px-6 gap-3">

        {{-- Volver a la app --}}
        <a href="{{ url('/admin') }}"
           class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-violet-600 transition-colors shrink-0 no-print"
           title="Volver al panel de administración">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span class="hidden sm:inline">Panel</span>
        </a>

        <div class="w-px h-5 bg-gray-200 shrink-0"></div>

        {{-- Icono + Título --}}
        <div class="p-1.5 bg-violet-100 text-violet-600 rounded-lg shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
        </div>

        <div class="min-w-0 flex-1">
            <span class="text-sm font-black text-gray-900 block leading-tight">sientiaERP <span class="text-violet-600">— Documentación</span></span>
            <span class="text-xs text-gray-400 truncate block leading-tight">{{ $menu[$slug] ?? $slug }}</span>
        </div>

        {{-- Acciones --}}
        <div class="flex items-center gap-2 shrink-0">
            <button onclick="window.print()"
                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-500 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-all no-print">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir / PDF
            </button>

            {{-- Botón móvil para sidebar --}}
            <button @click="mobileMenuOpen = !mobileMenuOpen"
                class="md:hidden inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-600 bg-violet-50 hover:bg-violet-100 rounded-lg transition-all no-print">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Índice
            </button>
        </div>
    </header>

    {{-- ════════════ MENÚ MÓVIL DROPDOWN ════════════ --}}
    <div x-show="mobileMenuOpen" x-transition x-cloak
        class="md:hidden fixed inset-0 z-40 bg-black/40 no-print"
        @click.self="mobileMenuOpen = false">
        <nav class="absolute left-0 top-14 bottom-0 w-72 bg-white overflow-y-auto shadow-2xl p-4 space-y-1">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 px-1">Manuales</p>
            @foreach($menu as $itemSlug => $itemTitle)
                <a href="{{ route('docs', $itemSlug) }}" @click="mobileMenuOpen = false"
                   class="flex items-center gap-2.5 px-3 py-2.5 text-sm font-semibold rounded-xl transition-all {{ $slug === $itemSlug ? 'nav-item-active' : 'text-gray-600 hover:bg-gray-100 hover:text-violet-700' }}">
                    <span>{{ $itemTitle }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ════════════ CUERPO PRINCIPAL ════════════ --}}
    <div id="docs-body">

        {{-- ── SIDEBAR DE ESCRITORIO ── --}}
        <nav id="docs-sidebar" class="no-print">
            <div class="space-y-4">

                {{-- Navegación de documentos --}}
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Manuales</p>
                    <div class="space-y-0.5">
                        @foreach($menu as $itemSlug => $itemTitle)
                            <a href="{{ route('docs', $itemSlug) }}"
                               class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold rounded-xl transition-all duration-150 {{ $slug === $itemSlug ? 'nav-item-active' : 'text-gray-600 hover:bg-white hover:text-violet-700 hover:shadow-sm' }}">
                                <span>{{ $itemTitle }}</span>
                                @if($slug === $itemSlug)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-80 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Idioma activo --}}
                <div class="p-3 bg-gradient-to-br from-violet-500 to-indigo-600 rounded-2xl text-white shadow-md">
                    <p class="text-[9px] font-black opacity-70 uppercase tracking-widest mb-0.5">Idioma</p>
                    <p class="text-sm font-bold flex items-center gap-1.5">
                        <span>{{ app()->getLocale() === 'es' ? '🇪🇸' : '🇬🇧' }}</span>
                        {{ app()->getLocale() === 'es' ? 'Español' : 'English' }}
                    </p>
                </div>

                {{-- Apoya el proyecto --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-3 shadow-sm">
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Apoya el Proyecto</p>
                    <a href="https://www.patreon.com/cw/sientia" target="_blank"
                       class="flex items-center gap-2.5 px-2 py-2 text-xs font-bold text-orange-600 hover:bg-orange-50 rounded-xl transition-all group">
                        <i class="fab fa-patreon text-base group-hover:scale-110 transition-transform"></i>
                        Patreon
                    </a>
                    <a href="https://buymeacoffee.com/sientia" target="_blank"
                       class="flex items-center gap-2.5 px-2 py-2 text-xs font-bold text-yellow-600 hover:bg-yellow-50 rounded-xl transition-all group">
                        <i class="fas fa-mug-hot text-base group-hover:scale-110 transition-transform"></i>
                        Buy me a coffee
                    </a>
                </div>
            </div>
        </nav>

        {{-- ── ÁREA DE CONTENIDO CON SCROLL PROPIO ── --}}
        <main id="docs-content">
            <div class="max-w-4xl mx-auto">

                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-6 no-print">
                    <a href="{{ route('docs', array_key_first($menu)) }}" class="hover:text-violet-600 transition-colors font-medium">Documentación</a>
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-violet-600 font-semibold">{{ $menu[$slug] ?? $slug }}</span>
                </nav>

                {{-- Contenido Markdown --}}
                <article class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-8 sm:px-10 prose">
                        {!! $content !!}
                    </div>
                    {{-- Footer del documento --}}
                    <div class="px-8 sm:px-10 py-4 bg-gray-50 border-t border-gray-100 flex flex-wrap gap-3 justify-between items-center no-print">
                        <span class="text-xs text-gray-400">© {{ date('Y') }} Sientia — Documentación sientiaERP</span>
                        <button onclick="window.print()"
                            class="text-xs font-semibold text-violet-600 hover:text-violet-700 flex items-center gap-1.5 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Imprimir / PDF
                        </button>
                    </div>
                </article>

            </div>
        </main>

    </div>{{-- fin #docs-body --}}

</body>
</html>
