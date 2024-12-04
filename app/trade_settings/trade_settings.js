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
          addSequence('buy-loss-sequences', sequence.trigger, sequence.trail)

        $("#buy-loss-run [name=stop_loss_percent]").val(xhr.parsed.buy.stop_loss_percent)

        for (let sequence of xhr.parsed.short.sequences)
          addSequence('short-loss-sequences', sequence.trigger, sequence.trail)

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

        $("[submit=buy_settings]").removeAttr('disabled')
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

        $("[submit=short_settings]").removeAttr('disabled')
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
        sid: $(container).find('[name=sid]').val(),
        dir: $(container).find('[type=radio]:checked').val(),
        skip: $(container).find('[name=skip]').val()
      }

      $(container).find('ul li').each(function (i, li) {
        run.sequences.push({
          trigger: $(li).find("[name='trigger[]']").val(),
          trail: $(li).find("[name='trail[]']").val(),
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
        sid: $(container).find('[name=sid]').val(),
        dir: $(container).find('[type=radio]:checked').val(),
        skip: $(container).find('[name=skip]').val()
      }

      $(container).find('ul li').each(function (i, li) {
        run.sequences.push({
          trigger: $(li).find("[name='trigger[]']").val(),
          trail: $(li).find("[name='trail[]']").val(),
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
        trigger: $(li).find("[name='trigger[]']").val(),
        trail: $(li).find("[name='trail[]']").val(),
      })
    })

    $("#short-loss-sequences li").each(function (i, li) {
      short_sequences.push({
        trigger: $(li).find("[name='trigger[]']").val(),
        trail: $(li).find("[name='trail[]']").val(),
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
    trigger: "",
    trail: ""
  }],
  sid: -1,
  dir: 'ASC',
  skip: 0
}) {

  if (window.filters === undefined) {
    $(document).on('filters-loaded', () => addRun(type, i, run))
    return;
  }

  let filters_options = ``;
  for (let filter of filters)
    filters_options += `<option value="${filter.sid}" ${filter.sid == run.sid ? 'selected' : ''}>${filter.name}</option>`

  $("#" + type + "-runs").append(`
    <div class="run-container ${type}">
      <div class="form-row justify-content-between align-items-center">
        <h4>Run ${parseInt(i) + 1}</h4>
        <div>
        <i class="bi bi-clipboard" onclick="duplicate(this)"></i>
        <i class="bi bi-trash text-danger" onclick="deleteRun(this)"></i>
        </div>
      </div>
      <div class="form-row">
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
      <div class="form-row">
        <div class="form-group col">
          <label for="${type}-${i}-number_of_trades">#Trades</label>
          <input class="form-control" value="${run.number_of_trades}" id="${type}-${i}-number_of_trades" name="number_of_trades" placeholder="e.g. 10">
        </div>

        <div class="form-group col">
          <label for="${type}-${i}-skip">#stocks to be skipped</label>
          <input class="form-control" value="${run.skip ?? 0}" id="${type}-${i}-skip" name="skip" placeholder="e.g. 10">
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

      <div class="form-check-inline mt-2">
        <input class="form-check-input" type="radio" name="${type}-dir-${i}" id="${type}-${i}-asc" value="ASC" ${run.dir != 'DESC' ? 'checked' : ''}>
        <label class="form-check-label" for="${type}-${i}-asc">
          Ascending
        </label>
      </div>
      <div class="form-check-inline mt-2">
        <input class="form-check-input" type="radio" name="${type}-dir-${i}" id="${type}-${i}-desc" value="DESC" ${run.dir == 'DESC' ? 'checked' : ''}>
        <label class="form-check-label" for="${type}-${i}-desc">
          Descending
        </label>
      </div>
    </div>
  `)

  $("[name=buying_power_percent]").on('input', function () {
    if ($(this).val() > 1)
      $(this).val(1)
    if ($(this).val() < 0)
      $(this).val(0);
  })

  for (let sequence of run.sequences)
    addSequence(type + '-' + i + '-sequences', sequence.trigger, sequence.trail)

  if (!run.sequences.length)
    addSequence(type + '-' + i + '-sequences', 0, 0)
}

function duplicate(icon) {
  let container = $(icon).closest('.run-container')

  let sequences = [];
  container.find('ul li').each(function (i, li) {
    sequences.push({
      trigger: $(li).find("[name='trigger[]']").val(),
      trail: $(li).find("[name='trail[]']").val()
    })
  })

  addRun(container.hasClass('buy') ? 'buy' : "short", container.closest('ul').find('.run-container').length, {
    number_of_trades: container.find("[name=number_of_trades]").val(),
    trade_after: container.find("[name=trade_after]").val(),
    buying_power_percent: container.find("[name=buying_power_percent]").val(),
    stop_loss_percent: container.find("[name=stop_loss_percent]").val(),
    sequences: sequences,
    sid: container.find("[name=side]").val(),
    dir: container.find("[type=radio]:checked").val(),
    skip: container.find("[name=skip]").val()
  })

  Swal.fire({
    icon: 'success',
    text: 'Scroll down to see the added run',
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
  })
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

function addSequence(type, trigger = '', trail = '') {
  $("#" + type).append(`
    <li>
      <div class="form-row">
        <div class="mx-2" style="width: fit-content">
          <p style="color:#fff;">#</p>
          <i onclick="addSequence('${type}')" class="bi bi-plus-circle mr-1 text-success"></i>
          <i onclick="removeStage(this)" class="bi bi-trash text-danger"></i>
        </div>
        <div class="col form-group">
          <label for="key">Trail Trigger Percent</label>
          <input type="text" value="${trigger}" name="trigger[]" class="form-control" placeholder="e.g. 0.01">
        </div>
        <div class="col form-group">
          <label for="key">Trail Percent</label>
          <input type="text" value="${trail}" name="trail[]" class="form-control" placeholder="e.g. 0.009">
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
  if (!confirm(`Are you sure your want to stop the ${type} processes`))
    return;

  AJAX.ajax({
    url: config.API + '/stop/' + type.charAt(0).toUpperCase() + type.slice(1),
    type: "POST",
    data: {
      graceful: confirm(`Do you want to close all ${type} positons as well ? Note that unclosed positions become untrackable by the system.`) ? 1 : 0,
      debug: true
    },
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