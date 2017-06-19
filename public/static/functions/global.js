
// set all checkboxes in formElem by val of checkbox passed
function toggleChecks(formElem,masterElem) {
	if (masterElem.checked) { checked=true; } else { checked=false; }
	for(s=0; s<$('#'+formElem).raw().elements.length; s++) {
		if ($('#'+formElem).raw().elements[s].type=="checkbox") {
			$('#'+formElem).raw().elements[s].checked=checked;
		}
	}
}

// return true if any checkboxes in the passed form are checked
function anyChecks(formElem) {
	for(s=0; s<$('#'+formElem).raw().elements.length; s++) {
		if ($('#'+formElem).raw().elements[s].type=="checkbox") {
			if ($('#'+formElem).raw().elements[s].checked==true) return true;
		}
	}
    alert("No messages are selected");
    return false;
}

//Lightbox stuff
var lightbox = {
	init: function (image, size) {
		if (image.naturalWidth === undefined) {
			var tmp = document.createElement('img');
			tmp.style.visibility = 'hidden';
			tmp.src = image.src;
			image.naturalWidth = tmp.width;
			delete tmp;
		}
		if (image.naturalWidth > size) {
			lightbox.box(image);
		}
	},
	box: function (image) {
		if(image.parentNode.tagName.toUpperCase() != 'A') {
			$('#lightbox').show().listen('click',lightbox.unbox).raw().innerHTML = '<img src="' + image.src + '" />';
			$('#curtain').show().listen('click',lightbox.unbox);
		}
	},
	unbox: function (data) {
		$('#curtain').hide();
		$('#lightbox').hide().raw().innerHTML = '';
	}
};

/* Still some issues
function caps_check(e) {
	if (e === undefined) {
		e = window.event;
	}
	if (e.which === undefined) {
		e.which = e.keyCode;
	}
	if (e.which > 47 && e.which < 58) {
		return;
	}
	if ((e.which > 64 && e.which <  91 && !e.shiftKey) || (e.which > 96 && e.which < 123 && e.shiftKey)) {
		$('#capslock').show();
	}
}
*/

function hexify(str) {
   str = str.replace(/rgb\(|\)/g, "").split(",");
   str[0] = parseInt(str[0], 10).toString(16).toLowerCase();
   str[1] = parseInt(str[1], 10).toString(16).toLowerCase();
   str[2] = parseInt(str[2], 10).toString(16).toLowerCase();
   str[0] = (str[0].length == 1) ? '0' + str[0] : str[0];
   str[1] = (str[1].length == 1) ? '0' + str[1] : str[1];
   str[2] = (str[2].length == 1) ? '0' + str[2] : str[2];
   return (str.join(""));
}

function resize(id) {
	var textarea = document.getElementById(id);
	if (textarea.scrollHeight > textarea.clientHeight) {
		//textarea.style.overflowY = 'hidden';
		textarea.style.height = Math.min(1000, textarea.scrollHeight + textarea.style.fontSize) + 'px';
	}
}

//ZIP downloader stuff
function add_selection() {
	var selected = $('#formats').raw().options[$('#formats').raw().selectedIndex];
	if (selected.disabled === false) {
		var listitem = document.createElement("li");
		listitem.id = 'list' + selected.value;
		listitem.innerHTML = '						<input type="hidden" name="list[]" value="'+selected.value+'" /> ' +
'						<span style="float:left;">'+selected.innerHTML+'</span>' +
'						<a href="#" onclick="remove_selection(\''+selected.value+'\');return false;" style="float:right;">[X]</a>' +
'						<br style="clear:all;" />';
		$('#list').raw().appendChild(listitem);
		$('#opt' + selected.value).raw().disabled = true;
	}
}

function remove_selection(index) {
	$('#list' + index).remove();
	$('#opt' + index).raw().disabled='';
}

function Stats(stat) {
	ajax.get("ajax.php?action=stats&stat=" + stat);
}



/*
// from https://github.com/component/textarea-caret-position
The MIT License (MIT)

Copyright (c) 2015 Jonathan Ong me@jongleberry.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
(function() {

    // The properties that we copy into a mirrored div.
    // Note that some browsers, such as Firefox,
    // do not concatenate properties, i.e. padding-top, bottom etc. -> padding,
    // so we have to do every single property specifically.
    var properties = [
        'direction', // RTL support
        'boxSizing',
        'width', // on Chrome and IE, exclude the scrollbar, so the mirror div wraps exactly as the textarea does
        'height',
        'overflowX',
        'overflowY', // copy the scrollbar for IE

        'borderTopWidth',
        'borderRightWidth',
        'borderBottomWidth',
        'borderLeftWidth',
        'borderStyle',

        'paddingTop',
        'paddingRight',
        'paddingBottom',
        'paddingLeft',

        // https://developer.mozilla.org/en-US/docs/Web/CSS/font
        'fontStyle',
        'fontVariant',
        'fontWeight',
        'fontStretch',
        'fontSize',
        'fontSizeAdjust',
        'lineHeight',
        'fontFamily',

        'textAlign',
        'textTransform',
        'textIndent',
        'textDecoration', // might not make a difference, but better be safe

        'letterSpacing',
        'wordSpacing',

        'tabSize',
        'MozTabSize'

    ];

    var isBrowser = (typeof window !== 'undefined');
    var isFirefox = (isBrowser && window.mozInnerScreenX != null);

    function getCaretCoordinates(element, position, options) {
        if (!isBrowser) {
            throw new Error('textarea-caret-position#getCaretCoordinates should only be called in a browser');
        }

        var debug = options && options.debug || false;
        if (debug) {
            var el = document.querySelector('#input-textarea-caret-position-mirror-div');
            if (el) {
                el.parentNode.removeChild(el);
            }
        }

        // mirrored div
        var div = document.createElement('div');
        div.id = 'input-textarea-caret-position-mirror-div';
        document.body.appendChild(div);

        var style = div.style;
        var computed = window.getComputedStyle ? getComputedStyle(element) : element.currentStyle; // currentStyle for IE < 9

        // default textarea styles
        style.whiteSpace = 'pre-wrap';
        if (element.nodeName !== 'INPUT') style.wordWrap = 'break-word'; // only for textarea-s

        // position off-screen
        style.position = 'absolute'; // required to return coordinates properly
        if (!debug) style.visibility = 'hidden'; // not 'display: none' because we want rendering

        // transfer the element's properties to the div
        properties.forEach(function(prop) {
            style[prop] = computed[prop];
        });

        if (isFirefox) {
            // Firefox lies about the overflow property for textareas: https://bugzilla.mozilla.org/show_bug.cgi?id=984275
            if (element.scrollHeight > parseInt(computed.height)) style.overflowY = 'scroll';
        }
        else {
            style.overflow = 'hidden'; // for Chrome to not render a scrollbar; IE keeps overflowY = 'scroll'
        }

        div.textContent = element.value.substring(0, position);
        // the second special handling for input type="text" vs textarea: spaces need to be replaced with non-breaking spaces - http://stackoverflow.com/a/13402035/1269037
        if (element.nodeName === 'INPUT') div.textContent = div.textContent.replace(/\s/g, '\u00a0');

        var span = document.createElement('span');
        // Wrapping must be replicated *exactly*, including when a long word gets
        // onto the next line, with whitespace at the end of the line before (#7).
        // The  *only* reliable way to do that is to copy the *entire* rest of the
        // textarea's content into the <span> created at the caret position.
        // for inputs, just '.' would be enough, but why bother?
        span.textContent = element.value.substring(position) || '.'; // || because a completely empty faux span doesn't render at all
        div.appendChild(span);

        var coordinates = {
            top: span.offsetTop + parseInt(computed['borderTopWidth']),
            left: span.offsetLeft + parseInt(computed['borderLeftWidth'])
        };

        if (debug) {
            span.style.backgroundColor = '#aaa';
        } else {
            document.body.removeChild(div);
        }

        return coordinates;
    }

    if (typeof module != 'undefined' && typeof module.exports != 'undefined') {
        module.exports = getCaretCoordinates;
    } else if (isBrowser) {
        window.getCaretCoordinates = getCaretCoordinates;
    }

}());
