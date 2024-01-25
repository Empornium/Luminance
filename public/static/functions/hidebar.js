var lastScrollTop = 0;
jQuery(window).scroll(function() {
    if (jQuery(window).scrollTop() >= 200) {
        var thisScrollTop = jQuery(window).scrollTop();
        if (thisScrollTop < lastScrollTop) {
            jQuery('#hidebar').addClass('visible');
        } else {
           jQuery('#hidebar').removeClass('visible');
        }
        lastScrollTop = thisScrollTop;
    }
    else {
       jQuery('#hidebar').removeClass('visible');
    }
});
