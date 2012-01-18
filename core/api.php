<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

/**
 * Class for calling the BackWPup API
 */
class BackWPup_api {

	private $apiurl='https://api.backwpup.com/1/';
	private $headers=array();

	/**
	 *
	 */
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
		//Add filter to get api keys
		add_filter('backwpup_api_appkey',  array($this,'app_keys'), 10, 2);
		//Add action for cron updates
		add_action('backwpup_api_cron_update',  array($this,'cronupdate'));
		//Add action for delte blog from api
		add_action('backwpup_api_delete',  array($this,'delete'));
	}

	/**
	 *
	 * API for cron trigger
	 *
	 * @return bool
	 */
	public function cronupdate() {
		global $wpdb;
		if (!backwpup_get_option('cfg','apicronservicekey'))
			return true;
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

	/**
	 *
	 * Get data for plugin update check
	 *
	 * @param $checked_data
	 * @return mixed
	 */
	public function plugin_update_check($checked_data) {
		if (empty($checked_data->checked))
			return $checked_data;
		// Start checking for an update
		$post=array();
		$post['ACTION']='updatecheck';
		$saved=backwpup_get_option('temp','updatecheck');
		if (!empty($saved) and $saved['version']==BACKWPUP_VERSION and (time()-$saved['time'])<=43200) {
			$checked_data->response[BACKWPUP_PLUGIN_BASENAME] = $saved['response'];
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
				$checked_data->response[BACKWPUP_PLUGIN_BASENAME] = $response;
				backwpup_update_option('temp','updatecheck',array('time'=>time(),'version'=>BACKWPUP_VERSION,'response'=>$response));
			}
		}
		return $checked_data;
	}

	/**
	 *
	 * Get data to display the info screen on Plugin updates
	 *
	 * @param $def
	 * @param $action
	 * @param $args
	 * @return bool|array|WP_Error
	 */
	public function plugin_infoscreen($def, $action, $args) {
		if (strtolower($action)!='plugin_information' or $args->slug!='backwpup')
			return false;
		$post=array();
		$post['ACTION']='updateinfo';
		$saved=backwpup_get_option('temp','updateinfo');
		if (!empty($saved) and $saved['version']==BACKWPUP_VERSION and (time()-$saved['time'])<=43200)
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

	/**
	 *
	 * Delte blog form BackWPup API
	 *
	 * @return bool
	 */
	public function delete() {
		$post=array();
		$post['ACTION']='delete';
		$raw_response=wp_remote_post($this->apiurl, array('sslverify' => false, 'body'=>$post, 'headers'=>$this->headers));
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response))
			return true;
		else
			return false;
	}

	/**
	 *
	 * Get Keys for some Services
	 *
	 * @param string $appkey to get
	 * @param mixed $defauft to giv back if no return
	 * @return bool|mixed
	 */
	public function app_keys($appkey,$defauft=false) {
		$apiapp=backwpup_get_option('temp','apiapp');
		if (!$apiapp) {
			backwpup_update_option('temp','updatecheck','');
			$data = new stdClass;
			$data->checked = true;
			$this->plugin_update_check($data);
			$apiapp=backwpup_get_option('temp','apiapp');
		}
		$apiapp=unserialize(backwpup_decrypt($apiapp,md5(trim(get_bloginfo('url')))));
		if (!empty($apiapp[$appkey]))
			return $apiapp[$appkey];
		else
			return $defauft;
	}
}
new BackWPup_api();