document.addEventListener('DOMContentLoaded', function() {
  const swup = new Swup({
    containers: ['#agnostic-content'],
    animationSelector: '[class*="transition-"]',
    animateHistoryBrowsing: true,
  });

  // Make swup a global variable
  window.swup = swup;

  // Define our custom animation
  const pageTransition = (from, to, trigger) => {
    return new Promise((resolve) => {
      const oldContent = from.querySelector('#agnostic-content');
      const newContent = to.querySelector('#agnostic-content');
      
      // Set initial states
      oldContent.style.position = 'absolute';
      oldContent.style.width = '100%';
      newContent.style.position = 'absolute';
      newContent.style.width = '100%';
      newContent.style.opacity = '0';
      newContent.style.transform = 'translateY(20px)';

      // Animate out the old content
      anime({
        targets: oldContent,
        opacity: 0,
        translateY: -20,
        easing: 'easeInOutQuad',
        duration: 300
      });

      // Animate in the new content
      anime({
        targets: newContent,
        opacity: 1,
        translateY: 0,
        easing: 'easeOutQuad',
        duration: 300,
        delay: 150,
        complete: () => {
          // Reset styles after animation
          oldContent.style.position = '';
          oldContent.style.width = '';
          newContent.style.position = '';
          newContent.style.width = '';
          resolve();
        }
      });
    });
  };

  // Register the custom animation with Swup
  swup.hooks.on('animation:in', pageTransition);
  swup.hooks.on('animation:out', pageTransition);

  // Reinitialize scripts after Swup content is replaced
  swup.hooks.on('page:view', function() {
    window.parent.console.log('Page view event triggered');
    initializeScripts();
    initializeFormSubmission();
  });

  // Log any Swup errors
  swup.hooks.on('error', (error) => {
    window.parent.console.error('Swup error:', error);
  });
});

function initializeScripts() {
  window.parent.console.log('Initializing scripts');
  // any scripts you want to run on every page load or after swup replaces the content
}

// Call initializeScripts on initial page load
initializeScripts();

// Check if the main container exists
if (!document.getElementById('agnostic-content')) {
  window.parent.console.warn('The main container #agnostic-content is missing. Swup requires this element to work properly.');
}