jQuery(document).ready(function ($) {
    $('.help_tip').tipTip({
        'attribute':'title',
        'fadeIn':50,
        'fadeOut':50,
		'keepAlive': true,
		'activation': 'hover'
    });
    $(".backwpup-fancybox").fancybox({
        maxWidth	: 800,
        maxHeight	: 600,
        fitToView	: false,
        width		: '70%',
        height		: '80%',
        autoSize	: false,
        closeClick	: false,
        openEffect	: 'none',
        closeEffect	: 'none'
    });
});