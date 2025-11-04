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

        // Touch/click handling for carousel navigation
        let touchStartX = 0;
        let touchEndX = 0;
        let clickStartX = 0;
        let clickStartY = 0;
        let clickStartTime = 0;

        // Mouse/touch down
        carouselContainer.addEventListener('mousedown', handleInteractionStart);
        carouselContainer.addEventListener('touchstart', handleInteractionStart);

        // Mouse/touch up
        carouselContainer.addEventListener('mouseup', handleInteractionEnd);
        carouselContainer.addEventListener('touchend', handleInteractionEnd);

        function handleInteractionStart(e) {
            clickStartTime = Date.now();

            if (e.type === 'mousedown') {
                clickStartX = e.clientX;
                clickStartY = e.clientY;
            } else if (e.type === 'touchstart') {
                touchStartX = e.touches[0].clientX;
                clickStartX = touchStartX;
                clickStartY = e.touches[0].clientY;
            }
        }

        function handleInteractionEnd(e) {
            const clickEndTime = Date.now();
            const clickDuration = clickEndTime - clickStartTime;

            let endX, endY;

            if (e.type === 'mouseup') {
                endX = e.clientX;
                endY = e.clientY;
            } else if (e.type === 'touchend') {
                touchEndX = e.changedTouches[0].clientX;
                endX = touchEndX;
                endY = e.changedTouches[0].clientY;
            }

            const deltaX = Math.abs(endX - clickStartX);
            const deltaY = Math.abs(endY - clickStartY);

            // Check if user clicked on a link or button (allow navigation)
            const clickedElement = e.target;
            const isLink = clickedElement.tagName === 'A' || clickedElement.closest('a');
            const isButton = clickedElement.tagName === 'BUTTON' || clickedElement.closest('button');

            // If clicked on a link, don't interfere with navigation
            if (isLink) {
                return; // Let the browser handle the link click
            }

            // Check if it's a click/tap (not a drag/scroll)
            // Click/tap: short duration, minimal movement
            if (clickDuration < 300 && deltaX < 10 && deltaY < 10) {
                // Don't navigate if clicking on navigation buttons
                if (isButton) {
                    return;
                }

                // Navigate to next slide on tap/click anywhere on carousel
                e.preventDefault();
                goToNextSlide();
                resetAutoPlay();
            } else if (deltaX > 50 && deltaX > deltaY) {
                // Swipe gesture - don't prevent default if it was on a link
                if (!isLink) {
                    e.preventDefault();
                }

                if (endX < clickStartX) {
                    // Swipe left - next slide
                    goToNextSlide();
                } else {
                    // Swipe right - previous slide
                    goToPrevSlide();
                }

                resetAutoPlay();
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
