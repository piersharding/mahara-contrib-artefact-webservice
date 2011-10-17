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

    // === institution related functions ===

    'mahara_institution_add_members' => array(
        'classname'   => 'mahara_institution_external',
        'methodname'  => 'add_members',
        'classpath'   => 'artefact/webservice/serviceplugins/institution',
        'description' => 'add members to institutions',
        'type'        => 'write',
    ),

    'mahara_institution_remove_members' => array(
        'classname'   => 'mahara_institution_external',
        'methodname'  => 'remove_members',
        'classpath'   => 'artefact/webservice/serviceplugins/institution',
        'description' => 'remove members from institutions',
        'type'        => 'write',
    ),

    'mahara_institution_invite_members' => array(
        'classname'   => 'mahara_institution_external',
        'methodname'  => 'invite_members',
        'classpath'   => 'artefact/webservice/serviceplugins/institution',
        'description' => 'invite members to institutions',
        'type'        => 'write',
    ),

    'mahara_institution_decline_members' => array(
        'classname'   => 'mahara_institution_external',
        'methodname'  => 'decline_members',
        'classpath'   => 'artefact/webservice/serviceplugins/institution',
        'description' => 'decline request for institiution membership',
        'type'        => 'write',
    ),

    'mahara_institution_get_members' => array(
        'classname'   => 'mahara_institution_external',
        'methodname'  => 'get_members',
        'classpath'   => 'artefact/webservice/serviceplugins/institution',
        'description' => 'decline request for institiution membership',
        'type'        => 'read',
    ),

    'mahara_institution_get_requests' => array(
        'classname'   => 'mahara_institution_external',
        'methodname'  => 'get_requests',
        'classpath'   => 'artefact/webservice/serviceplugins/institution',
        'description' => 'decline request for institiution membership',
        'type'        => 'read',
    ),
);


$services = array(
        'Institution Provisioning' => array(
                'functions' => array ('mahara_institution_add_members', 'mahara_institution_remove_members', 'mahara_institution_invite_members', 'mahara_institution_decline_members',),
                'enabled'=>1,
        ),
        'Institution Query' => array(
                'functions' => array ('mahara_institution_get_members', 'mahara_institution_get_requests'),
                'enabled'=>1,
        ),
        'Simple Institution Provisioning' => array(
                'functions' => array ('mahara_institution_add_members', 'mahara_institution_remove_members', 'mahara_institution_invite_members', 'mahara_institution_decline_members',),
                'enabled'=>1,
                'restrictedusers'=>1,
        ),
        'Simple Institution Query' => array(
                'functions' => array ('mahara_institution_get_members', 'mahara_institution_get_requests'),
                'enabled'=>1,
                'restrictedusers'=>1,
        ),
);
