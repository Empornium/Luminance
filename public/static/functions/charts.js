function drawChart(chartDiv, data = []) {
    config = data['config'];
    if (typeof config === 'undefined') {
        config = {};
    }

    layout = data['layout'];
    if (typeof layout === 'undefined') {
        layout = {};
    }

    data = data['data'];

    config.displaylogo = false;
    config.modeBarButtonsToRemove = ['toImage', 'hoverClosestPie'];
    config.responsive = true;
    layout.paper_bgcolor = 'rgba(0,0,0,0)';
    layout.plot_bgcolor = 'rgba(0,0,0,0)';
    layout.legend = {
        bgcolor: 'rgba(0,0,0,0)'
    };
    layout.font= {
        color: '#7f7f7f'
    };

    Plotly.newPlot(chartDiv, data, layout, config);
}

function redrawChart(chartDiv) {
    var chartDiv = document.getElementById(chartDiv);

    config = {};
    config.displaylogo = false;
    config.modeBarButtonsToRemove = ['toImage', 'hoverClosestPie'];
    config.responsive = true;

    Plotly.newPlot(chartDiv, chartDiv.data, chartDiv.layout, config);
}

// Load graphs via json
document.addEventListener('LuminanceLoaded', function() {
    jQuery('[data-load_chart]').each(function(index, element) {
        ajax.get(jQuery(element).data('load_chart'), function(data) {
            try {
                data = JSON.parse(data);
                drawChart(jQuery(element).attr('id'), data);
            } catch (e) {
                // Could be bad JSON or just a Plotly bug...
            }
        });
    });
});
