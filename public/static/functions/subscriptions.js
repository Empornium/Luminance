

function Subscribe(topicid) {
    ajax.get("userhistory.php?action=thread_subscribe&topicid=" + topicid + "&auth=" + authkey, function(data) {

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

function Collapse() {
	var collapseLink = $('#collapselink').raw();
	var hide = (collapseLink.innerHTML.substr(0,1) == 'H' ? 1 : 0);
	if($('.row').results() > 0) {
		$('.row').toggle();
	}
	if(hide) {
		collapseLink.innerHTML = 'Show post bodies';
	} else {
		collapseLink.innerHTML = 'Hide post bodies';
	}
}
