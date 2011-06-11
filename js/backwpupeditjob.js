jQuery(document).ready( function($) {

	$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
	
	$('.jobtype-select').change(function() {
		if ( true == $('#jobtype-select-FILE').prop('checked') || true ==  $('#jobtype-select-DB').prop('checked') || true == $('#jobtype-select-WPEXP').prop('checked')) {
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
		if ( true == $('#jobtype-select-DB').prop('checked') || true == $('#jobtype-select-CHECK').prop('checked') || true == $('#jobtype-select-OPTIMIZE').prop('checked')) {
			$('#databasejobs').show();
		} else {
			$('#databasejobs').hide();
		}
		if ( true == $('#jobtype-select-DB').prop('checked')) {
			$('#dbshortinsert').show();
		} else {
			$('#dbshortinsert').hide();
		}
		if ( true == $('#jobtype-select-FILE').prop('checked')) {
			$('#filebackup').show();
		} else {
			$('#filebackup').hide();
		}
	});
	
	function awsgetbucket() {
		var data = {
			action: 'backwpup_get_aws_buckets',
			backwpupajaxpage: 'backwpupeditjob',
			awsAccessKey: jQuery('#awsAccessKey').val(),
			awsSecretKey: jQuery('#awsSecretKey').val(),
			awsselected: jQuery('#awsBucketselected').val(),
			_ajax_nonce: jQuery('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
			$('#awsBucket').remove();
			$('#awsBucketselected').after(response);
		});		
	}
	$('#awsAccessKey').change(function() {awsgetbucket();});
	$('#awsSecretKey').change(function() {awsgetbucket();});

	function gstoragegetbucket() {
		var data = {
			action: 'backwpup_get_gstorage_buckets',
			backwpupajaxpage: 'backwpupeditjob',
			GStorageAccessKey: jQuery('#GStorageAccessKey').val(),
			GStorageSecret: jQuery('#GStorageSecret').val(),
			GStorageselected: jQuery('#GStorageselected').val(),
			_ajax_nonce: jQuery('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
			$('#GStorageBucket').remove();
			$('#GStorageselected').after(response);
		});		
	}
	$('#GStorageAccessKey').change(function() {gstoragegetbucket();});
	$('#GStorageSecret').change(function() {gstoragegetbucket();});	
	
	function msazuregetcontainer() {
		var data = {
			action: 'backwpup_get_msazure_container',
			backwpupajaxpage: 'backwpupeditjob',
			msazureHost: jQuery('#msazureHost').val(),
			msazureAccName: jQuery('#msazureAccName').val(),
			msazureKey: jQuery('#msazureKey').val(),
			msazureselected: jQuery('#msazureContainerselected').val(),
			_ajax_nonce: jQuery('#backwpupeditjobajaxnonce').val()
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
		var data = {
			action: 'backwpup_get_rsc_container',
			backwpupajaxpage: 'backwpupeditjob',
			rscUsername: jQuery('#rscUsername').val(),
			rscAPIKey: jQuery('#rscAPIKey').val(),
			rscselected: jQuery('#rscContainerselected').val(),
			_ajax_nonce: jQuery('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
			$('#rscContainer').remove();
			$('#rscContainerselected').after(response);
		});		
	}
	$('#rscUsername').change(function() {rscgetcontainer();});
	$('#rscAPIKey').change(function() {rscgetcontainer();});

	function sugarsyncgetroot() {
		var data = {
			action: 'backwpup_get_sugarsync_root',
			backwpupajaxpage: 'backwpupeditjob',
			sugaruser: jQuery('#sugaruser').val(),
			sugarpass: jQuery('#sugarpass').val(),
			sugarrootselected: jQuery('#sugarrootselected').val(),
			_ajax_nonce: jQuery('#backwpupeditjobajaxnonce').val()
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

