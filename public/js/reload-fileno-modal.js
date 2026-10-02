// Force script reload by changing query string
const cacheBuster = Math.floor(Math.random() * 10000000);
const scriptUrl = `/js/global-fileno-modal.js?v=${cacheBuster}`;

// Create a new script element
const newScript = document.createElement('script');
newScript.src = scriptUrl;
newScript.onload = function() {
    console.log('Tab script reloaded with cache busting');
    // Initialize after reload if needed
    if (typeof GlobalFileNoModal !== 'undefined') {
        GlobalFileNoModal.init();
    }
};

// Find the existing script
const existingScript = document.querySelector('script[src*="global-fileno-modal.js"]');

if (existingScript) {
    // Replace the existing script
    existingScript.parentNode.replaceChild(newScript, existingScript);
} else {
    // Just append the new script if we can't find the old one
    document.head.appendChild(newScript);
}
