"use strict";

/* Prototypes */
String.prototype.trim = function () {
  return this.replace(/^\s+|\s+$/g, '');
};

function addCommas(nStr)
{
  nStr += '';
  var x = nStr.split('.');
  var x1 = x[0];
  var x2 = x.length > 1 ? '.' + x[1] : '';
  var rgx = /(\d+)(\d{3})/;
  while (rgx.test(x1)) {
    x1 = x1.replace(rgx, '$1' + ',' + '$2');
  }
  return x1 + x2;
}

var listener = {
  set: function (el,type,callback) {
    if (document.addEventListener) {
      el.addEventListener(type, callback, false);
    } else {
      // IE hack courtesy of http://blog.stchur.com/2006/10/12/fixing-ies-attachevent-failures
      var f = function() {
        callback.call(el);
      };
      el.attachEvent('on'+type, f);
    }
  }
};

var data = {
  set: function(key, value) {
    if (!key || !value) {return;}

    if (typeof value === "object") {
      value = JSON.stringify(value);
    }
    localStorage.setItem(key, value);
  },
  get: function(key) {
    var value = localStorage.getItem(key);

    if (!value) {return;}

    // assume it is an object that has been stringified
    if (value[0] === "{" || value[0] === "[") {
      value = JSON.parse(value);
    }

    return value;
  }
}

/* Site wide functions */

// http://www.thefutureoftheweb.com/blog/adddomloadevent
// retrieved 2010-08-12
// Brilliantly if you go read the blog this is ripped from
// 1) it is not open source
//  2) there is a known IE bug (from before they grabbed it) which is going to fuck us up
var addDOMLoadEvent=(function(){var e=[],t,s,n,i,o,d=document,w=window,r='readyState',c='onreadystatechange',x=function(){n=1;clearInterval(t);while(i=e.shift())i();if(s)s[c]=''};return function(f){if(n)return f();if(!e[0]){d.addEventListener&&d.addEventListener("DOMContentLoaded",x,false);/*@cc_on@*//*@if(@_win32)d.write("<script id=__ie_onload defer src=//0><\/scr"+"ipt>");s=d.getElementById("__ie_onload");s[c]=function(){s[r]=="complete"&&x()};/*@end@*/if(/WebKit/i.test(navigator.userAgent))t=setInterval(function(){/loaded|complete/.test(d[r])&&x()},10);o=w.onload;w.onload=function(){x();o&&o()}}e.push(f)}})();



// from https://jsfiddle.net/fx6a6n6x/  2/2017
// Copies a string to the clipboard. Must be called from within an
// event handler such as click. May return false if it failed, but
// this is not always possible. Browser support for Chrome 43+,
// Firefox 42+, Safari 10+, Edge and IE 10+.
// IE: The clipboard feature may be disabled by an administrator. By
// default a prompt is shown the first time the clipboard is
// used (per session).
function copyToClipboard(text) {
    if (window.clipboardData && window.clipboardData.setData) {
        // IE specific code path to prevent textarea being shown while dialog is visible.
        return clipboardData.setData("Text", text);

    } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
        var textarea = document.createElement("textarea");
        textarea.textContent = text;
        textarea.style.position = "fixed";  // Prevent scrolling to bottom of page in MS Edge.
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand("copy");  // Security exception may be thrown by some browsers.
        } catch (ex) {
            console.warn("Copy to clipboard failed.", ex);
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }
}


var getTextWidth = function(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    //var font = getTextWidth.font || (getTextWidth.font = 'normal 11pt "Lucida Grande", Helvetica, Arial, Verdana, sans-serif');
    //'    font: normal 11pt "New Courier", Courier, monospace'
        //console.log('getTextWidth.font: '+ font.toString());
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}



//PHP ports
function isset(variable) {
  return (typeof(variable) === 'undefined') ? false : true;
}

function is_array(input) {
  return typeof(input) === 'object' && input instanceof Array;
}

function function_exists(function_name) {
  return (typeof this.window[function_name] === 'function');
}

function html_entity_encode (str) {
    return (str+'').replace(/./gm, function(s) {
        return "&#" + s.charCodeAt(0) + ";";
    });
};

function html_entity_decode(str) {
    var el = document.createElement("textarea");
    el.innerHTML = str;
    for (var i = 0, ret = ''; i < el.childNodes.length; i++) {
      ret += el.childNodes[i].nodeValue;
    }
    return ret;
}


function get_size(size) {
    return get_size_fixed(size, 2)
}
function get_size_fixed(size,places){
  var steps = 0;
  while(size>=1024) {
    steps++;
    size=size/1024;
  }
  var ext;
  switch(steps) {
    case 0:ext = ' B';
        break;
    case 1:ext = ' KiB';
        break;
    case 2:ext = ' MiB';
        break;
    case 3:ext = ' GiB';
        break;
    case 4:ext = ' TiB';
        break;
    case 5:ext = ' PiB';
        break;
    case 6:ext = ' EiB';
        break;
    case 7:ext = ' ZiB';
        break;
    case 8:ext = ' YiB';
        break;
    default:"0.00 MB";
  }
    if (steps>=4) places++;
  return (size.toFixed(places) + ext);
}

function get_ratio_color(ratio) {
  if (ratio < 0.1) {return 'r00';}
  if (ratio < 0.2) {return 'r01';}
  if (ratio < 0.3) {return 'r02';}
  if (ratio < 0.4) {return 'r03';}
  if (ratio < 0.5) {return 'r04';}
  if (ratio < 0.6) {return 'r05';}
  if (ratio < 0.7) {return 'r06';}
  if (ratio < 0.8) {return 'r07';}
  if (ratio < 0.9) {return 'r08';}
  if (ratio < 1) {return 'r09';}
  if (ratio < 2) {return 'r10';}
  if (ratio < 5) {return 'r20';}
  return 'r50';
}

function ratio(dividend, divisor, color) {
  if(!color) {
    color = true;
  }
  if(divisor == 0 && dividend == 0) {
    return '--';
  } else if(divisor == 0) {
    return '<span class="r99">∞</span>';
  } else if(dividend == 0 && divisor > 0) {
    return '<span class="r00">-∞</span>';
  }
  var rat = ((dividend/divisor)-0.005).toFixed(2); //Subtract .005 to floor to 2 decimals
  if(color) {
    var col = get_ratio_color(rat);
    if(col) {
      rat = '<span class="'+col+'">'+rat+'</span>';
    }
  }
  return rat;
}


function save_message(message) {
  var messageDiv = document.createElement("div");
  messageDiv.className = "save_message";
  messageDiv.innerHTML = message;
  //$("#content").raw().insertBefore(messageDiv,$("#messages").raw());
    $("#messages").raw().parentNode.insertBefore(messageDiv,$("#messages").raw());
  //$("#content").raw().insertBefore(messageDiv,$("#content").raw().firstChild);
}

function error_message(message) {
  var messageDiv = document.createElement("div");
  messageDiv.className = "error_message";
  messageDiv.innerHTML = html_entity_encode(message); //
  $("#content").raw().insertBefore(messageDiv,$("#content").raw().firstChild);
}

//returns key if true, and false if false better than the php funciton
function in_array(needle, haystack, strict) {
  if (strict === undefined) {
    strict = false;
  }
  for (var key in haystack) {
    if ((haystack[key] == needle && strict === false) || haystack[key] === needle) {
      return true;
    }
  }
  return false;
}

function array_search(needle, haystack, strict) {
  if (strict === undefined) {
    strict = false;
  }
  for (var key in haystack) {
    if ((strict === false && haystack[key] == needle) || haystack[key] === needle) {
      return key;
    }
  }
  return false;
}

var util = function (selector, context) {
  return new util.fn.init(selector, context);
}


util.fn = util.prototype = {
  objects: new Array(),
  init: function (selector, context) {
    if(typeof(selector) == 'object') {
      this.objects[0] = selector;
    } else {
      this.objects = Sizzle(selector, context);
    }
    return this;
  },
  results: function () {
    return this.objects.length;
  },
  show: function () {
    return this.remove_class('hidden');
  },
  hide: function (force) {
    return this.add_class('hidden', force);
  },
  toggle: function (force) {
    //Should we interate and invert all entries, or just go by the first?
    if (!in_array('hidden', this.objects[0].className.split(' '))) {
      this.add_class('hidden', force);
    } else {
      this.remove_class('hidden');
    }
    return this;
  },
  listen: function (event, callback) {
    for (var i=0,il=this.objects.length;i<il;i++) {
      var object = this.objects[i];
      if (document.addEventListener) {
        object.addEventListener(event, callback, false);
      } else {
        object.attachEvent('on' + event, callback);
      }
    }
    return this;
  },
  remove: function () {
    for (var i=0,il=this.objects.length;i<il;i++) {
      var object = this.objects[i];
      object.parentNode.removeChild(object);
    }
    return this;
  },
  add_class: function (class_name, force) {
    for (var i=0,il=this.objects.length;i<il;i++) {
      var object = this.objects[i];
      if (object.className === '') {
        object.className = class_name;
      } else if (force || !in_array(class_name, object.className.split(' '))) {
        object.className = object.className + ' ' + class_name;
      }
    }
    return this;
  },
  remove_class: function (class_name) {
    for (var i=0,il=this.objects.length;i<il;i++) {
      var object = this.objects[i];
      var classes = object.className.split(' ');
      var result = array_search(class_name, classes);
      if (result !== false) {
        classes.splice(result,1);
        object.className = classes.join(' ');
      }
    }
    return this;
  },
  has_class: function(class_name) {
    for (var i=0,il=this.objects.length;i<il;i++) {
      var object = this.objects[i];
      var classes = object.className.split(' ');
      if(array_search(class_name, classes)) {
        return true;
      }
    }
    return false;
  },
  disable : function (set_value) {
    if (set_value === undefined) {
      set_value = true;
    }
    for (var i=0,il=this.objects.length;i<il;i++) {
      this.objects[i].disabled = set_value;
    }
    return this;
  },
  html : function (html) {
    for (var i=0,il=this.objects.length;i<il;i++) {
      this.objects[i].innerHTML = html;
    }
    return this;
  },
  raw: function (number) {
    if (number === undefined) {
      number = 0;
    }
    return this.objects[number];
  },
  nextElementSibling: function () {
    var here = this.objects[0];
    if (here.nextElementSibling) {
      return $(here.nextElementSibling);
    }
    do {
      here = here.nextSibling;
    } while (here.nodeType != 1);
    return $(here);
  },
  previousElementSibling: function () {
    var here = this.objects[0];
    if (here.previousElementSibling) {
      return $(here.previousElementSibling);
    }
    do {
      here = here.nextSibling;
    } while (here.nodeType != 1);
    return $(here);
  }
}

util.fn.init.prototype = util.fn;
var $ = util;

new SVGInjector().inject(document.querySelectorAll('svg[data-src]'));

document.addEventListener('DOMContentLoaded', function() {
    if(window.jQuery) {
        jQuery.noConflict();
        console.log('jQuery running in compatibility mode');
    }
    $ = util;
    var event = document.createEvent('Event');
    event.initEvent('LuminanceLoaded', true, true);
    this.dispatchEvent(event);
}, false);
