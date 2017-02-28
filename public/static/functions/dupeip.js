
var loadedlists = new Array(0);

function get_users(el_id, ip) {
    
    if(!$('#users_'+el_id).has_class('hidden')) {
        $('#button_'+el_id).html('(show)');
        $('#users_'+el_id).hide();
        return;
    }
        
    //only load each list once
    if (in_array(el_id, loadedlists)) {
        $('#button_'+el_id).html('(hide)');
        $('#users_'+el_id).show();
        return;
    }
    //record if we started fetching this one already
    loadedlists.push(el_id);
    
	ajax.get('ajax.php?action=get_ip_dupes&ip=' + ip, function (response) {
        var x = json.decode(response); 
        if ( is_array(x)){
            //alert(x[1]);
            $('#users_'+el_id).html(x[0]);
            $('#button_'+el_id).html('(hide)');
            $('#users_'+el_id).show();
        } else {    // error from ajax
            alert(x);
        } 
	});
}


function change_view(order,way){
    var weeks = parseInt($('#weeks').raw().value);
    var banr = $('#ban_reason').raw().selectedIndex ;
    location.href = "tools.php?action=banned_ip_users&ban_reason="+banr+"&weeks="+weeks+"&order_by="+order+"&order_way="+way;
}