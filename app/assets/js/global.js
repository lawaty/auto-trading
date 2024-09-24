"use strict";

AJAX.default_handlers[400] = (xhr) => {
  Swal.fire({
    icon: "error",
    text: xhr.responseText
  })
}

AJAX.default_handlers[0] = (xhr) => {
  Swal.fire({
    icon: "error",
    text: "Something Went Wrong"
  })
}

AJAX.default_handlers[401] = (xhr) => {
  Swal.fire({
    icon: "warning",
    text: "Session Expired, Login again, please"
  }).then(() => {
    local.remove('user')
    redirect('login')
    return ;
  })
}

const token = local.get('token') ? local.get('token') : false

// ------ template pluginCustomization.js
$(document).on('component-loaded', () => {
})

window.filters = undefined
AJAX.ajax({
  url: config.API + '/stockMonitor/getFilters',
  type: "GET",
  complete: {
    200: (xhr) => {
      window.filters = xhr.parsed
      $(document).trigger('filters-loaded')
    }
  }
})