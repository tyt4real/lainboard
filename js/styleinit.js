(function () {
  const savedTheme = localStorage.getItem('theme') || 'yotsuba';
  document.documentElement.setAttribute('data-theme', savedTheme);
})();