/**
 * Mobile Menu Hamburger Component
 * Handles opening/closing of mobile navigation drawer
 */

(function() {
    'use strict';

    // Detect BASE_PATH using multiple methods
    function getBasePath() {
        // Method 1: From script tag
        const scripts = document.querySelectorAll('script[src]');
        for (let script of scripts) {
            const src = script.getAttribute('src');
            if (src && src.includes('mobile-menu.js')) {
                const match = src.match(/^(.*?)\/includes\/mobile-menu\.js/);
                if (match && match[1]) {
                    console.log('Mobile menu: BASE_PATH detected from script:', match[1]);
                    return match[1];
                }
            }
        }

        // Method 2: From current page path
        const currentPath = window.location.pathname;
        // If we're at /shop/index.php, BASE_PATH is /shop
        const pathParts = currentPath.split('/').filter(p => p);
        if (pathParts.length > 0 && pathParts[0] !== 'index.php' && !pathParts[0].includes('.php')) {
            const detectedPath = '/' + pathParts[0];
            console.log('Mobile menu: BASE_PATH detected from URL:', detectedPath);
            return detectedPath;
        }

        // Method 3: Check if we're in a subdirectory by looking at document.baseURI
        if (document.baseURI) {
            const baseUrl = new URL(document.baseURI);
            const basePath = baseUrl.pathname.split('/').filter(p => p && !p.includes('.'))[0];
            if (basePath) {
                const detectedPath = '/' + basePath;
                console.log('Mobile menu: BASE_PATH detected from baseURI:', detectedPath);
                return detectedPath;
            }
        }

        console.log('Mobile menu: No BASE_PATH detected, using empty string');
        return '';
    }

    const BASE_PATH = getBasePath();
    console.log('Mobile menu: Final BASE_PATH =', BASE_PATH);

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
