
function change_pmto(reportid) {
	
    var val = $('#pm_type'+reportid).raw().options[$('#pm_type'+reportid).raw().selectedIndex].innerHTML;
    //alert(val);
    $('#submit_pm'+reportid).raw().value = "Send message to "+val;
}

function Set_Message(appendid) {
    if (appendid == undefined ) appendid = '';
	var id = document.getElementById('common_answers_select'+appendid).value;

	ajax.get("staffpm.php?action=get_response&plain=1&id=" + id, function (data) {
		if ( $('#message'+appendid).raw().value != '') data = "\n"+data+"\n";
            insert(data, 'message'+appendid);
            resize('message'+appendid);
		$('#common_answers'+appendid).hide();
	});
}

function Update_Message(appendid) {
    if (appendid == undefined ) appendid = '';
	var id = document.getElementById('common_answers_select'+appendid).value;

	ajax.get("staffpm.php?action=get_response&plain=0&id=" + id, function (data) {
		$('#common_answers_body'+appendid).raw().innerHTML = data;
		$('#first_common_response'+appendid).remove()
	});
}

function Open_Compose_Message(reportid){
    
    jQuery('#compose'+reportid).slideToggle('fast');
    
    CursorToEnd($('#message'+reportid).raw());
  
}
