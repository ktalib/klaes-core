// Test script to verify routes are working
$(document).ready(function() {
    console.log('Testing routes...');
    
    // Test betterment route
    fetch(`${window.location.origin}/test-betterment/1`)
        .then(response => response.json())
        .then(data => {
            console.log('Betterment test route:', data);
        })
        .catch(error => {
            console.error('Betterment test route error:', error);
        });
    
    // Test final bill route
    fetch(`${window.location.origin}/test-final-bill/1`)
        .then(response => response.json())
        .then(data => {
            console.log('Final bill test route:', data);
        })
        .catch(error => {
            console.error('Final bill test route error:', error);
        });
    
    // Test app details route
    fetch(`${window.location.origin}/test-app-details/1/primary`)
        .then(response => response.json())
        .then(data => {
            console.log('App details test route:', data);
        })
        .catch(error => {
            console.error('App details test route error:', error);
        });
});