// Initialize Lucide Icons
lucide.createIcons();

// Theme Toggle Functionality
const themeToggleBtn = document.getElementById('themeToggle');
const htmlElement = document.documentElement;

const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
if (savedTheme === 'dark') {
    htmlElement.classList.add('dark');
} else {
    htmlElement.classList.remove('dark');
}

if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
        htmlElement.classList.toggle('dark');
        const isDark = htmlElement.classList.contains('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
}

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');
const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    mobileNavLinks.forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
        });
    });
}

// Scrollspy Navigation
const sections = document.querySelectorAll('section');
const navLinks = document.querySelectorAll('.nav-link');
const backToTopBtn = document.getElementById('backToTop');

window.addEventListener('scroll', () => {
    let current = '';
    const scrollPosition = window.scrollY;

    sections.forEach(section => {
        const sectionTop = section.offsetTop - 120;
        const sectionHeight = section.clientHeight;
        if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });

    if (backToTopBtn) {
        if (scrollPosition > 400) {
            backToTopBtn.classList.remove('hidden');
        } else {
            backToTopBtn.classList.add('hidden');
        }
    }
});

if (backToTopBtn) {
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Interactive Cost Calculator
const calcOptions = document.querySelectorAll('.calc-option');
const calcAddons = document.querySelectorAll('.calc-addon');
const calcTotalPriceEl = document.getElementById('calcTotalPrice');

let basePrice = 250;

function calculateTotal() {
    let total = basePrice;
    calcAddons.forEach(addon => {
        if (addon.checked) {
            total += parseInt(addon.getAttribute('data-price') || 0);
        }
    });
    if (calcTotalPriceEl) {
        calcTotalPriceEl.textContent = `$${total}`;
    }
}

calcOptions.forEach(option => {
    option.addEventListener('click', () => {
        calcOptions.forEach(opt => {
            opt.classList.remove('active', 'border-brand-500', 'bg-brand-500/10', 'text-brand-600', 'dark:text-brand-400');
            opt.classList.add('border-slate-200', 'dark:border-slate-800');
        });
        option.classList.add('active', 'border-brand-500', 'bg-brand-500/10', 'text-brand-600', 'dark:text-brand-400');
        option.classList.remove('border-slate-200', 'dark:border-slate-800');

        basePrice = parseInt(option.getAttribute('data-price') || 250);
        calculateTotal();
    });
});

calcAddons.forEach(addon => {
    addon.addEventListener('change', calculateTotal);
});

// Portfolio Filter
const filterButtons = document.querySelectorAll('.filter-btn');
const portfolioItems = document.querySelectorAll('.portfolio-item');

filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        filterButtons.forEach(b => {
            b.classList.remove('active', 'bg-brand-600', 'text-white', 'shadow-md', 'shadow-brand-500/20');
            b.classList.add('bg-slate-200/70', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-300');
        });

        btn.classList.add('active', 'bg-brand-600', 'text-white', 'shadow-md', 'shadow-brand-500/20');
        btn.classList.remove('bg-slate-200/70', 'dark:bg-slate-800', 'text-slate-700', 'dark:text-slate-300');

        const filterValue = btn.getAttribute('data-filter');

        portfolioItems.forEach(item => {
            if (filterValue === 'all' || item.classList.contains(filterValue)) {
                item.style.display = 'block';
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 50);
            } else {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.display = 'none';
                }, 300);
            }
        });
    });
});

// Modal Dialog
function openModal(title, desc, tech, result) {
    const modal = document.getElementById('projectModal');
    if (modal) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalDesc').textContent = desc;
        document.getElementById('modalTech').textContent = tech;
        document.getElementById('modalResult').textContent = result;
        modal.classList.remove('hidden');
    }
}

function closeModal() {
    const modal = document.getElementById('projectModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// FAQ Accordion
const faqBtns = document.querySelectorAll('.faq-btn');
faqBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('[data-lucide="chevron-down"]');
        
        content.classList.toggle('hidden');
        if (icon) {
            icon.classList.toggle('rotate-180');
        }
    });
});

// Form Submission Toast
const contactForm = document.getElementById('contactForm');
const toast = document.getElementById('toast');

if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (toast) {
            toast.classList.remove('-translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.remove('translate-x-0', 'opacity-100');
                toast.classList.add('-translate-x-full', 'opacity-0');
            }, 4000);
        }
        
        contactForm.reset();
    });
}
