jQuery(document).ready( function($) {

	$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
	
	$('input[name="type[]"]').change(function() {
		if ( 'FILE' == $('#jobtype-select-FILE:checked').val() || 'DB' ==  $('#jobtype-select-DB:checked').val() || 'WPEXP' == $('#jobtype-select-WPEXP:checked').val()) {
            $('div[id^=backwpup_jobedit_dest]').show();
            if ($('input[name="backuptype"]:checked').val()=='sync') {
                $('div[id^=nosync_backwpup_jobedit_dest]').hide();
            } else {
                $('div[id^=nosync_backwpup_jobedit_dest]').show();
            }
		} else {
            $('div[id^=backwpup_jobedit_dest]').hide();
            $('div[id^=nosync_backwpup_jobedit_dest]').hide();
		}
		if ( 'DB' == $('#jobtype-select-DB:checked').val() || 'CHECK' == $('#jobtype-select-CHECK:checked').val() || 'OPTIMIZE' == $('#jobtype-select-OPTIMIZE:checked').val()) {
			$('#databasejobs').show();
		} else {
			$('#databasejobs').hide();
		}
		if ( 'DB' == $('#jobtype-select-DB:checked').val()) {
			$('#dbdump').show();
		} else {
			$('#dbdump').hide();
		}
		if ( 'WPEXP' == $('#jobtype-select-WPEXP:checked').val()) {
			$('#wpexport').show();
		} else {
			$('#wpexport').hide();
		}
		if ( 'FILE' == $('#jobtype-select-FILE:checked').val()) {
			$('#filebackup').show();
		} else {
			$('#filebackup').hide();
		}
	});

	$('input[name="backuptype"]').change(function() {
		if ($(this).val()=='sync') {
			$('.nosync').hide();
            $('div[id^=nosync_backwpup_jobedit_dest]').hide();
            $('.sync').show();
		} else {
			$('.nosync').show();
            if ( 'FILE' == $('#jobtype-select-FILE:checked').val() || 'DB' ==  $('#jobtype-select-DB:checked').val() || 'WPEXP' == $('#jobtype-select-WPEXP:checked').val()) {
                $('div[id^=nosync_backwpup_jobedit_dest]').show();
            }
            $('.sync').hide();
		}
	});
	
	if ($('input[name="backuptype"]:checked').val()=='sync') {
		$('.nosync').hide();
        $('div[id^=nosync_backwpup_jobedit_dest]').hide();
        $('.sync').show();
	} else {
        $('.nosync').show();
        if ( 'FILE' == $('#jobtype-select-FILE:checked').val() || 'DB' ==  $('#jobtype-select-DB:checked').val() || 'WPEXP' == $('#jobtype-select-WPEXP:checked').val()) {
            $('div[id^=nosync_backwpup_jobedit_dest]').show();
        }
        $('.sync').hide();
    }

    $('input[name="activetype"]').change(function() {
        if ($(this).val()=='') {
            $('#schedulecron').hide();
        } else {
            $('#schedulecron').show();
        }
    });

    $('input[name="fileprefix"]').keyup(function() {
        $('#backupfileprefix').replaceWith('<span id="backupfileprefix">'+$(this).val()+'</span>');
    });

    $('input[name="fileformart"]').change(function() {
        $('#backupfileformart').replaceWith('<span id="backupfileformart">'+$(this).val()+'</span>');
    });

	$('input[name="cronselect"]').change(function() {
		if ( 'basic' == $('input[name="cronselect"]:checked').val()) {
			$('#schedadvanced').hide();
			$('#schedbasic').show();
			cronstampbasic();
		} else {
			$('#schedadvanced').show();
			$('#schedbasic').hide();
			cronstampadvanced();
		}
	});

	function cronstampadvanced() {
		var cronminutes = [];
		var cronhours = [];
		var cronmday = [];
		var cronmon = [];
		var cronwday = [];
		$('input[name="cronminutes[]"]:checked').each(function() {
			cronminutes.push($(this).val());
		});
		$('input[name="cronhours[]"]:checked').each(function() {
			cronhours.push($(this).val());
		});
		$('input[name="cronmday[]"]:checked').each(function() {
			cronmday.push($(this).val());
		});
		$('input[name="cronmon[]"]:checked').each(function() {
			cronmon.push($(this).val());
		});
		$('input[name="cronwday[]"]:checked').each(function() {
			cronwday.push($(this).val());
		});		
		var data = {
			action: 'backwpup_get_cron_text',
            page: 'backwpupeditjob',
			cronminutes: cronminutes,
			cronhours: cronhours,
			cronmday: cronmday,
			cronmon: cronmon,
			cronwday: cronwday,
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
			$('#cron-text').replaceWith(response);
		});		
	}
	$('input[name="cronminutes[]"]').change(function() {cronstampadvanced();});
	$('input[name="cronhours[]"]').change(function() {cronstampadvanced();});
	$('input[name="cronmday[]"]').change(function() {cronstampadvanced();});
	$('input[name="cronmon[]"]').change(function() {cronstampadvanced();});
	$('input[name="cronwday[]"]').change(function() {cronstampadvanced();});

	function cronstampbasic() {
		var cronminutes = [];
		var cronhours = [];
		var cronmday = [];
		var cronmon = [];
		var cronwday = [];
		if ( 'mon' == $('input[name="cronbtype"]:checked').val()) {
			cronminutes.push($('select[name="moncronminutes"]').val());
			cronhours.push($('select[name="moncronhours"]').val());
			cronmday.push($('select[name="moncronmday"]').val());
			cronmon.push('*');
			cronwday.push('*');		
		}
		if ( 'week' == $('input[name="cronbtype"]:checked').val()) {
			cronminutes.push($('select[name="weekcronminutes"]').val());
			cronhours.push($('select[name="weekcronhours"]').val());
			cronmday.push('*');
			cronmon.push('*');
			cronwday.push($('select[name="weekcronwday"]').val());	
		}
		if ( 'day' == $('input[name="cronbtype"]:checked').val()) {
			cronminutes.push($('select[name="daycronminutes"]').val());
			cronhours.push($('select[name="daycronhours"]').val());
			cronmday.push('*');
			cronmon.push('*');
			cronwday.push('*');	
		}
		if ( 'hour' == $('input[name="cronbtype"]:checked').val()) {
			cronminutes.push($('select[name="hourcronminutes"]').val());
			cronhours.push('*');
			cronmday.push('*');
			cronmon.push('*');
			cronwday.push('*');
		}	
		var data = {
			action: 'backwpup_get_cron_text',
            page: 'backwpupeditjob',
			cronminutes: cronminutes,
			cronhours: cronhours,
			cronmday: cronmday,
			cronmon: cronmon,
			cronwday: cronwday,
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
			$('#cron-text').replaceWith(response);
		});		
	}
	$('input[name="cronbtype"]').change(function() {cronstampbasic();});
	$('select[name="moncronmday"]').change(function() {cronstampbasic();});
	$('select[name="moncronhours"]').change(function() {cronstampbasic();});
	$('select[name="moncronminutes"]').change(function() {cronstampbasic();});
	$('select[name="weekcronwday"]').change(function() {cronstampbasic();});
	$('select[name="weekcronhours"]').change(function() {cronstampbasic();});
	$('select[name="weekcronminutes"]').change(function() {cronstampbasic();});
	$('select[name="daycronhours"]').change(function() {cronstampbasic();});
	$('select[name="daycronminutes"]').change(function() {cronstampbasic();});
	$('select[name="hourcronminutes"]').change(function() {cronstampbasic();});
	

	function awsgetbucket() {
        var data = {
			action: 'backwpup_get_aws_buckets',
            page: 'backwpupeditjob',
			awsAccessKey: $('#awsAccessKey').val(),
			awsSecretKey: $('#awsSecretKey').val(),
			awsselected: $('#awsBucketselected').val(),
            awsdisablessl: $('input[name="awsdisablessl"]:checked').val(),
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
			$('#awsBucketerror').remove();
            $('#awsBucket').remove();
			$('#awsBucketselected').after(response);
		});		
	}
	$('#awsAccessKey').change(function() {awsgetbucket();});
	$('#awsSecretKey').change(function() {awsgetbucket();});
    $('input[name="awsdisablessl"]').change(function() {awsgetbucket();});

	function gstoragegetbucket() {
		var data = {
			action: 'backwpup_get_gstorage_buckets',
            page: 'backwpupeditjob',
			GStorageAccessKey: $('#GStorageAccessKey').val(),
			GStorageSecret: $('#GStorageSecret').val(),
			GStorageselected: $('#GStorageselected').val(),
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
            $('#GStorageBucketerror').remove();
            $('#GStorageBucket').remove();
			$('#GStorageselected').after(response);
		});		
	}
	$('#GStorageAccessKey').change(function() {gstoragegetbucket();});
	$('#GStorageSecret').change(function() {gstoragegetbucket();});	
	
	function msazuregetcontainer() {
		var data = {
			action: 'backwpup_get_msazure_container',
            page: 'backwpupeditjob',
			msazureHost: $('#msazureHost').val(),
			msazureAccName: $('#msazureAccName').val(),
			msazureKey: $('#msazureKey').val(),
			msazureselected: $('#msazureContainerselected').val(),
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
            $('#msazureContainererror').remove();
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
            page: 'backwpupeditjob',
			rscUsername: $('#rscUsername').val(),
			rscAPIKey: $('#rscAPIKey').val(),
			rscselected: $('#rscContainerselected').val(),
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
            $('#rscContainererror').remove();
            $('#rscContainer').remove();
			$('#rscContainerselected').after(response);
		});		
	}
	$('#rscUsername').change(function() {rscgetcontainer();});
	$('#rscAPIKey').change(function() {rscgetcontainer();});

	function sugarsyncgetroot() {
		var data = {
			action: 'backwpup_get_sugarsync_root',
            page: 'backwpupeditjob',
			sugaruser: $('#sugaruser').val(),
			sugarpass: $('#sugarpass').val(),
			sugarrootselected: $('#sugarrootselected').val(),
			_ajax_nonce: $('#backwpupeditjobajaxnonce').val()
		};
		$.post(ajaxurl, data, function(response) {
            $('#sugarrooterror').remove();
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

