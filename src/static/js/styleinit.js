(function () {
  const savedTheme = localStorage.getItem('theme') || 'grey';
  document.documentElement.setAttribute('data-theme', savedTheme);
})();