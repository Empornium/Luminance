function Subscribe(topicid) {
    ajax.get("userhistory.php?action=thread_subscribe&topicid=" + topicid + "&auth=" + authkey, function() {
       var subscribeLink = $("#subscribelink" + topicid).raw();
       if(subscribeLink) {
           if(subscribeLink.firstChild.nodeValue.substr(0,1) == 'U') {
               subscribeLink.firstChild.nodeValue = "Subscribe";
           } else {
               subscribeLink.firstChild.nodeValue = "Unsubscribe";
           }
           return;
        }
        
        // jQuery is all fucked up here, revert to plain old JS  
        var subscribeLink = document.getElementsByClassName("subscribelink" + topicid);
        if(subscribeLink.length > 0) {
            if(subscribeLink[0].firstChild.nodeValue.substr(1,1) == 'U') {
                for (i=0; i<subscribeLink.length; i++) {
                    subscribeLink[i].firstChild.nodeValue = "[Subscribe]";
                }
            } else {
                for (i=0; i<subscribeLink.length; i++) {
                    subscribeLink[i].firstChild.nodeValue = "[Unsubscribe]";
                }
            } 
            return;
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
