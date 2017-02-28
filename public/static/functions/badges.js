
function reload_num_forms(action){
    
    var num = parseInt( $('#numAdds').raw().value);
    window.location = location.protocol + '//' + location.hostname + "/tools.php?action="+action+"&numadd=" + num;
		 
}

function Select_Badge(id){ 
        
        var badgeid = $('#badgeid'+id).raw().value;
        ajax.getXML('ajax.php?action=get_badge_info&badgeid='+badgeid, function (responseXML) {
            x=responseXML.documentElement.getElementsByTagName("name");
            try {
              name = x[0].firstChild.nodeValue;
            } catch (er) {}
            x=responseXML.documentElement.getElementsByTagName("desc");
            try {
              desc = x[0].firstChild.nodeValue;
            } catch (er) {}
            x=responseXML.documentElement.getElementsByTagName("image");
            try {
              image = x[0].firstChild.nodeValue;
            } catch (er) {}
            
            $('#image'+id).raw().innerHTML = '<img src="'+image+'" title="'+name+'. '+desc+'" alt="'+name+'" />';
            $('#descr'+id).raw().innerHTML = desc;
            Set_Edit(id);
        }); 
}

function Select_Image(id){
        var image_file = $('#imagesrc'+id).raw().value;
        $('#image'+id).raw().innerHTML = '<img src="/static/common/badges/'+image_file+'" title="'+image_file+'" alt="'+image_file+'" />';
        Set_Edit(id);
}

function Set_Edit(id){
    $('#id_'+id).raw().checked = true;
}

function Fill_From(fillfrom, elementnames){
    // copies all elements upto fillfrom index alternately into all elements following
    var totalnum =  parseInt( $('#totalnum').raw().value);
    var num = fillfrom+1; // the num of elements to copy in each loop
    var iterations = Math.floor(totalnum/num); // num of loops to fill all elements after fillfrom index
    var i=num; // start at one element past fillfrom index
    for(var j=0;j<iterations;j++){ 
        for(var k=0;k<num;k++){
            if (i>=totalnum) break;
            var id = 'new'+k; // copy from the first num elements 
            var fillid = 'new'+i;
            if (fillid!=id){
                for(var l=0;l<elementnames.length;l++){
                    var name = '#' + elementnames[l];
                    if(name == '#descr' || name == '#image')
                        $(name+fillid).raw().innerHTML = $(name+id).raw().innerHTML;
                    else if(name == '#sendpm' || name == '#active')
                        $(name+fillid).raw().checked = $(name+id).raw().checked;
                    else
                        $(name+fillid).raw().value = $(name+id).raw().value;
                }
                /*
                $('#badge'+fillid).raw().value = $('#badge'+id).raw().value;
                $('#title'+fillid).raw().value = $('#title'+id).raw().value;
                $('#desc'+fillid).raw().value = $('#desc'+id).raw().value;
                $('#type'+fillid).raw().value = $('#type'+id).raw().value;
                $('#row'+fillid).raw().value = $('#row'+id).raw().value;
                $('#rank'+fillid).raw().value = $('#rank'+id).raw().value;
                $('#sort'+fillid).raw().value = $('#sort'+id).raw().value;
                $('#cost'+fillid).raw().value = $('#cost'+id).raw().value; */
            }
            //var a = ["badge","title","desc","type","row","rank","sort","cost"];
            i++;
        }
    }
}

 