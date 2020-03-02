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
    console.error('Error:', text);
  },

  // Change land color
  color: function (element, cases) {
    cases = parseInt(cases);

    for (var i = 1; i < 6; i++) {
      var step = Math.pow(10, i);

      if (cases <= step) {
        return element.setAttribute('data-infected', i);
      }
    }
  },

  // Hide lands
  dimmer: function (region) {
    var lands = app.canvas.querySelectorAll('svg [data-infected]');

    for (var i = 0; i < lands.length; i++) {
      var selected = app.canvas.querySelector('svg [title="' + region + '"]');

      if (lands[i] !== selected) {
        lands[i].removeAttribute('data-infected');
      }
    }
  },

  // Land click
  land: function(e) {
    e.preventDefault();
    var title = this.getAttribute('title');
    // try find tr by title
    var tr = document.querySelector('tr[data-region="' + title + '"]');
    if (tr) {
      // call row func with row and empty event
      app.row.call(tr, {preventDefault: function(){}});
      // try to scroll to tr in table
      if (typeof tr.scrollIntoView === 'function') {
        tr.scrollIntoView({block: 'center', behavior: 'smooth'});
      }
    }
  },

  // Table row click
  row: function (e) {
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

  // Create table header
  header: function(table, order) {
    var thead = document.createElement('thead');
    table.appendChild(thead);

    var tr = document.createElement('tr');
    thead.appendChild(tr);

    for (var i = 0; i < order.length; i++) {
      var td = document.createElement('td');
      td.textContent = order[i];
      tr.appendChild(td);
    }
  },

  // Create table footer
  footer: function(table, order, total) {
    var tfoot = document.createElement('tfoot');
    table.appendChild(tfoot);

    var tr = document.createElement('tr');
    tfoot.appendChild(tr);

    for (var i = 0; i < order.length; i++) {
      var td = document.createElement('td');
      tr.appendChild(td);

      if (order[i] === 'region') {
        td.textContent = 'total';
        continue;
      }

      td.textContent = total[order[i]];
    }
  },

  // Create table with data
  info: function () {
    var data = app.data;

    // Create table
    var table = document.createElement('table');

    // Table fields
    var order = ['region', 'cases', 'death'];

    // Create table header
    app.header(table, order);

    // Store total data
    var total = [];

    // Create tbody
    var tbody = document.createElement('tbody');
    table.appendChild(tbody);

    for (var i = 0; i < data.length; i++) {
      var tr = document.createElement('tr');
      tr.addEventListener('click', app.row);

      for (key in data[i]) {
        var td = document.createElement('td');
        td.textContent = data[i][key];
        tr.setAttribute('data-' + key, data[i][key]);
        tr.appendChild(td);

        // Skip region column
        if (key === 'region') {
          continue;
        }

        // Update total
        total[key] = parseInt(data[i][key]) + (total[key] || 0)
      }

      tbody.appendChild(tr);
    }

    // Create table footer
    app.footer(table, order, total);

    // Create info block
    var info = document.createElement('div');
    info.classList.add('info');
    info.appendChild(table);

    app.canvas.insertBefore(info, app.canvas.firstChild);
  },

  // Color infected lands
  paint: function () {
    var data = app.data;

    for (var i = 0; i < data.length; i++) {
      var info = data[i];

      // Try to find land by title
      var land = app.canvas.querySelector('svg [title="' + info.region + '"]');

      if (land !== null) {
        app.color(land, info.cases);
        land.addEventListener('click', app.land);
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
