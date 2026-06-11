/*!
 * -------------------------------------------------------------------------
 * Simviewer plugin for GLPI — top nav-bar link injection (PRD FR-1a).
 *
 * Guarantees the SIM Viewer link sits in the top navigation bar at the
 * configured anchor (#menu_1595890973 by default). Strategy:
 *   1. If GLPI's native helpdesk menu entry already rendered a link to our
 *      page, relocate it into the anchor (preserves its handlers/markup).
 *   2. Otherwise build the link ourselves and insert it at the anchor.
 * Degrades silently if the anchor is absent (e.g. Service Catalog mode or a
 * different GLPI build where the menu id differs).
 *
 * This file is only loaded for users that hold the plugin READ right and are
 * on the simplified (helpdesk) interface (gated in setup.php).
 * -------------------------------------------------------------------------
 */
(function () {
    'use strict';

    // Configurable per deployment; defaults to the PRD-specified anchor.
    var NAV_SELECTOR = (window.SIMVIEWER && window.SIMVIEWER.nav_selector) || '#menu_1595890973';
    var LINK_ID = 'simviewer-nav-link';
    var PATH = '/plugins/simviewer/front/simcard.php';

    function rootDoc() {
        if (window.CFG_GLPI && typeof window.CFG_GLPI.root_doc === 'string') {
            return window.CFG_GLPI.root_doc;
        }
        return '';
    }

    function label() {
        if (window.SIMVIEWER && window.SIMVIEWER.label) {
            return window.SIMVIEWER.label;
        }
        var lang = (document.documentElement.getAttribute('lang') || 'en').toLowerCase();
        return lang.indexOf('pl') === 0 ? 'Podgląd SIM' : 'SIM Viewer';
    }

    function findExistingLink() {
        // A link to our controller, wherever GLPI's native menu placed it.
        return document.querySelector('a[href*="' + PATH + '"]');
    }

    function buildLink() {
        var a = document.createElement('a');
        a.id = LINK_ID;
        a.className = 'nav-link';
        a.href = rootDoc() + PATH;

        var icon = document.createElement('i');
        icon.className = 'ti ti-device-sim me-1';
        a.appendChild(icon);
        a.appendChild(document.createTextNode(label()));

        return a;
    }

    function init() {
        var anchor = document.querySelector(NAV_SELECTOR);
        if (!anchor) {
            // Anchor not present in this interface/build — nothing to do.
            return;
        }

        var existing = findExistingLink();

        // Already positioned inside the anchor: leave it untouched.
        if (existing && anchor.contains(existing)) {
            return;
        }

        if (existing) {
            // Native menu rendered the entry elsewhere — relocate the closest
            // menu item into the anchor so there is exactly one link, at the
            // required position.
            var item = existing.closest('li') || existing;
            if (!item.id) {
                item.id = LINK_ID;
            }
            anchor.appendChild(item);
            return;
        }

        // Native positioning produced nothing — create the link ourselves.
        if (document.getElementById(LINK_ID)) {
            return; // guard against double-insertion
        }

        var node = buildLink();
        if (anchor.tagName === 'UL') {
            var li = document.createElement('li');
            li.className = 'nav-item';
            li.appendChild(node);
            anchor.appendChild(li);
        } else {
            anchor.appendChild(node);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
