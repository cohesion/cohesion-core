<?php
namespace Cohesion\Auth;

interface FacebookUserServiceInterface extends UserServiceInterface {
    public function getFacebookUser($facebookUserId);
    public function createFromFacebookUser($facebookUser);
    public function setFacebookId($facebookUserId);
    public function getFacebookToken();
    public function setFacebookToken($token);
}
