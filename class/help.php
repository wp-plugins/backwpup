<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

class BackWPup_Help {
	public function help() {
		if (method_exists(get_current_screen(),'add_help_tab')) {
			get_current_screen()->add_help_tab( array(
			'id'      => 'plugininfo',
			'title'   => __('Plugin Info','backwpup'),
			'content' =>
			'<p><a href="http://backwpup.com" target="_blank">BackWPup</a> v. '.backwpup_get_version().', <a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL2</a> &copy 2009-'.date('Y').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a></p><p>'.__('BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup').'</p>'
			) );
			get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:','backwpup' ) . '</strong></p>' .
			'<p>' . __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="https://flattr.com/thing/345067/BackWPup" target="_blank">Flattr</a>','backwpup' ) . '</p>'
			);
		} elseif (function_exists('add_contextual_help')) { //for WP < 3.3 help
			add_contextual_help( get_current_screen(),
				'<p><a href="http://backwpup.com" target="_blank">BackWPup</a> v. '.backwpup_get_version().', <a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL2</a> &copy 2009-'.date('Y').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a></p><p>'.__('BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup').'</p>'.
					'<p><strong>' . __( 'For more information:','backwpup' ) . '</strong></p><p>' .
					' ' . __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="https://flattr.com/thing/345067/BackWPup" target="_blank">Flattr</a>','backwpup' ) . ' ' .
					'</p>'
			);
		}
	}

	public function add_tab($tab=array()) {
		if (method_exists(get_current_screen(),'add_help_tab'))
			get_current_screen()->add_help_tab( $tab );
		elseif (function_exists('add_contextual_help'))  //for WP < 3.3 help
			add_contextual_help( get_current_screen(),'<p><strong>' . $tab['title'] . '</strong></p>' .content);
	}

}