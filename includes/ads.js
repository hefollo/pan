(function () {
  var current = document.currentScript;
  var base = current && current.src ? current.src.replace(/ads\.js(?:\?.*)?$/, '') : 'includes/';
  var script = document.createElement('script');
  script.src = base + 'ads.php?_=' + Date.now();
  script.async = false;
  script.setAttribute('data-mpimg-loader', 'ads');
  (document.head || document.documentElement).appendChild(script);
})();
