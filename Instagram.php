<?php

class Instagram{
	
	private $ch; // curl handle
	private $auth_url = 'https://api.instagram.com/oauth/access_token';
	private $login_url = "https://api.instagram.com/oauth/authorize/";
	private $api_url = 'https://api.instagram.com/v1/';
	private $access_token;
	private $client_id = null;
	private $client_secret = null;
	private $redirect_uri = null;
	public $err_msg = false;
	private $code;
	private $token;
	private $user;
	
	
	public function __construct($params){
	
		if(isset($params['client_id'])) $this->client_id = $params['client_id'];
		if(isset($params['client_secret'])) $this->client_secret = $params['client_secret'];
		if(isset($params['redirect_uri'])) $this->redirect_uri = $params['redirect_uri'];
	
		// If client details not set, throw an exception
		if(!$this->client_id && $this->client_secret && $this->redirect_uri ) throw new Exception('Client details not set.');
	
		// If no session, start one
		if(!session_id()){
			session_start();
		}
		
		// If code is in GET array, set it in session, object
		if(isset($_GET['code'])) $this->setCode($_GET['code']);
		
		// If code is in session, set it in object
		if($this->getPersistentData('code')) $this->setCode( $this->getPersistentData('code') );
		
		// If code is in session, set it in object
		if($this->getPersistentData('user')) $this->setUserData( $this->getPersistentData('user') );
		
		// If token is in session, set it in object
		if($this->getPersistentData('token')) $this->setToken( $this->getPersistentData('token') );

	}
	
	/*  Persistent Data Functions  */
	private function setPersistentData($var, $data){
		$_SESSION['insta'][$var] = $data;
		return;
	}
	
	private function getPersistentData($var){
		if(!isset($_SESSION['insta'][$var])) return false;
		return $_SESSION['insta'][$var];
	}
	
	/* Code Getter/Setter */
	public function getCode(){
		return $this->code;
	}
	public function setCode($code){
		$this->setPersistentData('code', $code);
		$this->code = $code;
	}
	
	/* Token Getter/Setter */
	public function getToken(){
		return $this->token;
	}
	public function setToken($token){
		$this->setPersistentData('token',$token);
		$this->token = $token;
	}
	
	/* User Data Getter/Setter */
	public function getUserData(){
		return $this->user;
	}
	public function setUserData($user){
		$this->setPersistentData('user',$user);
		$this->user = $user;
	}
	
	private function doAuthenticate(){
			
			$token_data = $this->requestToken($this->getcode(), $this->client_id, $this->client_secret, $this->redirect_uri, $this->auth_url);
			
			$this->setUserData( $token_data->user );
			
			// Extract the token and set it in persistent data
			$token = $this->extractAccessToken($token_data);
			$this->setToken($token);
			return;
	
	}
	
	/* Determine whether access_token exists and if valid */
	public function isAuthenticated(){

		if($this->getcode() && !$this->getToken()){
			// Looks like they were just redirected, 
			// Try to get a token from the code
			$this->doAuthenticate();
		}
	
		if(!$this->getToken()) return false;
		
		// probably make a call to make sure token is valid
		
		return true;
	}
	
	public function getLoginUrl($params){

		$login_qs = "";
		$login_qs .= "?client_id=" . $this->client_id;
		$login_qs .= "&redirect_uri=" . urlencode($this->redirect_uri);
		$login_qs .= (isset($params['scope'])) ? "&scope=" . $params['scope'] : '';
		$login_qs .= "&response_type=code";
	
		return $this->login_url . $login_qs;
	
	}
	
	public function queryMedia($tag){

		$search_url = 'tags/'.$tag.'/media/recent';
		$res = $this->api($search_url);
		return json_decode($res);
	}
	
	public function api($endpoint, $method = 'get', $params = null){
		$access_str = '?access_token='.$this->getToken();
		return json_decode(file_get_contents($this->api_url . $endpoint . $access_str, $method));
	}
	
	private function doCurl($url, $query_string = null, $method, $ssl=false){
	
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_URL,$url);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER,1);
		
		// Set request method
		if(strtolower($method) == 'post') {
			curl_setopt($this->ch, CURLOPT_POST, 1);
		}else{
			curl_setopt($this->ch, CURLOPT_POST, 0);
		}
		
		// Set query data here with CURLOPT_POSTFIELDS
		if ($query_string) curl_setopt($this->ch, CURLOPT_POSTFIELDS, $query_string);
		// Below two option will enable the HTTPS option.
		if($ssl) {
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, TRUE);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST,  2);
		}else{
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST,  2);
		}
		$result = curl_exec($this->ch);

		return $result;
	}
	
	
	public function requestToken($code, $client_id, $secret, $redirect, $auth_url){
		
		$qry_str = 'grant_type=authorization_code&client_id='.$client_id.'&client_secret='.$secret.'&redirect_uri='.$redirect.'&code='.$code;
		
		return json_decode($this->doCurl($auth_url, $qry_str, 'post'));
		
	}
	
	function extractAccessToken($request_res){
		
		$res_arr = $request_res;
				
		$this->access_token = $res_arr->access_token;
		
		return $this->access_token;
	}
	
}
?>