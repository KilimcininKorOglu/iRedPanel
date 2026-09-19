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

  showFlash();
})();
