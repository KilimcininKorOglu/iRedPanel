/* iRedPanel UI behavior: confirmations, flash toasts, form helpers. */
(function () {
  'use strict';

  var data = JSON.parse(document.getElementById('app-data').textContent);
  var i18n = data.i18n;

  var Dialog = Swal.mixin({
    theme: 'dark',
    buttonsStyling: false,
    reverseButtons: true,
    customClass: {
      confirmButton: 'btn btn-danger',
      cancelButton: 'btn btn-outline-secondary'
    }
  });

  var Toast = Swal.mixin({
    theme: 'dark',
    toast: true,
    // The page header keeps its action buttons at the top right.
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true,
    didOpen: function (toast) {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });

  function showFlash() {
    if (data.flash.error) {
      Toast.fire({ icon: 'error', title: data.flash.error, timer: 8000 });
    } else if (data.flash.success) {
      Toast.fire({ icon: 'success', title: data.flash.success });
    }
  }

  /* A bulk button carries a JSON map from the selected action to its question.
     The key "*" applies to every non-empty action. */
  function bulkQuestion(form, submitter) {
    if (!submitter || !submitter.dataset.bulkConfirm) {
      return '';
    }
    var select = form.elements.namedItem('action');
    var action = select ? select.value : '';
    if (!action) {
      return '';
    }
    var questions = JSON.parse(submitter.dataset.bulkConfirm);
    return questions[action] || questions['*'] || '';
  }

  function confirmQuestion(form, submitter) {
    if (submitter && submitter.dataset.confirm) {
      return submitter.dataset.confirm;
    }
    return form.dataset.confirm || bulkQuestion(form, submitter);
  }

  function askAndSubmit(form, submitter, question) {
    Dialog.fire({
      icon: 'warning',
      title: i18n.confirmTitle,
      text: question,
      showCancelButton: true,
      confirmButtonText: i18n.confirm,
      cancelButtonText: i18n.cancel,
      focusCancel: true
    }).then(function (result) {
      if (!result.isConfirmed) {
        return;
      }
      form.dataset.confirmed = '1';
      if (submitter && submitter.form === form) {
        form.requestSubmit(submitter);
      } else {
        form.requestSubmit();
      }
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.dataset.confirmed === '1') {
      delete form.dataset.confirmed;
      return;
    }
    var question = confirmQuestion(form, event.submitter);
    if (question) {
      event.preventDefault();
      askAndSubmit(form, event.submitter, question);
    }
  });

  document.addEventListener('change', function (event) {
    var el = event.target;
    if (el.matches('[data-autosubmit]')) {
      el.form.requestSubmit();
    } else if (el.matches('input[data-select-all]')) {
      var scope = el.form || document;
      scope.querySelectorAll('input[type="checkbox"][name="' + CSS.escape(el.dataset.selectAll) + '"]').forEach(function (box) {
        box.checked = el.checked;
      });
    }
  });

  function randomInt(max) {
    var buf = new Uint32Array(1);
    crypto.getRandomValues(buf);
    return buf[0] % max;
  }

  function randomChar(chars) {
    return chars[randomInt(chars.length)];
  }

  function generatePassword() {
    var p = data.passwordPolicy || {};
    var sets = [
      [p.lowercase, 'abcdefghjkmnpqrstuvwxyz'],
      [p.uppercase, 'ABCDEFGHJKLMNPQRSTUVWXYZ'],
      [p.numbers, '23456789'],
      [p.special, '$@#%!^&*()-_+={}[]']
    ];
    var pool = '';
    var chars = [];
    sets.forEach(function (set) {
      if (set[0]) {
        chars.push(randomChar(set[1]));
        pool += set[1];
      }
    });
    pool = pool || sets[0][1] + sets[1][1] + sets[2][1];
    var length = Math.max(p.minLength || 8, 16);
    while (chars.length < length) {
      chars.push(randomChar(pool));
    }
    for (var i = chars.length - 1; i > 0; i--) {
      var j = randomInt(i + 1);
      var t = chars[i];
      chars[i] = chars[j];
      chars[j] = t;
    }
    return chars.join('');
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-generate-password]');
    if (!button) {
      return;
    }
    var password = generatePassword();
    var first = document.getElementById('password');
    var repeat = document.getElementById('password_repeat');
    if (first) {
      first.value = password;
      first.type = 'text';
      setTimeout(function () { first.type = 'password'; }, 3000);
    }
    if (repeat) {
      repeat.value = password;
    }
  });

  /* ---------- Account pickers ----------
     data-account-picker="single" turns an input into a one-address picker.
     data-account-picker="multi" turns a textarea into a multi-address picker and
     writes the selection back to the textarea, one address per line, so the
     server-side parsing stays the same. data-types limits the account types and
     data-domain limits the domain. A free-text address is always accepted. */

  function lookupUrl(field, query) {
    var params = new URLSearchParams({ q: query, types: field.dataset.types || 'user,alias,ml' });
    if (field.dataset.domain) {
      params.set('domain', field.dataset.domain);
    }
    return '/ajax/accounts?' + params.toString();
  }

  async function fetchAccounts(url) {
    var response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    var type = response.headers.get('Content-Type') || '';
    // An expired session redirects to the HTML login page.
    if (!response.ok || type.indexOf('application/json') !== 0) {
      throw new Error('Account lookup returned HTTP ' + response.status);
    }
    return response.json();
  }

  function pickerOptions(field, multi) {
    var failed = false;
    return {
      valueField: 'value',
      labelField: 'value',
      searchField: ['value', 'text'],
      create: true,
      createOnBlur: true,
      persist: false,
      clearAfterSelect: true,
      plugins: multi
        ? { remove_button: { title: i18n.pickerRemove } }
        : { clear_button: { title: i18n.pickerRemove } },
      maxItems: multi ? null : 1,
      splitOn: multi ? /[\s,;]+/ : null,
      loadThrottle: 250,
      placeholder: field.getAttribute('placeholder') || i18n.pickerPlaceholder,
      shouldLoad: function (query) {
        return query.length >= 2;
      },
      load: function (query, callback) {
        failed = false;
        fetchAccounts(lookupUrl(field, query)).then(callback, function (error) {
          failed = true;
          console.error(error);
          callback();
        });
      },
      render: {
        option: function (item, escape) {
          var type = item.type ? '<span class="badge text-bg-secondary ms-2">' + escape(i18n.types[item.type] || item.type) + '</span>' : '';
          return '<div class="d-flex align-items-center justify-content-between"><span>' + escape(item.text || item.value) + '</span>' + type + '</div>';
        },
        item: function (item, escape) {
          return '<div title="' + escape(item.text || item.value) + '">' + escape(item.value) + '</div>';
        },
        option_create: function (item, escape) {
          return '<div class="create">' + escape(i18n.pickerAdd) + ': <strong>' + escape(item.input) + '</strong></div>';
        },
        no_results: function () {
          return failed
            ? '<div class="no-results text-danger">' + i18n.pickerLoadFailed.replace(/</g, '&lt;') + '</div>'
            : '<div class="no-results">' + i18n.pickerNoResults.replace(/</g, '&lt;') + '</div>';
        }
      }
    };
  }

  function initMultiPicker(textarea) {
    var input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.value = textarea.value.split(/[\s,;]+/).filter(Boolean).join(',');
    if (textarea.id) {
      input.id = textarea.id + '-picker';
      var label = document.querySelector('label[for="' + CSS.escape(textarea.id) + '"]');
      if (label) {
        label.htmlFor = input.id;
      }
    }
    // A hidden required field blocks the submit without a visible message, so the picker takes the flag.
    input.required = textarea.required;
    textarea.required = false;
    textarea.classList.add('d-none');
    textarea.after(input);
    var picker = new TomSelect(input, pickerOptions(textarea, true));
    picker.on('change', function () {
      textarea.value = picker.items.join('\n');
    });
  }

  function initPickers() {
    document.querySelectorAll('[data-account-picker]').forEach(function (field) {
      if (field.dataset.accountPicker === 'multi') {
        initMultiPicker(field);
      } else {
        new TomSelect(field, pickerOptions(field, false));
      }
    });
  }

  initPickers();
  showFlash();
})();
