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
        $field->setAttributes(XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $field);
        $table = new XMLDBTable('external_services_users');
        $field = new XMLDBField('lastaccess');
        $field->setAttributes(XMLDB_TYPE_DATETIME);
        add_field($table, $field);
        $field = new XMLDBField('publickey');
        $field->setAttributes(XMLDB_TYPE_TEXT, null, false, true, false, null, null, '');
        add_field($table, $field);
        $field = new XMLDBField('publickeyexpires');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null, null, 0);
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

    if ($oldversion < 2010012706) {
        // add oauth tables
        $table = new XMLDBTable('oauth_consumer_registry');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('consumer_key', XMLDB_TYPE_CHAR, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('signature_methods', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('server_uri', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('server_uri_host', XMLDB_TYPE_CHAR, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('server_uri_path', XMLDB_TYPE_CHAR, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('request_token_uri', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('authorize_uri', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('access_token_uri', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('timestamp', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, 'current_timestamp');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('userid', XMLDB_KEY_FOREIGN, array('userid'), 'usr', array('id'));
        $table->addIndexInfo('uk_userid_server_uri', XMLDB_INDEX_UNIQUE, array('consumer_key', 'userid', 'server_uri'));
        create_table($table);

        $table = new XMLDBTable('oauth_consumer_token');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('ocr_id_ref', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, 64, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token', XMLDB_TYPE_CHAR, 64, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token_secret', XMLDB_TYPE_CHAR, 64, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token_type', XMLDB_TYPE_CHAR, 20, null, XMLDB_NOTNULL, null, true, array('request', 'authorized', 'access'));
        $table->addFieldInfo('token_ttl', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, "'9999-12-31'");
        $table->addFieldInfo('timestamp', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, 'current_timestamp');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('fk_ocr_id_ref', XMLDB_KEY_FOREIGN, array('ocr_id_ref'), 'oauth_consumer_registry', array('id'));
        $table->addKeyInfo('userid', XMLDB_KEY_FOREIGN, array('userid'), 'usr', array('id'));
        $table->addIndexInfo('uk_id_ref_token', XMLDB_INDEX_UNIQUE, array('ocr_id_ref', 'token'));
        $table->addIndexInfo('uk_userid_id_ref_type_name', XMLDB_INDEX_UNIQUE, array('userid', 'ocr_id_ref', 'token_type', 'name'));
        $table->addIndexInfo('i_token_ttl', XMLDB_INDEX_NOTUNIQUE, array('token_ttl'));
        create_table($table);

        $table = new XMLDBTable('oauth_server_registry');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('externalserviceid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('institution', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('consumer_key', XMLDB_TYPE_CHAR, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('consumer_secret', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('enabled', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        $table->addFieldInfo('status', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('requester_name', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('requester_email', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('callback_uri', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('application_uri', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('application_title', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('application_descr', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('application_notes', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('application_type', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('issue_date', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('timestamp', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, 'current_timestamp');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('userid', XMLDB_KEY_FOREIGN, array('userid'), 'usr', array('id'));
        $table->addKeyInfo('externalserviceid', XMLDB_KEY_FOREIGN, array('externalserviceid'), 'external_services', array('id'));
        $table->addKeyInfo('institutionfk', XMLDB_KEY_FOREIGN, array('institution'), 'institution', array('name'));
        $table->addIndexInfo('uk_consumer_key', XMLDB_INDEX_UNIQUE, array('consumer_key'));
        create_table($table);
        
        $table = new XMLDBTable('oauth_server_nonce');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('consumer_key', XMLDB_TYPE_CHAR, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token', XMLDB_TYPE_CHAR, 64, null, XMLDB_NOTNULL);
        $table->addFieldInfo('nonce', XMLDB_TYPE_CHAR, 80, null, XMLDB_NOTNULL);
        $table->addFieldInfo('timestamp', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, 'current_timestamp');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addIndexInfo('uk_keys', XMLDB_INDEX_UNIQUE, array('consumer_key', 'token', 'timestamp', 'nonce'));
        create_table($table);

        $table = new XMLDBTable('oauth_server_token');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('osr_id_ref', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token', XMLDB_TYPE_CHAR, 64, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token_secret', XMLDB_TYPE_CHAR, 64, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token_type', XMLDB_TYPE_CHAR, 20, null, XMLDB_NOTNULL, null, true, array('request', 'access'));
        $table->addFieldInfo('authorized', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        $table->addFieldInfo('referrer_host', XMLDB_TYPE_CHAR, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('callback_uri', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('verifier', XMLDB_TYPE_CHAR, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('token_ttl', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, "'9999-12-31'");
        $table->addFieldInfo('timestamp', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL, null, null, null, 'current_timestamp');
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('fk_ref_id', XMLDB_KEY_FOREIGN, array('osr_id_ref'), 'oauth_server_registry', array('id'));
        $table->addKeyInfo('userid', XMLDB_KEY_FOREIGN, array('userid'), 'usr', array('id'));
        $table->addIndexInfo('uk_token', XMLDB_INDEX_UNIQUE, array('token'));
        $table->addIndexInfo('i_token_ttl', XMLDB_INDEX_NOTUNIQUE, array('token_ttl'));
        create_table($table);
    }

    // sweep for webservice updates everytime
    $status = external_upgrade_webservices();
    return $status;
}

?>
