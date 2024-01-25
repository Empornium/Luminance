function displayFlashes(messages) {
    // Flash close control
    var close = document.createElement('span');
    close.id = 'flashClose';
    close.classList.add('close');
    close.textContent = 'Dismiss';
    close.onclick = (function(){$(forcediv).remove();});

    // Flash controls
    var controls = document.createElement('div');
    controls.classList.add('flashControls');
    controls.appendChild(close);


    // Flashes div
    var flashes = document.createElement('div');
    flashes.id = 'flashes';
    flashes.classList.add('flashes');
    flashes.style.marginBottom = "20px"
    flashes.appendChild(controls);

    // Force div
    var force = document.createElement('div');
    force.id = 'forcediv';
    force.appendChild(flashes);

    document.getElementById('content').appendChild(force);

    messages.forEach(function(message) {
        // Error content
        var content = document.createElement('pre');
        content.innerHTML = message.message;

        // Error div
        var error = document.createElement('div');
        error.id = 'messagebarA;'
        error.classList.add('flash');
        error.classList.add(message.severity);
        error.appendChild(content);
        flashes.appendChild(error);
    });
}

document.addEventListener('LuminanceLoaded', function() {
  var event = document.createEvent('Event');
  event.initEvent('ShowFlashes', true, true);
  this.dispatchEvent(event);
});

document.addEventListener('ShowFlashes', function() {
    var flashes = cookie.get('flashes');
    cookie.del('flashes');
    if (flashes) {
        displayFlashes(JSON.parse(unescape(flashes)));
    }
});
