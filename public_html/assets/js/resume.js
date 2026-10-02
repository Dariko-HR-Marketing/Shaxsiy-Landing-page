/* DARIKO — rezyume sahifasi skripti (v12: resume.html dagi inline <script> va on*="" atributlaridan ko'chirildi,
   CSP script-src'dan 'unsafe-inline' olib tashlanishi uchun). */
let isEditing = false;

function toggleEditMode() {
  isEditing = !isEditing;
  const editableElements = document.querySelectorAll('.editable');
  const btnText = document.getElementById('editBtnText');
  const btn = document.getElementById('toggleEditBtn');

  editableElements.forEach(el => {
    el.setAttribute('contenteditable', isEditing ? 'true' : 'false');
  });

  if (isEditing) {
    btnText.innerText = "Saqlash";
    btn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-500');
    btn.classList.remove('bg-slate-800', 'text-slate-200');
  } else {
    btnText.innerText = "Tahrirlash";
    btn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-500');
    btn.classList.add('bg-slate-800', 'text-slate-200');
  }
}

function previewUserPhoto(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const box = document.getElementById('profileImg');
      const img = document.createElement('img');
      img.src = e.target.result; img.alt = 'Khikmatullo Turaev';
      box.replaceChildren(img);
    };
    reader.readAsDataURL(file);
  }
}

function setTheme(theme) {
  const sheet = document.getElementById('resumeSheet');
  const navyBtn = document.getElementById('navyBtn');
  const emeraldBtn = document.getElementById('emeraldBtn');

  if (theme === 'emerald') {
    sheet.classList.remove('theme-navy');
    sheet.classList.add('theme-emerald');
    emeraldBtn.className = "px-3 py-1.5 rounded-lg font-bold text-white bg-[#0BA360] shadow-sm transition-all flex items-center gap-1.5 cursor-pointer";
    navyBtn.className = "px-3 py-1.5 rounded-lg font-bold text-slate-300 hover:text-white transition-all flex items-center gap-1.5 cursor-pointer";
  } else {
    sheet.classList.remove('theme-emerald');
    sheet.classList.add('theme-navy');
    navyBtn.className = "px-3 py-1.5 rounded-lg font-bold text-white bg-[#0BA360] shadow-sm transition-all flex items-center gap-1.5 cursor-pointer";
    emeraldBtn.className = "px-3 py-1.5 rounded-lg font-bold text-slate-300 hover:text-white transition-all flex items-center gap-1.5 cursor-pointer";
  }
}

// v12: inline onclick/onchange/onerror o'rniga hodisa tinglovchilari.
document.addEventListener('click', e => {
  const el = e.target.closest && e.target.closest('[data-action]');
  if (!el) return;
  switch (el.dataset.action) {
    case 'theme': setTheme(el.dataset.theme); break;
    case 'toggle-edit': toggleEditMode(); break;
    case 'print': window.print(); break;
    case 'pick-photo': document.getElementById('photoInput').click(); break;
  }
});
(() => {
  const input = document.getElementById('photoInput');
  if (input) input.addEventListener('change', previewUserPhoto);
  // Surat yuklanmasa "KT" fallback. Skript sahifa oxirida — xato undan oldin sodir bo'lgan bo'lishi mumkin.
  const img = document.querySelector('#profileImg img');
  if (img) {
    const fallback = () => { if (img.parentNode) img.parentNode.textContent = 'KT'; };
    if (img.complete && img.naturalWidth === 0) fallback(); else img.addEventListener('error', fallback);
  }
})();
