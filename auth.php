<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');

class auth_plugin_vettrak extends auth_plugin_base {

    public $client;
    public $token;
    public static $wsdl;
    public static $username;
    public static $password;

    public function __construct() {

        global $CFG;

        require_once($CFG->dirroot . '/auth/vettrak/vendor/autoload.php');

        $this->log = new Monolog\Logger('auth_vettrak');
        $this->log->pushHandler(new Monolog\Handler\StreamHandler($CFG->dataroot . '/auth_vettrak.log',  Monolog\Logger::DEBUG));
        $this->log->pushHandler(new Monolog\Handler\StreamHandler('php://stdout',  Monolog\Logger::DEBUG));

        $this->authtype = 'vettrak';
        $this->config = get_config('auth_vettrak');
        self::$wsdl = $this->config->webservice;
        self::$username = $this->config->username;
        self::$password = $this->config->password;
        $this->vettrak_client();
        $this->token = $this->vettrak_get_token();
    }

    //sync users in vettrak
    public function sync_users() {
        global $DB;

        $lastRun = $DB->get_record_sql('SELECT id, component, classname, lastruntime
                                FROM {task_scheduled}
                                WHERE classname = ?', array('\auth_vettrak\task\syncUsers'));

        $date = new DateTime("@$lastRun->lastruntime");
        $date = $date->format('c');

        // Initialise filter criteria here.
        $filterCriteria = array(
            array(
                'Field' => 'WebPublishFlag',
                'Operator' => 'Equals',
                'Value' => 'Y'
            ),
            array(
                'Field' => 'ModifiedDate',
                'Operator' => 'GreaterThanOrEqualTo',
                'Value' => $date
            )
        );

        // Force a full sync if it is set.
        if($this->config->forcefullsync){
            $filterCriteria = array(
                array(
                    'Field' => 'WebPublishFlag',
                    'Operator' => 'Equals',
                    'Value' => 'Y'
                )
            );
            // Reset the config here.
            set_config('forcefullsync', 0,'auth_vettrak');
        }

        $query = array(
            'token' => $this->token,
            'entityName' => 'Client',
            'filterCriteria' => $filterCriteria,
            'returnFields' => array(
                array(
                    'Field' => 'ClientCode'
                )
            )
        );

        $WebPublishedClients = $this->client->QueryAdditionalData($query);

        $x = $WebPublishedClients->QueryAdditionalDataResult->Values->ArrayOfString;
        $x = count($x);
        mtrace('Counting: ' . $x . ' web published clients');

        foreach ($WebPublishedClients->QueryAdditionalDataResult->Values->ArrayOfString as $key => $client) {

            $cliecode = $client->string;

            if (is_null($cliecode)) {
                $cliecode = $client;
            }

            try {

                $client = $this->client->GetClientDetails(array(
                        'sToken' => $this->token,
                        'sClie_Code' => $cliecode
                    )
                );

            } catch (Exception $e) {
                mtrace('Unable to fetch!');
                continue;
            }

            if ($client->GetClientDetailsResult->Auth->ID == '-1') {
                mtrace('Unable to Identify User. ' . $cliecode . ' Skipping.');
                continue;
            }

            try {
                $this->upsert_client($client);
            } catch (Exception $e) {
                mtrace('Fatal Error uploading account!');
                mtrace(print_r($client, true));
                print_r($e->getMessage());
            }

        }
    }

    public function upsert_client($client) {
        global $DB, $CFG;

        $client = $client->GetClientDetailsResult->ClieDetail;

        $this->log->addInfo('Upserting Client', array($client->Clie_Code));

        if ($client->Clie_Code == 'System') { // Skip that system account!
            $this->log->addInfo('Skipping System Account');
            return;
        }

        require_once($CFG->dirroot . '/user/lib.php');

        $user = new stdClass();
        $user->auth = $this->authtype;
        $user->confirmed = 1;
        $user->mnethostid = 1;

        if ($this->config->user_internal_password_management) {
            if ($this->config->user_internal_password_default) {
                $user->password = $this->config->user_internal_password_default;
            }
        }

        $query = array(
            'token' => $this->token,
            'entityName' => 'Client',
            'filterCriteria' => array(
                array(
                    'Field' => 'ClientCode',
                    'Operator' => 'Equals',
                    'Value' => $client->Clie_Code
                )
            ),
            'returnFields' => array(
                array(
                    'Field' => 'ActiveFlag'
                )
            )
        );

        $suspendStatus = 0;
        $activeString = 'Y';

        $WebPublishedClient = $this->client->QueryAdditionalData($query);
        foreach ($WebPublishedClient->QueryAdditionalDataResult->Values->ArrayOfString as $key => $value) {
            $activeString = $value;

            if (is_null($activeString)) {
                $activeString = 'Y';
            }
        }

        if ($activeString == 'N') {
            $suspendStatus = 1;
        }
        $user->deleted = 0;
        $user->suspended = $suspendStatus;
        $user->firstname = $client->Clie_Given;
        $user->city = isset($client->Stat_RShortName) && !empty($client->Stat_RShortName) ? $client->Stat_RShortName : '';

        $user->lastname = $client->Clie_Surname;
        $user->idnumber = $client->Clie_Code;

        if (isset($client->Clie_Username) && !empty($client->Clie_Username)) {
            $user->username = strtolower($client->Clie_Username);
        }

        //Fill empty fields with default value.
        if(!empty($this->config->defaultvaluesempty) && empty(trim($user->firstname))){
            $user->firstname = $this->config->defaultvaluesempty;
        }

        if(!empty($this->config->defaultvaluesempty) && empty(trim($user->lastname))){
            $user->lastname = $this->config->defaultvaluesempty;
        }

        if ($this->config->user_synchronisation_usernameisexternaldebtorcode) {

            $ExternalDebtorCode = $this->client->QueryAdditionalData(array(
                'token' => $this->token,
                'entityName' => 'Client',
                'filterCriteria' => array(
                    array(
                        'Field' => 'ClientCode',
                        'Operator' => 'Equals',
                        'Value' => $client->Clie_Code
                    ),
                ),
                'returnFields' => array(
                    array(
                        'Field' => 'ExternalDebtorCode'
                    )
                )
            ));

            $ExternalDebtorCode = @$ExternalDebtorCode->QueryAdditionalDataResult->Values->ArrayOfString->string;

            if (!empty($ExternalDebtorCode)) {
                $user->username = strtolower($ExternalDebtorCode);
            } else {
                mtrace('Unable to identify External Debtor Code, Skipping ' . $client->Clie_Code);
                return;
            }
        } else {
            $user->username = strtolower($client->Clie_Username);
        }

        if (empty($user->username)) {
            mtrace('No Client Username set for: , Skipping ' . $client->Clie_Code);
        }

        $user->email = $this->config->defaultemailifnotset;

        if (isset($client->Clie_Email) && !empty($client->Clie_Email)) {
            $user->email = $client->Clie_Email;
        }
        if (isset($client->Clie_MobilePhone) && !empty($client->Clie_MobilePhone)) {
            $user->phone2 = $client->Clie_MobilePhone;
        }

        $existingUser = false;

        if (@$this->config->user_synchronisation_matchusernameoridnumber) {
            $existingUser = $DB->get_record_sql("SELECT * FROM {user} U WHERE U.username = ? OR U.idnumber = ? OR U.idnumber = ? ", array($user->username, $user->idnumber, $client->Clie_Code));
        } else {
            $existingUser = $DB->get_record('user', array('idnumber' => $client->Clie_Code));
        }

        if ($existingUser) {

            unset($user->password);
            $this->log->addInfo('Updating Existing Client', array($client->Clie_Code));
            $user->id = $existingUser->id;
            user_update_user($user, false, false);

            // Trigger event.
            $event_data = array(
                'objectid' => $user->id,
                'userid' => $user->id,
                'context' => context_system::instance(),
            );

            $event = \auth_vettrak\event\auth_vettrak_user_updated::create($event_data);
            $event->trigger();
        } else {
            $updatePasswordOnAccountCreation = false;
            if ($this->config->user_internal_password_management) {
                if ($this->config->user_internal_password_default) {
                    $updatePasswordOnAccountCreation = true;
                }
            }
            $this->log->addInfo('Creating New Client', array($client->Clie_Code));
            $userid = user_create_user($user, $updatePasswordOnAccountCreation, false);
            $user->id = $userid;
            set_user_preference('auth_forcepasswordchange', true, $user->id);

            // Trigger event.
            $event_data = array(
                'objectid' => $userid,
                'userid' => $userid,
                'context' => context_system::instance(),
            );

            $event = \auth_vettrak\event\auth_vettrak_add_user::create($event_data);
            $event->trigger();
        }
        $this->log->addInfo('Successfully processed client!', array($client->Clie_Code));
    }

    private function parseAdditionalData($additionaldata) {
        $rows = array();
        $fields = $additionaldata->QueryAdditionalDataResult->Fields->string;
        $data = $additionaldata->QueryAdditionalDataResult->Values->ArrayOfString;
        foreach ($data as $d) {
            $id = $d['string'][0];
            $tmpobj = new stdClass;
            foreach ($d['string'] as $dkey => $dvalue) {
                $tmpobj->{$fields[$dkey]} = $dvalue;
            }
            $rows[$id] = $tmpobj;
        }
        return $rows;
    }

    public function fetchEnrolledUnits() {
        mtrace('Fetching Enrolled Units');

        $additionaldata = $this->client->QueryAdditionalData(array(
                'token' => $this->token,
                'entityName' => 'EnrolledUnit'
            )
        );

        return $this->parseAdditionalData($additionaldata);

    }

    public function fetchClients() {

        $additionaldata = $this->client->QueryAdditionalData(array(
                'token' => $this->token,
                'entityName' => 'Client'
            )
        );

        return $this->parseAdditionalData($additionaldata);

    }

    public function user_login($username, $password) {

        global $CFG, $DB, $USER;

        if ($this->config->user_internal_password_management) {

            if (!$user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id))) {
                return false;
            }
            if (!validate_internal_user_password($user, $password)) {
                return false;
            }
            if ($password === 'changeme') {
                // force the change - this is deprecated and it makes sense only for manual auth,
                // because most other plugins can not change password easily or
                // passwords are always specified by users
                set_user_preference('auth_forcepasswordchange', true, $user->id);
            }
            return true;

        } else {
            $res = $this->auth_vettrak_DoesUsernamePasswordExist($username, $password);

            if (!$res) {
                return false;
            }
            $_SESSION['vettrak_identifier'] = $res;

            return true;

        }

    }

    public function auth_vettrak_DoesUsernamePasswordExist($username, $password) {

        $DoesUsernamePasswordExist = $this->client->DoesUsernamePasswordExist(array(
                'sToken' => $this->token,
                'sUsername' => $username,
                'sPassword' => $password
            )
        );

        if ($DoesUsernamePasswordExist->DoesUsernamePasswordExistResult->ID == '-1') {
            return false;
        } else {
            return $DoesUsernamePasswordExist->DoesUsernamePasswordExistResult->Identifier;
        }

    }

    public function vettrak_client() {

        $clientparams = array(
            'location' => self::$wsdl,
            'cache_wsdl' => WSDL_CACHE_NONE
        );
        // Added this check for fresh installations without it defined.
        if(!empty(self::$wsdl)){
            try {
                $client = new SoapClient(self::$wsdl, $clientparams);
                $this->client = $client;
            } catch (\SoapFault $e) {
                die("vettrak Client construction error: {$e}\n");
            }
        }
    }

    public function vettrak_sync_all_users() {

        $plugin = enrol_get_plugin('vettrak');

        $cache = cache::make('auth_vettrak', 'vettrak');

        $last_sync_date = $cache->get('last_user_sync');

        if ($last_sync_date === false) {
            $last_sync_date = new DateTime();
            $last_sync_date->setTimestamp(1372636800);
            // $last_sync_date = $last_sync_date->format('U');
        } else {
            $lsd = $last_sync_date;
            $last_sync_date = new DateTime();
            $last_sync_date->setTimestamp($last_sync_date);
        }

        $GetAccessibleClients = $this->client->GetAccessibleClients(array(
                'token' => $this->token,
                'clientCode' => auth_plugin_vettrak::$username,
                'sinceDate' => $last_sync_date->format('c')
            )
        );

        foreach ($GetAccessibleClients->GetAccessibleClientsResult->ClieList->TClie as $Client) {
            $ClientDetails = $this->vettrak_getclientdetails($Client->Clie_Code);
            $user = $this->vettrak_upsert_userdetails($ClientDetails);
            $plugin->vettrak_enrol($user);

        }

        $result = $cache->set('last_user_sync', $last_sync_date->format('U'));
    }

    public function vettrak_upsert_userdetails($ClientDetails) {

        global $DB, $CFG;

        require_once($CFG->dirroot . '/user/lib.php');

        $ExistingUser = $DB->get_record('user', array(
            'auth' => 'vettrak',
            'idnumber' => $ClientDetails->Clie_Code,
            'username' => trim(core_text::strtolower($ClientDetails->Clie_Username))
            )
        );

        if (!$ExistingUser) {
            $user = new stdClass();
            $user->confirmed  = 1;
            $user->auth       = $this->authtype;
            $user->mnethostid = $CFG->mnet_localhost_id;
            $user->idnumber = $ClientDetails->Clie_Code;

            $user->username = trim(core_text::strtolower($ClientDetails->Clie_Username));
            $user->firstname = $ClientDetails->Clie_Given;
            $user->lastname = $ClientDetails->Clie_Surname;
            $user->phone2 = $ClientDetails->Clie_MobilePhone;

            $user->city = @$ClientDetails->Clie_PCity;
            $user->country = 'AU';

            if (!empty($ClientDetails->Clie_Email)) {
                $user->email = $ClientDetails->Clie_Email;
            } else {
                $user->email = 'changeme@test.com';
            }

            $user->lang = $CFG->lang;
            $user->calendartype = $CFG->calendartype;
            $user->password = "Learning-1";

            $id = user_create_user($user, true, false);
            $user->id = $id;
            mtrace('Creating account: ' . $user->username);
            $ExistingUser = $user;
        } else {
            $ExistingUser->username = trim(core_text::strtolower($ClientDetails->Clie_Username));
            $ExistingUser->firstname = $ClientDetails->Clie_Given;
            $ExistingUser->lastname = $ClientDetails->Clie_Surname;
            $ExistingUser->phone2 = $ClientDetails->Clie_MobilePhone;
            if (!empty($ClientDetails->Clie_Email)) {
                $ExistingUser->email = $ClientDetails->Clie_Email;
            }
            $ExistingUser->city = $ClientDetails->Clie_PCity;
            $ExistingUser->country = 'AU';
            user_update_user($ExistingUser, false);
        }

        return $ExistingUser;
    }

    public function vettrak_getclientdetails($clientcode) {

        $GetClientDetails = $this->client->GetClientDetails(array(
                'sToken' => $this->token,
                'sClie_Code' => $clientcode,
            )
        );

        return $GetClientDetails->GetClientDetailsResult->ClieDetail;

    }

    public function vettrak_getclientextendeddetails($clientcode) {

        $x = new DateTime();
        $x->setTimestamp(1290137643);
        $x = $x->format('c');
        $y = new DateTime();
        $y->setTimestamp(time());
        $y = $y->format('c');

        $GetClientExtendedDetails = $this->client->call('GetClientExtendedDetails', array(
                'sToken' => $this->token,
                'sClie_Code' => $clientcode,
                'xsdStart' => $x,
                'xsdEnd' => $y
            )
        );

        return $GetClientExtendedDetails;
    }

    public function vettrak_get_token() {

        $token_cache = cache::make('auth_vettrak', 'vettrak_token');

        $token = $token_cache->get('token');

        // Added the null check for an unitialized client.
        if ($token == false && $this->client != null) {
            $authobject = array(
                'sUsername' => self::$username,
                'sPassword' => self::$password
            );
            $token = $this->client->ValidateClient($authobject);

            $tkn = $token->ValidateClientResult->Token;
            $token_cache->set('token', $tkn);
            return $tkn;
        } else {
            return $token;
        }
    }

    public function get_userinfo($username) {

        $GetClientDetails = $this->client->GetClientDetails(array(
                'sToken' => $this->token,
                'sClie_Code' => $_SESSION['vettrak_identifier']
            )
        );

        if (isset($GetClientDetails->GetClientDetailsResult->ClieDetail)) {
                $userArrayObject = array();
                $userArrayObject['firstname'] = $GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_Given;
                $userArrayObject['lastname'] = $GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_Surname;
                $userArrayObject['idnumber'] = $GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_Code;

                if (!empty($GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_Email)) {
                    $userArrayObject['email'] = $GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_Email;
                } else {
                    $userArrayObject['email'] = 'changeme@test.com';
                }
                $userArrayObject['phone2'] = $GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_MobilePhone;
                $userArrayObject['city'] = $GetClientDetails->GetClientDetailsResult->ClieDetail->Clie_PCity;
                $userArrayObject['country'] = 'AU';
                return $userArrayObject;
        } else {
            return array();
        }
    }

    function user_update_password($user, $newpassword) {

        if ($this->config->user_internal_password_management) {
            $user = get_complete_user_data('id', $user->id);
            set_user_preference('auth_manual_passwordupdatetime', time(), $user->id);
            return update_internal_user_password($user, $newpassword);
        } else {
            $user = get_complete_user_data('id',$user->id);
            // This will also update the stored hash to the latest algorithm
            // if the existing hash is using an out-of-date algorithm (or the
            // legacy md5 algorithm).
            $Updatepassword = $this->client->UpdateClientUsernamePassword(array(
                    'sToken' => $this->token,
                    'sClie_Code' => $user->idnumber,
                    'sUsername' => $user->username,
                    'sPassword' => $newpassword
                )
            );
            return $Updatepassword->UpdateClientUsernamePasswordResult->Status;
        }
    }

    function is_internal() {
        if ($this->config->user_internal_password_management) {
            return true;
        } else {
            return false;
        }
    }

    function can_change_password() {
        return true;
    }

    function change_password_url() {
        return null;
    }

    function can_reset_password() {
        return true;
    }

    function user_confirm($username, $confirmsecret = null) {
        return AUTH_CONFIRM_ERROR;
    }
}
