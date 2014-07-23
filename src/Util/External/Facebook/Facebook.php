<?php
namespace Cohesion\Util\External\Facebook;

use \Cohesion\Util\External\ExternalAPI;
use \Cohesion\Util\External\ExternalAPIException;

class Facebook extends ExternalAPI {

    private $token;

    public function setToken($token) {
        $this->token = $token;
    }

    public function get($id) {
        return $this->queryGraph(urlencode($id));
    }

    public function search($query, $limit = 5) {
        $fields = array('id', 'name', 'picture', 'category', 'is_verified', 'likes');
        $pages = $this->queryGraph('search', array('q' => $query, 'limit' => (int)$limit, 'type' => 'page', 'fields' => implode(',', $fields)));
        return $pages;
    }

    public function getUserDetails() {
        $facebookUser = $this->queryGraph('me');
        return $facebookUser;
    }

    public function getLikes($param = false, $isAssociativeArray = false) {
        $facebookLikes = $this->queryGraph('me/likes', $param, $isAssociativeArray);
        return $facebookLikes;
    }

    public function getProfilePicture($name, $size = array('width' => 50, 'height' => 50)) {
        return $this->queryGraph($name . '/picture', $size);
    }

    public function queryGraph($query, Array $params = array(), $isAssociativeArray = false) {
        if (!$this->token) {
            throw new FacebookException('Missing token');
        }

        $paramStr = '?access_token=' . urlencode($this->token);
        foreach ($params as $i => $param) {
            $paramStr .= "&$i=" . urlencode($param);
        }
        $graphUrl = $this->config->get('api_url') . $query . $paramStr;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $graphUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $response = json_decode($response, $isAssociativeArray);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($statusCode !== 200) {
            if ($statusCode === 400) {
                if ($isAssociativeArray) {
                    $message = $response['error']['message'];
                } else {
                    $message = $response->error->message;
                }
                throw new SessionExpiredException("Facebook session has expired. " . $message);
            }
        }
        return $response;
    }
}

class FacebookException extends ExternalAPIException {}
class SessionExpiredException extends FacebookException {}

