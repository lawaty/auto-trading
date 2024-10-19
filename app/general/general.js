"use strict";

$(document).on('general-loaded', () => {
  if (!token) {
    redirect('login')
    return;
  }

  Store.loadComponent('widgets/sidebar', $("#sidebar"))
  // Store.loadComponent('widgets/header', $("#main-header"))

  AJAX.ajax({
    url: config.API + "/params",
    type: "GET",
    beforeSend: (xhr) => {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`)
    },
    complete: {
      200: (xhr) => {
        let is_live = xhr.parsed.globals.is_live
        $("[name=is_live][value=" + is_live + "]").prop('checked', true)

        $("#balance").val(xhr.parsed.balance)
        let money_format = xhr.parsed.globals.money_format
        $("[name=money_format][value=" + money_format + "]").prop('checked', true)
        if (money_format != 1)
          $("[name=money_format][value=" + money_format + "]").next().next().removeAttr('readonly')

        $("[name=money]").val(xhr.parsed.globals.money)
        $("#money").val(xhr.parsed.globals.money)
        $("#money-percent").val(xhr.parsed.globals.money / xhr.parsed.balance * 100)

        $("[name=account_id]").val(xhr.parsed.globals.account_id)
        $("[name=leave_percent]").val(xhr.parsed.globals.leave_percent)

        let no_revert_val = xhr.parsed.globals['no-revert'] ? 1 : 0
        $(`[name=no-revert][value=${no_revert_val}]`).prop('checked', true)
      }
    }
  })

  window.form = new Form($("#general_settings"))

  window.form.payload = function () {
    let form_data = new FormData(form.form[0])

    let closed_days_csv = form_data.getAll('closed_days[]').join(',');
    form_data.delete('closed_days[]');
    form_data.append('closed_days', closed_days_csv);

    let keys_csv = form_data.getAll('keys[]').join(',');
    form_data.delete('keys[]');
    form_data.append('keys', keys_csv);

    let secrets_csv = form_data.getAll('secrets[]').join(',');
    form_data.delete('secrets[]');
    form_data.append('secrets', secrets_csv);
    return form_data
  }

  window.form.setCallback({
    200: (xhr) => {
      Swal.fire({
        icon: "success",
        text: "Updated Successfully"
      })
    }
  })

  $("#money").on('input', function () {
    if ($(this).val() > $("#balance").val())
      $(this).val($("#balance").val())

    if ($(this).val() < 0)
      $(this).val(0)

    $("[name=money]").val($(this).val())
  })

  $("#money-percent").on('input', function () {
    if ($(this).val() > 100)
      $(this).val(100)

    else if ($(this).val() < 0)
      $(this).val(0)

    $("[name=money]").val($("#balance").val() * $(this).val() / 100)
  })

  $("[name=money_format]").on('change', function () {
    $("#money-row input[type=number]").attr('readonly', 'readonly')

    $(this).next().next().removeAttr('readonly')
  })
})

function addAPIKey(key = '', secret = '') {
  $("#keys").append(`
<li>
  <div class="form-row">
    <div class="mx-2" style="width: fit-content">
      <p style="color:#fff;">#</p>
      <i onclick="addAPIKey()" class="bi bi-plus-circle mr-1 text-success"></i>
      <i onclick="$(this).closest('li').remove()" class="bi bi-trash text-danger"></i>
    </div>
    <div class="col form-group">
      <label for="key">key</label>
      <input type="text" value="${key}" name="keys[]" class="form-control" placeholder="Key">
    </div>
    <div class="col form-group">
      <label for="key">Secret</label>
      <input type="text" value="${secret}" name="secrets[]" class="form-control" placeholder="Secret">
    </div>
  </div>
</li>`)
}