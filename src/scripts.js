var app = {
  canvas: document.querySelector('.canvas'),

  // Show error on download failed
  error: function (text) {
    var message = document.createElement('p');
    message.textContent = 'Sorry, something went wrong. Try again.';

    // Get header and append message
    var header = document.querySelector('.header');
    header.appendChild(message);

    // Show original message to console
    console.error('Error:',  text);
  },

  // Change land color
  color: function(element, cases) {
    cases = parseInt(cases);

    for (var i = 1; i < 6; i++) {
      var step = Math.pow(10, i);

      if (cases <= step) {
        return element.setAttribute('data-infected', i);
      }
    }
  },

  // Hide lands
  dimmer: function(region) {
    var lands = app.canvas.querySelectorAll('svg [data-infected]');

    for (var i = 0; i < lands.length; i++) {
      var selected = app.canvas.querySelector('svg [title="' + region + '"]');

      if (lands[i] !== selected) {
        lands[i].removeAttribute('data-infected');
      }
    }
  },

  // Table row click
  row: function(e) {
    e.preventDefault();

    // Check if row selected
    var selected = this.hasAttribute('data-infected');

    // Clear all other rows
    var rows = this.parentNode.querySelectorAll('tr');

    for (var i = 0; i < rows.length; i++) {
      rows[i].removeAttribute('data-infected');
    }

    // Paint lands
    app.paint();

    if (selected === false) {
      app.color(this, this.getAttribute('data-cases'))

      // Show only single land
      app.dimmer(this.getAttribute('data-region'));
    }
  },

  // Create table with data
  info: function () {
    var data = app.data;

    // Create table
    var table = document.createElement('table');

    // Create table header
    var thead = document.createElement('tr');
    table.appendChild(thead);

    // List of head titles
    var heads = ['Region', 'Cases', 'Deaths'];

    for (var i = 0; i < heads.length; i++) {
      var th = document.createElement('th');
      th.textContent = heads[i];
      thead.appendChild(th);
    }

    for (var i = 0; i < data.length; i++) {
      var tr = document.createElement('tr');
      tr.addEventListener('click', app.row);

      for (key in data[i]) {
        var td = document.createElement('td');
        td.textContent = data[i][key];
        tr.setAttribute('data-' + key, data[i][key]);
        tr.appendChild(td);
      }

      table.appendChild(tr);
    }

    // Create info block
    var info = document.createElement('div');
    info.classList.add('info');
    info.appendChild(table);

    app.canvas.insertBefore(info, app.canvas.firstChild);
  },

  // Color infected lands
  paint: function() {
    var data = app.data;

    for (var i = 0; i < data.length; i++) {
      var info = data[i];

      // Try to find land by title
      var land = app.canvas.querySelector('svg [title="' + info.region + '"]');

      if (land !== null) {
        app.color(land, info.cases);
      }
    }
  },

  // Draw elements
  draw: function (data) {
    app.data = data;

    if (app.data.length > 0) {
      app.info();
    }

    // Find and paint lands
    app.paint();

    // Show wrapper
    app.canvas.classList.add('canvas--visible');
  },

  // Load json
  request: function () {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/data.json?' + new Date().getTime(), true);
    xhr.responseType = 'json';

    // Add handler and send request
    xhr.onload = function () {
      if (xhr.status === 200) {
        return app.draw(xhr.response);
      }

      app.error("Wrong request status");
    }

    // Error handler
    xhr.onerror = function () {
      app.error("Can't load data.json file");
    }

    xhr.send();
  },

  // Init and send request
  init: function () {
    app.request();
  }
}

app.init();
