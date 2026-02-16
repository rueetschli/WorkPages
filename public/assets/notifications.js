/**
 * WorkPages AP15 - Notification bell dropdown and polling.
 * Vanilla JS, no dependencies.
 */
(function() {
    'use strict';

    var POLL_INTERVAL = 60000; // 60 seconds
    var baseUrl = '';
    var bellBtn, dropdown, badgeEl, listEl;
    var isOpen = false;
    var pollTimer = null;

    function init() {
        var metaBase = document.querySelector('meta[name="base-url"]');
        if (metaBase) {
            baseUrl = metaBase.getAttribute('content') || '';
        }

        bellBtn  = document.getElementById('notif-bell-btn');
        dropdown = document.getElementById('notif-dropdown');
        badgeEl  = document.getElementById('notif-badge');
        listEl   = document.getElementById('notif-dropdown-list');

        if (!bellBtn || !dropdown) {
            return;
        }

        bellBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        document.addEventListener('click', function(e) {
            if (isOpen && !dropdown.contains(e.target) && e.target !== bellBtn) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isOpen) {
                closeDropdown();
            }
        });

        // Start polling
        pollUnreadCount();
        pollTimer = setInterval(pollUnreadCount, POLL_INTERVAL);
    }

    function openDropdown() {
        isOpen = true;
        dropdown.classList.add('notif-dropdown-open');
        fetchLatest();
    }

    function closeDropdown() {
        isOpen = false;
        dropdown.classList.remove('notif-dropdown-open');
    }

    function pollUnreadCount() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', baseUrl + '/?r=api_notifications_unread', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    updateBadge(data.count || 0);
                } catch(e) {
                    // ignore
                }
            }
        };
        xhr.send();
    }

    function fetchLatest() {
        if (!listEl) return;

        var xhr = new XMLHttpRequest();
        xhr.open('GET', baseUrl + '/?r=api_notifications_latest', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    renderDropdownItems(data.items || [], data.count || 0);
                    updateBadge(data.count || 0);
                } catch(e) {
                    // ignore
                }
            }
        };
        xhr.send();
    }

    function updateBadge(count) {
        if (!badgeEl) return;

        if (count > 0) {
            badgeEl.textContent = count > 99 ? '99+' : String(count);
            badgeEl.style.display = '';
        } else {
            badgeEl.style.display = 'none';
        }
    }

    function renderDropdownItems(items, totalCount) {
        if (!listEl) return;

        if (items.length === 0) {
            listEl.innerHTML = '<div class="notif-dropdown-empty">Keine neuen Benachrichtigungen</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var unreadClass = item.is_read === 0 ? ' notif-dropdown-item-unread' : '';
            html += '<a href="' + escHtml(item.url) + '" class="notif-dropdown-item' + unreadClass + '">';
            html += '<span class="notif-dropdown-title">' + escHtml(item.title) + '</span>';
            if (item.body) {
                html += '<span class="notif-dropdown-body">' + escHtml(item.body) + '</span>';
            }
            html += '<span class="notif-dropdown-meta">' + escHtml(item.actor_name) + ' &middot; ' + formatTime(item.created_at) + '</span>';
            html += '</a>';
        }

        listEl.innerHTML = html;
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        var day = String(d.getDate()).padStart(2, '0');
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var hours = String(d.getHours()).padStart(2, '0');
        var minutes = String(d.getMinutes()).padStart(2, '0');
        return day + '.' + month + '. ' + hours + ':' + minutes;
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
