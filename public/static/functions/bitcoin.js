/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


function CheckAddressLoadNext(id, eur_rate, numconfirms, maxid, state, sleep) {
    $('#btc_balance_'+id).raw().innerHTML="fetching....";
    var address =  $('#address_'+id).raw().innerHTML;
	ajax.get("ajax.php?action=check_donation&address="+address+"&numt="+numconfirms, function(response){
        var euros = response*eur_rate;
        $('#btc_balance_'+id).raw().innerHTML= parseFloat(response).toFixed(6);
                           
        $('#euros_'+id).raw().innerHTML=" &euro;"+euros.toFixed(2) ;
	ajax.get("ajax.php?action=change_donation&address="+address+"&amount="+euros.toFixed(2));
        if(state=='1' && $('#status_'+id).raw().innerHTML=='submitted' && parseFloat(response)==0){
            $('#state_button_'+id).raw().innerHTML= '<input type="button" onclick="ChangeState('+id+',\''+address+'\',\'cleared\', false)" value="change state to cleared" />' ;
        }
        var next = parseInt(id) + 1;
        if (next<=maxid) {
            setTimeout("CheckAddressLoadNext("+next+","+eur_rate+",'6',"+maxid+","+state+","+sleep+")", sleep );
        }
	});
}
 


function CheckAddress(id, eur_rate, address, numconfirms, state) {
    $('#btc_balance_'+id).raw().innerHTML="fetching....";
	ajax.get("ajax.php?action=check_donation&address="+address+"&numt="+numconfirms, function(response){
        var euros = response*eur_rate;
        $('#btc_balance_'+id).raw().innerHTML= parseFloat(response).toFixed(6);
        $('#euros_'+id).raw().innerHTML=" &euro;"+euros.toFixed(2)+"";
	ajax.get("ajax.php?action=change_donation&address="+address+"&amount="+euros.toFixed(2));
        if(state=='1' && $('#status_'+id).raw().innerHTML=='submitted' && parseFloat(response)==0){
            $('#state_button_'+id).raw().innerHTML= '<input type="button" onclick="ChangeState('+id+',\''+address+'\',\'cleared\', false)" value="change state to cleared" title="clearing a donation changes its status to cleared and moves this record to the cleared list. This is for book-keeping purposes only and can only be seen here in the donation log." />' ;
        }
	});
}


function ChangeState(id, address, newstate, warn) {
    if (!in_array(newstate, new Array( 'unused','submitted','cleared'))) newstate='unused';
    if (!confirm("Are you sure you want to change the status to '"+newstate+"'")) return;
	ajax.get("ajax.php?action=change_donation&address="+address+"&state="+newstate, function(response){
        if(response==1){ 
            $('.record'+id).hide();
            //$('#status_'+id).raw().innerHTML= newstate;
            //$('#state_button_'+id).raw().innerHTML= "";
        } else { // error
            $('#state_button_'+id).raw().innerHTML= response;
        }
	});
}
