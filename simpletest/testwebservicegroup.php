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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Test the different web service protocols.
 *
 * @author jerome@moodle.com
 * @author     Piers Harding
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package web service
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 */

// must be run from the command line
if (isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['GATEWAY_INTERFACE'])){
    die('Direct access to this script is forbidden.');
}

define('INTERNAL', 1);
require_once('testwebservicebase.php');



/**
 * How to configure this unit tests:
 * 0- Enable the web service you wish to test
 * 1- Create a service with all functions
 * 2- Create a token associate this service and to an admin (or a user with all required capabilities)
 * 3- Configure setUp() function:
 *      a- write the token
 *      b- activate the protocols you wish to test
 *      c- activate the functions you wish to test (readonlytests and writetests arrays)
 *      d- set the number of times the web services are run
 * Do not run WRITE test function on a production site as they impact the DB (even though every
 * test should clean the modified data)
 *
 * How to write a new function:
 * 1- Add the function name to the array readonlytests/writetests
 * 2- Set it as false when you commit!
 * 3- write the function  - Do not prefix the function name by 'test'
 */
class webservice_test extends webservice_test_base {


    function setUp() {
        // default current user to admin
        parent::setUp();


        //protocols to test
//        $this->testrest = true;
        $this->testxmlrpc = true;
//        $this->testsoap = true;

        ////// READ-ONLY DB tests ////
        $this->readonlytests = array(
            'mahara_group_get_groups_by_id' => true,
            'mahara_group_get_groups' => true,
        );

        ////// WRITE DB tests ////
        $this->writetests = array(
            'mahara_group_create_groups' => true,
            'mahara_group_update_groups' => true,
            'mahara_group_delete_groups' => true,
        );

        //performance testing: number of time the web service are run
        $this->iteration = 1;

    }



    function testRun() {
        global $CFG;

        if (!$this->testrest and !$this->testxmlrpc and !$this->testsoap) {
            print_r("Web service unit tests are not run as not setup.
                (see /artefact/webservice/simpletest/testwebservice.php)");
        }

        // need a token to test
        if (!empty($this->testtoken)) {

            // test the REST interface
            if ($this->testrest) {
                $this->timerrest = time();
                require_once(get_config('docroot') . "/artefact/webservice/rest/lib.php");
                // iterate the token and user auth types
                foreach (array('server', 'simpleserver') as $type) {
                    $restclient = new webservice_rest_client(get_config('wwwroot')
                                    . 'artefact/webservice/rest/'.$type.'.php',
                                     ($type == 'server' ? array('wstoken' => $this->testtoken) :
                                                          array('wsusername' => $this->testuser, 'wspassword' => $this->testuser)), $type);
                    for ($i = 1; $i <= $this->iteration; $i = $i + 1) {
                        foreach ($this->readonlytests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($restclient);
                            }
                        }
                        foreach ($this->writetests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($restclient);
                            }
                        }
                    }
                }

                $this->timerrest = time() - $this->timerrest;
                //here you could call a log function to display the timer
                //example:
//                error_log('REST time: ');
//                error_log(print_r($this->timerrest));
            }

            // test the XML-RPC interface
            if ($this->testxmlrpc) {
                $this->timerxmlrpc = time();
                require_once(get_config('docroot') . "/artefact/webservice/xmlrpc/lib.php");
                // iterate the token and user auth types
                foreach (array('server', 'simpleserver') as $type) {
                    $xmlrpcclient = new webservice_xmlrpc_client(get_config('wwwroot')
                                    . 'artefact/webservice/xmlrpc/'.$type.'.php',
                                     ($type == 'server' ? array('wstoken' => $this->testtoken) :
                                                          array('wsusername' => $this->testuser, 'wspassword' => $this->testuser)));

                    for ($i = 1; $i <= $this->iteration; $i = $i + 1) {
                        foreach ($this->readonlytests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($xmlrpcclient);
                            }
                        }
                        foreach ($this->writetests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($xmlrpcclient);
                            }
                        }
                    }
                }

                $this->timerxmlrpc = time() - $this->timerxmlrpc;
                //here you could call a log function to display the timer
                //example:
//                error_log('XML-RPC time: ');
//                error_log(print_r($this->timerxmlrpc));
            }

            // test the SOAP interface
            if ($this->testsoap) {
                $this->timersoap = time();
                require_once(get_config('docroot') . "/artefact/webservice/soap/lib.php");

                // iterate the token and user auth types
                foreach (array(array('wstoken' => $this->testtoken, 'type' => 'server'),
                               array('type' => 'simpleserver', 'wsusername' => $this->testuser, 'wspassword' => $this->testuser),
                               array('type' => 'simpleserver', 'wsse' => 1)) as $parms) {
                    $type = $parms['type'];
                    unset($parms['type']);
                    if (isset($parms['wsse'])) {
                        $soapclient = new webservice_soap_client(get_config('wwwroot')
                                        . 'artefact/webservice/soap/'.$type.'.php', array('wsservice' => $this->servicename),
                                            array("features" => SOAP_WAIT_ONE_WAY_CALLS)); //force SOAP synchronous mode
                                                                                           //when function return null
                        $wsseSoapClient = new webservice_soap_client_wsse(array($soapclient, '_doRequest'), $soapclient->wsdl, $soapclient->getOptions());
                        $wsseSoapClient->__setUsernameToken($this->testuser, $this->testuser);
                        $soapclient->setSoapClient($wsseSoapClient);
                    }
                    else {
                        $soapclient = new webservice_soap_client(get_config('wwwroot')
                                        . 'artefact/webservice/soap/'.$type.'.php', $parms,
                                            array("features" => SOAP_WAIT_ONE_WAY_CALLS)); //force SOAP synchronous mode
                                                                                           //when function return null
                    }
                    $soapclient->setWsdlCache(false);
                    for ($i = 1; $i <= $this->iteration; $i = $i + 1) {
                        foreach ($this->readonlytests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($soapclient);
                            }
                        }
                        foreach ($this->writetests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($soapclient);
                            }
                        }
                    }
                }

                $this->timersoap = time() - $this->timersoap;
                //here you could call a log function to display the timer
                //example:
//                error_log('SOAP time: ');
//                error_log(print_r($this->timersoap));
            }
        }
    }

    ///// WEB SERVICE TEST FUNCTIONS


    // simple get groups by ID
    function mahara_group_get_groups_by_id($client) {

        error_log('getting groups by id');

        $dbgroups = get_records_sql_array('SELECT * FROM {group} WHERE institution = ? AND shortname = ? AND deleted = 0', array('mahara', 'mytestgroup1'));
        $groupids = array();
        foreach ($dbgroups as $dbgroup) {
            if ($dbgroup->id == 0) continue;
            $groupids[] = array('id' => $dbgroup->id);
        }
        $function = 'mahara_group_get_groups_by_id';

        $params = array('groups' => $groupids);
        $groups = $client->call($function, $params);
        $this->assertEqual(count($groups), count($groupids));
    }


    // simple get all groups
    function mahara_group_get_groups($client) {

        error_log('getting all groups');

        $function = 'mahara_group_get_groups';
        $dbgroups = get_records_sql_array('SELECT * FROM {group} WHERE institution = ? AND shortname = ? AND deleted = 0', array('mahara', 'mytestgroup1'));
        $params = array();
        $groups = $client->call($function, $params);

        $this->assertEqual(count($groups), count($groups));
    }


    // create user test
    function mahara_group_create_groups($client) {

        error_log('creating groups');
        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $groupcategories = get_records_array('group_category','','','displayorder');
        $category = array_shift($groupcategories);
        //Test data
        //a full group: group1
        $group1 = new stdClass();
        $group1->name           = 'The test group 1 - create';
        $group1->shortname      = 'testgroupshortname1';
        $group1->description    = 'a description for test group 1';
        $group1->institution    = 'mahara';
        $group1->grouptype      = 'standard';
        $group1->category       = $category->title;
//        $group1->jointype       = 'invite';
        $group1->open           = 1;
        $group1->request        = 0;
        $group1->controlled     = 0;
        $group1->submitpages    = 0;
        $group1->hidemembers    = 0;
        $group1->invitefriends  = 0;
        $group1->suggestfriends = 0;
        $group1->hidden         = 0;
        $group1->hidemembersfrommembers = 0;
        $group1->public         = 0;
        $group1->usersautoadded = 0;
//        $group1->viewnotify     = 0;
        $group1->members        = array(array('id' => $dbuser1->id, 'role' => 'admin'), array('id' => $dbuser2->id, 'role' => 'admin'));

        //a small group: group2
        $group2 = new stdClass();
        $group2->shortname      = 'testgroupshortname2';
        $group2->name           = 'The test group 2 - create';
        $group2->description    = 'a description for test group 2';
        $group2->institution    = 'mahara';
        $group2->grouptype      = 'standard';
        $group2->category       = $category->title;
//        $group2->jointype       = 'invite';
        $group2->open           = 1;
        $group2->request        = 0;
        $group2->controlled     = 0;
        $group2->submitpages    = 0;
        $group2->hidemembers    = 0;
        $group2->invitefriends  = 0;
        $group2->suggestfriends = 0;
        $group2->hidden         = 0;
        $group2->hidemembersfrommembers = 0;
        $group2->public         = 0;
        $group2->usersautoadded = 0;
//        $group2->viewnotify     = 0;
        $group2->members        = array(array('username' => $dbuser1->username, 'role' => 'admin'), array('username' => $dbuser2->username, 'role' => 'admin'));
        $groups = array($group1, $group2);

        //do not run the test if group1 or group2 already exists
        foreach (array($group1->shortname, $group2->shortname) as $shortname) {
            $existinggroup = get_record('group', 'shortname', $shortname, 'institution', 'mahara');
            if (!empty($existinggroup)) {
                group_delete($existinggroup->id);
            }
        }

        $function = 'mahara_group_create_groups';
        $params = array('groups' => $groups);
        $resultgroups = $client->call($function, $params);

        // store groups for deletion at the end
        foreach ($resultgroups as $g) {
            $this->created_groups[]= $g['id'];
        }
        $this->assertEqual(count($groups), count($resultgroups));
        $dbgroup1 = get_record('group', 'shortname', $group1->shortname, 'institution', 'mahara');
        $dbgroupmembers1 = get_records_array('group_member', 'group', $dbgroup1->id);

        $dbgroup2 = get_record('group', 'shortname', $group2->shortname, 'institution', 'mahara');
        $dbgroupmembers2 = get_records_array('group_member', 'group', $dbgroup2->id);

        // temporary hack untl group changes are sorted XXX
        $dbgroup1->open = ($dbgroup1->jointype == 'open' ? 1 : 0);
        $dbgroup1->request = ($dbgroup1->jointype == 'request' ? 1 : 0);
        $dbgroup1->controlled = ($dbgroup1->jointype == 'controlled' ? 1 : 0);
        $dbgroup1->submitpages = (isset($dbgroup1->submitpages) ? $dbgroup1->submitpages : 0);
        $dbgroup1->hidemembers = (isset($dbgroup1->hidemembers) ? $dbgroup1->hidemembers : 0);
        $dbgroup1->invitefriends = (isset($dbgroup1->invitefriends) ? $dbgroup1->invitefriends : 0);
        $dbgroup1->suggestfriends = (isset($dbgroup1->suggestfriends) ? $dbgroup1->suggestfriends : 0);
        $dbgroup1->hidden = (isset($dbgroup1->hidden) ? $dbgroup1->hidden : 0);
        $dbgroup1->hidemembersfrommembers = (isset($dbgroup1->hidemembersfrommembers) ? $dbgroup1->hidemembersfrommembers : 0);
        $dbgroup2->open = ($dbgroup2->jointype == 'open' ? 1 : 0);
        $dbgroup2->request = ($dbgroup2->jointype == 'request' ? 1 : 0);
        $dbgroup2->controlled = ($dbgroup2->jointype == 'controlled' ? 1 : 0);
        $dbgroup2->submitpages = (isset($dbgroup2->submitpages) ? $dbgroup2->submitpages : 0);
        $dbgroup2->hidemembers = (isset($dbgroup2->hidemembers) ? $dbgroup2->hidemembers : 0);
        $dbgroup2->invitefriends = (isset($dbgroup2->invitefriends) ? $dbgroup2->invitefriends : 0);
        $dbgroup2->suggestfriends = (isset($dbgroup2->suggestfriends) ? $dbgroup2->suggestfriends : 0);
        $dbgroup2->hidden = (isset($dbgroup2->hidden) ? $dbgroup2->hidden : 0);
        $dbgroup2->hidemembersfrommembers = (isset($dbgroup2->hidemembersfrommembers) ? $dbgroup2->hidemembersfrommembers : 0);

        //retrieve groups from the DB and check values
        $this->assertEqual($dbgroup1->name, $group1->name);
        $this->assertEqual($dbgroup1->description, $group1->description);
        $this->assertEqual($dbgroup1->grouptype, $group1->grouptype);
        $this->assertEqual($dbgroup1->category, $category->id);
//        $this->assertEqual($dbgroup1->jointype, $group1->jointype);
        $this->assertEqual($dbgroup1->open, $group1->open);
        $this->assertEqual($dbgroup1->request, $group1->request);
        $this->assertEqual($dbgroup1->controlled, $group1->controlled);
        $this->assertEqual($dbgroup1->submitpages, $group1->submitpages);
        $this->assertEqual($dbgroup1->hidemembers, $group1->hidemembers);
        $this->assertEqual($dbgroup1->invitefriends, $group1->invitefriends);
        $this->assertEqual($dbgroup1->suggestfriends, $group1->suggestfriends);
        $this->assertEqual($dbgroup1->hidden, $group1->hidden);
        $this->assertEqual($dbgroup1->hidemembersfrommembers, $group1->hidemembersfrommembers);
        $this->assertEqual($dbgroup1->public, $group1->public);
        $this->assertEqual($dbgroup1->usersautoadded, $group1->usersautoadded);
        $this->assertEqual($dbgroup1->viewnotify, 1);
        $this->assertEqual(count($dbgroupmembers1), count($group1->members)+1); // current user added as admin

        $this->assertEqual($dbgroup2->name, $group2->name);
        $this->assertEqual($dbgroup2->description, $group2->description);
        $this->assertEqual($dbgroup2->grouptype, $group2->grouptype);
        $this->assertEqual($dbgroup2->category, $category->id);
//        $this->assertEqual($dbgroup2->jointype, $group2->jointype);
        $this->assertEqual($dbgroup2->open, $group2->open);
        $this->assertEqual($dbgroup2->request, $group2->request);
        $this->assertEqual($dbgroup2->controlled, $group2->controlled);
        $this->assertEqual($dbgroup2->submitpages, $group2->submitpages);
        $this->assertEqual($dbgroup2->hidemembers, $group2->hidemembers);
        $this->assertEqual($dbgroup2->invitefriends, $group2->invitefriends);
        $this->assertEqual($dbgroup2->suggestfriends, $group2->suggestfriends);
        $this->assertEqual($dbgroup2->hidden, $group2->hidden);
        $this->assertEqual($dbgroup2->hidemembersfrommembers, $group2->hidemembersfrommembers);
        $this->assertEqual($dbgroup2->public, $group2->public);
        $this->assertEqual($dbgroup2->usersautoadded, $group2->usersautoadded);
        $this->assertEqual($dbgroup2->viewnotify, 1);
        $this->assertEqual(count($dbgroupmembers2), count($group2->members)+1); // current user added as admin
    }


    // delete user test
    function mahara_group_delete_groups($client) {
        global $DB, $CFG;

        error_log('deleting groups');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $groupcategories = get_records_array('group_category','','','displayorder');
        $category = array_shift($groupcategories);
        //Test data
        //a full group: group1
        $group1 = new stdClass();
        $group1->name           = 'The test group 1 - create';
        $group1->shortname      = 'testgroupshortname1';
        $group1->description    = 'a description for test group 1';
        $group1->institution    = 'mahara';
        $group1->grouptype      = 'standard';
        $group1->category       = $category->id;
        $group1->jointype       = 'open';
        $group1->open           = 1;
        $group1->request        = 0;
        $group1->controlled     = 0;
        $group1->submitpages    = 0;
        $group1->hidemembers    = 0;
        $group1->invitefriends  = 0;
        $group1->suggestfriends = 0;
        $group1->hidden         = 0;
        $group1->hidemembersfrommembers = 0;
        $group1->public         = 0;
        $group1->usersautoadded = 0;
//        $group1->viewnotify     = 0;
        $group1->members        = array(array('id' => $dbuser1->id, 'role' => 'admin'), array('id' => $dbuser2->id, 'role' => 'admin'));

        //a small group: group2
        $group2 = new stdClass();
        $group2->shortname      = 'testgroupshortname2';
        $group2->name           = 'The test group 2 - create';
        $group2->description    = 'a description for test group 2';
        $group2->institution    = 'mahara';
        $group2->grouptype      = 'standard';
        $group2->category       = $category->id;
        $group2->jointype       = 'open';
        $group2->open           = 1;
        $group2->request        = 0;
        $group2->controlled     = 0;
        $group2->submitpages    = 0;
        $group2->hidemembers    = 0;
        $group2->invitefriends  = 0;
        $group2->suggestfriends = 0;
        $group2->hidden         = 0;
        $group2->hidemembersfrommembers = 0;
        $group2->public         = 0;
        $group2->usersautoadded = 0;
//        $group2->viewnotify     = 0;
        $group2->members        = array(array('username' => $dbuser1->username, 'role' => 'admin'), array('username' => $dbuser2->username, 'role' => 'admin'));

        //do not run the test if group1 or group2 already exists
        foreach (array($group1->shortname, $group2->shortname) as $shortname) {
            $existinggroup = get_record('group', 'shortname', $shortname, 'institution', 'mahara');
            if (!empty($existinggroup)) {
                group_delete($existinggroup->id);
            }
        }

        // setup test groups
        $groupid1 = group_create((array) $group1);
        $groupid2 = group_create((array) $group2);


        $dbgroup1 = get_record('group', 'shortname', $group1->shortname, 'institution', 'mahara');
        $dbgroup2 = get_record('group', 'shortname', $group2->shortname, 'institution', 'mahara');

        //delete the users by webservice
        $function = 'mahara_group_delete_groups';
        $params = array('groups' => array(array('id' => $dbgroup1->id), array('shortname' => $dbgroup2->shortname, 'institution' => $dbgroup2->institution)));
        $client->call($function, $params);

        //search for them => TESTS they don't exists
        foreach (array($dbgroup1, $dbgroup2) as $group) {
            $group = get_record('group', 'id', $group->id, 'deleted', 0);
            $this->assertTrue(empty($group));
        }
    }


    // update user test
    function mahara_group_update_groups($client) {
        global $DB, $CFG;

        error_log('updating groups');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $groupcategories = get_records_array('group_category','','','displayorder');
        $category = array_shift($groupcategories);
        //Test data
        //a full group: group1
        $group1 = new stdClass();
        $group1->name           = 'The test group 1 - create';
        $group1->shortname      = 'testgroupshortname1';
        $group1->description    = 'a description for test group 1';
        $group1->institution    = 'mahara';
        $group1->grouptype      = 'standard';
        $group1->category       = $category->id;
        $group1->jointype       = 'invite';
        $group1->open           = 1;
        $group1->request        = 0;
        $group1->controlled     = 0;
        $group1->submitpages    = 0;
        $group1->hidemembers    = 0;
        $group1->invitefriends  = 0;
        $group1->suggestfriends = 0;
        $group1->hidden         = 0;
        $group1->hidemembersfrommembers = 0;
        $group1->public         = 0;
        $group1->usersautoadded = 0;
//        $group1->viewnotify     = 0;
        $group1->members        = array(array('id' => $dbuser1->id, 'role' => 'admin'), array('id' => $dbuser2->id, 'role' => 'admin'));

        //a small group: group2
        $group2 = new stdClass();
        $group2->shortname      = 'testgroupshortname2';
        $group2->name           = 'The test group 2 - create';
        $group2->description    = 'a description for test group 2';
        $group2->institution    = 'mahara';
        $group2->grouptype      = 'standard';
        $group2->category       = $category->id;
        $group2->jointype       = 'invite';
        $group2->open           = 1;
        $group2->request        = 0;
        $group2->controlled     = 0;
        $group2->submitpages    = 0;
        $group2->hidemembers    = 0;
        $group2->invitefriends  = 0;
        $group2->suggestfriends = 0;
        $group2->hidden         = 0;
        $group2->hidemembersfrommembers = 0;
        $group2->public         = 0;
        $group2->usersautoadded = 0;
//        $group2->viewnotify     = 0;
        $group2->members        = array(array('username' => $dbuser1->username, 'role' => 'admin'), array('username' => $dbuser2->username, 'role' => 'admin'));

        //do not run the test if group1 or group2 already exists
        foreach (array($group1->shortname, $group2->shortname) as $shortname) {
            $existinggroup = get_record('group', 'shortname', $shortname, 'institution', 'mahara');
            if (!empty($existinggroup)) {
                group_delete($existinggroup->id);
            }
        }

        // setup test groups
        $groupid1 = group_create((array) $group1);
        $groupid2 = group_create((array) $group2);
        $this->created_groups[]= $groupid1;
        $this->created_groups[]= $groupid2;

        $dbgroup1 = get_record('group', 'shortname', $group1->shortname, 'institution', 'mahara');
        $dbgroup2 = get_record('group', 'shortname', $group2->shortname, 'institution', 'mahara');

        //update the test data
        $group1 = new stdClass();
        $group1->id             = $dbgroup1->id;
        $group1->name           = 'The test group 1 - changed';
        $group1->shortname      = 'testgroupshortname1 - changed';
        $group1->description    = 'a description for test group 1 - changed';
        $group1->institution    = 'mahara';
        $group1->grouptype      = 'standard';
        $group1->category       = $category->title;
//        $group1->jointype       = 'invite';
        $group1->open           = 1;
        $group1->request        = 0;
        $group1->controlled     = 0;
        $group1->submitpages    = 0;
        $group1->hidemembers    = 0;
        $group1->invitefriends  = 0;
        $group1->suggestfriends = 0;
        $group1->hidden         = 0;
        $group1->hidemembersfrommembers = 0;
        $group1->public         = 0;
        $group1->usersautoadded = 0;
//        $group1->viewnotify     = 0;
        $group1->members        = array(array('id' => $dbuser1->id, 'role' => 'admin'));

        //a small group: group2
        $group2 = new stdClass();
//        $group2->id             = $dbgroup2->id;
        $group2->shortname      = 'testgroupshortname2';
        $group2->name           = 'The test group 2 - changed';
        $group2->description    = 'a description for test group 2 - changed';
        $group2->institution    = 'mahara';
        $group2->grouptype      = 'standard';
        $group2->category       = $category->title;
//        $group2->jointype       = 'invite';
        $group2->open           = 1;
        $group2->request        = 0;
        $group2->controlled     = 0;
        $group2->submitpages    = 0;
        $group2->hidemembers    = 0;
        $group2->invitefriends  = 0;
        $group2->suggestfriends = 0;
        $group2->hidden         = 0;
        $group2->hidemembersfrommembers = 0;
        $group2->public         = 0;
        $group2->usersautoadded = 0;
//        $group2->viewnotify     = 0;
        $group2->members        = array(array('username' => $dbuser2->username, 'role' => 'admin'));
        $groups = array($group1, $group2);

        //update the users by web service
        $function = 'mahara_group_update_groups';
        $params = array('groups' => $groups);
        $client->call($function, $params);

        $dbgroup1 = get_record('group', 'id', $groupid1);
        $dbgroupmembers1 = get_records_array('group_member', 'group', $dbgroup1->id);

        $dbgroup2 = get_record('group', 'id', $groupid2);
        $dbgroupmembers2 = get_records_array('group_member', 'group', $dbgroup2->id);

        // temporary hack untl group changes are sorted XXX
        $dbgroup1->open = ($dbgroup1->jointype == 'open' ? 1 : 0);
        $dbgroup1->request = ($dbgroup1->jointype == 'request' ? 1 : 0);
        $dbgroup1->controlled = ($dbgroup1->jointype == 'controlled' ? 1 : 0);
        $dbgroup1->submitpages = (isset($dbgroup1->submitpages) ? $dbgroup1->submitpages : 0);
        $dbgroup1->hidemembers = (isset($dbgroup1->hidemembers) ? $dbgroup1->hidemembers : 0);
        $dbgroup1->invitefriends = (isset($dbgroup1->invitefriends) ? $dbgroup1->invitefriends : 0);
        $dbgroup1->suggestfriends = (isset($dbgroup1->suggestfriends) ? $dbgroup1->suggestfriends : 0);
        $dbgroup1->hidden = (isset($dbgroup1->hidden) ? $dbgroup1->hidden : 0);
        $dbgroup1->hidemembersfrommembers = (isset($dbgroup1->hidemembersfrommembers) ? $dbgroup1->hidemembersfrommembers : 0);
        $dbgroup2->open = ($dbgroup2->jointype == 'open' ? 1 : 0);
        $dbgroup2->request = ($dbgroup2->jointype == 'request' ? 1 : 0);
        $dbgroup2->controlled = ($dbgroup2->jointype == 'controlled' ? 1 : 0);
        $dbgroup2->submitpages = (isset($dbgroup2->submitpages) ? $dbgroup2->submitpages : 0);
        $dbgroup2->hidemembers = (isset($dbgroup2->hidemembers) ? $dbgroup2->hidemembers : 0);
        $dbgroup2->invitefriends = (isset($dbgroup2->invitefriends) ? $dbgroup2->invitefriends : 0);
        $dbgroup2->suggestfriends = (isset($dbgroup2->suggestfriends) ? $dbgroup2->suggestfriends : 0);
        $dbgroup2->hidden = (isset($dbgroup2->hidden) ? $dbgroup2->hidden : 0);
        $dbgroup2->hidemembersfrommembers = (isset($dbgroup2->hidemembersfrommembers) ? $dbgroup2->hidemembersfrommembers : 0);

        //compare DB group with the test data
        //retrieve groups from the DB and check values
        $this->assertEqual($dbgroup1->name, $group1->name);
        $this->assertEqual($dbgroup1->description, $group1->description);
        $this->assertEqual($dbgroup1->grouptype, $group1->grouptype);
        $this->assertEqual($dbgroup1->category, $category->id);
//        $this->assertEqual($dbgroup1->jointype, $group1->jointype);
        $this->assertEqual($dbgroup1->open, $group1->open);
        $this->assertEqual($dbgroup1->request, $group1->request);
        $this->assertEqual($dbgroup1->controlled, $group1->controlled);
        $this->assertEqual($dbgroup1->submitpages, $group1->submitpages);
        $this->assertEqual($dbgroup1->hidemembers, $group1->hidemembers);
        $this->assertEqual($dbgroup1->invitefriends, $group1->invitefriends);
        $this->assertEqual($dbgroup1->suggestfriends, $group1->suggestfriends);
        $this->assertEqual($dbgroup1->hidden, $group1->hidden);
        $this->assertEqual($dbgroup1->hidemembersfrommembers, $group1->hidemembersfrommembers);
        $this->assertEqual($dbgroup1->public, $group1->public);
        $this->assertEqual($dbgroup1->usersautoadded, $group1->usersautoadded);
//        $this->assertEqual($dbgroup1->viewnotify, 1);
        $this->assertEqual(count($dbgroupmembers1), count($group1->members)+1); // current user added as admin

        $this->assertEqual($dbgroup2->name, $group2->name);
        $this->assertEqual($dbgroup2->description, $group2->description);
        $this->assertEqual($dbgroup2->grouptype, $group2->grouptype);
        $this->assertEqual($dbgroup2->category, $category->id);
//        $this->assertEqual($dbgroup2->jointype, $group2->jointype);
        $this->assertEqual($dbgroup2->open, $group2->open);
        $this->assertEqual($dbgroup2->request, $group2->request);
        $this->assertEqual($dbgroup2->controlled, $group2->controlled);
        $this->assertEqual($dbgroup2->submitpages, $group2->submitpages);
        $this->assertEqual($dbgroup2->hidemembers, $group2->hidemembers);
        $this->assertEqual($dbgroup2->invitefriends, $group2->invitefriends);
        $this->assertEqual($dbgroup2->suggestfriends, $group2->suggestfriends);
        $this->assertEqual($dbgroup2->hidden, $group2->hidden);
        $this->assertEqual($dbgroup2->hidemembersfrommembers, $group2->hidemembersfrommembers);
        $this->assertEqual($dbgroup2->public, $group2->public);
        $this->assertEqual($dbgroup2->usersautoadded, $group2->usersautoadded);
//        $this->assertEqual($dbgroup2->viewnotify, 1);
        $this->assertEqual(count($dbgroupmembers2), count($group2->members)+1); // current user added as admin
    }

}
