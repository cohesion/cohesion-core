<?php
namespace Cohesion\Auth;

interface User {
    public function isAdmin();

    public function checkPassword($password);
}
