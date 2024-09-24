"use strict";

$(document).on('home-loaded', () => {
  let fetchLogs = () => {
    AJAX.ajax({
      url: config.API + "/operations/Buy",
      type: "GET",
      beforeSend: (xhr) => {
        xhr.setRequestHeader('Authorization', 'Bearer ' + token)
      },
      complete: {
        200: (xhr) => {
          buy_table.reset()
          if (!xhr.parsed.length)
            buy_table.empty()

          for (let transaction of xhr.parsed) {
            if (!transaction.symbol)
              transaction = transaction['0']

            buy_table.addRecord([transaction.symbol, transaction.change, transaction.operation, transaction.price, transaction.time])
          }

          buy_table.filter($("#buy-search").val())
        }
      }
    })

    AJAX.ajax({
      url: config.API + "/operations/Short",
      type: "GET",
      beforeSend: (xhr) => {
        xhr.setRequestHeader('Authorization', 'Bearer ' + token)
      },
      complete: {
        200: (xhr) => {
          short_table.reset()
          if (!xhr.parsed.length)
            short_table.empty()

          for (let transaction of xhr.parsed) {
            if (!transaction.symbol)
              transaction = transaction['0']

            short_table.addRecord([transaction.symbol, transaction.change, transaction.operation, transaction.price, transaction.time])
          }

          short_table.filter($("#short-search").val())
        },
        500: (xhr) => { }
      }
    })
  }

  if (!token) {
    redirect('login')
    return;
  }

  Store.loadComponent('widgets/sidebar', $("#sidebar"))
  // Store.loadComponent('widgets/header', $("#main-header"))

  Store.loadJS('/' + config.APP + '/home/chart.js')

  window.short_table = new Table("#short-table");
  window.buy_table = new Table("#buy-table");

  short_table.createTD = function (col, value, attrs = '') {
    let data_value = Regex.GENERIC.test(value) ? `data-value="${value}"` : ''
    return `<td class="col-${col}" ${data_value} ${attrs}><h6 class="fw-semibold mb-0">${value}</h6></td>`
  }
  buy_table.createTD = function (col, value, attrs = '') {
    let data_value = Regex.GENERIC.test(value) ? `data-value="${value}"` : ''
    return `<td class="col-${col}" ${data_value} ${attrs}><h6 class="fw-semibold mb-0">${value}</h6></td>`
  }

  fetchLogs()
  window.logs_interval = setInterval(fetchLogs, 30000);

  $("#buy-search").on('input', function () {
    buy_table.filter($(this).val())
  })

  $("#short-search").on('input', function () {
    short_table.filter($(this).val())
  })
})

$(document).on('component-loaded', () => {
    if (Store.state.current_page != 'home' && window.logs_interval)
      clearInterval(window.logs_interval)
})