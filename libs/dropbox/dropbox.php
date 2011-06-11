<?PHP
if (!class_exists('OAuthException'))
	require_once(dirname(__FILE__).'/oauth.php');

class Dropbox {
	const API_URL = 'https://api.dropbox.com/';
	const API_CONTENT_URL = 'https://api-content.dropbox.com/';
	const API_WWW_URL = 'https://www.dropbox.com/';
	const API_VERSION_URL = '0/';
	
	protected $root = 'dropbox';
	protected $OAuthToken;
	protected $OAuthConsumer;
	protected $OAuthSignatureMethod;
	protected $ProgressFunction = false;
	
	public function __construct($applicationKey, $applicationSecret) {	
		$this->OAuthConsumer = new OAuthConsumer($applicationKey, $applicationSecret);
		$this->OAuthSignatureMethod = new OAuthSignatureMethod_HMAC_SHA1;
	}

	public function setOAuthTokens($token,$secret) {
		$this->OAuthToken = new OAuthToken($token, $secret);
	}
	
	public function setDropbox() {
		$this->root = 'dropbox';
	}
	
	public function setSandbox() {
		$this->root = 'sandbox';
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
	
	public function upload($file, $path = ''){
		$file = str_replace("\\", "/",$file);
		if (!is_readable($file) or !is_file($file)){
			throw new DropboxException("Error: File \"$file\" is not readable or doesn't exist.");
		}
		if (filesize($file)>314572800){
			throw new DropboxException("Error: File \"$file\" is to big max. 300 MB.");
		}
		$url = self::API_CONTENT_URL.self::API_VERSION_URL. 'files/' . $this->root . '/' . trim($path, '/');
		return $this->request($url, array('file' => $file), 'POST', $file);
	}
	
	public function download($path){
		$url = self::API_CONTENT_URL.self::API_VERSION_URL. 'files/' . $this->root . '/' . trim($path, '/');
		return $this->request($url);
	}
	
	public function metadata($path = '', $listContents = true, $fileLimit = 10000){
		$url = self::API_URL.self::API_VERSION_URL. 'metadata/' . $this->root . '/' . ltrim($path, '/');
		return $this->request($url, array('list' => ($listContents)? 'true' : 'false', 'file_limit' => $fileLimit));
	}
	
	public function createAccount($firstName, $lastName, $email, $password){
		$url = self::API_URL.self::API_VERSION_URL.'account';
		return $this->request($url, array('first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'password' => $password), 'POST', true);	
	}
	
	public function fileopsDelete($path){
		$url = self::API_URL.self::API_VERSION_URL.'fileops/delete';
		return $this->request($url, array('path' => $path, 'root' => $this->root));
	}
	
	public function fileopsMove($from, $to){
		$url = self::API_URL.self::API_VERSION_URL.'fileops/move';
		return $this->request($url, array('from_path' => $from, 'to_path' => $to, 'root' => $this->root));
	}
	
	public function fileopsCreateFolder($path){
		$url = self::API_URL.self::API_VERSION_URL.'fileops/create_folder';
		return $this->request($url, array('path' => $path, 'root' => $this->root));
	}
	
	public function fileopsCopy($from, $to){
		$url = self::API_URL.self::API_VERSION_URL.'fileops/copy';
		return $this->request($url, array('from_path' => $from, 'to_path' => $to, 'root' => $this->root));
	}

	public function thumbnail($path, $size = 'small', $format = 'JPEG', $raw = false){
		$url = self::API_CONTENT_URL.self::API_VERSION_URL. 'dropbox/' . ltrim($path, '/');
		$result = $this->request($url, array('size' => $size, 'format' => $format));
		if ($raw){
			return $result;
		}
		else{
			return 'data:image/' . $format . ';base64,' . base64_encode( (isset($result['body'])) ? $result['body'] : (!is_array($result)) ? $result : '' );
		}
	}

	public function oAuthRequestToken() {
		$req_req = OAuthRequest::from_consumer_and_token($this->OAuthConsumer, NULL, "GET", self::API_URL.self::API_VERSION_URL.'oauth/request_token');
		$req_req->sign_request($this->OAuthSignatureMethod, $this->OAuthConsumer, NULL);
	    if (!empty($_SERVER["HTTP_ACCEPT"]))
				$headers[] = 'Accept: ' . $_SERVER["HTTP_ACCEPT"];
		if (!empty($_SERVER["REMOTE_ADDR"]))
				$headers[] = 'X-Forwarded-For: ' . $_SERVER["REMOTE_ADDR"];
		$headers[]='Expect:';	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $req_req);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER , true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($status>=200 and $status<300 and 0==curl_errno($ch) ) {
			$content = (array) explode('&', $content);
			foreach($content as $chunk) {
				$chunks = explode('=', $chunk, 2);
				if(count($chunks) == 2) $return[$chunks[0]] = $chunks[1];
			}
			curl_close($ch);
			return $return;
		} else {
			$output = json_decode($content, true);
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			elseif (0!=curl_errno($ch)) $message = '('.curl_errno($ch).') '.curl_error($ch);
			else $message = '('.$status.') Invalid response.';
			throw new DropboxException($message);		
		}
	}
	
	public function oAuthAuthorize($oAuthToken,$callback_url) {
		$auth_url = self::API_WWW_URL.self::API_VERSION_URL."oauth/authorize?oauth_token=".OAuthUtil::urlencode_rfc3986($oAuthToken)."&oauth_callback=".OAuthUtil::urlencode_rfc3986($callback_url);
		header('Location: '. $auth_url);
		exit;
	}
	
	public function oAuthAccessToken($oauth_token, $oauth_token_secret) {
		$oAuthToken = new OAuthConsumer($oauth_token, $oauth_token_secret);
		$acc_req = OAuthRequest::from_consumer_and_token($this->OAuthConsumer, $oAuthToken, "GET", self::API_URL.self::API_VERSION_URL.'oauth/access_token');
		$acc_req->sign_request($this->OAuthSignatureMethod, $this->OAuthConsumer, $oAuthToken);
	    if (!empty($_SERVER["HTTP_ACCEPT"]))
				$headers[] = 'Accept: ' . $_SERVER["HTTP_ACCEPT"];
		if (!empty($_SERVER["REMOTE_ADDR"]))
				$headers[] = 'X-Forwarded-For: ' . $_SERVER["REMOTE_ADDR"];
		$headers[]='Expect:';		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $acc_req);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER , true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($status>=200 and $status<300  and 0==curl_errno($ch)) {
			$content = (array) explode('&', $content);
			$return = array();
			foreach($content as $chunk) {
				$chunks = explode('=', $chunk, 2);
				if(count($chunks) == 2) $return[$chunks[0]] = $chunks[1];
			}
			$this->setOAuthTokens($return['oauth_token'],$return['oauth_token_secret']);
			return $return;
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
		
		/* Sign Request*/
		$dropoauthreq = OAuthRequest::from_consumer_and_token($this->OAuthConsumer, $this->OAuthToken, $method, $url, $args);
		$dropoauthreq->sign_request($this->OAuthSignatureMethod, $this->OAuthConsumer, $this->OAuthToken);
		
		/* Header*/
	    if (!empty($_SERVER["HTTP_ACCEPT"]))
				$headers[] = 'Accept: ' . $_SERVER["HTTP_ACCEPT"];
		if (!empty($_SERVER["REMOTE_ADDR"]))
				$headers[] = 'X-Forwarded-For: ' . $_SERVER["REMOTE_ADDR"];
		$headers[]='Expect:';
		
		/* Build cURL Request */
		$ch = curl_init();
		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			if (is_file($file)) { /* file upload */		
				curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => "@$file"));
				$headers[]='Content-Length: ' .filesize($file)+strlen(http_build_query(array('file' => "$file")));
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
				$args = (is_array($args)) ? http_build_query($args) : $args;
				$headers[]='Content-Length: ' .strlen($args);
			}
			$headers[]=$dropoauthreq->to_header($url);
			curl_setopt($ch, CURLOPT_URL, $url);
		} else {
			curl_setopt($ch, CURLOPT_URL, $dropoauthreq->to_url());
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER , true);
		if (function_exists($this->ProgressFunction)) {
			curl_setopt($ch, CURLOPT_NOPROGRESS, false);
			curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $this->ProgressFunction);
			curl_setopt($ch, CURLOPT_BUFFERSIZE, 512);
		}
		
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$output = json_decode($content, true);

		if (isset($output['error']) or $status>=300 or $status<200 or curl_errno($ch)>0) {
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			elseif (0!=curl_errno($ch)) $message = '('.curl_errno($ch).') '.curl_error($ch);
			else $message = '('.$status.') Invalid response.';
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