/*
Spent hours debugging opera, turns out they reserve the global variable autocomplete. Bitches.
*/
/*
 *   Elements needed to make this work:
 *   'section' (eg. tags) where /section/index.php has a switch for action=autocomplete&name=searchvalue (to fetch results)
 *   'cacheprefix' - a string to prefix to js cache results
 *   input text should have keydown and keyup events attached to autocomp.keydown(event) etc
 *   a ul element for results, pass id's for these elements in startup call
 *   a function submitted() {} event which is called when the user presses enter with nothing selected
 *   and an initialising event (DOMLoad is good) where autocomp.start(section,cacheprefix,inputid,listid) is called
EXAMPLE:

addDOMLoadEvent(startAutoComp);

function startAutoComp() {
    autocomp.start('section','cacheprefix','inputid', 'listid');
}

function submitted() {
    // do something when the enter key is pressed with nothing selected in the dropdown
    $('#search_form').submit();
}

 * html example:
                        <div class="autoresults">
                            <input type="text" id="inputid"
                                        onkeyup="return autocomp.keyup(event);"
                                        onkeydown="return autocomp.keydown(event);"
                                        autocomplete="off"
                                        title="enter text to search for tags, click (or enter) to select a tag from the drop-down" />
                            <ul id="listid"></ul>
                        </div>
 */
"use strict";
var autocomp = {
    id_prefix: "",
    section: "",
    wordvalue: "",
    wordindex: -1,
    left: 0,
    top: 0,
    tag: null,
    timer: null,
    input: null,
    list: null,
    pos: -1,
    ignore: ['and','or','not','+','|','-','&','!','(',')'],
    cache : [],
    start : function(section, id_prefix, inputid, listid)
    {
        this.section = section;
        this.id_prefix = id_prefix;
        this.cache[id_prefix] = ["", [], [], [] ];
        this.input = document.getElementById(inputid);
        this.list = document.getElementById(listid);
        listener.set(document.body, 'click', function() {
            autocomp.end();
        });
    },
    end : function()
    {
        this.tag = null;
        this.highlight('none');
        this.list.style.visibility = 'hidden';
        clearTimeout(this.timer);
    },
    setwordvalue: function()
    {
        var caretPos = this.input.selectionStart;

        if (!this.input.value || this.input.value.trim()=='') {
            this.wordvalue = '';
        } else {
            var words = this.input.value.split(' ');
            if (words && is_array(words) && words.length>0) {
                var count=0;
                // find the word under the current caret
                for (var i = 0; i < words.length; i++) {
                    count += words[i].length;
                    if (count >= caretPos) {
                        this.wordvalue = words[i].trim();
                        this.wordindex = i;
                        break;
                    }
                    // add count for spaces between words
                    count++;
                }
            }
            if (in_array(this.wordvalue.toLowerCase(), this.ignore)) {
                this.wordvalue ='';
            }
        }
        if (this.wordvalue == '') {
            this.wordindex = -1;
            this.end();
        } else {
            var coords = getCaretCoordinates(this.input, caretPos);
            this.left = coords.left;
            this.top = coords.top + 18;
        }
    },
    clicked : function(tag)
    {
        if (tag === null || tag == '' || this.wordindex < 0) return;
        var prefix = '';
        var startPosition = this.input.selectionStart;
        var caretPos = 0;
        var inputtext = this.input.value;
        var words = inputtext.split(" ");
        if (words && is_array(words) && words.length > 0) {
            // wordindex was stored at lookup
            if (this.wordindex >= words.length) return;
            var word = words[this.wordindex];
            // if lastword has an operator as the first character grab it
            var result = /^[!&\|\-\+]/i.exec(word);
            if (result && result.index === 0) {
                prefix = result[0];
            }
            // replace searched on word with selected word & rebuild input string
            words[this.wordindex] = prefix + tag;
            // add space if we appended at the end of wordlist
            if (this.wordindex == words.length - 1) {
                words[this.wordindex] += " ";
            }
            
            inputtext = words.join(" ");
            // to find the caret position of the end of the inserted word we loop through and add whole word lengths
            var count = 0;
            for (var i = 0; i < words.length; i++) {
                count += words[i].length;
                if (count >= startPosition) {
                    caretPos = count;
                    break;
                }
                // add count for spaces between words
                count++;
            }
        }
        this.input.value = inputtext;
        this.input.focus();
        this.input.selectionStart = caretPos;
        this.input.selectionEnd = caretPos;
    },
    keyup : function(e)
    {
        clearTimeout(this.timer);
        this.setwordvalue();
        var key = (window.event) ? window.event.keyCode : e.keyCode;
        switch (key) {
            case 27: //esc
            case 9:  //tab
                this.end();
                break;
            case 8:  //backspace
                this.list.style.visibility = 'hidden';
                if (this.wordvalue.length > 0) {
                    this.timer = setTimeout(function() { autocomp.get((autocomp.wordvalue)); }, 300);
                }
                break;
            case 38: //up
                this.highlight('up');
                break;
            case 40: //down
                this.highlight('down');
                break;
            case 13: //enter
                if (this.tag != null) {
                    this.clicked(this.tag);
                } else if (typeof submitted == 'function') {
                    // if there is an external submitted() function then call it
                    submitted();
                }
                this.end();
                break;
            default:
                if (this.wordvalue.length > 0) {
                    this.timer = setTimeout(function() { autocomp.get((autocomp.wordvalue)); }, 200);
                }
                return true;
        }
        return false;
    },
    keydown : function(e)
    {
        switch ((window.event) ? window.event.keyCode : e.keyCode) {
            case 27: //esc
            case 9:  //tab
                this.end();
                return false;
                break;
            case 38: //up
            case 40: //down
                e.preventDefault();
                return 1;
                break;
            case 13: //enter
                return false;
        }
        return 1;
    },
    highlight : function(change)
    {
        //No highlights on no list
        if (this.list.children.length === 0) return;
        this.list.style.visibility = 'visible';

        //Remove the previous highlight
        if (this.pos >= 0 && this.pos < this.list.children.length) {
            this.list.children[this.pos].className = "";
        }

        //Change position
        if (change == 'down') ++this.pos;
        else if (change == 'up') --this.pos;
        else if (change == 'none') this.pos = -20;
        else this.pos = parseInt(change);

        if (this.pos !== -20) {
            // wrap around
            if (this.pos >= this.list.children.length) {
                this.pos = 0;
            } else if (this.pos < 0) {
                this.pos = this.list.children.length - 1;
            }
            this.tag = this.list.children[this.pos].tag;
            this.list.children[this.pos].className = "highlight";
        } else {
            this.tag = null;
        }
    },
    get : function(value)
    {
        this.tag = null;
        this.pos = -1;

        if (typeof this.cache[this.id_prefix + value] === 'object') {
            this.display(this.cache[this.id_prefix + value]);
            return;
        }

        ajax.get(this.section + '.php?action=autocomplete&name=' + value, function(jstr) {
            var data = json.decode(jstr);
            autocomp.cache[autocomp.id_prefix + data[0]] = data[1];
            autocomp.display(data[1]);
        });
    },
    display : function(data)
    {
        var i, il, li;
        this.list.innerHTML = '';
        il = data.length;
        for (i = 0; i < il; i++) {
            li = document.createElement('li');
            li.tag = data[i][0];
            li.innerHTML = data[i][1];
            li.i = i;
            listener.set(li, 'mouseover', function() {
                autocomp.highlight(this.i);
            });
            listener.set(li, 'click', function() {
                autocomp.clicked(this.tag);
            });
            this.list.appendChild(li);
        }
        if (i > 0) {
            this.list.style.left = this.left + 'px';
            this.list.style.top =  this.top + 'px';
            this.list.style.visibility = 'visible';
        } else {
            this.list.style.visibility = 'hidden';
        }
    }
};
