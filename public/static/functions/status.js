
function change_status(onoff){
    var ToPost = [];
    ToPost['auth'] = authkey;
    //ToPost['location'] = location;
    if (onoff=='0') ToPost['remove'] = 1;
    ajax.post("torrents.php?action=change_status", ToPost, function(response){  
		$('#staff_status').raw().innerHTML = response; 	
    });
}


function Update_status(){
    var ToPost = [];
    ToPost['auth'] = authkey;
    ajax.post("torrents.php?action=update_status", ToPost, function(response){  
		$('#staff_status').raw().innerHTML = response; 	
            setTimeout("Update_status();", 15000);
    });
}


