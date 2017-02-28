var BBCode = {
	spoiler: function(link) {
		if($(link.nextSibling).has_class('hidden')) {
			$(link.nextSibling).show();
			$(link).html('Hide');
		} else {
			$(link.nextSibling).hide();
			$(link).html('Show');
		}
	}
};

function Validate_Form(message_div, fields) {
    if ( !is_array(fields)) {
        if (fields == null) return false;
        fields = new Array(fields);
    }
    message_div = '#' + message_div;
    failed = false;
    for (i=0;i<fields.length;i++) {
        var message =  jQuery.trim($('#'+fields[i]).raw().value);
        //alert('checking field: #'+fields[i] + ' msg: '+ message);
        if (message==null || message==""){
            failed=true;
            break;
        }
    }
    if (failed) {
	  $(message_div).raw().innerHTML = 'One or more fields were blank.';
        $(message_div).add_class('alert');
        $(message_div).show();
        jQuery(message_div).fadeIn(0);
        setTimeout("jQuery('" + message_div + "').fadeOut(400)", 2000);
        return false;
    }
    return true;
}

function Preview_Collage() {
	if ($('#preview').has_class('hidden')) {
		var ToPost = [];
		ToPost['body'] = $('#description').raw().value;
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

function Sandbox_Preview() { 
    $('#preview_button').raw().value = "Updating...";
    ajax.post("ajax.php?action=preview","messageform", function(response){
        $('#preview_content').raw().innerHTML = response;
        $('#preview').show();
        $('#preview_button').raw().value = "Update Preview";
    });
}


function Quick_Preview_Blog() { 
	$('#post_preview').raw().value = "Edit";
	$('#post_preview').raw().preview = true;
	ajax.post("ajax.php?action=preview_blog","quickpostform", function(response){
		$('#quickreplypreview').show();
		$('#contentpreview').raw().innerHTML = response;
		$('#quickreplytext').hide();
	});
}

function Quick_Edit_Blog() { 
	$('#post_preview').raw().value = "Preview";
	$('#post_preview').raw().preview = false;
	$('#quickreplypreview').hide();
	$('#quickreplytext').show();
}


function Preview_Article() { 
	$('#post_preview').raw().value = "Edit";
	$('#post_preview').raw().preview = true;
	ajax.post("ajax.php?action=preview_article","quickpostform", function(response){
		$('#quickreplypreview').show();
		$('#contentpreview').raw().innerHTML = response;
		$('#quickreplytext').hide();
	});
}

function Edit_Article() { 
	$('#post_preview').raw().value = "Preview";
	$('#post_preview').raw().preview = false;
	$('#quickreplypreview').hide();
	$('#quickreplytext').show();
}


//numLoaded = 0;
//maxSmilies = 9999;
function Open_Smilies(alreadyloaded, loadincrement, textID) {
      // first call inspect alreadyloaded param to allow for preloaded smilies in php
      var open_overflow_button = '#open_overflow' + textID;
      var open_overflow_more_button = '#open_overflow_more' + textID;
      var smiley_overflow_area = '#smiley_overflow' + textID;
      if ($('#smiley_count' + textID).raw().innerHTML != "")
            numLoaded = parseInt($('#smiley_count' + textID).raw().innerHTML);
      else numLoaded=0;
      if ($('#smiley_max' + textID).raw().innerHTML != "")
            maxSmilies = parseInt($('#smiley_max' + textID).raw().innerHTML);
      else maxSmilies=9999;
      if (numLoaded == 0) { 
          if (alreadyloaded > 0) {numLoaded = alreadyloaded;}
          opento = numLoaded + loadincrement;
      } else if ($(open_overflow_button).raw().isopen == true) {
          opento = numLoaded + loadincrement;
      }
      if (opento > maxSmilies) {opento = maxSmilies;} 
	$(open_overflow_button).raw().isopen = true; // track first button status
      if (numLoaded < opento && numLoaded < maxSmilies) {
          // depending on which buttons are visible display loading status in one of them
          if ($(open_overflow_more_button).raw().isopen) {$(open_overflow_more_button).raw().innerHTML = "Loading smilies";}
          else {$(open_overflow_button).raw().innerHTML = "Loading smilies";}
          // get the requested smiley data as xml;
          // <smilies><smiley><bbcode>: code :</bbcode><url>http://url</url></smiley></smilies><maxsmilies>num</maxsmilies>
          ajax.getXML("ajax.php?action=get_smilies&indexfrom=" + numLoaded + "&indexto=" + opento, function(responseXML){
                txt='';
                // construct the html from the xml data
                x=responseXML.documentElement.getElementsByTagName("smiley");
                for (i=0;i<x.length;i++) {
                    xx=x[i].getElementsByTagName("bbcode"); 
                    try {
                        txt=txt +'<a class="bb_smiley" title="' + xx[0].firstChild.nodeValue + '" href="javascript:insert(\' ' + xx[0].firstChild.nodeValue + ' \', \'' + textID + '\' );">';
                    } catch (er) { }
                    xx=x[i].getElementsByTagName("url"); 
                    try {
                        txt=txt + xx[0].firstChild.nodeValue + '</a>';
                    } catch (er) { }
                }
                x=responseXML.documentElement.getElementsByTagName("maxsmilies");
                try {
                    maxSmilies = x[0].firstChild.nodeValue;
                } catch (er) {}
                $(smiley_overflow_area).raw().innerHTML += txt;
                //$(smiley_overflow_area).show();
                $(open_overflow_button).raw().innerHTML = "Hide smilies";
                numLoaded = opento;
                Toggle_Load_Button(numLoaded < maxSmilies, textID);
                $('#smiley_max' + textID).raw().innerHTML = maxSmilies;
                $('#smiley_count' + textID).raw().innerHTML = numLoaded;
                $(smiley_overflow_area).show();
          });
      } else { 
          $(smiley_overflow_area).show();
          $(open_overflow_button).raw().innerHTML = "Hide smilies";
          Toggle_Load_Button(numLoaded < maxSmilies, textID);
      }
}
function Toggle_Load_Button(show, textID){
    if (show) {
        $('#open_overflow_more'+ textID).raw().isopen = true; 
        $('#open_overflow_more'+ textID).raw().innerHTML = "Load more smilies";
        $('#open_overflow_more'+ textID).show();
    } else {
        $('#open_overflow_more'+ textID).raw().isopen = false; 
        $('#open_overflow_more'+ textID).raw().innerHTML = "";
        $('#open_overflow_more'+ textID).hide();
    } 
}
function Close_Smilies(textID) { 
	$('#smiley_overflow'+ textID).hide();
	$('#open_overflow'+ textID).raw().isopen = false;
	$('#open_overflow'+ textID).raw().innerHTML = "Show smilies";
      $('#open_overflow_more'+ textID).raw().isopen = false; 
      $('#open_overflow_more'+ textID).raw().innerHTML = "";
      $('#open_overflow_more'+ textID).hide();
}


function CursorToEnd(textarea){ 
     // set the cursor to the end of the text already present
    if (textarea.setSelectionRange) { // ff/chrome/opera
        var len = textarea.value.length * 2; //(*2 for opera stupidness)
        textarea.setSelectionRange(len, len);
    } else { // ie8-, fails in chrome
        textarea.value = textarea.value;
    }
}
function EndsWith(str, suffix) {
    return str.indexOf(suffix, str.length - suffix.length) !== -1;
}
 

//made by putyn@tbdev.net lastupdate 28/12/2009
function wrap(tag, replacetext, attribute, textID) {
  var r = replacetext ? replacetext : "";
  var v = tag ? tag : "";
  var e = attribute ? attribute : "";

  var obj = document.getElementById(textID);
  var opentag = "[" + v + (e ? "=" + e : "") + "]";
  var closetag = "[/" + v + "]";

  if (document.selection) {
    var str = document.selection.createRange().text;
    obj.focus();
    var range = document.selection.createRange();
    
    //range.text = "[" + v + (e ? "=" + e : "") + "]" + (r ? r : str) + "[/" + v + "]";
    
    range.text = opentag + (r ? r : str) + closetag;
    range.moveStart('character', +opentag.length);
    range.moveEnd('character', -closetag.length);
    //range.select();
  } else {
    var len = obj.value.length;
    var start = obj.selectionStart;
    var end = obj.selectionEnd;
    var sel = obj.value.substring(start, end);
    /* var opentag = "[" + v + (e ? "=" + e : "") + "]";
    var closetag = "[/" + v + "]"; */
    obj.value = obj.value.substring(0, start) + opentag + (r ? r : sel) + closetag + obj.value.substring(end, len);
    obj.selectionStart = start + opentag.length;
    obj.selectionEnd = start + opentag.length + (r ? r : sel).length ;
  }
  obj.focus();
}

function tagwrap(opentag, closetag, textID) {
  opentag = opentag ? opentag : "";
  closetag = closetag ? closetag : "";

  var textarea = document.getElementById(textID);

  if (document.selection) {
    var str = document.selection.createRange().text;
    textarea.focus();
    var range = document.selection.createRange();
    range.text = opentag + str + closetag;
    range.moveStart('character', +opentag.length);
    range.moveEnd('character', -closetag.length);
  } else {
    var len = textarea.value.length;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var sel = textarea.value.substring(start, end);
    textarea.value = textarea.value.substring(0, start) + opentag + sel + closetag + textarea.value.substring(end, len);
    textarea.selectionStart = start + opentag.length;
    textarea.selectionEnd = end + opentag.length;
  }
  textarea.focus();
}


function video(textID) {
    var linkAddr = prompt("Please enter the url ", "http://www.youtube.com/");

    if (linkAddr && linkAddr != "http://www.youtube.com/") insert('[video=' + linkAddr + ']', textID);
}

function flash(textID) {
    var linkAddr = prompt("Please enter the url for the flash object", "http://");
    if (linkAddr && linkAddr != "http://") {
        var linkSize = prompt("Please enter the size for the flash object", "400,400");
        if (linkAddr && linkSize) wrap('flash', linkAddr, linkSize, textID);
    }
}

function anchor(textID) {
    var linkName = prompt("Please enter the name for the anchored heading", "");
    if (linkName && linkName != "") {
        var linkTitle = prompt("Please enter the heading text", "");
        //if (linkName && linkTitle) 
        wrap('anchor', linkTitle, linkName, textID);
    }
}

function link(textID) {
    var linkAddr = prompt("Please enter the relative page url\nNOTE: only local pages!", "/");
    if (linkAddr && linkAddr != "/") {
        var linkTitle = prompt("Please enter the link text", "");
        if (linkTitle == "") linkTitle = linkAddr;
        if (linkAddr && linkTitle) wrap('link', linkTitle, linkAddr, textID);
    }
}

function url(textID) {
    var linkAddr = prompt("Please enter the full URL", "http://");
    if (linkAddr && linkAddr != "http://") {
        var linkTitle = prompt("Please enter the title", "");
        if (linkTitle == "") linkTitle = linkAddr;
        if (linkAddr && linkTitle) wrap('url', linkTitle, linkAddr, textID);
    }
}

function spoiler(textID) {
    var spoilertext = prompt("Please enter the spoiler text", "");
    if (spoilertext && spoilertext != "") {
        var linkTitle = prompt("Please enter the title", "");
        if (spoilertext && linkTitle) wrap('spoiler', spoilertext, linkTitle, textID);
    }
}

function image_prompt(textID) {
    var link;
    //var img_regex = /(?:([^:/?#]+):)?(?:\/\/([^\/?#]*))?([^?#]*\.(?:jpg|gif|png|php|asp|html|htm|shtml|jsp|cgi))(?:\?([^#]*))?(?:#(.*))?/i;
    // use a more permissive regex... allows image urls with no extension in them
    var img_regex = /^(https?):\/\/([a-z0-9\-\_]+\.)+([a-z]{1,5}[^\.])(\/[^<>]+)*$/i;
    do {
        link = prompt("Please enter the full URL for your image", "http://");
        if (img_regex.test(link) == false && link != "http://" && link){
            alert("Not a valid image url");
        } else break;
    } while(true)
    if (link != "http://" && link) wrap('img', link, '', textID);
}

function table(textID) {
      //return some bbcode for a table
      var input = prompt("Enter the number of columns and rows for your table\nin the format 'columns, rows'", '2,2');
      var numx = 1;var numy = 1;
      if (input != null && input != ""){
          var splits = input.split(",",2);
          if(splits.length > 0) numx = parseInt(splits[0]);
          if(splits.length > 1) numy = parseInt(splits[1]);
      }
      if (numx<=0)numx=1;if (numy<=0)numy=1;
      var x=0;var y=0;
      var opentag='[table]\n[tr]\n[td] ';
      var closetag='';
      for (y=0;y<numy;y++){
          if(y>0) closetag += ' [/td]\n[/tr]\n[tr]\n[td] ';
          for (x=1;x<numx;x++){
              closetag += ' [/td][td] ';
          }
      }
      closetag += ' [/td]\n[/tr]\n[/table]\n';
      tagwrap(opentag, closetag, textID);
      // insert("[table]\n[tr]\n[td] [/td][td] [/td]\n[/tr]\n[/table]\n", textID) 
}

function tag(v , textID) {
  wrap(v, '', '', textID);
}

function mail(textID) {
  var email = "";
  email = prompt("Plese enter the email addres", " ");
  var filter = /^[\w.-]+@([\w.-]+\.)+[a-z]{2,6}$/i;
  if (!filter.test(email) && email.length > 1) {
    alert("Please provide a valid email address");
    email = prompt("Plese enter the email addres", " ");
  }
  if (email.length > 1) wrap('mail', email, '', textID);
}

function text(to, textID) {
  var obj = document.getElementById(textID);

  if (document.selection) {
        var str = document.selection.createRange().text;
        obj.focus();
        var sel = document.selection.createRange();
        sel.text = (to == 'up' ? str.toUpperCase() : str.toLowerCase())
  } else {
        var len = obj.value.length;
        var start = obj.selectionStart;
        var end = obj.selectionEnd;
        var sel = obj.value.substring(start, end);
        obj.value = obj.value.substring(0, start) + (to == 'up' ? sel.toUpperCase() : sel.toLowerCase()) + obj.value.substring(end, len);
        obj.selectionStart = start;
        obj.selectionEnd = end;
  }
  obj.focus();
}

function fonts(w, textID) {
  var fmin = 12;
  var fmax = 24;
  var obj = document.getElementById(textID);
  var size = obj.style.fontSize;
  size = (parseInt(size));
  var nsize;
  if (w == 'up' && (size + 1 < fmax)) nsize = (size + 1) + "px";
  if (w == 'down' && (size - 1 > fmin)) nsize = (size - 1) + "px";

  obj.style.fontSize = nsize;
  obj.focus();
}

function font(w, f, textID) {
  if (w == 'color' || w == 'bg') f = "#" + f;

  var obj = document.getElementById(textID);

  if (document.selection) {
    var str = document.selection.createRange().text;
    obj.focus();
    var sel = document.selection.createRange();
    sel.text = "[" + w + "=" + f + "]" + str + "[/" + w + "]";
  } else {
    var len = obj.value.length;
    var start = obj.selectionStart;
    var end = obj.selectionEnd;
    var sel = obj.value.substring(start, end);
    var opentag = "[" + w + "=" + f + "]";
    obj.value = obj.value.substring(0, start) + opentag + sel + "[/" + w + "]" + obj.value.substring(end, len);
    obj.selectionStart = start + opentag.length;
    obj.selectionEnd = end + opentag.length;
  }
  if (w != "color" && w != "bg") document.getElementById("font" + w + textID).selectedIndex = 0;
  obj.focus();
}

function insert(f, textID) {
  var obj = document.getElementById(textID);

  if (document.selection) {
    var str = document.selection.createRange().text;
    obj.focus();
    var sel = document.selection.createRange();
    sel.text = f;
  } else {
    var len = obj.value.length;
    var start = obj.selectionStart;
    var end = obj.selectionEnd;
    var sel = obj.value.substring(start, end);
    obj.value = obj.value.substring(0, start) + f + obj.value.substring(end, len);
    obj.selectionStart = start + f.length;
    obj.selectionEnd = start + f.length;
  }
  obj.focus();
}
document.onmousemove = MouseUpdate;
var hX;
var hY;

function chover(obj, act, textID) {
  var color = obj.style.backgroundColor;
  var obj2 = document.getElementById("hover_pick" + textID);

  if (act == "show") {
    obj2.style.left = hX + "px";
    obj2.style.top = hY + "px";
    obj2.style.backgroundColor = color;
    obj2.style.display = "block";
  } else obj2.style.display = "none";

}


function getcolorfor(f, textID) {
    font(currentTag, f, textID) ;
}

var currentTag = ""; // this is gonna be a minor bug when there are 2 bbocde helpers on a page and the user clicks on bg tag on one and text color on the other... but its such an edge case...

function colorpicker(textID, tagname) {
    currentTag = tagname;
    
    var obj2 = document.getElementById("pickerholder" + textID);
    if (obj2.innerHTML=="") {
        var myColors = new Array('000000', '000033', '000066', '000099', '0000CC', '0000FF', '003300', '003333', '003366', '003399', '0033CC', '0033FF', '006600', '006633', '006666', '006699', '0066CC', '0066FF', '009900', '009933', '009966', '009999', '0099CC', '0099FF', '00CC00', '00CC33', '00CC66', '00CC99', '00CCCC', '00CCFF', '00FF00', '00FF33', '00FF66', '00FF99', '00FFCC', '00FFFF', '330000', '330033', '330066', '330099', '3300CC', '3300FF', '333300', '333333', '333366', '333399', '3333CC', '3333FF', '336600', '336633', '336666', '336699', '3366CC', '3366FF', '339900', '339933', '339966', '339999', '3399CC', '3399FF', '33CC00', '33CC33', '33CC66', '33CC99', '33CCCC', '33CCFF', '33FF00', '33FF33', '33FF66', '33FF99', '33FFCC', '33FFFF', '660000', '660033', '660066', '660099', '6600CC', '6600FF', '663300', '663333', '663366', '663399', '6633CC', '6633FF', '666600', '666633', '666666', '666699', '6666CC', '6666FF', '669900', '669933', '669966', '669999', '6699CC', '6699FF', '66CC00', '66CC33', '66CC66', '66CC99', '66CCCC', '66CCFF', '66FF00', '66FF33', '66FF66', '66FF99', '66FFCC', '66FFFF', '990000', '990033', '990066', '990099', '9900CC', '9900FF', '993300', '993333', '993366', '993399', '9933CC', '9933FF', '996600', '996633', '996666', '996699', '9966CC', '9966FF', '999900', '999933', '999966', '999999', '9999CC', '9999FF', '99CC00', '99CC33', '99CC66', '99CC99', '99CCCC', '99CCFF', '99FF00', '99FF33', '99FF66', '99FF99', '99FFCC', '99FFFF', 'CC0000', 'CC0033', 'CC0066', 'CC0099', 'CC00CC', 'CC00FF', 'CC3300', 'CC3333', 'CC3366', 'CC3399', 'CC33CC', 'CC33FF', 'CC6600', 'CC6633', 'CC6666', 'CC6699', 'CC66CC', 'CC66FF', 'CC9900', 'CC9933', 'CC9966', 'CC9999', 'CC99CC', 'CC99FF', 'CCCC00', 'CCCC33', 'CCCC66', 'CCCC99', 'CCCCCC', 'CCCCFF', 'CCFF00', 'CCFF33', 'CCFF66', 'CCFF99', 'CCFFCC', 'CCFFFF', 'FF0000', 'FF0033', 'FF0066', 'FF0099', 'FF00CC', 'FF00FF', 'FF3300', 'FF3333', 'FF3366', 'FF3399', 'FF33CC', 'FF33FF', 'FF6600', 'FF6633', 'FF6666', 'FF6699', 'FF66CC', 'FF66FF', 'FF9900', 'FF9933', 'FF9966', 'FF9999', 'FF99CC', 'FF99FF', 'FFCC00', 'FFCC33', 'FFCC66', 'FFCC99', 'FFCCCC', 'FFCCFF', 'FFFF00', 'FFFF33', 'FFFF66', 'FFFF99', 'FFFFCC', 'FFFFFF');
        var pickerBody = '';

        pickerBody += "<table class=\"color_pick\" id=\"color_pick"+ textID + "\" style=\"border:0px solid black; margin:2px auto 8px; float:right;display:none;\"><tr>";
        for (i = 0; i < myColors.length; i++) {

          if (i % 36 == 0 && i != 0) pickerBody += "<\/tr><tr>";
          pickerBody += "<td onclick=\"getcolorfor('" + myColors[i] + "','"+textID+"');colorpicker('"+textID+"');\" onmouseover=\"chover(this,'show','"+textID+"');\" onmouseout=\"chover(this,'back','"+textID+"');\" style=\"background:#" + myColors[i] + ";\"></td>"

        }
        pickerBody += "<\/tr><\/table>"; 
        obj2.innerHTML = pickerBody;
    }
    var obj = document.getElementById("color_pick"+ textID );

    if (obj.style.display == "block") obj.style.display = "none";
    else {
        obj.style.left = hX + "px";
        obj.style.top = hY + "px";
        obj.style.display = "block";
    }
}

//function to capture the mouse cords
// http://www.howtocreate.co.uk/tutorials/javascript/eventinf


function MouseUpdate(e) {
  var mouse = MouseXY(e);
  hX = 5 + mouse[0];
  hY = 5 + mouse[1];
}

function MouseXY(e) {
  if (!e) {
    if (window.event) {
      //Internet Explorer
      e = window.event;
    } else {
      //total failure, we have no way of referencing the event
      return;
    }
  }
  if (typeof(e.pageX) == 'number') {
    //most browsers
    var xcoord = e.pageX;
    var ycoord = e.pageY;
  } else if (typeof(e.clientX) == 'number') {
    //Internet Explorer and older browsers
    //other browsers provide this, but follow the pageX/Y branch
    var xcoord = e.clientX;
    var ycoord = e.clientY;
    var badOldBrowser = (window.navigator.userAgent.indexOf('Opera') + 1) || (window.ScriptEngine && ScriptEngine().indexOf('InScript') + 1) || (navigator.vendor == 'KDE');
    if (!badOldBrowser) {
      if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
        //IE 4, 5 & 6 (in non-standards compliant mode)
        xcoord += document.body.scrollLeft;
        ycoord += document.body.scrollTop;
      } else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
        //IE 6 (in standards compliant mode)
        xcoord += document.documentElement.scrollLeft;
        ycoord += document.documentElement.scrollTop;
      }
    }
  } else {
    //total failure, we have no way of obtaining the mouse coordinates
    return;
  }
  return [xcoord, ycoord];
}
