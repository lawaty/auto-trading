$(document).on('trade_settings-loaded', () => {
  Store.loadComponent('widgets/sidebar', "#sidebar")

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
          $("#buy-runs").append(`
            <h4>Run ${i}</h4>
            <div class="form-group">
              <label for="buy-${i}-number_of_trades">Number of Trades</label>
              <input class="form-control" value="${run.number_of_trades}" id="buy-${i}-number_of_trades" name="number_of_trades" placeholder="e.g. 10">
            </div>

            <div class="form-group">
              <label for="start_after">Start After (in mins)</label>
              <input value="${number.trade_after}" class="form-control" id="start_after" name="trade_after" placeholder="e.g. 10">
            </div>

            <label>Buy Sequences</label>
            <ul id="buy-${i}-sequences">
              <!-- Dynamically Loaded -->
            </ul>

            <div class="form-group">
              <label for="buy_${i}_stop_loss">Stop Loss Percent</label>
              <input value="${run.stop_loss_percent}" class="form-control" id="buy_${i}_stop_loss" name="stop_loss_percent" placeholder="e.g. 10">
            </div>
          `)

          for (let sequence of run.sequences)
            addSequence('buy-' + i + '-sequences', sequence.percent, sequence.wait_time)
        }
        
        if (xhr.parsed.pid) {
          $("#buy-start").hide()
          $("#buy-stop").show()
        }
      }
    }
  })

  // AJAX.ajax({
  //   url: config.API + "/params/short",
  //   type: "GET",
  //   beforeSend: (xhr) => {
  //     xhr.setRequestHeader('Authorization', 'Bearer ' + token)
  //   },
  //   complete: {
  //     200: (xhr) => {
  //       $("#sell_settings [name=trade_after]").val(xhr.parsed.trade_after)
  //       $("#sell_settings [name=stop_loss_percent]").val(xhr.parsed.stop_loss_percent)
  //       $("#sell_settings [name=number_of_trades]").val(xhr.parsed.number_of_trades)

  //       for (let sequence of xhr.parsed.sequences)
  //         addSequence('sell', sequence.percent, sequence.wait_time)


  //       if (xhr.parsed.pid) {
  //         $("#sell-start").hide()
  //         $("#sell-stop").show()
  //       }
  //     }
  //   }
  // })

  window.buy_settings = new Form($("#buy_settings"))

  window.buy_settings.payload = function () {
    let form_data = new FormData(buy_settings.form[0])

    let percent_csv = form_data.getAll('percent[]').join(',');
    form_data.delete('percent[]');
    form_data.append('percent', percent_csv);

    let wait_time_csv = form_data.getAll('wait_time[]').join(',');
    form_data.delete('wait_time[]');
    form_data.append('wait_time', wait_time_csv);

    return form_data
  }

  window.buy_settings.setCallback({
    200: (xhr) => {
      Swal.fire({
        icon: "success",
        text: "Updated Successfully!"
      })
    }
  })

  window.sell_settings = new Form($("#sell_settings"))

  window.sell_settings.payload = function () {
    let form_data = new FormData(sell_settings.form[0])

    let percent_csv = form_data.getAll('percent[]').join(',');
    form_data.delete('percent[]');
    form_data.append('percent', percent_csv);

    let wait_time_csv = form_data.getAll('wait_time[]').join(',');
    form_data.delete('wait_time[]');
    form_data.append('wait_time', wait_time_csv);

    return form_data
  }

  window.sell_settings.setCallback({
    200: (xhr) => {
      Swal.fire({
        icon: "success",
        text: "Updated Successfully!"
      })
    }
  })
})

function addSequence(which, percent = '', wait_time = '') {
  $("#" + which).append(`
<li>
  <div class="form-row">
    <div class="mx-2" style="width: fit-content">
      <p style="color:#fff;">#</p>
      <i onclick="addSequence('${which}')" class="bi bi-plus-circle mr-1 text-success"></i>
      <i onclick="$(this).closest('li').remove()" class="bi bi-trash text-danger"></i>
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