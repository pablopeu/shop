/**
 * Carousel JavaScript
 * Handles carousel navigation and animations
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCarousel);
    } else {
        initCarousel();
    }

    function initCarousel() {
        const carouselContainer = document.querySelector('.carousel-container');
        if (!carouselContainer) return;

        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.carousel-dot');
        const prevBtn = document.querySelector('.carousel-prev');
        const nextBtn = document.querySelector('.carousel-next');
        const titleEl = document.getElementById('carousel-title');
        const carouselData = window.carouselData || [];

        if (slides.length === 0) return;

        let currentIndex = 0;
        let isAnimating = false;
        let autoPlayInterval = null;
        const autoPlayDelay = window.carouselAutoAdvanceTime || 5000; // Use configured time or default to 5 seconds

        // Touch handling for swipe navigation only
        let touchStartX = 0;
        let touchStartY = 0;
        let touchStartTime = 0;

        // Only handle touch events for mobile swipe gestures
        carouselContainer.addEventListener('touchstart', handleTouchStart, { passive: true });
        carouselContainer.addEventListener('touchend', handleTouchEnd, { passive: false });

        function handleTouchStart(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchStartTime = Date.now();
        }

        function handleTouchEnd(e) {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const touchDuration = Date.now() - touchStartTime;

            const deltaX = touchEndX - touchStartX;
            const deltaY = Math.abs(touchEndY - touchStartY);

            // Only handle swipes (not taps)
            // Swipe must be > 50px horizontal and < 100px vertical
            if (Math.abs(deltaX) > 50 && deltaY < 100 && touchDuration < 500) {
                // Check if swipe started on a link - if so, don't interfere
                const target = e.target;
                const isOnLink = target.closest('a');

                if (!isOnLink) {
                    e.preventDefault();

                    if (deltaX < 0) {
                        // Swipe left - next slide
                        goToNextSlide();
                    } else {
                        // Swipe right - previous slide
                        goToPrevSlide();
                    }

                    resetAutoPlay();
                }
            }
        }

        // Arrow button navigation
        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                goToPrevSlide();
                resetAutoPlay();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                goToNextSlide();
                resetAutoPlay();
            });
        }

        // Dot navigation
        dots.forEach((dot, index) => {
            dot.addEventListener('click', (e) => {
                e.stopPropagation();
                goToSlide(index);
                resetAutoPlay();
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                goToPrevSlide();
                resetAutoPlay();
            } else if (e.key === 'ArrowRight') {
                goToNextSlide();
                resetAutoPlay();
            }
        });

        function goToNextSlide() {
            const nextIndex = (currentIndex + 1) % slides.length;
            goToSlide(nextIndex);
        }

        function goToPrevSlide() {
            const prevIndex = (currentIndex - 1 + slides.length) % slides.length;
            goToSlide(prevIndex);
        }

        function goToSlide(index) {
            if (isAnimating || index === currentIndex) return;

            isAnimating = true;

            const currentSlide = slides[currentIndex];
            const nextSlide = slides[index];

            // Remove any existing animation classes
            slides.forEach(slide => {
                slide.classList.remove('active', 'sliding-out', 'sliding-in');
            });

            // Set up animation: current slides out to left, next slides in from right
            currentSlide.classList.add('active', 'sliding-out');
            nextSlide.classList.add('active', 'sliding-in');

            // Update dots
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });

            // Update title
            if (titleEl && carouselData[index]) {
                const newTitle = carouselData[index].title || '';
                if (newTitle) {
                    // Fade out
                    titleEl.style.opacity = '0';

                    setTimeout(() => {
                        titleEl.textContent = newTitle;
                        // Fade in
                        titleEl.style.opacity = '1';
                    }, 250);
                } else {
                    titleEl.style.opacity = '0';
                }
            }

            // Clean up after animation
            setTimeout(() => {
                currentSlide.classList.remove('active', 'sliding-out');
                nextSlide.classList.remove('sliding-in');

                currentIndex = index;
                isAnimating = false;
            }, 500); // Match animation duration
        }

        function startAutoPlay() {
            if (slides.length > 1) {
                autoPlayInterval = setInterval(goToNextSlide, autoPlayDelay);
            }
        }

        function stopAutoPlay() {
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        }

        function resetAutoPlay() {
            stopAutoPlay();
            startAutoPlay();
        }

        // Pause on hover
        carouselContainer.addEventListener('mouseenter', stopAutoPlay);
        carouselContainer.addEventListener('mouseleave', startAutoPlay);

        // Start autoplay
        startAutoPlay();

        // Pause when tab is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoPlay();
            } else {
                startAutoPlay();
            }
        });
    }
})();
