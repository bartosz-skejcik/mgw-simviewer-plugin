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
    $migration = new Migration(PLUGIN_SIMVIEWER_VERSION);

    // Register the dedicated plugin right on all existing profiles (no access
    // by default — granting is an explicit admin action, see PRD §5).
    ProfileRight::addProfileRights([Simcard::$rightname]);

    // Give profiles that can update the GLPI configuration (super-admins) the
    // READ right by default so the feature is reachable out of the box.
    $migration->addRight(Simcard::$rightname, READ, ['config' => UPDATE]);

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
