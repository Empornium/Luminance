

function change_flag() {
    var flag = $('#flag').raw().value;
    if (flag == '' || flag == '??') flag = '';
    else flag = '<img src="/static/common/flags/64/'+flag+'.png"/>'
    $('#flag_image').raw().innerHTML=flag;
}

// TODO: Fix this shit. Flags are stored by their id in the languages table != filename
function change_lang_flag() {
    var flag = $('#new_lang').raw().value;
    if (flag == '' || flag == '??') flag = '';
    else flag = '<img src="/static/common/flags/iso16/'+flag+'.png"/>'
    $('#lang_image').raw().innerHTML=flag;
}

function delete_conn_record(elem_id, user_id, ip) {
 
	ajax.get('ajax.php?action=delete_conn_record&ip=' + ip + '&userid=' + user_id, function (response) {
        var x = json.decode(response); 
        if ( is_array(x)){
            if ( x[0] == true){
               $('#'+elem_id).remove();
            } else {
                alert(x[1]);
            }
        } else {    // error from ajax
            alert(x);
        } 
	}); 
}


function unset_conn_status(elemstatus_id, elemlink_id, user_id, ip) {
 
	ajax.get('ajax.php?action=remove_conn_status&ip=' + ip + '&userid=' + user_id, function (response) {
        var x = json.decode(response); 
        if ( is_array(x)){
            if ( x[0] == true){
               $('#'+elemstatus_id).html("?"); 
               $('#'+elemstatus_id).remove_class("red"); 
               $('#'+elemstatus_id).remove_class("green"); 
               $('#'+elemstatus_id).add_class("grey"); 
               $('#'+elemlink_id).remove();
            } else {
                alert(x[1]);
            }
        } else {    // error from ajax
            alert(x);
        } 
	}); 
}

function Toggle_view(elem_id) {

    jQuery('#'+elem_id+'div').toggle();
 
    if (jQuery('#'+elem_id+'div').is(':hidden')) 
        jQuery('#'+elem_id+'button').text('(Show)');
    else  
        jQuery('#'+elem_id+'button').text('(Hide)');
     
    var t= [get_hidden_value('profile'), 
                        get_hidden_value('bonus'), 
                        get_hidden_value('donate'), 
                        get_hidden_value('snatches'), 
                        get_hidden_value('recentuploads'), 
                        get_hidden_value('linked'), 
                        get_hidden_value('invite'), 
                        get_hidden_value('requests'), 
                        get_hidden_value('staffpms'), 
                        get_hidden_value('notes'), 
                        get_hidden_value('history'), 
                        get_hidden_value('info'), 
                        get_hidden_value('badgesadmin'), 
                        get_hidden_value('warn'), 
                        get_hidden_value('privilege'), 
                        get_hidden_value('session'),
                        get_hidden_value('submit'),
                        get_hidden_value('loginwatch'), 
                        get_hidden_value('iplinked'), 
                        get_hidden_value('elinked'), 
                        get_hidden_value('reports')]; 
            
    jQuery.cookie('userPageState', json.encode(t));
    return false;
}



function get_hidden_value(elem_id){
    
    if (!in_array(elem_id, cookieitems, false)) 
        return 'not';
    else
        return ( jQuery('#'+elem_id+'div').is(':hidden') )?'0':'1';
     
    /*
    var element =  document.getElementById(elem_id);
 
    if (typeof(element) != 'undefined' && element != null)
    {
      // exists.
      return ( jQuery('#'+elem_id).is(':hidden') )?'0':'1';
    }
    return '0';
    //if (jQuery('#'+elem_id).length == 0) alert("un: "+elem_id);
    if (jQuery('#'+elem_id).length == 0) return '0';
    else return ( jQuery('#'+elem_id).is(':hidden') )?'0':'1'; */
}

function set_hidden_value(elem_id, state){
    
    if (!in_array(elem_id, cookieitems, false))  return ;
   
    //if (jQuery('#'+elem_id).length == 0) return;
    if(state != '1') {
        jQuery('#'+elem_id+'div').hide();
	  jQuery('#'+elem_id+'button').text('(Show)');
    } else {
        jQuery('#'+elem_id+'div').show();
	  jQuery('#'+elem_id+'button').text('(Hide)');
    }
}

function Load_User_Cookie()  { 
    
	if(jQuery.cookie('userPageState') == undefined) {
		jQuery.cookie('userPageState', json.encode(['0', '1', '1', '0', '0', '0', '0', '0', '0', '1', '0', '1', '0', '1', '1', '1', '1', '1']));
	}
	var state = json.decode( jQuery.cookie('userPageState') );
     
      set_hidden_value('profile', state[0]);
      set_hidden_value('bonus', state[1]);
      set_hidden_value('donate', state[2]);
      set_hidden_value('snatches', state[3]);
      set_hidden_value('recentuploads', state[4]);
      set_hidden_value('linked', state[5]);
      set_hidden_value('invite', state[6]);
      set_hidden_value('requests', state[7]);
      set_hidden_value('staffpms', state[8]);
      set_hidden_value('notes', state[9]);
      set_hidden_value('history', state[10]);
      set_hidden_value('info', state[11]);
      set_hidden_value('badgesadmin', state[12]);
      set_hidden_value('warn', state[13]);
      set_hidden_value('privilege', state[14]);
      set_hidden_value('session', state[15]);
      set_hidden_value('submit', state[16]);
      set_hidden_value('loginwatch', state[17]);
      set_hidden_value('iplinked', state[18]);
      set_hidden_value('elinked', state[19]);
      set_hidden_value('reports', state[20]);
}



function ChangeTo(to) {
	if(to == "text") {
		$('#admincommentlinks').hide();
		$('#admincomment').show();
		resize('admincomment');
		var buttons = document.getElementsByName('admincommentbutton');
		for(var i = 0; i < buttons.length; i++) {
			buttons[i].setAttribute('onclick',"ChangeTo('links'); return false;");
		}
	} else if(to == "links") {
		ajax.post("ajax.php?action=preview","form", function(response){
			$('#admincommentlinks').raw().innerHTML = response;
			$('#admincomment').hide();
			$('#admincommentlinks').show();
			var buttons = document.getElementsByName('admincommentbutton');
			for(var i = 0; i < buttons.length; i++) {
				buttons[i].setAttribute('onclick',"ChangeTo('text'); return false;");
			}
		})
	}
}

function Preview_Toggle(id) {
	var preview_div = '#preview_'+id;
	if ($(preview_div).has_class('hidden')) {
		var ToPost = [];
		ToPost['body'] = $('#preview_message_'+id).raw().value;
		ajax.post('ajax.php?action=preview', ToPost, function (data) {
			$(preview_div).raw().innerHTML = data;
			$(preview_div).toggle();
			$('#editor_'+id).toggle();
		});
	} else {
		$(preview_div).toggle();
		$('#editor_'+id).toggle();
	}
}

function CalculateAdjustUpload(name, radioObj, currentvalue){
    var adjustamount = $('#' + name + 'value').raw().value; 
    if ( adjustamount == '' ) adjustamount =0;
    else adjustamount = parseFloat(adjustamount); 
    if (adjustamount != 0){
        var mul = 1;
	  var radioLength = radioObj.length;
	  for(var i = 0; i < radioLength; i++) {
		if(radioObj[i].checked) {
                if (radioObj[i].value == 'mb') mul = 1024 * 1024;
                 else if (radioObj[i].value == 'gb') mul = 1024 * 1024 * 1024;
                 else if (radioObj[i].value == 'tb') mul = 1024 * 1024 * 1024 * 1024;
                break;
		}
	  }
        adjustamount = adjustamount * mul;
        var newvalue = Math.max(currentvalue + adjustamount, 0); 
        $('#' + name + 'result').raw().setAttribute('class', (adjustamount > 0 ? 'green' : 'red'));
        $('#' + name + 'result').raw().innerHTML = (adjustamount > 0 ? '+ ' : '- ') + get_size_fixed(Math.abs(adjustamount),3) + ' => ' + get_size_fixed(newvalue, 3);
    } else {
        $('#' + name + 'result').raw().setAttribute('class', 'none');
        $('#' + name + 'result').raw().innerHTML = '';
    }
}

function SetAllLatestForumTopicsCheckboxes(state) {
    if (state == undefined)
        state = true;

    // $.noConflict :( -> no $().each()
    jQuery('input[name^="disable_lt_"][type="checkbox"]').each(function(index, checkbox) {
        jQuery(checkbox).prop('checked', state);
    });
}

function UncheckIfDisabled(checkbox) {
	if (checkbox.disabled) {
		checkbox.checked = false;
	}
}

function AlterParanoia() {
	// Required Ratio is almost deducible from downloaded, the count of seeding and the count of snatched
	// we will "warn" the user by automatically checking the required ratio box when they are
	// revealing that information elsewhere
	if(!$('input[name=p_ratio]').raw()) {
		return;
	}
	var showDownload = $('input[name=p_downloaded]').raw().checked || ($('input[name=p_uploaded]').raw().checked && $('input[name=p_ratio]').raw().checked);
	if (($('input[name=p_seeding_c]').raw().checked) && ($('input[name=p_snatched_c]').raw().checked) && showDownload) {
		$('input[type=checkbox][name=p_requiredratio]').raw().checked = true;
		$('input[type=checkbox][name=p_requiredratio]').raw().disabled = true;
	} else {
		$('input[type=checkbox][name=p_requiredratio]').raw().disabled = false;
	}
	$('input[name=p_torrentcomments_l]').raw().disabled = !$('input[name=p_torrentcomments_c]').raw().checked;
	$('input[name=p_collagecontribs_l]').raw().disabled = !$('input[name=p_collagecontribs_c]').raw().checked;
	$('input[name=p_requestsfilled_list]').raw().disabled = !($('input[name=p_requestsfilled_count]').raw().checked && $('input[name=p_requestsfilled_bounty]').raw().checked);
	$('input[name=p_requestsvoted_list]').raw().disabled = !($('input[name=p_requestsvoted_count]').raw().checked && $('input[name=p_requestsvoted_bounty]').raw().checked);
	$('input[name=p_uploads_l]').raw().disabled = !$('input[name=p_uploads_c]').raw().checked;
	$('input[name=p_seeding_l]').raw().disabled = !$('input[name=p_seeding_c]').raw().checked;
	$('input[name=p_leeching_l]').raw().disabled = !$('input[name=p_leeching_c]').raw().checked;
	$('input[name=p_snatched_l]').raw().disabled = !$('input[name=p_snatched_c]').raw().checked;
	$('input[name=p_grabbed_l]').raw().disabled = !$('input[name=p_grabbed_c]').raw().checked;
	$('input[name=p_tags_l]').raw().disabled = !$('input[name=p_tags_c]').raw().checked;
	UncheckIfDisabled($('input[name=p_torrentcomments_l]').raw());
	UncheckIfDisabled($('input[name=p_collagecontribs_l]').raw());
	UncheckIfDisabled($('input[name=p_requestsfilled_list]').raw());
	UncheckIfDisabled($('input[name=p_requestsvoted_list]').raw());
	UncheckIfDisabled($('input[name=p_uploads_l]').raw());
	UncheckIfDisabled($('input[name=p_seeding_l]').raw());
	UncheckIfDisabled($('input[name=p_leeching_l]').raw());
	UncheckIfDisabled($('input[name=p_snatched_l]').raw());
	UncheckIfDisabled($('input[name=p_grabbed_l]').raw());
	UncheckIfDisabled($('input[name=p_tags_l]').raw());
	if ($('input[name=p_collagecontribs_l]').raw().checked) {
		$('input[name=p_collages_c]').raw().disabled = true;
		$('input[name=p_collages_l]').raw().disabled = true;
		$('input[name=p_collages_c]').raw().checked = true;
		$('input[name=p_collages_l]').raw().checked = true;
	} else {
		$('input[name=p_collages_c]').raw().disabled = false;
		$('input[name=p_collages_l]').raw().disabled = !$('input[name=p_collages_c]').raw().checked;
		UncheckIfDisabled($('input[name=p_collages_l]').raw());
	}
}

function ParanoiaReset(checkbox, drops) {
	var selects = $('select');
	for (var i = 0; i < selects.results(); i++) {
		if (selects.raw(i).name.match(/^p_/)) {
			if(drops == 0) {
				selects.raw(i).selectedIndex = 0;
			} else if(drops == 1) {
				selects.raw(i).selectedIndex = selects.raw(i).options.length - 2;
			} else if(drops == 2) {
				selects.raw(i).selectedIndex = selects.raw(i).options.length - 1;
			}
			AlterParanoia();
		}
	}
	var checkboxes = $(':checkbox');
	for (var i = 0; i < checkboxes.results(); i++) {
		if (checkboxes.raw(i).name.match(/^p_/) && (checkboxes.raw(i).name != 'p_lastseen')) {
                if (checkbox == 3) 
                    checkboxes.raw(i).checked = !(checkboxes.raw(i).name.match(/_list$/) || checkboxes.raw(i).name.match(/_l$/));
                else 
                    checkboxes.raw(i).checked = checkbox; 
                AlterParanoia();			
		}
	}
}

function ParanoiaResetOff() {
	ParanoiaReset(true, 0);
}

function ParanoiaResetStats() {
	ParanoiaReset(3, 0);
	$('input[name=p_collages_l]').raw().checked = false;
}


function ParanoiaResetStats2() {
	ParanoiaReset(3, 0);
	$('input[name=p_torrentcomments_l]').raw().checked = true;
      $('input[name=p_collagecontribs_l]').raw().checked = true;
      $('input[name=p_requestsfilled_list]').raw().checked = true;
      $('input[name=p_requestsvoted_list]').raw().checked = true;
      $('input[name=p_uploads_l]').raw().checked = true;
      AlterParanoia();			
}

function ParanoiaResetOn() {
	ParanoiaReset(false, 0);
	$('input[name=p_collages_c]').raw().checked = false;
	$('input[name=p_collages_l]').raw().checked = false;
}

addDOMLoadEvent(Load_User_Cookie);
addDOMLoadEvent(AlterParanoia);
