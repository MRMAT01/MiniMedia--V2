/**
 * Loading Manager
 * Handles loading overlays and progress indicators
 */

const LoadingManager = {
    /**
     * Show basic loading overlay
     */
    show(message = 'Loading...') {
        this.hide(); // Remove any existing overlay
        
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.id = 'loadingOverlay';
        overlay.innerHTML = `
            <div class="spinner"></div>
            <p>${message}</p>
        `;
        document.body.appendChild(overlay);
    },
    
    /**
     * Hide loading overlay
     */
    hide() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
        }
    },
    
    /**
     * Show loading with progress bar
     */
    showProgress(message = 'Processing...') {
        this.hide();
        
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.id = 'loadingOverlay';
        overlay.innerHTML = `
            <div class="spinner"></div>
            <p>${message}</p>
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: 0%">0%</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    },
    
    /**
     * Update progress percentage
     */
    updateProgress(percent, message = null) {
        const fill = document.getElementById('progressFill');
        if (fill) {
            const rounded = Math.round(percent);
            fill.style.width = rounded + '%';
            fill.textContent = rounded + '%';
        }
        
        if (message) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                const p = overlay.querySelector('p');
                if (p) p.textContent = message;
            }
        }
    },
    
    /**
     * Add loading state to button
     */
    setButtonLoading(button, loading = true) {
        if (loading) {
            button.classList.add('btn-loading');
            button.disabled = true;
            button.dataset.originalText = button.textContent;
        } else {
            button.classList.remove('btn-loading');
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    },
    
    /**
     * Wrap async function with loading indicator
     */
    async wrap(asyncFn, message = 'Loading...') {
        this.show(message);
        try {
            const result = await asyncFn();
            return result;
        } finally {
            this.hide();
        }
    }
};

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LoadingManager;
}
