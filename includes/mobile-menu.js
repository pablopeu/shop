/**
 * Mobile Menu Hamburger Component
 * Handles opening/closing of mobile navigation drawer
 */

(function() {
    'use strict';

    // Detect BASE_PATH from script URL
    function getBasePath() {
        const script = document.querySelector('script[src*="mobile-menu.js"]');
        if (script) {
            const src = script.getAttribute('src');
            // Extract base path from script URL
            // e.g., /shop/includes/mobile-menu.js -> /shop
            const match = src.match(/^(.*?)\/includes\/mobile-menu\.js/);
            if (match && match[1]) {
                return match[1];
            }
        }
        return '';
    }

    const BASE_PATH = getBasePath();
    console.log('Mobile menu: Detected BASE_PATH =', BASE_PATH);

    // Helper function to create URL with BASE_PATH
    function url(path) {
        if (!path.startsWith('/')) {
            path = '/' + path;
        }
        return BASE_PATH + path;
    }

    // Create mobile menu HTML structure
    function initMobileMenu() {
        console.log('Mobile menu: Initializing...');

        // Check if already initialized
        if (document.querySelector('.mobile-menu-drawer')) {
            console.log('Mobile menu: Already initialized');
            return;
        }

        // Get cart count
        const cartCountElement = document.getElementById('cart-count');
        const cartCount = cartCountElement ? cartCountElement.textContent : '0';
        console.log('Mobile menu: Cart count =', cartCount);

        // Create hamburger button
        const header = document.querySelector('.header-content');
        if (!header) {
            console.error('Mobile menu: Header not found!');
            return;
        }
        console.log('Mobile menu: Header found');

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
        console.log('Mobile menu: Hamburger button added to header');

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
                <a href="${url('/')}">
                    <span class="mobile-menu-icon">🏠</span>
                    Inicio
                </a>
                <a href="${url('/buscar.php')}">
                    <span class="mobile-menu-icon">🔍</span>
                    Buscar
                </a>
                <a href="${url('/favoritos.php')}">
                    <span class="mobile-menu-icon">❤️</span>
                    Favoritos
                </a>
                <a href="${url('/carrito.php')}">
                    <span class="mobile-menu-icon">🛒</span>
                    Carrito
                    ${cartCount !== '0' ? `<span class="mobile-menu-badge">${cartCount}</span>` : ''}
                </a>
                <a href="${url('/pedido.php')}">
                    <span class="mobile-menu-icon">📦</span>
                    Mis Pedidos
                </a>
            </nav>
        `;

        // Add elements to DOM
        document.body.appendChild(overlay);
        document.body.appendChild(drawer);
        console.log('Mobile menu: Overlay and drawer added to DOM');

        // Setup event listeners
        hamburgerBtn.addEventListener('click', openMenu);
        overlay.addEventListener('click', closeMenu);
        drawer.querySelector('.mobile-menu-close').addEventListener('click', closeMenu);
        console.log('Mobile menu: Event listeners attached');
        console.log('Mobile menu: Initialization complete!');

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
                const cartLink = drawer.querySelector(`a[href="${url('/carrito.php')}"]`);

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
        console.log('Mobile menu: Opening menu...');
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        const drawer = document.querySelector('.mobile-menu-drawer');

        if (!toggle || !overlay || !drawer) {
            console.error('Mobile menu: Cannot open - elements not found', { toggle, overlay, drawer });
            return;
        }

        toggle.classList.add('active');
        overlay.classList.add('active');
        drawer.classList.add('active');
        document.body.classList.add('mobile-menu-open');
        console.log('Mobile menu: Menu opened');

        // Trap focus in drawer
        const focusableElements = drawer.querySelectorAll('a, button');
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    }

    function closeMenu() {
        console.log('Mobile menu: Closing menu...');
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-menu-overlay');
        const drawer = document.querySelector('.mobile-menu-drawer');

        if (!toggle || !overlay || !drawer) {
            console.error('Mobile menu: Cannot close - elements not found');
            return;
        }

        toggle.classList.remove('active');
        overlay.classList.remove('active');
        drawer.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
        console.log('Mobile menu: Menu closed');
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
