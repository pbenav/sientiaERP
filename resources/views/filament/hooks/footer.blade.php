<footer class="bg-white border-t border-gray-200 py-4 mt-auto">
    <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
            <div class="mb-2 md:mb-0">
                <span class="font-bold">© {{ date('Y') }} Sientia</span>
                <span class="mx-1">|</span>
                <span>v{{ config('app.version', '1.0.0') }}</span>
                <span class="mx-1">|</span>
                <a href="https://www.gnu.org/licenses/agpl-3.0.txt" target="_blank" class="hover:text-gray-700">Licencia AGPL v3</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="https://www.patreon.com/cw/sientiaCTH_ControlHorario" target="_blank"
                    class="text-orange-500 hover:text-orange-600 font-medium transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path d="M2.912 8.411c-.312 0-.533.225-.533.533v11.43c0 .312.225.533.533.533H21.09c.312 0 .533-.225.533-.533V8.944c0-.312-.225-.533-.533-.533H2.912zm0-2.666H21.09c1.782 0 3.2 1.418 3.2 3.2v11.43c0 1.782-1.418 3.2-3.2 3.2H2.912C1.13 23.535-.285 22.117-.285 20.335V8.944c0-1.782 1.418-3.2 3.2-3.2zM4.156 2.666c0-.533.433-.966.966-.966h13.754c.533 0 .966.433.966.966s-.433.966-.966.966H5.122c-.533 0-.966-.433-.966-.966z" />
                    </svg>
                    Apoyar en Patreon
                </a>
            </div>
        </div>
    </div>
</footer>
