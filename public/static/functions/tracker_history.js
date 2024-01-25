// Convert string with common suffix into bytes numerical(float)
function getBytes(bytes_text) {
  bytes_text = bytes_text.replace(/,/g,'');
  var bytes_scale = 1;

  if(~bytes_text.indexOf('B'))   bytes_scale = 1;
  if(~bytes_text.indexOf('KB'))  bytes_scale = 1024;
  if(~bytes_text.indexOf('KiB')) bytes_scale = 1024;
  if(~bytes_text.indexOf('MB'))  bytes_scale = 1024*1024;
  if(~bytes_text.indexOf('MiB')) bytes_scale = 1024*1024;
  if(~bytes_text.indexOf('GB'))  bytes_scale = 1024*1024*1024;
  if(~bytes_text.indexOf('GiB')) bytes_scale = 1024*1024*1024;
  if(~bytes_text.indexOf('TB'))  bytes_scale = 1024*1024*1024*1024;
  if(~bytes_text.indexOf('TiB')) bytes_scale = 1024*1024*1024*1024;

  return (parseFloat(bytes_text)*bytes_scale);
}

function makePane() {
  // Generate array of links to table cells
  var THistory     = document.getElementById("historydiv");
  var THistories   = document.getElementsByClassName("box pad seedhistory scrollbox");

  // Create new document elements to be inserted.
  var THGraph      = document.createElement("div");
  var LinBreak     = document.createElement("br");
  var THGSVG       = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  var THGULline    = document.createElementNS("http://www.w3.org/2000/svg", "polyline");
  var THGDLline    = document.createElementNS("http://www.w3.org/2000/svg", "polyline");
  var THVAxis      = document.createElementNS("http://www.w3.org/2000/svg", "line");
  var THHAxis      = document.createElementNS("http://www.w3.org/2000/svg", "line");

  // Insert the THGraph in the middle of the
  // Tracker History pane.
  THistory.insertBefore(THGraph, THistories[1]);

  // Style the new entries.
  THGraph.setAttribute("class",    "box pad");
  THGraph.appendChild(THGSVG);
  THGSVG.setAttribute("height", THGraph.offsetHeight);
  THGSVG.setAttribute("width",  THGraph.offsetWidth-20);

  // Insert the lines and axis

  THGSVG.appendChild(THGDLline);
  THGSVG.appendChild(THGULline);
  THGSVG.appendChild(THVAxis  );
  THGSVG.appendChild(THHAxis  );

  // Scrape the stats from tracker history pane
  var TrackerStatsDates = THistories[1].innerHTML.match(/[\d]+-[\d]+-[\d]+/g);
  var TrackerUpStats    = THistories[1].innerHTML.match(/up: (.*?)(?= \|)/g);
  var TrackerDownStats  = THistories[1].innerHTML.match(/down: (.*?)(?= \|)/g);

  // If less than 5 entries do not graph
  if(THistories[1].getElementsByTagName("br").length < 5)
  {
    THGraph.setAttribute("class",    "hidden");
  }

  // Variables for intermediate stats values
  var TrackerRatioStats = [];
  var TrackerUpTotal    = 0.0;
  var TrackerDownTotal  = 0.0;

  // Graph scaling stuff
  var GpadLeft = 1;
  var GWidth   = THGraph.offsetWidth-35;
  var GHeight  = THGraph.offsetHeight-35;
  var HScale   = GWidth/(TrackerUpStats.length-1);
  var ULMax    = 1;
  var DLMax    = 1;

  // Variables for points on the graph
  var ULpoints = "";
  var DLpoints = "";
  var Month = "";

  // Clean the scraped stats and get Max up and down for scaling
  for (i=0; i<TrackerUpStats.length;i++)
  {
    var newMonth = TrackerStatsDates[i].match(/-[\d]+-/)[0];
    if (Month != newMonth)
    {
      Month = newMonth;
      var THHAxisGrid = document.createElementNS("http://www.w3.org/2000/svg", "line");
      THGSVG.appendChild(THHAxisGrid);
      THHAxisGrid.setAttributeNS(null, "x1", (GpadLeft+(GWidth-(i*HScale))));
      THHAxisGrid.setAttributeNS(null, "x2", (GpadLeft+(GWidth-(i*HScale))));
      THHAxisGrid.setAttributeNS(null, "y1", 0);
      THHAxisGrid.setAttributeNS(null, "y2", GHeight+10);
      THHAxisGrid.setAttributeNS(null, "fill",   "none");
      THHAxisGrid.setAttributeNS(null, "stroke", "grey");
      THHAxisGrid.setAttributeNS(null, "stroke-opacity", "0.4");

      var THHAxisScale = document.createElementNS("http://www.w3.org/2000/svg", "text");
      THGSVG.appendChild(THHAxisScale);
      THHAxisScale.setAttributeNS(null, "x", (GpadLeft+(GWidth-(i*HScale))));
      THHAxisScale.setAttributeNS(null, "y", GHeight+10);
      THHAxisScale.setAttributeNS(null, "font-size","8");
      THHAxisScale.textContent = TrackerStatsDates[i];
    }
    TrackerUpStats[i]=TrackerUpStats[i].replace("up: ","");
    TrackerUpStats[i] = getBytes(TrackerUpStats[i]);
    TrackerDownStats[i]=TrackerDownStats[i].replace("down: ","");
    TrackerDownStats[i] = getBytes(TrackerDownStats[i]);

    if(TrackerUpStats[i] > ULMax) ULMax = TrackerUpStats[i];
    if(TrackerDownStats[i] > DLMax) DLMax = TrackerDownStats[i];
  }

  // Calculate the scale factors
  var VMax = Math.max(ULMax, DLMax);
  var VScale = GHeight/VMax;
  for (i=0; i<4;i++)
  {
    var THVAxisGrid = document.createElementNS("http://www.w3.org/2000/svg", "line");
    var Vpos = (GHeight-((GHeight/4)*(i+1)));
    THGSVG.appendChild(THVAxisGrid);
    THVAxisGrid.setAttributeNS(null, "x1", GpadLeft);
    THVAxisGrid.setAttributeNS(null, "x2", GWidth);
    THVAxisGrid.setAttributeNS(null, "y1", Vpos);
    THVAxisGrid.setAttributeNS(null, "y2", Vpos);
    THVAxisGrid.setAttributeNS(null, "fill",   "none");
    THVAxisGrid.setAttributeNS(null, "stroke", "grey");
    THVAxisGrid.setAttributeNS(null, "stroke-opacity", "0.4");

    var THVAxisScale = document.createElementNS("http://www.w3.org/2000/svg", "text");
    THGSVG.appendChild(THVAxisScale);
    THVAxisScale.setAttributeNS(null, "x", GpadLeft+2);
    THVAxisScale.setAttributeNS(null, "y", Vpos+8);
    THVAxisScale.setAttributeNS(null, "font-size","8");
    THVAxisScale.textContent = get_size((VMax/4)*(i+1));
  }

  // Calculate the total and build the graph points strings.
  // Also calculate ratio per day, if NaN is reported then log it.
  for (i=0; i<TrackerUpStats.length;i++)
  {
    TrackerUpTotal   += TrackerUpStats[i];
    TrackerDownTotal += TrackerDownStats[i];
    ULpoints = ULpoints+" "+(GpadLeft+(GWidth-(i*HScale)))+","+(GHeight-(TrackerUpStats[i]*VScale));
    DLpoints = DLpoints+" "+(GpadLeft+(GWidth-(i*HScale)))+","+(GHeight-(TrackerDownStats[i]*VScale));
    TrackerRatioStats.push((TrackerUpStats[i]/TrackerDownStats[i]).toFixed(2).replace("Infinity", "&infin;"));
    if (TrackerRatioStats[i] == "NaN")
    {
      console.log("NaN | up: "+TrackerUpStats[i]+" down: "+TrackerDownStats[i])
      TrackerRatioStats[i] = 0.0;
    }
  }

  // Add totals to the text at the top of the pane
  THistories[0].innerHTML = THistories[0].innerHTML.replace("<br>"," | up: "+get_size(TrackerUpTotal)+ " | down: "+get_size(TrackerDownTotal)+"<br>");


  // Append ratios to the end of daily logs.
  var THLog = THistories[1].getElementsByTagName("br");
  for (i=0; i<THLog.length; i++)
  {
    var THRatio = document.createElement("span");
    THRatio.setAttribute("style", "color:grey");
    THRatio.innerHTML=" | up/down: "+TrackerRatioStats[i];
    THistories[1].insertBefore(THRatio, THLog[i]);
  }

  // Get the alignment positions of each element
  THistory = THistories[1].innerHTML.split('\n');
  var alignments = [0, 0, 0, 0, 0];
  for (i=0; i<THistory.length; i++)
  {
    var this_line=THistory[i].split('|');
    for (j=0; j<this_line.length; j++)
    {
        alignments[j]=Math.max(this_line[j].length, alignments[j]);
    }
  }

  // Align the elements
  for (i=0; i<THistory.length; i++)
  {
    var this_line=THistory[i].split('|');
    for (j=0; j<this_line.length-1; j++)
    {
        this_line[j]=Array((alignments[j] - this_line[j].length) + 1).join('\xA0').concat(this_line[j]);
        if(~this_line[j].indexOf("up:")) {
            this_line[j] = this_line[j].replace("up:", '').concat("up ");
        }
        if(~this_line[j].indexOf("down:")) {
            this_line[j] = this_line[j].replace("down:", '').concat("down ");
        }
    }
    THistory[i] = this_line.join('|').replace("credits", '\xA2');
  }
  THistories[1].innerHTML = THistory.join('\n');

  // Style and display the graph lines
  // Upload plot
  THGULline.setAttributeNS(null, "points", ULpoints);
  THGULline.setAttributeNS(null, "fill",   "none");
  THGULline.setAttributeNS(null, "stroke", "green");

  // Download plot
  THGDLline.setAttributeNS(null, "points", DLpoints);
  THGDLline.setAttributeNS(null, "fill",   "none");
  THGDLline.setAttributeNS(null, "stroke", "blue");
  THGDLline.setAttributeNS(null, "stroke-opacity", "0.8");

  // Verticle Axis
  THVAxis.setAttributeNS(null, "x1", GpadLeft);
  THVAxis.setAttributeNS(null, "x2", GpadLeft);
  THVAxis.setAttributeNS(null, "y1", 0);
  THVAxis.setAttributeNS(null, "y2", GHeight);
  THVAxis.setAttributeNS(null, "fill",   "none");
  THVAxis.setAttributeNS(null, "stroke", "black");

  // Horizontal Axis
  THHAxis.setAttributeNS(null, "x1", GpadLeft);
  THHAxis.setAttributeNS(null, "x2", GWidth);
  THHAxis.setAttributeNS(null, "y1", GHeight);
  THHAxis.setAttributeNS(null, "y2", GHeight);
  THHAxis.setAttributeNS(null, "fill",   "none");
  THHAxis.setAttributeNS(null, "stroke", "black");

  // Graph controls
  var ULcheckbox     = document.createElement('input');
  ULcheckbox.type    = "checkbox";
  ULcheckbox.name    = "Plot Uloaded";
  ULcheckbox.checked = true;
  ULcheckbox.id      = "ULCB";

  var ULCBlabel = document.createElement('label')
  ULCBlabel.htmlFor = "ULCBL";
  ULCBlabel.appendChild(document.createTextNode('Plot Uloaded  '));

  ULcheckbox.onclick = (function() {
    if(this.checked)
    {
      THGULline.setAttributeNS(null, "stroke-opacity", "1.0");
    }
    else
    {
      THGULline.setAttributeNS(null, "stroke-opacity", "0.3");
    }
  });

  THGraph.appendChild(ULcheckbox);
  THGraph.appendChild(ULCBlabel);

  var DLcheckbox     = document.createElement('input');
  DLcheckbox.type    = "checkbox";
  DLcheckbox.name    = "Plot Downloaded";
  DLcheckbox.checked = true;
  DLcheckbox.id      = "DLCB";

  var DLCBlabel = document.createElement('label')
  DLCBlabel.htmlFor = "DLCBL";
  DLCBlabel.appendChild(document.createTextNode('Plot Downloaded'));

  DLcheckbox.onclick = (function() {
    if(this.checked)
    {
      THGDLline.setAttributeNS(null, "stroke-opacity", "0.8");
    }
    else
    {
      THGDLline.setAttributeNS(null, "stroke-opacity", "0.2");
    }
  });

  THGraph.appendChild(DLcheckbox);
  THGraph.appendChild(DLCBlabel);


}

addDOMLoadEvent(makePane);
