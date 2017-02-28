
function Preview_Toggle() {
	var preview_div = '#preview';
	if ($(preview_div).has_class('hidden')) {
		ajax.post('ajax.php?action=preview_edit_torrent', 'edit_torrent', function (response) {
			$(preview_div).raw().innerHTML = response;
			$(preview_div).toggle();
			$('#editor').toggle();
                  $(preview_div + '_button').raw().value = "Make changes";
		});
	} else {
		$(preview_div).raw().innerHTML = '';
            $(preview_div + '_button').raw().value = "Preview";
		$(preview_div).toggle();
		$('#editor').toggle();
	}
}
