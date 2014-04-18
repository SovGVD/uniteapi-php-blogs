<?php

class sync_fb {
    public $token=false;
    private $client=false;
    private $clientID="";
    private $clientSecret="";
    private $api_scope="publish_actions";
    private $api_redirect_uri="http://domain/oauth/fb/";

    function sync_fb($auth=false) {
	require_once '/where/fbapi/facebook.php';
	if ($auth) {
	    $this->token=$auth['token'];
	}
	$config = array();
            $config['appId'] = $this->clientID;
            $config['secret'] = $this->clientSecret;
            $config['fileUpload'] = false;
        $this->client = new Facebook($config);
    }
    
	public function postArticle($a,$article_id) {
        // if id==0 or post is not exists - create post... else nothing
        /*
            expect:
                title, text (in plain text), privacy[public|private|friends], tags (array), dt (YYYY-MM-DD HH:MM:SS), link (optional)
            return:
                itemid (unique)
        */
	if ($a['privacy']=='friends') {
	    $a['privacy']='ALL_FRIENDS';
        } else if ($a['privacy']!='public') {
            $a['privacy']='SELF';
        } else {
            $a['privacy']='EVERYONE';
        }
        $out=array(
                "message"=>$a['title'].". \n".$a['text'],
                "privacy"=>array('value'=>$a['privacy'])
            );
	if (isset($a['link'])) {
	    $out['link']=$a['link'];
	    $out['name']=$a['title'];
	    $out['description']=$a['text'];
	    $out['message']=$a['title'];
	}
        if ($article_id>0) {
            $method=false;
        } else {
            $method="/me/feed";
        }
	if ($method) {
	    $this->client->setAccessToken($this->token);
	    $tmp = $this->client->api(
		$method,
		"POST",
		$out
	    );

	    if (isset($tmp['id'])) {
		return $tmp['id'];
	    } else {
		return false;
	    }
	} else {
	    return false;
	}
    }

    public function getComments($article_id) {
	    $this->client->setAccessToken($this->token);
	    // actually we need add check of pagination... but may be in future
	    $tmp = $this->client->api(
		"/".$article_id."/comments"
	    );
	if ($tmp['data']) {
	    $out=array();
            foreach($tmp['data'] as $v) {
                $out[]=array(
                    "comment_id"=>$v['id'],
                    "level"=>0,
                    "parent_id"=>0,
                    "username"=>$v['from']['name'],
                    "userid"=>$v['from']['id'],
                    "avatar"=>'http://graph.facebook.com/'.$v['from']['id'].'/picture',
                    "text"=>$v['message'],
                    "dt"=>str_replace("T"," ",substr($v['created_time'],0,19)),
                    "ts"=>mktime((int)substr($v['created_time'],11,2),(int)substr($v['created_time'],14,2),(int)substr($v['created_time'],17,2), (int)substr($v['created_time'],5,2),(int)substr($v['created_time'],8,2),(int)substr($v['created_time'],0,4))
                );
            }
	    return $out;
	} else {
	    return false;
	}
    }


    public function getAuthData($auth) {
        if ($auth==false) {
            // create url for redirect
	    $params = array(
		'scope' => $this->api_scope,
		'redirect_uri' => $this->api_redirect_uri
	    );
            return array("redirect_url"=>$this->client->getLoginUrl($params));
        } else if (isset($auth['code'])){
            // get token
	    $this->token=$this->client->getAccessToken();
            $this->client->setExtendedAccessToken();
            $this->token=$this->client->getAccessToken();

            if ($this->token) {
                return array("token"=>$this->token);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

}

?>