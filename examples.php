<?php

// Facebook ----------------------------------------------------------------------------
include ("fb.php");
$auth=array(
	"token"=>"your token here",
    );

$fb=new sync_fb($auth);

$a=array(
	"title"=>"Title in unicode",
	"text"=>"some text here",
	"privacy"=>"private",
	"dt"=>date("Y-m-d H:i:s"),
	"tags"=>array("test","demo"),
	"link"=>"http://google.com/"
    );
var_dump($fb->postArticle($a,false));		// this will return ID of record

// LiveJournal ----------------------------------------------------------------------------
include ("lj.php");

$auth=array(
	"username"=>"login",
	"md5pass"=>md5("password")
    );

$lj=new sync_lj($auth);

$a=array(
	"title"=>"Title in unicode",
	"text"=>"hello<br>123<br>\n<b>BOLD</b>... русский текст (russian text)",
	"privacy"=>"private",
	"dt"=>date("Y-m-d H:i:s"),
	"tags"=>array("test","demo","lj")
    );
var_dump($lj->postArticle($a));		// new, will return ID of post for next update... may be 9
var_dump($lj->postArticle($a,9));	// update
var_dump($lj->getArticle(9));
var_dump($lj->getComments(9));
var_dump($lj->deleteArticle(9));

// VKontakte ----------------------------------------------------------------------------
include("vk.php");

$auth=array(
	"userid"=>0000,
	"token"=>"your token here"
    );

$vk=new sync_vk($auth);
//var_dump($vk->getAuthData(false));					// get oauth code 			(first step)
//var_dump($vk->getAuthData(array("code"=>"some code here")));		// get user_id and token by code 	(final step)

var_dump($vk->postArticle($a));		// new, $a from LJ example

$a=array(
	"title"=>"Title in unicode",
	"text"=>"Text for VKontakte",
	"privacy"=>"friends",		// ATTENTION HERE
	"dt"=>date("Y-m-d H:i:s"),
	"tags"=>array("test")
    );

var_dump($vk->postArticle($a,9));	// update with change privacy to "friends"
var_dump($vk->getArticle(9));
var_dump($vk->getComments(9));
var_dump($vk->deleteArticle(9));


?>