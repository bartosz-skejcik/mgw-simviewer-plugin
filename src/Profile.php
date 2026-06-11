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

namespace GlpiPlugin\Simviewer;

use CommonGLPI;
use Html;
use Session;

/**
 * Exposes the dedicated `plugin_simviewer` READ right on the Profile form so
 * an admin can grant SIM Viewer access per profile (self-service or otherwise).
 */
class Profile extends \Profile
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Profile && $item->getField('id')) {
            return self::createTabEntry(__('SIM Viewer', 'simviewer'));
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof \Profile) {
            (new self())->showForm($item->getID());
        }

        return true;
    }

    public function showForm($profiles_id = 0, array $options = [])
    {
        if (!self::canView()) {
            return false;
        }

        echo "<div class='spaced'>";

        $can_edit = Session::haveRight(self::$rightname, UPDATE);
        if ($can_edit) {
            echo "<form method='post' action='" . htmlspecialchars(\Profile::getFormURL()) . "'>";
        }

        $rights = [
            [
                'itemtype' => Simcard::class,
                'label'    => Simcard::getMenuName(),
                'field'    => Simcard::$rightname,
                // Read-only feature: only the READ permission is offered.
                'rights'   => [READ => __('Read')],
            ],
        ];

        $this->displayRightsChoiceMatrix($rights, [
            'canedit' => $can_edit,
            'title'   => Simcard::getMenuName(),
        ]);

        if ($can_edit) {
            echo "<div class='text-center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }

        echo "</div>";

        return true;
    }
}
