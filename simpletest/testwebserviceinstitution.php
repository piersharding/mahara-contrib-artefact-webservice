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
            'mahara_institution_get_members' => true,
            'mahara_institution_get_requests' => true,
        );

        ////// WRITE DB tests ////
        $this->writetests = array(
            'mahara_institution_add_members' => true,
            'mahara_institution_remove_members' => true,
            'mahara_institution_invite_members' => true,
            'mahara_institution_decline_members' => true,
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
                                    . '/artefact/webservice/rest/'.$type.'.php',
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
                                    . '/artefact/webservice/xmlrpc/'.$type.'.php',
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
                foreach (array('server' => array('wstoken' => $this->testtoken),
                               'simpleserver' => array('wsusername' => $this->testuser, 'wspassword' => $this->testuser),
                               'simpleserver' => array('wsse' => 1)) as $type => $parms) {
                    if (isset($parms['wsse'])) {
                        $soapclient = new webservice_soap_client(get_config('wwwroot')
                                        . '/artefact/webservice/soap/'.$type.'.php', array('wsservice' => $this->servicename),
                                            array("features" => SOAP_WAIT_ONE_WAY_CALLS)); //force SOAP synchronous mode
                                                                                           //when function return null
                        $wsseSoapClient = new webservice_soap_client_wsse(array($soapclient, '_doRequest'), $soapclient->wsdl, $soapclient->getOptions());
                        $wsseSoapClient->__setUsernameToken($this->testuser, $this->testuser);
                        $soapclient->setSoapClient($wsseSoapClient);
                    }
                    else {
                        $soapclient = new webservice_soap_client(get_config('wwwroot')
                                        . '/artefact/webservice/soap/'.$type.'.php', $parms,
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
    function mahara_institution_get_members($client) {
        global $CFG;

        error_log('getting members');
        require_once($CFG->docroot.'/lib/searchlib.php');

        $institution = new Institution('mahara');
        $data = institutional_admin_user_search('', $institution, 0);

        $function = 'mahara_institution_get_members';
        $params = array('institution' => 'mahara');
        $users = $client->call($function, $params);

        $this->assertEqual(count($users), $data['count']);
    }


    // simple get users by ID
    function mahara_institution_get_requests($client) {
        global $CFG;
        error_log('getting requests');
        require_once($CFG->docroot.'/lib/searchlib.php');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $institution = new Institution($this->testinstitution);
        $institution->invite_users(array($dbuser1->id, $dbuser2->id));
        $dbinvites = get_records_array('usr_institution_request', 'institution', $this->testinstitution);

        $function = 'mahara_institution_get_requests';
        $params = array('institution' => $this->testinstitution);
        $users = $client->call($function, $params);

        $this->assertEqual(count($users), count($dbinvites));
    }


    // update user test
    function mahara_institution_invite_members($client) {
        error_log('invite members');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $this->clean_institution();

        //update the users by web service
        $function = 'mahara_institution_invite_members';
        $params = array('institution' => $this->testinstitution, 'users' => array(array('id' => $dbuser1->id), array('id' => $dbuser2->id),));
        $client->call($function, $params);

        $dbinvites = get_records_array('usr_institution_request', 'institution', $this->testinstitution);

        //compare DB user with the test data
        $this->assertEqual(count($params['users']), count($dbinvites));
    }


    // update user test
    function mahara_institution_add_members($client) {
        error_log('add members');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $this->clean_institution();

        // members before
        $dbmembers_before = get_records_array('usr_institution', 'institution', $this->testinstitution);

        //update the users by web service
        $function = 'mahara_institution_add_members';
        $params = array('institution' => $this->testinstitution, 'users' => array(array('id' => $dbuser1->id), array('id' => $dbuser2->id),));
        $client->call($function, $params);

        $dbmembers = get_records_array('usr_institution', 'institution', $this->testinstitution);

        //compare DB user with the test data
        $this->assertEqual(count($params['users']), count($dbmembers));
    }


    // update user test
    function mahara_institution_remove_members($client) {
        error_log('remove members');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $this->clean_institution();

        $institution = new Institution($this->testinstitution);
        $institution->add_members(array($dbuser1->id, $dbuser2->id));
        $dbmembers = get_records_array('usr_institution', 'institution', $this->testinstitution);
        $this->assertEqual(2, count($dbmembers));

        //update the users by web service
        $function = 'mahara_institution_remove_members';
        $params = array('institution' => $this->testinstitution, 'users' => array(array('id' => $dbuser1->id), array('id' => $dbuser2->id),));
        $client->call($function, $params);

        $dbmembers = get_records_array('usr_institution', 'institution', $this->testinstitution);
        $this->assertTrue(empty($dbmembers));
    }


    // update user test
    function mahara_institution_decline_members($client) {
        error_log('decline members');

        //Set test data
        $dbuser1 = $this->create_user1_for_update();
        $dbuser2 = $this->create_user2_for_update();

        $this->clean_institution();

        $institution = new Institution($this->testinstitution);
        $institution->addRequestFromUser($dbuser1);
        $institution->addRequestFromUser($dbuser2);
//        $institution->invite_users(array($dbuser1->id, $dbuser2->id));

//        // XXXXXXXXXXXXXXXXX this could be a hack - not sure if this is due to a bug in $institution->decline_requests
//
//        set_field('usr_institution_request', 'confirmedusr', 1, 'institution', $this->testinstitution, 'usr', $dbuser1->id);
//        set_field('usr_institution_request', 'confirmedusr', 1, 'institution', $this->testinstitution, 'usr', $dbuser2->id);

        $dbinvites = get_records_array('usr_institution_request', 'institution', $this->testinstitution);
        $this->assertEqual(2, count($dbinvites));

        //update the users by web service
        $function = 'mahara_institution_decline_members';
        $params = array('institution' => $this->testinstitution, 'users' => array(array('id' => $dbuser1->id), array('id' => $dbuser2->id),));
        $client->call($function, $params);

        $dbinvites = get_records_array('usr_institution_request', 'institution', $this->testinstitution);
        $this->assertTrue(empty($dbinvites));
    }
}
