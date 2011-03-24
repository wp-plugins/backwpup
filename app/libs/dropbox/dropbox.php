<?PHP
require_once(dirname(__FILE__).'/oauth.php');

class Dropbox {
	const API_URL = 'https://api.dropbox.com/';
	const API_CONTENT_URL = 'https://api-content.dropbox.com/';
	const API_VERSION_URL = '0/';
	
	protected $root = 'dropbox';
	protected $OAuthToken;
	protected $OAuthConsumer;
	protected $OAuthSignatureMethod;
	
	public function __construct($applicationKey, $applicationSecret) {	
		$this->OAuthConsumer = new OAuthConsumer($applicationKey, $applicationSecret);
		$this->OAuthSignatureMethod = new OAuthSignatureMethod_HMAC_SHA1;
	}

	public function setOAuthTokens($token,$secret) {
		$this->oAuthToken = $token;
		$this->oAuthTokenSecret = $secret;
		$this->OAuthToken = new OAuthToken($this->oAuthToken, $this->oAuthTokenSecret);
	}
	
	public function setDropbox() {
		$this->root = 'dropbox';
	}
	
	public function setSandbox() {
		$this->root = 'sandbox';
	}
	
	public function accountInfo(){
		$url = self::API_URL.self::API_VERSION_URL.'account/info';
		return $this->request($url);
	}
	
	public function upload($file, $path = ''){
		$file = preg_replace("/\\\\/", "/",$file);
		if (!is_readable($file)){
			throw new DropboxException("Error: File \"$file\" is not readable or doesn't exist.");
		}
		if (!filesize($file)>314572800){
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
		return $this->request($url, array('first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'password' => $password), 'GET', true);	
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
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $req_req);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($status>=200 and $status<300) {
			$content = (array) explode('&', $content);
			foreach($content as $chunk) {
				$chunks = explode('=', $chunk, 2);
				if(count($chunks) == 2) $return[$chunks[0]] = $chunks[1];
			}
			return $return;
		} else {
			$output = json_decode($content, true);
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			else $message = '('.$status.') Invalid response.';
			throw new DropboxException($message);		
		}
	}
	
	public function oAuthAuthorize($oAuthToken,$callback_url) {
		$auth_url = self::API_URL.self::API_VERSION_URL."oauth/authorize?oauth_token=".$oAuthToken."&oauth_callback=".urlencode($callback_url);
		header('Location: '. $auth_url);
		exit;
	}
	
	public function oAuthAccessToken($oauth_token, $oauth_token_secret) {
		$oAuthToken = new OAuthConsumer($oauth_token, $oauth_token_secret);
		$acc_req = OAuthRequest::from_consumer_and_token($this->OAuthConsumer, $oAuthToken, "GET", self::API_URL.self::API_VERSION_URL.'oauth/access_token');
		$acc_req->sign_request($this->OAuthSignatureMethod, $this->OAuthConsumer, $oAuthToken);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $acc_req);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($status>=200 and $status<300) {
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
			else $message = '('.$status.') Invalid response.';
			throw new DropboxException($message);		
		}
	}	
	
	protected function request($url, $args = null, $method = 'GET', $file = null){
		$args = (is_array($args)) ? $args : array();
		
		/* Sign Request*/
		$Request = OAuthRequest::from_consumer_and_token($this->OAuthConsumer, $this->OAuthToken, $method, $url, $args);
		$Request->sign_request($this->OAuthSignatureMethod, $this->OAuthConsumer, $this->OAuthToken);
		
		/* Build cURL Request */
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $Request->to_url());
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($this->noSSLCheck){
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		}
		
		/* file upload */
		if ($file !== null){
			$data = array('file' => "@$file");
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		curl_close($ch);
		$output = json_decode($content, true);

		
		if (isset($output['error']) or $status>=300 or $status<200) {
			if(isset($output['error']) && is_string($output['error'])) $message = $output['error'];
			elseif(isset($output['error']['hash']) && $output['error']['hash'] != '') $message = (string) $output['error']['hash'];
			else $message = '('.$status.') Invalid response.';
			throw new DropboxException($message);
		} else {
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