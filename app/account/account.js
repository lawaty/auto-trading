"use strict";

$(document).on('account-loaded', () => {
  if (!token) {
    redirect('login')
    return;
  }

  Store.loadComponent('widgets/sidebar', $("#sidebar"))
  // Store.loadComponent('widgets/header', $("#main-header"))
})