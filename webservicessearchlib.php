<?php

/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2011 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
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
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Ltd
 * @author     Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2011 Catalyst IT Ltd http://catalyst.net.nz
 * @copyright  (C) portions from Moodle, (C) Martin Dougiamas http://dougiamas.com
 */

defined('INTERNAL') || die();


require_once('searchlib.php');


function build_webservice_user_search_results($search, $offset, $limit, $sortby, $sortdir) {
    global $USER, $token, $suid;

    $results = get_admin_user_search_results($search, $offset, $limit, $sortby, $sortdir);

    $params = array();
    foreach ($search as $k => $v) {
        if (!empty($v)) {
            $params[] = $k . '=' . $v;
        }
    }
    if ($suid) {
        $params[] = 'suid='.$suid;
    }

    $searchurl = get_config('wwwroot') . 'artefact/webservice/search.php?' . join('&', $params) . '&limit=' . $limit;

    $usernametemplate = '<a href="' . get_config('wwwroot')
        . '{if $USER->is_admin_for_user($r.id)}admin/users/edit.php?id={$r.id}{else}user/view.php?id={$r.id}{/if}">{$r.username}</a>';

    if ($suid) {
        if ($suid == 'add') {
            $url = get_config('wwwroot').'artefact/webservice/pluginconfig.php?';
        }
        else {
            $url = get_config('wwwroot').'artefact/webservice/userconfig.php?searchreturn=1&suid='.$suid;
        }
    }
    else {
        if ($token == 'add') {
            $url = get_config('wwwroot').'artefact/webservice/pluginconfig.php?';
        }
        else {
            $url = get_config('wwwroot').'artefact/webservice/tokenconfig.php?searchreturn=1&token='.$token;
        }
    }

    $cols = array(
        'icon'        => array('name'     => '',
                               'template' => '<a href="' . $url.($suid == 'add' ? '' : '&').'user={$r.id}"><img src="{profile_icon_url user=$r maxwidth=40 maxheight=40}" alt="' . get_string('profileimage') . '" /></a>',
                               'class'    => 'center'),
        'firstname'   => array('name'     => get_string('firstname')),
        'lastname'    => array('name'     => get_string('lastname')),
        'username'    => array('name'     => get_string('username'),
                               'template' => $usernametemplate),
        'email'       => array('name'     => get_string('email')),
    );

    $institutions = get_records_assoc('institution', '', '', '', 'name,displayname');
    if (count($institutions) > 1) {
        $cols['institution'] = array('name'     => get_string('institution'),
                                     'template' => '{if !$r.institutions}{$institutions.mahara->displayname}{else}{foreach from=$r.institutions item=i}<div>{$institutions[$i]->displayname}</div>{/foreach}{/if}{if !$r.requested}{foreach from=$r.requested item=i}<div class="pending">{str tag=requestto section=admin} {$institutions[$i]->displayname}{if $USER->is_institutional_admin("$i")} (<a href="{$WWWROOT}admin/users/addtoinstitution.php?id={$r.id}&institution={$i}">{str tag=confirm section=admin}</a>){/if}</div>{/foreach}{/if}{if !$r.invitedby}{foreach from=$r.invitedby item=i}<div class="pending">{str tag=invitedby section=admin} {$institutions[$i]->displayname}</div>{/foreach}{/if}');
    }

    $smarty = smarty_core();
    $smarty->assign_by_ref('results', $results);
    $smarty->assign_by_ref('institutions', $institutions);
    $smarty->assign('USER', $USER);
    $smarty->assign('searchurl', $searchurl);
    $smarty->assign('sortby', $sortby);
    $smarty->assign('sortdir', $sortdir);
    $smarty->assign('token', $token);
    $smarty->assign('suid', $suid);
    $smarty->assign('pagebaseurl', $searchurl . '&suid=' . $suid  . '&token=' . $token . '&sortby=' . $sortby . '&sortdir=' . $sortdir);
    $smarty->assign('cols', $cols);
    $smarty->assign('ncols', count($cols));
    return $smarty->fetch('searchresulttable.tpl');
}

