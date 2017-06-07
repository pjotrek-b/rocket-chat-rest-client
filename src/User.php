<?php

namespace RocketChat;

use Httpful\Request;
use RocketChat\Client;

class User extends Client {
    public $username;
    private $password;
    public $id;
    public $name;
    public $email;
    public $active = true;
    public $verified = true;
    public $roles = ['user'];

    public $livechatAgent = null;
    public $livechatManager = null;
    //    public $livechatDepartments = [];

    public $remoteData;

    public function __construct($username=null, $password=null, $fields = array()){
        parent::__construct();

        if (!empty($username)) {
            if(is_array($username)) {
                $fields = $username;
            } else {
                $fields['username'] = $username;
                $fields['password'] = $password;
            }
        }

        $this->setData($fields);
    }

    /**
     * Set data for user properties
     * @param array $data
     * @return The populated User object or False if $data was empty.
     */
    public function setData(array $data)
    {
        if (empty($data)) return false;

        foreach($data as $field => $value) {
            if(!property_exists($this, $field)) continue;
            $this->{$field} = $value;
        }
        return $this;
    }

    public function setRemoteData($data) {
        $this->remoteData = $data;

        $this->id = $this->remoteData->_id;
        $this->username = $this->remoteData->username;
        $this->email = $this->remoteData->emails[0]->address;
        $this->name = $this->remoteData->name;
        $this->active = $this->remoteData->active;
        $this->roles = $this->remoteData->roles;
    }

    /**
     * Authenticate with the REST API.
     */
    public function login($save_auth = true) {
        $response = Request::post( $this->api . 'login' )
            ->body(array( 'user' => $this->username, 'password' => $this->password ))
            ->send();

        if( $response->code == 200 && isset($response->body->status) && $response->body->status == 'success' ) {
            if( $save_auth) {
                // save auth token for future requests
                $tmp = Request::init()
                    ->addHeader('X-Auth-Token', $response->body->data->authToken)
                    ->addHeader('X-User-Id', $response->body->data->userId);
                Request::ini( $tmp );
            }
            $this->id = $response->body->data->userId;
            return true;
        } else {
            $this->lastError = $response->body->message;
            return false;
        }
    }

    /**
     * Gets a user’s information, limited to the caller’s permissions.
     */
    public function info() {
        $response = Request::get( $this->api . 'users.info?userId=' . $this->id )->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true )
        {
            $this->setRemoteData($response->body->user);
            return $response->body;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Create a new user.
     */
    public function create() {
        $response = Request::post( $this->api . 'users.create' )
            ->body(array(
                'name' => $this->name,
                'email' => $this->email,
                'username' => $this->username,
                'password' => $this->password,
                'roles' => $this->roles,
                'verified' => $this->verified,
            ))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $this->setRemoteData($response->body->user);
            return $this->remoteData;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Update user.
     */
    public function update($data)
    {
        $this->setData($data);

        $requestBody = array(
            'userId' => $this->id,
            'data' => array(
                'name' => $this->name,
                'email' => $this->email,
                'username' => $this->username,
                'roles' => $this->roles,
                'active' => $this->active,
                'verified' => $this->verified,
            ),
        );

        if(isset($data['password'])) {
            $requestBody['data']['password'] = $data['password'];
        }

        $response = Request::post( $this->api . 'users.update' )
            ->body($requestBody)
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $this->setRemoteData($response->body->user);
            return $this->remoteData;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Deletes an existing user.
     */
    public function delete() {

        // get user ID if needed
        if( !isset($this->id) ){
            $this->me();
        }
        $response = Request::post( $this->api . 'users.delete' )
            ->body(array('userId' => $this->id))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return true;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }


    /**
     * Get user channels
     */
    public function getChannels($update = false) {
        $result = [];
        if(empty($this->username)) return $result;

        $channels = $this->getAllChannels($update);
        foreach($channels as $channel) {
            $isMember = false;
            foreach($channel->members as $channelMember) {
                if($this->username != $channelMember->username) continue;
                $isMember = true;
            }
            if(!$isMember) continue;
            $result[$channel->id] = $channel->name;
        }

        return $result;
    }

    /**
     * Get user channels
     */
    public function getLivechatDepartments($update = false) {
        $result = [];
        if(empty($this->username)) return $result;

        $livechatDepartments = $this->getAllLivechatDepartments($update);

        foreach($livechatDepartments as $livechatDepartment) {
            $isMember = false;

            //load info if not loaded
            if(count($livechatDepartment->agents)) {
                $livechatDepartment->loadInfo();
            }

            $isMember = isset($livechatDepartment->agents[$this->username]);
            if(!$isMember) continue;

            $result[$livechatDepartment->id] = $livechatDepartment;
        }

        return $result;
    }
}
