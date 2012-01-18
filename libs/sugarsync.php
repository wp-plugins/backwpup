<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

class backwpup_SugarSync {

	// url for the sugarsync-api
	const API_URL = 'https://api.sugarsync.com';

	// current version
	const VERSION = '1.0.0';
	
	
	/**
	 * The Auth-token
	 *
	 * @var	string
	 */
	protected $AuthToken = '';
	
	protected $folder = '';
	
	protected $ProgressFunction = false;
	
// class methods
	/**
	 * Default constructor/Auth
	 *
	 * @return	void
	 * @param	string $email				The consumer E-Mail.
	 * @param	string $password			The consumer password.
	 * @param	string $accessKeyId			The developer access key.
	 * @param	string $privateAccessKey	The developer access scret.
	 */
	public function __construct($email, $password)
	{
		if(!is_string($email) or empty($email)) 
			throw new SugarSyncException('You must set Account E-Mail!');
		if(!is_string($password) or empty($password)) 
			throw new SugarSyncException('You must set Account Password!');
		
		//auth xml
		$auth ='<?xml version="1.0" encoding="UTF-8" ?>';
		$auth.='<authRequest>';
		$auth.='<username>'.utf8_encode($email).'</username>';
		$auth.='<password>'.utf8_encode($password).'</password>';
		$auth.='<accessKeyId>'.utf8_encode(apply_filters('backwpup_api_appkey','SUGARSYNC_ACCESSKEY')).'</accessKeyId>';
		$auth.='<privateAccessKey>'.utf8_encode(apply_filters('backwpup_api_appkey','SUGARSYNC_PRIVATEACCESSKEY')).'</privateAccessKey>';
		$auth.='</authRequest>';
		// init
		$curl = curl_init();
		//set otions
		curl_setopt($curl,CURLOPT_URL,self::API_URL .'/authorization');
		curl_setopt($curl,CURLOPT_USERAGENT,'PHP SugarSync/'. self::VERSION);
		if(ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($curl,CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl,CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,2);
		curl_setopt($curl,CURLOPT_SSLVERSION,3);
		if (is_file(dirname(__FILE__).'/cert/gd-class2-root.pem'))
			curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__).'/cert/gd-class2-root.pem');
		curl_setopt($curl,CURLOPT_HEADER,true);
		curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/xml; charset=UTF-8'));
		curl_setopt($curl,CURLOPT_POSTFIELDS,$auth);
		curl_setopt($curl,CURLOPT_POST,true);		
		// execute
		$response = curl_exec($curl);
		$curlgetinfo = curl_getinfo($curl);
		// fetch curl errors
		if (curl_errno($curl) != 0)
			throw new SugarSyncException('cUrl Error: '. curl_error($curl));
		
		curl_close($curl);
		
		if ($curlgetinfo['http_code']>=200 and $curlgetinfo['http_code']<=204) {
			if (preg_match('/Location:(.*?)\r/i', $response, $matches)) 
				$this->AuthToken=$matches[1];
		} else {
			if ($curlgetinfo['http_code']==401)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' Authorization required.');
			elseif ($curlgetinfo['http_code']==403)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' (Forbidden)  Authentication failed.');
			elseif ($curlgetinfo['http_code']==404)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' Not found');
			else
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code']);
		}
	}	
	
	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $url						The url to call.
	 * @param	string[optiona] $data			File on put, xml on post.
	 * @param	string[optional] $method		The method to use. Possible values are GET, POST, PUT, DELETE.
	 */
	private function doCall($url, $data = '', $method = 'GET') {
		// allowed methods
		$allowedMethods = array('GET','POST','PUT','DELETE');

		// redefine
		$url = (string) $url;
		$method = (string) $method;

		// validate method
		if(!in_array($method, $allowedMethods)) 
			throw new SugarSyncException('Unknown method ('. $method .'). Allowed methods are: '. implode(', ', $allowedMethods));

		// check auth token
		if(!is_string($this->AuthToken) or empty($this->AuthToken) or !strripos($this->AuthToken,self::API_URL)) 
			throw new SugarSyncException('Auth Token not set correctly!!');
		else
			$headers[] = 'Authorization: '.$this->AuthToken;
		$headers[] = 'Expect:';
		
		// init
		$curl = curl_init();
		//set otions
		curl_setopt($curl,CURLOPT_URL, $url);
		curl_setopt($curl,CURLOPT_USERAGENT,'PHP SugarSync/'. self::VERSION);
		if(ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl,CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,2);
		curl_setopt($curl,CURLOPT_SSLVERSION,3);
		if (is_file(dirname(__FILE__).'/cert/gd-class2-root.pem'))
			curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__).'/cert/gd-class2-root.pem');
		
		if ($method == 'POST') {	
			$headers[]='Content-Type: application/xml; charset=UTF-8';
			curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
			curl_setopt($curl,CURLOPT_POST,true);
			$headers[]='Content-Length: '.strlen($data);	
		} elseif ($method == 'PUT') {
			if (is_file($data) and is_readable($data)) {
				$headers[]='Content-Length: '.filesize($data);
				$datafilefd=fopen($data,'r');
				curl_setopt($curl,CURLOPT_PUT,true);
				curl_setopt($curl,CURLOPT_INFILE,$datafilefd);
				curl_setopt($curl,CURLOPT_INFILESIZE,filesize($data));
				curl_setopt($curl,CURLOPT_READFUNCTION, array(&$this, '_read_cb'));
			}  else {
				throw new SugarSyncException('Is not a readable file:'. $data);
			}
		} elseif ($method == 'DELETE') {
			curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'DELETE');
		} else {
			curl_setopt($curl,CURLOPT_POST,false);
		}

		// set headers
		curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);
		// execute
		$response = curl_exec($curl);
		$curlgetinfo = curl_getinfo($curl);
		
		// fetch curl errors
		if (curl_errno($curl) != 0)
			throw new SugarSyncException('cUrl Error: '. curl_error($curl));
		curl_close($curl);
		if (!empty($datafilefd) and is_resource($datafilefd))
			fclose($datafilefd);
		
		if ($curlgetinfo['http_code']>=200 and $curlgetinfo['http_code']<300) {
			if (false !== stripos($curlgetinfo['content_type'],'xml') and !empty($response))
				return simplexml_load_string($response);
			else
				return $response;
		} else {
			if ($curlgetinfo['http_code']==401)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' Authorization required.');
			elseif ($curlgetinfo['http_code']==403)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' (Forbidden)  Authentication failed.');
			elseif ($curlgetinfo['http_code']==404)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' Not found');
			else
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code']);
		}
	}

	private function _read_cb($curl, $fd, $length) {
		$data = fread($fd, $length);
		$len = strlen($data);
		if (isset($this->ProgressFunction)) {
			call_user_func($this->ProgressFunction, $len);
		}
		return $data;
	}

	public function chdir($folder,$root='') {
		$folder=rtrim($folder,'/');
		if (substr($folder,0,1)=='/' or empty($this->folder)) {
			if (!empty($root))
				$this->folder=$root;
			else
				throw new SugarSyncException('chdir: root folder must set!');
		}
		$folders=explode('/',$folder);
		foreach ($folders as $dir) {
			if ($dir=='..') {
				$contents=$this->doCall($this->folder);
				if (!empty($contents->parent))
					$this->folder=$contents->parent;
			} elseif (!empty($dir) and $dir!='.') {
				$isdir=false;
				$contents=$this->getcontents('folder');
				foreach ($contents->collection as $collection) {
					if (strtolower($collection->displayName)==strtolower($dir)) {
						$isdir=true;
						$this->folder=$collection->ref;
						break;
					}
				}
				if (!$isdir)
					throw new SugarSyncException('chdir: Folder '. $folder.' not exitst');
			}
		}
		return $this->folder;
	}
	
	public function showdir($folderid) {
		$showfolder='';
		while ($folderid) {
			$contents=$this->doCall($folderid);
			$showfolder=$contents->displayName.'/'.$showfolder;
			if (isset($contents->parent))
				$folderid=$contents->parent;
			else
				break;
		}
		return $showfolder;
	}
	
	public function mkdir($folder,$root='') {
		$savefolder=$this->folder;
		$folder=rtrim($folder,'/');
		if (substr($folder,0,1)=='/' or empty($this->folder)) {
			if (!empty($root))
				$this->folder=$root;
			else
				throw new SugarSyncException('mkdir: root folder must set!');
		} 
		$folders=explode('/',$folder);
		foreach ($folders as $dir) {
			if ($dir=='..') {
				$contents=$this->doCall($this->folder);
				if (!empty($contents->parent))
					$this->folder=$contents->parent;
			} elseif (!empty($dir) and $dir!='.') {
				$isdir=false;
				$contents=$this->getcontents('folder');
				foreach ($contents->collection as $collection) {
					if (strtolower($collection->displayName)==strtolower($dir)) {
						$isdir=true;
						$this->folder=$collection->ref;
						break;
					}
				}
				if (!$isdir) {
					$request=$this->doCall($this->folder,'<?xml version="1.0" encoding="UTF-8"?><folder><displayName>'.utf8_encode($dir).'</displayName></folder>','POST');
					$contents=$this->getcontents('folder');
					foreach ($contents->collection as $collection) {
						if (strtolower($collection->displayName)==strtolower($dir)) {
							$isdir=true;
							$this->folder=$collection->ref;
							break;
						}
					}
				}
			}
		}
		$this->folder=$savefolder;
		return true;
	}	
	
	
	public function user() {
		return $this->doCall(self::API_URL .'/user');
	}

 
	public function get($url) {
		return $this->doCall($url,'','GET');
	}
	
	public function download($url) {
		return $this->doCall($url.'/data');
	}
	
	public function delete($url) {
		return $this->doCall($url,'','DELETE');
	}

	
	public function getcontents($type='',$start=0,$max=500) {
		$parameters='';
		if (strtolower($type)=='folder' or strtolower($type)=='file')
			$parameters.='type='.strtolower($type);
		if (!empty($start) and is_integer($start)) {
			if (!empty($parameters))
				$parameters.='&';
			$parameters.='start='.$start;
			
		}
		if (!empty($max) and is_integer($max)) {
			if (!empty($parameters))
				$parameters.='&';
			$parameters.='max='.$max;
		}	
			
		$request=$this->doCall($this->folder.'/contents?'.$parameters);
		return $request;
	}

	public function upload($file,$name='') {
		if (empty($name))
			$name=basename($file);
		$xmlrequest ='<?xml version="1.0" encoding="UTF-8"?>';
		$xmlrequest.='<file>';
		$xmlrequest.='<displayName>'.utf8_encode($name).'</displayName>';
		if (!is_file($file)) {
			$finfo = fopen($file,'r');
			$xmlrequest.='<mediaType>'.mime_content_type($finfo).'</mediaType>';
			fclose($finfo);
		}
		$xmlrequest.='</file>';
		$request=$this->doCall($this->folder,$xmlrequest,'POST');
		$getfiles=$this->getcontents('file');
		foreach ($getfiles->file as $getfile) {
			if ($getfile->displayName==$name) {
				$this->doCall($getfile->ref.'/data',$file,'PUT');
				return $getfile->ref;
			}
		}	
	}

	public function setProgressFunction($function) {
		if (!empty($function))
			$this->ProgressFunction = $function;
		else
			$this->ProgressFunction = false;
	}	
}

/**
 * SugarSync Exception class
 *
 * @author	Daniel Huesken <daniel@huersken-net.de>
 */
class SugarSyncException extends Exception {
}