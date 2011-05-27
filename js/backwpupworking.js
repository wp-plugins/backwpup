jQuery(document).ready( function($) {
	$.ajaxSetup({ cache: false });
	var data = {
		action: 'backwpup_working_update',
		backwpupajaxpage: 'backwpupworking',
		logfile: jQuery('input[name="logfile"]').val(),
		_ajax_nonce: jQuery('#backwpupworkingajaxnonce').val()
	};
	var refreshId = setInterval(function() {
		$("#showworking").load(ajaxurl,data);
		$("#stopworking").each(function(index) {
			$("#message").remove();
			clearInterval(refreshId);
		});
	}, 100);
});

