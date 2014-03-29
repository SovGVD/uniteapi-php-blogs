<?php
class sync_vk {
    var $userid=false;
    var $token=false;
    var $api_redirect_uri="http://domain/oauth/vk/";
    var $api_display="page";
    var $api_scope="notes,wall,offline";
    var $api_version="5.14";
    var $api_id="";
    var $api_secret="";
    var $api_url="https://api.vk.com/method/";


    public function sync_vk($auth=false) {
	if ($auth) {
	    $this->userid=$auth['userid'];
	    $this->token=$auth['token'];
	}
    }

    public function postArticle($a,$article_id) {
        // if id==0 or post is not exists - create note... else update note
        /*
            expect:
                title, text (in html), privacy[public or private], tags (array), dt (YYYY-MM-DD HH:MM:SS)
            return:
        	itemid (unique)
        */
	if ($a['privacy']!='public') {
	    $a['privacy']=3;
	} else {
	    $a['privacy']=0;
	}
	$out=array(
		"title"=>$a['title'],
		"text"=>$a['text'],
		"privacy"=>$a['privacy'],
		"comment_privacy"=>$a['privacy']
	    );
	 if ($article_id>0) {
            $method="notes.edit";
	    $out['note_id']=$article_id;
        } else {
            $method="notes.add";
        }
	$tmp=$this->_api($method,$out,'POST');
	if (isset($tmp->response) && $tmp->response==1) {
	    return $article_id;
	} else if (isset($tmp->response)) {
	    return $tmp->response;
	} else {
	    return false;
	}
    }

    public function getArticle($article_id) {
	$tmp=$this->_api('notes.getById',array("note_id"=>$article_id,"owner_id"=>$this->userid,"need_wik"=>0));
	if (isset($tmp->response)) {
	    $tmp=$tmp->response;
	    if ($tmp->privacy!=0) {
		$tmp->privacy="private";
	    } else {
		$tmp->privacy="public";
	    }
	    return array(
                "id"=>$tmp->id,
                "title"=>$tmp->title,
                "text"=>$tmp->text,
                "text_type"=>"html",
                "privacy"=>$tmp->privacy,
                "tags"=>array(),
                "dt"=>date("Y-m-d H:i:s",$tmp->date),
                "ts"=>$tmp->date
            );
	} else {
	    return false;
	}
    }

    public function deleteArticle($article_id) {
	$tmp=$this->_api('notes.delete',array("note_id"=>$article_id));
	if (isset($tmp->response) && $tmp->response==1) {
	    return true;
	} else {
	    return false;
	}
    }

    public function getComments($article_id) {
	$tmp=$this->_api('notes.getComments',array("note_id"=>$article_id,"owner_id"=>$this->userid,"sort"=>1,"count"=>100));
	if (isset($tmp->response->items)) {
	    $tmp=$tmp->response->items;
	    $out=array();
	    $extra_users=array();
	    foreach($tmp as $v) {
		$extra_users[$v->uid]=true;
		$v->message=preg_replace('#\[id(.+)\|(.+)\]#iUs', '$2', $v->message);
		$out[]=array(
		    "comment_id"=>$v->id,
                    "level"=>0,
                    "parent_id"=>0,
                    "username"=>'',
                    "userid"=>$v->uid,
                    "avatar"=>'',
                    "text"=>$v->message,
                    "dt"=>date("Y-m-d H:i:s",$v->date),
                    "ts"=>$v->date
		);
	    }
	    $extra_users=$this->_extra_getUserData(array_keys($extra_users));
	    if ($extra_users) {
		foreach ($out as $k=>$v) {
		    if (isset($extra_users[($v['userid'])])) {
			$out[$k]['username']=$extra_users[($v['userid'])]['username'];
			$out[$k]['avatar']=$extra_users[($v['userid'])]['avatar'];
		    }
		}
	    }
	    return $out;
	} else {
	    return false;
	}
	
    }

    private function _extra_getUserData($uids) {
	$tmp=$this->_api('users.get',array("user_ids"=>implode(",",$uids),"fields"=>"photo_50"),'POST');
	if (isset($tmp->response)) {
	    $out=array();
	    foreach($tmp->response as $u) {
		$out[$u->id]=array("username"=>$u->first_name." ".$u->last_name,"avatar"=>$u->photo_50);
	    }
	    return $out;
	} else {
	    return false;
	}
    }

    public function getAuthData($auth) {
	if ($auth==false) {
	    // create url for redirect
	    $url="http://oauth.vk.com/authorize?client_id=".$this->api_id."&scope=".urlencode($this->api_scope)."&redirect_uri=".urlencode($this->api_redirect_uri)."&display=".$this->api_display."&v=".$this->api_version."&response_type=code";
	    return array("redirect_url"=>$url);
	} else if (isset($auth['code'])){
	    // get token
	    $url="https://oauth.vk.com/access_token?client_id=".$this->api_id."&client_secret=".$this->api_secret."&code=".$auth['code']."&redirect_uri=".urlencode($this->api_redirect_uri);
	    $url=$this->_http($url);
	    if (isset($url->access_token)) {
		return array("userid"=>$url->user_id,"token"=>$url->access_token);
	    } else {
		return false;
	    }
	} else {
	    return false;
	}
    }

    private function _http ($url, $method = 'GET', $postfields = null) {
        // get from https://github.com/vladkens/VK/blob/master/VK.php
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT,      'niceblog');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT,        60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!is_null($postfields)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        $rs = curl_exec($ch);
        curl_close($ch);
        return json_decode($rs);
    }

    private function _api($method, $params = array(), $curl_method='GET') {
	$params['user_id']=$this->userid;
	$params['access_token']=$this->token;
	$params['v']=$this->api_version;
	if ($curl_method=='GET') {
	    $url = $this->_createURL($params, $method);
	} else {
	    $url = $this->api_url.$method;
	}
        return $this->_http($url,$curl_method,$params);
    }

    private function _createURL($parameters,$method) {
        return $this->api_url.$method."?".http_build_query($parameters);
    }


}
