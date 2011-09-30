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
        $this->testrest = true;
        $this->testxmlrpc = true;
        $this->testsoap = true;

        ////// READ-ONLY DB tests ////
        $this->readonlytests = array(
            'mahara_user_get_users_by_id' => true,
            'mahara_user_get_users' => true,
        );

        ////// WRITE DB tests ////
        $this->writetests = array(
            'mahara_user_create_users' => true,
            'mahara_user_update_users' => true,
            'mahara_user_delete_users' => true,
            'mahara_user_update_favourites' => true,
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
                require_once(get_config('docroot') . "artefact/webservice/rest/lib.php");
                // iterate the token and user auth types
                foreach (array('server', 'simpleserver', 'oauth') as $type) {
                    switch ($type) {
                        case 'server':
                             $restclient = new webservice_rest_client(get_config('wwwroot') . '/artefact/webservice/rest/'.$type.'.php',
                                                                     array('wstoken' => $this->testtoken),
                                                                     $type);
                             break;
                        case 'simpleserver':
                            $restclient = new webservice_rest_client(get_config('wwwroot') . '/artefact/webservice/rest/'.$type.'.php',
                                                                     array('wsusername' => $this->testuser, 'wspassword' => $this->testuser),
                                                                     $type);
                             break;
                        case 'oauth':
                             $restclient = new webservice_rest_client(get_config('wwwroot') . '/artefact/webservice/rest/server.php',
                                                                     array(),
                                                                     $type);
                             $restclient->set_oauth($this->consumer, $this->access_token);
                             break;
                    }
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

    // simple get users by ID
    function mahara_user_get_users_by_id($client) {

        error_log('getting users by id');

        $dbusers = get_records_sql_array('SELECT u.id AS id FROM {usr} u INNER JOIN {auth_instance} ai ON u.authinstance = ai.id WHERE u.deleted = 0 AND ai.institution = \'mahara\'', null);
        $users_in = array();
        foreach ($dbusers as $dbuser) {
            if ($dbuser->id == 0) continue;
            $users_in[] = array('id' => $dbuser->id);
        }
        $function = 'mahara_user_get_users_by_id';

        $params = array('users' => $users_in);

        // standard call
        $users = $client->call($function, $params);
        $this->assertEqual(count($users), count($users_in));

        // JSON call
        $users = $client->call($function, $params, true);
        $this->assertEqual(count($users), count($users_in));
    }


    // simple get all users
    function mahara_user_get_users($client) {

        error_log('getting all users');

        $function = 'mahara_user_get_users';
        $dbusers = get_records_sql_array('SELECT u.id AS id FROM {usr} u INNER JOIN {auth_instance} ai ON u.authinstance = ai.id WHERE u.deleted = 0 AND ai.institution = \'mahara\'', null);
        $userids = array();
        foreach ($dbusers as $dbuser) {
            if ($dbuser->id == 0) continue;
            $userids[] = $dbuser->id;
        }
        $params = array();
        $users = $client->call($function, $params);

        $this->assertEqual(count($users), count($userids));
    }

    // create user test
    function mahara_user_create_users($client) {

        error_log('creating users');

        //Test data
        //a full user: user1
        $user1 = new stdClass();
        $user1->username = 'testusername1';
        $user1->password = 'testpassword1';
        $user1->firstname = 'testfirstname1';
        $user1->lastname = 'testlastname1';
        $user1->email = 'testemail1@hogwarts.school.nz';
        $user1->auth = 'internal';
        $user1->institution = 'mahara';
        $user1->studentid = 'testidnumber1';
        $user1->preferredname = 'Hello World!';
        $user1->city = 'testcity1';
        $user1->country = 'au';
        //a small user: user2
        $user2 = new stdClass();
        $user2->username = 'testusername2';
        $user2->password = 'testpassword2';
        $user2->firstname = 'testfirstname2';
        $user2->lastname = 'testlastname2';
        $user2->email = 'testemail1@hogwarts.school.nz';
        $user2->auth = 'webservice';
        $user2->institution = 'mahara';
        $users = array($user1, $user2);

        //do not run the test if user1 or user2 already exists
        foreach (array($user1->username, $user2->username) as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }

        $function = 'mahara_user_create_users';
        $params = array('users' => $users);
        $resultusers = $client->call($function, $params);

        // store users for deletion at the end
        foreach ($resultusers as $u) {
            $this->created_users[]= $u['id'];
        }
        $this->assertEqual(count($users), count($resultusers));

        //retrieve user1 from the DB and check values
        $dbuser1 = get_record('usr', 'username', $user1->username);
        $this->assertEqual($dbuser1->firstname, $user1->firstname);
        $this->assertEqual($dbuser1->password,
                self::encrypt_password($user1->password, $dbuser1->salt));
        $this->assertEqual($dbuser1->lastname, $user1->lastname);
        $this->assertEqual($dbuser1->email, $user1->email);
        $this->assertEqual($dbuser1->studentid, $user1->studentid);
        $this->assertEqual($dbuser1->preferredname, $user1->preferredname);
        foreach (array('city', 'country') as $field) {
            $artefact = get_profile_field($dbuser1->id, $field);
            $this->assertEqual($artefact, $user1->{$field});
        }

        //retrieve user2 from the DB and check values
        $dbuser2 = get_record('usr', 'username', $user2->username);
        $this->assertEqual($dbuser2->firstname, $user2->firstname);
        $this->assertEqual($dbuser2->password,
                self::encrypt_password($user2->password, $dbuser2->salt));
        $this->assertEqual($dbuser2->lastname, $user2->lastname);
        $this->assertEqual($dbuser2->email, $user2->email);
    }

    // delete user test
    function mahara_user_delete_users($client) {
        global $CFG;

        error_log('deleting users');

        //Set test data
        //a full user: user1
        if (!$authinstance = get_record('auth_instance', 'institution', 'mahara', 'authname', 'webservice')) {
            throw new invalid_parameter_exception('Invalid authentication type: mahara/webservce');
        }
        $institution = new Institution($authinstance->institution);

        //can run this test only if test usernames don't exist
        foreach (array( 'deletetestusername1', 'deletetestusername2') as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }
        db_begin();
        $new_user = new StdClass;
        $new_user->authinstance = $authinstance->id;
        $new_user->username     = 'deletetestusername1';
        $new_user->firstname    = $new_user->username;
        $new_user->lastname     = $new_user->username;
        $new_user->password     = $new_user->username;
        $new_user->email        = $new_user->username.'@hogwarts.school.nz';
        $new_user->passwordchange = 0;
        $new_user->admin        = 0;
        $profilefields = new StdClass;
        $userid = create_user($new_user, $profilefields, $institution, $authinstance);
        db_commit();
        $dbuser1 = get_record('usr', 'username', $new_user->username);
        $this->assertTrue($dbuser1);
        $this->created_users[]= $dbuser1->id;

        db_begin();
        $new_user = new StdClass;
        $new_user->authinstance = $authinstance->id;
        $new_user->username     = 'deletetestusername2';
        $new_user->firstname    = $new_user->username;
        $new_user->lastname     = $new_user->username;
        $new_user->password     = $new_user->username;
        $new_user->email        = $new_user->username.'@hogwarts.school.nz';
        $new_user->passwordchange = 0;
        $new_user->admin        = 0;
        $profilefields = new StdClass;
        $userid = create_user($new_user, $profilefields, $institution, $authinstance);
        db_commit();
        $dbuser2 = get_record('usr', 'username', $new_user->username);
        $this->assertTrue($dbuser2);
        $this->created_users[]= $dbuser2->id;

        //delete the users by webservice
        $function = 'mahara_user_delete_users';
        $params = array('users' => array(array('id' => $dbuser1->id), array('id' => $dbuser2->id)));
        $client->call($function, $params);

        //search for them => TESTS they don't exists
        foreach (array($dbuser1, $dbuser2) as $user) {
            $user = get_record('usr', 'id', $user->id, 'deleted', 0);
            $this->assertTrue(empty($user));
        }
    }


    // update user test
    function mahara_user_update_users($client) {
        global $CFG;

        error_log('updating users');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        //update the test data
        $user1 = new stdClass();
        $user1->id = $dbuser1->id;
        $user1->username = 'veryimprobabletestusername1_updated';
        $user1->password = 'testpassword1_updated';
        $user1->firstname = 'testfirstname1_updated';
        $user1->lastname = 'testlastname1_updated';
        $user1->email = 'testemail1_updated@hogwarts.school.nz';
        $user1->studentid = 'testidnumber1_updated';
        $user1->preferredname = 'Hello World!_updated';
        $user1->city = 'testcity1_updated';
        $user1->country = 'au';
        $user2 = new stdClass();
        $user2->id = $dbuser2->id;
        $user2->username = 'veryimprobabletestusername2_updated';
        $user2->password = 'testpassword2_updated';
        $user2->firstname = 'testfirstname2_updated';
        $user2->lastname = 'testlastname2_updated';
        $user2->email = 'testemail1_updated@hogwarts.school.nz';
        $users = array($user1, $user2);

        //update the users by web service
        $function = 'mahara_user_update_users';
        $params = array('users' => $users);
        $client->call($function, $params);

        //compare DB user with the test data
        $dbuser1 = get_record('usr', 'username', $user1->username);
        $this->assertEqual($dbuser1->firstname, $user1->firstname);
        $this->assertEqual($dbuser1->password,
                self::encrypt_password($user1->password, $dbuser1->salt));
        $this->assertEqual($dbuser1->lastname, $user1->lastname);
        $this->assertEqual($dbuser1->email, $user1->email);
        $this->assertEqual($dbuser1->studentid, $user1->studentid);
        $this->assertEqual($dbuser1->preferredname, $user1->preferredname);
        foreach (array('city', 'country') as $field) {
            $artefact = get_profile_field($dbuser1->id, $field);
            $this->assertEqual($artefact, $user1->{$field});
        }

        $dbuser2 = get_record('usr', 'username', $user2->username);
        $this->assertEqual($dbuser2->firstname, $user2->firstname);
        $this->assertEqual($dbuser2->password,
                self::encrypt_password($user2->password, $dbuser2->salt));
        $this->assertEqual($dbuser2->lastname, $user2->lastname);
        $this->assertEqual($dbuser2->email, $user2->email);
    }


    // update user test
    function mahara_user_update_favourites($client) {
        global $CFG;

        error_log('updating & reading favourites');
        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        //update the test data
        $user1 = new stdClass();
        $user1->id = $dbuser1->id;
        $user1->shortname = 'testshortname1';
        $user1->institution = 'mahara';
        $user1->favourites = array(array('id' => 1), array('username' => $dbuser2->username));
        $user2 = new stdClass();
        $user2->username = $dbuser2->username;
        $user2->shortname = 'testshortname1';
        $user2->institution = 'mahara';
        $user2->favourites = array(array('id' => 1), array('username' => $dbuser1->username));
        $users = array($user1, $user2);

        //update the users by web service
        $function = 'mahara_user_update_favourites';
        $params = array('users' => $users);
        $client->call($function, $params);

        // check the new favourites lists
        $fav1 = self::prune_nasty_zero(get_user_favorites($dbuser1->id, 100, 0));
        $fav2 = self::prune_nasty_zero(get_user_favorites($dbuser2->id, 100, 0));
        $this->assertEqual(count($fav1), count($user1->favourites));
        $this->assertEqual($dbuser2->id, self::find_new_fav($fav1));
        $this->assertEqual(count($fav2), count($user2->favourites));
        $this->assertEqual($dbuser1->id, self::find_new_fav($fav2));

        $function = 'mahara_user_get_favourites';
        $params = array('users' => array(array('shortname' => 'testshortname1', 'userid' => $dbuser1->id),array('shortname' => 'testshortname1', 'userid' => $dbuser2->id)));
        $users = $client->call($function, $params);
        foreach ($users as $user) {
            $favs = self::prune_nasty_zero($user['favourites']);
            $this->assertEqual(count($favs), count($user1->favourites));
            $this->assertEqual($user['shortname'], $user1->shortname);
            $this->assertEqual($user['institution'], $user1->institution);
        }

        // get all favourites
        $function = 'mahara_user_get_all_favourites';
        $params = array('shortname' => 'testshortname1');
        $users = $client->call($function, $params);
        $this->assertTrue(count($users) >= 2);
        foreach ($users as $user) {
            // skip users that we don't know
            if ($user['id'] != $dbuser1->id && $user['id'] != $dbuser2->id) {
                continue;
            }
            $favs = self::prune_nasty_zero($user['favourites']);
            $this->assertEqual(count($favs), count($user1->favourites));
            $this->assertEqual($user['shortname'], $user1->shortname);
            $this->assertEqual($user['institution'], $user1->institution);
        }
    }
}
