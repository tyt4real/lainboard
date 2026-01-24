class UserSettings {
    constructor() {
        this.settings = {
            threadUpdater: false,
            threadWatcher: false,
            threadHiding: false,
            clickableLinks: false,
            showAnnouncements: true,
            watchedThreads: [],
            hiddenThreads: [],
            watchedThreadsWidget: {
                visible: true,
                minimized: false,
                position: { top: 16, right: 16 }
            }
        };
        this.init();
    }

    init() {
        this.loadSettings();
        this.setupModal();
        this.setupDraggable();
        this.setupEventListeners();
        this.setupWatchedThreadsWidget();
        this.setupAnnouncementsToggle();
        this.applyCurrentSettings();
    }

    loadSettings() {
        const saved = localStorage.getItem('lainboard-settings');
        if (saved) {
            try {
                this.settings = { ...this.settings, ...JSON.parse(saved) };
            } catch (e) {
                console.error('Failed to parse saved settings:', e);
            }
        }
    }

    saveSettings() {
        localStorage.setItem('lainboard-settings', JSON.stringify(this.settings));
    }

    setupModal() {
        const toggle = document.getElementById('settings-toggle');
        const modal = document.getElementById('settings-modal');
        const closeBtn = document.getElementById('settings-close');
        const cancelBtn = document.getElementById('settings-cancel');
        const saveBtn = document.getElementById('settings-save');

        toggle?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showModal();
        });

        closeBtn?.addEventListener('click', () => this.hideModal());
        cancelBtn?.addEventListener('click', () => this.hideModal());

        saveBtn?.addEventListener('click', () => {
            this.saveSettingsFromModal();
            this.hideModal();
        });

        modal?.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.hideModal();
            }
        });

        // Load settings into modal
        this.loadSettingsIntoModal();
    }

    setupDraggable() {
        const modal = document.getElementById('settings-modal');
        const window = document.getElementById('settings-window');
        const header = document.getElementById('settings-header');

        let isDragging = false;
        let dragStartX = 0;
        let dragStartY = 0;
        let modalStartX = 0;
        let modalStartY = 0;

        header?.addEventListener('mousedown', (e) => {
            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            const rect = window.getBoundingClientRect();
            modalStartX = rect.left;
            modalStartY = rect.top;
            header.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const deltaX = e.clientX - dragStartX;
            const deltaY = e.clientY - dragStartY;

            const newLeft = modalStartX + deltaX;
            const newTop = modalStartY + deltaY;

            // Keep modal within viewport bounds
            const maxLeft = window.innerWidth - window.offsetWidth;
            const maxTop = window.innerHeight - window.offsetHeight;

            window.style.left = Math.max(0, Math.min(newLeft, maxLeft)) + 'px';
            window.style.top = Math.max(0, Math.min(newTop, maxTop)) + 'px';
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
            header.style.cursor = 'grab';
        });
    }

    setupEventListeners() {
        // Update watched threads visibility
        document.getElementById('thread-watcher')?.addEventListener('change', (e) => {
            const watchedSection = document.getElementById('watched-threads');
            watchedSection.style.display = e.target.checked ? 'block' : 'none';
        });

        // Update hidden threads visibility
        document.getElementById('thread-hiding')?.addEventListener('change', (e) => {
            const hiddenSection = document.getElementById('hidden-threads');
            hiddenSection.style.display = e.target.checked ? 'block' : 'none';
        });
    }

    setupWatchedThreadsWidget() {
        const widget = document.getElementById('watched-threads-widget');
        const header = document.getElementById('watched-threads-header');
        const minimizeBtn = document.getElementById('watched-threads-minimize');
        const closeBtn = document.getElementById('watched-threads-close');
        const content = document.getElementById('watched-threads-content');

        if (!widget || !header || !minimizeBtn || !closeBtn || !content) return;

        // Set initial position
        this.updateWidgetPosition();

        // Make widget draggable
        this.setupWidgetDraggable();

        // Minimize button
        minimizeBtn.addEventListener('click', () => {
            this.settings.watchedThreadsWidget.minimized = !this.settings.watchedThreadsWidget.minimized;
            this.updateWidgetMinimizedState();
            this.saveSettings();
        });

        // Close button
        closeBtn.addEventListener('click', () => {
            this.settings.watchedThreadsWidget.visible = false;
            widget.classList.add('hidden');
            this.saveSettings();
        });

        // Double-click header to minimize/restore
        header.addEventListener('dblclick', () => {
            this.settings.watchedThreadsWidget.minimized = !this.settings.watchedThreadsWidget.minimized;
            this.updateWidgetMinimizedState();
            this.saveSettings();
        });
    }

    setupWidgetDraggable() {
        const widget = document.getElementById('watched-threads-widget');
        const header = document.getElementById('watched-threads-header');

        let isDragging = false;
        let dragStartX = 0;
        let dragStartY = 0;
        let widgetStartX = 0;
        let widgetStartY = 0;

        header.addEventListener('mousedown', (e) => {
            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            const rect = widget.getBoundingClientRect();
            widgetStartX = rect.left;
            widgetStartY = rect.top;
            header.style.cursor = 'grabbing';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const deltaX = e.clientX - dragStartX;
            const deltaY = e.clientY - dragStartY;

            const newLeft = widgetStartX + deltaX;
            const newTop = widgetStartY + deltaY;

            // Keep widget within viewport bounds
            const maxLeft = window.innerWidth - widget.offsetWidth;
            const maxTop = window.innerHeight - widget.offsetHeight;

            const clampedLeft = Math.max(0, Math.min(newLeft, maxLeft));
            const clampedTop = Math.max(0, Math.min(newTop, maxTop));

            widget.style.left = clampedLeft + 'px';
            widget.style.top = clampedTop + 'px';
            widget.style.right = 'auto';

            // Update stored position
            this.settings.watchedThreadsWidget.position = {
                top: clampedTop,
                right: window.innerWidth - clampedLeft - widget.offsetWidth
            };
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                header.style.cursor = 'grab';
                this.saveSettings();
            }
        });
    }

    updateWidgetPosition() {
        const widget = document.getElementById('watched-threads-widget');
        if (!widget) return;

        const pos = this.settings.watchedThreadsWidget.position;
        widget.style.top = pos.top + 'px';
        widget.style.right = pos.right + 'px';
        widget.style.left = 'auto';
    }

    updateWidgetMinimizedState() {
        const content = document.getElementById('watched-threads-content');
        const minimizeBtn = document.getElementById('watched-threads-minimize');

        if (!content || !minimizeBtn) return;

        if (this.settings.watchedThreadsWidget.minimized) {
            content.classList.add('hidden');
            minimizeBtn.textContent = '+';
        } else {
            content.classList.remove('hidden');
            minimizeBtn.textContent = '−';
        }
    }

    updateWatchedThreadsWidget() {
        const widget = document.getElementById('watched-threads-widget');
        const tbody = document.getElementById('watched-threads-body');

        if (!widget || !tbody) return;

        // Clear existing rows
        tbody.innerHTML = '';

        if (this.settings.watchedThreads.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center p-4 text-[var(--subordinate-header)] text-sm">No watched threads</td></tr>';
            widget.classList.add('hidden');
            return;
        }

        // Show widget if we have watched threads and watcher is enabled
        if (this.settings.threadWatcher && this.settings.watchedThreadsWidget.visible) {
            widget.classList.remove('hidden');
        } else {
            widget.classList.add('hidden');
            return;
        }

        // Add rows for each watched thread
        this.settings.watchedThreads.forEach(thread => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-[var(--reply-bg)]';

            // Check if there are new posts (simplified - would need actual API)
            const hasNewPosts = Math.random() > 0.7; // Simulate for demo
            const newIndicator = hasNewPosts ? '<span class="text-red-400 font-bold">●</span>' : '';

            row.innerHTML = `
                <td class="p-2 border-b border-[var(--post-border)]">
                    <a href="${thread.url}" class="text-[var(--link-color)] hover:text-[var(--link-hover)] text-xs block truncate" title="${thread.title}">
                        ${thread.title}
                    </a>
                </td>
                <td class="p-2 text-center border-b border-[var(--post-border)]">
                    ${newIndicator}
                </td>
                <td class="p-2 text-center border-b border-[var(--post-border)]">
                    <button class="text-red-400 hover:text-red-600 text-sm" onclick="userSettings.unwatchThread('${thread.id}')" title="Unwatch">×</button>
                </td>
            `;

            tbody.appendChild(row);
        });

        this.updateWidgetMinimizedState();
    }

    showModal() {
        const modal = document.getElementById('settings-modal');
        const window = document.getElementById('settings-window');

        modal.classList.remove('hidden');
        modal.classList.add('flex', 'items-center', 'justify-center');

        // Center the modal initially
        window.style.left = '50%';
        window.style.top = '50%';
        window.style.transform = 'translate(-50%, -50%)';
    }

    hideModal() {
        const modal = document.getElementById('settings-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex', 'items-center', 'justify-center');
    }

    loadSettingsIntoModal() {
        document.getElementById('thread-updater').checked = this.settings.threadUpdater;
        document.getElementById('thread-watcher').checked = this.settings.threadWatcher;
        document.getElementById('thread-hiding').checked = this.settings.threadHiding;
        document.getElementById('clickable-links').checked = this.settings.clickableLinks;
        document.getElementById('show-announcements').checked = this.settings.showAnnouncements;

        // Show/hide sections based on checkbox state
        document.getElementById('watched-threads').style.display = this.settings.threadWatcher ? 'block' : 'none';
        document.getElementById('hidden-threads').style.display = this.settings.threadHiding ? 'block' : 'none';

        this.updateWatchedThreadsList();
        this.updateHiddenThreadsList();
    }

    saveSettingsFromModal() {
        this.settings.threadUpdater = document.getElementById('thread-updater').checked;
        this.settings.threadWatcher = document.getElementById('thread-watcher').checked;
        this.settings.threadHiding = document.getElementById('thread-hiding').checked;
        this.settings.clickableLinks = document.getElementById('clickable-links').checked;
        this.settings.showAnnouncements = document.getElementById('show-announcements').checked;

        this.saveSettings();
        this.applyCurrentSettings();
    }

    updateWatchedThreadsList() {
        const list = document.getElementById('watched-threads-list');
        list.innerHTML = '';

        if (this.settings.watchedThreads.length === 0) {
            list.innerHTML = '<div class="text-[var(--subordinate-header)]">No watched threads</div>';
            return;
        }

        this.settings.watchedThreads.forEach(thread => {
            const div = document.createElement('div');
            div.className = 'flex justify-between items-center';
            div.innerHTML = `
                <a href="${thread.url}" class="text-[var(--link-color)] hover:text-[var(--link-hover)]">${thread.title}</a>
                <button class="text-red-400 hover:text-red-600 ml-2" onclick="userSettings.unwatchThread('${thread.id}')">×</button>
            `;
            list.appendChild(div);
        });
    }

    updateHiddenThreadsList() {
        const list = document.getElementById('hidden-threads-list');
        list.innerHTML = '';

        if (this.settings.hiddenThreads.length === 0) {
            list.innerHTML = '<div class="text-[var(--subordinate-header)]">No hidden threads</div>';
            return;
        }

        this.settings.hiddenThreads.forEach(threadId => {
            const div = document.createElement('div');
            div.className = 'flex justify-between items-center';
            div.innerHTML = `
                <span class="text-[var(--font-color)]">Thread #${threadId}</span>
                <button class="text-red-400 hover:text-red-600 ml-2" onclick="userSettings.unhideThread('${threadId}')">×</button>
            `;
            list.appendChild(div);
        });
    }

    applyCurrentSettings() {
        // Apply thread updater
        if (this.settings.threadUpdater) {
            this.enableThreadUpdater();
        } else {
            this.disableThreadUpdater();
        }

        // Apply thread watcher
        if (this.settings.threadWatcher) {
            this.enableThreadWatcher();
        } else {
            this.disableThreadWatcher();
        }

        // Apply thread hiding
        if (this.settings.threadHiding) {
            this.enableThreadHiding();
        } else {
            this.disableThreadHiding();
        }

        // Apply clickable links
        if (this.settings.clickableLinks) {
            this.enableClickableLinks();
        } else {
            this.disableClickableLinks();
        }

        // Apply announcements visibility
        this.updateAnnouncementsVisibility();
    }

    // Thread Updater functionality
    enableThreadUpdater() {
        if (this.threadUpdaterInterval) return;

        // Only enable on thread pages
        if (!window.location.pathname.includes('/thread/')) return;

        this.threadUpdaterInterval = setInterval(() => {
            this.checkForNewPosts();
        }, 30000); // Check every 30 seconds
    }

    disableThreadUpdater() {
        if (this.threadUpdaterInterval) {
            clearInterval(this.threadUpdaterInterval);
            this.threadUpdaterInterval = null;
        }
    }

    async checkForNewPosts() {
        try {
            const threadMatch = window.location.pathname.match(/\/(\w+)\/thread\/(\d+)/);
            if (!threadMatch) return;

            const board = threadMatch[1];
            const threadId = threadMatch[2];

            // Get the latest post ID on the page
            const posts = document.querySelectorAll('.post');
            if (posts.length === 0) return;

            const lastPostId = Array.from(posts).pop().id.replace('p', '');

            // Fetch new posts
            const response = await fetch(`/api/thread/${threadId}/posts?after=${lastPostId}`);
            if (!response.ok) return;

            const newPosts = await response.json();

            if (newPosts.length > 0) {
                this.appendNewPosts(newPosts, board);
            }
        } catch (error) {
            console.error('Thread updater error:', error);
        }
    }

    appendNewPosts(posts, board) {
        const repliesContainer = document.querySelector('.replies-container');
        if (!repliesContainer) return;

        posts.forEach(post => {
            const postElement = document.createElement('div');
            // This would need to be implemented to render posts properly
            // For now, just show a simple notification
            postElement.innerHTML = `<div class="text-green-400 text-sm mb-2">New post: ${post.comment.substring(0, 50)}...</div>`;
            repliesContainer.appendChild(postElement);
        });
    }

    // Thread Watcher functionality
    enableThreadWatcher() {
        // Add watch buttons to threads
        document.querySelectorAll('.thread').forEach(thread => {
            if (thread.querySelector('.watch-btn')) return; // Already has button

            const threadId = thread.querySelector('.post-number a')?.href?.match(/thread\/(\d+)/)?.[1];
            if (!threadId) return;

            const isWatched = this.settings.watchedThreads.some(t => t.id === threadId);
            const watchBtn = document.createElement('a');
            watchBtn.className = `watch-btn text-xs ml-2 ${isWatched ? 'text-green-600' : 'text-blue-600'}`;
            watchBtn.textContent = isWatched ? 'Watched' : 'Watch';
            watchBtn.onclick = () => this.toggleWatchThread(threadId);

            const postNumber = thread.querySelector('.post-number');
            if (postNumber) {
                postNumber.appendChild(watchBtn);
            }
        });

        this.startWatcherPolling();
        this.updateWatchedThreadsWidget();
    }

    disableThreadWatcher() {
        document.querySelectorAll('.watch-btn').forEach(btn => btn.remove());
        this.stopWatcherPolling();
        // Hide widget
        const widget = document.getElementById('watched-threads-widget');
        if (widget) {
            widget.classList.add('hidden');
        }
    }

    toggleWatchThread(threadId) {
        const threadTitle = document.querySelector(`#p${threadId} .post-subject`)?.textContent || `Thread #${threadId}`;
        const threadUrl = window.location.pathname;
        const existingIndex = this.settings.watchedThreads.findIndex(t => t.id === threadId);

        if (existingIndex >= 0) {
            // Unwatch
            this.settings.watchedThreads.splice(existingIndex, 1);
        } else {
            // Watch
            this.settings.watchedThreads.push({
                id: threadId,
                title: threadTitle,
                url: threadUrl,
                lastPostCount: document.querySelectorAll('.post').length
            });
        }

        this.saveSettings();
        this.updateWatchedThreadsList();
        this.updateWatchedThreadsWidget();
        this.enableThreadWatcher(); // Refresh buttons
    }

    unwatchThread(threadId) {
        this.settings.watchedThreads = this.settings.watchedThreads.filter(t => t.id !== threadId);
        this.saveSettings();
        this.updateWatchedThreadsList();
        this.updateWatchedThreadsWidget();
        this.enableThreadWatcher();
    }

    startWatcherPolling() {
        if (this.watcherInterval) return;

        this.watcherInterval = setInterval(() => {
            this.checkWatchedThreads();
        }, 60000); // Check every minute
    }

    stopWatcherPolling() {
        if (this.watcherInterval) {
            clearInterval(this.watcherInterval);
            this.watcherInterval = null;
        }
    }

    async checkWatchedThreads() {
        for (const thread of this.settings.watchedThreads) {
            try {
                // Simple notification for new posts (would need backend API)
                if (Math.random() > 0.8) { // Simulate random new posts
                    this.showNotification(`New posts in watched thread: ${thread.title}`);
                    // Update widget to show new posts indicator
                    this.updateWatchedThreadsWidget();
                }
            } catch (error) {
                console.error('Watcher check error:', error);
            }
        }
    }

    showNotification(message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-600 text-white px-4 py-2 rounded shadow-lg z-50';
        notification.textContent = message;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Thread Hiding functionality
    enableThreadHiding() {
        // Add hide buttons to threads
        document.querySelectorAll('.thread').forEach(thread => {
            if (thread.querySelector('.hide-btn')) return; // Already has button

            const threadId = thread.querySelector('.post-number a')?.href?.match(/thread\/(\d+)/)?.[1];
            if (!threadId) return;

            const isHidden = this.settings.hiddenThreads.includes(threadId);
            const hideBtn = document.createElement('button');
            hideBtn.className = `hide-btn text-xs px-2 py-1 rounded ml-2 ${isHidden ? 'bg-red-600' : 'bg-gray-600'} text-white`;
            hideBtn.textContent = isHidden ? 'Hidden' : 'Hide';
            hideBtn.onclick = () => this.toggleHideThread(threadId, thread);

            const postNumber = thread.querySelector('.post-number');
            if (postNumber) {
                postNumber.appendChild(hideBtn);
            }

            if (isHidden) {
                thread.style.display = 'none';
            }
        });
    }

    disableThreadHiding() {
        document.querySelectorAll('.hide-btn').forEach(btn => btn.remove());
        document.querySelectorAll('.thread[style*="display: none"]').forEach(thread => {
            thread.style.display = '';
        });
    }

    toggleHideThread(threadId, threadElement) {
        const existingIndex = this.settings.hiddenThreads.indexOf(threadId);

        if (existingIndex >= 0) {
            // Unhide
            this.settings.hiddenThreads.splice(existingIndex, 1);
            threadElement.style.display = '';
        } else {
            // Hide
            this.settings.hiddenThreads.push(threadId);
            threadElement.style.display = 'none';
        }

        this.saveSettings();
        this.updateHiddenThreadsList();
        this.enableThreadHiding(); // Refresh buttons
    }

    unhideThread(threadId) {
        this.settings.hiddenThreads = this.settings.hiddenThreads.filter(id => id !== threadId);
        this.saveSettings();
        this.updateHiddenThreadsList();

        // Show the thread if it exists on the page
        const thread = document.querySelector(`.thread .post-number a[href*="/thread/${threadId}"]`)?.closest('.thread');
        if (thread) {
            thread.style.display = '';
        }

        this.enableThreadHiding();
    }

    // Clickable Links functionality
    enableClickableLinks() {
        document.querySelectorAll('.post-body').forEach(body => {
            if (body.dataset.linksEnabled) return; // Already processed

            body.dataset.linksEnabled = 'true';
            body.innerHTML = this.makeLinksClickable(body.innerHTML);
        });
    }

    disableClickableLinks() {
        document.querySelectorAll('.post-body').forEach(body => {
            delete body.dataset.linksEnabled;
            // Re-render the post content (this would need to be implemented properly)
            // For now, just remove the data attribute
        });
    }

    makeLinksClickable(html) {
        // Simple regex to find URLs and make them clickable
        const urlRegex = /(https?:\/\/[^\s<>"']+)/g;
        return html.replace(urlRegex, '<a href="$1" target="_blank" class="text-[var(--link-color)] hover:text-[var(--link-hover)]">$1</a>');
    }

    updateAnnouncementsVisibility() {
        const container = document.getElementById('announcements-container');
        if (!container) return;

        if (this.settings.showAnnouncements) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    setupAnnouncementsToggle() {
        const toggleBtn = document.getElementById('toggle-announcements');
        if (!toggleBtn) return;

        toggleBtn.addEventListener('click', () => {
            const container = document.getElementById('announcements-container');
            if (!container) return;

            const isMinimized = container.classList.contains('announcements-hidden');

            if (isMinimized) {
                container.classList.remove('announcements-hidden');
                toggleBtn.textContent = '−';
            } else {
                container.classList.add('announcements-hidden');
                toggleBtn.textContent = '+';
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.userSettings = new UserSettings();
});
