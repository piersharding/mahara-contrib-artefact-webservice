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
 * Core external functions and service definitions.
 *
 * @package    core
 * @subpackage webservice
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

$functions = array(

    // === group related functions ===

    'mahara_group_create_groups' => array(
        'classname'   => 'mahara_group_external',
        'methodname'  => 'create_groups',
        'classpath'   => 'artefact/webservice/serviceplugins/group',
        'description' => 'Create groups',
        'type'        => 'write',
    ),

    'mahara_group_update_groups' => array(
        'classname'   => 'mahara_group_external',
        'methodname'  => 'update_groups',
        'classpath'   => 'artefact/webservice/serviceplugins/group',
        'description' => 'Update groups',
        'type'        => 'write',
    ),

    'mahara_group_get_groups' => array(
        'classname'   => 'mahara_group_external',
        'methodname'  => 'get_groups',
        'classpath'   => 'artefact/webservice/serviceplugins/group',
        'description' => 'Get all groups',
        'type'        => 'read',
    ),

    'mahara_group_get_groups_by_id' => array(
        'classname'   => 'mahara_group_external',
        'methodname'  => 'get_groups_by_id',
        'classpath'   => 'artefact/webservice/serviceplugins/group',
        'description' => 'Get groups by id.',
        'type'        => 'read',
    ),

    'mahara_group_delete_groups' => array(
        'classname'   => 'mahara_group_external',
        'methodname'  => 'delete_groups',
        'classpath'   => 'artefact/webservice/serviceplugins/group',
        'description' => 'Delete groups by id.',
        'type'        => 'write',
    ),
);


$services = array(
        'Group Provisioning' => array(
                'functions' => array ('mahara_group_get_groups', 'mahara_group_get_groups_by_id', 'mahara_group_create_groups', 'mahara_group_delete_groups', 'mahara_group_update_groups'),
                'enabled'=>1,
        ),
        'Group Query' => array(
                'functions' => array ('mahara_group_get_groups', 'mahara_group_get_groups_by_id'),
                'enabled'=>1,
        ),
        'Simple Group Provisioning' => array(
                'functions' => array ('mahara_group_get_groups', 'mahara_group_get_groups_by_id', 'mahara_group_create_groups', 'mahara_group_delete_groups', 'mahara_group_update_groups'),
                'enabled'=>1,
                'restrictedusers'=>1,
        ),
        'Simple Group Query' => array(
                'functions' => array ('mahara_group_get_groups', 'mahara_group_get_groups_by_id'),
                'enabled'=>1,
                'restrictedusers'=>1,
        ),
);


