/**
 * Carousel V2 JavaScript
 * Auto-rotación hacia la izquierda con puntos indicadores
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
        console.log('[CAROUSEL V2] Initializing...');

        const wrapper = document.querySelector('.carousel-v2-wrapper');
        if (!wrapper) {
            console.log('[CAROUSEL V2] No carousel wrapper found');
            return;
        }

        const slides = document.querySelectorAll('.carousel-v2-slide');
        const dots = document.querySelectorAll('.carousel-v2-dot');
        const carouselData = window.carouselV2Data || {};

        console.log('[CAROUSEL V2] Found', slides.length, 'slides');

        if (slides.length === 0) {
            console.log('[CAROUSEL V2] No slides found');
            return;
        }

        let currentIndex = 0;
        let isAnimating = false;
        let autoPlayInterval = null;
        const autoAdvanceTime = carouselData.autoAdvanceTime || 5000;

        console.log('[CAROUSEL V2] Auto-advance time:', autoAdvanceTime, 'ms');

        /**
         * Go to next slide (always advances to the right in the array)
         */
        function goToNextSlide() {
            const nextIndex = (currentIndex + 1) % slides.length;
            goToSlide(nextIndex);
        }

        /**
         * Go to a specific slide with animation
         */
        function goToSlide(targetIndex) {
            if (isAnimating || targetIndex === currentIndex) {
                return;
            }

            console.log('[CAROUSEL V2] Transitioning from slide', currentIndex, 'to', targetIndex);

            isAnimating = true;

            const currentSlide = slides[currentIndex];
            const nextSlide = slides[targetIndex];

            // Remove all animation classes from all slides
            slides.forEach(slide => {
                slide.classList.remove('active', 'slide-out', 'slide-in');
            });

            // Animate: current slide out to left, next slide in from right
            currentSlide.classList.add('active', 'slide-out');
            nextSlide.classList.add('active', 'slide-in');

            // Update dots
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === targetIndex);
            });

            // Clean up after animation completes
            setTimeout(() => {
                currentSlide.classList.remove('active', 'slide-out');
                nextSlide.classList.remove('slide-in');

                currentIndex = targetIndex;
                isAnimating = false;

                console.log('[CAROUSEL V2] Transition complete. Current slide:', currentIndex);
            }, 600); // Match animation duration in CSS
        }

        /**
         * Start auto-rotation
         */
        function startAutoPlay() {
            if (slides.length > 1) {
                console.log('[CAROUSEL V2] Starting auto-play');
                autoPlayInterval = setInterval(() => {
                    goToNextSlide();
                }, autoAdvanceTime);
            }
        }

        /**
         * Stop auto-rotation
         */
        function stopAutoPlay() {
            if (autoPlayInterval) {
                console.log('[CAROUSEL V2] Stopping auto-play');
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        }

        /**
         * Reset auto-rotation (stop and start again)
         */
        function resetAutoPlay() {
            stopAutoPlay();
            startAutoPlay();
        }

        // Dot navigation - click on a dot to go to that slide
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                goToSlide(index);
                resetAutoPlay(); // Reset timer when user manually navigates
            });
        });

        // Pause on hover
        wrapper.addEventListener('mouseenter', () => {
            console.log('[CAROUSEL V2] Mouse enter - pausing auto-play');
            stopAutoPlay();
        });

        wrapper.addEventListener('mouseleave', () => {
            console.log('[CAROUSEL V2] Mouse leave - resuming auto-play');
            startAutoPlay();
        });

        // Pause when tab is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('[CAROUSEL V2] Tab hidden - pausing auto-play');
                stopAutoPlay();
            } else {
                console.log('[CAROUSEL V2] Tab visible - resuming auto-play');
                startAutoPlay();
            }
        });

        // Keyboard navigation (optional)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') {
                goToNextSlide();
                resetAutoPlay();
            } else if (e.key === 'ArrowLeft') {
                const prevIndex = (currentIndex - 1 + slides.length) % slides.length;
                goToSlide(prevIndex);
                resetAutoPlay();
            }
        });

        // Start auto-play
        startAutoPlay();

        console.log('[CAROUSEL V2] Initialization complete');
    }
})();
