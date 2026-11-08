(function () {
  'use strict';

  var FIELD = 'belege';

  function configure(instance) {
    var container = instance.container;
    var element = container instanceof Element ? container : (container && container[0]);
    var dropzone = instance.dropzone;
    if (!element || !dropzone || element.getAttribute('data-file-field-name') !== FIELD) {
      return;
    }

    dropzone.options.maxFilesize = null;
    dropzone.options.transformFile = function (file, done) {
      if (!/^image\/(png|jpe?g)$/i.test(file.type)) {
        done(file);
        return;
      }

      var maxWidth = this.options.resizeWidth || 1920;
      var maxHeight = this.options.resizeHeight || 1920;
      var quality = this.options.resizeQuality || 0.8;
      var reader = new FileReader();

      reader.onload = function () {
        var image = new Image();
        image.onload = function () {
          var width = image.naturalWidth;
          var height = image.naturalHeight;
          if (!width || !height) {
            done(file);
            return;
          }

          var scale = Math.min(maxWidth / width, maxHeight / height, 1);
          var targetWidth = Math.max(1, Math.round(width * scale));
          var targetHeight = Math.max(1, Math.round(height * scale));
          var canvas = document.createElement('canvas');
          canvas.width = targetWidth;
          canvas.height = targetHeight;

          var context = canvas.getContext('2d');
          context.fillStyle = '#ffffff';
          context.fillRect(0, 0, targetWidth, targetHeight);
          context.drawImage(image, 0, 0, targetWidth, targetHeight);

          if (!canvas.toBlob) {
            done(file);
            return;
          }

          canvas.toBlob(function (blob) {
            if (!blob) {
              done(file);
              return;
            }
            var name = /\.png$/i.test(file.name) ? file.name.replace(/\.png$/i, '.jpg') : file.name;
            if (file.upload) {
              file.upload.filename = name;
            }
            done(new File([blob], name, { type: 'image/jpeg' }));
          }, 'image/jpeg', quality);
        };
        image.onerror = function () {
          done(file);
        };
        image.src = reader.result;
      };
      reader.onerror = function () {
        done(file);
      };
      reader.readAsDataURL(file);
    };
  }

  function setup() {
    var filesField = window.GravForm && window.GravForm.FilesField;
    var instances = filesField && filesField.instances;
    if (!Array.isArray(instances)) {
      return false;
    }
    if (!instances.photoResizeHooked) {
      instances.photoResizeHooked = true;
      var previousPush = instances.push;
      instances.push = function () {
        var result = previousPush.apply(this, arguments);
        for (var i = 0; i < arguments.length; i++) {
          configure(arguments[i]);
        }
        return result;
      };
    }
    instances.forEach(configure);
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
