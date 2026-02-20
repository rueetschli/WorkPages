<?php
/**
 * Tag autocomplete input partial (AP22).
 *
 * Variables expected from parent scope:
 *   $tagInputId    - string: HTML id for the input
 *   $tagInputName  - string: HTML name for the hidden input (comma-separated)
 *   $tagInputValue - string: current comma-separated tags
 *   $baseUrl       - string
 */
$_tagId    = $tagInputId ?? 'tags';
$_tagName  = $tagInputName ?? 'tags';
$_tagVal   = $tagInputValue ?? '';
$_tags     = array_filter(array_map('trim', explode(',', $_tagVal)));
?>
<div class="tag-input-wrap" id="<?= Security::esc($_tagId) ?>-wrap">
    <div class="tag-input-chips" id="<?= Security::esc($_tagId) ?>-chips">
        <?php foreach ($_tags as $t): ?>
            <span class="tag-input-chip" data-tag="<?= Security::esc($t) ?>">
                <?= Security::esc($t) ?>
                <button type="button" class="tag-input-chip-remove" aria-label="Entfernen">&times;</button>
            </span>
        <?php endforeach; ?>
        <input type="text" class="tag-input-field" id="<?= Security::esc($_tagId) ?>-field"
               placeholder="Tag eingeben..." autocomplete="off" maxlength="50">
    </div>
    <input type="hidden" name="<?= Security::esc($_tagName) ?>" id="<?= Security::esc($_tagId) ?>-hidden"
           value="<?= Security::esc($_tagVal) ?>">
    <div class="tag-input-dropdown" id="<?= Security::esc($_tagId) ?>-dropdown"></div>
</div>

<script>
(function() {
    'use strict';
    var wrapId    = <?= json_encode($_tagId) ?>;
    var baseUrl   = <?= json_encode($baseUrl) ?>;
    var field     = document.getElementById(wrapId + '-field');
    var hiddenEl  = document.getElementById(wrapId + '-hidden');
    var chipsEl   = document.getElementById(wrapId + '-chips');
    var dropdown  = document.getElementById(wrapId + '-dropdown');
    var debounce  = null;

    function getTags() {
        var val = hiddenEl.value.trim();
        if (val === '') return [];
        return val.split(',').map(function(t){ return t.trim(); }).filter(Boolean);
    }

    function setTags(arr) {
        hiddenEl.value = arr.join(', ');
    }

    function addTag(name) {
        name = name.trim().toLowerCase();
        if (!name) return;
        var tags = getTags();
        for (var i = 0; i < tags.length; i++) {
            if (tags[i].toLowerCase() === name) return;
        }
        tags.push(name);
        setTags(tags);
        renderChips();
        field.value = '';
        hideDropdown();
    }

    function removeTag(name) {
        var tags = getTags().filter(function(t) {
            return t.toLowerCase() !== name.toLowerCase();
        });
        setTags(tags);
        renderChips();
    }

    function renderChips() {
        var chips = chipsEl.querySelectorAll('.tag-input-chip');
        for (var i = chips.length - 1; i >= 0; i--) {
            chips[i].remove();
        }
        var tags = getTags();
        for (var j = 0; j < tags.length; j++) {
            var chip = document.createElement('span');
            chip.className = 'tag-input-chip';
            chip.dataset.tag = tags[j];
            chip.textContent = tags[j];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tag-input-chip-remove';
            btn.setAttribute('aria-label', 'Entfernen');
            btn.innerHTML = '&times;';
            btn.addEventListener('click', (function(tagName) {
                return function() { removeTag(tagName); };
            })(tags[j]));
            chip.appendChild(btn);
            chipsEl.insertBefore(chip, field);
        }
    }

    function showDropdown(items, query) {
        dropdown.innerHTML = '';
        if (items.length === 0 && query.length > 0) {
            var newItem = document.createElement('div');
            newItem.className = 'tag-input-option tag-input-option-new';
            newItem.textContent = '"' + query + '" erstellen';
            newItem.addEventListener('mousedown', function(e) {
                e.preventDefault();
                addTag(query);
            });
            dropdown.appendChild(newItem);
        }
        for (var i = 0; i < items.length; i++) {
            var opt = document.createElement('div');
            opt.className = 'tag-input-option';
            opt.textContent = items[i].label;
            opt.addEventListener('mousedown', (function(label) {
                return function(e) {
                    e.preventDefault();
                    addTag(label);
                };
            })(items[i].label));
            dropdown.appendChild(opt);
        }
        // Show "create new" if query not in list
        if (items.length > 0 && query.length > 0) {
            var exists = false;
            for (var k = 0; k < items.length; k++) {
                if (items[k].label.toLowerCase() === query.toLowerCase()) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                var newOpt = document.createElement('div');
                newOpt.className = 'tag-input-option tag-input-option-new';
                newOpt.textContent = '"' + query + '" erstellen';
                newOpt.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    addTag(query);
                });
                dropdown.appendChild(newOpt);
            }
        }
        dropdown.style.display = dropdown.children.length > 0 ? 'block' : 'none';
    }

    function hideDropdown() {
        dropdown.style.display = 'none';
    }

    function fetchTags(query) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', baseUrl + '/?r=api_tags&q=' + encodeURIComponent(query), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var items = JSON.parse(xhr.responseText);
                    // Filter out already selected tags
                    var current = getTags().map(function(t){ return t.toLowerCase(); });
                    items = items.filter(function(item) {
                        return current.indexOf(item.label.toLowerCase()) === -1;
                    });
                    showDropdown(items, query);
                } catch(e) {}
            }
        };
        xhr.send();
    }

    field.addEventListener('input', function() {
        var q = field.value.trim();
        if (q.length < 1) {
            hideDropdown();
            return;
        }
        clearTimeout(debounce);
        debounce = setTimeout(function() {
            fetchTags(q);
        }, 200);
    });

    field.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            var val = field.value.replace(',', '').trim();
            if (val) addTag(val);
        }
        if (e.key === 'Backspace' && field.value === '') {
            var tags = getTags();
            if (tags.length > 0) {
                removeTag(tags[tags.length - 1]);
            }
        }
    });

    field.addEventListener('blur', function() {
        setTimeout(hideDropdown, 150);
        var val = field.value.replace(',', '').trim();
        if (val) addTag(val);
    });

    // Delegate chip remove clicks
    chipsEl.addEventListener('click', function(e) {
        var btn = e.target.closest('.tag-input-chip-remove');
        if (!btn) return;
        var chip = btn.closest('.tag-input-chip');
        if (chip) removeTag(chip.dataset.tag);
    });

    // Click on wrap focuses input
    chipsEl.addEventListener('click', function(e) {
        if (e.target === chipsEl) field.focus();
    });
})();
</script>
