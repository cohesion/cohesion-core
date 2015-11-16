<?php
namespace Cohesion\Auth;

use \Cohesion\util\Input;

class HTTPAuth extends Auth {

    protected $input;

    public function __construct(UserServiceInterface $userService) {
        parent::__construct($userService);
        if (isset($_SESSION['user_id']) && isset($_SESSION['auth_hash'])) {
            if ($this->validateAuthHash($_SESSION['user_id'], $_SESSION['auth_hash'])) {
                $this->user = $this->userService->getUserById($_SESSION['user_id']);
            }
        }
        $this->input = new Input($_REQUEST ?: array());
    }

    public function login() {
        if ($this->isLoggedIn()) {
            return true;
        }
        $errors = array();
        if (!$this->input->required(array('username', 'password'), $errors)) {
            throw new UnauthorisedException(implode('. ', $errors));
        }
        if ($this->validateCredentials($this->input->get('username'), $this->input->get('password'))) {
            $user = $this->userService->getUserByUsername($this->input->get('username'));
            $this->setUserLoggedIn($user);
            return true;
        }
        return false;
    }

    public function logout() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['auth_hash'])) {
            $this->userService->invalidateAuthHash($_SESSION['user_id'], $_SESSION['auth_hash']);
        }
        $this->user = null;
        session_destroy();
    }

    public function setUserLoggedIn($user) {
        $hash = $this->generateHash();
        $this->userService->setAuthHash($user, $hash);
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['auth_hash'] = strtoupper(bin2hex($hash));
        $this->user = $user;
    }
}

