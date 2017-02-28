/*function Categories() {
	ajax.get('ajax.php?action=upload_section&categoryid=' + $('#categories').raw().value, function (response) {
		$('#dynamic_form').raw().innerHTML = response;
	});
}
 */

function add_tag() {
	if($('#tags').raw().value == "") {
		$('#tags').raw().value = $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
	} else if($('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value == '---') {
	} else {
		$('#tags').raw().value = $('#tags').raw().value + ' ' + $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
	}
      CursorToEnd($('#tags').raw());
      resize('tags');
}


function SynchInterface(){
    change_tagtext();
    resize('tags');
}

function SelectTemplate(can_delete_any){ // a proper check is done in the backend.. the param is just for the interface
    $('#fill').disable($('#template').raw().selectedIndex==0);
    var can_delete = can_delete_any=='1' || !EndsWith($('#template').raw().options[$('#template').raw().selectedIndex].text, ')*');
    $('#delete').disable($('#template').raw().selectedIndex==0 || !can_delete);
    $('#save').disable($('#template').raw().selectedIndex==0 || !can_delete);
    return false;
}


function DeleteTemplate(can_delete_any){
    
    var TemplateID = $('#template').raw().options[$('#template').raw().selectedIndex].value; 
    if(TemplateID==0) return false;
    
    if(!confirm("This will permanently delete the selected template '" + $('#template').raw().options[$('#template').raw().selectedIndex].text + "'\nAre you sure you want to proceed?"))return false;
    
    var ToPost = [];
    ToPost['template'] = TemplateID;
 
    ajax.post("upload.php?action=delete_template", ToPost, function(response){
        var x = json.decode(response);  
        if ( is_array(x)){ 
            if (x[0]==0) { //  
                $('#messagebar').add_class('alert');
                $('#messagebar').html(x[1]);
            } else {  
                $('#messagebar').remove_class('alert');
                $('#messagebar').html(x[1]);
            } 
            $('#template_container').html(x[2]);
        } else { // a non number == an error  if ( !isnumeric(response)) 
                $('#messagebar').add_class('alert');
                $('#messagebar').html(x);
        }
        $('#messagebar').show(); 
        SelectTemplate(can_delete_any);
    });
    return false;
}



function OverwriteTemplate(can_delete_any){
      
    var TemplateID = $('#template').raw().options[$('#template').raw().selectedIndex].value;
    if(TemplateID==0) return false;
    
    if(!confirm("This will overwrite the selected template '" + $('#template').raw().options[$('#template').raw().selectedIndex].text + "'\nAre you sure you want to proceed?"))return false;
    
    return SaveTemplate(can_delete_any, 0, '', TemplateID);
}

function AddTemplate(can_delete_any, is_public){
    if(is_public==1) if(!confirm("Public templates are available for any user to use and display the authorname\nWarning: You cannot delete a public template once it is created\nAre you sure you want to proceed?"))return false;
    var name = prompt("Please enter the name for this template", "");
    if (!name || name =='') return false; 
    return SaveTemplate(can_delete_any, is_public, name, 0);
}



function SaveTemplate(can_delete_any, is_public, name, id){
    
    var ToPost = [];
    ToPost['templateID'] = id;
    ToPost['name'] = name;
    ToPost['ispublic'] = is_public;
    ToPost['title'] = $('#title').raw().value;
    ToPost['category'] = $('#category').raw().value;
    ToPost['image'] = $('#image').raw().value;
    ToPost['tags'] = $('#tags').raw().value;
    ToPost['body'] = $('#desc').raw().value;
    
    ajax.post("upload.php?action=add_template", ToPost, function(response){
        
        var x = json.decode(response);  
        if ( is_array(x)){ 
            if (x[0]==0) { //  
                $('#messagebar').add_class('alert');
                $('#messagebar').html(x[1]);
            } else {  
                $('#messagebar').remove_class('alert');
                $('#messagebar').html(x[1]);
            } 
            $('#template_container').html(x[2]);
        } else { // a non number == an error  if ( !isnumeric(response)) 
                $('#messagebar').add_class('alert');
                $('#messagebar').html(x);
        }
        $('#messagebar').show(); 
        SelectTemplate(can_delete_any);
       
    });
    return false;
}





var LogCount = 1;

function AddLogField() {
		if(LogCount >= 200) {return;}
		var LogField = document.createElement("input");
		LogField.type = "file";
		LogField.id = "file";
		LogField.name = "logfiles[]";
		LogField.size = 50;
		var x = $('#logfields').raw();
		x.appendChild(document.createElement("br"));
		x.appendChild(LogField);
		LogCount++;
}

function RemoveLogField() {
		if(LogCount == 1) {return;}
		var x = $('#logfields').raw();
		for (i=0; i<2; i++) {x.removeChild(x.lastChild);}
		LogCount--;
}


function Upload_Quick_Preview() { 
	$('#post_preview').raw().value = "Make changes";
	$('#post_preview').raw().preview = true;
	ajax.post("ajax.php?action=preview_upload","upload_table", function(response){
        
        var x = json.decode(response); 
        if ( is_array(x)){
            $('#uploadpreviewbody').show();
            $('#messagebar').raw().innerHTML = x[0];
            if(x[0]) $('#messagebar').show();
            else $('#messagebar').hide()
            $('#contentpreview').raw().innerHTML = x[1];
            $('.uploadbody').hide(); 
        }
	});
}

function Upload_Quick_Edit() {
	$('#post_preview').raw().value = "Preview";
	$('#post_preview').raw().preview = false;
	$('#uploadpreviewbody').hide();
	$('.uploadbody').show(); 
}
 