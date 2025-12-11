/**
 * Rating Manager
 * Handle star rating interactions
 */

const RatingManager = {
    /**
     * Submit rating
     */
    async rate(mediaId, rating, mediaType = 'media') {
        try {
            const formData = new FormData();
            formData.append('action', 'rate');
            formData.append('media_id', mediaId);
            formData.append('rating', rating);
            formData.append('media_type', mediaType);

            const response = await fetch('api/rating.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                ToastManager.success(`Rated ${rating} star${rating > 1 ? 's' : ''}`);
                this.updateDisplay(mediaId, data.user_rating, data.avg_rating, data.total_ratings);
            } else {
                ToastManager.error(data.error || 'Failed to submit rating');
            }

            return data;
        } catch (error) {
            console.error('Rating error:', error);
            ToastManager.error('Failed to submit rating');
            return { success: false };
        }
    },

    /**
     * Get ratings
     */
    async get(mediaId, mediaType = 'media') {
        try {
            const response = await fetch(`api/rating.php?action=get&media_id=${mediaId}&media_type=${mediaType}`);
            const data = await response.json();

            if (data.success) {
                this.updateDisplay(mediaId, data.user_rating, data.avg_rating, data.total_ratings);
            }

            return data;
        } catch (error) {
            console.error('Rating fetch error:', error);
            return { success: false };
        }
    },

    /**
     * Initialize rating stars
     */
    initStars() {
        // Find all rating containers and inject HTML
        const containers = document.querySelectorAll('.rating-container');
        containers.forEach(container => {
            const mediaId = container.dataset.mediaId;
            const mediaType = container.dataset.mediaType || 'media';

            // Inject HTML if not already present
            if (!container.querySelector('.rating-stars')) {
                container.innerHTML = `
                    ${this.createStars(mediaId, mediaType)}
                    <span class="avg-rating" data-media-id="${mediaId}"></span>
                    <span class="rating-count" data-media-id="${mediaId}"></span>
                `;
            }
        });

        // Now bind events to the injected stars
        const ratingStars = document.querySelectorAll('.rating-stars');

        ratingStars.forEach(container => {
            const mediaId = container.dataset.mediaId;
            const mediaType = container.dataset.mediaType || 'media';
            const stars = container.querySelectorAll('.star');

            stars.forEach((star, index) => {
                const rating = index + 1;

                // Hover effect
                star.addEventListener('mouseenter', () => {
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.classList.add('filled');
                        } else {
                            s.classList.remove('filled');
                        }
                    });
                });

                // Click to rate
                star.addEventListener('click', async () => {
                    await this.rate(mediaId, rating, mediaType);
                });
            });

            // Reset on mouse leave
            container.addEventListener('mouseleave', async () => {
                // We need to fetch the current user rating to reset correctly
                // Optimization: Store user rating in data attribute after fetch
                const userRating = container.dataset.userRating;

                if (userRating) {
                    stars.forEach((s, i) => {
                        if (i < userRating) {
                            s.classList.add('filled');
                        } else {
                            s.classList.remove('filled');
                        }
                    });
                } else {
                    // If we don't have it yet, we might need to wait or just clear
                    // For now, let's clear and let the fetch update it
                    stars.forEach(s => s.classList.remove('filled'));
                    // Re-fetch to be sure (or we could rely on the initial fetch)
                    this.get(mediaId, mediaType);
                }
            });

            // Load current rating
            this.get(mediaId, mediaType);
        });
    },

    /**
     * Create star rating HTML
     */
    createStars(mediaId, mediaType = 'media') {
        return `
            <div class="rating-stars" data-media-id="${mediaId}" data-media-type="${mediaType}">
                <span class="star" data-rating="1">★</span>
                <span class="star" data-rating="2">★</span>
                <span class="star" data-rating="3">★</span>
                <span class="star" data-rating="4">★</span>
                <span class="star" data-rating="5">★</span>
            </div>
        `;
    },

    /**
     * Update rating display
     */
    updateDisplay(mediaId, userRating, avgRating, totalRatings) {
        // Update user rating stars
        const userStars = document.querySelector(`.rating-stars[data-media-id="${mediaId}"]`);
        if (userStars) {
            // Store user rating for hover reset
            userStars.dataset.userRating = userRating || 0;

            const stars = userStars.querySelectorAll('.star');
            stars.forEach((star, index) => {
                if (index < userRating) {
                    star.classList.add('filled');
                } else {
                    star.classList.remove('filled');
                }
            });
        }

        // Update average rating display
        const avgDisplay = document.querySelector(`.avg-rating[data-media-id="${mediaId}"]`);
        if (avgDisplay) {
            avgDisplay.textContent = avgRating ? avgRating.toFixed(1) : '';
        }

        // Update total ratings count
        const countDisplay = document.querySelector(`.rating-count[data-media-id="${mediaId}"]`);
        if (countDisplay) {
            countDisplay.textContent = totalRatings ? `(${totalRatings})` : '';
        }
    }
};

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    RatingManager.initStars();
});
