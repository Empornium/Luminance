function Subscribe(topicid) {
    ajax.get("/userhistory.php?action=thread_subscribe&threadid=" + topicid + "&auth=" + authkey, function(data) {

        var text ='';
        if (data == 1) text = 'Unsubscribe';
        else if (data == -1) text = 'Subscribe';

        if (text == '') {
            alert(data);
        } else {
            jQuery('.subscribelink'+ topicid).each(function(index, element) {
                jQuery(element).html(text);
            });
        }
    });
}

function Collapse(postID = null) {
    var collapseLink = $('#collapselink').raw();
    var hide = (collapseLink.innerHTML.substr(0,1) == 'H' ? 1 : 0);
    if (postID === null) {
        if(hide) {
            $("[id^=subscribed_post]").hide();
            $("[id^=header_post]").show();
            $('.colhead').show();
            collapseLink.innerHTML = 'Show post bodies';
        } else {
            $("[id^=subscribed_post]").show();
            $("[id^=header_post]").hide();
            $('.colhead').hide();
            collapseLink.innerHTML = 'Hide post bodies';
        }
    } else {
        $('#subscribed_post'+postID).toggle();
        $('#header_post'+postID).toggle();
    }
}
