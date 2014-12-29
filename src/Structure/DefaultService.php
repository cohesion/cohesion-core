<?php
namespace Cohesion\Structure;

use \Cohesion\Config\Config;
use \Cohesion\Auth\UnauthorisedException;
use \Cohesion\Auth\User;

/**
 * Services contain all the business logic about an object but do not contain
 * any data access logic or object data
 */
abstract class DefaultService extends Service {
    protected $config;
    protected $dao;
    protected $user;
    protected $admin;

    public function __construct(Config $config = null, DAO $dao = null, User $user = null) {
        $this->config = $config;
        $this->dao = $dao;
        $this->setUser($user);
    }

    public function setUser(User $user = null) {
        if ($user && (!$this->user || $this->admin)) {
            if (!$this->user) {
                if ($user->isAdmin()) {
                    $this->admin = $user;
                }
            }
            $this->user = $user;
        } else if ($user) {
            throw new UnauthorisedException('Only admins can set the user');
        }
    }
}
