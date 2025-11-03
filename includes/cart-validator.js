/**
 * Cart Validator - Validates cart items against product availability
 * This script should be included in all public pages to ensure cart integrity
 */

// Validate cart and update count
async function validateCartAndUpdateCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');

    if (cart.length === 0) {
        updateCartCountDisplay(0);
        return;
    }

    const productIds = cart.map(item => item.product_id || item.id);

    try {
        const response = await fetch('/api/get_products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ product_ids: productIds })
        });

        if (!response.ok) {
            // If API fails, just update count without validation
            updateCartCountDisplay(cart.reduce((sum, item) => sum + item.quantity, 0));
            return;
        }

        const products = await response.json();

        // Clean cart by removing unavailable products
        const cleanedCart = cart.filter(item => {
            const productId = item.product_id || item.id;
            const product = products.find(p => p.id === productId);

            // Keep only active products with stock
            if (!product || !product.active || product.stock <= 0) {
                return false;
            }

            // Adjust quantity if it exceeds available stock
            if (item.quantity > product.stock) {
                item.quantity = product.stock;
            }

            return true;
        });

        // If cart was cleaned, save it
        if (cleanedCart.length !== cart.length) {
            localStorage.setItem('cart', JSON.stringify(cleanedCart));
        }

        // Update count with valid items only
        const validCount = cleanedCart.reduce((sum, item) => sum + item.quantity, 0);
        updateCartCountDisplay(validCount);

    } catch (error) {
        console.error('Error validating cart:', error);
        // Fallback: show count without validation
        updateCartCountDisplay(cart.reduce((sum, item) => sum + item.quantity, 0));
    }
}

// Update cart count display
function updateCartCountDisplay(count) {
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = count;
    }
}

// Simple update (without validation) for immediate feedback
function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    updateCartCountDisplay(count);
}

// Validate on page load
document.addEventListener('DOMContentLoaded', () => {
    validateCartAndUpdateCount();
});

// Also validate when storage changes (in case another tab modified the cart)
window.addEventListener('storage', (e) => {
    if (e.key === 'cart') {
        validateCartAndUpdateCount();
    }
});
