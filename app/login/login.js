$(document).on('login-loaded', () => {
  if(token){
    redirect('home')
    return ;
  }

  window.login_form = new Form($("#login-form"))
  login_form.setCallback({
    200: (xhr) => {
      local.set('token', xhr.responseText)
      Swal.fire({icon: "success", text: "Welcome Again!"}).then(() => {
        redirect('home', true)
        return ;
      })
    },
    401: (xhr) => {
      Swal.fire({
        icon: "warning",
        title: "Invalid Credentials"
      })
    }
  })
})