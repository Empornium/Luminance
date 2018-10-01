
var startsize = 0;
var currentsize = 0;
var filesizes = [];

addDOMLoadEvent(InitDupeHelper);

function InitDupeHelper()
{
    // if this is a dupe pm
    if ( $('h2:first').raw().innerHTML.substr(0, 37) == 'Staff PM - Possible dupe was uploaded') {

        startsize = 0;
        jQuery('span[id^="filesize_"]').each(function(index, element) {
            // id == newfile_sizeinbytes_torrentid
            var tag = jQuery(element).attr('id');
            var lindex = tag.lastIndexOf('_');
            var size = parseInt(tag.substring(9, lindex));
            var torrentid = parseInt(tag.substr(lindex +1));
            // filesizes will have a single copy of each unique filesize
            if (filesizes.indexOf(size)==-1) filesizes.push(size);
            // add checkbox
            jQuery(element).after('<input type="checkbox" onclick="RecalculateSize();" name="filecheck_'+size+'" tid="'+torrentid+'" checked="checked" style="float:right" />');
        });
        var tsize = jQuery('#totalsize').html();
        var tindex = tsize.indexOf(' ');
        startsize = tsize.substring(0, tindex);
        var mul = tsize.substr(tindex+1);
        if (mul == 'MiB') startsize *= 1024 * 1024;
        else if (mul == 'GiB') startsize *= 1024 * 1024 * 1024;
        else if (mul == 'TiB') startsize *= 1024 * 1024 * 1024 * 1024;
        else if (mul == 'PiB') startsize *= 1024 * 1024 * 1024 * 1024 * 1024;
        currentsize = startsize;
        $('#copylink').html('<a href="#" onclick="CopyDupePMtext();" title="auto generates bbcode PM text for the uploader and puts it in the clipboard">generate PM bbcode to clipboard</a><br/><a href="#" onclick="CopyLinks();" title="copy the raw urls to the clipboard">copy selected links to clipboard</a><br/><a href="#" onclick="GotoDelete();" title="takes you to the v2 delete form and auto fills it">delete torrent v2</a>')
    }
}

function RecalculateSize()
{
    currentsize = 0;
    var num = 0;
    for (i = 0; i < filesizes.length; i++) {
        var check = false;
        jQuery('input[name="filecheck_'+filesizes[i]+'"]').each(function(index, element) {
            // if any are checked count this filesize
            if (jQuery( element ).prop("checked")) check = true;
        });
        if (check) {
            currentsize += filesizes[i];
            num++;
        }
    }

    jQuery('#size').html(get_size(currentsize));
    var percent = ((currentsize/startsize)*100).toFixed(2);
    jQuery('#percent').html(percent);

    if (percent >= 50) {
        jQuery('#percent').parent('span').attr({ style: 'color:#AA0000'});
        jQuery('#percent').next('img').attr({ alt: ":dupe:", src: 'static/common/smileys/dupe.gif'});
    } else {
        jQuery('#percent').parent('span').attr({ style: 'color:#009900'});
        jQuery('#percent').next('img').attr({ alt: ":gjob:", src: 'static/common/smileys/thumbup.gif'});
    }
    jQuery('#numresults').html(num);
}

function CopyDupePMtext()
{
    var str = 'Your upload:\n[url='+jQuery('#urlnew a.link').attr('href')+']'+jQuery('#urlnew a.link').html()+'[/url]\n\ndupes these exisitng files:\n';
    jQuery('input[name^="filecheck_"]').each(function(index, element) {
        if (jQuery( element ).prop( "checked")) {
            var torrentid = jQuery(element).attr('tid');
            var link = jQuery('#url_'+torrentid+' a.link').attr('href');
            var title = jQuery('#url_'+torrentid+' a.link').html();
            var id = jQuery(element).attr('name').substr(10);
            var file = jQuery('span[id^="oldfile_'+id+'"]').html();
            str += '\n[url='+link+']'+title+'[/url]\nYour file: [b]'+jQuery('#newfile_'+id).html()+'[/b]  Duped file: [b]'+file+'[/b] ('+get_size(id)+")\n";
        }
    });
    str += "\nDuped files: "+jQuery('#numresults').html()+' / '+jQuery('#numchecked').html();
    str += '\nDuped size: '+get_size(currentsize)+' / '+get_size(startsize)+' ('+ ((currentsize/startsize)*100).toFixed(2)+'%)\n\n';
    copyToClipboard(str);
}

function CopyLinks()
{
    var ids = [];
    var str = '';
    jQuery('input[name^="filecheck_"]').each(function(index, element) {
        if (jQuery( element ).prop( "checked")) {
            var torrentid = jQuery(element).attr('tid');
            if (ids.indexOf(torrentid)==-1) {
                ids.push(torrentid);
                var link = jQuery('#url_'+torrentid+' a.link').attr('href');
                if (str != '') str += ', ';
                str += window.location.origin+link;
            }
        }
    });
    copyToClipboard(str);
}

function GetLinkIDs()
{
    var ids = [];
    var str = '';
    jQuery('input[name^="filecheck_"]').each(function(index, element) {
        if (jQuery( element ).prop( "checked")) {
            var torrentid = parseInt(jQuery(element).attr('tid'));
            if (ids.indexOf(torrentid)==-1) {
                ids.push(torrentid);
                if (str != '') str += ' ';
                str += torrentid;
            }
        }
    });
    return str;
}

function GotoDelete()
{
    var src = jQuery('#urlnew a.link').attr('href');
    var tid = src.substr(src.indexOf("id=")+3);
    var ids = GetLinkIDs();
    window.location = window.location.origin + "/torrents.php?action=delete&type=dupe&torrentid="+tid+"&extraIDs="+encodeURI(ids);
}


function SetMessage() {
  var id = document.getElementById('common_answers_select').value;

  ajax.get("?action=get_response&plain=1&id=" + id, function (data) {
    if ( $('#quickpost').raw().value != '') data = "\n"+data+"\n";
        insert(data, 'quickpost');
    $('#common_answers').hide();
  });
}

function UpdateMessage() {
  var id = document.getElementById('common_answers_select').value;

  ajax.get("?action=get_response&plain=0&id=" + id, function (data) {
    $('#common_answers_body').raw().innerHTML = data;
    $('#first_common_response').remove()
  });
}

function ValidateForm(id) {
    var ajax_message = '#ajax_message_' + id;
    var name =  jQuery.trim($('#response_name_' + id).raw().value);
    var message =  jQuery.trim($('#response_message_' + id).raw().value);

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
                //$JustAdded = (int)$_GET['added'];
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
  ToPost['name'] = $('#response_name_' + id).raw().value;
  ToPost['message'] = $('#response_message_' + id).raw().value;

  ajax.post("?action=edit_response", ToPost, function (data) {
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
  ajax.post("?action=delete_response", ToPost, function (data) {
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

function Assign() {
  var ToPost = [];
  ToPost['assign'] = document.getElementById('assign_to').value;
  ToPost['convid'] = document.getElementById('convid').value;
  ajax.post("?action=assign", ToPost, function (data) {
    if (data == '1') {
      document.getElementById('ajax_message').textContent = 'Conversation successfully assigned.';
    } else {
      document.getElementById('ajax_message').textContent = 'Something went wrong.';
    }
    $('#ajax_message').show();
        jQuery('#ajax_message').fadeIn(0);
    setTimeout("jQuery('#ajax_message').fadeOut(400)", 2000);
        location.reload();
  });
}


function AssignUrgency() {
    var ToPost = [];
    ToPost['urgency'] = $('#urgency').raw().value;
    ToPost['convid'] = $('#convid').raw().value;
    if (ToPost['urgency'] != 'No' && $('#resolved').raw().value == '1') {
        if (!confirm("This conversation will be automatically set to 'Unanswered' & 'Unread' if you set a Force response status.\n\nDo you want to proceed?")) {
            return false;
        }
    }
    /* // probably these are more annoying than they are worth,
       // for the moment it just resolves non logical states in the backend automagically and logs the actions in staff notes
    if ($('#unread').raw().value == '0') {
        if (ToPost['urgency']=='Read') {
            alert("You cannot set this conversation to 'User must Read' whilst it has a (user has) 'Read' status.\n\nYou must reply first before setting the Force Response status to 'User must Read'.");
            return false;
        } else if (ToPost['urgency']=='Respond') {
            if (!confirm("You are setting this conversation to 'User must Respond' before sending a new message.\n(the last message has already been read by the user).\n\nAre you sure you want to proceed?\n(Doing so will set the status to 'Unread')")) {
                return false;
            }
        }
    } */
    ajax.post("?action=assign_urgency", ToPost, function (data) {
        if (data == '1') {
            $('#ajax_message').raw().innerHTML = 'Force Response assigned.';
        } else {
            $('#ajax_message').raw().innerHTML = 'Something went wrong.';
        }
        $('#ajax_message').show();
        jQuery('#ajax_message').fadeIn(0);
        setTimeout("jQuery('#ajax_message').fadeOut(400)", 2000);
        location.reload();
    });
}

function PreviewResponse(id) {
  var div = '#response_div_'+id;
  if ($(div).has_class('hidden')) {
    var ToPost = [];
    ToPost['message'] = document.getElementById('response_message_'+id).value;
    ajax.post('?action=preview', ToPost, function (data) {
      document.getElementById('response_div_'+id).innerHTML = data;
      $(div).toggle();
      $('#response_editor_'+id).toggle();
      Prism.highlightAll();
      MathJax.Hub.Queue(["Typeset",MathJax.Hub]);
      lazy_load();
    });
  } else {
    $(div).toggle();
    $('#response_editor_'+id).toggle();
  }
}

function PreviewMessage() {
  if ($('#preview').has_class('hidden')) {
    var ToPost = [];
    ToPost['message'] = document.getElementById('quickpost').value;
    ajax.post('?action=preview', ToPost, function (data) {
      document.getElementById('preview').innerHTML = data;
      $('#preview').toggle();
      $('#quickpost').toggle();
      $('.bb_holder').toggle();
      $('#previewbtn').raw().value = "Edit";
      Prism.highlightAll();
      MathJax.Hub.Queue(["Typeset",MathJax.Hub]);
      lazy_load();
    });
  } else {
    $('#preview').toggle();
    $('#quickpost').toggle();
    $('.bb_holder').toggle();
    $('#previewbtn').raw().value = "Preview";
  }
}
