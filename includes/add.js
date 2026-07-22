(function () {
  var current = document.currentScript;
  var base = current && current.src ? current.src.replace(/add\.js(?:\?.*)?$/, '') : 'includes/';
  var script = document.createElement('script');
  script.src = base + 'announcement.php?_=' + Date.now();
  script.async = false;
  script.setAttribute('data-mpimg-loader', 'announcement');
  (document.head || document.documentElement).appendChild(script);
})();
