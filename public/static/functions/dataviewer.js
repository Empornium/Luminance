function changeQuery(element)
{
    var selectedindex = element.selectedIndex;
    if (selectedindex<1) {
        jQuery('#querydesc').hide();
    } else {
        jQuery('#querydesc').show();
    }
    jQuery('#querydesc > td:last-child').html(jQuery(element.options[selectedindex]).attr('desc'));
}
