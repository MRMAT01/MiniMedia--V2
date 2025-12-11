/**
 * Watchlist Manager
 * Handle adding/removing items from watchlist
 */

const WatchlistManager = {
    /**
     * Toggle watchlist status
     */
    async toggle(mediaId, mediaType = 'media') {
        const inWatchlist = await this.check(mediaId, mediaType);

        if (inWatchlist) {
            return await this.remove(mediaId, mediaType);
        } else {
            return await this.add(mediaId, mediaType);
        }
    },

    /**
     * Add to watchlist
     */
    async add(mediaId, mediaType = 'media') {
        try {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('media_id', mediaId);
            formData.append('media_type', mediaType);

            const response = await fetch('api/watchlist.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                ToastManager.success('Added to watchlist');
                this.updateButton(mediaId, true);
            } else {
                ToastManager.error(data.error || 'Failed to add to watchlist');
            }

            return data;
        } catch (error) {
            console.error('Watchlist add error:', error);
            ToastManager.error('Failed to add to watchlist');
            return { success: false };
        }
    },

    /**
     * Remove from watchlist
     */
    async remove(mediaId, mediaType = 'media') {
        try {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('media_id', mediaId);
            formData.append('media_type', mediaType);

            const response = await fetch('api/watchlist.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                ToastManager.success('Removed from watchlist');
                this.updateButton(mediaId, false);
            } else {
                ToastManager.error(data.error || 'Failed to remove from watchlist');
            }

            return data;
        } catch (error) {
            console.error('Watchlist remove error:', error);
            ToastManager.error('Failed to remove from watchlist');
            return { success: false };
        }
    },

    /**
     * Check if in watchlist
     */
    async check(mediaId, mediaType = 'media') {
        try {
            const formData = new FormData();
            formData.append('action', 'check');
            formData.append('media_id', mediaId);
            formData.append('media_type', mediaType);

            const response = await fetch('api/watchlist.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            return data.success && data.in_watchlist;
        } catch (error) {
            console.error('Watchlist check error:', error);
            return false;
        }
    },

    /**
     * Update watchlist button state
     */
    updateButton(mediaId, inWatchlist) {
        const button = document.querySelector(`[data-media-id="${mediaId}"].watchlist-btn, [data-media-id="${mediaId}"].watchlist-button`);
        if (button) {
            if (inWatchlist) {
                button.classList.add('in-watchlist');
                button.innerHTML = '<i class="fas fa-heart"> List</i>';
                button.title = 'Remove from watchlist';
            } else {
                button.classList.remove('in-watchlist');
                button.innerHTML = '<i class="fas fa-plus"> List</i>';
                button.title = 'Add to watchlist';
            }
        }
    },

    /**
     * Initialize watchlist buttons
     */
    async initButtons() {
        const buttons = document.querySelectorAll('.watchlist-btn, .watchlist-button');

        for (const button of buttons) {
            const mediaId = button.dataset.mediaId;
            const mediaType = button.dataset.mediaType || 'movie';

            if (!mediaId) continue;

            // Check current status
            const inWatchlist = await this.check(mediaId, mediaType);
            this.updateButton(mediaId, inWatchlist);

            // Add click handler
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                await this.toggle(mediaId, mediaType);
            });
        }
    }
};

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    WatchlistManager.initButtons();
});
