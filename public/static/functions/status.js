
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
    });
}

function Status_Timer()
{
    var interval_id, bool = true, timeout = 15000;

    // This independent timer makes sure we don't run the update
    // if the user keeps switching tabs (focus/blur)
    setInterval(function(){
        bool = true;
    }, timeout);

    // Run status update only on focus (active tab), every x seconds
    jQuery(window).on("focus load", function() {
        if (!interval_id){
            // setInterval runs with a delay,
            // and we want to run the update once when we're back on focus;
            if (bool) {
                bool = false;
                Update_status();
            }
            interval_id = setInterval(Update_status, timeout);
        }
    });

    // Stop running the update when focus is lost
    jQuery(window).blur(function() {
        clearInterval(interval_id);
        interval_id = 0;
    });
}