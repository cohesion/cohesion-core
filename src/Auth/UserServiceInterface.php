<?php
namespace Cohesion\Auth;

interface UserServiceInterface {
    public function getUserById($id);
    public function getUserByUsername($username);
    public function setAuthHash(User $user, $hash);
    public function getAuthHash($userId, $hash);
    public function invalidateAuthHash($userId, $hash);
    public function setUser(User $user);
}
