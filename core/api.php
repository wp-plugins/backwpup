<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

class BackWPup_api {

	private $apiurl='https://api.backwpup.com/1/';
	private $headers=array();

	public function __construct() {
		global $wp_version;
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
			$apiapp=backwpup_get_option('temp','apiapp');
			if (!$apiapp) {
				$data = new stdClass;
				$data->checked = true;
				$this->plugin_update_check($data);
				$apiapp=backwpup_get_option('temp','apiapp');
			}
			$apiapp=unserialize(backwpup_decrypt($apiapp,md5($blogurl)));
			if ($apiapp and is_array($apiapp)) {
				$alloptions=wp_cache_get( 'options', 'backwpup' );
				foreach ($apiapp as $app => $appvalue) {
					if (!isset($alloptions['cfg'][$app]))
						$alloptions['cfg'][$app]=$appvalue;
				}
				wp_cache_set( 'options', $alloptions, 'backwpup' );
			}
		}
	}
	
	//API for cron trigger
	public function cronupdate() {
		global $wpdb;
		if (backwpup_get_option('cfg','apicronservicekey'))
			return;
		$post=array();
		$post['ACTION']='cronupdate';
		$post['OFFSET']=get_option('gmt_offset');
		if (backwpup_get_option('cfg','httpauthuser') and backwpup_get_option('cfg','httpauthpassword'))
			$post['httpauth']=base64_encode(backwpup_get_option('cfg','httpauthuser').':'.backwpup_decrypt(backwpup_get_option('cfg','httpauthpassword')));
		$activejobs=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='activetype' AND value='backwpupapi' ORDER BY main");
		if (!empty($activejobs)) {
			foreach ($activejobs as $mainname) {
				$jobid=backwpup_get_option($mainname,'jobid');
				$cron=backwpup_get_option($mainname,'cron');
				if (!empty($cron)) {
					$post["JOBCRON[".$jobid."]"]=$cron;
					$url=backwpup_jobrun_url('apirun',$jobid);
					$post["RUNURL[".$jobid."]"]=$url['url'];
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
		$saved=backwpup_get_option('temp','updatecheck');
		if ($saved['version']==BACKWPUP_VERSION and $saved['time']>time()-43200) {
			$checked_data->response[BACKWPUP_PLUGIN_BASENAME.'/'.BACKWPUP_PLUGIN_FILE] = $saved['response'];
			return $checked_data;
		}
		$raw_response = wp_remote_post($this->apiurl, array( 'sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
			$response = unserialize(wp_remote_retrieve_body($raw_response));
			if (is_object($response) && !empty($response->apiapps)) {
				backwpup_update_option('temp','apiapp',$response->apiapps);
				unset($response->apiapps);
			}
			if (is_object($response) && !empty($response->slug)) {
				$checked_data->response[BACKWPUP_PLUGIN_BASENAME.'/'.BACKWPUP_PLUGIN_FILE] = $response;
				backwpup_update_option('temp','updatecheck',array('time'=>time(),'version'=>BACKWPUP_VERSION,'response'=>$response));
			}
		}
		return $checked_data;
	}

	//infoscreen
	public function plugin_infoscreen($def, $action, $args) {
		if (!isset($args->slug) or $args->slug != BACKWPUP_PLUGIN_BASENAME)
			return false;
		$post=array();
		$post['ACTION']='updateinfo';
		$saved=backwpup_get_option('temp','updateinfo');
		if ($saved['version']==BACKWPUP_VERSION and $saved['time']>time()-43200)
			return $saved['return'];
		$request = wp_remote_post($this->apiurl, array( 'sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (is_wp_error($request)) {
			$res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
		} else {
			$res = unserialize(wp_remote_retrieve_body($request));
			if ($res === false)
				$res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
			else
				backwpup_update_option('temp','updateinfo',array('time'=>time(),'version'=>BACKWPUP_VERSION,'return'=>$res));
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
}
global $backwpupapi;
$backwpupapi=new BackWPup_api();
?>