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
        console.log('[CAROUSEL] Initializing carousel...');

        const carouselContainer = document.querySelector('.carousel-container');
        if (!carouselContainer) {
            console.log('[CAROUSEL] No carousel container found');
            return;
        }

        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.carousel-dot');
        const prevBtn = document.querySelector('.carousel-prev');
        const nextBtn = document.querySelector('.carousel-next');
        const titleEl = document.getElementById('carousel-title');
        const carouselData = window.carouselData || [];

        console.log('[CAROUSEL] Found', slides.length, 'slides');
        console.log('[CAROUSEL] Carousel data:', carouselData);

        if (slides.length === 0) return;

        // Log all carousel links
        const allLinks = document.querySelectorAll('.carousel-link-overlay');
        console.log('[CAROUSEL] Found', allLinks.length, 'link overlays');
        allLinks.forEach((link, index) => {
            console.log(`[CAROUSEL] Link ${index}: href="${link.href}", z-index="${window.getComputedStyle(link).zIndex}"`);
        });

        let currentIndex = 0;
        let isAnimating = false;
        let autoPlayInterval = null;
        const autoPlayDelay = window.carouselAutoAdvanceTime || 5000; // Use configured time or default to 5 seconds

        // Touch handling for swipe navigation only
        let touchStartX = 0;
        let touchStartY = 0;
        let touchStartTime = 0;

        // Add click listener to links for debugging
        allLinks.forEach((link, index) => {
            link.addEventListener('click', (e) => {
                console.log(`[CAROUSEL] ✅ CLICK on link ${index}:`, link.href);
                console.log('[CAROUSEL] Event:', e);
                console.log('[CAROUSEL] Target:', e.target);
                console.log('[CAROUSEL] CurrentTarget:', e.currentTarget);
                // DO NOT prevent default - let it navigate
            });
        });

        // Add global click listener on carousel container to see who captures clicks
        carouselContainer.addEventListener('click', (e) => {
            console.log('[CAROUSEL] 🖱️ CLICK detected on container!');
            console.log('[CAROUSEL] Target:', e.target);
            console.log('[CAROUSEL] Target className:', e.target.className);
            console.log('[CAROUSEL] Target tagName:', e.target.tagName);
            console.log('[CAROUSEL] Computed z-index:', window.getComputedStyle(e.target).zIndex);
            console.log('[CAROUSEL] Computed pointer-events:', window.getComputedStyle(e.target).pointerEvents);

            // Check if target is or is inside a link
            const closestLink = e.target.closest('a');
            console.log('[CAROUSEL] Closest link:', closestLink ? closestLink.href : 'NONE');
        }, true); // Use capture phase

        // Add even higher level listener on document
        document.addEventListener('click', (e) => {
            // Only log if click is within carousel area
            if (e.target.closest('.carousel-wrapper, .carousel-container')) {
                console.log('[CAROUSEL] 🌍 DOCUMENT LEVEL CLICK detected!');
                console.log('[CAROUSEL] Target:', e.target);
                console.log('[CAROUSEL] Target className:', e.target.className);
                console.log('[CAROUSEL] Target tagName:', e.target.tagName);
                console.log('[CAROUSEL] Path:', e.composedPath().map(el => el.className || el.tagName).join(' → '));
            }
        }, true); // Capture phase

        // Only handle touch events for mobile swipe gestures
        carouselContainer.addEventListener('touchstart', handleTouchStart, { passive: true });
        carouselContainer.addEventListener('touchend', handleTouchEnd, { passive: false });

        function handleTouchStart(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchStartTime = Date.now();
            console.log('[CAROUSEL] Touch start at', touchStartX, touchStartY);
        }

        function handleTouchEnd(e) {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const touchDuration = Date.now() - touchStartTime;

            const deltaX = touchEndX - touchStartX;
            const deltaY = Math.abs(touchEndY - touchStartY);

            console.log('[CAROUSEL] Touch end - deltaX:', deltaX, 'deltaY:', deltaY, 'duration:', touchDuration);

            // Only handle swipes (not taps)
            // Swipe must be > 50px horizontal and < 100px vertical
            if (Math.abs(deltaX) > 50 && deltaY < 100 && touchDuration < 500) {
                // Check if swipe started on a link - if so, don't interfere
                const target = e.target;
                const isOnLink = target.closest('a');

                console.log('[CAROUSEL] Swipe detected, target:', target, 'isOnLink:', isOnLink);

                if (!isOnLink) {
                    console.log('[CAROUSEL] Preventing swipe and navigating slides');
                    e.preventDefault();

                    if (deltaX < 0) {
                        // Swipe left - next slide
                        goToNextSlide();
                    } else {
                        // Swipe right - previous slide
                        goToPrevSlide();
                    }

                    resetAutoPlay();
                } else {
                    console.log('[CAROUSEL] Swipe on link, not interfering');
                }
            } else {
                console.log('[CAROUSEL] Not a swipe (too short or vertical)');
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
            if (isAnimating || index === currentIndex) {
                console.log('[CAROUSEL] goToSlide blocked - isAnimating:', isAnimating, 'currentIndex:', currentIndex, 'targetIndex:', index);
                return;
            }

            console.log('[CAROUSEL] goToSlide called - from', currentIndex, 'to', index);
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
                console.log('[CAROUSEL] Starting autoplay with delay:', autoPlayDelay);
                autoPlayInterval = setInterval(() => {
                    console.log('[CAROUSEL] Autoplay tick - advancing slide');
                    goToNextSlide();
                }, autoPlayDelay);
            }
        }

        function stopAutoPlay() {
            if (autoPlayInterval) {
                console.log('[CAROUSEL] Stopping autoplay');
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        }

        function resetAutoPlay() {
            console.log('[CAROUSEL] Resetting autoplay');
            stopAutoPlay();
            startAutoPlay();
        }

        // Pause on hover
        carouselContainer.addEventListener('mouseenter', () => {
            console.log('[CAROUSEL] Mouse enter - pausing autoplay');
            stopAutoPlay();
        });
        carouselContainer.addEventListener('mouseleave', () => {
            console.log('[CAROUSEL] Mouse leave - resuming autoplay');
            startAutoPlay();
        });

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
