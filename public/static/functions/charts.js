/*
 *Note: unlike other js files this should not be renamed... privateheader specifically tests for this filename
 * to include a ref to the google js charts api in the head
 */
var data;
var chart;
var options; 
var maxrows;

//============================================================================================

function Load_Sitestats(){
    google.load("visualization", "1", {packages:["corechart"]});
    google.setOnLoadCallback(Start_Sitestats);
}

function Start_Sitestats(){
    data = new google.visualization.DataTable(chartdata);
    chart = new google.visualization.LineChart(document.getElementById('chart_div'));
    var startat = endrow-180;
    if(startat<startrow) startat=startrow;
    options = {
            title:'start',
            height:700,
            chartArea:{left:80,top:20,width:"82%",height:630},
            vAxes:[{gridlines:{color: '#bbb', count: -1}}],
            series:[{color: 'blue', visibleInLegend: true},
                    {color: 'orange', visibleInLegend: true}, 
                    {color: 'green', visibleInLegend: true}, 
                    {color: 'red', visibleInLegend: true}],
            tooltip:{ showColorCode: true},
            animation:{
                duration: 2000,
                easing: 'linear'
            }, 
            hAxis:{viewWindow: {min:startat, max:endrow},slantedText:false,maxTextLines:1,maxAlternation:1}
        }; 
    maxrows = data.getNumberOfRows() -1;
    drawChart();
}

//============================================================================================
function Load_Userstats(){
    google.load("visualization", "1", {packages:["corechart"]});
    google.setOnLoadCallback(Start_Userstats);
}

function Start_Userstats(){
    data = new google.visualization.DataTable(chartdata);
    chart = new google.visualization.SteppedAreaChart(document.getElementById('chart_div'));
    var startat = endrow-60;
    if(startat<startrow) startat=startrow;
    options = {
            title:'start',
            height:600,
            chartArea:{left:60,top:80,width:"90%",height:500},
            vAxes:[{gridlines:{color: '#bbb', count: -1}}],
            series:[{color: 'blue', visibleInLegend: true}, 
                    {color: 'red', visibleInLegend: true}],
            tooltip:{ showColorCode: true},
            legend:{position: 'top',alignment:'center'},
            animation:{
                duration: 2000,
                easing: 'linear'
            }, 
            hAxis:{viewWindow: {min:startat, max:endrow},slantedText:false,maxTextLines:1,maxAlternation:1}
        }; 
    maxrows = data.getNumberOfRows() -1;
    drawChart();
}

//============================================================================================


function Load_Torrentstats(){
    google.load("visualization", "1", {packages:["corechart"]});
    google.setOnLoadCallback(Start_Torrentstats);
}


function Start_Torrentstats(){
    data = new google.visualization.DataTable(chartdata);
    chart = new google.visualization.SteppedAreaChart(document.getElementById('chart_div'));
    var startat = endrow-28;
    if(startat<startrow) startat=startrow;
    options = {
            title:'start',
            height:280,
            chartArea:{left:80,top:20,width:"82%",height:230},
            vAxes:[{gridlines:{color: '#bbb', count: -1}},{baseline: 0}],
            series:[{color: 'blue', visibleInLegend: false}],
            tooltip:{ showColorCode: true},
            animation:{
                duration: 2000,
                easing: 'linear'
            }, 
            isStacked: true, 
            hAxis:{viewWindow: {min:startat, max:endrow},slantedText:false,maxTextLines:1,maxAlternation:1}
        }; 
    maxrows = data.getNumberOfRows() -1;
    drawChart();
}
 

//============================================================================================



function drawChart() {
    options.title = data.getValue(options.hAxis.viewWindow.min, 0) + ' to ' + data.getValue(options.hAxis.viewWindow.max, 0);
    chart.draw(data, options);
}
 
function zoomout(){
    options.animation.duration = 1000;
    var range = options.hAxis.viewWindow.max-options.hAxis.viewWindow.min+1;
    //if(range>maxrows) range = maxrows;
    range = parseInt(range/2); 
    var shift = range;
    if (options.hAxis.viewWindow.min - range < 0) shift = options.hAxis.viewWindow.min;
    options.hAxis.viewWindow.min -= shift;
    if (options.hAxis.viewWindow.max + range > maxrows) shift = maxrows - options.hAxis.viewWindow.max;
    else shift = range;
    options.hAxis.viewWindow.max += shift; 
    drawChart();
    /*      
    options.animation.duration = 1000;
    var mrange = parseInt(maxrows * 0.15);
    var shift = mrange;
    if (options.hAxis.viewWindow.min - mrange < 0) shift = options.hAxis.viewWindow.min;
    options.hAxis.viewWindow.min -= shift;
    if (options.hAxis.viewWindow.max + mrange > maxrows) shift = maxrows - options.hAxis.viewWindow.max;
    else shift = mrange;
    options.hAxis.viewWindow.max += shift; 
    drawChart(); */
}   
function zoomin(){
    options.animation.duration = 1000;
    var range = options.hAxis.viewWindow.max-options.hAxis.viewWindow.min+1; 
    if (range<14) {
        range = range - 7;
        if (range<0) { return; }
    }  
    range = parseInt(range/4); 
    //alert(range);
    options.hAxis.viewWindow.min += range; 
    options.hAxis.viewWindow.max -= range;
    drawChart();
    
    /*
    var mrange = parseInt(maxrows * 0.15);
    if ((mrange*2)+7 > (options.hAxis.viewWindow.max-options.hAxis.viewWindow.min)) {
        mrange = parseInt((options.hAxis.viewWindow.max-options.hAxis.viewWindow.min-7) * 0.5  );
        if (mrange<0) return;
    }  
    options.hAxis.viewWindow.min += mrange; 
    options.hAxis.viewWindow.max -= mrange;
    drawChart(); */
}

function prev(jump,durationMS) {
    options.animation.duration = durationMS;
    var range = parseInt( (options.hAxis.viewWindow.max  - options.hAxis.viewWindow.min+1) * jump);
    if (options.hAxis.viewWindow.min - range < 0) range = options.hAxis.viewWindow.min;
    options.hAxis.viewWindow.min -= range;
    options.hAxis.viewWindow.max -= range;
    drawChart();
}

function next(jump,durationMS) {
    options.animation.duration = durationMS;
    var range = parseInt( (options.hAxis.viewWindow.max  - options.hAxis.viewWindow.min+1) * jump);
    if (options.hAxis.viewWindow.max + range > maxrows) range = maxrows - options.hAxis.viewWindow.max;
    options.hAxis.viewWindow.min += range;
    options.hAxis.viewWindow.max += range;
    drawChart();
}

function gstart(durationMS){
    options.animation.duration = durationMS;
    var shift = options.hAxis.viewWindow.min;
    options.hAxis.viewWindow.min -= shift;
    options.hAxis.viewWindow.max -= shift;
    drawChart();
}

function gend(durationMS){
    options.animation.duration = durationMS;
    var shift = maxrows - options.hAxis.viewWindow.max;
    options.hAxis.viewWindow.min += shift;
    options.hAxis.viewWindow.max += shift;
    drawChart();
}




