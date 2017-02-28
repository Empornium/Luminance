
addDOMLoadEvent(Start_Tags);

function Start_Tags() {
    autocomp.start('tags','torrents');  
    resize('tags'); 
}

function clicked(tag) {
    
    if (tag===null || tag=='') return;
	if($('#tags').raw().value == "") {
		$('#tags').raw().value = tag; 
	} else {
		$('#tags').raw().value = $('#tags').raw().value + ' ' + tag;
	}
    resize('tags'); 
    $('#torrentssearch').raw().value ='';
    $('#torrentssearch').raw().focus();
    //CursorToEnd($('#tags').raw());
    
}


