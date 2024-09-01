function listFilters() {
  let filters_options = ``;
  for (let filter of window.filters)
    filters_options += `<option value="${filter.sid}">${filter.name}</option>`

  $("[name=sid]").html(filters_options);
}

$(document).on('trade_settings-loaded', () => {
  Store.loadComponent('widgets/sidebar', "#sidebar")

  $("[add-run]").on('click', function () {
    let which = $(this).attr('add-run')
    let count = $(this).prev().find('.run-container').length
    addRun(which, count)
  })

  if (window.filters)
    listFilters()
  else
    $(document).on('filters-loaded', listFilters)

  AJAX.ajax({
    url: config.API + "/params/loss",
    type: "GET",
    beforeSend: (xhr) => {
      xhr.setRequestHeader('Authorization', 'Bearer ' + token)
    },
    complete: {
      200: (xhr) => {
        for (let sequence of xhr.parsed.buy.sequences)
          addSequence('buy-loss-sequences', sequence.percent, sequence.wait_time)

        $("#buy-loss-run [name=stop_loss_percent]").val(xhr.parsed.buy.stop_loss_percent)

        for (let sequence of xhr.parsed.short.sequences)
          addSequence('short-loss-sequences', sequence.percent, sequence.wait_time)

        $("#short-loss-run [name=stop_loss_percent]").val(xhr.parsed.short.stop_loss_percent)
      }
    }
  })


  AJAX.ajax({
    url: config.API + "/params/buy",
    type: "GET",
    beforeSend: (xhr) => {
      xhr.setRequestHeader('Authorization', 'Bearer ' + token)
    },
    complete: {
      200: (xhr) => {
        for (let i in xhr.parsed.runs) {
          let run = xhr.parsed.runs[i]
          addRun("buy", i, run)
        }

        if (xhr.parsed.pid) {
          $("#buy-start").hide()
          $("#buy-stop").show()
        }
      }
    }
  })

  AJAX.ajax({
    url: config.API + "/params/short",
    type: "GET",
    beforeSend: (xhr) => {
      xhr.setRequestHeader('Authorization', 'Bearer ' + token)
    },
    complete: {
      200: (xhr) => {
        for (let i in xhr.parsed.runs) {
          let run = xhr.parsed.runs[i]
          addRun("short", i, run)
        }

        if (xhr.parsed.pid) {
          $("#short-start").hide()
          $("#short-stop").show()
        }
      }
    }
  })

  window.buy_settings = new Form($("#buy_settings"))

  window.buy_settings.payload = function () {
    let runs = []
    $("#buy-runs .run-container").each(function (i, container) {
      let run = {
        number_of_trades: $(container).find('[name=number_of_trades]').val(),
        buying_power_percent: $(container).find('[name=buying_power_percent]').val(),
        trade_after: $(container).find('[name=trade_after]').val(),
        stop_loss_percent: $(container).find('[name=stop_loss_percent]').val(),
        sequences: [],
        sid: $(container).find('[name=sid]').val()
      }

      $(container).find('ul li').each(function (i, li) {
        run.sequences.push({
          wait_time: $(li).find("[name='wait_time[]']").val(),
          percent: $(li).find("[name='percent[]']").val(),
        })
      })

      runs.push(run)
    })

    return { 'runs': runs }
  }

  window.buy_settings.setCallback({
    200: (xhr) => {
      Swal.fire({
        icon: "success",
        text: "Updated Successfully!"
      })
    }
  })

  window.short_settings = new Form($("#short_settings"))

  window.short_settings.payload = function () {
    let runs = []
    $("#short-runs .run-container").each(function (i, container) {
      let run = {
        number_of_trades: $(container).find('[name=number_of_trades]').val(),
        buying_power_percent: $(container).find('[name=buying_power_percent]').val(),
        trade_after: $(container).find('[name=trade_after]').val(),
        stop_loss_percent: $(container).find('[name=stop_loss_percent]').val(),
        sequences: [],
        sid: $(container).find('[name=sid]').val()
      }

      $(container).find('ul li').each(function (i, li) {
        run.sequences.push({
          wait_time: $(li).find("[name='wait_time[]']").val(),
          percent: $(li).find("[name='percent[]']").val(),
        })
      })

      runs.push(run)
    })

    return { 'runs': runs }
  }

  window.short_settings.setCallback({
    200: (xhr) => {
      Swal.fire({
        icon: "success",
        text: "Updated Successfully!"
      })
    }
  })
  
  window.loss_settings = new Form($("#loss-settings"))
  window.loss_settings.payload = function () {
    let buy_sequences = []
    let short_sequences = []

    $("#buy-loss-sequences li").each(function (i, li) {
      buy_sequences.push({
        wait_time: $(li).find("[name='wait_time[]']").val(),
        percent: $(li).find("[name='percent[]']").val(),
      })
    })

    $("#short-loss-sequences li").each(function (i, li) {
      short_sequences.push({
        wait_time: $(li).find("[name='wait_time[]']").val(),
        percent: $(li).find("[name='percent[]']").val(),
      })
    })

    return {
      buy: {
        stop_loss_percent: $("#buy-loss-run [name=stop_loss_percent]").val(),
        sequences: buy_sequences
      }, short: {
        stop_loss_percent: $("#short-loss-run [name=stop_loss_percent]").val(),
        sequences: short_sequences
      }
    }
  }

  window.loss_settings.setCallback({
    200: (xhr) => {
      Swal.fire({
        icon: "success",
        text: "Updated Successfully!"
      })
    }
  })
})

function addRun(type, i, run = {
  number_of_trades: "",
  trade_after: "",
  buying_power_percent: 1,
  stop_loss_percent: "",
  sequences: [{
    percent: "",
    wait_time: ""
  }],
  sid: -1
}) {

  if (window.filters === undefined) {
    $(document).on('filters-loaded', () => addRun(type, i, run))
    return;
  }

  let filters_options = ``;
  for (let filter of filters)
    filters_options += `<option value="${filter.sid}" ${filter.sid == run.sid ? 'selected' : ''}>${filter.name}</option>`

  $("#" + type + "-runs").append(`
    <div class="run-container">
      <div class="form-row justify-content-between align-items-center">
        <h4>Run ${parseInt(i) + 1}</h4>
        <i class="bi bi-trash text-danger" onclick="deleteRun(this)"></i>
      </div>
      <div class="form-row">
        <div class="form-group col">
          <label for="${type}-${i}-number_of_trades">#Trades</label>
          <input class="form-control" value="${run.number_of_trades}" id="${type}-${i}-number_of_trades" name="number_of_trades" placeholder="e.g. 10">
        </div>

        <div class="form-group col">
          <label for="${type}-${i}-number_of_trades">Buying Power %</label>
          <input class="form-control" value="${run.buying_power_percent ?? 1}" id="${type}-${i}-buying_power_percent" name="buying_power_percent" placeholder="e.g. 10">
        </div>

        <div class="form-group col">
          <label for="start_after">Start After (in mins)</label>
          <input value="${run.trade_after}" class="form-control" id="start_after" name="trade_after" placeholder="e.g. 10">
        </div>

        <div class="form-group col">
          <label for="${type}_${i}_stop_loss">Stop Loss Percent</label>
          <input value="${run.stop_loss_percent}" class="form-control" id="b${type}${i}_stop_loss" name="stop_loss_percent" placeholder="e.g. 10">
        </div>
      </div>

      <h6>Sequences</h6>
      <ul id="${type}-${i}-sequences">
        <!-- Dynamically Loaded -->
      </ul>

      <h6>Filter</h6>
      <select class="form-control" name="sid">
        ${filters_options}
      </select>
    </div>
  `)

  $("[name=buying_power_percent]").on('input', function () {
    if ($(this).val() > 1)
      $(this).val(1)
    if ($(this).val() < 0)
      $(this).val(0);
  })

  for (let sequence of run.sequences)
    addSequence(type + '-' + i + '-sequences', sequence.percent, sequence.wait_time)

  if (!run.sequences.length)
    addSequence(type + '-' + i + '-sequences', 0, 0)
}

function deleteRun(i) {
  // if($(i).closest('ul').find('.run-container').length == 1) {
  //   Swal.fire({
  //     icon: "warning",
  //     title: "Impossible",
  //     text: "At least one run must be there"
  //   })
  //   return ;
  // }

  $(i).closest('.run-container').remove()
}

function addSequence(type, percent = '', wait_time = '') {
  $("#" + type).append(`
    <li>
      <div class="form-row">
        <div class="mx-2" style="width: fit-content">
          <p style="color:#fff;">#</p>
          <i onclick="addSequence('${type}')" class="bi bi-plus-circle mr-1 text-success"></i>
          <i onclick="removeStage(this)" class="bi bi-trash text-danger"></i>
        </div>
        <div class="col form-group">
          <label for="key">Wait Time</label>
          <input type="text" value="${wait_time}" name="wait_time[]" class="form-control" placeholder="Wait Time">
        </div>
        <div class="col form-group">
          <label for="key">Percent</label>
          <input type="text" value="${percent}" name="percent[]" class="form-control" placeholder="Threshold Percent">
        </div>
      </div>
    </li>`)
}

function removeStage(i) {
  if ($(i).closest('ul').find('li').length == 1) {
    Swal.fire({
      icon: 'warning',
      title: "Warning",
      text: "Each run must have at least one stage"
    })

    return;
  }

  $(i).closest('li').remove()
}

function runTrader(btn, type) {
  AJAX.ajax({
    url: config.API + '/start/' + type.charAt(0).toUpperCase() + type.slice(1),
    type: "POST",
    beforeSend: (xhr) => {
      xhr.setRequestHeader('Authorization', 'Bearer ' + token)
    },
    complete: {
      200: (xhr) => {
        Swal.fire("Auto-Trader has began its work!")
        $(btn).hide().next().show()
      }
    }
  })
}

function stopTrader(btn, type) {
  AJAX.ajax({
    url: config.API + '/stop/' + type.charAt(0).toUpperCase() + type.slice(1),
    type: "POST",
    beforeSend: (xhr) => {
      xhr.setRequestHeader('Authorization', 'Bearer ' + token)
    },
    complete: {
      200: (xhr) => {
        Swal.fire("Auto-Trader has stopped!")
        $(btn).hide().prev().show()
      }
    }
  })
}