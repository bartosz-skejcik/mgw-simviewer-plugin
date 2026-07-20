<?php

/**
 * -------------------------------------------------------------------------
 * Simviewer plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by the Simviewer plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/simviewer
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Simviewer\Config;
use GlpiPlugin\Simviewer\Profile;
use GlpiPlugin\Simviewer\Simcard;

define('PLUGIN_SIMVIEWER_VERSION', '1.0.2');

// Minimal GLPI version, inclusive
define('PLUGIN_SIMVIEWER_MIN_GLPI_VERSION', '11.0.0');

// Maximum GLPI version, exclusive
define('PLUGIN_SIMVIEWER_MAX_GLPI_VERSION', '11.0.99');

/**
 * Default top-nav-bar anchor for the SIM Viewer link (see PRD FR-1a).
 * Auto-generated GLPI menu id; configurable per deployment via the plugin config.
 */
define('PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR', '#menu_1595890973');

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_simviewer(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Note: the `csrf_compliant` hook is deprecated in GLPI 11 (plugins are
    // CSRF-compliant by default), so it is intentionally not registered.

    // Expose the read right on the Profile form (admins grant it per profile).
    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

    // Admin configuration tab on the core Config item (central interface).
    Plugin::registerClass(Config::class, ['addtabon' => 'Config']);

    // Admin config page (central interface only).
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['simviewer'] = 'front/config.php';
    }

    // ---------------------------------------------------------------------
    // Self-service (helpdesk) menu entry — FR-1 / FR-1a.
    // Only surfaced to users that actually hold the plugin READ right and are
    // browsing the simplified (helpdesk) interface. Nothing is emitted at all
    // for users without the right: no menu entry, no JS, no data.
    // ---------------------------------------------------------------------
    $is_helpdesk = (($_SESSION['glpiactiveprofile']['interface'] ?? '') === 'helpdesk');

    if ($is_helpdesk && Simcard::canView()) {
        // Primary, native mechanism: add a link to the simplified-interface menu.
        $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY]['simviewer']      = '/front/simcard.php';
        $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY_ICON]['simviewer'] = 'ti ti-device-sim';

        // Fallback / exact-placement mechanism (FR-1a): a small JS asset that
        // guarantees the link lands in the top nav bar at the configured anchor
        // (#menu_1595890973 by default). It relocates the native entry if GLPI
        // rendered one elsewhere, or creates the link if native positioning did
        // nothing. Loaded only for entitled helpdesk users.
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['simviewer'] = ['js/nav-inject.js'];

        // Hand the configured anchor selector and translated label to the JS
        // asset. Static plugin files cannot receive PHP config, so they are
        // published as <meta> tags (values are Twig-escaped by the core head
        // template) that nav-inject.js reads back.
        $cfg = Config::getConfig();
        $PLUGIN_HOOKS[Hooks::ADD_HEADER_TAG]['simviewer'] = [
            [
                'tag'        => 'meta',
                'properties' => [
                    'name'    => 'simviewer:nav-selector',
                    'content' => ($cfg['nav_selector'] ?? '') !== ''
                        ? $cfg['nav_selector']
                        : PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR,
                ],
            ],
            [
                'tag'        => 'meta',
                'properties' => [
                    'name'    => 'simviewer:label',
                    'content' => Simcard::getMenuName(),
                ],
            ],
        ];
    }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array{
 *      name: string,
 *      version: string,
 *      author: string,
 *      license: string,
 *      homepage: string,
 *      requirements: array{
 *          glpi: array{
 *              min: string,
 *              max: string,
 *          }
 *      }
 * }
 */
function plugin_version_simviewer(): array
{
    return [
        'name'           => 'SIM Viewer',
        'version'        => PLUGIN_SIMVIEWER_VERSION,
        'author'         => 'Bartek',
        'license'        => 'MIT',
        'homepage'       => 'https://github.com/pluginsGLPI/simviewer',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_SIMVIEWER_MIN_GLPI_VERSION,
                'max' => PLUGIN_SIMVIEWER_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL
 */
function plugin_simviewer_check_prerequisites(): bool
{
    return true;
}

/**
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_simviewer_check_config(bool $verbose = false): bool
{
    return true;
}
