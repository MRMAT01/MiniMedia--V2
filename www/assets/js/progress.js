/**
 * Progress Tracking Manager
 * Tracks video playback and syncs with server
 */

const ProgressManager = {
    updateInterval: 5000, // Update every 5 seconds
    lastUpdate: 0,
    mediaId: null,

    /**
     * Initialize tracking for a video element
     */
    init(videoElement, mediaId) {
        if (!videoElement || !mediaId) return;

        this.mediaId = mediaId;

        // Load saved progress
        this.loadProgress(videoElement);

        // Track progress
        videoElement.addEventListener('timeupdate', () => {
            const now = Date.now();
            if (now - this.lastUpdate > this.updateInterval) {
                this.saveProgress(videoElement);
                this.lastUpdate = now;
            }
        });

        // Save on pause/end
        videoElement.addEventListener('pause', () => this.saveProgress(videoElement));
        videoElement.addEventListener('ended', () => this.markComplete());

        // Save before unload
        window.addEventListener('beforeunload', () => this.saveProgress(videoElement));
    },

    /**
     * Load saved progress from server
     */
    async loadProgress(videoElement) {
        try {
            const response = await fetch(`api/progress.php?action=get&media_id=${this.mediaId}`);
            const data = await response.json();

            if (data.success && data.has_progress && data.percentage < 95) {
                // Resume if not near the end
                if (confirm(`Resume from ${this.formatTime(data.position)}?`)) {
                    videoElement.currentTime = data.position;
                }
            }
        } catch (error) {
            console.error('Failed to load progress:', error);
        }
    },

    /**
     * Save current progress to server
     */
    async saveProgress(videoElement) {
        if (!this.mediaId || videoElement.paused && videoElement.currentTime === 0) return;

        try {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('media_id', this.mediaId);
            formData.append('position', Math.floor(videoElement.currentTime));
            formData.append('duration', Math.floor(videoElement.duration));

            await fetch('api/progress.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Failed to save progress:', error);
        }
    },

    /**
     * Mark as complete (remove progress)
     */
    async markComplete() {
        if (!this.mediaId) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('media_id', this.mediaId);

            await fetch('api/progress.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Failed to mark complete:', error);
        }
    },

    /**
     * Format seconds to HH:MM:SS
     */
    formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;

        if (h > 0) {
            return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }
        return `${m}:${s.toString().padStart(2, '0')}`;
    }
};
