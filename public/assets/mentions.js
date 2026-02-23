/**
 * AP14: Smart Text Commands - Autocomplete for @mentions and #tags.
 *
 * Attaches to all textareas with data-mentions="true".
 * Shows a dropdown for @ (users) and # (tags) with keyboard + mouse support.
 *
 * Token format inserted:
 *   @mention: @[Display Name](user:ID)
 *   #tag:     #tag-name
 */
(function() {
    'use strict';

    var BASE_URL = document.querySelector('meta[name="base-url"]');
    var baseUrl = BASE_URL ? BASE_URL.getAttribute('content') : '';

    /**
     * Debounce helper.
     */
    function debounce(fn, delay) {
        var timer = null;
        return function() {
            var args = arguments;
            var ctx = this;
            if (timer) clearTimeout(timer);
            timer = setTimeout(function() { fn.apply(ctx, args); }, delay);
        };
    }

    /**
     * Create the dropdown element.
     */
    function createDropdown() {
        var el = document.createElement('div');
        el.className = 'mentions-dropdown';
        el.style.display = 'none';
        document.body.appendChild(el);
        return el;
    }

    /**
     * Get caret coordinates relative to viewport.
     * Uses a mirror div technique with proper coordinate mapping.
     */
    function getCaretCoords(textarea) {
        var div = document.createElement('div');
        var style = window.getComputedStyle(textarea);
        var props = [
            'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing',
            'wordSpacing', 'textIndent', 'paddingTop', 'paddingRight', 'paddingBottom',
            'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth',
            'borderLeftWidth', 'boxSizing', 'whiteSpace', 'wordWrap', 'overflowWrap'
        ];

        var textareaRect = textarea.getBoundingClientRect();

        // Position mirror div at same location as textarea
        div.style.position = 'fixed';
        div.style.top = textareaRect.top + 'px';
        div.style.left = textareaRect.left + 'px';
        div.style.visibility = 'hidden';
        div.style.width = style.width;
        div.style.height = 'auto';
        div.style.overflow = 'hidden';

        for (var i = 0; i < props.length; i++) {
            div.style[props[i]] = style[props[i]];
        }

        // Pre-wrap to match textarea wrapping
        div.style.whiteSpace = 'pre-wrap';
        div.style.wordWrap = 'break-word';

        var text = textarea.value.substring(0, textarea.selectionEnd);
        var textNode = document.createTextNode(text);
        div.appendChild(textNode);

        var span = document.createElement('span');
        span.textContent = '\u200b'; // Zero-width space as caret marker
        div.appendChild(span);

        document.body.appendChild(div);

        var spanRect = span.getBoundingClientRect();

        // Account for textarea scroll offset
        var scrollTop = textarea.scrollTop;
        var top = spanRect.top - scrollTop;
        var left = spanRect.left;

        document.body.removeChild(div);

        return { top: top, left: left };
    }

    /**
     * Position dropdown below the caret or at bottom of textarea.
     */
    function positionDropdown(dropdown, textarea) {
        var coords = getCaretCoords(textarea);
        var lineHeight = parseInt(window.getComputedStyle(textarea).lineHeight, 10) || 20;

        dropdown.style.position = 'fixed';
        dropdown.style.top = (coords.top + lineHeight + 4) + 'px';
        dropdown.style.left = coords.left + 'px';

        // Ensure dropdown stays visible
        requestAnimationFrame(function() {
            var rect = dropdown.getBoundingClientRect();
            var viewH = window.innerHeight;
            var viewW = window.innerWidth;

            if (rect.bottom > viewH) {
                dropdown.style.top = (coords.top - rect.height - 4) + 'px';
            }
            if (rect.right > viewW) {
                dropdown.style.left = Math.max(4, viewW - rect.width - 4) + 'px';
            }
        });
    }

    /**
     * Find the trigger token (@, #) at the current caret position.
     * Returns { type: '@'|'#', query: string, start: int, end: int } or null.
     */
    function findTrigger(source) {
        var pos = source.getCursorPos();
        var text = source.getValue();

        // Walk backwards from caret to find trigger character
        var i = pos - 1;
        while (i >= 0) {
            var ch = text[i];

            // Found trigger
            if (ch === '@' || ch === '#') {
                // Must be at start of text or preceded by whitespace/newline
                if (i === 0 || /[\s\n]/.test(text[i - 1])) {
                    var query = text.substring(i + 1, pos);
                    // Query must not contain whitespace
                    if (!/\s/.test(query) && query.length >= 1 && query.length <= 50) {
                        return {
                            type: ch,
                            query: query,
                            start: i,
                            end: pos
                        };
                    }
                }
                return null;
            }

            // Stop at whitespace or newline
            if (/[\s\n]/.test(ch)) {
                return null;
            }

            i--;
        }

        return null;
    }

    /**
     * Fetch autocomplete results from API.
     */
    function fetchResults(type, query, callback) {
        var route = type === '@' ? 'api_users' : 'api_tags';
        var url = baseUrl + '/?r=' + route + '&q=' + encodeURIComponent(query);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        callback(data);
                    } catch (e) {
                        callback([]);
                    }
                } else {
                    callback([]);
                }
            }
        };
        xhr.send();
    }

    /**
     * Render dropdown items.
     */
    function renderItems(dropdown, items, type, selectedIndex) {
        dropdown.innerHTML = '';

        if (items.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var div = document.createElement('div');
            div.className = 'mentions-item' + (i === selectedIndex ? ' mentions-item-active' : '');
            div.setAttribute('data-index', i);

            if (type === '@') {
                div.innerHTML = '<span class="mentions-item-name">' + escapeHtml(item.label) + '</span>'
                    + '<span class="mentions-item-email">' + escapeHtml(item.email || '') + '</span>';
            } else {
                div.innerHTML = '<span class="mentions-item-tag">#' + escapeHtml(item.label) + '</span>';
            }

            dropdown.appendChild(div);
        }

        dropdown.style.display = 'block';
    }

    /**
     * Insert the selected item into textarea.
     */
    function insertToken(textarea, trigger, item) {
        var before = textarea.value.substring(0, trigger.start);
        var after = textarea.value.substring(trigger.end);
        var token;

        if (trigger.type === '@') {
            token = '@[' + item.label + '](user:' + item.id + ')';
        } else {
            token = '#' + item.label;
        }

        // Add a space after the token
        textarea.value = before + token + ' ' + after;

        // Position caret after the inserted token + space
        var newPos = before.length + token.length + 1;
        textarea.selectionStart = newPos;
        textarea.selectionEnd = newPos;
        textarea.focus();

        // Trigger input event for any listeners
        var evt = new Event('input', { bubbles: true });
        textarea.dispatchEvent(evt);
    }

    /**
     * Escape HTML entities.
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Initialise autocomplete on a textarea.
     */
    function createAutocompleteAdapter(adapter) {
        var dropdown = createDropdown();
        var items = [];
        var selectedIndex = 0;
        var currentTrigger = null;
        var isOpen = false;

        var debouncedFetch = debounce(function(type, query) {
            fetchResults(type, query, function(results) {
                items = results;
                selectedIndex = 0;
                if (items.length > 0) {
                    isOpen = true;
                    renderItems(dropdown, items, type, selectedIndex);
                    adapter.positionDropdown(dropdown);
                } else {
                    closeDropdown();
                }
            });
        }, 150);

        function closeDropdown() {
            isOpen = false;
            items = [];
            selectedIndex = 0;
            currentTrigger = null;
            dropdown.style.display = 'none';
        }

        function selectItem(index) {
            if (index >= 0 && index < items.length && currentTrigger) {
                adapter.insertToken(currentTrigger, items[index]);
                closeDropdown();
            }
        }

        // Input handler
        adapter.onInput(function() {
            var trigger = findTrigger(adapter);
            if (trigger) {
                currentTrigger = trigger;
                debouncedFetch(trigger.type, trigger.query);
            } else {
                closeDropdown();
            }
        });

        // Keyboard handler
        adapter.onKeydown(function(e) {
            if (!isOpen) return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    renderItems(dropdown, items, currentTrigger.type, selectedIndex);
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                    renderItems(dropdown, items, currentTrigger.type, selectedIndex);
                    break;

                case 'Enter':
                    e.preventDefault();
                    selectItem(selectedIndex);
                    break;

                case 'Escape':
                    e.preventDefault();
                    closeDropdown();
                    break;

                case 'Tab':
                    e.preventDefault();
                    selectItem(selectedIndex);
                    break;
            }
        });

        // Click handler on dropdown items
        dropdown.addEventListener('mousedown', function(e) {
            // Prevent textarea blur
            e.preventDefault();

            var target = e.target.closest('.mentions-item');
            if (target) {
                var index = parseInt(target.getAttribute('data-index'), 10);
                selectItem(index);
            }
        });

        // Hover handler for visual feedback
        dropdown.addEventListener('mouseover', function(e) {
            var target = e.target.closest('.mentions-item');
            if (target) {
                var index = parseInt(target.getAttribute('data-index'), 10);
                selectedIndex = index;
                renderItems(dropdown, items, currentTrigger ? currentTrigger.type : '@', selectedIndex);
            }
        });

        // Close on blur (with small delay for click events)
        adapter.onBlur(function() {
            setTimeout(function() {
                closeDropdown();
            }, 200);
        });

        // Close on scroll
        adapter.onScroll(function() {
            if (isOpen) {
                adapter.positionDropdown(dropdown);
            }
        });

        // Close when clicking outside
        document.addEventListener('mousedown', function(e) {
            if (!isOpen) return;
            if (dropdown.contains(e.target) || adapter.containsTarget(e.target)) {
                return;
            }
            closeDropdown();
        });
    }

    function initTextarea(textarea) {
        if (textarea.dataset.mentionsInit === '1') {
            return;
        }
        textarea.dataset.mentionsInit = '1';

        createAutocompleteAdapter({
            getValue: function() { return textarea.value; },
            getCursorPos: function() { return textarea.selectionEnd; },
            insertToken: function(trigger, item) { insertToken(textarea, trigger, item); },
            positionDropdown: function(dropdown) { positionDropdown(dropdown, textarea); },
            onInput: function(handler) { textarea.addEventListener('input', handler); },
            onKeydown: function(handler) { textarea.addEventListener('keydown', handler); },
            onBlur: function(handler) { textarea.addEventListener('blur', handler); },
            onScroll: function(handler) { textarea.addEventListener('scroll', handler); },
            containsTarget: function(target) { return textarea === target || textarea.contains(target); }
        });
    }

    function initCodeMirror(textarea, cm) {
        if (!cm || cm.state.workpagesMentionsInit) {
            return;
        }
        cm.state.workpagesMentionsInit = true;

        createAutocompleteAdapter({
            getValue: function() { return cm.getValue(); },
            getCursorPos: function() { return cm.indexFromPos(cm.getCursor()); },
            insertToken: function(trigger, item) {
                var token = trigger.type === '@'
                    ? '@[' + item.label + '](user:' + item.id + ') '
                    : '#' + item.label + ' ';
                cm.replaceRange(token, cm.posFromIndex(trigger.start), cm.posFromIndex(trigger.end));
                cm.focus();
                cm.save();
            },
            positionDropdown: function(dropdown) {
                var coords = cm.cursorCoords(null, 'page');
                dropdown.style.position = 'fixed';
                dropdown.style.top = (coords.bottom - window.pageYOffset + 4) + 'px';
                dropdown.style.left = (coords.left - window.pageXOffset) + 'px';
            },
            onInput: function(handler) { cm.on('changes', handler); },
            onKeydown: function(handler) { cm.on('keydown', function(_, e) { handler(e); }); },
            onBlur: function(handler) { cm.on('blur', handler); },
            onScroll: function(handler) { cm.on('scroll', handler); },
            containsTarget: function(target) {
                var wrapper = cm.getWrapperElement();
                return wrapper === target || wrapper.contains(target);
            }
        });
    }

    /**
     * Initialise all textareas with data-mentions attribute.
     */
    function init() {
        var textareas = document.querySelectorAll('textarea[data-mentions="true"]');
        for (var i = 0; i < textareas.length; i++) {
            var textarea = textareas[i];
            initTextarea(textarea);

            var container = textarea.closest('.EasyMDEContainer');
            if (container) {
                var cmEl = container.querySelector('.CodeMirror');
                if (cmEl && cmEl.CodeMirror) {
                    initCodeMirror(textarea, cmEl.CodeMirror);
                }
            }
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.WorkPagesMentionsInit = init;
})();
