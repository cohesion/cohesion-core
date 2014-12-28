<?php
namespace Cohesion\Auth;

use \Cohesion\util\Input;

class NoAuth extends Auth {

    public function __construct(UserServiceInterface $userService = null) {
        parent::__construct($userService);
    }

    public function login() {
        return false;
    }

    public function logout() {
        return false;
    }
}

