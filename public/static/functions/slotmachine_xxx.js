
count=0;
won=0;
bet=0;
winningreels= new Array(3);
animateMS=0;
var reel = new Array(4);
var stopped = new Array(4);
var offset = new Array(4);
    for(var i=0;i<4;i++) {
        offset[i]=0;
    }
arrayNum=0;
t=null;

function Set_Interface(){ // make sure the interface synchs with the bet data if user reloads page
    var num = parseInt($('#numbets').raw().value);
    Set_NumBets_Interface(num);
    var betamount = parseInt($('#betamount').raw().value);
    Set_Bet_Interface(betamount);
}
addDOMLoadEvent(Set_Interface);

function Change_NumBets(){
    var num = parseInt($('#numbets').raw().value);
    num++;  // increment (change) value
    if(num>3)num=1;  // rollover
    Set_NumBets_Interface(num);
}
function Set_NumBets_Interface(num){
    if (!in_array(num, new Array(1,2,3))) num = 1;
    $('#numbets').raw().value = num;
    Toggle_Play_Row('a',num>2);
    Toggle_Play_Row('c',num>1);
}
function Toggle_Play_Row(reels_row, is_playing){
    if(is_playing){
        $('#reels'+reels_row).add_class('play');
        $('#bet'+reels_row).show();
    } else {
        $('#reels'+reels_row).remove_class('play');
        $('#bet'+reels_row).hide();
    }
}
function Change_Bet(){
    var num = parseInt($('#betamount').raw().value); 
    num *= 10;
    if (num>100)num=1;
    Set_Bet_Interface(num);
}
function Set_Bet_Interface(num){
    if (!in_array(num, new Array(1,10,100))) num = 1;
    $('#betamount').raw().value = num;
    ajax.get("?action=ajax_slot_paytable&bet="+num, function (response) {
        $('#payout_table').html(response);
    });
    $('#betanum').html(num);
    $('#betbnum').html(num);
    $('#betcnum').html(num);
}
function PlaySound(wav){
    if ($('#forcesound').raw().checked)
        $('#sound').raw().innerHTML = '<embed src="static/common/casino/' + wav + '"  style="visibility:hidden;" autostart="true" loop="false" />';
    else
        $('#sound').raw().innerHTML = '<embed src="static/common/casino/' + wav + '" hidden="true" autostart="true" loop="false" />';
}
   

function Pull_Lever(){
    if (count>0) return; // make them wait!
    var num_bets = parseInt($('#numbets').raw().value);
    if (!in_array(num_bets, new Array(1,2,3))) num_bets = 1;
    var bet_amount = parseInt($('#betamount').raw().value);
    if (!in_array(bet_amount, new Array(1,10,100))) bet_amount = 1;
    bet = num_bets * bet_amount;
    if ( parseInt($('#winnings').raw().innerHTML.replace(/,/gi, '') ) < bet ) {
        alert('you do not have enough credits to bet ' + bet + ' credits');
        bet=0;
        return;
    }
    winningreels= new Array(3);
    count = 90; 
    animateMS=10;
    arrayNum=0;
    won=0;
    for(var i=0;i<4;i++) {
        reel[i]=-1;
        stopped[i]=0;
        $('#reela'+i).remove_class('win');
        $('#reelb'+i).remove_class('win');
        $('#reelc'+i).remove_class('win');
    }
    $('#lever').raw().setAttribute("src", 'static/common/casino/leverDown.png');
    if ($('#playsound').raw().checked) PlaySound("wheelspin.wav");
    
    
	var ToPost = [];
	ToPost['auth'] = authkey;
	ToPost['bet'] = bet_amount;
	ToPost['numbets'] = num_bets;
 
    ajax.post("?action=slot_result", ToPost, function(response){  // "form" + postid
	//ajax.get("?action=slot_result&bet="+bet_amount+"&numbets="+num_bets, function (response) {
        var x = json.decode(response); 
        setTimeout("leverup();", 800);
        if ( is_array(x)){
            animate();
            for(var i=0;i<4;i++) {
                reel[i]= x[i]; // store the end positions
            }
            //$('#res0').raw().innerHTML = x[6];  
            won = x[4]; // total won
            winningreels=x[5]; // which reels and rows to highlight in interface
        } else {    // error from ajax
            for(var j=0;j<4;j++) {
                EndReel(j,((arrayNum+offset[j])%20)); 
            }
            count=0;
            alert(x);
        }
    });
}

function leverup(){
    $('#lever').raw().setAttribute("src", 'static/common/casino/leverUp.png');
}

function animate(){
    
    if (stopped[0]==0 && (count > 80 || ((arrayNum+offset[0])%20) != reel[0])) RollReel(0);
    else EndReel(0,reel[0]);
    if (stopped[1]==0 && (count > 60 || stopped[0]==0 || ((arrayNum+offset[1])%20) != reel[1])) RollReel(1);
    else EndReel(1,reel[1]);
    if (stopped[2]==0 && (count > 40 || stopped[1]==0 || ((arrayNum+offset[2])%20) != reel[2])) RollReel(2);
    else EndReel(2,reel[2]);
    if (stopped[3]==0 && (count > 20 || stopped[2]==0 || ((arrayNum+offset[3])%20) != reel[3])) RollReel(3);
    else {
        clearTimeout(t);
        if (stopped[3]==0) {
            stopped[3]=1;
            for(var i=0;i<4;i++) {
                EndReel(i,reel[i]); 
                if (winningreels[0]>=(i+1)) $('#reelb'+i).add_class('win');
                if (winningreels[1]>=(i+1)) $('#reelc'+i).add_class('win');
                if (winningreels[2]>=(i+1)) $('#reela'+i).add_class('win');
            }
            $('#winnings').raw().innerHTML = addCommas( parseInt( $('#winnings').raw().innerHTML.replace(/,/gi, '') )+won-bet);
            $('#result').raw().innerHTML = won>0?'*Win* ' +won:'';
            count=0;
        }
    }
    if (stopped[3]==0){
        arrayNum++;
        if (arrayNum > 20) arrayNum = 0;
        count--;
        if(animateMS<100) animateMS = animateMS + 2;
        else if(animateMS<150) animateMS = animateMS + 1;
        t = setTimeout("animate();", animateMS);
    }
}

function RollReel(x){	 
    $('#reela'+ x).raw().setAttribute("src", 'static/common/casino/' + reelPix[x][(arrayNum+offset[x]+2)%20]+ '.png');
    $('#reelb'+ x).raw().setAttribute("src", 'static/common/casino/' + reelPix[x][(arrayNum+offset[x]+1)%20]+ '.png');
    $('#reelc'+ x).raw().setAttribute("src", 'static/common/casino/' + reelPix[x][(arrayNum+offset[x])%20]+ '.png');
}
function EndReel(x,pos){		 
    stopped[x]=1;
    offset[x]=pos;
    $('#reela'+ x).raw().setAttribute("src", 'static/common/casino/' + reelPix[x][(pos+2)%20] + '.png');
    $('#reelb'+ x).raw().setAttribute("src", 'static/common/casino/' + reelPix[x][(pos+1)%20] + '.png');
    $('#reelc'+ x).raw().setAttribute("src", 'static/common/casino/' + reelPix[x][pos] + '.png');
}


