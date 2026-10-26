(function () {
  'use strict';

  if (!document.querySelector('.dropzone.files-upload')) {
    return;
  }

  function wire(instance) {
    var container = instance.container;
    var element = container instanceof Element ? container : (container && container[0]);
    var dropzone = instance.dropzone;
    if (!element || !dropzone || instance.requiredToggled) {
      return;
    }
    var input = element.querySelector('input[type="file"]');
    if (!input || !input.required) {
      return;
    }
    instance.requiredToggled = true;
    var sync = function () {
      input.required = dropzone.getAcceptedFiles().length === 0;
    };
    ['success', 'error', 'removedfile', 'canceled'].forEach(function (event) {
      dropzone.on(event, sync);
    });
    sync();
  }

  function setup() {
    var filesField = window.GravForm && window.GravForm.FilesField;
    var instances = filesField && filesField.instances;
    if (!Array.isArray(instances)) {
      return false;
    }
    if (!instances.requiredPushHooked) {
      instances.requiredPushHooked = true;
      var push = Array.prototype.push;
      instances.push = function () {
        var result = push.apply(this, arguments);
        for (var i = 0; i < arguments.length; i++) {
          wire(arguments[i]);
        }
        return result;
      };
    }
    instances.forEach(wire);
    return true;
  }

  var attempts = 0;
  (function wait() {
    if (setup()) {
      return;
    }
    if (attempts++ < 40) {
      setTimeout(wait, 50);
    }
  })();
})();
