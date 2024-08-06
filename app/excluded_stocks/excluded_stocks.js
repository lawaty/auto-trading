
$(document).on('excluded_stocks-loaded',function() {
    Store.loadComponent('widgets/sidebar', $("#sidebar"))
    function loadExcludedStocks() {
        AJAX.ajax({
          url: config.API + "/exstocks/Get",
          type: "GET",
          beforeSend: (xhr) => {
            xhr.setRequestHeader('Authorization', 'Bearer ' + token);
          },
          complete: {
            200: (xhr) => {
              let data = xhr.parsed;
              const cardContainer = $('#card-container');
              cardContainer.empty(); // Clear existing cards
              data.forEach(entity => {
                const card = `
                  <div class="card">
                    <button class="close-btn" data-id="${entity.id}">&times;</button>
                    <h5 class="card-title">${entity.symbol}</h5>
                    <p class="card-text">${entity.type}</p>
                  </div>
                `;
                cardContainer.append(card);
              });
              // Attach click event to each close button
              $('.close-btn').click(function() {
                const id = $(this).data('id');
                deleteExcludedStock(id);
              });
            }
          }
        });
      }
        function addExcludedStock(symbol, type) {
            AJAX.ajax({
      url: config.API + "/exstocks/Add",
      type: "POST",
      data: { symbol:symbol, type:type },
      beforeSend: (xhr) => {
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
      },
      complete: {
        200: (xhr) => {
            loadExcludedStocks()
        }
    },
      error: function(xhr, status, error) {
        console.error('Error adding excluded stock:', error);
      }
    });
  }
  loadExcludedStocks()

  $('#add-stock-btn').click(function() {
    const symbol = prompt('Enter stock symbol:');
    const type = prompt('Enter stock type:');
    if (symbol && type) {
      addExcludedStock(symbol, type);
    }
  });
  function deleteExcludedStock(id) {
    AJAX.ajax({
      url: config.API + `/exstocks/Delete`,
      type: "POST",
      data: {
        stock_id:id
      },
      beforeSend: (xhr) => {
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
      },
      complete: {
        200: () => {
          loadExcludedStocks();
        }
      },
      error: function(xhr, status, error) {
        console.error('Error deleting excluded stock:', error);
      }
    });
  }
  $('#search-btn').click(function() {
    const searchTerm = $('#search-stock').val().trim().toLowerCase();
    $('.card').each(function() {
      const cardSymbol = $(this).find('.card-title').text().trim().toLowerCase();
      if (cardSymbol.includes(searchTerm)) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
  });
  });

  