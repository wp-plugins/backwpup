jQuery(document).ready( function($) {

	$('.jobtype-select').change(function() {
		if ( true == $('#jobtype-select-FILE').attr('checked') || true ==  $('#jobtype-select-DB').attr('checked') || true == $('#jobtype-select-WPEXP').attr('checked')) {
			$('#fileformart').show();
			$('#toftp').show();
			$('#toamazon').show();
			$('#tomsazure').show();
			$('#torsc').show();
			$('#todropbox').show();
			$('#tosugarsync').show();
			$('#todir').show();
			$('#tomail').show();
		} else {
			$('#fileformart').hide();
			$('#toftp').hide();
			$('#toamazon').hide();
			$('#tomsazure').hide();
			$('#torsc').hide();
			$('#todropbox').hide();
			$('#tosugarsync').hide();
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
			awsselected: awsBucket
		};
		$.post(ajaxurl, data, function(response) {
			$('#awsBucket').remove();
			$('#awsBucketselected').after(response);
		});		
	}
	
	$('#awsAccessKey').change(function() {awsgetbucket();});
	$('#awsSecretKey').change(function() {awsgetbucket();});

	function msazuregetcontainer() {
		var msazureHost = $('#msazureHost').val();
		var msazureAccName = $('#msazureAccName').val();
		var msazureKey = $('#msazureKey').val();
		var msazureContainer = $('#msazureContainerselected').val();
		var data = {
			action: 'backwpup_get_msazure_container',
			msazureHost: msazureHost,
			msazureAccName: msazureAccName,
			msazureKey: msazureKey,
			msazureselected: msazureContainer
		};
		$.post(ajaxurl, data, function(response) {
			$('#msazureContainer').remove();
			$('#msazureContainerselected').after(response);
		});		
	}
	
	$('#msazureHost').change(function() {msazuregetcontainer();});
	$('#msazureAccName').change(function() {msazuregetcontainer();});
	$('#msazureKey').change(function() {msazuregetcontainer();});
	
	function rscgetcontainer() {
		var rscUsername = $('#rscUsername').val();
		var rscAPIKey = $('#rscAPIKey').val();
		var rscContainer = $('#rscContainerselected').val();
		var data = {
			action: 'backwpup_get_rsc_container',
			rscUsername: rscUsername,
			rscAPIKey: rscAPIKey,
			rscselected: rscContainer
		};
		$.post(ajaxurl, data, function(response) {
			$('#rscContainer').remove();
			$('#rscContainerselected').after(response);
		});		
	}
	
	$('#rscUsername').change(function() {rscgetcontainer();});
	$('#rscAPIKey').change(function() {rscgetcontainer();});
	
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

