<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'nexERP') }} - Gestión Empresarial Inteligente</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
        @endif

        <style>
            body {
                font-family: 'Outfit', sans-serif;
            }
            .glass-effect {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .gradient-text {
                background: linear-gradient(135deg, #60A5FA 0%, #A78BFA 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
        </style>
    </head>
    <body class="bg-slate-950 text-white min-h-screen flex flex-col">
        <!-- Background Gradients -->
        <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] rounded-full bg-blue-600/20 blur-[128px]"></div>
            <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] rounded-full bg-purple-600/20 blur-[128px]"></div>
        </div>

        <!-- Navbar -->
        <nav class="relative z-50 w-full px-6 py-6 flex justify-between items-center max-w-7xl mx-auto">
            <div class="flex items-center gap-2">
                <!-- Simple Logo Icon -->
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="text-2xl font-bold tracking-tight text-white">nex<span class="text-blue-400">ERP</span></span>
            </div>
            
            <a href="{{ url('/admin/login') }}" class="group relative px-6 py-2.5 rounded-full overflow-hidden bg-white/10 hover:bg-white/20 transition-all duration-300 border border-white/5 hover:border-white/20">
                <div class="absolute inset-0 w-0 bg-gradient-to-r from-blue-600/50 to-purple-600/50 transition-all duration-[250ms] ease-out group-hover:w-full"></div>
                <span class="relative flex items-center gap-2 text-sm font-medium text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                    Área de Cliente
                </span>
            </a>
        </nav>

        <!-- Hero Section -->
        <main class="relative z-10 flex-grow flex items-center justify-center px-6">
            <div class="max-w-4xl mx-auto text-center space-y-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-xs font-medium uppercase tracking-wider mb-4 animate-fade-in-up">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    Versión 2.0 Disponible
                </div>
                
                <h1 class="text-5xl md:text-7xl font-bold leading-tight tracking-tight mb-2">
                    Tu negocio, <br/>
                    <span class="gradient-text">más inteligente.</span>
                </h1>
                
                <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    Gestiona facturación, inventario y recursos humanos en una única plataforma unificada.
                    Diseñada para la velocidad, construida para escalar.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-8">
                    <a href="{{ url('/admin/login') }}" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 text-white font-semibold text-lg shadow-lg shadow-blue-500/30 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-xl">
                        Comenzar Ahora
                    </a>
                    <a href="#features" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-slate-800/50 hover:bg-slate-800 text-white font-medium border border-slate-700 hover:border-slate-600 transition-all duration-300 backdrop-blur-sm">
                        Ver Características
                    </a>
                </div>
            </div>
        </main>

        <!-- Features Grid -->
        <section id="features" class="relative z-10 py-24 px-6">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="glass-effect p-8 rounded-2xl hover:bg-white/10 transition-colors duration-300">
                        <div class="w-12 h-12 rounded-lg bg-blue-500/20 flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3 text-white">Analíticas en Tiempo Real</h3>
                        <p class="text-slate-400 leading-relaxed">Visualiza el rendimiento de tu empresa con dashboards interactivos y reportes detallados al instante.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="glass-effect p-8 rounded-2xl hover:bg-white/10 transition-colors duration-300">
                        <div class="w-12 h-12 rounded-lg bg-purple-500/20 flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3 text-white">Eficiencia Automatizada</h3>
                        <p class="text-slate-400 leading-relaxed">Automatiza tareas repetitivas, desde la facturación hasta el control de stock, y ahorra tiempo valioso.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="glass-effect p-8 rounded-2xl hover:bg-white/10 transition-colors duration-300">
                        <div class="w-12 h-12 rounded-lg bg-teal-500/20 flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3 text-white">Seguridad Total</h3>
                        <p class="text-slate-400 leading-relaxed">Tus datos están protegidos con los estándares más altos de seguridad y control de acceso granular.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="relative z-10 border-t border-white/5 py-12 px-6 bg-slate-950/50 backdrop-blur-lg mt-auto">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex items-center gap-2">
                     <span class="text-xl font-bold tracking-tight text-white">nex<span class="text-blue-400">ERP</span></span>
                     <span class="text-slate-600 mx-2">|</span>
                     <span class="text-sm text-slate-500">&copy; {{ date('Y') }} Todos los derechos reservados.</span>
                </div>
                
                <div class="flex gap-6 text-sm text-slate-500">
                    <a href="#" class="hover:text-blue-400 transition-colors">Privacidad</a>
                    <a href="#" class="hover:text-blue-400 transition-colors">Términos</a>
                    <a href="#" class="hover:text-blue-400 transition-colors">Soporte</a>
                </div>
            </div>
        </footer>
    </body>
</html>
