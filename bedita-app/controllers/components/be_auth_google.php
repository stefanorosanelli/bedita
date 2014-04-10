<?php
/*-----8<--------------------------------------------------------------------
 *
* BEdita - a semantic content management framework
*
* Copyright 2013 ChannelWeb Srl, Chialab Srl
*
*------------------------------------------------------------------->8-----
*/

if(class_exists('BeAuthComponent') != true) {
    require(BEDITA_CORE_PATH . DS . "controllers" . DS . 'components' . DS . 'be_auth.php');
}

if(class_exists('Google_Client') != true) {
    set_include_path(BEDITA_CORE_PATH . DS . "vendors" . DS . 'google' . DS . 'src' . PATH_SEPARATOR . get_include_path());
    require_once('Google' . DS . 'Client.php');
}
if(class_exists('Google_Service_Oauth2') != true) {
    set_include_path(BEDITA_CORE_PATH . DS . "vendors" . DS . 'google' . DS . 'src' . PATH_SEPARATOR . get_include_path());
    require_once('Google' . DS . 'Service' . DS . 'OAuth2.php');
}

/**
 * Google User auth component
*/
class BeAuthGoogleComponent extends BeAuthComponent{
    var $components = array('Transaction');
    var $uses = array('Image', 'Card');

    public $userAuth = 'google';

    protected $params = null;
    protected $vendorController = null;
    protected $oauthTokens = null;
    protected $accessTokens = null;
    protected $userIdPrefix = 'google-';
    public $disabled = false;

    public function startup(&$controller=null) {
        $this->loadComponents();
        $this->controller = &$controller;
        $this->Session = &$controller->Session;

        $this->params = Configure::read("extAuthParams");

        if (isset( $this->params['google'] ) && isset( $this->params['google']['kies'] )) {
            $this->vendorController = new Google_Client();
            $this->vendorController->setClientId($this->params['google']['kies']['clientId']);
            $this->vendorController->setClientSecret($this->params['google']['kies']['clientSecret']);
            $this->vendorController->setRedirectUri($this->getCurrentUrl());
            foreach ($this->params['google']['scopes'] as $scope) {
               $this->vendorController->addScope($scope);
            }

            if (isset($_GET['code']) && !$this->Session->check('googleAccessToken') && $this->Session->check('googleRequestedToken')) {
                $this->vendorController->authenticate($_GET['code']);
                $this->Session->write('googleAccessToken', $this->vendorController->getAccessToken());
            }

            if ($this->Session->check('googleAccessToken')) {
                $this->vendorController->setAccessToken($this->Session->read('googleAccessToken'));
            } else {
                $this->vendorController->setRedirectUri($this->getCurrentUrl());
                return false;
            }
        }

        if($this->checkSessionKey()) {
            $this->user = $this->Session->read($this->sessionKey);
        }
        
        $this->controller->set($this->sessionKey, $this->user);
    }

    protected function checkSessionKey() {
        if (isset( $this->vendorController )) {
            
            $profile = $this->loadProfile();
            if ($profile) {
                $this->createUser($profile);
                if ($this->login()) {
                    return true;
                }
            } else {
                $this->log("Twitter login failed");
                return false;
            }
            return false;
        } else {
            return false;
        }
    }

    public function login() {
        $policy = $this->Session->read($this->sessionKey . 'Policy');
        $authGroupName = $this->Session->read($this->sessionKey . 'AuthGroupName');
        $userid = null;

        if (!isset( $this->vendorController )) {
            return;
        }

        if ($this->Session->check('googleAccessToken')) {
            $profile = $this->loadProfile();
            $userid = $this->userIdPrefix . $profile->id;
        }

        //get the user
        if ($userid) {
            //BE user
            $user = ClassRegistry::init('User');
            $user->containLevel("default");
            $u = $user->findByUserid($userid);
            if(!$this->loginPolicy($userid, $u, $policy, $authGroupName)) {
                return false;
            }
            return true;

        } else { 
            //get tokens
            $this->loginUrl();
        }
    }

    public function logout() {
        $this->vendorController->revokeToken();
        $this->Session->del('googleAccessToken');
    }

    protected function loginUrl() {
        $this->Session->write('googleRequestedToken', true);
        $url = $this->vendorController->createAuthUrl();
        $this->controller->redirect($url);
    }

    public function getUser() {
        return $this->user;
    }

    public function loadProfile() {
        if ($this->Session->check('googleAccessToken')) {
            $oauth2 = new Google_Service_Oauth2($this->vendorController);
            $profile = $oauth2->userinfo->get();
            if (property_exists($profile, 'id')) {
                return $profile;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function createUser($profile = null) {
        if ($profile == null) {
            $profile = $this->loadProfile();
        }

        //create the data array
        $res = array();
        $res['User'] = array(
            'userid' => $this->userIdPrefix . $profile->id,
            'realname' => $profile->name,
            'email' => $profile->email,
            'auth_type' => 'google',
            'auth_params' => array(
                'userid' => $profile->id
            )
        );

        $groups = array();
        if (!empty($this->params['google']['groups'])) {
            foreach ($this->params['google']['groups'] as $key => $value) {
                array_push($groups, $value);
            }
        }

        $res['Groups'] = $groups;

        //create the BE user
        $user = ClassRegistry::init('User');
        $user->containLevel("minimum");
        $u = $user->findByUserid($res['User']['userid']);
        if(!empty($u["User"])) {
            return $u;
        }

        $this->userGroupModel($res, $groups);
        
        $user->create();
        if(!$user->save($res)) {
            throw new BeditaException(__("Error saving user", true), $user->validationErrors);
        }
 
        $u = $user->findByUserid($res['User']['userid']);
        if(!empty($u["User"])) {
            if (!empty($this->params['google']['createCard']) && $this->params['google']['createCard']) {
                $this->createCard($u, $profile);
            }
            return $u;
        } else {
            return null;
        }
    }

    public function createCard($u, $profile = null) {
        $res = array();
        
        if ($profile == null) {
            $profile = $this->loadProfile();
            if ($profile == null) {
                return false;
            }
        }

        $res = array(
            'title' => $profile->name,
            'name' => $profile->givenName,
            'surname' => $profile->familyName,
            'email' => $profile->email,
            'gender' => $profile->gender,
            'avatar' => $profile->picture
        );

        $card = ClassRegistry::init("ObjectUser")->find("first", array(
            "conditions" => array("user_id" => $u['User']['id'], "switch" => "card" )
        ));

        $data = array(
            "title" => "",
            "name" => "",
            "surname" => "",
            "birthdate" => "",
            "person_title" => "",
            "gender" => "",
            "status" => "on",
            "ObjectUser" =>  array(
                    "card" => array(
                        0 => array(
                            "user_id" => $u['User']['id']
                        )
                    )
                )
        );

        $data = array_merge($data, $res);

        $avatarId = null;
        if (!empty($data['avatar'])) {
            $avatar = $this->uploadAvatarByUrl($data);
            $avatarId = $avatar->id;
            if ($avatarId) {
                $data['RelatedObject'] = array(
                    'attach' => array()
                );

                $data['RelatedObject']['attach'][$avatarId] = array(
                    'id' => $avatarId
                );
            }
        }

        $this->data = $data;

        $this->Transaction->begin();

        $cardModel = ClassRegistry::init("Card");
        if (!$cardModel->save($this->data)) {
            throw new BeditaRuntimeException(__("Error saving user data", true), $cardModel->validationErrors);
        }

        $this->Transaction->commit();
 
        return $cardModel;
    }

    protected function getCurrentUrl() {
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $parts = parse_url($currentUrl);

        // use port if non default
        $port = isset($parts['port']) && (($protocol === 'http://' && $parts['port'] !== 80) || ($protocol === 'https://' && $parts['port'] !== 443)) ? ':' . $parts['port'] : '';

        // rebuild
        return $protocol . $parts['host'] . $port . $parts['path'];
    }

    protected function uploadAvatarByUrl($userData) {
        $this->data = array(
            'title' => $userData['title'] . '\'s avatar',
            'uri' => $userData['avatar'],
            'status' => 'on'
        );
        $this->Transaction->begin();

        $mediaModel = ClassRegistry::init("Image");
        if (!$mediaModel->save($this->data)) {
            throw new BeditaRuntimeException(__("Error saving avatar data", true), $mediaModel->validationErrors);
        }
        $this->Transaction->commit();
        return $mediaModel;
    }
}
?>