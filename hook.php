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

use GlpiPlugin\Simviewer\Config;
use GlpiPlugin\Simviewer\Simcard;

/**
 * Plugin install process.
 *
 * The plugin stores no SIM data of its own. It registers a dedicated READ
 * right and seeds its configuration into the core `glpi_configs` table
 * (context `plugin:simviewer`). No custom tables are created.
 */
function plugin_simviewer_install(): bool
{
    /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
    global $GLPI_CACHE;

    $migration = new Migration(PLUGIN_SIMVIEWER_VERSION);

    // Migration::addRight() covers every profile missing the right in one pass:
    // profiles holding config UPDATE (super-admins) get READ, all others get a
    // row at 0. It skips profiles that already have the row, so install stays
    // idempotent. Do NOT add ProfileRight::addProfileRights() here: it INSERTs
    // blindly for every profile and collides with these rows.
    $migration->addRight(Simcard::$rightname, READ, ['config' => UPDATE]);

    // By design the SIM directory is for self-service users, so profiles on
    // the simplified (helpdesk) interface get READ out of the box. OR-merge,
    // idempotent, and bumps profiles' last_rights_update.
    $migration->addRightByInterface(Simcard::$rightname, READ, 'helpdesk');

    // GLPI caches the list of known right names ('all_possible_rights');
    // session right-loading filters against it, so without a reset freshly
    // logged-in users would not receive the new right until a manual
    // cache:clear. (ProfileRight::addProfileRights() used to reset it as a
    // side effect; Migration::addRight() does not.)
    $GLPI_CACHE->set('all_possible_rights', []);

    // Seed default configuration (privacy-first defaults, see PRD §9).
    Config::install();

    $migration->executeMigration();

    return true;
}

/**
 * Plugin uninstall process.
 *
 * Removes the plugin right and its configuration only. All core SIM data
 * (glpi_items_devicesimcards) and any Fields plugin data are left untouched.
 */
function plugin_simviewer_uninstall(): bool
{
    Config::uninstall();

    ProfileRight::deleteProfileRights([Simcard::$rightname]);

    return true;
}
