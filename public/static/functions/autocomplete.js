/*
    Usage:
        AutoComplete.addInput(element, section)
            Starts monitoring the specified element and provides suggestions based on input.
            section: remote end to get suggestions, such as /tags.php
            element: the element to monitor, can be either an input or textarea
        AutoComplete.stop([element])
            Stops monitoring the specified element. If no element is provided, then stops monitoring all elements.
        AutoComplete.resume([element])
            Resumes monitoring the specified or all elements.

    Example:

    document.body.addEventListener('DOMContentLoaded', function () {
        AutoComplete.addInput(document.getElementById('searchbox_tags'), '/tags.php');
    });
*/
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // enable autocomplete for the following elements
    var tag_elements = [ 'searchbox_tags', 'taginput' ];

    for (var i = 0; i < tag_elements.length; i++) {
        var element = document.getElementById(tag_elements[i]);

        if (element) {
            AutoComplete.addInput(element, '/tags.php');
        }
    }

    var autocompleteToggle = document.getElementById('autocomplete_toggle');

    // turn off in case the option is disabled and there's no toggle
    if (!autocompleteToggle) {
        if (localStorage.getItem('tag_autocomplete_toggle') == 'off') {
            AutoComplete.stop();
        }
        return;
    }

    // in order to enable browser suggestions we need to replace textarea with input
    var textarea = document.getElementById('taginput');
    var input = document.createElement('input');
    input.id = textarea.id;
    input.name = textarea.name;
    input.title = textarea.title;
    input.autocomplete = 'on';
    input.setAttribute('style', 'font: 10pt monospace;');
    input.classList.add('inputtext', 'medium');

    function swapInputs (newInput, oldInput) {
        newInput.value = oldInput.value;
        oldInput.parentElement.replaceChild(newInput, oldInput);
    }

    // toggle listener
    autocompleteToggle.addEventListener('change', function (event) {
        if (this.checked) {
            AutoComplete.resume();
            swapInputs(textarea, input);
        }
        else {
            AutoComplete.stop();
            swapInputs(input, textarea);
        }
        localStorage.setItem('tag_autocomplete_toggle', this.checked ? 'on' : 'off');
    });

    // turn off in case the option is disabled
    if (localStorage.getItem('tag_autocomplete_toggle') == 'off') {
        if (autocompleteToggle.checked) {
            autocompleteToggle.click(); // simulate change event to turn off
        }
        else {
            swapInputs(input, textarea); // in case the page was refreshed and the toggle is unchecked
        }
    }
});

var AutoComplete = {
    // properties
    inputs:    [],
    sections:  [],
    listeners: [],
    cache:     {},

    // methods
    addInput: function (element, section) {
        // check if element has already been added
        if (this.inputs.indexOf(element) != -1) return;

        // generate and store listener so it's possible to remove
        var listener = this.keydown.bind(this);

        this.inputs.push(element);
        this.sections.push(section);
        this.listeners.push(listener);

        // start listening
        element.setAttribute('autocomplete', 'off'); // disable browser autocomplete
        element.addEventListener('keydown', listener);

        // hide tooltip when the input element loses focus; see note below
        element.addEventListener('blur', this.tooltip.hideIfNotFocused.bind(this.tooltip));

        // init tooltip
        this.tooltip.init();

        // only do once
        if (this.inputs.length == 1) {
            // as the blur event comes before click, we have to monitor when the tooltip is active
            // otherwise, the tooltip is hidden before the user is able to click on a suggestion
            this.tooltip.wrapper.addEventListener('mouseenter', this.tooltip.onFocus.bind(this.tooltip));
            this.tooltip.wrapper.addEventListener('mouseleave', this.tooltip.onBlur.bind(this.tooltip));
        }
    },
    stop: function (element) {
        if (element) {
            var i = this.inputs.indexOf(element);

            if (i != -1) {
                this.inputs[i].setAttribute('autocomplete', 'on');
                this.inputs[i].removeEventListener('keydown', this.listeners[i]);
            }
        }
        else {
            for (var i = 0; i < this.inputs.length; i++) {
                this.inputs[i].setAttribute('autocomplete', 'on');
                this.inputs[i].removeEventListener('keydown', this.listeners[i]);
            }
        }
    },
    resume: function (element) {
        if (element) {
            var i = this.inputs.indexOf(element);

            if (i != -1) {
                this.inputs[i].setAttribute('autocomplete', 'off');
                this.inputs[i].addEventListener('keydown', this.listeners[i]);
            }
        }
        else {
            for (var i = 0; i < this.inputs.length; i++) {
                this.inputs[i].setAttribute('autocomplete', 'off');
                this.inputs[i].addEventListener('keydown', this.listeners[i]);
            }
        }
    },
    keydown: function (event) {
        clearTimeout(this.timer);
        this.input = event.target;

        if (event.shiftKey || event.ctrlKey || event.altKey) return;

        switch (event.keyCode) {
            case 27: // esc
                if (this.tooltip.visible) {
                    this.tooltip.hide();
                    event.preventDefault();
                }
                break;
            case 38: // up
                if (this.tooltip.visible) {
                    this.tooltip.selectPrevious();
                    event.preventDefault();
                }
                break;
            case 40: // down
                if (this.tooltip.visible) {
                    this.tooltip.selectNext();
                    event.preventDefault();
                }
                break;
            case 9:  // tab
                if (this.tooltip.visible) {
                    this.replace();
                    event.preventDefault();
                }
                break;
            case 13: // enter
                if (this.tooltip.visible) {
                    this.replace();
                    event.preventDefault();
                }
                else if (this.input.nodeName == 'TEXTAREA' && this.input.form) {
                    this.input.form.submit();
                    event.preventDefault();
                }
                break;
            case 37: // left
            case 39: // right
                break;
            default:
                this.timer = setTimeout(this.getSuggestions.bind(this), 300);
        }
    },
    getSuggestions: function () {
        // ignore special chars
        var word = this.getCurrentWord().replace(/[^a-zA-Z0-9.]/g, '');

        if (!word.length) return this.tooltip.hide();

        var i = this.inputs.indexOf(this.input);
        var url = this.sections[i] + '?action=autocomplete&name=' + word;

        if (this.cache.hasOwnProperty(url)) {
            this.tooltip.display(this.generateList(this.cache[url]), this.tooltipCoords, url);
            this.tooltip.select(0);
        }
        else {
            ajax.get(url, function(response) {
                // data = [query, [ [suggestion1, html], ... ] ];
                var data = json.decode(response);
                this.cache[url] = data[1];
                this.tooltip.display(this.generateList(data[1]), this.tooltipCoords, url);
                this.tooltip.select(0);
            }.bind(this));
        }
    },
    getCurrentWord: function () {
        this.wordStart = this.wordEnd = this.input.selectionStart;

        // set index after previous space or start
        do {
            this.wordStart--;
        } while(this.wordStart > -1 && this.input.value.charAt(this.wordStart) != ' ');
        this.wordStart++;

        // if the first char is a boolean operator, skip it.
        // Doing it with a switch to maximize browser compat,
        // a bit hacky looking but it works nice.
        switch(this.input.value.charAt(this.wordStart)) {
            case '-':
            case '!':
            case '|':
            case '&':
            case '(':
            case ')':
                this.wordStart++;
        }

        // set index before next space or end
        while (this.input.value.charAt(this.wordEnd) != ' ' && this.wordEnd < this.input.value.length) {
            this.wordEnd++;
        }

        // set tooltip coords
        var rect = this.input.getBoundingClientRect(); // IE uses rect.left & rect.top instead of rect.x & rect.y
        this.tooltipCoords = {
            x: (rect.left || rect.x) + pageXOffset + getCaretCoordinates(this.input, this.wordStart).left,
            y: (rect.top || rect.y) + pageYOffset + getCaretCoordinates(this.input, this.wordStart).top + 20
        };

        return this.input.value.substring(this.wordStart, this.wordEnd);
    },
    generateList: function (data) {
        var list = [ ];
        var i;

        var click = function (event) {
        }.bind(this);

        for (i = 0; i < data.length; i++) {
            var li = document.createElement('li');
            li.dataset.tag = data[i][0];
            li.dataset.index = i;
            li.insertAdjacentHTML('afterbegin', data[i][1]);
            li.addEventListener('mouseenter', this.suggestionHover.bind(this));
            li.addEventListener('click', this.suggestionClick.bind(this));
            list.push(li);
        }

        return list;
    },
    replace: function () {
        var suggestion = this.tooltip.getSelection().dataset.tag;
        this.input.value = (
            // before word
            this.input.value.substring(0, this.wordStart) +

            // new word
            suggestion +

            // inject space
            ' ' +

            // after word
            this.input.value.substring(this.wordEnd)
        );

        // position caret after the suggestion, as changing the value puts the caret at the end
        this.input.setSelectionRange(this.wordStart + suggestion.length + 1, this.wordStart + suggestion.length + 1);
        this.tooltip.hide();
    },

    //events
    suggestionHover: function (event) {
        this.tooltip.select(event.target.dataset.index);
    },
    suggestionClick: function (event) {
        this.replace();
        this.input.focus();

        event.preventDefault();
        event.stopPropagation();
    },
};

AutoComplete.tooltip = {
    // properties
    isFocused: false,

    // methods
    init: function () {
        if (this.wrapper) return;

        this.wrapper = document.createElement('ul');
        this.wrapper.id = 'autoresults';
        this.wrapper.style.position = 'absolute';
        this.hide();

        document.body.appendChild(this.wrapper);
    },
    display: function (list, coords, id) {
        // check list length
        if (!list.length) return this.hide();

        // same list as last
        if (this.id == id) {
            if (this.coords != coords) {
                this.coords = coords;
                this.position();
            }
            return;
        }

        this.id     = id;
        this.coords = coords;
        this.list   = list;

        // clear previous entries
        while (this.wrapper.firstChild) {
            this.wrapper.removeChild(this.wrapper.firstChild);
        }

        // append list
        var i;
        for (i = 0; i < list.length; i++) {
            this.wrapper.appendChild(list[i]);
        }

        // position and display
        this.position();
    },
    hide: function () {
        this.visible = 0;
        this.wrapper.style.display = 'none';
    },
    position: function () {
        this.wrapper.style.top     = this.coords.y + 'px';
        this.wrapper.style.left    = this.coords.x + 'px';
        this.wrapper.style.display = 'inline-block';
        this.visible = 1;
    },
    select: function (index) {
        if (!this.list || this.list.length == 0) return;
        if (this.selected == this.list[index]) return;

        // wrap around
        if (index > this.list.length - 1) {
            index = 0;
        }
        if (index < 0) {
            index = this.list.length - 1;
        }

        if (this.selected) {
            this.selected.classList.remove('highlight');
        }

        this.selected = this.list[index];
        this.selected.classList.add('highlight');
        this.selectedIndex = index;
    },
    selectNext: function () {
        if (this.visible)
            this.select(this.selectedIndex + 1);
    },
    selectPrevious: function () {
        if (this.visible)
            this.select(this.selectedIndex - 1);
    },
    getSelection: function () {
        if (this.selectedIndex == undefined)
            return null;
        return this.list[this.selectedIndex];
    },

    // events
    onFocus: function (event) {
        this.isFocused = true;
    },
    onBlur: function (event) {
        this.isFocused = false;
    },
    hideIfNotFocused: function (event) {
        if (!this.isFocused) {
            this.hide();
        }
    },
};
