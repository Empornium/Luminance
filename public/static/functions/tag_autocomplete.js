// we could have different versions of this for the 3 different pages its used on but a bit of switching on location does the job...

addDOMLoadEvent(function () {
    autocomp.start('tags','torrents','taginput', 'tagdropdown');
    // if on torrents browse or upload but not on torrent details
    if (location.href.match(/torrents\.php(?!\?(id|groupid|torrentid)=)|upload\.php/i) ) {
        var off = data.get('tag_autocomplete_toggle')=='off';
        //console.log("on: "+on);
        if (off) {
            jQuery('#autocomplete_toggle').prop("checked", false);
            ToggleAutoComplete();
        }
    }
});

function ToggleAutoComplete()
{
    var on = jQuery('#autocomplete_toggle').prop("checked");
    var content = html_entity_encode(jQuery('#taginput').val());
    jQuery('#taginput').remove();
    //console.log("content: " + content + "    on: " + on);
    if (on) {
        jQuery('#autoresults').after('<textarea id="taginput" name="taglist" class="inputtext medium" style="font: 10pt monospace;" onkeyup="return autocomp.keyup(event);" onkeydown="return autocomp.keydown(event);" autocomplete="off" title="Search Tags, supports full boolean search" >'+content+'</textarea>');
        autocomp.start('tags','torrents','taginput', 'tagdropdown');
    } else {
        jQuery('#autoresults').after('<input id="taginput" name="taglist" class="inputtext medium" style="font: 10pt monospace;" autocomplete="on" title="Search Tags, supports full boolean search" value="'+content+'" />');
    }
    data.set('tag_autocomplete_toggle', on?'on':'off');
}

function submitted()
{
    if (location.href.match(/torrents\.php\?(id|groupid|torrentid)=/)) {
        // if on torrent details page then add the tag
        addTag();
        return false;
    } else if (location.href.match(/torrents\.php/)) {
        // if on torrents browse page submit search
        jQuery('#search_form').submit();
        return false;
    }
}
