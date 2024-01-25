var username;
var postid;

function GetSection() {
    if (location.href.match(/forum/) || location.href.match(/userhistory\.php\?action\=posts/)) {
        return {
            link:'/forum/',
            name:'forum',
            endpoint:'/forum/post',
            legacy: false
        }
    } else if (location.href.match(/collage/)) {
        return {
            link:'/collage/',
            name:'collage',
            endpoint:'/collage/post',
            legacy: false
        }
    } else if (location.href.match(/requests\.php/)) {
        return {
            link:'/requests.php',
            name:'requests',
            legacy: true
        }
    } else if (location.href.match(/torrents\.php/) || location.href.match(/userhistory\.php\?action\=comments/)) {
        return {
            link:'/torrents.php',
            name:'torrents',
            legacy: true
        }
    } else if (location.href.match(/staffpm\.php/)) {
        return {
            link:'/staffpm.php',
            name:'staffpm',
            legacy: true
        }
    } else if (location.href.match(/user\/inbox/)) {
        return {
            link:'/user/inbox/',
            name:'inbox',
            endpoint:'/user/inbox/message',
            legacy: false
        }
    }
}

const luminance = ['forum', 'collage', 'inbox'];

function Quote(post, place, user) {
    username = user;
    postid = post;
    section = GetSection();
    var ajaxurl = section.link + "?action=get_post&section=" + section.name + "&body=1&post=" + postid;
    if (luminance.includes(section.name)) {
        ajaxurl = section.endpoint + '/' + postid + '/get?&body=1';
    }
    ajax.get(ajaxurl, function(response) {
        if (luminance.includes(section.name)) {
            var x = json.decode(response);
        } else { // TODO fix all the other url endpoints to return JSON
            var x = response;
        }
        var params = place != '' ? ","+place+","+postid : '';
        var s = "[quote="+username+params+"]" +  html_entity_decode(x) + "[/quote]";
        if ( $('#quickpost').raw().value != '')   s = "\n" + s + "\n";
        insert( s, 'quickpost');
        resize('quickpost');
    });
}

function Edit_Form(post) {
    postid = post;
    section = GetSection();
    if ($('#edit'+postid).disabled) {
        return;
    }
    $('#bar' + postid).raw().cancel = $('#content' + postid).raw().innerHTML;
    $('#bar' + postid).raw().oldbar = $('#bar' + postid).raw().innerHTML;
    $('#content' + postid).raw().innerHTML = "<div id=\"preview" + postid + "\"></div><input type=\"hidden\" name=\"auth\" value=\"" + authkey + "\" /><input type=\"hidden\" name=\"post\" value=\"" + postid + "\" /><div id=\"editcont" + postid + "\"></div>";
    $('#edit'+postid).disable(true);
    if (section.legacy) {
        bar  = "";
        bar += "<input type=\"button\" value=\"Preview\" onclick=\"PreviewEdit('" + postid + "');\" />";
        bar += "<input type=\"button\" value=\"Post\" onclick=\"SaveEdit('" + postid + "');\" />";
        bar += "<input type=\"button\" value=\"Cancel\" onclick=\"CancelEdit('" + postid + "');\" />";
        $('#bar' + postid).raw().innerHTML = bar;
    } else {
        $('#edit_preview_' + postid).show();
        $('#edit_preview_cancel_' + postid).hide();
        $('#edit_save_' + postid).show();
        $('#edit_cancel_' + postid).show();
    }
    var ajaxurl = section.link + "?action=get_post&section=" + section.name + "&post=" + postid;
    if (luminance.includes(section.name)) {
        ajaxurl = '/'+section.name+'/post/' + postid + '/get';
    }
    ajax.get(ajaxurl, function(response) {
        $('#editcont' + postid).raw().innerHTML = response;
        resize('editbox' + postid);
    });
}

function CancelEdit(postid) {
    if (section.legacy) {
        $('#bar' + postid).raw().innerHTML = $('#bar' + postid).raw().oldbar;
    } else {
        $('#edit_preview_' + postid).hide();
        $('#edit_preview_cancel_' + postid).hide();
        $('#edit_save_' + postid).hide();
        $('#edit_cancel_' + postid).hide();
    }
    $('#content' + postid).raw().innerHTML = $('#bar' + postid).raw().cancel;
    $('#edit'+postid).disable(false);
}

function PreviewEdit(postid) {
    var toPost = [];
    toPost['auth'] = authkey;
    toPost['post'] = postid;
    toPost['body'] = $('#editbox'+postid).raw().value;

    if (section.legacy) {
        bar  = "";
        bar += "<input type=\"button\" value=\"Editor\" onclick=\"CancelPreview('" + postid + "');\" />";
        bar += "<input type=\"button\" value=\"Post\" onclick=\"SaveEdit('" + postid + "')\" />";
        bar += "<input type=\"button\" value=\"Cancel\" onclick=\"CancelEdit('" + postid + "');\" />";
        $('#bar' + postid).raw().innerHTML = bar;
    } else {
        $('#edit_preview_' + postid).hide();
        $('#edit_preview_cancel_' + postid).show();
        $('#edit_save_' + postid).show();
        $('#edit_cancel_' + postid).show();
    }
    ajax.post("/ajax.php?action=preview", toPost, function(response) {
        $('#preview' + postid).raw().innerHTML = response;
        $('#editcont' + postid).hide();
        Prism.highlightAll();
        MathJax.Hub.Queue(["Typeset",MathJax.Hub]);
        lazy_load();
    });
}

function CancelPreview(postid) {
    if (section.legacy) {
        bar  = "";
        bar += "<input type=\"button\" value=\"Preview\" onclick=\"PreviewEdit('" + postid + "');\" />";
        bar += "<input type=\"button\" value=\"Post\" onclick=\"SaveEdit('" + postid + "');\" />";
        bar += "<input type=\"button\" value=\"Cancel\" onclick=\"CancelEdit('" + postid + "');\" />";
        $('#bar' + postid).raw().innerHTML = bar;
    } else {
        $('#edit_preview_' + postid).show();
        $('#edit_preview_cancel_' + postid).hide();
        $('#edit_save_' + postid).show();
        $('#edit_cancel_' + postid).show();
    }
    $('#preview' + postid).raw().innerHTML = "";
    $('#editcont' + postid).show();
}

function SaveEdit(postid, token = null) {
    var toPost = [];
    toPost['body'] = $('#editbox'+postid).raw().value;
    var ajaxurl = '/ajax.php?action=takeedit_post';

    section = GetSection();
    toPost['section'] = section.name;
    toPost['token'] = token;

    // Split Luminance and Gazelle endpoints
    if (luminance.includes(section.name)) {
        ajaxurl = '/'+section.name+'/post/'+postid+'/edit';
    } else {
        toPost['auth'] = authkey;
        toPost['post'] = postid;
    }

    ajax.post(ajaxurl, toPost, function (response) {
        var x = json.decode(response);
        if (!is_array(x)) {
             alert(x);
             return;
        }
        if (x[0]=='saved') {
            if (section.legacy) {
                $('#bar' + postid).raw().innerHTML = $('#bar' + postid).raw().oldbar;
            } else {
                $('#edit_preview_' + postid).hide();
                $('#edit_preview_cancel_' + postid).hide();
                $('#edit_save_' + postid).hide();
                $('#edit_cancel_' + postid).hide();
            }

            $('#editcont' + postid).raw().innerHTML = '';

            // Forum is "special"
            if (luminance.includes(section.name)) {
                $('#post' + postid).raw().innerHTML = x[1];
            } else {
                $('#content' + postid).raw().innerHTML = x[1];
            }
            $('#edit'+postid).disable(false);
        } else {
            if (section.legacy) {
                bar  = "";
                bar += "<input type=\"button\" value=\"Editor\" onclick=\"CancelPreview('" + postid + "');\" />";
                bar += "<input type=\"button\" value=\"Post\" onclick=\"SaveEdit('" + postid + "')\" />";
                bar += "<input type=\"button\" value=\"Cancel\" onclick=\"CancelEdit('" + postid + "');\" />";
                $('#bar' + postid).raw().innerHTML = bar;
            } else {
                $('#edit_preview_' + postid).hide();
                $('#edit_preview_cancel_' + postid).show();
                $('#edit_save_' + postid).show();
                $('#edit_cancel_' + postid).show();
            }
            $('#preview' + postid).raw().innerHTML = x[1];
        }
        $('#editcont' + postid).hide();
        Prism.highlightAll();
        MathJax.Hub.Queue(["Typeset",MathJax.Hub]);
        lazy_load();
    });

}

function EditLock(postid, status, token) {
    var toPost = [];
    toPost['status'] = status;
    toPost['token'] = token;
    console.log(toPost);
    if (location.href.match(/forum/) || location.href.match(/userhistory\.php\?action\=posts/)) {
        ajax.post("/forum/post/"+postid+"/editlock", toPost, function (response) {
            $('#post' + postid).raw().innerHTML = response;
        });
    }
    if (location.href.match(/collage/)) {
        ajax.post("/collage/post/"+postid+"/editlock", toPost, function (response) {
            $('#post' + postid).raw().innerHTML = response;
        });
    }
}

function TimeLock(postid, status, token) {
    var toPost = [];
    toPost['status'] = status;
    toPost['token'] = token;
    console.log(toPost);
    if (location.href.match(/forum/) || location.href.match(/userhistory\.php\?action\=posts/)) {
        ajax.post("/forum/post/"+postid+"/timelock", toPost, function (response) {
            $('#post' + postid).raw().innerHTML = response;
        });
    }
    if (location.href.match(/collage/)) {
        ajax.post("/collage/post/"+postid+"/timelock", toPost, function (response) {
            $('#post' + postid).raw().innerHTML = response;
        });
    }
}

function PinPost(postid, status, token) {
    var toPost = [];
    toPost['status'] = status;
    toPost['token'] = token;
    console.log(toPost);
    if (location.href.match(/forum/) || location.href.match(/userhistory\.php\?action\=posts/)) {
        ajax.post("/forum/post/"+postid+"/pinpost", toPost, function (response) {
            $('#post' + postid).raw().innerHTML = response;
        });
    }
    if (location.href.match(/collage/)) {
        ajax.post("/collage/post/"+postid+"/pinpost", toPost, function (response) {
            $('#post' + postid).raw().innerHTML = response;
        });
    }
}

function TrashPost(postid, status, token) {
    var toPost = [];
    toPost['status'] = status;
    toPost['token'] = token;
    console.log(toPost);
    if (location.href.match(/forum/) || location.href.match(/userhistory\.php\?action\=posts/)) {
        ajax.post("/forum/post/"+postid+"/trash", toPost, function (response) {
            jQuery('#post' + postid).replaceWith(response);
        });
    }
    if (location.href.match(/collage/)) {
        ajax.post("/collage/post/"+postid+"/trash", toPost, function (response) {
            jQuery('#post' + postid).replaceWith(response);
        });
    }
}

function SetSplitInterface() {
    $('#splitintothreadid').disable( !$('#split_merge').raw().checked );
    $('#split_comment').disable( !$('#split_trash').raw().checked );
    if ( $('#split_new').raw().checked ) {
       jQuery('#split_forum').css("color", 'black');
       jQuery('#split_forum').css("background", 'none');
       $('#split_forum').disable(false);
       $('#split_title').disable(false);
    } else {
       jQuery('#split_forum').css("color", '#bbb');
       jQuery('#split_forum').css("background-color", '#eee');
       $('#split_forum').disable(true);
       $('#split_title').disable(true);
    }
}

function Trash(threadid, postid) {
    var reason = prompt('Move this post to the Trash forum\n\nComment:');
    if (reason && reason != '') {
        var toPost = [];
        toPost['action']= 'trash_post';
        toPost['auth'] = authkey;
        toPost['threadid']= threadid;
        toPost['postid'] = postid;
        toPost['comment'] = reason;
        ajax.post("/forum/post/" + postid + "/trash", toPost, function (response) {
            var x = json.decode(response);
            if (is_array(x)) {
                $('#post' + postid).hide();
                jQuery.modal.close();
                //location.href=x[0];
            } else {    // error from ajax
                alert(x);
            }
        });
    }
}


function DeletePost(post, token = null) {
    postid = post;
    section = GetSection();
    var toPost = [];
    if (confirm('Are you sure you wish to delete this post?') == true) {
        if (luminance.includes(section.name)) {
            ajaxurl = '/' + section.name + '/post/' + postid + '/delete';
            toPost['token'] = token;
            ajax.post(ajaxurl, toPost, function(response) {
                var x = json.decode(response);
                if (is_array(x)) {
                    $('#post' + postid).hide();
                    jQuery.modal.close();
                // error from ajax... will be set inside the cookie
                } else {
                    alert(x);
                }
            });
        } else {
            switch(section.name) {

                case 'torrents':
                    ajax.get("/torrents.php?action=delete_post&auth=" + authkey + "&postid=" + postid, function () {
                      $('#post' + postid).hide();
                    });
                    break;

                case 'requests':
                    ajax.get("/requests.php?action=delete_comment&auth=" + authkey + "&postid=" + postid, function () {
                      $('#post' + postid).hide();
                    });
                    break;

                default:
                    // can't delete other types of post
                    break;
            }
        }
    }
}

function Quick_Preview() {
  //var quickreplybuttons;
  $('#post_preview').raw().value = "Make changes";
  $('#post_preview').raw().preview = true;
  ajax.post("/ajax.php?action=preview","quickpostform", function(response) {
    $('#quickreplypreview').show();
    $('#contentpreview').raw().innerHTML = response;
    $('#quickreplytext').hide();
    Prism.highlightAll();
    MathJax.Hub.Queue(["Typeset",MathJax.Hub]);
    lazy_load();
  });
}

function Quick_Edit() {
  //var quickreplybuttons;
  $('#post_preview').raw().value = "Preview";
  $('#post_preview').raw().preview = false;
  $('#quickreplypreview').hide();
  $('#quickreplytext').show();
}

function Newthread_Preview(mode) {
  $('#newthreadpreviewbutton').toggle();
  $('#newthreadeditbutton').toggle();
  if (mode) { // Preview
    ajax.post("/ajax.php?action=preview","newthreadform", function(response) {
      $('#contentpreview').raw().innerHTML = response;
    });
    $('#newthreadtitle').raw().innerHTML = $('#title').raw().value;
    var pollanswers = $('#answer_block').raw();
    if (pollanswers && pollanswers.children.length > 4) {
      pollanswers = pollanswers.children;
      $('#pollquestion').raw().innerHTML = $('#pollquestionfield').raw().value;
      for (var i=0; i<pollanswers.length; i+=2) {
        if (!pollanswers[i].value) {continue;}
        var el = document.createElement('input');
        el.id = 'answer_'+(i+1);
        el.type = 'radio';
        el.name = 'vote';
        $('#pollanswers').raw().appendChild(el);
        $('#pollanswers').raw().appendChild(document.createTextNode(' '));
        el = document.createElement('label');
        el.htmlFor = 'answer_'+(i+1);
        el.innerHTML = pollanswers[i].value;
        $('#pollanswers').raw().appendChild(el);
        $('#pollanswers').raw().appendChild(document.createElement('br'));
      }
      if ($('#pollanswers').raw().children.length > 4) {
        $('#pollpreview').show();
      }
    }
  } else { // Back to editor
    $('#pollpreview').hide();
    $('#newthreadtitle').raw().innerHTML = 'New Topic';
    var pollanswers = $('#pollanswers').raw();
    if (pollanswers) {
      var el = document.createElement('div');
      el.id = 'pollanswers';
      pollanswers.parentNode.replaceChild(el, pollanswers);
    }
  }
  $('#newthreadtext').toggle();
  $('#newthreadpreview').toggle();
  $('#subscribediv').toggle();
}

function LoadEdit(postid, depth) {
    section = GetSection();
    if (luminance.includes(section.name)) {
        ajaxurl = '/'+section.name + '/post/' + postid + '/edit?&depth=' + depth;
    } else {
        var ajaxurl = section.link + "?action=ajax_get_edit&postid=" + postid + "&depth=" + depth + "&type=" + section.name;
    }
    ajax.get(ajaxurl, function(response) {
        $('#content' + postid).raw().innerHTML = response;
    });
}

function LoadTorEdit(postid, depth) {
    section = GetSection();
    ajaxurl = "/torrents.php?action=ajax_get_edit&postid=" + postid + "&depth=" + depth + "&type=descriptions";

    ajax.get(ajaxurl, function(response) {
        $('#content' + postid).raw().innerHTML = response;
    });
}

function RevertEdit(postid, token = null) {
    var toPost = [];
    section = GetSection();
    var ajaxurl = section.link;

    // Split Luminance and Gazelle endpoints
    if (luminance.includes(section.name)) {
        ajaxurl = '/'+section.name+'/post/'+postid+'/revert';
        toPost['token'] = token;
    } else {
        toPost['action']= 'revertedit';
        toPost['auth'] = authkey;
        toPost['post'] = postid;
    }

    ajax.post(ajaxurl, toPost, function(response) {
        var x = json.decode(response);
        $('#post' + postid).raw().innerHTML = x;
    });
}

function pollVote(threadID, vote, token) {
    var toPost = [];
    toPost['token'] = token;
    toPost['vote'] = vote;
    section = GetSection();
    ajax.post("/forum/thread/"+threadID+"/poll/vote", toPost, function() {
        location.reload();
    });
}

function removePollOption(threadID, vote, token) {
    var toPost = [];
    toPost['token'] = token;
    toPost['vote'] = vote;
    section = GetSection();
    ajax.post("/forum/thread/"+threadID+"/poll/remove", toPost, function() {
        location.reload();
    });
}

function addPollOption(threadID, token) {
  var list = $('#poll_options').raw();
  var item = document.createElement("li");
    var form = document.createElement("form");
    form.method = "POST";
    form.action = "/forum/thread/"+threadID+"/poll/add";
      var auth = document.createElement("input");
      auth.type = "hidden";
      auth.name = "token";
      auth.value = token;
      form.appendChild(auth);

      var input = document.createElement("input");
      input.type = "text";
      input.name = "new_option";
      input.size = "50";
      form.appendChild(input);

      var submit = document.createElement("input");
      submit.type = "submit";
      submit.id = "new_submit";
      submit.value = "Add";
      form.appendChild(submit);
    item.appendChild(form);
  list.appendChild(item);
}

function check(checked) {
    var checkboxes = document.getElementById('forumfilterdiv').getElementsByTagName('input');

    if (checked) {
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].type == 'checkbox') {
                checkboxes[i].checked = true;
            }
        }
    } else {
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].type == 'checkbox') {
                checkboxes[i].checked = false;
            }
        }
    }
}

function Toggle_view(elem_id) {
    jQuery('#'+elem_id+'div').toggle();

    if (jQuery('#'+elem_id+'div').is(':hidden')) {
        jQuery('#'+elem_id+'button').text('(Show)');
        jQuery.cookie('allpost_forums', 0);
    } else {
        jQuery('#'+elem_id+'button').text('(Hide)');
        jQuery.cookie('allpost_forums', 1);
    }

    return false;
}

document.addEventListener('LuminanceLoaded', function() {
    var state = jQuery.cookie('allpost_forums');

    if (state == 1) {
        jQuery('#forumfilterbutton').text('(Hide)');
        jQuery('#forumfilterdiv').show();
    } else {
        jQuery('#forumfilterbutton').text('(Show)');
        jQuery('#forumfilterdiv').hide();
    }
});
