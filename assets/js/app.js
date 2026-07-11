/**
 * IFRI Portail — Utilitaires JavaScript
 *
 * Contient : confirmModal, previewFile, validateForm, debounce, showToast
 * Charge sur toutes les pages du portail étudiant et admin.
 */

// ─── Toast System ──────────────────────────────────────────────
function showToast(message, type) {
    var colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-amber-500' };
    var icons = { success: 'check_circle', error: 'error', info: 'info', warning: 'warning' };
    var bg = colors[type] || 'bg-gray-800';
    var icon = icons[type] || 'info';
    var toast = document.createElement('div');
    toast.className = 'fixed top-6 right-6 z-[200] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-white text-sm font-medium ' + bg + ' transition-all duration-500 translate-x-[120%] opacity-0';
    toast.innerHTML = '<span class="material-symbols-outlined text-[20px]">' + icon + '</span><span>' + message + '</span><button onclick="this.parentElement.remove()" class="ml-2 opacity-70 hover:opacity-100"><span class="material-symbols-outlined text-[18px]">close</span></button>';
    document.body.appendChild(toast);
    requestAnimationFrame(function() {
        toast.classList.remove('translate-x-[120%]', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
    });
    setTimeout(function() {
        toast.classList.add('translate-x-[120%]', 'opacity-0');
        setTimeout(function() { toast.remove(); }, 500);
    }, 4000);
}

// ─── Confirmation Modal (remplace confirm()) ──────────────────
function confirmModal(message, options) {
    options = options || {};
    var title = options.title || 'Confirmation';
    var type = options.type || 'warning'; // 'danger', 'warning', 'info'
    var confirmText = options.confirmText || 'Confirmer';
    var cancelText = options.cancelText || 'Annuler';

    var icons = {
        danger: '<span class="material-symbols-outlined text-[32px] text-red-500">delete_forever</span>',
        warning: '<span class="material-symbols-outlined text-[32px] text-amber-500">warning</span>',
        info: '<span class="material-symbols-outlined text-[32px] text-blue-500">info</span>'
    };
    var colors = {
        danger: 'bg-red-500 hover:bg-red-600',
        warning: 'bg-amber-500 hover:bg-amber-600',
        info: 'bg-blue-500 hover:bg-blue-600'
    };
    var icon = icons[type] || icons.warning;
    var btnColor = colors[type] || colors.warning;

    return new Promise(function(resolve) {
        var overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[300] flex items-center justify-center p-4';
        overlay.style.cssText = 'background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);';

        overlay.innerHTML = '\
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 overflow-hidden animate-modal-in" style="animation: modalIn 0.3s ease-out;">\
                <div class="p-6 text-center">\
                    <div class="mb-4 flex justify-center">' + icon + '</div>\
                    <h3 class="text-lg font-bold text-slate-900 mb-2">' + title + '</h3>\
                    <p class="text-sm text-slate-600 leading-relaxed">' + message + '</p>\
                </div>\
                <div class="flex border-t border-slate-100 divide-x divide-slate-100">\
                    <button class="cancel-btn flex-1 py-3.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">' + cancelText + '</button>\
                    <button class="confirm-btn flex-1 py-3.5 text-sm font-semibold text-white transition-colors ' + btnColor + '">' + confirmText + '</button>\
                </div>\
            </div>';

        document.body.appendChild(overlay);

        overlay.querySelector('.cancel-btn').addEventListener('click', function() {
            overlay.remove();
            resolve(false);
        });
        overlay.querySelector('.confirm-btn').addEventListener('click', function() {
            overlay.remove();
            resolve(true);
        });
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.remove();
                resolve(false);
            }
        });
    });
}

// ─── File Preview Lightbox ─────────────────────────────────────
function previewFile(url, filename) {
    filename = filename || 'Fichier';
    var isImage = url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i);
    var overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[300] flex items-center justify-center p-4';
    overlay.style.cssText = 'background: rgba(0,0,0,0.8); backdrop-filter: blur(8px);';

    var content = '';
    if (isImage) {
        content = '<img src="' + url + '" alt="' + filename + '" class="max-w-full max-h-[80vh] rounded-2xl shadow-2xl object-contain" style="animation: modalIn 0.3s ease-out;" />';
    } else {
        content = '<iframe src="' + url + '" class="w-full max-w-4xl h-[80vh] rounded-2xl shadow-2xl bg-white" style="animation: modalIn 0.3s ease-out;"></iframe>';
    }

    overlay.innerHTML = '\
        <div class="relative max-w-5xl w-full mx-auto flex flex-col items-center">\
            <div class="flex items-center justify-between w-full max-w-4xl mb-3 px-1">\
                <span class="text-white text-sm font-medium truncate max-w-xs">' + filename + '</span>\
                <div class="flex items-center gap-2">\
                    <a href="' + url + '" download class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-semibold rounded-lg transition-colors">\
                        <span class="material-symbols-outlined text-[16px]">download</span> Telecharger\
                    </a>\
                    <button class="close-btn inline-flex items-center justify-center w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors">\
                        <span class="material-symbols-outlined text-[18px]">close</span>\
                    </button>\
                </div>\
            </div>\
            <div class="flex items-center justify-center">' + content + '</div>\
        </div>';

    document.body.appendChild(overlay);

    overlay.querySelector('.close-btn').addEventListener('click', function() { overlay.remove(); });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.remove();
    });
    document.addEventListener('keydown', function handler(e) {
        if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', handler); }
    });
}

// ─── Debounce ──────────────────────────────────────────────────
function debounce(fn, delay) {
    var timer = null;
    return function() {
        var context = this;
        var args = arguments;
        clearTimeout(timer);
        timer = setTimeout(function() {
            fn.apply(context, args);
        }, delay || 300);
    };
}

// ─── Form Validation ───────────────────────────────────────────
function validateForm(formElement) {
    var valid = true;
    var inputs = formElement.querySelectorAll('input[required], select[required], textarea[required]');

    inputs.forEach(function(input) {
        var errorEl = input.parentElement.querySelector('.validation-error');
        if (!input.value.trim()) {
            valid = false;
            input.classList.add('border-red-400', 'ring-1', 'ring-red-400');
            input.classList.remove('border-gray-200', 'border-slate-200');
            if (!errorEl) {
                var err = document.createElement('p');
                err.className = 'validation-error text-red-500 text-xs mt-1';
                err.textContent = 'Ce champ est requis';
                input.parentElement.appendChild(err);
            }
        } else {
            input.classList.remove('border-red-400', 'ring-1', 'ring-red-400');
            input.classList.add('border-gray-200');
            if (errorEl) errorEl.remove();

            if (input.type === 'email' && input.value.includes('@') === false) {
                valid = false;
                input.classList.add('border-red-400', 'ring-1', 'ring-red-400');
                var err = input.parentElement.querySelector('.validation-error');
                if (!err) {
                    err = document.createElement('p');
                    err.className = 'validation-error text-red-500 text-xs mt-1';
                    err.textContent = 'Email invalide';
                    input.parentElement.appendChild(err);
                }
            }
        }
    });

    return valid;
}

// ─── Auto-init debounced search ────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[data-search]').forEach(function(input) {
        var targetSelector = input.getAttribute('data-search');
        input.addEventListener('input', debounce(function(e) {
            var term = e.target.value.toLowerCase().trim();
            document.querySelectorAll(targetSelector).forEach(function(item) {
                var text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        }, 300));
    });
});

// Add modal animation keyframes
var style = document.createElement('style');
style.textContent = '\
    @keyframes modalIn {\
        from { opacity: 0; transform: scale(0.9) translateY(20px); }\
        to { opacity: 1; transform: scale(1) translateY(0); }\
    }\
    .validation-error { animation: fadeIn 0.2s ease-out; }\
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }\
';
document.head.appendChild(style);
