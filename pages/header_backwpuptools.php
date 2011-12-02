<?PHP
if (!defined('ABSPATH')) 
	die();

//add Help
get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content'	=>
	'<p>' . '</p>'
) );
?>