jQuery(document).ready( function($) {
	$('.waiting').each( function (index) {
		var mode = $('input[name="mode"]').val();
		var jobid = $(this).attr('id').replace('image-wait-','');
		var data = {
			action: 'backwpup_show_info_td',
			backwpupajaxpage: 'backwpup',
			jobid: jobid,
			mode: mode
		};
		$.post(ajaxurl, data, function(response) {
			$('#image-wait-' + jobid).css('display','none');
			$('#image-wait-' + jobid).after(response);
		});		
	});	
});

