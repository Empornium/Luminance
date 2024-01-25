/* bookmark actions */
document.addEventListener('bookmark', updateBookmark);
document.addEventListener('unbookmark', updateBookmark);

function updateBookmark (event) {
	var params = json.decode(event.target.dataset.actionParameters);

	if (params) {
		params = '&' + Object.keys(params).map(function (key) {
			return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
		}).join('&');
	}

	if (event.target.dataset.action == 'bookmark') {
		ajax.get('/bookmarks.php?action=add' + params + '&auth=' + authkey, function() {
			event.target.classList.add('bookmarked');
			event.target.dataset.action = 'unbookmark';
			event.target.title = 'You have this torrent bookmarked';
			event.target.dataset.actionConfirm = '';
		});
	}
	else {
		ajax.get('/bookmarks.php?action=remove&' + params + '&auth=' + authkey, function() {
			event.target.classList.remove('bookmarked');
			event.target.dataset.action = 'bookmark';
			event.target.title = 'Bookmark this torrent';
			delete event.target.dataset.actionConfirm;
		});
	}
}

/* data-action implementation */
var dataActionTimer;

document.addEventListener('click', function (event) {
    if (!event || event.button || !event.target || !event.target.dataset || !event.target.dataset.action) {
        return;
    }

    if (event.target.classList.contains('action_confirm')) {
        dataActionCancel.call(event.target);
        clearTimeout(dataActionTimer);
    }
    else if (event.target.dataset.hasOwnProperty('actionConfirm')) {
        event.target.classList.add('action_confirm');
        dataActionTimer = setTimeout(dataActionCancel.bind(event.target), 3000);
        event.preventDefault();
        return;
    }

    var actionEvent = new Event(event.target.dataset.action, { bubbles: true, cancelable: true });
    event.target.dispatchEvent(actionEvent);

    if (actionEvent.defaultPrevented) {
        event.preventDefault();
    }
});

function dataActionCancel() {
    this.classList.remove('action_confirm');
}
