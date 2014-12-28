<?php
namespace Cohesion\Auth;

use \Cohesion\Structure\Factory\ServiceFactory;

abstract class Auth {
    protected $user;
    protected $userService;

    const AUTH_HASH_SIZE = 32;

    public function __construct(UserServiceInterface $userService = null) {
        $this->userService = $userService;
    }

    public function isLoggedIn() {
        return $this->user != null;
    }

    public abstract function login();

    public abstract function logout();

    public function getUser() {
        return $this->user;
    }

    protected function validateCredentials($username, $password) {
        if ($this->userService) {
            $user = $this->userService->getUserByUsername($username);
            if ($user && $user->checkPassword($password)) {
                $this->user = $user;
                return true;
            }
        }
        return false;
    }

    protected function validateAuthHash($userId, $hash) {
        if ($this->userService) {
            return $this->userService->getAuthHash($userId, $hash);
        }
        return false;
    }

    protected function generateHash() {
        return openssl_random_pseudo_bytes(self::AUTH_HASH_SIZE);
    }
}
