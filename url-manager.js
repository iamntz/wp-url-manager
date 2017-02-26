jQuery(document).ready(function($) {
  var wrapper = $('.ntz-link-manager-add');
  var searchBox = $('input', wrapper);
  var submit = $('button', wrapper);

  searchBox.autocomplete({
    source: function(request, response) {
      $.getJSON(ajaxurl, {
        ntz_nonce: ntz_url_manager.nonce,
        action: 'ntz_url_manager_search',
        query: encodeURIComponent(searchBox.val())
      }, response);
    },
    select: function(e, ui){
      copyIdToClipboard(ui.item.id, e.ctrlKey);
      return false;
    },
    minLength: 3
  }).autocomplete("instance")._renderItem = function(ul, item) {
    return $("<li>")
      .append("<div data-id='" + item.id + "'>" + item.url + "<br><small>" + (item.title || item.url) + "</small></div>")
      .appendTo(ul);
  };


  searchBox.on('keydown', function(e){
    if (e.keyCode === 13 && !$(searchBox.autocomplete('widget')).is(':visible')) {
      submit.trigger('click');
      return false;
    }
  });

  function copyToClipboard(value) {
    var oText = false;
    var bResult = false;
    $('.ntz-copied-to-clipboard').remove();
    try {
      oText = document.createElement("textarea");
      $(oText).addClass('clipboardCopier').val(value).insertAfter('body').focus();
      oText.select();
      document.execCommand("Copy");
      bResult = true;

      $('<div class="ntz-copied-to-clipboard"/>').html( 'Copied to clipboard:<br>' + value ).appendTo('body');

      window.setTimeout( function(){
        $('.ntz-copied-to-clipboard').fadeOut();
      }, 1000 );

    } catch ( e ) {
      console.log(e);
    }

    $(oText).remove();
    return bResult;
  }

  function copyIdToClipboard(id, isCtrl) {
    var prepend = '';
    if (isCtrl) {
      prepend = ntz_url_manager.redirect_to;
    }
    copyToClipboard(prepend + id);
  }

  submit.on('click', function() {
    var submittingClass = 'is-submitting loading-content';
    if (searchBox.val() === '' || submit.hasClass(submittingClass)) {
      return false;
    }
    submit.addClass(submittingClass);
    submit.append('<span class="spinner" />');
    $.post(ajaxurl, {
      ntz_nonce: ntz_url_manager.nonce,
      action: 'ntz_url_manager_add',
      url: encodeURIComponent(searchBox.val())
    }, function(response) {
      if (!response.length) {
        return;
      }
      searchBox.val('');
      submit.removeClass(submittingClass);
      submit.find('.spinner').remove();

      $(response).addClass('added').prependTo('.ntz-link-manager-list');
      window.setTimeout(function() {
        $('.ntz-link-manager-list li').removeClass('added');
      }, 1000);
    });
    return false;
  });


  var list = $('.ntz-link-manager-list');
  list.on('click', '.copy-id', function(e) {
    copyIdToClipboard($(e.currentTarget).attr('data-id'), e.ctrlKey);
  });

  list.on('click', '.delete-link', function(e){
    if (window.confirm('Really, really delete?')) {
      var li = $(e.currentTarget).closest('li').addClass('to-be-deleted');
      $.post(ajaxurl, {
        ntz_nonce: ntz_url_manager.nonce,
        action: 'ntz_url_manager_delete',
        id: $(e.currentTarget).attr('data-id')
      }, function(){
        li.slideUp();
      });
    }
    return false;
  });
});