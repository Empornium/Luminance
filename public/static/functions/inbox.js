function Inbox_Preview(appendid, isReport) {
    // Inbox_Preview is called from different forms with different DOM ids
  var data_form = (typeof isReport !== 'undefined') ? 'report_form' : 'messageform';

  if (appendid == undefined) {
      appendid = '';
  }

  if ($('#preview'+appendid).has_class('hidden')) {
    ajax.post('/ajax.php?action=preview_newpm', data_form+appendid, function (response) {
                  $('#preview'+appendid).raw().innerHTML = response;
                  $('#preview'+appendid).show();
      $('#quickpost'+appendid).hide();
      $('#previewbtn'+appendid).raw().value = "Edit Message";
      Prism.highlightAll();
    });
  } else {
    $('#preview'+appendid).hide();
    $('#quickpost'+appendid).toggle();
    $('#previewbtn'+appendid).raw().value = "Preview";
  }
}
