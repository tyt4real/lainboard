(function () {
  const savedTheme = localStorage.getItem('theme') || 'grey';
  document.documentElement.setAttribute('data-theme', savedTheme);

  const savedFont = localStorage.getItem('post-font') || 'Vera Mono';
  if (savedFont && savedFont !== 'default') {
    document.querySelectorAll('.post-body').forEach(postBody => {
      postBody.style.fontFamily = `'${savedFont}', sans-serif`;
    });
  }
})();