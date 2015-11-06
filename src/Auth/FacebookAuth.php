<?php
namespace Cohesion\Auth;

use \Cohesion\Config\Config;
use \Cohesion\Util\External\Facebook\Facebook;
use \Cohesion\Util\Input;

class FacebookAuth extends HTTPAuth {

    protected $appId;
    protected $secret;
    protected $permissions;
    protected $redirectUrl;
    protected $siteName;
    protected $config;

    public $token = false;

    public function __construct(FacebookUserServiceInterface $userService, Config $config) {
        parent::__construct($userService);
        $this->config = $config->getConfig('utility.Facebook');
        $this->appId = $this->config->get('app_id');
        $this->secret = $this->config->get('secret');
        $this->permissions = $this->config->get('permissions');
        $this->redirectUrl = $config->get('global.base_url') . $config->get('global.uri');
        $this->siteName = $config->get('global.site_name');
    }

    public function login() {
        if ($this->isLoggedIn() && $this->user->getFacebookId()) {
            return true;
        }

        $code = $this->input->get('code');

        if (!$code) {
            $_SESSION['fb_hash'] = md5(uniqid(rand(), true));
            $dialogUrl = "https://www.facebook.com/dialog/oauth?client_id={$this->appId}&redirect_uri={$this->redirectUrl}&scope=" . implode(',', $this->permissions) . "&state=" . $_SESSION['fb_hash'];
            header('Location: ' . $dialogUrl);
            exit();
        }

        $state = $this->input->get('state');
        if (isset($_SESSION['fb_hash']) && $state && $_SESSION['fb_hash'] === $state) {
            $token_url = "https://graph.facebook.com/oauth/access_token?client_id={$this->appId}&redirect_uri={$this->redirectUrl}&client_secret={$this->secret}&code=$code";

            $response = file_get_contents($token_url);
            $params = null;
            parse_str($response, $params);

            $this->token = $params['access_token'];

            $facebook = new Facebook($this->config);
            $facebook->setToken($this->token);
            $facebookUser = $facebook->getUserDetails();

            if ($facebookUser) {
                $user = $this->userService->getFacebookUser($facebookUser->id);
                $newUser = false;
                if (!$user && !$this->user) {
                    $user = $this->userService->createFromFacebookUser($facebookUser);
                    $newUser = true;
                } else if ($this->user) {
                    $this->userService->setFacebookId($facebookUser->id);
                    $this->user->setFacebookId($facebookUser->id);
                    $user = $this->user;
                }
                $this->user = $user;
                $this->userService->setUser($user);
                $this->userService->setFacebookToken($this->token);
                $this->setUserLoggedIn($user);
                if ($newUser) {
                    header('Location: /user/signedup');
                    exit();
                }
                return true;
            } else {
                throw new AuthException("You must allow the {$this->siteName} facebook app to login.");
            }
        } else {
            throw new AuthException('Invalid access to facebook login.');
        }
    }

    public function enableLongLiveAccess() {
        if (!$this->token) {
            throw new AuthException('Must have short live token');
        }
        $url = 'https://graph.facebook.com/oauth/access_token?';
        $query = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->secret,
            'fb_exchange_token' => $this->token
        ];
        $url = $url . http_build_query($query);
        $response = file_get_contents($url);
        if (!$response) {
            throw new AuthException('Unable to create long lived token');
        }
        parse_str($response, $params);
        if (isset($params['access_token'])) {
            $this->token = $params['access_token'];
            $this->userService->setFacebookToken($this->token, (int) $params['expires']);
        }
    }

    public function getToken() {
        if (!$this->token) {
            if ($this->isLoggedIn()) {
                $token = $this->userService->getFacebookToken($this->user->getId());
                if ($token) {
                    $this->token = $token;
                }
            }
        }
        return $this->token;
    }
}
