<?PHP
class backwpup_api {

	private $apiurl='https://api.backwpup.com';
	private $headers=array();

	public function __construct() {
		global $wp_version;
		$this->headers['User-Agent']='BackWPup/'.BACKWPUP_VERSION.' WordPress/'.$wp_version;
		$this->headers['Authorization']='Basic '.base64_encode(BACKWPUP_VERSION.':'.md5(trim(get_bloginfo('url'))));
		$this->headers['Referer']=trim(get_bloginfo('url'));
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
			$post['httpauth']=base64_encode($backwpup_cfg['httpauthuser'].':'.base64_decode($backwpup_cfg['httpauthpassword']));
		$activejobs=$wpdb->get_col("SELECT main_name FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'JOB_%' AND name='activated' AND value='1' ORDER BY main_name");
		if (!empty($activejobs)) {
			foreach ($activejobs as $mainname) {
				$jobid=backwpup_get_option($mainname,'jobid');
				$cron=backwpup_get_option($mainname,'cron');
				if (!empty($cron))
					$post["JOBCRON[".$jobid."]"]=$cron;
			}
		}
		$raw_response = wp_remote_post($this->apiurl, array('sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			return true;
		else
			return false;
	}

	//check for Plugin Updates
	public function plugin_update_check() {
		// Start checking for an update
		$post=array();
		$post['ACTION']='updatecheck';
		$raw_response = wp_remote_post($this->apiurl, array( 'sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			$response = unserialize(wp_remote_retrieve_body($raw_response));
		return $response;
	}
	
	//infoscreen
	public function plugin_infoscreen() {
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
	
	//get Keys
	public function get_keys() {
		$keys=backwpup_get_option('API','KEYS');
		if (!is_array($keys) or empty($keys['lastupdate']) or $keys['lastupdate']<time()-(60*60*24*7)) {
			$post=array();
			$post['ACTION']='getkeys';
			$raw_response = wp_remote_post($this->apiurl, array( 'sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
			if (!is_wp_error($raw_response) and 200 == wp_remote_retrieve_response_code($raw_response))
				$keys = unserialize(trim(base64_decode(wp_remote_retrieve_body($raw_response))));
			if (is_array($keys)) {
				$keys['lastupdate']=time();
				backwpup_update_option('API','KEYS',$keys);
			}
		}
		return $keys;
	}
	
	//delete blog
	public function delete() {
		$post=array();
		$post['ACTION']='delete';
		delete_transient('backwpup_api');
		$raw_response=wp_remote_post($this->apiurl, array('sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			return true;
		else
			return false;
	}
}

function backwpup_api_plugin_update_check($checked_data) {
	if (empty($checked_data->checked))
		return $checked_data;
	$backwpupapi=new backwpup_api();
	$response=$backwpupapi->plugin_update_check();
	if (is_object($response) && !empty($response)) // Feed the update data into WP updater
		$checked_data->response[BACKWPUP_PLUGIN_BASEDIR.'/backwpup.php'] = $response;
	return $checked_data;
}
//Add filter for Plugin Updates from backwpup.com
add_filter('pre_set_site_transient_update_plugins', 'backwpup_api_plugin_update_check');

function backwpup_api_plugin_infoscreen($def, $action, $args) {
	if (!isset($args->slug) or $args->slug != BACKWPUP_PLUGIN_BASEDIR)
		return false;
	$backwpupapi=new backwpup_api();
	$res=$backwpupapi->plugin_infoscreen();
	return $res;
}
//Add filter to take over the Plugin info screen
add_filter('plugins_api', 'backwpup_api_plugin_infoscreen', 10, 3);
?>