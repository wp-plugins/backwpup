<?php

/**
 * SugarSync class
 *
 * This source file can be used to communicate with SugarSync (http://sugarsync.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-dropbox-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 *
 * Changelog since 1.0.0
 * - fixed some issues with generation off the basestring
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
 * @version		1.0.0
 *
 * @copyright	Copyright (c), Daniel Huesken. All rights reserved.
 * @license		BSD License
 */

class SugarSync {

	// debug
	const DEBUG = true;

	// url for the sugarsync-api
	const API_URL = 'https://api.sugarsync.com';

	// current version
	const VERSION = '1.0.0';
	
	
	/**
	 * The Auth-token
	 *
	 * @var	string
	 */
	private $AuthToken = '';

	
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
	public function __construct($email, $password, $accessKeyId, $privateAccessKey)
	{
		if(!is_string($email) or empty($email)) 
			throw new SugarSyncException('You must set Account E-Mail!');
		if(!is_string($password) or empty($password)) 
			throw new SugarSyncException('You must set Account Password!');
		if(!is_string($accessKeyId) or empty($accessKeyId)) 
			throw new SugarSyncException('You must Developer access Key!');
		if(!is_string($privateAccessKey) or empty($privateAccessKey)) 
			throw new SugarSyncException('You must  Developer access Secret!');
		
		//auth xml
		$auth ='<?xml version="1.0" encoding="UTF-8" ?>';
		$auth.='<authRequest>';
		$auth.='<username>'.utf8_encode($email).'</username>';
		$auth.='<password>'.utf8_encode($password).'</password>';
		$auth.='<accessKeyId>'.utf8_encode($accessKeyId).'</accessKeyId>';
		$auth.='<privateAccessKey>'.utf8_encode($privateAccessKey).'</privateAccessKey>';
		$auth.='</authRequest>';
		// init
		$curl = curl_init();
		//set otions
		curl_setopt($curl,CURLOPT_URL,self::API_URL .'/authorization');
		//curl_setopt($curl,CURLOPT_USERAGENT,'PHP SugarSync/'. self::VERSION);
		if(ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
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
			if (preg_match('/Location:(.*?)\n/', $response, $matches)) 
				$this->AuthToken=$matches[1];
		} else {
			if ($curlgetinfo['http_code']==401)
				throw new SugarSyncException('Http Error: '. $curlgetinfo['http_code'].' Authorization required or worng.');
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
		$data = (string) $data;
		$method = (string) $method;

		// validate method
		if(!in_array($method, $allowedMethods)) 
			throw new SugarSyncException('Unknown method ('. $method .'). Allowed methods are: '. implode(', ', $allowedMethods));

		// check auth token
		if(!is_string($this->AuthToken) or empty($this->AuthToken) or !strripos($this->AuthToken,self::API_URL)) 
			throw new SugarSyncException('Auth Token not set correctly!!');
		else
			$headers[] = 'Authorization: '.$this->AuthToken;

		// init
		$curl = curl_init();
		//set otions
		curl_setopt($curl,CURLOPT_USERAGENT,'PHP SugarSync/'. self::VERSION);
		if(ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
		
	
		if ($method == 'POST') {		
			$headers[] = 'Content-Type: application/xml; charset=UTF-8';
			//$url=str_replace(':','/',$url);
			curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
			echo $data;
			curl_setopt($curl,CURLOPT_POST,true);
		} elseif ($method == 'PUT') {
			if (is_file($data) and is_readable($data)) {
				$datafilefd=fopen($data,'r');
				curl_setopt($curl,CURLOPT_PUT,true);
				curl_setopt($curl,CURLOPT_INFILE,$datafilefd);
				curl_setopt($curl,CURLOPT_INFILESIZE,filesize($data));
			} elseif (is_sting($data) and !is_file($data)) {
				curl_setopt($curl,CURLOPT_PUT,true);
				curl_setopt($curl,CURLOPT_INFILE,$data);
				curl_setopt($curl,CURLOPT_INFILESIZE,strnlen($data));
			} else {
				throw new SugarSyncException('Is not a readable file or string:'. $data);
			}
		} elseif ($method == 'DELETE') {
			curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'DELETE');
		} else {
			curl_setopt($curl,CURLOPT_POST,false);
		}

		// set headers
		curl_setopt($curl,CURLOPT_URL, $url);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($curl,CURLINFO_HEADER_OUT,self::DEBUG);

		// execute
		$response = curl_exec($curl);
		$curlgetinfo = curl_getinfo($curl);
		
		if (self::DEBUG) {
			echo "<pre>";
			var_dump($response);
			var_dump($curlgetinfo);
			echo "</pre>";
		}
		
		// fetch curl errors
		if (curl_errno($curl) != 0)
			throw new SugarSyncException('cUrl Error: '. curl_error($curl));
		
		curl_close($curl);
		
		if ($curlgetinfo['http_code']>=200 and $curlgetinfo['http_code']<300) {
			if (!empty($response))
				return simplexml_load_string($response);
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

	public function user() {
		$request=$this->doCall(self::API_URL .'/user');
		return $request;
	}

 
	public function get($url) {
		$request=$this->doCall($url,'','GET');
		return $request;
	}
	
	public function delete($url) {
		$request=$this->doCall($url,'DELTE');
	}

	
	public function getcontents($url,$start=0,$max=500) {
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
			
		$request=$this->doCall($url.'?'.$parameters);
		return $request;
	}

	public function createfile($url,$file,$name='') {
		if (empty($name))
			$name=basename($file);
		$name=utf8_encode($name);
		$xmlrequest ='<?xml version="1.0" encoding="UTF-8"?>';
		$xmlrequest.='<file>';
		$xmlrequest.='<displayName>'.$name.'</displayName>';
		if (!is_file($file)) 
			$xmlrequest.='<mediaType>'.mime_content_type($file).'</mediaType>';
		$xmlrequest.='</file>';
		$request=$this->doCall($url,$xmlrequest,'POST');
				
		//$request=$this->doCall($url,$file,'PUT');
	}
	
	public function createfolder($url,$folder) {
		$xmlrequest ='<?xml version="1.0" encoding="UTF-8"?>';
		$xmlrequest.='<folder>';
		$xmlrequest.='<displayName>'.utf8_encode($folder).'</displayName>';
		$xmlrequest.='</folder>';
		$request=$this->doCall($url,$xmlrequest,'POST');
	}
	
}


/**
 * SugarSync Exception class
 *
 * @author	Daniel Huesken <daniel@huersken-net.de>
 */
class SugarSyncException extends Exception {
}