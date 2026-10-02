(function configureTailwind() {
  if (typeof tailwind === 'undefined') {
    console.warn('Tailwind CDN not detected; header theme overrides skipped.');
    return;
  }

  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: {
            red: '#D42E12',
            green: '#107C41',
            yellow: '#FFBA08',
            black: '#212121',
          },
        },
      },
    },
  };
})();
