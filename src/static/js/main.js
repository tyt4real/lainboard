// Global variable to track current expanded image
let currentExpandedImage = null;

function expandImage(linkElement, fullUrl) {
    // If there's already an expanded image, close it first
    if (currentExpandedImage) {
        closeExpandedImage();
        // If we were clicking the same image, don't reopen it
        // Compare fullUrl to avoid issues with different linkElement references
        if (currentExpandedImage.fullUrl === fullUrl) {
            currentExpandedImage = null;
            return;
        }
    }

    // Create overlay container
    const overlay = document.createElement('div');
    overlay.id = 'image-expansion-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;

    // Create expanded image container
    const imageContainer = document.createElement('div');
    imageContainer.style.cssText = `
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        align-items: center;
        justify-content: center;
    `;

    // Create the expanded image
    const expandedImg = document.createElement('img');
    expandedImg.src = fullUrl;
    expandedImg.style.cssText = `
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: 4px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        transform: scale(0.9);
        transition: transform 0.3s ease;
    `;

    // Add loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.textContent = 'Loading...';
    loadingIndicator.style.cssText = `
        position: absolute;
        color: white;
        font-size: 16px;
        opacity: 0.7;
    `;
    imageContainer.appendChild(loadingIndicator);

    // Handle image load
    expandedImg.onload = function() {
        loadingIndicator.remove();
        expandedImg.style.transform = 'scale(1)';
        overlay.style.opacity = '1';
    };

    // Handle click to close
    overlay.addEventListener('click', function(e) {
        // Only close if clicking the overlay background, not the image
        if (e.target === overlay) {
            closeExpandedImage();
        }
    });

    // Prevent closing when clicking the image
    expandedImg.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Assemble the overlay
    imageContainer.appendChild(expandedImg);
    overlay.appendChild(imageContainer);
    document.body.appendChild(overlay);

    // Store reference to the expanded image details for comparison
    currentExpandedImage = { element: linkElement, fullUrl: fullUrl };

    // Add ESC key support
    document.addEventListener('keydown', handleKeyDown);
}

function closeExpandedImage() {
    const overlay = document.getElementById('image-expansion-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
    currentExpandedImage = null;
    document.removeEventListener('keydown', handleKeyDown);
}

function handleKeyDown(e) {
    if (e.key === 'Escape' && currentExpandedImage) {
        closeExpandedImage();
    }
}

function toggleImageSpoiler(linkElement) {
    const spoilerContainer = linkElement.closest('.image-spoiler');
    if (!spoilerContainer) return false;

    spoilerContainer.classList.toggle('revealed');

    if (spoilerContainer.classList.contains('revealed')) {
        // If revealed, potentially expand the image as well
        expandImage(linkElement, linkElement.href);
    } else {
        // If re-censored, close the expanded image if it's currently this one
        if (currentExpandedImage && currentExpandedImage.fullUrl === linkElement.href) {
            closeExpandedImage();
        }
    }
    return false; // Prevent default link behavior
}

// Enhanced quotelink hover previews with theme support
document.querySelectorAll('.quotelink').forEach(function(link) {
    link.addEventListener('mouseenter', function(e) {
        var postId = this.textContent.replace('>>', '');
        var targetPost = document.getElementById('p' + postId);
        if (targetPost) {
            var preview = document.createElement('div');
            preview.className = 'quote-preview hover-preview';
            preview.innerHTML = targetPost.innerHTML;

            // Use computed styles to get theme colors
            var computedStyle = getComputedStyle(document.documentElement);
            var postBg = computedStyle.getPropertyValue('--post-bg');
            var postBorder = computedStyle.getPropertyValue('--post-border');

            preview.style.cssText = `
                position: absolute;
                background: ${postBg};
                border: 1px solid ${postBorder};
                padding: 12px;
                max-width: 400px;
                max-height: 300px;
                overflow-y: auto;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                border-radius: 4px;
                font-size: 13px;
                line-height: 1.4;
            `;

            // Position the preview intelligently
            var rect = link.getBoundingClientRect();
            var previewRect = preview.getBoundingClientRect();

            // Try to position to the right first
            var left = rect.right + 10;
            var top = rect.top;

            // If it would go off-screen to the right, position to the left
            if (left + 400 > window.innerWidth) {
                left = rect.left - 410;
            }

            // If it would go off-screen to the left, center it
            if (left < 10) {
                left = Math.max(10, (window.innerWidth - 400) / 2);
            }

            // Adjust vertical position if needed
            if (top + 300 > window.innerHeight) {
                top = window.innerHeight - 310;
            }

            preview.style.left = left + 'px';
            preview.style.top = top + 'px';
            preview.id = 'quote-preview-' + postId;

            document.body.appendChild(preview);
        }
    });

    link.addEventListener('mouseleave', function() {
        var postId = this.textContent.replace('>>', '');
        var preview = document.getElementById('quote-preview-' + postId);
        if (preview) {
            preview.remove();
        }
    });
});

// Image hover previews
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.post-thumb').forEach(function(thumb) {
        var fullImageUrl = thumb.src.replace('/uploads/thumbnails/', '/uploads/');
        var preview = null;

        thumb.addEventListener('mouseenter', function(e) {
            // Only create preview if the image isn't already expanded and no image is currently expanded
            // Also, don't show hover preview if it's a censored image and not yet revealed
            const spoilerContainer = thumb.closest('.image-spoiler');
            if (thumb.dataset.expanded !== 'true' && !currentExpandedImage && (!spoilerContainer || spoilerContainer.classList.contains('revealed'))) {
                preview = document.createElement('img');
                preview.src = fullImageUrl;
                preview.className = 'image-preview hover-preview';
                preview.style.cssText = `
                    position: absolute;
                    max-width: 400px;
                    max-height: 400px;
                    z-index: 10000;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    border-radius: 4px;
                    border: 1px solid var(--post-border);
                    background: var(--post-bg);
                    display: none;
                `;

                // Position the preview
                var rect = thumb.getBoundingClientRect();
                var left = rect.right + 10;
                var top = rect.top;

                // If it would go off-screen to the right, position to the left
                if (left + 400 > window.innerWidth) {
                    left = rect.left - 410;
                }

                // If it would go off-screen to the left, center it
                if (left < 10) {
                    left = Math.max(10, (window.innerWidth - 400) / 2);
                }

                // Adjust vertical position if needed
                if (top + 400 > window.innerHeight) {
                    top = window.innerHeight - 410;
                }

                preview.style.left = left + 'px';
                preview.style.top = top + 'px';

                // Load the image first, then show it
                preview.onload = function() {
                    preview.style.display = 'block';
                };

                document.body.appendChild(preview);
            }
        });

        thumb.addEventListener('mouseleave', function() {
            if (preview) {
                preview.remove();
                preview = null;
            }
        });

        // Also hide preview when moving mouse away from the thumbnail area
        thumb.addEventListener('mouseout', function(e) {
            // Check if mouse is still over the thumbnail or preview
            var rect = thumb.getBoundingClientRect();
            var x = e.clientX;
            var y = e.clientY;

            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                if (preview) {
                    preview.remove();
                    preview = null;
                }
            }
        });
    });
});

document.querySelectorAll('.post-number a').forEach(function(link) {
    link.addEventListener('click', function(e) {
        var textarea = document.querySelector('textarea[name="comment"]');
        if (textarea) {
            e.preventDefault();
            var postId = this.textContent;
            textarea.value += '>>' + postId + '\n';
            textarea.focus();
        }
    });
});