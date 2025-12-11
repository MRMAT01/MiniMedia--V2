/**
 * Global Search Manager
 * Handles search input, AJAX requests, and results display
 */

const SearchManager = {
    debounceTimer: null,
    currentResults: [],

    /**
     * Initialize search functionality
     * @param {string} inputId ID of search input element
     * @param {string} resultsId ID of results container element
     */
    init(inputId, resultsId) {
        const input = document.getElementById(inputId);
        const results = document.getElementById(resultsId);

        if (!input || !results) {
            console.error('Search elements not found');
            return;
        }

        // Input handler with debouncing
        input.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            const query = e.target.value.trim();

            if (query.length < 3) {
                results.innerHTML = '';
                results.style.display = 'none';
                return;
            }

            this.debounceTimer = setTimeout(() => {
                this.search(query, results);
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });

        // Show results when focusing on input with existing query
        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 3 && results.innerHTML) {
                results.style.display = 'block';
            }
        });

        // Handle Enter key
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && this.currentResults.length > 0) {
                // Navigate to first result
                window.location.href = this.getLink(this.currentResults[0]);
            }
        });
    },

    /**
     * Perform search via AJAX
     */
    async search(query, resultsElement) {
        try {
            const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                this.currentResults = data.results;
                this.displayResults(data.results, resultsElement);
            } else {
                this.displayError(resultsElement);
            }
        } catch (error) {
            console.error('Search error:', error);
            this.displayError(resultsElement);
        }
    },

    /**
     * Display search results
     */
    displayResults(results, element) {
        if (results.length === 0) {
            element.innerHTML = '<div class="search-result-item no-results">No results found</div>';
        } else {
            element.innerHTML = results.map(item => {
                const coverPath = item.cover.startsWith('http') ? item.cover : item.cover;
                return `
                    <a href="${this.getLink(item)}" class="search-result-item">
                        <img src="${coverPath}" alt="${this.escapeHtml(item.title)}" 
                             onerror="this.src='images/noimage.png'">
                        <div class="search-result-info">
                            <strong>${this.escapeHtml(item.title)}</strong>
                            <small>${this.escapeHtml(item.subtitle)}</small>
                        </div>
                        <i class="fas fa-chevron-right search-result-icon"></i>
                    </a>
                `;
            }).join('');
        }
        element.style.display = 'block';
    },

    /**
     * Display error message
     */
    displayError(element) {
        element.innerHTML = '<div class="search-result-item error">Search failed. Please try again.</div>';
        element.style.display = 'block';
    },

    /**
     * Get link for result item
     */
    getLink(item) {
        if (item.type === 'music') {
            return `music.php`;
        } else if (item.type === 'tv') {
            // TV shows use the show parameter with the title
            return `tv_show.php?show=${encodeURIComponent(item.title)}`;
        } else if (item.type === 'featured') {
            return `featured_media.php?short=${item.short_url}`;
        } else if (item.type === 'movie') {
            return `movies.php?short=${item.short_url}`;
        }
        // Default fallback
        return `movies.php?short=${item.short_url}`;
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Auto-initialize if search elements exist
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('globalSearchResults');

    if (searchInput && searchResults) {
        SearchManager.init('globalSearch', 'globalSearchResults');
    }
});
