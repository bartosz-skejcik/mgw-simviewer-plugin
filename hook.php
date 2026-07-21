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

use Glpi\Helpdesk\Tile\ExternalPageTile;
use Glpi\Helpdesk\Tile\TilesManager;
use GlpiPlugin\Simviewer\Config;
use GlpiPlugin\Simviewer\Simcard;

/**
 * Core \Profile objects for every profile on the simplified (helpdesk)
 * interface — the population the native tile is registered for/removed
 * from, mirroring the addRightByInterface(..., 'helpdesk') grant above.
 *
 * @return list<\Profile>
 */
function plugin_simviewer_get_helpdesk_profiles(): array
{
    $profiles = [];

    foreach ((new \Profile())->find(['interface' => 'helpdesk']) as $row) {
        $profile = new \Profile();
        if ($profile->getFromDB($row['id'])) {
            $profiles[] = $profile;
        }
    }

    return $profiles;
}

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

    // Capture the pre-install stored config BEFORE Config::install() seeds
    // defaults, so the one-time enable_export migration below can tell a
    // fresh install (empty) apart from an upgrade (populated) — see EXP-02
    // migration block after Config::install().
    $pre_existing = \Config::getConfigurationValues(Config::CONTEXT);
    $is_upgrade   = !empty($pre_existing);

    // Seed default configuration (privacy-first defaults, see PRD §9).
    Config::install();

    // Migrate away the retired top-nav-anchor option: delete the stale
    // 'nav_selector' key from stored config (idempotent no-op once removed,
    // and a no-op on a fresh install where it was never seeded).
    $stored_config = \Config::getConfigurationValues(Config::CONTEXT);
    if (isset($stored_config['nav_selector'])) {
        (new \Config())->deleteConfigurationValues(Config::CONTEXT, ['nav_selector']);
    }

    // One-time enable_export default flip (D: EXP-02, satisfies SC2 without
    // clobbering a later admin decision — the prohibition). The stored marker
    // 'export_default_migrated' is the source of truth for "already migrated"
    // (not a PLUGIN_SIMVIEWER_VERSION string comparison), and is race-free
    // within the single-threaded console install process. Fresh installs
    // seed enable_export='1' via getDefaults() and set the marker WITHOUT a
    // flip, so a later admin disable survives future reinstalls. An upgrade
    // from 1.1.0 with stored '0' (production) flips to '1' exactly once.
    if (!isset($pre_existing['export_default_migrated'])) {
        if ($is_upgrade && ($pre_existing['enable_export'] ?? null) === '0') {
            (new \Config())->setConfigurationValues(Config::CONTEXT, ['enable_export' => '1']);
        }
        (new \Config())->setConfigurationValues(Config::CONTEXT, ['export_default_migrated' => '1']);
    }

    // Register the native "Podgląd SIM" home-page tile (system Tiles,
    // ExternalPageTile) on the ROOT ENTITY, not on helpdesk profiles. GLPI's
    // TilesManager::getVisibleTilesForSession() loads PROFILE tiles first and
    // falls back to entity tiles ONLY when the profile has none — so linking
    // the tile to a profile REPLACES the entity's whole default tile set
    // (Service Catalog forms/pages vanish from /Helpdesk). Linking to the
    // root entity appends the tile after the existing defaults (rank is
    // getMaxUsedRankForItem()+1) and every helpdesk-interface profile keeps
    // its Service Catalog tiles. Runs on both fresh install and update, so it
    // must stay idempotent: skip when the entity already carries a tile
    // pointing at the SIM catalog.
    //
    // KNOWN LIMITATION: an entity tile renders for every helpdesk-interface
    // user of that entity regardless of the plugin right — GLPI's Tiles
    // system has no per-request "should this tile render" callback for
    // plugins (ExternalPageTile::isAvailable() is unconditional). A user
    // whose profile lacks Simcard::$rightname still sees the tile; clicking
    // it hits Session::checkRight() in front/simcard.php and gets a rights
    // error (no authorization bypass). In practice all helpdesk profiles are
    // granted READ at install (addRightByInterface above). See README.md
    // "Known limitations" for the admin-facing note.
    $tiles_manager = TilesManager::getInstance();
    $tile_url      = Simcard::getListUrl();
    $tile_title    = Simcard::getMenuName();

    // Migration from the profile-linked variant (first 1.2.0 install):
    // remove any simviewer tile still attached to a helpdesk profile, so the
    // profile tile set becomes empty again and GLPI falls back to entity
    // tiles. Matching on URL + title, same ownership heuristic as uninstall.
    foreach (plugin_simviewer_get_helpdesk_profiles() as $profile) {
        foreach ($tiles_manager->getTilesForItem($profile) as $tile) {
            if (
                $tile instanceof ExternalPageTile
                && $tile->getTileUrl() === $tile_url
                && (!method_exists($tile, 'getTitle') || $tile->getTitle() === $tile_title)
            ) {
                $tiles_manager->deleteTile($tile);
            }
        }
    }

    $root_entity = new \Entity();
    if ($root_entity->getFromDB(0)) {
        $has_tile = false;
        foreach ($tiles_manager->getTilesForItem($root_entity) as $tile) {
            if ($tile instanceof ExternalPageTile && $tile->getTileUrl() === $tile_url) {
                $has_tile = true;
                break;
            }
        }

        if (!$has_tile) {
            $tiles_manager->addTile($root_entity, ExternalPageTile::class, [
                'title'       => $tile_title,
                'description' => __('Coworkers\' business phone numbers', 'simviewer'),
                'url'         => $tile_url,
            ]);
        }
    }

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

    // Remove every "Podgląd SIM" ExternalPageTile registered by install()
    // (deleteTile() also removes the Item_Tile profile association).
    //
    // GLPI's Tiles schema carries no first-class "owner"/creator metadata, so
    // there is no definitive way to tell a tile this plugin created apart
    // from one an admin manually added via the core Tiles UI that happens to
    // target the same URL. Matching on URL *and* title (both values install()
    // sets when it creates the tile) narrows — but does not eliminate — the
    // chance of an accidental collision with an unrelated, admin-authored
    // tile; a tile with both the exact same URL and the exact same title is
    // exceedingly unlikely to be anything other than one this plugin made.
    // If `getTitle()` is unavailable on the tile object, fall back to the
    // prior URL-only match rather than fatal-erroring.
    $tiles_manager = TilesManager::getInstance();
    $tile_url      = Simcard::getListUrl();
    $tile_title    = Simcard::getMenuName();

    // Current variant: tile linked to the root entity.
    $root_entity = new \Entity();
    if ($root_entity->getFromDB(0)) {
        foreach ($tiles_manager->getTilesForItem($root_entity) as $tile) {
            if (
                $tile instanceof ExternalPageTile
                && $tile->getTileUrl() === $tile_url
                && (!method_exists($tile, 'getTitle') || $tile->getTitle() === $tile_title)
            ) {
                $tiles_manager->deleteTile($tile);
            }
        }
    }

    // Legacy variant (first 1.2.0 install linked tiles to helpdesk profiles).
    foreach (plugin_simviewer_get_helpdesk_profiles() as $profile) {
        foreach ($tiles_manager->getTilesForItem($profile) as $tile) {
            if (
                $tile instanceof ExternalPageTile
                && $tile->getTileUrl() === $tile_url
                && (!method_exists($tile, 'getTitle') || $tile->getTitle() === $tile_title)
            ) {
                $tiles_manager->deleteTile($tile);
            }
        }
    }

    return true;
}
