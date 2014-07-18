<?php
namespace Cohesion\Auth;

class AdminAuth extends Auth {
    public function __construct() {
        parent::__construct();
        $this->user = $this->userService->getUserByUsername('admin');
        if (!$this->user) {
            throw new LogicException("You must have an 'admin' user to be able to use the Admin Auth");
        }
    }

    public function login() {
        throw new LogicException('Unable to login using the Admin Auth class.');
    }

    public function logout() {
        throw new LogicException('Unable to logout using the Admin Auth class.');
    }
}

