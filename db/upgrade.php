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
 * @subpackage artefact-webservice
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2011 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();


function xmldb_artefact_webservice_upgrade($oldversion=0) {

    $status = true;

    if ($oldversion < 2010012703) {
        // delete everything as we changed the component name
        delete_records('external_services_users');
        delete_records('external_tokens');
        delete_records('external_services_functions');
        delete_records('external_services');
        delete_records('external_functions');
    }

    if ($oldversion < 2010012704) {
        // add public key validation for SOAP/XML-RPC enc/sig
        $table = new XMLDBTable('external_tokens');
        $field = new XMLDBField('publickey');
        $field->setAttributes(XMLDB_TYPE_TEXT, null, false, true, false, null, null, '');
        add_field($table, $field);
        $field = new XMLDBField('publickeyexpires');
        $field->setAttributes(XMLDB_TYPE_DATETIME);
        add_field($table, $field);
        $table = new XMLDBTable('external_services_users');
        $field = new XMLDBField('lastaccess');
        $field->setAttributes(XMLDB_TYPE_DATETIME);
        add_field($table, $field);
        $field = new XMLDBField('publickey');
        $field->setAttributes(XMLDB_TYPE_TEXT, null, false, true, false, null, null, '');
        add_field($table, $field);
        $field = new XMLDBField('publickeyexpires');
        $field->setAttributes(XMLDB_TYPE_DATETIME);
        add_field($table, $field);
    }

    if ($oldversion < 2010012705) {
        // add public key switch
        $table = new XMLDBTable('external_tokens');
        $field = new XMLDBField('wssigenc');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $field);
        $table = new XMLDBTable('external_services_users');
        $field = new XMLDBField('wssigenc');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $field);
    }
    // sweep for webservice updates everytime
    $status = external_upgrade_webservices();
    return $status;
}

?>
