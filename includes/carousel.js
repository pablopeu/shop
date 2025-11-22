/**
 * Carousel V2 JavaScript - Infinite Horizontal Scroll
 * Desplazamiento continuo horizontal con efecto loop infinito
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCarouselV2);
    } else {
        initCarouselV2();
    }

    function initCarouselV2() {
        console.log('[CAROUSEL V2] Initializing infinite scroll...');

        const wrapper = document.querySelector('.carousel-v2-wrapper');
        const track = document.getElementById('carousel-track');

        if (!wrapper || !track) {
            console.log('[CAROUSEL V2] No carousel found');
            return;
        }

        const slides = track.querySelectorAll('.carousel-v2-slide');
        const carouselData = window.carouselV2Data || {};
        const originalSlidesCount = carouselData.slides?.length || 0;

        console.log('[CAROUSEL V2] Original slides:', originalSlidesCount, 'Total rendered:', slides.length);

        if (originalSlidesCount === 0) {
            console.log('[CAROUSEL V2] No slides found');
            return;
        }

        let currentPosition = 0;
        let isPaused = false;
        let animationFrame = null;

        // Velocity: pixels per frame (60fps)
        // Slower speed for better viewing: 0.5 pixels per frame = 30 pixels/sec
        const scrollSpeed = 0.5;

        // Calculate when to reset (after one full set of slides)
        const slideWidth = slides[0].offsetWidth;
        const resetPoint = slideWidth * originalSlidesCount;

        console.log('[CAROUSEL V2] Slide width:', slideWidth, 'Reset point:', resetPoint);

        /**
         * Animate the carousel continuously
         */
        function animate() {
            if (!isPaused) {
                currentPosition += scrollSpeed;

                // Reset to start when we've scrolled through one full set
                if (currentPosition >= resetPoint) {
                    currentPosition = 0;
                }

                track.style.transform = `translateX(-${currentPosition}px)`;
            }

            animationFrame = requestAnimationFrame(animate);
        }

        /**
         * Pause animation
         */
        function pause() {
            isPaused = true;
            console.log('[CAROUSEL V2] Paused');
        }

        /**
         * Resume animation
         */
        function resume() {
            isPaused = false;
            console.log('[CAROUSEL V2] Resumed');
        }

        // Pause on hover
        wrapper.addEventListener('mouseenter', pause);
        wrapper.addEventListener('mouseleave', resume);

        // Pause when tab is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                pause();
            } else {
                resume();
            }
        });

        // Start animation
        animate();

        console.log('[CAROUSEL V2] Infinite scroll started');
    }
})();
