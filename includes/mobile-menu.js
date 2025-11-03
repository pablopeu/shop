/**
 * Mobile Menu Hamburger Component
 * Handles opening/closing of mobile navigation drawer
 */

(function() {
    'use strict';

    // Create mobile menu HTML structure
    function initMobileMenu() {
        // Check if already initialized
        if (document.querySelector('.mobile-menu-drawer')) {
            return;
        }

        // Get cart count
        const cartCountElement = document.getElementById('cart-count');
        const cartCount = cartCountElement ? cartCountElement.textContent : '0';

        // Create hamburger button
        const header = document.querySelector('.header-content');
        if (!header) return;

        const hamburgerBtn = document.createElement('button');
        hamburgerBtn.className = 'mobile-menu-toggle';
        hamburgerBtn.setAttribute('aria-label', 'Menú');
        hamburgerBtn.innerHTML = `
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;

        // Add button to header
        header.appendChild(hamburgerBtn);

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'mobile-menu-overlay';

        // Create drawer
        const drawer = document.createElement('div');
        drawer.className = 'mobile-menu-drawer';
        drawer.innerHTML = `
            <div class="mobile-menu-header">
                <div class="mobile-menu-title">Menú</div>
                <button class="mobile-menu-close" aria-label="Cerrar menú">&times;</button>
            </div>
            <nav class="mobile-menu-nav">
                <a href="/">
                    <span class="mobile-menu-icon">🏠</span>
                    Inicio
                </a>
                <a href="/buscar.php">
                    <span class="mobile-menu-icon">🔍</span>
                    Buscar
                </a>
                <a href="/favoritos.php">
                    <span class="mobile-menu-icon">❤️</span>
                    Favoritos
                </a>
                <a href="/carrito.php">
                    <span class="mobile-menu-icon">🛒</span>
                    Carrito
                    ${cartCount !== '0' ? `<span class="mobile-menu-badge">${cartCount}</span>` : ''}
                </a>
                <a href="/pedido.php">
                    <span class="mobile-menu-icon">📦</span>
                    Mis Pedidos
                </a>
            </nav>
        `;

        // Add elements to DOM
        document.body.appendChild(overlay);
        document.body.appendChild(drawer);

        // Setup event listeners
        hamburgerBtn.addEventListener('click', openMenu);
        overlay.addEventListener('click', closeMenu);
        drawer.querySelector('.mobile-menu-close').addEventListener('click', closeMenu);

        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && drawer.classList.contains('active')) {
                closeMenu();
            }
        });

        // Update cart count when it changes
        if (cartCountElement) {
            const observer = new MutationObserver(() => {
                const newCount = cartCountElement.textContent;
                const badge = drawer.querySelector('.mobile-menu-badge');
                const cartLink = drawer.querySelector('a[href="/carrito.php"]');

                if (newCount !== '0') {
                    if (!badge) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'mobile-menu-badge';
                        newBadge.textContent = newCount;
                        cartLink.appendChild(newBadge);
                    } else {
                        badge.textContent = newCount;
                    }
                } else if (badge) {
                    badge.remove();
                }
            });

            observer.observe(cartCountElement, { childList: true, characterData: true, subtree: true });
        }
    }

    function openMenu() {
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        const drawer = document.querySelector('.mobile-menu-drawer');

        toggle.classList.add('active');
        overlay.classList.add('active');
        drawer.classList.add('active');
        document.body.classList.add('mobile-menu-open');

        // Trap focus in drawer
        const focusableElements = drawer.querySelectorAll('a, button');
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    }

    function closeMenu() {
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        const drawer = document.querySelector('.mobile-menu-drawer');

        toggle.classList.remove('active');
        overlay.classList.remove('active');
        drawer.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }

    // Expose close function globally for external use
    window.closeMobileMenu = closeMenu;
})();
