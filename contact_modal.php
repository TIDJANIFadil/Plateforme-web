<!-- ===== BOUTON FLOTTANT CONTACT ===== -->
<button id="contactFloatingBtn" onclick="openContactModal()"
    class="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-5 py-3 bg-[#004A99] text-white rounded-full shadow-lg hover:bg-[#00387a] hover:shadow-xl hover:scale-105 active:scale-95 transition-all duration-300" style="box-shadow: 0 8px 24px rgba(0,74,153,0.35);">
    <span class="material-symbols-outlined" style="font-size:20px;">headset_mic</span>
    <span class="text-sm font-bold">Besoin d'aide ?</span>
</button>

<!-- ===== MODAL CONTACT ===== -->
<div id="contactModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4); backdrop-filter: blur(4px);">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <!-- En-tête -->
        <div class="px-6 py-5 bg-gradient-to-r from-[#003d7a] to-[#004d99] flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-white" style="font-size:22px;">headset_mic</span>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Contactez l'administration</h3>
                    <p class="text-xs text-white/70">Un problème ? Nous sommes là pour vous aider</p>
                </div>
            </div>
            <button onclick="closeContactModal()" class="text-white/80 hover:bg-white/20 p-2 rounded-full transition-colors">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            </button>
        </div>

        <!-- Corps du formulaire -->
        <form id="contactForm" class="p-6 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" value="send_contact">

            <!-- Sujet -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sujet</label>
                <select name="sujet" id="contactSujet" required
                    class="w-full p-3 rounded-xl border border-gray-200 bg-white text-sm font-medium focus:border-[#004A99] focus:ring-2 focus:ring-[#004A99]/20 transition-all">
                    <option value="" disabled selected>-- Choisissez un sujet --</option>
                    <option value="Problème technique">💻 Problème technique</option>
                    <option value="Question administrative">📋 Question administrative</option>
                    <option value="Suggestion">💡 Suggestion</option>
                    <option value="Reclamation">⚠️ Réclamation</option>
                    <option value="Autre">📝 Autre</option>
                </select>
            </div>

            <!-- Message -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Votre message</label>
                <textarea name="message" id="contactMessage" rows="4" required placeholder="Décrivez votre problème en quelques lignes..."
                    class="w-full p-3 rounded-xl border border-gray-200 bg-white text-sm focus:border-[#004A99] focus:ring-2 focus:ring-[#004A99]/20 transition-all resize-none"></textarea>
            </div>

            <!-- Pièce jointe (optionnelle) -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Capture d'écran (optionnelle)</label>
                <div class="relative">
                    <input type="file" name="piece_jointe" id="contactFile" accept="image/*,.pdf"
                        class="w-full p-2.5 rounded-xl border border-gray-200 bg-white text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-[#004A99]/10 file:text-[#004A99] file:text-xs file:font-bold hover:file:bg-[#004A99]/20 transition-all cursor-pointer" />
                    <p class="text-[10px] text-slate-400 mt-1">JPG, PNG, WEBP, PDF max 5 Mo</p>
                </div>
            </div>

            <!-- Barre de progression -->
            <div id="contactProgress" class="hidden">
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                        <div id="contactProgressBar" class="h-full bg-[#004A99] rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <span id="contactProgressText" class="text-xs font-semibold text-slate-500">0%</span>
                </div>
            </div>

            <!-- Message de réponse -->
            <div id="contactResponse" class="hidden p-3 rounded-xl text-sm"></div>

            <!-- Bouton envoyer -->
            <button type="submit" id="contactSubmitBtn"
                class="w-full py-3.5 bg-[#004A99] text-white rounded-xl font-bold text-sm shadow-md hover:bg-[#00387a] active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                <span class="material-symbols-outlined" style="font-size:18px;">send</span>
                Envoyer le message
            </button>
        </form>
    </div>
</div>

<script>
// ===== OUVERTURE / FERMETURE =====
function openContactModal() {
    document.getElementById('contactModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    resetContactForm();
}
function closeContactModal() {
    document.getElementById('contactModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Fermeture en cliquant sur l'overlay
document.getElementById('contactModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeContactModal();
});

// Touche Echap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeContactModal();
});

// ===== RÉINITIALISATION =====
function resetContactForm() {
    const form = document.getElementById('contactForm');
    form.reset();
    document.getElementById('contactResponse').classList.add('hidden');
    document.getElementById('contactProgress').classList.add('hidden');
    document.getElementById('contactSubmitBtn').disabled = false;
    document.getElementById('contactSubmitBtn').innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">send</span> Envoyer le message';
}

// ===== ENVOI DU FORMULAIRE VIA AJAX =====
document.getElementById('contactForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('contactSubmitBtn');
    const response = document.getElementById('contactResponse');
    const progress = document.getElementById('contactProgress');

    // Désactiver le bouton
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">hourglass_top</span> Envoi en cours...';

    // Afficher la progression
    progress.classList.remove('hidden');
    document.getElementById('contactProgressBar').style.width = '60%';
    document.getElementById('contactProgressText').textContent = '60%';

    const formData = new FormData(this);

    fetch('contact_process.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('contactProgressBar').style.width = '100%';
        document.getElementById('contactProgressText').textContent = '100%';

        response.classList.remove('hidden');
        if (data.success) {
            response.className = 'p-3 rounded-xl text-sm bg-emerald-50 border border-emerald-200 text-emerald-700';
            response.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">check_circle</span>' + data.message + '</div>';
            setTimeout(() => { closeContactModal(); }, 3000);
        } else {
            response.className = 'p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-700';
            response.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">error</span>' + data.message + '</div>';
            setTimeout(() => { progress.classList.add('hidden'); }, 2000);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">send</span> Envoyer le message';
        }
    })
    .catch(err => {
        response.classList.remove('hidden');
        response.className = 'p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-700';
        response.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">error</span> Erreur de connexion. Veuillez réessayer.</div>';
        document.getElementById('contactProgress').classList.add('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">send</span> Envoyer le message';
    });
});
</script>
