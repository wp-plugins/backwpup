jQuery(document).ready( function($) {

	$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
	
	$('.jobtype-select').change(function() {
		if ( true == $('#jobtype-select-FILE').attr('checked') || true ==  $('#jobtype-select-DB').attr('checked') || true == $('#jobtype-select-WPEXP').attr('checked')) {
			$('#backwpup_jobedit_backupfile').show();
			$('#backwpup_jobedit_destftp').show();
			$('#backwpup_jobedit_dests3').show();
			$('#backwpup_jobedit_destazure').show();
			$('#backwpup_jobedit_destrsc').show();
			$('#backwpup_jobedit_destdropbox').show();
			$('#backwpup_jobedit_destsugarsync').show();
			$('#backwpup_jobedit_destfile').show();
			$('#backwpup_jobedit_destmail').show();
		} else {
			$('#backwpup_jobedit_backupfile').hide();
			$('#backwpup_jobedit_destftp').hide();
			$('#backwpup_jobedit_dests3').hide();
			$('#backwpup_jobedit_destazure').hide();
			$('#backwpup_jobedit_destrsc').hide();
			$('#backwpup_jobedit_destdropbox').hide();
			$('#backwpup_jobedit_destsugarsync').hide();
			$('#backwpup_jobedit_destfile').hide();
			$('#backwpup_jobedit_destmail').hide();
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
	
	function awsgetbucket() {
		var awsAccessKey = $('#awsAccessKey').val();
		var awsSecretKey = $('#awsSecretKey').val();
		var awsBucket = $('#awsBucketselected').val();
		var data = {
			action: 'backwpup_get_aws_buckets',
			backwpupajaxpage: 'BackWPupEditJob',
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
			backwpupajaxpage: 'BackWPupEditJob',
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
			backwpupajaxpage: 'BackWPupEditJob',
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

	function sugarsyncgetroot() {
		var sugaruser = $('#sugaruser').val();
		var sugarpass = $('#sugarpass').val();
		var sugarrootselected = $('#sugarrootselected').val();
		var data = {
			action: 'backwpup_get_sugarsync_root',
			backwpupajaxpage: 'BackWPupEditJob',
			sugaruser: sugaruser,
			sugarpass: sugarpass,
			sugarrootselected: sugarrootselected
		};
		$.post(ajaxurl, data, function(response) {
			$('#sugarroot').remove();
			$('#sugarrootselected').after(response);
		});		
	}
	
	$('#sugaruser').change(function() {sugarsyncgetroot();});
	$('#sugarpass').change(function() {sugarsyncgetroot();});
	
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

