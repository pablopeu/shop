/**
 * Unsaved Changes Warning
 * Warns user when leaving page with unsaved changes
 */

(function() {
    let hasUnsavedChanges = false;
    let formSubmitted = false;

    // Track changes in all form inputs
    function trackFormChanges() {
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            // Track changes on all input types
            const inputs = form.querySelectorAll('input, textarea, select');

            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    if (!formSubmitted) {
                        hasUnsavedChanges = true;
                    }
                });

                input.addEventListener('change', () => {
                    if (!formSubmitted) {
                        hasUnsavedChanges = true;
                    }
                });
            });

            // Reset flag when form is submitted
            form.addEventListener('submit', () => {
                formSubmitted = true;
                hasUnsavedChanges = false;
            });
        });
    }

    // Warn before leaving page
    function warnBeforeUnload(e) {
        if (hasUnsavedChanges && !formSubmitted) {
            e.preventDefault();
            // Modern browsers ignore custom message and show their own
            e.returnValue = '';
            return '';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackFormChanges);
    } else {
        trackFormChanges();
    }

    // Add beforeunload listener
    window.addEventListener('beforeunload', warnBeforeUnload);
})();
