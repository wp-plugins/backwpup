<?PHP
class BackWPup_api {

	private $apiurl='https://api.backwpup.com/1/';
	private $headers=array();

	public function __construct() {
		global $wp_version,$backwpup_cfg;
		if (!defined('BACKWPUP_VERSION'))
			define('BACKWPUP_VERSION','0.0');
		$blogurl=trim(get_bloginfo('url'));
		$this->headers['User-Agent']='BackWPup/'.BACKWPUP_VERSION.' WordPress/'.$wp_version;
		$this->headers['Authorization']='Basic '.base64_encode(BACKWPUP_VERSION.':'.md5($blogurl));
		$this->headers['Referer']=$blogurl;
		//Add filter for Plugin Updates
		add_filter('pre_set_site_transient_update_plugins', array($this,'plugin_update_check'));
		//Add filter to take over the Plugin info screen
		add_filter('plugins_api',  array($this,'plugin_infoscreen'), 10, 3);
		//move appapi to config
		if (function_exists('backwpup_get_option')) {
			$apiapp=backwpup_get_option('api','apiapp');
			if (empty($apiapp)) {
				$data = new stdClass;
				$data->checked = true;
				$this->plugin_update_check($data);
			}
			$apiapp=unserialize(backwpup_decrypt(backwpup_get_option('api','apiapp'),md5($blogurl)));
			if (!is_array($backwpup_cfg))
				$backwpup_cfg=array();
			if (!empty($apiapp) and is_array($apiapp))
				$backwpup_cfg=array_merge($backwpup_cfg,$apiapp);
		}
	}
	
	//API for cron trigger
	public function cronupdate() {
		global $wpdb,$backwpup_cfg;
		if (empty($backwpup_cfg['apicronservice']))
			return;
		$post=array();
		$post['ACTION']='cronupdate';
		$post['OFFSET']=get_option('gmt_offset');
		if (!empty($backwpup_cfg['httpauthuser']) and !empty($backwpup_cfg['httpauthpassword']))
			$post['httpauth']=base64_encode($backwpup_cfg['httpauthuser'].':'.backwpup_decrypt($backwpup_cfg['httpauthpassword']));
		$activejobs=$wpdb->get_col("SELECT main_name FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='activetype' AND value='backwpupapi' ORDER BY main_name");
		if (!empty($activejobs)) {
			foreach ($activejobs as $mainname) {
				$jobid=backwpup_get_option($mainname,'jobid');
				$cron=backwpup_get_option($mainname,'cron');
				$abspath='';
				if (WP_PLUGIN_DIR==ABSPATH.'/wp-content/plugins')
					$abspath='ABSPATH='.urlencode(str_replace('\\','/',ABSPATH)).'&';
				if (!empty($cron)) {
					$post["JOBCRON[".$jobid."]"]=$cron;
					$post["RUNURL[".$jobid."]"]=BACKWPUP_PLUGIN_BASEURL.'/backwpup-job.php?'.$abspath.'_wpnonce='.$backwpup_cfg['apicronservicekey'].'&starttype=apirun&jobid='.$jobid;
				}
			}
		}
		$raw_response = wp_remote_post($this->apiurl, array('sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			return true;
		else
			return false;
	}

	//check for Plugin Updates
	public function plugin_update_check($checked_data) {
		if (empty($checked_data->checked))
			return $checked_data;
		// Start checking for an update
		$post=array();
		$post['ACTION']='updatecheck';
		$raw_response = wp_remote_post($this->apiurl, array( 'sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
			$response = unserialize(wp_remote_retrieve_body($raw_response));
			if (is_object($response) && !empty($response->apiapps)) {
				backwpup_update_option('api','apiapp',$response->apiapps);
				unset($response->apiapps);
			}
			if (is_object($response) && !empty($response->slug))
				$checked_data->response[BACKWPUP_PLUGIN_BASENAME.'/backwpup.php'] = $response;
		}
		return $checked_data;
	}

	//infoscreen
	public function plugin_infoscreen($def, $action, $args) {
		if (!isset($args->slug) or $args->slug != BACKWPUP_PLUGIN_BASENAME)
			return false;
		$post=array();
		$post['ACTION']='updateinfo';
		$request = wp_remote_post($this->apiurl, array( 'sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (is_wp_error($request)) {
			$res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
		} else {
			$res = unserialize(wp_remote_retrieve_body($request));	
			if ($res === false)
				$res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
		}
		return $res;
	}
	
	//delete blog
	public function delete() {
		$post=array();
		$post['ACTION']='delete';
		$raw_response=wp_remote_post($this->apiurl, array('sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			return true;
		else
			return false;
	}
	
	//box.net Proxy
	public function boxnetauthproxy($ticket,$callback) {
		$post=array();
		$post['ACTION']='boxnetproxy';
		$post['TICKET']=$ticket;
		$post['CALLBACK']=$callback;
		$raw_response=wp_remote_post($this->apiurl, array('sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			return base64_decode(wp_remote_retrieve_body($raw_response));
		else
			return false;
	}
}
$backwpupapi=new BackWPup_api();
?>