jQuery(document).ready( function($) {
	$.ajaxSetup({ cache: false });
	var logfile = $('input[name="logfile"]').val();
	var data = {
		action: 'backwpup_working_update',
		backwpupajaxpage: 'backwpupworking',
		logfile: logfile
	};
	var refreshId = setInterval(function() {
		$("#showworking").load(ajaxurl,data);
		$("#stopworking").each(function(index) {
			$("#message").remove();
			clearInterval(refreshId);
		});
	}, 100);
});

