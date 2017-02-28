//

function Toggle_All(open){
    if (open) {
        $('.friendinfo').show(); // weirdly the $ selector chokes when trying to set multiple elements innerHTML with a class selector
        jQuery('.togglelink').html('(Hide)');
    } else {
        $('.friendinfo').hide();
        jQuery('.togglelink').html('(View)'); 
    }
    return false;
}

function Edit_Comment() {
    $('#showcomment').hide();
    $('#updatecombtn').show();
    $('#comment').show();
    $('#editcombtn').hide();
}

        
function Check_Users() {
	
    var userlist = $('#adduserstext').raw().value;
    if (!userlist) return false;
      
    var ToPost = [];
    ToPost['userlist'] = userlist; 
    ToPost['auth'] = authkey; 
    ToPost['applyto'] = 'group';
    ToPost['action'] = 'checkusers';
    ajax.post("groups.php", ToPost, function(response){
        $('#showuserlist').raw().innerHTML = response;
	  $('#showuserlist').show();
        $('#adduserstext').hide();
        $('#checkusersbutton').hide();
        $('#editusersbutton').show();
        $('#addusersbutton').show();
        $('#addusersbutton').disable( $('#userids').raw().value == '' );
    });
    return false;
}

function Edit_Users() {
    $('#showuserlist').hide();
    $('#adduserstext').show();
    $('#checkusersbutton').show();
    $('#editusersbutton').hide();
    $('#addusersbutton').hide();
}
 

