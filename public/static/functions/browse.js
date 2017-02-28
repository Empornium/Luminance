
function submitOnEnter(e) {
    e = e || event;
    if (e.keyCode === 13 && !e.shiftKey) {
        jQuery('#search_form').submit();
        return false;
    }
    return true;
}

function add_tag(tag) {
    if ($('#tags').raw().value == "") {
        $('#tags').raw().value = tag;
    } else {
        $('#tags').raw().value = $('#tags').raw().value + " " + tag;
    }
    CursorToEnd($('#tags').raw());
}

function CursorToEnd(textarea){
     // set the cursor to the end of the text already present
    if (textarea.setSelectionRange) { // ff/chrome/opera
        var len = textarea.value.length * 2; //(*2 for opera stupidness)
        textarea.setSelectionRange(len, len);
    } else { // ie8-, fails in chrome
        textarea.value = textarea.value;
    }
}

function Load_Cookie()  {

	if(jQuery.cookie('searchPanelState') == undefined) {
		jQuery.cookie('searchPanelState', 'expanded', { expires: 100 });
	}

	if(jQuery.cookie('searchPanelState') == 'collapsed') {
		jQuery('#search_box').hide();
		jQuery('#search_button').text('Open Search Center');
	} else {
		jQuery('#search_button').text('Close Search Center');
      }
}

function Panel_Toggle() {
    jQuery('#search_box').slideToggle('slow', function() {
        if(jQuery.cookie('searchPanelState') == 'expanded') {
            jQuery.cookie('searchPanelState', 'collapsed', { expires: 100 });
            jQuery('#search_button').text('Open Search Center');
        } else {
            jQuery.cookie('searchPanelState', 'expanded', { expires: 100 });
            jQuery('#search_button').text('Close Search Center');
        }
    });
    return false;
}

addDOMLoadEvent(Load_Cookie);
