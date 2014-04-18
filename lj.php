<?php

class sync_lj {
    var $username=false;
    var $md5pass=false;
    var $protocol_version=1;
    var $lineendings="unix";
    var $xmlurl="http://www.livejournal.com/interface/xmlrpc";
    var $comments=array();

    function sync_lj($auth=false) {
	if ($auth) {
	    $this->username=$auth['username'];
	    $this->md5pass=$auth['md5pass'];
	}
    }

    public function postArticle($a,$id=0) {
	// if id==0 or post is not exists - create post... else update post
	/*
	    expect:
		title, text (in html), privacy[public|private|friends], tags (array), dt (YYYY-MM-DD HH:MM:SS)
	    return:
		lj itemid (unique)
	*/
	if ($id>0) {
	    $method="editevent";
	} else {
	    $method="postevent";
	}
	$a['dt']=explode(" ",str_replace(array("-"," ",":")," ",$a['dt']));
	$params=array(
		"event"=>str_replace(array("\r","\n"),"",$a['text']),
		"subject"=>$a['title'],
		"security"=>$a['privacy'],
		"props"=>array('taglist'=>implode(", ",$a['tags']),'unknown8bit'=>false),
		"year"=>(int)$a['dt'][0],
		"mon"=>(int)$a['dt'][1],
		"day"=>(int)$a['dt'][2],
		"hour"=>(int)$a['dt'][3],
		"min"=>(int)$a['dt'][4]
	    );
	    if($a['privacy']=='friends') {
		//$params['security']="allfriends";
		$params['security']="usemask";
		$params['allowmask']=1;
	    }
	if ($id>0) {
	    $params['itemid']=$id;
	}

	$tmp=$this->_send("LJ.XMLRPC.".$method,$params);
	if (isset($tmp['itemid'])) {
	    if ($id==0) {
		// TODO dirty untested crunch
		$method="editevent";
		$params['itemid']=$tmp['itemid'];
		$tmp=$this->_send("LJ.XMLRPC.".$method,$params);
	    }
	    return $tmp['itemid'];
	} else {
	    return false;
	}
    }

    public function getArticle($article_id) {
	$params=array(
		"itemid"=>$article_id,
		"selecttype"=>"one"
	    );
	$tmp=$this->_send("LJ.XMLRPC.getevents",$params);
	if (isset($tmp['events'][0])) {
	    $tmp=$tmp['events'][0];
	    if (isset($tmp['security'])){
		if (isset($tmp['allowmask']) && $tmp['allowmask']==1) {
		    $tmp['security']='friends';
		} else {
		    $tmp['security']='private';
		}
	    } else {
		 $tmp['security']='public';
	    }
	    return array(
		"id"=>$tmp['itemid'],
		"title"=>$tmp['subject']->scalar,
		"text"=>$tmp['event']->scalar,
		"text_type"=>"html",
		"privacy"=>$tmp['security'],
		"tags"=>explode(", ",$tmp['props']['taglist']->scalar),
		"dt"=>$tmp['eventtime'],
		"ts"=>$tmp['event_timestamp']
	    );
	} else {
	    return false;
	}
    }

    public function deleteArticle($article_id) {
	$params=array(
		"itemid"=>$article_id,
		"event"=>'',
		"subject"=>'Deleted post'
	    );
	$tmp=$this->_send("LJ.XMLRPC.editevent",$params);
	if (isset($tmp['itemid'])) {
	    return $tmp['itemid'];
	} else {
	    return false;
	}
    }

    public function getComments($article_id) {
	$params=array(
		"itemid"=>$article_id,
		"expand_strategy"=>"expand_all",
	    );
	$tmp=$this->_send("LJ.XMLRPC.getcomments",$params);
	if (isset($tmp['comments'])) {
	    $this->comments=array();
	    //var_dump($tmp['comments']);
	    $this->_parseComments($tmp['comments'],0);
	    return $this->comments;
	} else {
	    return false;
	}
    }
    private function _parseComments($c,$parent_id=0) {
	foreach ($c as $k=>$v) {
	    if (isset($v['children'])) {
		$this->_parseComments($v['children'],$v['dtalkid']);
	    }
		if (isset($v['body']->scalar)) $v['body']=$v['body']->scalar;
		$this->comments[]=array(
			"comment_id"=>$v['dtalkid'],
			"level"=>$v['level'],
			"parent_id"=>$parent_id,
			"username"=>$v['postername'],
			"userid"=>$v['posterid'],
			"avatar"=>'',
			"text"=>$v['body'],
			"dt"=>date("Y-m-d H:i:s",$v['datepostunix']),
			"ts"=>$v['datepostunix']
		    );
	}
    }

    public function getAuthData($auth) {
	if ($auth===false) {
	    // create url for redirect
	    $url="http://".$_SERVER['SERVER_NAME']."/auth/lj/";
	    return array("redirect_url"=>$url);
	} else if (is_array($auth)) {
	    return array("username"=>$auth['username'],"md5pass"=>md5($auth['password']));
	} else {
	    return false;
	}
    }

    private function _send($method, $lj_xmlrpc) {
	$lj_xmlrpc["auth_challenge"] = $this->_getChallenge();
	if ($lj_xmlrpc["auth_challenge"]) {
	    $lj_xmlrpc["username"] = $this->username;
	    $lj_xmlrpc["auth_method"] = 'challenge';
	    $lj_xmlrpc["auth_response"] = md5($lj_xmlrpc["auth_challenge"].$this->md5pass);
	    $lj_xmlrpc["ver"] = $this->protocol_version;
	    $lj_xmlrpc["lineendings"] = $this->lineendings;
	    $tmp=$this->_xmlSend($method,$lj_xmlrpc);
	    return $tmp;
	} else {
	    return false;
	}
    }

    private function _getChallenge() {
	$tmp=$this->_xmlSend("LJ.XMLRPC.getchallenge",array());
	if ($tmp) {
	    return $tmp['challenge'];
	} else {
	    return false;
	}
    }

    private function _xmlSend($method,$params) {
	$request = xmlrpc_encode_request($method, $params, array("encoding"=>"UTF-8","escaping"=>"markup"));
	$context = stream_context_create(array('http' => array(
		'method' => "POST",
		'header' => "Content-Type: text/xml",
		'content' => $request
	)));
	$file = file_get_contents($this->xmlurl, false, $context);
	$response = xmlrpc_decode($file);
	return $response;
    }
}