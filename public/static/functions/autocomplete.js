
/*
Spent hours debugging opera, turns out they reserve the global variable autocomplete. Bitches.
*/
/*
 *   Elements needed to make this work:
 *   id= 'section' (ie. torrents) where /section/index.php has a switch for action=autocomplete&name=searchvalue (to fetch results)
 *   input text id="id+search" with keydown and keyup events attached to autocomp.keydown(event) etc
 *   a ul element with id="id+complete" for results
 *   a function clicked(value) {} event which is called when the user selects a suggested value  
 *   and an initialising event (DOMLoad is good) where autocomp.start(id) is called
EXAMPLE:

addDOMLoadEvent(Start_AutoComp);

function Start_AutoComp() {
    autocomp.start('section');   
}

function clicked(value) { 
    // do something with value selected by user 
}

 * html example:
                        <div class="autoresults">
                            <input type="text" id="torrentssearch" 
                                        onkeyup="return autocomp.keyup(event);" 
                                        onkeydown="return autocomp.keydown(event);"
                                        autocomplete="off"
                                        title="enter text to search for tags, click (or enter) to select a tag from the drop-down" />
                            <ul id="torrentscomplete"></ul>
                        </div>
 */
"use strict";
var autocomp = {
	id_prefix: "",
	section: "",
	value: "",
	href: null,
	tag: null,
	timer: null,
	input: null,
	list: null,
	pos: -1,
	cache: [],
	start: function (section, id_prefix) {
		this.section = section;
		this.id_prefix = id_prefix;
		this.cache[id_prefix] = ["",[],[],[]];
		this.input = document.getElementById(id_prefix + "search");
		this.list = document.getElementById(id_prefix + "complete");
		listener.set(document.body,'click',function(){
			autocomp.value = autocomp.input.value;
			autocomp.end();
		});
	},
	end: function () {
		//this.input.value = this.value;
		this.tag = null;
		this.highlight(-1);
		this.list.style.visibility = 'hidden';
		clearTimeout(this.timer);
	},
	keyup: function (e) {
		clearTimeout(this.timer);
		var key = (window.event)?window.event.keyCode:e.keyCode;
		switch (key) {
			case 27: //esc
				break;
			case 8: //backspace
				this.tag = null;
				this.list.style.visibility = 'hidden';
                if( this.input.value.length>0 )
                    this.timer = setTimeout("autocomp.get('" + escape(this.input.value) + "');",500); 
				break;
			case 38: //up
				this.highlight('up');
				if(this.pos !== -1) {
					this.tag = this.list.children[this.pos].tag;
					this.input.value = this.tag;    // this.list.children[this.pos].textContent || this.list.children[this.pos].value;
				}
				break;
			case 40: //down
				this.highlight('down');
				if(this.pos !== -1) {
					this.tag = this.list.children[this.pos].tag;
					this.input.value = this.tag;    // this.list.children[this.pos].textContent || this.list.children[this.pos].value;
				}
				break;
			case 13:
				if(this.tag != null) {
                    clicked(this.tag);
				}
				return false;
			default:
				this.tag = null;
                if( this.input.value.length>0 )
                    this.timer = setTimeout("autocomp.get('" + escape(this.input.value) + "');",300);
				return true;
		}
		return false;
	},
	keydown: function (e) {
		switch ((window.event)?window.event.keyCode:e.keyCode) {
			case 9: //tab
				this.value = this.input.value;
                return 1;
				break;
			case 27: //esc
				this.end();
                return 1;
				break;
			case 38:
				e.preventDefault();
                return 1;
				break;
			case 13: //enter
				return false;
		}
		return 1;
    },
	highlight: function(change) {
		//No highlights on no list
		if (this.list.children.length === 0) {
			return;
		}

		//Show me the
		this.list.style.visibility = 'visible';

		//Remove the previous highlight
		if (this.pos !== -1) {
			this.list.children[this.pos].className = "";
		}

		//Change position
        if( change == 'down') ++this.pos;
		else if (change == 'up') --this.pos;
		else this.pos = parseInt(change);
        /*
		if (change === 40) ++this.pos;
		else if (change === 38) --this.pos;
		else this.pos = change; */
		 

		//Wrap arounds
		if (this.pos >= this.list.children.length) {
			this.pos = -1;
		} else if (this.pos < -1) {
			this.pos = this.list.children.length-1;
		}

		if (this.pos !== -1) {
			this.list.children[this.pos].className = "highlight";
		} else {
			this.tag = null;
			this.input.value = this.value;
		}
	},
	get: function (value) {
		this.pos = -1;
		this.value = unescape(value);

		if (typeof this.cache[this.id_prefix+value] === 'object') {
            //this.cache[this.id+value].unshift(new Array('cache','cache!!')); // test caching
			this.display(this.cache[this.id_prefix+value]);
			return;
		}

		ajax.get(this.section+'.php?action=autocomplete&name='+this.input.value,function(jstr){
			var data = json.decode(jstr);
			autocomp.cache[autocomp.id_prefix+data[0]] = data[1];
			autocomp.display(data[1]);
		});
	},
	display: function (data) {
		var i,il,li;
		this.list.innerHTML = '';
		for (i=0,il=data.length;i<il;++i) {
			li = document.createElement('li');
            li.tag =  data[i][0];
			li.innerHTML = data[i][1];  // + "&nbsp;&nbsp;";
			li.i = i;
			listener.set(li,'mouseover',function(){
				autocomp.highlight(this.i);
			});
			listener.set(li,'click',function(){
                clicked(this.tag);
			});
			this.list.appendChild(li);
		}
		if (i > 0) {
			this.list.style.visibility = 'visible';
		} else {
			this.list.style.visibility = 'hidden';
		}
	}
};
