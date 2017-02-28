function ValidateForm(id) {
    var ajax_message = '#ajax_message_' + id;
    var name         =  jQuery.trim($('#response_name_' + id).raw().value);
    var message      =  jQuery.trim($('#response_message_' + id).raw().value);
     
    if (name==null || name=="" || message==null || message=="")
    {
	$(ajax_message).raw().innerHTML = 'One or more fields were blank.';
        $(ajax_message).add_class('alert');
        $(ajax_message).show();
        jQuery(ajax_message).fadeIn(0);
        setTimeout("jQuery('" + ajax_message + "').fadeOut(400)", 2000);
        return false;
    }
    return true;
}
// displays a message in common_responses
function Display_Message(added_id){
    if (added_id>0) {
        msg = "Response successfully created.";  
        $('#ajax_message_' + added_id).remove_class('alert');  
    } else  {
        if (added_id==-1) msg='One or more fields were blank.';
        else if (added_id==-2) msg='Not a valid ID!';
        else msg = "Something unexpected went wrong!";  
        added_id=0;  
        $('#ajax_message_' + added_id).add_class('alert');  
   }  
   $('#ajax_message_' + added_id).show();
   $('#ajax_message_' + added_id).raw().innerHTML = msg;
   setTimeout("jQuery('#ajax_message_" + added_id + "').fadeOut(400)", 3000); 
}

function SaveMessage(id) {
	var ajax_message = '#ajax_message_' + id;
	var ToPost = [];
	
	ToPost['id'] = id;
	ToPost['sort'] = $('#response_sort_' + id).raw().value;
	ToPost['name'] = $('#response_name_' + id).raw().value;
	ToPost['description'] = $('#response_message_' + id).raw().value;

	ajax.post("?action=mfd_edit_reason", ToPost, function (data) {
                        data = data.trim();
			if (data == '1') {
				$(ajax_message).raw().innerHTML = 'Response successfully created.';
                        $(ajax_message).remove_class('alert');
			} else if (data == '2') {
				$(ajax_message).raw().innerHTML = 'Response successfully edited.';
                        $(ajax_message).remove_class('alert');
                        
			} else if (data == '-1') {
				$(ajax_message).raw().innerHTML = 'One or more fields were blank.';
                        $(ajax_message).add_class('alert');
			} else if (data == '-2') {
				$(ajax_message).raw().innerHTML = 'Not a valid ID!';
                        $(ajax_message).add_class('alert');
			} else {
				$(ajax_message).raw().innerHTML = data;
                        $(ajax_message).add_class('alert');
			}
			$(ajax_message).show();
                  jQuery(ajax_message).fadeIn(0);
                  setTimeout("jQuery('" + ajax_message + "').fadeOut(400)", 2000);
		}
	);
}
 

function DeleteMessage(id) {
      var tt = $('#response_name_' + id).raw().value;
      if(!confirm("Are you sure you want to delete response #" + id + "\n'" + tt + "' ?")) return;
	var ajax_message = '#ajax_message_' + id;

	var ToPost = [];
	ToPost['id'] = id;
	ajax.post("?action=mfd_delete_reason", ToPost, function (data) {
		$('#response_head_' + id).hide();
		$('#response_' + id).hide();
		if (data == '1') {
			$(ajax_message).raw().textContent = "Response #" + id + " successfully deleted.";
		} else {
			$(ajax_message).raw().textContent = 'Something went wrong.';
		}
		$(ajax_message).show();
            jQuery(ajax_message).fadeIn(0);
		setTimeout("jQuery('" + ajax_message + "').fadeOut(400)", 2000);
		setTimeout("$('#container_" + id + "').hide()", 2400);
	});
}

function PreviewResponse(id) {
	var div = '#response_div_'+id;
	if ($(div).has_class('hidden')) {
		var ToPost = [];
		ToPost['description'] = document.getElementById('response_message_'+id).value;
		ajax.post('?action=mfd_preview_reason', ToPost, function (data) {
			document.getElementById('response_div_'+id).innerHTML = data;
			$(div).toggle();
			$('#response_editor_'+id).toggle();
		});
	} else {
		$(div).toggle();
		$('#response_editor_'+id).toggle();
	}
}
