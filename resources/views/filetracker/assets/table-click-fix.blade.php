<script>
$(document).ready(function() {
  // Fix for table row clicks - add simple click handler
  $(document).on('click', 'tr.file-row', function(e) {
    e.preventDefault();
    const trackingId = $(this).data('tracking-id');
    if (trackingId) {
      console.log('Row clicked, tracking ID:', trackingId);
      
      // Highlight the selected row
      $('tr.file-row').removeClass('bg-gray-50');
      $(this).addClass('bg-gray-50');
      
      // Update sidebar with row data (simple fallback)
      const fileId = 'TRK-' + String(trackingId).padStart(6, '0');
      const status = $(this).find('.badge').text().trim();
      const location = $(this).find('td:nth-child(4)').text().trim();
      const handler = $(this).find('td:nth-child(5)').text().trim();
      
      // Update sidebar elements
      $('.file-details .px-6.py-4.border-b p.text-sm.text-gray-500').text(fileId);
      if (status) $('.file-details .px-6.py-4.border-b .badge').text(status);
      
      // Update location and handler if elements exist
      const locationEl = $('.file-details').find('p.text-sm.font-medium:contains("Current Location")').parent().find('p.text-sm').last();
      if (locationEl.length && location) locationEl.text(location);
      
      const handlerEl = $('.file-details').find('p.text-sm.font-medium:contains("Current Handler")').parent().find('p.text-sm').last();
      if (handlerEl.length && handler) handlerEl.text(handler);
      
      console.log('Sidebar updated with basic info');
    }
  });
});
</script>