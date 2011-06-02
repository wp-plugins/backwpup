jQuery(document).ready( function($) {
	$.ajaxSetup({ cache: false });
	var data = {
		action: 'backwpup_working_update',
		backwpupajaxpage: 'backwpupworking',
		_ajax_nonce: jQuery('#backwpupworkingajaxnonce').val()
	};
	var refreshId = setInterval(function() {
		$.ajax({type: 'POST',
				url: ajaxurl,
				data: data,
				dataType: 'json',
				success: function(rundata) {
						if ( '' != rundata.LOG ) {
							$('#showworking').append(rundata.LOG);
						}
						if ( '' != rundata.ERROR ) {
							$('#errors').replaceWith('<span id=\"errors\">'+rundata.ERROR+'</span>');
						}
						if ( '' != rundata.WARNING ) {
							$('#warnings').replaceWith('<span id=\"warnings\">'+rundata.WARNING+'</span>');
						}
						if ( '' != rundata.STEPSPERSENT ) {
							$('#progressstep').replaceWith('<div id="progressstep" style="background-color:gray;height:20px;width:0;color:black;text-align:center">'+rundata.STEPSPERSENT+'%</div>');
							$('#progressstep').css('width', parseFloat(rundata.STEPSPERSENT)+'%');
						}
						if ( '' != rundata.STEPPERSENT ) {
							$('#progresssteps').replaceWith('<div id="progresssteps" style="background-color:yellow;height:20px;width:0;color:black;text-align:center">'+rundata.STEPPERSENT+'%</div>');
							$('#progresssteps').css('width', parseFloat(rundata.STEPPERSENT)+'%');
						}						
					},
				});
		$("#stopworking").each(function(index) {
			$("#message").remove();
			clearInterval(refreshId);
		});
	}, 50);
});

