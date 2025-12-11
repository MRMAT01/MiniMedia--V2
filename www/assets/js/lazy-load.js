/**
 * Lazy Loading for Images
 * Improves performance by loading images only when they're visible
 */

document.addEventListener('DOMContentLoaded', function () {
    // Select all images with data-src attribute
    const lazyImages = document.querySelectorAll('img[data-src]');

    if ('IntersectionObserver' in window) {
        // Use Intersection Observer for modern browsers
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;

                    // Set src from data-src
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                    }

                    // Optional: set srcset
                    if (img.dataset.srcset) {
                        img.srcset = img.dataset.srcset;
                    }

                    // Add loaded class for CSS transitions
                    img.classList.add('loaded');

                    // Clean up
                    img.removeAttribute('data-src');
                    img.removeAttribute('data-srcset');

                    // Stop observing this image
                    observer.unobserve(img);
                }
            });
        }, {
            // Start loading 50px before image enters viewport
            rootMargin: '50px',
            // Trigger when 10% of image is visible
            threshold: 0.1
        });

        // Observe all lazy images
        lazyImages.forEach(img => {
            // Add placeholder while loading
            if (!img.src && !img.classList.contains('loaded')) {
                img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23333" width="400" height="300"/%3E%3C/svg%3E';
            }
            imageObserver.observe(img);
        });

    } else {
        // Fallback for older browsers: load all images immediately
        lazyImages.forEach(img => {
            if (img.dataset.src) {
                img.src = img.dataset.src;
            }
            if (img.dataset.srcset) {
                img.srcset = img.dataset.srcset;
            }
            img.classList.add('loaded');
        });
    }
});

// CSS for lazy loading (add fade-in effect)
const style = document.createElement('style');
style.textContent = `
    img[data-src] {
        opacity: 0;
        transition: opacity 0.3s ease-in;
    }
    
    img.loaded {
        opacity: 1;
    }
    
    img[data-src]:not(.loaded) {
        background: #222;
    }
`;
document.head.appendChild(style);
