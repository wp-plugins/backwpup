<?PHP

/**
 * Dropbox class
 *
 * This source file can be used to communicate with DropBox (http://dropbox.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. 
 * If you report a bug, make sure you give me enough information (include your code).
 *
 *
 *
 * License
 * Copyright (c), Daniel Huesken. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author		Daniel Huesken <daniel@huesken-net.de>
 * @version		2.0.0
 *
 * @copyright	Copyright (c), Daniel Huesken. All rights reserved.
 * @license		BSD License
 */

if (!class_exists('OAuthSimple'))
	require_once(dirname(__FILE__).'/OAuthSimple.php');

class Dropbox {
	const API_URL = 'https://api.dropbox.com/';
	const API_CONTENT_URL = 'https://api-content.dropbox.com/';
	const API_WWW_URL = 'https://www.dropbox.com/';
	const API_VERSION_URL = '1/';
	
	protected $root = 'dropbox';
	protected $OAuthObject;
	protected $OAuthToken;
	protected $OAuthSignMethod='HMAC-SHA1';
	protected $ProgressFunction = false;
	
	public function __construct($applicationKey, $applicationSecret) {
		$this->OAuthObject = new OAuthSimple($applicationKey, $applicationSecret);
	}

	public function setOAuthTokens($token,$secret) {
		$this->OAuthToken = array('oauth_token'=>$token,'oauth_secret'=> $secret);
	}
	
	public function setDropbox() {
		$this->root = 'dropbox';
	}
	
	public function setSandbox() {
		$this->root = 'sandbox';
	}

	public function setoAuthSignMethodSHA1() {
		$this->OAuthSignMethod = 'HMAC-SHA1';
	}
	
	public function setoAuthSignMethodPlain() {
		$this->OAuthSignMethod = 'PLAINTEXT';
	}
	
	public function setProgressFunction($function) {
		if (function_exists($function))
			$this->ProgressFunction = $function;
		else
			$this->ProgressFunction = false;
	}
	
	public function accountInfo(){
		$url = self::API_URL.self::API_VERSION_URL.'account/info';
		return $this->request($url);
	}
	
	public function upload($file, $path = '',$overwrite=true){
		$file = str_replace("\\", "/",$file);
		if (!is_readable($file) or !is_file($file)){
			throw new DropboxException("Error: File \"$file\" is not readable or doesn't exist.");
		}
		if (filesize($file)>314572800){
			throw new DropboxException("Error: File \"$file\" is to big max. 300 MB.");
		}
		$url = self::API_CONTENT_URL.self::API_VERSION_URL.'files_put/'.$this->root.'/'.trim($path, '/').'/'.basename($file);
		return $this->request($url, array('overwrite' => ($overwrite)? 'true' : 'false'), 'PUT', $file);
	}
	
	public function download($path){
		$url = self::API_CONTENT_URL.self::API_VERSION_URL. 'files/'.$this->root.'/'.$path;
		return $this->request($url);
	}
	
	public function metadata($path = '', $listContents = true, $fileLimit = 10000){
		$url = self::API_URL.self::API_VERSION_URL. 'metadata/' . $this->root . '/' . ltrim($path, '/');
		return $this->request($url, array('list' => ($listContents)? 'true' : 'false', 'file_limit' => $fileLimit));
	}
	
	public function fileopsDelete($path){
		$url = self::API_URL.self::API_VERSION_URL.'fileops/delete';
		return $this->request($url, array('path' => $path, 'root' => $this->root));
	}

	public function oAuthAuthorize($callback_url) {
		//request tokens
		$OAuthSign = $this->OAuthObject->sign(array(
			'path'    	=>self::API_URL.self::API_VERSION_URL.'oauth/request_token',
			'method' 	=> $this->OAuthSignMethod,
			'action'	=>'GET',
			'parameters'=>array('oauth_callback'=>$callback_url)));
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $OAuthSign['signed_url']);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER , true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($status>=200 and $status<300 and 0==curl_errno($ch) ) {
			parse_str($content, $oauth_token);
		} else {
			$output = json_decode($content, true);
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			elseif (0!=curl_errno($ch)) $message = '('.curl_errno($ch).') '.curl_error($ch);
			else $message = '('.$status.') Invalid response.';
			throw new DropboxException($message);		
		}
		curl_close($ch);
		$OAuthSign = $this->OAuthObject->sign(array(
			'path'      =>self::API_WWW_URL.self::API_VERSION_URL.'oauth/authorize',
			'action'	=>'GET',
			'parameters'=>array(
				'oauth_token' => $oauth_token['oauth_token'])));
		return array('authurl'=>$OAuthSign['signed_url'],'oauth_token'=>$oauth_token['oauth_token'],'oauth_token_secret'=>$oauth_token['oauth_token_secret']);
	}
	
	public function oAuthAccessToken($oauth_token, $oauth_token_secret) {
		 $OAuthSign = $this->OAuthObject->sign(array(
			'path'      => self::API_URL.self::API_VERSION_URL.'oauth/access_token',
			'action'	=>'GET',
			'method' 	=> $this->OAuthSignMethod,
			'parameters'=>array('oauth_token'    => $oauth_token),
			'signatures'=>array('oauth_token'=>$oauth_token,'oauth_secret'=>$oauth_token_secret)));
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $OAuthSign['signed_url']);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER , true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($status>=200 and $status<300  and 0==curl_errno($ch)) {
			parse_str($content, $oauth_token);
			$this->setOAuthTokens($oauth_token['oauth_token'],$oauth_token['oauth_token_secret']);
			return $oauth_token;
		} else {
			$output = json_decode($content, true);
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			elseif (0!=curl_errno($ch)) $message = '('.curl_errno($ch).') '.curl_error($ch);
			else $message = '('.$status.') Invalid response.';
			throw new DropboxException($message);		
		}
	}	
	
	protected function request($url, $args = null, $method = 'GET', $file = null){
		$args = (is_array($args)) ? $args : array();
		$url=utf8_encode($url); //utf8 encode
		/* Sign Request*/
		$this->OAuthObject->reset();
		$OAuthSign=$this->OAuthObject->sign(array(
			'path'      => $url,
			'parameters'=> $args,
			'action'=> $method,
			'method' => $this->OAuthSignMethod,
			'signatures'=> $this->OAuthToken));
		
		/* Header*/
		$headers[]='Expect:';
		
		/* Build cURL Request */
		$ch = curl_init();
		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			$args = (is_array($args)) ? http_build_query($args) : $args;
			$headers[]='Content-Length: ' .strlen($args);
			$headers[]='Authorization: '.$OAuthSign['header'];
			curl_setopt($ch, CURLOPT_URL, $url);
		} elseif ($method == 'PUT') {
			$datafilefd=fopen($file,'r');
			curl_setopt($ch,CURLOPT_PUT,true);
			curl_setopt($ch,CURLOPT_INFILE,$datafilefd);
			curl_setopt($ch,CURLOPT_INFILESIZE,filesize($file));
			$args = (is_array($args)) ? '?'.http_build_query($args) : $args;
			$headers[]='Content-Length: ' .strlen(filesize($file));
			$headers[]='Authorization: '.$OAuthSign['header'];
			trigger_error($url.$args,E_USER_WARNING);
			curl_setopt($ch, CURLOPT_URL, $url.$args);
		} else {
			curl_setopt($ch, CURLOPT_URL, $OAuthSign['signed_url']);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		if (function_exists($this->ProgressFunction) and defined('CURLOPT_PROGRESSFUNCTION')) {
			curl_setopt($ch, CURLOPT_NOPROGRESS, false);
			curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $this->ProgressFunction);
			curl_setopt($ch, CURLOPT_BUFFERSIZE, 512);
		}
		$content = curl_exec($ch);
		$status = curl_getinfo($ch);
		$output = json_decode($content, true);
		
		if (isset($output['error']) or $status['http_code']>=300 or $status['http_code']<200 or curl_errno($ch)>0) {
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			elseif (0!=curl_errno($ch)) $message = '('.curl_errno($ch).') '.curl_error($ch);
			else $message = '('.$status['http_code'].') Invalid response.';
			throw new DropboxException($message);
		} else {
			curl_close($ch);
			if (!is_array($output))
				return $content;
			else
				return $output;
		}
	}

}

class DropboxException extends Exception {
}
?>