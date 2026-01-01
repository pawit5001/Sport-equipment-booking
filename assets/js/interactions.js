/*
    Sports Equipment Booking System - JavaScript Enhancements
    Micro-interactions & Modern Features
*/

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // SMOOTH SCROLL BEHAVIOR
    // ============================================
    document.querySelectorAll('a[href^="#"]:not([data-bs-toggle]):not(.dropdown-toggle)').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            // Skip if href is just "#" or invalid or has data-bs-toggle
            if (!href || href === '#' || href.length <= 1) {
                return;
            }
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    // ============================================
    // BUTTON RIPPLE EFFECT
    // ============================================
    document.querySelectorAll('.btn:not([data-bs-toggle])').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // ============================================
    // FADE IN ON SCROLL
    // ============================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.card, .stat-card, .equipment-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    
    // ============================================
    // FORM INPUT FOCUS ANIMATION
    // ============================================
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary-color)';
            this.style.boxShadow = '0 0 0 4px rgba(30, 64, 175, 0.1)';
        });
        
        input.addEventListener('blur', function() {
            this.style.borderColor = 'var(--border-color)';
            this.style.boxShadow = 'none';
        });
    });
    
    // ============================================
    // ACTIVE NAVIGATION LINK
    // ============================================
    const currentPage = window.location.pathname;
    document.querySelectorAll('#menu-top .nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPage || 
            currentPage.includes(link.getAttribute('href').replace('.php', ''))) {
            link.classList.add('active');
            link.style.borderBottomColor = 'var(--accent-color)';
            link.style.color = 'white';
        }
    });
    
    // ============================================
    // TOAST NOTIFICATIONS
    // ============================================
    window.showToast = function(message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.style.animation = 'slideDown 0.3s ease-out';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };
    
    // ============================================
    // TABLE ROW HOVER
    // ============================================
    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'var(--light-bg)';
            this.style.boxShadow = 'inset 4px 0 0 var(--primary-color)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.boxShadow = '';
        });
    });
    
    // ============================================
    // CONFIRM BEFORE DELETE
    // ============================================
    // Global delete confirm disabled for bulk delete; rely on dedicated modals per page
    document.querySelectorAll('a[href*="delete"], form[method="POST"] [type="submit"]').forEach(el => {
        if (el.id === 'bulkDeleteBtn' || el.closest('#bulkDeleteForm')) {
            return; // bulk delete handled elsewhere
        }
        if (el.textContent.toLowerCase().includes('ลบ') || 
            el.textContent.toLowerCase().includes('delete')) {
            el.addEventListener('click', function(e) {
                // If a page provides its own modal (data attribute flag), skip native confirm
                if (this.hasAttribute('data-use-modal')) return;
                if (!confirm('คุณแน่ใจหรือว่าต้องการลบ?')) {
                    e.preventDefault();
                }
            });
        }
    });
    
    // ============================================
    // LOADING STATE FOR FORMS
    // ============================================
    document.querySelectorAll('form').forEach(form => {
        // Skip forms that manage their own loading state (AJAX handlers)
        if (
            form.id === 'adminLoginForm' ||
            form.id === 'loginForm' ||
            form.id === 'signupForm' ||
            form.hasAttribute('data-no-auto-loading')
        ) {
            return;
        }

        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังประมวลผล...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 3000);
            }
        });
    });
    
    // ============================================
    // SEARCH/FILTER ENHANCEMENT
    // ============================================
    const searchInput = document.querySelector('input[type="search"], input[placeholder*="ค้นหา"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.toLowerCase();
            
            document.querySelectorAll('.equipment-card, .table tbody tr').forEach(el => {
                const text = el.textContent.toLowerCase();
                el.style.display = text.includes(query) ? '' : 'none';
                el.style.opacity = text.includes(query) ? '1' : '0.5';
            });
        });
    }
    
    // ============================================
    // MOBILE MENU CLOSE ON LINK CLICK
    // ============================================
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        document.querySelectorAll('.navbar-collapse .nav-link:not(.dropdown-toggle)').forEach(link => {
            link.addEventListener('click', () => {
                if (navbarCollapse.classList.contains('show')) {
                    navbarToggler.click();
                }
            });
        });
    }
    
    // ============================================
    // CARD COUNTER ANIMATION
    // ============================================
    window.animateCounter = function(element, endValue, duration = 1000) {
        const startValue = 0;
        const startTime = Date.now();
        
        const animate = () => {
            const currentTime = Date.now();
            const progress = (currentTime - startTime) / duration;
            
            if (progress < 1) {
                const value = Math.floor(startValue + (endValue - startValue) * progress);
                element.textContent = value;
                requestAnimationFrame(animate);
            } else {
                element.textContent = endValue;
            }
        };
        
        animate();
    };
    
    // ============================================
    // TOOLTIP INITIALIZATION
    // ============================================
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Skip if inside dropdown
        if (document.querySelector('.dropdown-menu.show')) {
            return;
        }
        
        // Alt + H = Home
        if (e.altKey && e.key === 'h') {
            window.location.href = 'dashboard.php';
        }
        // Alt + L = Logout
        if (e.altKey && e.key === 'l') {
            if (document.querySelector('a[href="logout.php"]')) {
                window.location.href = 'logout.php';
            }
        }
    });
    
    console.log('Sports Equipment Booking System - UI Enhanced ✓');
});

// ============================================
// CUSTOM CSS ANIMATIONS
// ============================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    
    .spinner-border {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        vertical-align: text-bottom;
        border: 0.25em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border 0.75s linear infinite;
    }
    
    @keyframes spinner-border {
        to {
            transform: rotate(360deg);
        }
    }
`;
document.head.appendChild(style);
