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

    // === user related functions ===

    'mahara_user_create_users' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'create_users',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Create users',
        'type'        => 'write',
    ),

    'mahara_user_update_users' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'update_users',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Update users',
        'type'        => 'write',
    ),

    'mahara_user_get_users' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'get_users',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Get all users',
        'type'        => 'read',
    ),

    'mahara_user_get_users_by_id' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'get_users_by_id',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Get users by id.',
        'type'        => 'read',
    ),

    'mahara_user_get_my_user' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'get_my_user',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Get the current user details',
        'type'        => 'read',
    ),

    'mahara_user_delete_users' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'delete_users',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Delete users by id.',
        'type'        => 'write',
    ),

    'mahara_user_get_favourites' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'get_favourites',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Get favourites for a user',
        'type'        => 'read',
    ),

    'mahara_user_get_all_favourites' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'get_all_favourites',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Get all user favourites',
        'type'        => 'read',
    ),

    'mahara_user_update_favourites' => array(
        'classname'   => 'mahara_user_external',
        'methodname'  => 'update_favourites',
        'classpath'   => 'artefact/webservice/serviceplugins/user',
        'description' => 'Update user favourites',
        'type'        => 'write',
    ),

);


$services = array(
        'User Provisioning' => array(
                'functions' => array ('mahara_user_get_all_favourites', 'mahara_user_get_favourites', 'mahara_user_update_favourites', 'mahara_user_get_users', 'mahara_user_get_users_by_id', 'mahara_user_create_users', 'mahara_user_delete_users', 'mahara_user_update_users'),
                'enabled'=>1,
        ),
        'User Query' => array(
                'functions' => array ('mahara_user_get_all_favourites', 'mahara_user_get_favourites', 'mahara_user_get_users', 'mahara_user_get_users_by_id'),
                'enabled'=>1,
        ),
        'Simple User Provisioning' => array(
                'functions' => array ('mahara_user_get_all_favourites', 'mahara_user_get_favourites', 'mahara_user_update_favourites', 'mahara_user_get_users', 'mahara_user_get_users_by_id', 'mahara_user_create_users', 'mahara_user_delete_users', 'mahara_user_update_users'),
                'enabled'=>1,
                'restrictedusers'=>1,
        ),
        'Simple User Query' => array(
                'functions' => array ('mahara_user_get_all_favourites', 'mahara_user_get_favourites', 'mahara_user_get_users', 'mahara_user_get_users_by_id'),
                'enabled'=>1,
                'restrictedusers'=>1,
        ),
        'UserToken User Query' => array(
                'functions' => array ('mahara_user_get_my_user'),
                'enabled'=>1,
                'tokenusers'=>1,
        ),
);


