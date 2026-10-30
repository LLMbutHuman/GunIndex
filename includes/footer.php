<footer class="mt-40 border-t border-white/5 bg-black/20 pt-20 pb-12 px-6 lg:px-12 backdrop-blur-sm">
        <div class="max-w-[1400px] mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-16 lg:gap-8">
                
                <div class="lg:col-span-4 space-y-8">
                    <div class="text-3xl font-black tracking-tighter text-white uppercase italic">
                        GUN<span class="text-[#8A2BE2]">DIRECT</span>.de
                    </div>
                    <p class="text-gray-500 text-[10px] leading-relaxed max-w-xs uppercase font-bold tracking-[0.2em] italic opacity-60">
                        High-End Marktplatz für zivile Waffen und Ausrüstung.
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse shadow-[0_0_10px_rgba(34,197,94,0.5)]"></div>
                        <span class="text-[9px] text-gray-400 font-black uppercase tracking-widest">Status: Aktiv</span>
                    </div>
                </div>
                
                <div class="lg:col-span-2 space-y-6">
                    <h4 class="text-white font-black text-[11px] uppercase tracking-[0.3em] italic border-l-2 border-[#8A2BE2] pl-3">Kategorien</h4>
                    <ul class="space-y-3">
                        <li><a href="../marketplace/search.php?type=frei" class="text-gray-500 hover:text-white text-[10px] uppercase font-bold tracking-widest transition-colors duration-300">Frei (18+)</a></li>
                        <li><a href="../marketplace/search.php?type=ewb" class="text-gray-500 hover:text-white text-[10px] uppercase font-bold tracking-widest transition-colors duration-300">EWB</a></li>
                        <li><a href="../marketplace/search.php?type=optik" class="text-gray-500 hover:text-white text-[10px] uppercase font-bold tracking-widest transition-colors duration-300">Optik & Sicht</a></li>
                    </ul>
                </div>
                
                <div class="lg:col-span-3 space-y-6">
                    <h4 class="text-white font-black text-[11px] uppercase tracking-[0.3em] italic border-l-2 border-[#8A2BE2] pl-3">Nutzer spezifisch</h4>
                    <ul class="space-y-3">
                        <li><a href="../user/dashboard.php" class="text-gray-500 hover:text-white text-[10px] uppercase font-bold tracking-widest transition-colors duration-300">Dashboard</a></li>
                        <li><a href="../user/create-listing.php" class="text-gray-500 hover:text-[#8A2BE2] text-[10px] uppercase font-black tracking-widest transition-colors duration-300">Inserat Schalten</a></li>
                        <li><a href="../user/profile.php" class="text-gray-500 hover:text-white text-[10px] uppercase font-bold tracking-widest transition-colors duration-300">Identität & PLZ</a></li>
                    </ul>
                </div>
                
                <div class="lg:col-span-3 space-y-6">
                    <h4 class="text-white font-black text-[11px] uppercase tracking-[0.3em] italic border-l-2 border-[#8A2BE2] pl-3">Rechtliches</h4>
                    <div class="grid grid-cols-1 gap-3">
                        <a href="#" class="text-gray-600 hover:text-white text-[9px] uppercase font-black tracking-widest transition-all">Impressum</a>
                        <a href="#" class="text-gray-600 hover:text-white text-[9px] uppercase font-black tracking-widest transition-all">Datenschutz</a>
                        <a href="#" class="text-gray-600 hover:text-white text-[9px] uppercase font-black tracking-widest transition-all">Nutzungsbedingungen</a>
                    </div>
                </div>
            </div>

            <div class="mt-20 pt-10 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex flex-col gap-1">
                    <span class="text-[9px] text-gray-700 font-black uppercase tracking-[0.3em]">© 2026 GUNDIRECT.de – High-End Maktplatz</span>
                </div>
                
                <div class="flex items-center gap-8">
                    <div class="flex items-center gap-3">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#8A2BE2]"></span>
                        <span class="text-[9px] text-white font-black uppercase tracking-widest italic">Zertifiziert</span>
                    </div>
                    <div class="px-4 py-2 border border-white/10 rounded-lg">
                        <span class="text-[10px] text-white font-black uppercase italic tracking-tighter">EST. 2026</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Modal-Steuerung für Filter
        function openFilters() {
            const modal = document.getElementById('filterModal');
            if(modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        function closeFilters() {
            const modal = document.getElementById('filterModal');
            if(modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        // Schließen bei Klick außerhalb oder Escape
        window.addEventListener('click', (e) => {
            if (e.target == document.getElementById('filterModal')) closeFilters();
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeFilters();
        });
    </script>
</body>
</html>