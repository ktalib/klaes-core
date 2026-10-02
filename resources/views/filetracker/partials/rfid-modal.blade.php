<div id="rfid-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-md">
    <div class="px-6 py-4 border-b">
      <h3 class="text-lg font-semibold">RFID Scan Results</h3>
      <p class="text-sm text-gray-500">The following files were detected by the RFID scanner</p>
    </div>
    <div class="p-4">
      <div class="rounded-md border overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b">
              <th class="text-left py-2 px-3 font-medium">RFID Tag</th>
              <th class="text-left py-2 px-3 font-medium">File ID</th>
              <th class="text-left py-2 px-3 font-medium">File Number</th>
              <th class="text-right py-2 px-3 font-medium">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-b">
              <td class="py-2 px-3 font-medium">RFID-00125478</td>
              <td class="py-2 px-3">TRK-2023-001</td>
              <td class="py-2 px-3 truncate max-w-[150px]">RES-2015-4859</td>
              <td class="py-2 px-3 text-right">
                <button class="text-blue-600 hover:text-blue-800 text-sm view-file-btn">View</button>
              </td>
            </tr>
            <tr class="border-b">
              <td class="py-2 px-3 font-medium">RFID-00125479</td>
              <td class="py-2 px-3">TRK-2023-002</td>
              <td class="py-2 px-3 truncate max-w-[150px]">RES-86-2244</td>
              <td class="py-2 px-3 text-right">
                <button class="text-blue-600 hover:text-blue-800 text-sm view-file-btn">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="px-6 py-4 border-t flex justify-end gap-2">
      <button id="close-rfid-modal" class="border rounded-md px-4 py-2 text-sm">Close</button>
      <button class="bg-blue-600 text-white rounded-md px-4 py-2 text-sm">Update Locations</button>
    </div>
  </div>
</div>
