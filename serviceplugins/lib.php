<?php

/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2009 Moodle Pty Ltd (http://moodle.com)
 * Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * External user API
 *
 * @package    artefact
 * @subpackage webservice
 * @copyright  2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

require_once(get_config('docroot') . '/artefact/webservice/libs/externallib.php');
require_once(get_config('docroot') . '/lib/user.php');
require_once(get_config('docroot') . '/lib/group.php');
require_once('institution.php');

/**
 * Check that a user is in the institution
 *
 * @param array $user array('id' => .., 'username' => ..)
 * @param string $institution
 * @return boolean true on yes
 */
function mahara_external_in_institution($user, $institution) {
    $institutions = array_keys(load_user_institutions($user->id));
    $auth_instance = get_record('auth_instance', 'id', $user->authinstance);
    $institutions[]= $auth_instance->institution;
    if (!in_array($institution, $institutions)) {
        return false;
    }
    return true;
}

