function ChangeReportType() {
	ajax.post("/reportsv2.php?action=ajax_report","report_table", function (response) {
		$('#dynamic_form').raw().innerHTML = response;
	});
}

function ChangeResolve(reportid) {
    torrentid = $('#torrentid' + reportid).raw().value;
    ajax.get('/reportsv2.php?action=ajax_change_resolve&torrentid=' + torrentid + '&id=' + reportid + '&type=' + $('#resolve_type' + reportid).raw().value, function (response) {
            var x = json.decode(response);
            if (!is_array(x)) {
                 alert(x);
                 return;
            }
            $('#delete' + reportid).raw().checked = (x[0] == '1' ? true : false);
            $('#bounty_amount' + reportid).raw().innerHTML ='('+ x[3] + ')';
            $('#bounty' + reportid).raw().disabled = (x[3] == '0' ? true : false);
            $('#pm_message' + reportid).raw().innerHTML =x[4];
            if($('#uploaderid' + reportid).raw().value == $('#reporterid' + reportid).raw().value) {
                $('#warning' + reportid).raw().selectedIndex = 0;
                $('#upload' + reportid).raw().checked = false;
                $('#bounty' + reportid).raw().checked = false;
            } else {
                $('#upload' + reportid).raw().checked = (x[1] == '1' ? true : false);
                $('#warning' + reportid).raw().selectedIndex = x[2];
                $('#bounty' + reportid).raw().checked = (x[3] != '0' ? true : false);
            }
            if (x[5][0] == '-1') {
                $('#refundufl' + reportid).raw().disabled = true;
                jQuery('#refundufl' + reportid).parent().attr('title', 'There is no refundable UFL on this torrent');
            } else {
                $('#refundufl' + reportid).raw().disabled = false;
                $('#refundufl' + reportid).raw().checked = x[5][0] == '1' && x[0] == '1';
                jQuery('#refundufl' + reportid).attr('restore_value', x[5][0]);
                jQuery('#refundufl' + reportid).parent().attr('title', 'There is UFL on this torrent that the uploader paid '+addCommas(x[5][1])+' credits for');
            }
            jQuery('#refundufl' + reportid).attr('default_enable', $('#refundufl' + reportid).raw().disabled == false);
            $('#update_resolve' + reportid).raw().disabled = false;

        }
    );
}

function Load(reportid) {
	var t = $('#type' + reportid).raw().value;
	for (var i = 0; i<$('#resolve_type' + reportid).raw().options.length; i++) {
		if($('#resolve_type' + reportid).raw().options[i].value == t) {
			$('#resolve_type' + reportid).raw().selectedIndex = i;
			break;
		}
	}
    torrentid = $('#torrentid' + reportid).raw().value;
	//Can't use ChangeResolve() because we need it to block to do the uploader==reporter part
	ajax.get('/reportsv2.php?action=ajax_change_resolve&torrentid=' + torrentid + '&id=' + reportid + '&type=' + $('#resolve_type' + reportid).raw().value , function (response) {
            var x = json.decode(response);
            if (!is_array(x)) {
                 alert(x);
                 return;
            }
            $('#delete' + reportid).raw().checked = (x[0] == '1' ? true : false);
            $('#bounty_amount' + reportid).raw().innerHTML = '('+ x[3] + ')';
            $('#bounty' + reportid).raw().disabled = (x[3] == '0' ? true : false);
            $('#pm_message' + reportid).raw().innerHTML =x[4];
            if($('#uploaderid' + reportid).raw().value == $('#reporterid' + reportid).raw().value) {
                $('#warning' + reportid).raw().selectedIndex = 0;
                $('#upload' + reportid).raw().checked = false;
                $('#bounty' + reportid).raw().checked = false;
            } else {
                $('#upload' + reportid).raw().checked = (x[1] == '1' ? true : false);
                $('#warning' + reportid).raw().selectedIndex = x[2];
                $('#bounty' + reportid).raw().checked = (x[3] != '0' ? true : false);
            }
            if (x[5][0] == '-1') {
                $('#refundufl' + reportid).raw().disabled = true;
                jQuery('#refundufl' + reportid).parent().attr('title', 'There is no refundable UFL on this torrent');
            } else {
                $('#refundufl' + reportid).raw().disabled = false;
                $('#refundufl' + reportid).raw().checked = x[5][0] == '1' && x[0] == '1';
                jQuery('#refundufl' + reportid).attr('restore_value', x[5][0]);
                jQuery('#refundufl' + reportid).parent().attr('title', 'There is UFL on this torrent that the uploader paid '+addCommas(x[5][1])+' credits for');
            }
            jQuery('#refundufl' + reportid).attr('default_enable', $('#refundufl' + reportid).raw().disabled == false);
            if ($('#update_resolve' + reportid)) $('#update_resolve' + reportid).raw().disabled = false;
            UpdateUFLOption(reportid);
        }
    );
}

function UpdateUFLOption(reportid)
{
    if (jQuery('#refundufl' + reportid).attr('default_enable') == 'true') {
        // refundUFL is only available if we are deleting the torrent, let the interface show this
        $('#refundufl' + reportid).raw().disabled = !$('#delete' + reportid).raw().checked;
        // save/restore the current checked value
        if ($('#refundufl' + reportid).raw().disabled == false) {
            $('#refundufl' + reportid).raw().checked = jQuery('#refundufl' + reportid).attr('restore_value') == '1';
        } else {
            jQuery('#refundufl' + reportid).attr('restore_value', $('#refundufl' + reportid).raw().checked ? '1' : '0');
            $('#refundufl' + reportid).raw().checked = false;
        }
    }

}

function ErrorBox(reportid, message) {
	var div = document.createElement("div");
	div.id = "#error_box";
	div.innerHTML = "<table><tr><td class='center'>Message from report " + reportid + ": " + message + "\n <input type='button' value='Hide Errors' onclick='HideErrors();' /></td></tr></table>";
	$('#all_reports').raw().insertBefore(div, $('#all_reports').raw().firstChild);
}

function HideErrors() {
	if($('#error_box')) {
		$('#error_box').remove();
	}
}

function TakeResolve(reportid) {
	$('#submit_' + reportid).disable();
	ajax.post("/reportsv2.php?action=takeresolve","report_form" + reportid, function (response) {
		if(response) {
			ErrorBox(reportid, response);
		} else {
			if($('#from_delete' + reportid).raw().value > 0) {
                window.location = location.protocol + '//' + location.host + "/log.php?search=Torrent+"+ $('#from_delete' + reportid).raw().value;
                //window.location = location.protocol + '//' + location.host + location.pathname + "?id=" + $('#from_delete' + reportid).raw().value;
			} else {
				$('#report' + reportid).remove();
				if($('#dynamic').raw().checked) {
					NewReport(1);
				}
			}
		}
	});
}

function NewReport(q, view, id) {
	for (var i = 0; i < q; i++) {
		var url = "/reportsv2.php?action=ajax_new_report";
		if(view) {
			url += "&view=" + view;
		}
		if(id) {
			url += "&id=" + id;
		}

		ajax.get(url, function (response) {
			if(response) {
				var div = document.createElement("div");
				div.id = "report";
				div.innerHTML = response;
				$('#all_reports').raw().appendChild(div);
				var id = $('#newreportid').raw().value;
				Load(id);
				$('#newreportid').remove();
				if($('#no_reports').results()) {
					$('#all_reports').raw().removeChild($('#no_reports').raw());
				}
			} else {
				//No new reports at this time
				if(!$('#report').results() && !$('#no_reports').results()) {
					var div = document.createElement("div");
					div.id = "no_reports";
					div.innerHTML = "<table><tr><td class='center'><strong>No new reports! \\o/</strong></td></tr></table>";
					$('#all_reports').raw().appendChild(div);
				}
			}
		});
	}
}

function AddMore(view, id) {
	//Function will add the amount of reports in the input box unless that will take it over 50
	var x = 10;
	var a = $('#repop_amount').raw().value;
	if(a) {
		if(!isNaN(a) && a <= 50) {
			x = a;
		}
	}

	if(document.getElementsByName("reportid").length + x <= 50) {
		NewReport(x, view, id);
	} else {
		NewReport(50 - document.getElementsByName("reportid").length, view, id);
	}
}

function SendPM(reportid) {
	ajax.post("/reportsv2.php?action=ajax_take_pm", "report_form" + reportid, function (response) {
		if(response) {
			$('#uploader_pm' + reportid).raw().value = response;
		} else {
			$('#uploader_pm' + reportid).raw().value = "";
		}
	});
}

function UpdateComment(reportid) {
	ajax.post("/reportsv2.php?action=ajax_update_comment", 'report_form' + reportid, function (response) {
		if(response) {
			alert(response);
		}
	});
}

function GiveBack(id) {
	if(!id) {
		var x = document.getElementsByName("reportid");
            var str = ''; var div = '';
		for (i = 0; i < x.length; i++) {
                  str += div + x[i].value;
			div=',';
		}
            if(str != ''){
			ajax.get("/ajax.php?action=giveback_report&id=" + str, function (response) {
				//if(response) // alert(response);
                        for (i = x.length - 1; i >= 0 ; i--) {
                            $('#report' + x[i].value).remove();
                        }
			});
            }
	} else {
		ajax.get("/ajax.php?action=giveback_report&id=" + id, function (response) {
			//if(response) alert(response);
                  $('#report' + id).remove();
		});

	}
}

function ManualResolve(reportid) {
	var option = document.createElement("OPTION");
	option.value = "manual";
	option.text = "Manual Resolve";
	$('#resolve_type' + reportid).raw().options.add(option);
	$('#resolve_type' + reportid).raw().selectedIndex = $('#resolve_type' + reportid).raw().options.length - 1;
	TakeResolve(reportid);
}

function Dismiss(reportid) {
	var option = document.createElement("OPTION");
	option.value = "dismiss";
	option.text = "Invalid Report";
	$('#resolve_type' + reportid).raw().options.add(option);
	$('#resolve_type' + reportid).raw().selectedIndex = $('#resolve_type' + reportid).raw().options.length - 1;
	TakeResolve(reportid);
}

function ClearReport(reportid) {
	$('#report' + reportid).remove();
}

function Grab(reportid) {
	if(reportid) {
		ajax.get("/reportsv2.php?action=ajax_grab_report&id=" + reportid, function (response) {
			if(response == '1') {
				$('#grab' + reportid).raw().disabled = true;
			} else {
				alert('Grab failed for some reason :/');
			}
		});
	} else {
		var x = document.getElementsByName("reportid");
		for (i = 0; i < x.length; i++) {
			ajax.get("/reportsv2.php?action=ajax_grab_report&id=" + x[i].value, function (response) {
				if(response != '1') {
					alert("One of those grabs failed, sorry I can't be more useful :P");
				}
			});
			$('#grab' + x[i].value).raw().disabled = true;
		}
	}
}

function MultiResolve() {
	var multi = document.getElementsByName('multi');
	for (var j = 0; j < multi.length; j++) {
		if (multi[j].checked) {
			TakeResolve(multi[j].id.substring(5));
		}
	}

}

function UpdateResolve(reportid) {
	var newresolve = $('#resolve_type' + reportid).raw().options[$('#resolve_type' + reportid).raw().selectedIndex].value;
	ajax.get("/reportsv2.php?action=ajax_update_resolve&reportid=" + reportid + "&newresolve=" + newresolve, function (response) {
		//$('#update_resolve' + reportid).raw().disabled = true;
		window.location.reload(true);
      });
}


function Switch(reportid, reporterid, usercomment, torrentid, otherid) {
	//We want to switch positions of the reported torrent
	//This entails invalidating the current report and creating a new with the correct preset.
	Dismiss(reportid);

	var report = new Array();
	report['auth'] = authkey;
	report['torrentid'] = otherid;
	report['type'] = $('#type' + reportid).raw().value;
	report['otherid'] = torrentid;
	report['reporterid'] = reporterid;
	report['usercomment'] = usercomment;

	ajax.post('/reportsv2.php?action=ajax_create_report', report, function (response) {
			//Returns new report ID.
			if(isNaN(response)) {
				alert(response);
			} else {
				window.location = 'reportsv2.php?view=report&id=' + response;
			}
		}
	);
}
