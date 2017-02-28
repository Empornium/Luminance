
        
function SetUsername(itemID){
    var name= prompt("Enter the username of the person you wish to give a gift to");
    if (name!=null && name!="") {
        $('#' + itemID).raw().value = name;
        return true;
    }
    return false;
}

function SetTitle(itemID){
    var name= prompt("Enter the custom title you want to have\n(max 32 chars)");
    if (name!=null && name!="") {
        $('#' + itemID).raw().value = name;
        return true;
    }
    return false;
}
 
function SetTorrent(itemID){
    var id= prompt("Universal Freeleech Slot\nEnter the ID of a torrent that you uploaded to make permanently freeleech\nie. for torrents.php?id=3333&torrentid=4444 enter the id number '3333'");
    if (id!=null && id!="" ) {
        $('#' + itemID).raw().value = id;
        return true;
    }
    return false;
}

