jQuery(document).ready( function($) {

	$('.jobtype-select').change(function() {
		if ( true == $('#jobtype-select-FILE').attr('checked') || true ==  $('#jobtype-select-DB').attr('checked') || true == $('#jobtype-select-WPEXP').attr('checked')) {
			$('#fileformart').show();
			$('#toftp').show();
			$('#toamazon').show();
			$('#todir').show();
			$('#tomail').show();
		} else {
			$('#fileformart').hide();
			$('#toftp').hide();
			$('#toamazon').hide();
			$('#todir').hide();
			$('#tomail').hide();
		}
		if ( true == $('#jobtype-select-DB').attr('checked') || true == $('#jobtype-select-CHECK').attr('checked') || true == $('#jobtype-select-OPTIMIZE').attr('checked')) {
			$('#databasejobs').show();
		} else {
			$('#databasejobs').hide();
		}
		if ( true == $('#jobtype-select-DB').attr('checked')) {
			$('#dbshortinsert').show();
		} else {
			$('#dbshortinsert').hide();
		}

		if ( true == $('#jobtype-select-FILE').attr('checked')) {
			$('#filebackup').show();
		} else {
			$('#filebackup').hide();
		}
	});

	$('#mailmethod').change(function() {
		if ( 'SMTP' == $('#mailmethod').val()) {
			$('#mailsmtp').show();
			$('#mailsendmail').hide();
		} else if ( 'Sendmail' == $('#mailmethod').val()) {
			$('#mailsmtp').hide();
			$('#mailsendmail').show();
		} else {
			$('#mailsmtp').hide();
			$('#mailsendmail').hide();		
		}
	});
	
	function awsgetbucket() {
		var awsAccessKey = $('#awsAccessKey').val();
		var awsSecretKey = $('#awsSecretKey').val();
		var awsBucket = $('#awsBucketselected').val();
		var data = {
			action: 'backwpup_get_aws_buckets',
			awsAccessKey: awsAccessKey,
			awsSecretKey: awsSecretKey,
			selected: awsBucket
		};
		$.post(ajaxurl, data, function(response) {
			$('#awsBucket').remove();
			$('#awsBucketselected').after(response);
		});		
	}
	
	awsgetbucket();
	$('#awsAccessKey').change(function() {awsgetbucket();});
	$('#awsSecretKey').change(function() {awsgetbucket();});
	
	$('#reloadbuckets').onClick(awsgetbucket());
	
	if ( $('#title').val() == '' )
		$('#title').siblings('#title-prompt-text').css('visibility', '');
	$('#title-prompt-text').click(function(){
		$(this).css('visibility', 'hidden').siblings('#title').focus();
	});
	$('#title').blur(function(){
		if (this.value == '')
			$(this).siblings('#title-prompt-text').css('visibility', '');
	}).focus(function(){
		$(this).siblings('#title-prompt-text').css('visibility', 'hidden');
	}).keydown(function(e){
		$(this).siblings('#title-prompt-text').css('visibility', 'hidden');
		$(this).unbind(e);
	});

});