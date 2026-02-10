// Font management system
class FontManager {
  constructor() {
    this.fontSelect = document.getElementById('font-select');
    this.init();
  }

  init() {
    // Set the dropdown to match current font
    const currentFont = localStorage.getItem('post-font') || 'Vera Mono';
    this.fontSelect.value = currentFont;
    this.applyFont(currentFont);

    // Listen for font changes
    this.fontSelect.addEventListener('change', (e) => {
      this.setFont(e.target.value);
    });
  }

  setFont(fontName) {
    // Save to localStorage
    localStorage.setItem('post-font', fontName);
    this.applyFont(fontName);
  }

  applyFont(fontName) {
    const postBodies = document.querySelectorAll('.post-body');
    postBodies.forEach(postBody => {
      if (fontName === 'default') {
        postBody.style.fontFamily = ''; // Reset to default CSS
      } else {
        postBody.style.fontFamily = `'${fontName}', sans-serif`;
      }
    });
  }
}

// Initialize font manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new FontManager();
});

