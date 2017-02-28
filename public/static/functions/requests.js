

function SynchInterface(){
    change_tagtext();
    resize('tags');
}

function Cover_Toggle() {

    jQuery('#coverimage').toggle();

    if (jQuery('#coverimage').is(':hidden'))
        jQuery('#covertoggle').html('(Show)');
    else
        jQuery('#covertoggle').html('(Hide)');

    jQuery.cookie('requestDetailsState', Get_Cookie());
    return false;
}

function TagBox_Toggle() {

    jQuery('#tag_container').toggle();

    if (jQuery('#tag_container').is(':hidden'))
        jQuery('#tagtoggle').html('(Show)');
    else
        jQuery('#tagtoggle').html('(Hide)');

    jQuery.cookie('requestDetailsState', Get_Cookie());
    return false;
}

function Desc_Toggle() {

    jQuery('#descbox').toggle();

    if (jQuery('#descbox').is(':hidden'))
        jQuery('#desctoggle').html('(Show)');
    else
        jQuery('#desctoggle').html('(Hide)');

    jQuery.cookie('requestDetailsState', Get_Cookie());
    return false;
}



function Get_Cookie() {
    return json.encode([((jQuery('#coverimage').is(':hidden'))?'0':'1'),
                        ((jQuery('#tag_container').is(':hidden'))?'0':'1'),
                        ((jQuery('#descbox').is(':hidden'))?'0':'1')]);
}


function Load_Details_Cookie()  {


	if(jQuery.cookie('requestDetailsState') == undefined) {
		jQuery.cookie('requestDetailsState', json.encode(['1', '1','1']));
	}
	var state = json.decode( jQuery.cookie('requestDetailsState') );

	if(state[0] == '0') {
		jQuery('#coverimage').hide();
		jQuery('#covertoggle').text('(Show)');
      } else
		jQuery('#covertoggle').text('(Hide)');

	if(state[1] == '0') {
		jQuery('#tag_container').hide();
		jQuery('#tagtoggle').text('(Show)');
      } else
		jQuery('#tagtoggle').text('(Hide)');

	if(state[2] == '0') {
		jQuery('#descbox').hide();
		jQuery('#desctoggle').text('(Show)');
      } else
		jQuery('#desctoggle').text('(Hide)');

}













function Preview_Request() {
	if ($('#preview').has_class('hidden')) {
		var ToPost = [];
		ToPost['body'] = $('#quickcomment').raw().value;
		ajax.post('ajax.php?action=preview', ToPost, function (data) {
			$('#preview').raw().innerHTML = data;
			$('#preview').toggle();
			$('#editor').toggle();
			$('#previewbtn').raw().value = "Edit";
		});
	} else {
		$('#preview').toggle();
		$('#editor').toggle();
		$('#previewbtn').raw().value = "Preview";
	}
}

function ReadableAmount(size) {
    var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    var i = 0;
    while(size >= 1024) {
        size /= 1024;
        ++i;
    }
    return size.toFixed(1) + ' ' + units[i];
}


// used on the requests page and user profile
function VotePromptMB(requestid) {
	if(!requestid) return; // error
    var amount = prompt("Please enter the amount in MB you want to add to the bounty\nmin vote: 20 MB\nmax vote 10,240 MB = 10 GB", 100);
    if(!amount || amount==0) return;
    if(amount < 20 ) amount = 20 ;
    if (amount> 10240 ) amount = 10240; // max vote 10gb from this prompt
    //Vote(amountmb * 1024 * 1024, requestid);
    amount = amount * 1024 * 1024; // convert to bytes

    if (!confirm(get_size(amount) + ' will immediately be removed from your upload total, are you sure?')) return;

	ajax.get('requests.php?action=takevote&id=' + requestid + '&auth=' + $('#auth').raw().value + '&amount=' + amount, function (response) {

			var x = json.decode(response);

            if(!is_array(x)) {  // unexpected error
				alert("Error processing vote: " + response );
                return;
            }
            amount = x[1];      // amount

            if(x[0] == 'bankrupt') {    // failed to vote
				alert("You do not have sufficient upload credit to add " + get_size(amount) + " to this request");
				return;
            } else if (x[0] == 'success') {
				//votecount.innerHTML = (parseInt(votecount.innerHTML)) + 1;
			}
            // now we get all values from ajax, means page always stays internally consistent even with paralell voting
            $('#vote_count_'+requestid).html( x[3]);
			$('#bounty_'+requestid).html(get_size(x[2]));

		}
	);

}

// used on request page
function Vote(amount, requestid) {
    if(typeof amount == 'undefined') {
        amount = parseInt($('#amount').raw().value);
    }

    if(amount == 0) amount = 100 * 1024 * 1024;
    else if(amount < 20*1024*1024) amount = 20 * 1024 * 1024;

    if (!confirm(ReadableAmount(amount) + ' will immediately be removed from your upload total, are you sure?')) return;

    var votecount;
    if(!requestid) { // used on request page
        requestid = $('#requestid').raw().value;
        votecount = $('#votecount').raw();
    } else {        // used on requests browse page
        votecount = $('#vote_count_' + requestid).raw();
    }

    ajax.get('requests.php?action=takevote&id=' + requestid + '&auth=' + $('#auth').raw().value + '&amount=' + amount, function (response) {

            var x = json.decode(response);

            // unexpected error
            if(!is_array(x)) {
                error_message("Error processing vote: " + response );
                return;
            }
            amount = x[1];

            if(x[0] == 'bankrupt') {    // failed to vote
                error_message("You do not have sufficient upload credit to add " + get_size(amount) + " to this request");
                return;
            }
            // keep updated just in case they vote again
            $('#current_uploaded').raw().value = $('#current_uploaded').raw().value - amount;

            // we get all values from ajax, means page always stays internally consistent even with parallel voting
            votecount.innerHTML = x[3];
            var startBounty = x[2] - x[1];
            var totalBounty = x[2];
            $('#total_bounty').raw().value = totalBounty;
            $('#formatted_bounty').raw().innerHTML = get_size(totalBounty);
            $('#formatted_fillerbounty').raw().innerHTML = get_size(totalBounty*0.5);
            $('#formatted_uploaderbounty').raw().innerHTML = get_size(totalBounty*0.5);

            save_message("Bounty was " + get_size(startBounty) + " + your vote of " + get_size(amount) + " = Total Bounty: " + get_size(totalBounty));
            $('#button_vote').raw().disabled = true;

            if (x[4]!==false) {
                $('#request_votes').html(x[4]);
            }
        }
    );
}

function Calculate() {
    var unit = $('#unit').raw().options[$('#unit').raw().selectedIndex].value;
    var mul;

    if(unit == 'mb') {
        mul = (1024*1024);
    } else if(unit == 'gb') {
        mul = (1024*1024*1024);
    } else { // tb
        mul = (1024*1024*1024*1024);
    }

    var value = $('#amount_box').raw().value;
    var amt = Math.floor(value * mul);
    var uploaded = $('#current_uploaded').raw().value;
    var downloaded = $('#current_downloaded').raw().value;
    var info = '';
    var allow = false;

    if (amt > uploaded) {
        $('#new_uploaded').raw().innerHTML = "0.00 B";
        $('#new_bounty').raw().innerHTML = "0.00 MB";
        $('#new_bounty').raw().innerHTML = get_size(amt);
        $('#new_ratio').raw().innerHTML = ratio(0, 1);
        allow = false;
        info = "You can't afford that request!";
    } else if (isNaN(value)) {
        $('#new_uploaded').raw().innerHTML = get_size(uploaded);
        $('#new_bounty').raw().innerHTML = "0.00 MB";
        allow = false;
        info = 'Not a valid number!';
    } else {
        if (window.location.search.indexOf('action=new') != -1 && amt < 100*1024*1024) {
            allow = false;
            info = 'New Requests must have at least 100MB bounty';
        } else if (window.location.search.indexOf('action=view') != -1 && amt < 20*1024*1024) {
            allow = false;
            info = 'Votes must add at least 20MB bounty';
        } else {
            allow = amt <= (uploaded - downloaded);
            if (allow) info = get_size(amt) + ' will immediately be removed from your upload total.';
            else info = get_size(amt) + ' is too large, request bounties cannot take your ratio below 1.0';
        }
        $('#amount').raw().value = amt;
        $('#new_uploaded').raw().innerHTML = get_size(uploaded - amt);
        $('#new_ratio').raw().innerHTML = ratio(uploaded - amt, downloaded);
        $('#new_bounty').raw().innerHTML = get_size(amt);
    }
    $('#button_vote').raw().disabled = !allow;
    if (!allow) info = '<strong class="warning">'+info+'</strong>'
    $('#inform').raw().innerHTML = info;
}


function add_tag() {
	if ($('#tags').raw().value == "") {
		$('#tags').raw().value = $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
	} else if ($('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value == "---") {
	} else {
		$('#tags').raw().value = $('#tags').raw().value + " " + $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
	}
}

function Toggle(id, disable) {
	var arr = document.getElementsByName(id + '[]');
	var master = $('#toggle_' + id).raw().checked;
	for (var x in arr) {
		arr[x].checked = master;
		if(disable == 1) {
			arr[x].disabled = master;
		}
	}

	if(id == "formats") {
		ToggleLogCue();
	}
}

function ToggleLogCue() {
	var formats = document.getElementsByName('formats[]');
	var flac = false;

	if(formats[1].checked) {
		flac = true;
	}

	if(flac) {
		$('#logcue_tr').show();
	} else {
		$('#logcue_tr').hide();
	}
	ToggleLogScore();
}

function ToggleLogScore() {
	if($('#needlog').raw().checked) {
		$('#minlogscore_span').show();
	} else {
		$('#minlogscore_span').hide();
	}
}



addDOMLoadEvent(Load_Details_Cookie);
