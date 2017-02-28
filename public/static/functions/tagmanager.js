
var started = new Array(0);
var multitaglist = new Array(0);

function Select_Tag(char_search, tagID, tagname) {
    if (!in_array(char_search, started)) return;
    if(tagID==0) return;
    if(in_array(tagID, multitaglist)) return;
    
    multitaglist.push(tagID);
    //alert("Tag: "+tagID +" '"+tagname+"'");
    var div = ($('#multiID').raw().value !='')?',':'';
    $('#multiID').raw().value += div+tagID;
    $('#showmultiID').raw().value += div+tagID;
    $('#multiNames').raw().innerHTML += div+tagname;
    
    //$('#movetagid').raw().selectedIndex = 0;
}


function Clear_Multi() {
    $('#multiID').raw().value = '';
    $('#showmultiID').raw().value = '';
    $('#multiNames').raw().innerHTML = '';
    multitaglist = new Array(0);
}


function Get_Taglist_All(select_id, char_search) {
    //only load each taglist once
    if (in_array(char_search, started)) return;
    //record if we started fetching this one already
    started.push(char_search);
 
    
    ajax.get('ajax.php?action=get_taglist&char='+char_search, function(response) {
        var x = json.decode(response);
        if ( is_array(x)){
            $('#'+select_id).raw().innerHTML = x[0];
        } else {
            alert(x);
        }
    });
}

function Get_Taglist(select_id, char_search) {
    //only load each taglist once
    if (in_array(char_search, started)) return;
    //record if we started fetching this one already
    started.push(char_search);
 
    var uses = '';
    if (!$('#excludeuses') || $('#excludeuses').raw().checked){
        var numuses = parseInt($('#numuses').raw().value);
        if (numuses>0) uses = '&minuses=' + numuses;
    }
    
    ajax.get('ajax.php?action=get_taglist&char='+char_search+uses, function(response) {
        var x = json.decode(response);
        if ( is_array(x)){
            $('#'+select_id).raw().innerHTML = x[0];
        } else {
            alert(x);
        }
    });
}

        
function Check_Taglist() {
	$('#checkresults').raw().innerHTML = '<div class=\"box pad\">checking input...</div>';
    var taglist = $('#tagconvertlist').raw().value;
    var ToPost = [];
    ToPost['taglist'] = taglist; 
    ToPost['auth'] = authkey; 
    ajax.post("ajax.php?action=check_synonym_list", ToPost, function(response){
        var x = json.decode(response);
        if ( is_array(x)){
            $('#taglisttosynomyn').disable( parseInt(x[0]) == 0 );
            $('#checkresults').raw().innerHTML = x[1];
            
        } else {
            //alert(x);
            $('#checkresults').raw().innerHTML = x;
        }
    });
    return false;
}

        
function Process_Taglist() {
	$('#checkresults').raw().innerHTML = '<div class=\"box pad\">processing input...</div>';
    var taglist = $('#tagconvertlist').raw().value;
    var ToPost = [];
    ToPost['taglist'] = taglist; 
    ToPost['auth'] = authkey; 
    ajax.post("ajax.php?action=input_synonyms_list", ToPost, function(response){
        var x = json.decode(response);
        if ( is_array(x)){
            $('#taglisttosynomyn').disable( true );
            $('#checkresults').raw().innerHTML = x[1];
            
        } else {
            //alert(x);
            $('#checkresults').raw().innerHTML = x;
        }
    });
    return false;
}


function Dirty_Taglist() {
    $('#taglisttosynomyn').disable(true);
    resize('tagconvertlist');
    
}

 